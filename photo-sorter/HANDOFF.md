# HANDOFF: Integrate PhotoSorter with the photography website

You are integrating an existing local tool (this folder) into the photography website
you are building. Read this whole file, then inspect the website repo and adapt the plan
to its stack (framework, database, hosting, auth). If something here conflicts with how
the website is already built, follow the website's conventions and tell the owner what
you changed.

---

## 1. Goal (end-to-end workflow)

```
SD card ──► PhotoSorter (local) ──► sorted folders ──► Lightroom Classic (edit)
                                                            │
                                                  export finished JPGs
                                                            ▼
                         PhotoSorter uploader (local) ──► Website API
                                                            ▼
                     Client gallery per event/section ──► purchase ──► delivery
```

1. Photographer creates an event and enters photo-number ranges per section
   (e.g. "preparation groom 1-254", "preparation bride 255-369", "Church ceremony 370-503").
2. SD card inserted → photos copied and sorted into `<library>/<date event>/<NN section>/`.
3. Photographer edits in Lightroom Classic and exports finished JPGs into
   `<library>/<date event>/_Export/<NN section>/` (one Lightroom export preset).
4. **NEW:** PhotoSorter detects new exports and uploads them to the website, tagged
   with event + section + original photo number.
5. **NEW:** The website shows a private gallery per event, grouped by section,
   with watermarked previews. Clients buy digital downloads and/or prints.
6. **NEW:** After payment, the client gets the full-resolution files.

The owner should never have to touch the website manually between export and sale.

---

## 2. What already exists (in this folder)

| File | Purpose |
|---|---|
| `app.py` | Flask app, runs locally on `127.0.0.1:5055`. Stores events in `events.json`, watches for SD cards (folder with `DCIM`), copies + sorts photos by the last digit group in the filename, writes XMP sidecars with keywords `Event` and `Event > Section` for RAW files. |
| `templates/index.html` | Local admin page: create events, enter/paste ranges, overlap/gap check, manual import, activity log. |
| `config.json` | Library path, card mount roots, extensions, port. |
| `README.md` | User setup guide (Lightroom + gallery steps). |

Event data model (`events.json`):

```json
{
  "id": "a1b2c3d4",
  "name": "Wedding Tobi & Luisa",
  "date": "2026-10-03",
  "client": "client@email.com",
  "active": true,
  "sections": [
    {"name": "preparation groom", "start": 1, "end": 254},
    {"name": "preparation bride", "start": 255, "end": 369},
    {"name": "Church ceremony",  "start": 370, "end": 503}
  ]
}
```

Keep the local app. It must keep working offline. The SD card can only be read
locally, so the sorting stays local. Only finished exports go online.

---

## 3. What to build

