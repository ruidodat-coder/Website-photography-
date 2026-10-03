#!/usr/bin/env python3
"""Build ruidodat.com via the WordPress + WooCommerce REST API. Safe to re-run (upserts by slug).

Auth: env WP_USER + WP_APP_PASSWORD, or a file "user:password" passed via --auth FILE.
Usage: python3 build.py [--auth FILE] [--step all|plugins|shop|products|design|pages]
"""
import argparse, json, os, sys, re, requests
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import products as catalog
import content

BASE = "https://ruidodat.com"
THEME = "twentytwentyfive"
HERE = Path(__file__).parent
CA = "/root/.ccr/ca-bundle.crt"

ap = argparse.ArgumentParser()
ap.add_argument("--auth")
ap.add_argument("--step", default="all")
ap.add_argument("--dry", action="store_true")
args = ap.parse_args()
if args.auth:
    USER, PW = Path(args.auth).read_text().strip().split(":", 1)
else:
    USER, PW = os.environ["WP_USER"], os.environ["WP_APP_PASSWORD"]

S = requests.Session()
S.auth = (USER, PW)
S.headers["User-Agent"] = "Mozilla/5.0 (rd-build; +https://ruidodat.com)"  # host ModSecurity rejects python-requests
if os.path.exists(CA):
    S.verify = CA


def api(method, route, data=None, params=None, ok=(200, 201)):
    q = {"rest_route": route}
    q.update(params or {})
    if args.dry and method != "GET":
        print("DRY", method, route, (json.dumps(data, ensure_ascii=False)[:120] if data else ""))
        return {}
    r = S.request(method, BASE + "/", params=q, json=data, timeout=120)
    if r.status_code not in ok:
        raise RuntimeError(f"{method} {route} -> {r.status_code}: {r.text[:400]}")
    return r.json() if r.text else {}


def get_all(route, **params):
    out, page = [], 1
    while True:
        p = {"per_page": 100, "page": page, **params}
        r = S.get(BASE + "/", params={"rest_route": route, **p}, timeout=60)
        if r.status_code != 200:
            break
        chunk = r.json()
        out += chunk
        if page >= int(r.headers.get("X-WP-TotalPages", 1)):
            break
        page += 1
    return out


def log(*a):
    print("•", *a, flush=True)


# ------------------------------------------------------------------ media
MEDIA = json.loads((HERE.parent / "media.json").read_text())
M = {int(k): {"id": int(k), "url": v["full"]} for k, v in MEDIA.items()}


# ------------------------------------------------------------------ 1. plugins
def step_plugins():
    have = {p["plugin"].split("/")[0]: p for p in api("GET", "/wp/v2/plugins")}
    for slug in ["woocommerce", "woocommerce-germanized"]:
        if slug not in have:
            log("install", slug)
            api("POST", "/wp/v2/plugins", {"slug": slug, "status": "active"})
        elif have[slug]["status"] != "active":
            log("activate", slug)
            api("POST", f"/wp/v2/plugins/{have[slug]['plugin']}", {"status": "active"})
        else:
            log("ok", slug)


