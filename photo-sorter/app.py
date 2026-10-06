"""
PhotoSorter - local web app
- You define events + photo-number ranges (sections) in the browser.
- When an SD card is inserted, photos are copied and sorted into
  <library>/<date event>/<NN section>/ by the number in the filename.
- RAW files get an XMP sidecar with keywords (event, section) so
  Lightroom Classic picks them up on import -> smart collections.
- Finished Lightroom exports in <event>/_Export/<NN section>/ are uploaded to the
  website gallery automatically (uploader.py).
Run:  python app.py   then open http://localhost:5055
"""
import json
import os
import re
import shutil
import string
import threading
import time
import uuid
from datetime import datetime
from pathlib import Path
from xml.sax.saxutils import escape

from flask import Flask, jsonify, render_template, request

from uploader import Uploader

BASE = Path(__file__).resolve().parent
CONFIG = json.loads((BASE / "config.json").read_text())
if (BASE / "config.local.json").exists():  # optional private overrides (API key), never shared
    CONFIG.update(json.loads((BASE / "config.local.json").read_text()))
CONFIG.setdefault("export_folder_name", "_Export")
CONFIG.setdefault("auto_upload", True)
CONFIG.setdefault("upload_poll_seconds", 30)
EVENTS_FILE = BASE / "events.json"
LIBRARY = Path(os.path.expanduser(CONFIG["library_root"]))
PHOTO_EXT = {e.lower() for e in CONFIG["photo_extensions"]}
RAW_EXT = {e.lower() for e in CONFIG["raw_extensions"]}

app = Flask(__name__)
lock = threading.Lock()
log_lines = []          # shown in the web UI
import_running = False


# ---------- storage ----------
def load_events():
    if EVENTS_FILE.exists():
        return json.loads(EVENTS_FILE.read_text())
    return []


def save_events(events):
    EVENTS_FILE.write_text(json.dumps(events, indent=2, ensure_ascii=False))


def log(msg):
    line = f"{datetime.now():%H:%M:%S}  {msg}"
    print(line)
    log_lines.append(line)
    del log_lines[:-300]


def safe_name(s):
    return re.sub(r'[<>:"/\\|?*]+', "-", s).strip() or "untitled"


# ---------- validation ----------
def validate_sections(sections):
    """Return list of human-readable problems (overlaps, bad ranges)."""
    problems = []
    clean = []
    for s in sections:
        try:
            a, b = int(s["start"]), int(s["end"])
        except (KeyError, ValueError, TypeError):
            problems.append(f"'{s.get('name', '?')}' has an invalid range")
            continue
        if a > b:
            problems.append(f"'{s['name']}' starts after it ends ({a} > {b})")
        clean.append((a, b, s["name"]))
    clean.sort()
    for (a1, b1, n1), (a2, b2, n2) in zip(clean, clean[1:]):
        if a2 <= b1:
            problems.append(f"'{n1}' and '{n2}' overlap ({a2}-{min(b1, b2)})")
    return problems


# ---------- photo number logic ----------
def photo_number(filename):
    """DSC_0254.NEF -> 254, IMG_1234.CR3 -> 1234. Uses the last digit group."""
    nums = re.findall(r"\d+", Path(filename).stem)
    return int(nums[-1]) if nums else None


def find_section(event, num):
    for idx, s in enumerate(event["sections"], start=1):
        if int(s["start"]) <= num <= int(s["end"]):
            return idx, s["name"]
    return None, None


def write_xmp(target, event_name, section):
    """Minimal XMP sidecar Lightroom reads on import (RAW files only).
    Creates keywords  <Event>  and  <Event> > <Section>."""
    li = lambda ks: "".join(f"<rdf:li>{escape(k)}</rdf:li>" for k in ks)
    flat = li([event_name, section])
    hier = li([event_name, f"{event_name}|{section}"])
    xmp = f"""<x:xmpmeta xmlns:x="adobe:ns:meta/">
 <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:lr="http://ns.adobe.com/lightroom/1.0/">
   <dc:subject><rdf:Bag>{flat}</rdf:Bag></dc:subject>
   <lr:hierarchicalSubject><rdf:Bag>{hier}</rdf:Bag></lr:hierarchicalSubject>
  </rdf:Description>
 </rdf:RDF>
</x:xmpmeta>"""
    target.with_suffix(".xmp").write_text(xmp, encoding="utf-8")


def import_card(card_root, event):
    global import_running
    with lock:
        if import_running:
            log("Import already running - skipped.")
            return
        import_running = True
    try:
        problems = validate_sections(event["sections"])
        if problems:
            log("Import stopped. Fix these first: " + "; ".join(problems))
            return
        event_dir = LIBRARY / safe_name(f"{event.get('date', '')} {event['name']}")
        dcim = Path(card_root) / "DCIM"
        files = [p for p in dcim.rglob("*") if p.is_file() and p.suffix.lower() in PHOTO_EXT]
        log(f"Card {card_root}: {len(files)} photos found -> {event['name']}")
        copied = skipped = unassigned = 0
        for f in sorted(files):
            num = photo_number(f.name)
            idx, section = find_section(event, num) if num is not None else (None, None)
            if section:
                sub = event_dir / f"{idx:02d} {safe_name(section)}"
            else:
                sub = event_dir / "_Unassigned"
                unassigned += 1
            sub.mkdir(parents=True, exist_ok=True)
            target = sub / f.name
            if target.exists() and target.stat().st_size == f.stat().st_size:
                skipped += 1
                continue
            shutil.copy2(f, target)
            if f.suffix.lower() in RAW_EXT:
                write_xmp(target, event["name"], section or "Unassigned")
            copied += 1
        log(f"Done: {copied} copied, {skipped} already there, {unassigned} outside all ranges.")
        log(f"Folder: {event_dir}")
    except Exception as e:  # keep the watcher alive
        log(f"Import error: {e}")
    finally:
        import_running = False


