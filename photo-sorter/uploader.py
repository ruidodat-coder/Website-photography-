"""
Uploads finished Lightroom exports to the website (ruidodat.com → Galerien).

<library>/<date event>/_Export/<NN section>/<file>.jpg  →  online gallery, section <section>

- Event sync: the event (name, date, client, world, mode, sections) is pushed when saved.
- Watcher: polls every event's _Export folder; new or changed JPGs are uploaded,
  photos removed from _Export are removed online. upload_state.json remembers what is
  online (filename → sha256) so restarts never re-upload.
- Works offline: if the website can't be reached, it simply tries again later.
"""
import hashlib
import json
import re
import threading
import time
from pathlib import Path

import requests

UA = "Mozilla/5.0 (rd-photosorter; +https://ruidodat.com)"  # host firewall blocks unknown clients


class Uploader:
    def __init__(self, base_dir, config, library, safe_name, log, load_events):
        self.base_dir = Path(base_dir)
        self.cfg = config
        self.library = library
        self.safe_name = safe_name
        self.log = log
        self.load_events = load_events
        self.state_file = self.base_dir / "upload_state.json"
        self.state = json.loads(self.state_file.read_text()) if self.state_file.exists() else {}
        self.lock = threading.Lock()
        self.busy = set()  # event ids currently uploading
        self.progress = {}  # event id -> {"done": n, "total": n, "error": str}

    # ---------- config ----------
    @property
    def enabled(self):
        return bool(self.cfg.get("website_url") and self.cfg.get("api_key"))

    def url(self, path):
        return self.cfg["website_url"].rstrip("/") + "/wp-json/rd/v1/admin" + path

    def export_dir(self, ev):
        return self.library / self.safe_name(f"{ev.get('date', '')} {ev['name']}") / self.cfg.get("export_folder_name", "_Export")

    # ---------- http with retry ----------
    def call(self, method, path, retries=5, **kw):
        headers = {"X-RD-Key": self.cfg["api_key"], "User-Agent": UA}
        delay = 2
        for attempt in range(retries):
            try:
                r = requests.request(method, self.url(path), headers=headers, timeout=(15, 300), **kw)
                if r.status_code in (429, 500, 502, 503, 504) and attempt < retries - 1:
                    raise requests.ConnectionError(f"HTTP {r.status_code}")
                try:
                    data = r.json()
                except ValueError:
                    data = {"message": r.text[:200]}
                if r.status_code >= 400:
                    raise RuntimeError(data.get("message") or f"HTTP {r.status_code}")
                return data
            except (requests.ConnectionError, requests.Timeout) as e:
                if attempt == retries - 1:
                    raise RuntimeError(f"Website not reachable ({e})")
                # files opened by the caller must be rewound before a retry
                for f in (kw.get("files") or {}).values():
                    if hasattr(f[1], "seek"):
                        f[1].seek(0)
                time.sleep(delay)
                delay *= 2

    # ---------- event sync / publish / status ----------
    def sync_event(self, ev):
        body = {k: ev.get(k) for k in ("name", "date", "client", "world", "mode")}
        body["sections"] = [{"name": s["name"], "start": int(s["start"]), "end": int(s["end"])} for s in ev.get("sections", [])]
        return self.call("PUT", f"/events/{ev['id']}", json=body)

    def status(self, ev_id):
        return self.call("GET", f"/events/{ev_id}", retries=2)

    def publish(self, ev_id, on):
        return self.call("POST", f"/events/{ev_id}/publish", json={"published": bool(on)})

    # ---------- exports ----------
    @staticmethod
    def photo_number(filename):
        nums = re.findall(r"\d+", Path(filename).stem)
        return int(nums[-1]) if nums else None

    @staticmethod
    def sha256(path):
        h = hashlib.sha256()
        with open(path, "rb") as f:
            for chunk in iter(lambda: f.read(1 << 20), b""):
                h.update(chunk)
        return h.hexdigest()

    def scan(self, ev):
        """Local export files: filename -> (path, section folder name)."""
        root = self.export_dir(ev)
        out = {}
        if not root.is_dir():
            return root, out
        for p in sorted(root.rglob("*")):
            if p.is_file() and p.suffix.lower() in (".jpg", ".jpeg") and not p.name.startswith("."):
                rel = p.relative_to(root).parts
                section = rel[0] if len(rel) > 1 else ""
                if section.lower() in ("_unassigned", "unassigned"):
                    section = ""
                if p.name in out:
                    self.log(f"Duplicate export name {p.name} in {section} — kept the first one. Use unique filenames.")
                    continue
                out[p.name] = (p, section)
        return root, out

    def sync_exports(self, ev, force=False):
        """Upload new/changed exports and remove deleted ones. Returns (uploaded, removed)."""
        if not self.enabled or ev["id"] in self.busy:
            return 0, 0
        root, files = self.scan(ev)
        if not root.is_dir():
            return 0, 0
        self.busy.add(ev["id"])
        try:
            st = self.state.setdefault(ev["id"], {})
            todo = []
            for name, (path, section) in files.items():
                stat = path.stat()
                known = st.get(name)
                sig = f"{stat.st_size}:{int(stat.st_mtime)}"
                if known and known.get("sig") == sig and not force:
                    continue  # unchanged since last check (cheap test, no hashing)
                sha = self.sha256(path)
                if known and known.get("sha") == sha and not force:
                    known["sig"] = sig
                    continue
                todo.append((name, path, section, sha, sig))
            gone = [n for n in st if n not in files]
            # safety: a missing/unplugged drive must not wipe the online gallery
            if gone and len(gone) > 10 and len(gone) > 0.5 * len(st):
                self.log(f"{ev['name']}: {len(gone)} exports missing locally — not deleting online. Delete them in the website admin if intended.")
                gone = []
            if not todo and not gone:
                self.save_state()
                return 0, 0
            if todo:
                self.sync_event(ev)  # make sure the event + sections exist online
            self.progress[ev["id"]] = {"done": 0, "total": len(todo), "error": ""}
            up = 0
            for name, path, section, sha, sig in todo:
                try:
                    with open(path, "rb") as fh:
                        res = self.call("POST", f"/events/{ev['id']}/photos",
                                        files={"file": (name, fh, "image/jpeg")},
                                        data={"section_name": section, "photo_number": self.photo_number(name) or "", "filename": name})
                    st[name] = {"sha": sha, "sig": sig, "status": res.get("status")}
                    up += 1
                    self.progress[ev["id"]]["done"] = up
                    if up % 10 == 0:
                        self.save_state()
                except RuntimeError as e:
                    self.progress[ev["id"]]["error"] = str(e)
                    self.log(f"Upload failed for {name}: {e} — will retry.")
                    break
            removed = 0
            for name in gone:
                try:
                    self.call("POST", f"/events/{ev['id']}/photos/delete", json={"filename": name})
                    st.pop(name, None)
                    removed += 1
                except RuntimeError as e:
                    self.log(f"Could not remove {name} online: {e}")
            self.save_state()
            if up or removed:
                self.log(f"{ev['name']}: {up} uploaded, {removed} removed online.")
            return up, removed
        finally:
            self.busy.discard(ev["id"])

    def save_state(self):
        with self.lock:
            tmp = self.state_file.with_suffix(".tmp")
            tmp.write_text(json.dumps(self.state, indent=1))
            tmp.replace(self.state_file)

    def local_counts(self, ev):
        """Per export folder: files found locally and how many are online."""
        _, files = self.scan(ev)
        st = self.state.get(ev["id"], {})
        counts = {}
        for name, (_, section) in files.items():
            c = counts.setdefault(section or "(no folder)", {"local": 0, "online": 0})
            c["local"] += 1
            if name in st:
                c["online"] += 1
        return counts

    # ---------- watcher ----------
    def loop(self):
        while True:
            time.sleep(self.cfg.get("upload_poll_seconds", 30))
            if not (self.enabled and self.cfg.get("auto_upload", True)):
                continue
            for ev in self.load_events():
                if ev.get("upload", True):
                    try:
                        self.sync_exports(ev)
                    except Exception as e:  # keep the watcher alive
                        self.log(f"Upload watcher: {e}")