# ------------------------------------------------------------------ 2. shop settings
def step_shop():
    general = {
        "woocommerce_store_address": "", "woocommerce_store_city": "Germersheim", "woocommerce_store_postcode": "76726",
        "woocommerce_default_country": "DE:RP", "woocommerce_currency": "EUR", "woocommerce_currency_pos": "right_space",
        "woocommerce_price_thousand_sep": ".", "woocommerce_price_decimal_sep": ",", "woocommerce_price_num_decimals": "2",
        "woocommerce_calc_taxes": "no", "woocommerce_allowed_countries": "specific", "woocommerce_specific_allowed_countries": ["DE", "AT", "CH", "LU", "FR", "NL"],
        "woocommerce_ship_to_countries": "specific", "woocommerce_specific_ship_to_countries": ["DE"],
    }
    api("POST", "/wc/v3/settings/general/batch", {"update": [{"id": k, "value": v} for k, v in general.items()]})
    general["woocommerce_default_customer_address"] = "base"
    api("POST", "/wc/v3/settings/general/batch", {"update": [{"id": "woocommerce_default_customer_address", "value": "base"}]})
    api("POST", "/wc/v3/settings/products/batch", {"update": [{"id": "woocommerce_enable_reviews", "value": "no"}]})
    api("POST", "/wp/v2/settings", {"date_format": "j. F Y", "time_format": "H:i", "start_of_week": 1})
    log("woo general settings")
    zones = api("GET", "/wc/v3/shipping/zones")
    z = next((z for z in zones if z["name"] == "Deutschland"), None)
    if not z:
        z = api("POST", "/wc/v3/shipping/zones", {"name": "Deutschland"})
        api("PUT", f"/wc/v3/shipping/zones/{z['id']}/locations", [{"code": "DE", "type": "country"}])
        api("POST", f"/wc/v3/shipping/zones/{z['id']}/methods", {"method_id": "flat_rate", "settings": {"title": "Versand (DHL)", "cost": "6.90"}})
        api("POST", f"/wc/v3/shipping/zones/{z['id']}/methods", {"method_id": "free_shipping", "settings": {"title": "Kostenloser Versand", "requires": "min_amount", "min_amount": "150"}})
    log("shipping zone Deutschland")
    api("POST", "/wc/v3/payment_gateways/bacs", {"enabled": True, "title": "Überweisung (Vorkasse)",
        "description": "Bitte überweise den Betrag nach der Bestellung. Die Bankverbindung erhältst du in der Bestätigungs-E-Mail. Termine werden nach Zahlungseingang bestätigt."})
    log("payment: Vorkasse enabled (add IBAN in WooCommerce → Settings → Payments)")


# ------------------------------------------------------------------ 3. products
def step_products():
    cats = {c["slug"]: c for c in get_all("/wc/v3/products/categories")}
    for slug, name in catalog.CATS.items():
        if slug not in cats:
            cats[slug] = api("POST", "/wc/v3/products/categories", {"name": name, "slug": slug})
            log("category", name)
    existing = {pr["slug"]: pr for pr in get_all("/wc/v3/products", status="any")}
    PR = {}
    for key, name, price, cat, virtual, short, long in catalog.P:
        slug = "rd-" + key
        body = {"name": name, "slug": slug, "type": "simple", "status": "publish", "regular_price": f"{price:.2f}",
                "virtual": virtual, "short_description": f"<p>{short}</p>", "description": catalog.description(key, cat, long),
                "categories": [{"id": cats[cat]["id"]}], "catalog_visibility": "hidden" if cat in ("anfahrt",) else "visible",
                "sold_individually": cat in catalog.SINGLE}
        if key in catalog.IMAGES:
            body["images"] = [{"id": catalog.IMAGES[key]}]
        if slug in existing:
            pr = api("PUT", f"/wc/v3/products/{existing[slug]['id']}", body)
        else:
            pr = api("POST", "/wc/v3/products", body)
            log("product", name)
        PR[key] = {"id": pr.get("id"), "url": pr.get("permalink", f"/?p={pr.get('id')}"), "price": price}
    catlinks = {}
    for slug in catalog.CATS:
        t = api("GET", f"/wp/v2/product_cat/{cats[slug]['id']}")
        catlinks[slug] = t.get("link")
    (HERE / ".products.json").write_text(json.dumps({"PR": PR, "CAT": catlinks}, indent=1))
    log(f"{len(PR)} products ok")
    return PR, catlinks


# ------------------------------------------------------------------ 4. design (styles, menus, parts, templates)
def nav_links(items):
    out = []
    for label, url in items:
        out.append(f'<!-- wp:navigation-link {json.dumps({"label": label, "url": url, "kind": "custom", "isTopLevelLink": True}, ensure_ascii=False)} /-->')
    return "\n".join(out)