# ---------- SD card watcher ----------
def mounted_cards():
    roots = []
    if os.name == "nt":  # Windows: check drive letters
        roots = [f"{d}:\\" for d in string.ascii_uppercase[3:] if Path(f"{d}:\\DCIM").exists()]
    else:
        for base in CONFIG["card_mount_roots"]:
            b = Path(os.path.expanduser(base))
            if b.exists():
                roots += [str(p) for p in b.iterdir() if (p / "DCIM").is_dir()]
    return set(roots)


def watcher():
    seen = mounted_cards()  # ignore cards already inserted at startup
    if seen:
        log(f"Cards already mounted (not auto-imported): {', '.join(seen)}")
    while True:
        time.sleep(CONFIG["poll_seconds"])
        now = mounted_cards()
        for card in now - seen:
            log(f"SD card detected: {card}")
            if not CONFIG["auto_import_on_card_insert"]:
                continue
            active = next((e for e in load_events() if e.get("active")), None)
            if active:
                threading.Thread(target=import_card, args=(card, active), daemon=True).start()
            else:
                log("No active event set - choose one in the web page, then press Import.")
        seen = now


# ---------- website upload ----------
uploader = Uploader(BASE, CONFIG, LIBRARY, safe_name, log, load_events)


def sync_event_online(ev):
    if not uploader.enabled:
        return
    try:
        res = uploader.sync_event(ev)
        log(f"Website: '{ev['name']}' synced ({res.get('status')}).")
    except Exception as e:
        log(f"Website sync for '{ev['name']}' failed: {e}")


# ---------- API ----------
@app.get("/")
def index():
    return render_template("index.html")


@app.get("/api/events")
def get_events():
    return jsonify(load_events())


@app.post("/api/events")
def upsert_event():
    ev = request.get_json()
    ev.setdefault("id", uuid.uuid4().hex[:8])
    ev.setdefault("sections", [])
    ev.setdefault("world", "hochzeit")   # hochzeit | pferde  (which part of the website)
    ev.setdefault("mode", "free")        # free = paid client downloads all | sale = watermarked, pay per photo
    ev.setdefault("upload", True)
    problems = validate_sections(ev["sections"])
    events = load_events()
    if ev.get("active"):
        for e in events:
            e["active"] = False
    events = [e for e in events if e["id"] != ev["id"]] + [ev]
    save_events(events)
    if not problems:
        threading.Thread(target=sync_event_online, args=(ev,), daemon=True).start()
    return jsonify({"event": ev, "problems": problems})


@app.delete("/api/events/<eid>")
def delete_event(eid):
    save_events([e for e in load_events() if e["id"] != eid])
    return jsonify({"ok": True})


@app.get("/api/cards")
def cards():
    return jsonify(sorted(mounted_cards()))


@app.post("/api/import")
def manual_import():
    data = request.get_json()
    ev = next((e for e in load_events() if e["id"] == data["event_id"]), None)
    if not ev:
        return jsonify({"error": "Event not found"}), 404
    threading.Thread(target=import_card, args=(data["card"], ev), daemon=True).start()
    return jsonify({"ok": True})


@app.get("/api/config")
def get_config():
    return jsonify({"upload_enabled": uploader.enabled, "website_url": CONFIG.get("website_url", ""),
                    "export_folder_name": CONFIG["export_folder_name"], "library": str(LIBRARY)})


@app.get("/api/events/<eid>/online")
def online_status(eid):
    ev = next((e for e in load_events() if e["id"] == eid), None)
    if not ev:
        return jsonify({"error": "Event not found"}), 404
    out = {"enabled": uploader.enabled, "export_dir": str(uploader.export_dir(ev)), "local": uploader.local_counts(ev),
           "progress": uploader.progress.get(eid), "busy": eid in uploader.busy, "online": None, "error": ""}
    if uploader.enabled:
        try:
            out["online"] = uploader.status(eid)
            out["online"].pop("files", None)
        except Exception as e:
            out["error"] = str(e)
    return jsonify(out)


@app.post("/api/events/<eid>/upload")
def upload_now(eid):
    ev = next((e for e in load_events() if e["id"] == eid), None)
    if not ev or not uploader.enabled:
        return jsonify({"error": "Event not found or website not configured"}), 400
    force = bool((request.get_json(silent=True) or {}).get("force"))
    threading.Thread(target=lambda: (sync_event_online(ev), uploader.sync_exports(ev, force=force)), daemon=True).start()
    return jsonify({"ok": True})


@app.post("/api/events/<eid>/publish")
def publish(eid):
    if not uploader.enabled:
        return jsonify({"error": "Website not configured"}), 400
    try:
        res = uploader.publish(eid, (request.get_json() or {}).get("published"))
        res.pop("files", None)
        log(f"Website: gallery {'published' if res['status'] == 'published' else 'taken offline'}.")
        return jsonify(res)
    except Exception as e:
        return jsonify({"error": str(e)}), 502


@app.get("/api/log")
def get_log():
    return jsonify({"lines": log_lines, "running": import_running})


if __name__ == "__main__":
    LIBRARY.mkdir(parents=True, exist_ok=True)
    threading.Thread(target=watcher, daemon=True).start()
    threading.Thread(target=uploader.loop, daemon=True).start()
    log(f"Library: {LIBRARY}")
    log(f"Website upload: {'on → ' + CONFIG['website_url'] if uploader.enabled else 'off (set website_url + api_key in config.json)'}")
    app.run(host="127.0.0.1", port=CONFIG["port"], debug=False)