### 3.1 Website backend
- **Data model** (adapt to the site's DB/ORM):
  - `Event`: id, slug, name, date, client_email, access_code (hashed), status
    (draft/published/archived), expires_at, cover_photo_id.
  - `Section`: id, event_id, name, position, start_no, end_no.
  - `Photo`: id, event_id, section_id, photo_number, filename, original_key (private
    storage), preview_key (watermarked, public/CDN), width, height, uploaded_at.
    Unique constraint on (event_id, filename).
  - `Product`: id, type (digital_single, digital_section, digital_full_event, print),
    size/format, price. The owner can edit products and prices in admin.
  - `Order` / `OrderItem`: client email, items, payment status, payment provider ref,
    download token, token expiry.
- **Uploader API** (used by the local PhotoSorter, authenticated with an API key):
  - `PUT /api/admin/events/{id}`: create/update event + sections (sync from local).
  - `POST /api/admin/events/{id}/photos`: multipart upload of one JPG with
    `section_name`, `photo_number`. Idempotent: same filename + same hash = no-op;
    same filename with a new hash = replace (re-edits).
  - `DELETE /api/admin/events/{id}/photos/{filename}`: for photos removed in Lightroom.
- **Processing on upload:** store the original privately (S3/R2/Supabase storage or
  whatever the site uses). Generate a watermarked preview (~2048px long edge) and a
  thumbnail (~600px). Strip GPS from all public files.
- **Payments:** Stripe Checkout, unless the site already uses another provider.
  Use the webhook to mark orders paid. Never trust the client-side redirect.
- **Delivery:** signed, expiring download links (single files + ZIP per section/event).
  Send an email with the link after payment. Prints: create a print-order record and
  notify the owner by email; print-lab automation is a later phase.

### 3.2 Website frontend
- `/gallery/{event-slug}`: access-code gate, then a gallery grouped by section in
  range order. Section tabs or anchors, lazy-loaded thumbnails, lightbox with the
  preview, and a shot number shown so clients can reference photos ("#254").
- Favourites/selection list per client (stored server-side by email + event).
- Cart: add single photo, whole section, or whole event. Checkout via Stripe.
- Mobile-first. Clients mostly open galleries on phones.
- Match the design system of the website being built.

### 3.3 Website admin (owner only)
- List of events with status, photo counts per section, orders, revenue.
- Publish/unpublish an event, set expiry, regenerate the access code, edit prices.
- "Send gallery to client" button: emails the link + code to `client_email`.

### 3.4 Changes to the local PhotoSorter (`app.py` + `index.html`)
- Add to `config.json`: `website_url`, `api_key`, `export_folder_name` (default `_Export`),
  `auto_upload` (bool).
- **Export watcher:** poll `<event_dir>/_Export/**` for new or changed JPGs. Map the
  section from the subfolder name (`NN section`). Fall back to the photo number +
  event ranges if the folder name doesn't match. Upload via the API with retry and
  backoff. Keep a local `upload_state.json` (filename → hash, status) so restarts
  don't re-upload.
- Event sync: when an event is saved locally, push it to `PUT /api/admin/events/{id}`.
- UI additions: per event, show uploaded/total per section, an "Upload now" button,
  a "Publish gallery" toggle (calls the website), and the client gallery link to copy.
- Lightroom export may rename files. Read the photo number from the filename (same
  regex as import) and keep the original number in the export filename
  (document this in README: Lightroom export naming = "Filename").

---

## 4. Rules and edge cases
- Photo numbers come from the last digit group in the filename (`DSC_0254.JPG` → 254).
- Ranges in one event must not overlap (the local app already validates this; repeat
  the validation server-side).
- Camera counter rollover (9999 → 0001) and two-camera shoots produce duplicate
  numbers. Uniqueness is by **filename** per event, not by number. Show the
  number in the UI only.
- Photos outside all ranges go to an "Other" section online (from `_Unassigned`).
- Re-exported (re-edited) photos replace the existing photo. Paid orders must still
  deliver the newest version.
- The API key lives only in local `config.json` and server env vars. Never commit it.
- Originals are never publicly reachable. Only previews and thumbnails are public.
- GDPR: galleries are private by default, expire (default 12 months, configurable),
  and the owner can delete an event with all its files.

---

## 5. Suggested order of work
1. Inspect the website repo; write a short plan back to the owner (stack, storage,
   payment provider) before coding.
2. DB models + storage + admin uploader API + preview/watermark pipeline.
3. Local PhotoSorter: config, event sync, export watcher, upload state, UI status.
4. Client gallery (gate, sections, lightbox, favourites).
5. Cart + Stripe Checkout + webhook + delivery emails/links.
6. Admin pages.
7. End-to-end test with the sample event above. Upload ~20 dummy JPGs across
   3 sections and complete a Stripe test-mode purchase.

## 6. Done when
- Inserting an SD card with an active event sorts the photos correctly.
- Exporting from Lightroom into `_Export` makes the photos appear in the right
  section of the online gallery within a minute, with no manual step.
- A client can open the gallery with the code, buy a photo/section/event in Stripe
  test mode, and download the full-resolution files from the emailed link.
- Re-exporting an edited photo updates it online.
- The README is updated with the new setup steps (API key, Lightroom export preset,
  starting the app).

## 7. Ask the owner if unknown
- Which products to sell (digital only, prints, packages) and prices.
- Watermark text/logo file.
- Payment provider and currency (default: Stripe, EUR).
- Email sending service already used by the website.