MENUS = {
    "Menü Pferde": [("Portfolio", "/pferde/portfolio/"), ("Shootings &amp; Preise", "/pferde/shootings-preise/"), ("Stalltag", "/pferde/stalltag/"),
                    ("Turnierfotos", "/pferde/turnierfotografie/"), ("Freie Termine", "/verfuegbarkeit/"), ("Gutscheine", "/gutscheine/"), ("FAQ", "/pferde/ablauf-faq/"),
                    ("Kontakt", "/kontakt/"), ("→ Hochzeit", "/hochzeit/")],
    "Menü Hochzeit": [("Echte Hochzeiten", "/hochzeit/echte-hochzeiten/"), ("Pakete &amp; Preise", "/hochzeit/pakete-preise/"),
                      ("Paarshootings", "/hochzeit/paarshootings/"), ("Alben", "/hochzeit/alben-wandbilder/"), ("Gästegalerie", "/hochzeit/gaestegalerie/"),
                      ("Freie Termine", "/verfuegbarkeit/"), ("FAQ", "/hochzeit/ablauf-faq-hochzeit/"), ("Kontakt", "/kontakt/"), ("→ Pferde", "/pferde/")],
    "Menü Hauptseite": [("Pferde", "/pferde/"), ("Hochzeit", "/hochzeit/"), ("Freie Termine", "/verfuegbarkeit/"), ("Gutscheine", "/gutscheine/"), ("Shop", "/shop/"),
                        ("Über mich", "/ueber-mich/"), ("Kontakt", "/kontakt/")],
}


def upsert_nav(title, items):
    navs = get_all("/wp/v2/navigation", status="publish")
    n = next((n for n in navs if n["title"]["rendered"] == title), None)
    body = {"title": title, "content": nav_links(items), "status": "publish"}
    n = api("POST", f"/wp/v2/navigation/{n['id']}", body) if n else api("POST", "/wp/v2/navigation", body)
    return n["id"]


def header(cls, nav_id, world_label, world_url):
    logo = M[441]
    return f'''<!-- wp:group {{"align":"full","className":"rd-header {cls}","layout":{{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}}}} -->
<div class="wp-block-group alignfull rd-header {cls}"><!-- wp:group {{"layout":{{"type":"flex","flexWrap":"nowrap"}}}} -->
<div class="wp-block-group"><!-- wp:image {{"id":441,"sizeSlug":"medium","linkDestination":"custom","className":"rd-logo"}} -->
<figure class="wp-block-image size-medium rd-logo"><a href="/"><img src="{logo["url"]}" alt="Rui Dodat Fotografie" class="wp-image-441"/></a></figure>
<!-- /wp:image -->

<!-- wp:paragraph {{"className":"rd-world"}} -->
<p class="rd-world"><a href="{world_url}">{world_label}</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {{"layout":{{"type":"flex","flexWrap":"nowrap"}}}} -->
<div class="wp-block-group"><!-- wp:navigation {{"ref":{nav_id},"overlayMenu":"mobile","layout":{{"type":"flex","justifyContent":"right"}}}} /-->

<!-- wp:woocommerce/mini-cart /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->'''


