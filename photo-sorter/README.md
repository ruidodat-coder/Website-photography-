# PhotoSorter — SD card → sorted folders → Lightroom → client gallery

## 1. Install (once)
1. Install Python 3.10+ (python.org). On Windows tick "Add to PATH".
2. Open a terminal in this folder and run: `pip install -r requirements.txt` (Flask + requests)
3. Edit `config.json`:
   - `library_root`: where sorted photos go (e.g. `D:/Photos/Clients` or `~/Pictures/PhotoSorter`).
   - `card_mount_roots`: macOS `["/Volumes"]`, Linux `["/media/<you>"]`. Windows is detected automatically (drive letters with a DCIM folder).
4. Start it: `python app.py` → open http://localhost:5055

Tip: add `python app.py` to your startup items so it always runs.

## 2. Per shoot
1. Before or after the shoot, create the event in the web page and enter the ranges
   (or paste a list: `preparation groom 1 - 254`).
2. Tick "Active event" and save.
3. Insert the SD card. It's copied and sorted automatically:
   `2026-10-03 Wedding Tobi & Luisa/01 preparation groom/…`
   Photos outside every range land in `_Unassigned`.
4. Or pick a card in the page and press "Import into this event".

## 3. Lightroom Classic (one-time setup)
1. Make a Develop preset with your base edit (and optionally a Metadata preset with copyright).
2. Save an Import preset: Add (don't move), apply your Develop + Metadata preset.
3. Import the event folder with that preset. RAW files come in with keywords
   `Event` and `Event > Section` from the XMP sidecars.
4. Create a Smart Collection per section: rule "Keywords contains <Section>". Save it as a
   Smart Collection template so every event gets the same structure.
5. Do your per-image fine-tuning and culling (pick flag P).

## 4. Online gallery on ruidodat.com (automatic upload)
PhotoSorter uploads your finished Lightroom exports to your own website. No other gallery service needed.

**Connect once**
1. In WordPress go to **Galerien** (left menu), section *PhotoSorter-Verbindung*, press "Schlüssel anzeigen".
2. Put the key into `config.json` (or a separate `config.local.json` that you never share):
   `"website_url": "https://ruidodat.com"`, `"api_key": "rdg_…"`. Restart `python app.py`.
3. The activity log shows `Website upload: on`.

**Per event**
1. In the event choose **Website area** (Hochzeit / Pferde) and **Gallery type**:
   - *Paid client*: all photos without watermark, free download (single or ZIP). For weddings and shootings that are paid.
   - *Sale*: previews with watermark; people buy single photos, a whole section (e.g. one rider's round) or the whole event,
     pay at checkout and get the full-resolution files by email link. For tournaments and guest sales.
   Tip for tournaments: one section per rider/start, e.g. `Anna Müller – Cornet – Prüfung 4  1201 - 1263`. Riders search by name.
2. **Lightroom export preset** (create once): Export to *Specific folder* = the event folder, *Put in subfolder* = `_Export`,
   then export each section into its own subfolder with the same name as the section folder (`01 preparation groom`, …).
   File naming: **Filename** (keeps `DSC_0254` so the number stays). Format JPEG, sRGB, full size, quality 90.
   Easiest: one export per section (select the section's smart collection → Export → choose the subfolder).
3. Exported files go online within ~30 seconds. Re-export an edited photo → the online version is replaced
   (buyers always download the newest version). Delete an export → it is removed online.
4. Press **Publish gallery** in the event's *Website gallery* box. There you also see the access code and the link.
   Send it to the client from WordPress → Galerien → "An Kunde senden" (email with link + code), or copy it.
5. Clients open **ruidodat.com → Euer Event / Dein Event**, type the code and see the gallery.

Galleries expire after 12 months by default (WordPress → Galerien: "+12 Monate" or change the default).
Deleting an event in WordPress removes all its photos from the server (GDPR).
Safety: if many exports suddenly disappear (e.g. external drive unplugged), PhotoSorter does NOT delete them online.

## Rules that keep the numbers reliable
- Filenames must contain the shot number (camera default: DSC_0254, IMG_0254). Don't rename in camera.
- Counter rollover: after 9999 the camera restarts at 0001. If a shoot crosses it, split into two imports or reset the counter before the shoot.
- Two cameras: numbers will collide. Give each camera its own filename prefix and set up one event per camera, or sync numbering and use separate ranges.
- Import is safe to repeat: files already copied (same name + size) are skipped. Nothing on the card is deleted.