def footer():
    col = lambda title, links: (f'<!-- wp:column -->\n<div class="wp-block-column"><!-- wp:heading {{"level":4}} -->\n<h4 class="wp-block-heading">{title}</h4>\n<!-- /wp:heading -->\n\n'
                                f'<!-- wp:paragraph -->\n<p>' + "<br>".join(f'<a href="{u}">{l}</a>' for l, u in links) + '</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:column -->')
    return f'''<!-- wp:group {{"align":"full","className":"rd-footer","layout":{{"type":"constrained","contentSize":"1240px"}}}} -->
<div class="wp-block-group alignfull rd-footer"><!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column {{"width":"34%"}} -->
<div class="wp-block-column" style="flex-basis:34%"><!-- wp:image {{"id":441,"sizeSlug":"medium","linkDestination":"custom","className":"rd-logo"}} -->
<figure class="wp-block-image size-medium rd-logo"><a href="/"><img src="{M[441]["url"]}" alt="Rui Dodat Fotografie" class="wp-image-441"/></a></figure>
<!-- /wp:image -->

<!-- wp:paragraph -->
<p>Pferde- &amp; Hochzeitsfotografie<br>76726 Germersheim · Südpfalz<br><a href="https://wa.me/491738505311">WhatsApp 0173 8505311</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

{col("Pferde", [("Shootings &amp; Preise", "/pferde/shootings-preise/"), ("Stalltag", "/pferde/stalltag/"), ("Turnierfotos", "/pferde/turnierfotografie/"), ("Portfolio", "/pferde/portfolio/")])}

{col("Hochzeit", [("Pakete &amp; Preise", "/hochzeit/pakete-preise/"), ("Paarshootings", "/hochzeit/paarshootings/"), ("Alben &amp; Wandbilder", "/hochzeit/alben-wandbilder/"), ("Gästegalerie", "/hochzeit/gaestegalerie/")])}

{col("Info", [("Freie Termine", "/verfuegbarkeit/"), ("Gutscheine", "/gutscheine/"), ("Über mich", "/ueber-mich/"), ("Kontakt", "/kontakt/"), ("Versand &amp; Zahlung", "/versand-zahlung/"), ("Impressum", "/impressum/"), ("Datenschutz", "/datenschutz/"), ("AGB", "/agb/"), ("Widerruf", "/widerruf/")])}</div>
<!-- /wp:columns -->

<!-- wp:paragraph {{"className":"rd-legal"}} -->
<p class="rd-legal">© 2026 Rui Dodat Fotografie · Alle Preise sind Endpreise. Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->'''


def page_template(part, world):
    return (f'<!-- wp:template-part {{"slug":"{part}","theme":"{THEME}","tagName":"header"}} /-->\n\n'
            f'<!-- wp:group {{"tagName":"main","className":"{world}","layout":{{"type":"default"}}}} -->\n'
            f'<main class="wp-block-group {world}"><!-- wp:post-content {{"layout":{{"type":"constrained"}}}} /--></main>\n<!-- /wp:group -->\n\n'
            f'<!-- wp:template-part {{"slug":"footer","theme":"{THEME}","tagName":"footer"}} /-->')


def upsert(kind, slug, title, body):
    rid = f"{THEME}//{slug}"
    r = S.get(BASE + "/", params={"rest_route": f"/wp/v2/{kind}/{rid}"}, timeout=60)
    if r.status_code == 200:
        api("POST", f"/wp/v2/{kind}/{rid}", body)
    else:
        api("POST", f"/wp/v2/{kind}", {"slug": slug, "title": title, **body})
    log(kind, slug)


def step_design():
    active = api("GET", "/wp/v2/themes", params={"status": "active"})[0]
    if active["stylesheet"] != THEME:
        sys.exit(f"Active theme is {active['stylesheet']}. Please activate Twenty Twenty-Five first (Design → Themes).")
    gs_href = active["_links"]["wp:user-global-styles"][0]["href"]
    gs_id = re.search(r"global-styles/(\d+)", gs_href).group(1)
    css = (HERE / "style.css").read_text()
    css += "\n.wp-block-post-content>.alignfull,.wp-block-post-content>.alignwide{margin-block-start:0;margin-block-end:0}\n"
    api("POST", f"/wp/v2/global-styles/{gs_id}", {"styles": {"css": css},
        "settings": {"layout": {"contentSize": "760px", "wideSize": "1240px"}}})
    log("global styles + CSS")
    ids = {t: upsert_nav(t, items) for t, items in MENUS.items()}
    upsert("template-parts", "header-pferde", "Header Pferde", {"area": "header", "content": header("rd-header-pferde", ids["Menü Pferde"], "Pferdefotografie", "/pferde/")})
    upsert("template-parts", "header-hochzeit", "Header Hochzeit", {"area": "header", "content": header("rd-header-hochzeit", ids["Menü Hochzeit"], "Hochzeitsfotografie", "/hochzeit/")})
    upsert("template-parts", "header", "Header", {"area": "header", "content": header("rd-header-main", ids["Menü Hauptseite"], "Fotografie", "/")})
    upsert("template-parts", "footer", "Footer", {"area": "footer", "content": footer()})
    upsert("templates", "rd-pferde", "Seite Pferde", {"content": page_template("header-pferde", "world-pferde")})
    upsert("templates", "rd-hochzeit", "Seite Hochzeit", {"content": page_template("header-hochzeit", "world-hochzeit")})
    upsert("templates", "rd-main", "Seite Allgemein", {"content": page_template("header", "world-main")})
    upsert("templates", "rd-blank", "Startseite Split", {"content": '<!-- wp:group {"tagName":"main","className":"world-home","layout":{"type":"default"}} -->\n'
           '<main class="wp-block-group world-home"><!-- wp:post-content {"layout":{"type":"constrained"}} /--></main>\n<!-- /wp:group -->'})


# ------------------------------------------------------------------ 5. pages
OLD_TO_DRAFT = [611, 605, 600, 598, 593, 591, 589, 587, 581, 565, 561, 559, 555, 552, 549, 546, 526, 523, 520, 517, 10]


def step_pages():
    data = json.loads((HERE / ".products.json").read_text())
    PR, CAT = data["PR"], data["CAT"]
    cal_file = HERE / "calendar_url.txt"
    CAL = cal_file.read_text().strip() if cal_file.exists() else None
    pages = content.build(M, PR, CAL)
    existing = {p["slug"]: p for p in get_all("/wp/v2/pages", status="publish,draft,private", context="edit")}
    ids = {}
    for pg in pages:
        html = pg["content"]
        for slug, link in CAT.items():
            html = html.replace(f"/produkt-kategorie/{slug}/", link)
        body = {"title": pg["title"], "slug": pg["slug"], "content": html, "status": "publish", "template": pg["template"],
                "parent": ids.get(pg["parent"], 0) if pg["parent"] else 0}
        cur = existing.get(pg["slug"])
        res = api("POST", f"/wp/v2/pages/{cur['id']}", body) if cur else api("POST", "/wp/v2/pages", body)
        ids[pg["slug"]] = res.get("id")
        log("page", pg["slug"], res.get("link", ""))
    for pid in OLD_TO_DRAFT:
        try:
            api("POST", f"/wp/v2/pages/{pid}", {"status": "draft"})
        except RuntimeError as e:
            log("skip draft", pid, str(e)[:80])
    log("old pages set to draft")
    api("POST", "/wp/v2/settings", {"title": "Rui Dodat Fotografie", "description": "Pferde- & Hochzeitsfotografie · Südpfalz",
                                     "show_on_front": "page", "page_on_front": ids["home"]})
    log("front page + site title")


def step_snippets():
    """PHP snippets via the Code Snippets plugin (upsert by name)."""
    have = {s["name"]: s for s in api("GET", "/code-snippets/v1/snippets")}
    for f in sorted((HERE / "snippets").glob("*.php")):
        name = "rd: " + f.stem
        code = f.read_text()
        if "RD_TERMIN_IDS" in code:
            PR = json.loads((HERE / ".products.json").read_text())["PR"]
            ids = sorted(PR[k]["id"] for k, _, _, cat, *_ in catalog.P if cat in ("pferde-shootings", "hochzeit", "paarshootings"))
            code = code.replace("RD_TERMIN_IDS", "array( " + ", ".join(map(str, ids)) + " )")
        body = {"name": name, "code": code, "scope": "global", "active": True,
                "desc": "Managed by site/build.py (Website-photography- repo)."}
        if name in have:
            api("POST", f"/code-snippets/v1/snippets/{have[name]['id']}", body)
        else:
            api("POST", "/code-snippets/v1/snippets", body)
        log("snippet", name)


steps = {"plugins": step_plugins, "snippets": step_snippets, "shop": step_shop, "products": step_products, "design": step_design, "pages": step_pages}
for name in (steps if args.step == "all" else [args.step]):
    print(f"\n== {name}")
    steps[name]()
print("\nDone.")
