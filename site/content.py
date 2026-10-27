"""Page content (German). build(M, PR) -> list of page dicts.
M: media id -> {"id","url"} ; PR: product key -> {"url","price"}"""
from blocks import *
import json as _json
from pathlib import Path as _Path

# Boho stock photos (Unsplash licence) used as placeholders until own wedding photos exist
_sf = _Path(__file__).with_name("stock.json")
STOCK = _json.loads(_sf.read_text()) if _sf.exists() else {}


def stock(name, pos="50% 50%"):
    st = STOCK[name]
    return {"url": st["url"], "id": st["id"], "pos": pos}


def stock_img(name, alt):
    st = STOCK[name]
    return ({"id": st["id"], "url": st["url"]}, alt + " (Symbolbild)")

WA = "https://wa.me/491738505311"

# Hero videos (Mixkit free licence, uploaded to the media library)
_vf = _Path(__file__).with_name("videos.json")
VIDEOS = _json.loads(_vf.read_text()) if _vf.exists() else {}


def hero_video(world):
    """Muted looping background video for a hero group; the poster doubles as the group's background image."""
    vid, poster = VIDEOS[f"hero-{world}.mp4"], VIDEOS[f"hero-{world}-poster.jpg"]
    return raw(f'<!-- wp:html -->\n<div class="rd-video" aria-hidden="true"><video autoplay muted loop playsinline preload="auto" '
               f'poster="{poster["url"]}"><source src="{vid["url"]}" type="video/mp4"></video></div>'
               f'<button class="rd-cue" type="button" aria-label="Nach unten scrollen"><span></span></button>\n<!-- /wp:html -->')


def poster_bg(world, pos="50% 50%"):
    pst = VIDEOS[f"hero-{world}-poster.jpg"]
    return {"url": pst["url"], "id": pst["id"], "pos": pos}


# Client galleries are served by the site itself (snippets/gallery-*.php); PhotoSorter uploads the exports.
def code_form(label, placeholder):
    """Access-code field; the footer script posts it to /rd/v1/gallery/open and opens the gallery."""
    return raw(f'<!-- wp:html -->\n<form class="rd-code" data-rd-open="/wp-json/rd/v1/gallery/open" method="post" action="#"><label for="rd-code-in">{label}</label>'
               f'<div class="rd-code-row"><input id="rd-code-in" name="qr" type="text" required minlength="4" autocomplete="off" autocapitalize="characters" '
               f'spellcheck="false" placeholder="{placeholder}"><button type="submit">Bilder öffnen</button></div>'
               f'<p class="rd-code-note" aria-live="polite"></p></form>\n<!-- /wp:html -->')


def event_types(items):
    """Cards explaining what a code unlocks: (icon, title, who, steps)."""
    cells = "".join(f'<div class="rd-etype"><span class="rd-etype-icon">{ic}</span><h3>{t}</h3><p class="rd-etype-who">{who}</p>'
                    f'<ol>{"".join(f"<li>{x}</li>" for x in steps)}</ol></div>' for ic, t, who, steps in items)
    return raw(f'<!-- wp:html -->\n<div class="rd-etypes">{cells}</div>\n<!-- /wp:html -->')


def stats(items):
    """Count-up numbers: (number, unit, title, text). The final number is in the HTML, JS animates from 0."""
    cells = "".join(f'<div class="rd-stat"><div class="rd-stat-n"><span class="rd-num" data-to="{n}">{n}</span>'
                    f'<small>{u}</small></div><p><strong>{t}</strong>{x}</p></div>' for n, u, t, x in items)
    return raw(f'<!-- wp:html -->\n<div class="rd-stats">{cells}</div>\n<!-- /wp:html -->')


def marquee(words):
    run = "".join(f"<span>{w}</span>" for w in words)
    return raw(f'<!-- wp:html -->\n<div class="rd-marquee" aria-hidden="true"><div class="rd-marquee-track">{run}{run}</div></div>\n<!-- /wp:html -->')


def before_after(before, after, before_alt, after_alt):
    """Draggable before/after comparison (works with mouse, touch and arrow keys; JS in motion.php)."""
    return raw(f'<!-- wp:html -->\n<div class="rd-ba" style="--pos:50%"><img src="{after["url"]}" alt="{after_alt}" width="1200" height="1800" loading="lazy"/>'
               f'<img class="rd-ba-before" src="{before["url"]}" alt="{before_alt}" width="1200" height="1800" loading="lazy"/>'
               '<span class="rd-ba-label rd-ba-l">Vorher</span><span class="rd-ba-label rd-ba-r">Nachher</span><span class="rd-ba-handle" aria-hidden="true"></span>'
               '<input type="range" min="0" max="100" value="50" aria-label="Vorher-Nachher-Vergleich: Regler verschieben"/></div>\n<!-- /wp:html -->')


def eur(v):
    s = f"{v:,.0f}".replace(",", ".")
    return f"{s} €"


def card(title, price, items, url, label="Jetzt buchen", popular=False, sub=None):
    return column(
        h(title, 3),
        p(price, "rd-price"),
        *( [p(sub, "rd-small")] if sub else [] ),
        ul(items),
        buttons(button(label, url)),
        cls="rd-popular" if popular else None,
    )


def HORSE_CARDS(PR, long=False):
    """Packages from the business plan, named after the gaits: Schritt / Trab / Galopp."""
    return [
        card("Schritt", "199 €", ["bis zu 60 Minuten", "1 Pferd", "5 bearbeitete Bilder"] + (["der kurze Einstieg"] if long else []), "/kontakt/?w=pferde", "Termin anfragen"),
        card("Trab", "349 €", ["bis zu 2 Stunden", "Pferd &amp; Reiter", "12 bearbeitete Bilder"] + (["2 Outfits möglich"] if long else []), "/kontakt/?w=pferde", "Termin anfragen", popular=True),
        card("Galopp", "549 €", ["bis zu 3 Stunden", "Pferd &amp; ganze Familie", "25 bearbeitete Bilder", "Wandbild inklusive"], "/kontakt/?w=pferde", "Termin anfragen"),
    ]


def WEDDING_CARDS(url, label, long=False):
    """Wedding packages from the business plan."""
    return [
        card("Halbtag", "1.490 €", ["5 Stunden Begleitung", "z. B. Trauung, Empfang &amp; Paarshooting", "alle gelungenen Bilder bearbeitet"] + (["ideal für Standesamt &amp; kleine Feiern"] if long else []), url, label),
        card("Ganztag", "2.490 €", ["9 Stunden Begleitung", "vom Getting Ready bis zur Party", "alle gelungenen Bilder bearbeitet", "Sneak Peek in 72 h"] + (["Paarshooting zur Golden Hour"] if long else []), url, label, popular=True),
    ]


def travel_table(world):
    rows = [
        ["1 · bis 30 km", "Speyer, Landau, Karlsruhe, Bruchsal, Hockenheim, Kandel", "inklusive"],
        ["2 · 30–60 km", "Mannheim, Heidelberg, Ludwigshafen, Neustadt, Rastatt, Pforzheim", "25 €" if world == "pferde" else "inklusive"],
        ["3 · 60–100 km", "Kaiserslautern, Pirmasens, Baden-Baden, Heilbronn, Worms", "49 €"],
        ["4 · über 100 km", "Stuttgart, Frankfurt, Saarland …", "49 € + 0,40 €/km ab km 100 (hin & zurück)"
         + ("<br>+ Übernachtung (max. 120 €) ab 200 km" if world == "hochzeit" else "")],
    ]
    return table(["Zone (ab Germersheim)", "Beispiele", "Anfahrt"], rows)


def cal_block(cal_url, world):
    """Public availability calendar: only busy/free, never booking details."""
    if not cal_url:
        return group(h("Freie Termine", 2), p("Der Verfügbarkeitskalender wird gerade eingerichtet. Frag mich gern direkt per WhatsApp oder Kontaktformular.", "rd-note"),
                     cls="rd-section", align="wide")
    sc = (f'[ics_calendar url="{cal_url}" view="month" nomobile="true" eventdesc="false" location="false" '
          f'organizer="false" pastdays="0" limitdays="548" timeformat="H:i"]')
    intro = ("Hier siehst du, welche Tage und Zeiten schon vergeben sind. Alles Weiße ist noch frei."
             if world == "pferde" else
             "Hier seht ihr auf einen Blick, ob euer Wunschtermin noch frei ist. Pro Tag begleite ich nur eine Hochzeit.")
    return group(
        p("Verfügbarkeit", "rd-eyebrow", align="center"), h("Freie Termine", 2, align="center"),
        p(intro, align="center"),
        raw(f"<!-- wp:shortcode -->\n{sc}\n<!-- /wp:shortcode -->"),
        p('<span class="free">frei</span><span class="part">teilweise belegt</span><span class="full">ganztägig belegt</span>', "rd-cal-legend"),
        p("Ein freier Tag ist noch nicht reserviert. Fest gebucht ist ein Termin erst nach Bezahlung bzw. Anzahlung. "
          "Der Kalender wird etwa stündlich aktualisiert.", "rd-small", align="center"),
        cls="rd-section rd-cal", align="wide")


# Parked: vouchers and the old guest page. No shop: everything is booked by request and paid in person.
# Client photo downloads are on: "Deine Bilder" / "Eure Bilder" (password per gallery, wp-admin → Galerien).
DRAFT = {"gaestegalerie", "gutscheine"}


def build(M, PR, CAL=None):
    m = lambda i: M[i]
    pages = []
    add = lambda **kw: pages.append(kw)

    # ------------------------------------------------------------------ HOME (split)
    home = columns(
        column(group(
            p(f'<a href="/"><img src="{M[441]["url"]}" alt="Rui Dodat Fotografie"/></a>', "rd-brandmark"),
            p("Südpfalz · Rhein-Neckar · Karlsruhe", "rd-eyebrow"),
            h("Pferde", 2),
            p("Portraits, Pferd &amp; Reiter, Turnierfotos. Echte Verbindung, ehrliche Bilder."),
            buttons(button("Zur Pferdefotografie", "/pferde/")),
            cls="rd-half rd-half-pferde", bg={"url": M[320]["url"], "id": 320, "pos": "50% 40%"})),
        column(group(
            p("Germersheim · Speyer · Landau · Karlsruhe", "rd-eyebrow"),
            h("Hochzeit", 2),
            p("Ungestellte Reportagen voller Gefühl, vom ersten Blick bis zum letzten Tanz."),
            buttons(button("Zur Hochzeitsfotografie", "/hochzeit/", outline=True)),
            cls="rd-half rd-half-hochzeit rd-half-photo", bg=stock("boho-braut-sand", "50% 40%"))),
        cls="rd-split", align="full")
    bar = group(p('<a href="/ueber-mich/">Über mich</a> '
                  '<a href="/kontakt/">Kontakt</a> <a href="/impressum/">Impressum</a> <a href="/datenschutz/">Datenschutz</a>',
                  align="center"), cls="rd-splitbar", align="full")
    add(slug="home", title="Rui Dodat Fotografie", template="rd-blank", parent=None, content=home + "\n\n" + bar)

    # ================================================================== HORSE WORLD
    T = "rd-pferde"
    pferde = "\n\n".join([
        group(hero_video("pferde"),
              p("Pferdefotograf Südpfalz · Pferd, Reiter &amp; Familie", "rd-eyebrow"),
              h("Dein Pferd.<br>Eure Geschichte.", 1),
              p("Pferdeerfahrene Shootings vom eigenen Reitbetrieb aus: Portraits, Pferd &amp; Reiter, Fohlen, Verkaufsfotos, "
                "Stalltage und Turniere, rund um Germersheim, Speyer, Landau und Karlsruhe.", "rd-lead"),
              buttons(button("Preise ansehen", "/pferde/shootings-preise/"),
                      button("Portfolio", "/pferde/portfolio/", outline=True)),
              cls="rd-hero rd-hero-video", align="full", bg=poster_bg("pferde")),
        group(stats([(199, "€", "Ab 199 €", "Schritt: bis zu 60 Minuten, 5 bearbeitete Bilder. Faire Festpreise."),
                     (8, "Pferde", "Stalltage", "Bis zu 8 Pferde an einem Tag in deinem Stall, weniger Anfahrt für alle."),
                     (30, "km", "Anfahrt inklusive", "Rund um Germersheim komme ich ohne Aufpreis zu dir an den Stall."),
                     (100, "%", "Pferdeerfahren", "Eigener Reitbetrieb: ruhig, sicher und im Tempo deines Pferdes.")]),
              cls="rd-section rd-stats-section", align="full"),
        group(marquee(["Portraits", "Pferd &amp; Reiter", "Fohlen", "Verkaufsfotos", "Stalltage", "Turniere", "Pferd &amp; Familie"]), cls="rd-marquee-wrap", align="full", layout="default"),
        group(p("Portfolio", "rd-eyebrow", align="center"), h("Momente, die bleiben", 2, align="center"),
              gallery([(m(930), "Reiterin küsst ihren Schimmel im Abendlicht"), (m(481), "Haflinger Portrait"), (m(929), "Schimmel schnuppert an seiner Reiterin"),
                       (m(483), "Pferd vor schwarzem Hintergrund"), (m(933), "Reiterin lehnt auf dem Rücken ihres Schimmels"), (m(479), "Grauer Wallach im Blütenhain")],
                      cls="rd-gallery rd-mosaic"),
              buttons(button("Ganzes Portfolio", "/pferde/portfolio/", outline=True), cls="is-content-justification-center"),
              cls="rd-section", align="full"),
        group(p("Shootings", "rd-eyebrow", align="center"), h("Finde dein Paket", 2, align="center"),
              columns(*HORSE_CARDS(PR), cls="rd-cards"),
              p("Bezahlt wird bequem vor Ort beim Shooting.", "rd-small", align="center"),
              p('<a href="/pferde/shootings-preise/">Alle Preise, Stalltage, Turniere &amp; Extras →</a>', align="center"),
              cls="rd-section rd-tint", align="full"),
        group(columns(
            column(p("Turniere &amp; Pferdeevents", "rd-eyebrow"), h("Deine Ritte. Deine Schleife.", 2),
                   p("Ich fotografiere Dressur- und Springturniere, Vereinsfeste und Lehrgänge in der Region. "
                     "Bilder ab 15 € pro Stück oder 59 € im Paket, bestellt und bezahlt direkt bei mir."),
                   p("Veranstalter? Ich komme gern als offizieller Fotograf zu eurem Turnier."),
                   buttons(button("Turnier anfragen", "/kontakt/?w=pferde"))),
            column(gallery([(m(489), "Dressurprüfung"), (m(485), "Siegerehrung"), (m(487), "Pony-Prüfung"), (m(484), "Glückwunsch nach dem Ritt")])),
        ), cls="rd-section rd-dark", align="full"),
        group(p("Für Betriebe", "rd-eyebrow", align="center"), h("Fotos für die Pferdebranche", 2, align="center"),
              p("Hufschmiede, Trainer, Sattler, Tierärzte, Ställe und Züchter: Bilder für Website, Social Media und Verkauf.", align="center"),
              buttons(button("Angebote für Betriebe", "/pferde/shootings-preise/#betriebe", outline=True), cls="is-content-justification-center"),
              cls="rd-section", align="wide"),
        group(p("Mobil", "rd-eyebrow", align="center"), h("Ich komme zu euch", 2, align="center"),
              p("An euren Stall, auf eine Reitanlage eurer Wahl oder an einen schönen Ort in der Natur. Bei der Wahl der Location berate ich dich gern.", align="center"),
              buttons(button("Termin anfragen", "/kontakt/?w=pferde"), cls="is-content-justification-center"),
              cls="rd-band rd-parallax", align="full", bg={"url": M[475]["url"], "id": 475, "pos": "50% 50%"}),
    ])
    add(slug="pferde", title="Pferdefotografie", template=T, parent=None, content=pferde)

    add(slug="portfolio", title="Portfolio Pferde", template=T, parent="pferde", content="\n\n".join([
        group(p("Portfolio", "rd-eyebrow"), h("Pferde &amp; ihre Menschen", 1), cls="rd-section", align="wide"),
        group(h("Shooting im Spätsommer", 2),
              gallery([(m(930), "Reiterin küsst ihren Schimmel im Abendlicht"), (m(929), "Schimmel schnuppert an seiner Reiterin"), (m(932), "Reiterin kniet bei ihrem Schimmel"),
                       (m(931), "Reiterin küsst ihren Schimmel, Schwarzweiß"), (m(928), "Reiterin sitzt vor ihrem Schimmel"), (m(934), "Reiterin lehnt lachend auf ihrem Schimmel")]),
              h("Pferd &amp; Reiter", 2),
              gallery([(m(475), "Reiterin im Blütenhain"), (m(476), "Reiterin mit Schimmel"), (m(479), "Schimmel unter dem Sattel"),
                       (m(322), "Reiterin mit Schimmel im Wald"), (m(320), "Schimmel in der Bahn"), (m(327), "Reiterin mit Kuh und Pferd")]),
              h("Portraits &amp; Black Background", 2),
              gallery([(m(481), "Haflinger Portrait"), (m(483), "Portrait vor Schwarz mit Kranz"), (m(482), "Palomino vor Schwarz"), (m(480), "Schimmel Portrait")]),
              h("Turnier", 2),
              gallery([(m(489), "Dressurprüfung"), (m(486), "Siegerehrung"), (m(487), "Pony-Prüfung"), (m(488), "Vorbereitung am Abreiteplatz")]),
              cls="rd-section", align="wide"),
    ]))

    add(slug="shootings-preise", title="Preise", template=T, parent="pferde", content="\n\n".join([
        group(p("Preise", "rd-eyebrow"), h("Ehrliche Festpreise.", 1),
              p("Paket wählen, Termin anfragen, entspannt shooten. Bezahlt wird persönlich vor Ort beim Shooting. "
                "Bei schlechtem Wetter verschieben wir kostenlos.", "rd-lead"),
              cls="rd-section", align="wide"),
        group(h("Pferd, Reiter &amp; Familie", 2), columns(*HORSE_CARDS(PR, long=True), cls="rd-cards"),
              p("Alle Preise sind Endpreise. Gemäß § 19 UStG wird keine Umsatzsteuer berechnet. "
                "Die Zeiten sind Höchstwerte: Wir shooten im Tempo deines Pferdes, und wenn es genug hat, hören wir auf. Der Preis bleibt gleich.", "rd-small"),
              cls="rd-section rd-tint", align="full"),
        group(p("Beispiel", "rd-eyebrow"), h("So sieht ein Shooting aus", 2),
              p("Ein Spätsommerabend auf dem Stoppelfeld, Reiterin und Schimmel, ganz in Ruhe. Du bekommst deine Bilder bearbeitet, in Farbe und auf Wunsch in Schwarzweiß."),
              gallery([(m(930), "Reiterin küsst ihren Schimmel im Abendlicht"), (m(929), "Schimmel schnuppert an seiner Reiterin"),
                       (m(932), "Reiterin kniet bei ihrem Schimmel")]),
              p('<a href="/pferde/portfolio/">Mehr im Portfolio →</a>', "rd-small"),
              h("Vorher &amp; Nachher", 3, align="center"),
              p("Zieh den Regler: links das Original aus der Kamera, rechts das fertige Bild. Seil entfernt, Licht, Farben und Hintergrund bearbeitet.", "rd-small", align="center"),
              before_after(m(942), m(943), "Unbearbeitetes Original: Reiterin sitzt vor ihrem Schimmel am Longierseil",
                           "Bearbeitetes Bild: Reiterin sitzt vor ihrem Schimmel, Seil entfernt"),
              cls="rd-section", align="wide"),
        group(h("Spezial-Shootings", 2), columns(
            card("Black Background", "199 €", ["bis zu 45 Minuten", "4 Studio-Portraits", "mobiles Studio an deinem Stall, inkl. Auf- und Abbau", "ab 3 Pferden: 150 € pro Pferd"], "/kontakt/?w=pferde", "Termin anfragen"),
            card("Fohlen- &amp; Verkaufsfotos", "169 €", ["Exterieur + Bewegung", "8 Bilder", "Lieferung in 48 h", "Verkaufsvideo auf Anfrage"], "/kontakt/?w=pferde", "Termin anfragen"),
            card("Stalltag", "89 € / Pferd", ["6–8 Pferde an einem Tag", "bis zu 30 Min. und 3 Bilder pro Pferd", "Anfahrt wird geteilt, Organisator/in gratis"], "/pferde/stalltag/", "Mehr erfahren"),
            cls="rd-cards"), cls="rd-section", align="wide"),
        group(h("Turnierfotografie", 2),
              table(["Für Reiter/innen", "Preis"], [["Einzelbild digital", "15–25 € je nach Prüfung"], ["Bilderpaket (alle Bilder eines Ritts)", "59 €"]]),
              p("Bestellung und Bezahlung direkt bei mir am Turnier oder per Nachricht. "
                '<a href="/pferde/turnierfotografie/">Mehr zur Turnierfotografie und für Veranstalter →</a>', "rd-small"),
              cls="rd-section", align="wide"),
        group(h("Für Betriebe der Pferdebranche", 2),
              table(["Für wen", "Was", "Preis"], [
                  ["Hufschmiede &amp; Hufpfleger", "Arbeitsfotos, Portrait, Website &amp; Social Media", "ab 250 €"],
                  ["Trainer &amp; Reitlehrer", "Portrait, Action im Unterricht, Kurs- und Lehrgangsfotos", "ab 300 €"],
                  ["Sattler, Shops &amp; Marken", "Produktfotos, Fotos am Pferd für Shop und Katalog", "ab 15 € / Bild, Tagessatz ab 600 €"],
                  ["Tierärzte, Osteopathen, Physios", "Team- und Behandlungsfotos für die Website", "ab 300 €"],
                  ["Ställe &amp; Züchter", "Anlagenfotos, Verkaufspferde, Hengstfotos", "ab 400 €"],
                  ["Futtermittel- &amp; Pferdehandel", "Werbe- und Social-Media-Content", "nach Projekt"]]),
              p("Regelmäßiger Content, z. B. 4 Shootings im Jahr, zu Sonderkonditionen. Produktshootings auch bei euch vor Ort.", "rd-small"),
              cls="rd-section", align="wide", anchor="betriebe"),
        group(h("Extras &amp; Wandbilder", 2), table(["Extra", "Preis"], [
            ["Zusätzliches bearbeitetes Bild", "25 €"], ["Bilderpaket 5 / 10", "99 € / 179 €"], ["Alle Bilder (basis-bearbeitet)", "249 €"],
            ["Weiteres Pferd", "49 €"], ["Weitere Person", "25 €"], ["Drohnenaufnahmen", "59 €"], ["Express-Lieferung 48 h", "49 €"],
            ["Fine-Art-Print 20×30 / 30×45 / 40×60", "39 € / 69 € / 99 €"], ["Leinwand 60×90", "189 €"], ["Acryl/Alu-Dibond 60×90", "269 €"],
            ["Fotoalbum 20×20 (20 Seiten) / 30×30 (30 Seiten)", "149 € / 349 €"]]),
            cls="rd-section", align="wide"),
        group(h("Anfahrt", 2), travel_table("pferde"),
              p("Beim Stalltag wird die Anfahrt auf alle Pferde aufgeteilt.", "rd-small"),
              cls="rd-section", align="wide"),
        group(h("So läuft's", 2), columns(
            column(h("Anfragen", 3), p("Paket wählen und 2–3 Wunschtermine schicken, per WhatsApp oder Kontaktformular.")),
            column(h("Vorbereiten", 3), p("Du bekommst eine Checkliste: Putzen, Outfits, Location, Uhrzeit fürs beste Licht.")),
            column(h("Shooten", 3), p("Ganz entspannt, im Tempo deines Pferdes. Bezahlt wird vor Ort, bar oder per Überweisung.")),
            column(h("Auswählen", 3), p("Du suchst deine Lieblingsbilder aus, ich bearbeite sie und schicke sie dir.")),
            cls="rd-steps"), cls="rd-section rd-tint", align="full"),
        group(h("Gut zu wissen", 2),
              details("Was passiert bei schlechtem Wetter?", p("Wir verschieben kostenlos auf einen neuen Termin.")),
              details("Wann und wie bezahle ich?", p("Persönlich beim Shooting. Online musst du nichts bezahlen.")),
              details("Kann ich absagen?", p("Ja. Sag bitte so früh wie möglich Bescheid, dann finden wir einen neuen Termin.")),
              details("Darf ich die Bilder online teilen?", p("Ja, für private Zwecke und Social Media gern, mit Verlinkung. Gewerbliche Nutzung (z. B. Verkaufsanzeigen von Händlern) bitte vorher anfragen.")),
              details("Warum keine Mehrwertsteuer?", p("Als Kleinunternehmer gemäß § 19 UStG wird keine Umsatzsteuer berechnet. Alle Preise sind Endpreise.")),
              cls="rd-section", align="wide"),
    ]))

    add(slug="stalltag", title="Stalltag – Gruppenshooting", template=T, parent="pferde", content="\n\n".join([
        group(p("Stalltag", "rd-eyebrow"), h("Ein Tag. Ein Stall. Viele glückliche Pferdemenschen.", 1),
              p("Organisiere einen Shooting-Tag an deinem Stall: 6–8 Pferde an einem Tag, jede/r bezahlt den eigenen Platz vor Ort. Du als Organisator/in bekommst dein Shooting gratis.", "rd-lead"),
              cls="rd-hero rd-hero-short", align="full", bg={"url": M[327]["url"], "id": 327}),
        group(columns(
            column(h("Für Teilnehmer", 3), ul(["89 € pro Pferd, bezahlt vor Ort", "bis zu 30 Minuten", "3 bearbeitete Bilder", "weitere Bilder: 25 € / Bild, 5 für 99 €"]),
                   buttons(button("Platz anfragen", "/kontakt/?w=pferde"))),
            column(h("Für Organisator/innen", 3), ul(["ab 5 Pferden", "dein eigenes Shooting gratis", "Anfahrt wird auf alle geteilt", "ich stelle dir einen Aushang (PDF) für den Stall bereit"]),
                   buttons(button("Stalltag anfragen", "/kontakt/?w=pferde", outline=True))),
            cls="rd-cards"), cls="rd-section", align="wide"),
    ]))

    add(slug="turnierfotografie", title="Turnierfotografie", template=T, parent="pferde", content="\n\n".join([
        group(p("Turnierfotografie", "rd-eyebrow"), h("Jeder Ritt. Jede Schleife.", 1),
              p("Ich fotografiere Dressur- und Springturniere, Vereinsfeste und Lehrgänge in der Region. "
                "Deine Bilder bestellst und bezahlst du direkt bei mir, am Turnier oder per Nachricht.", "rd-lead"),
              buttons(button("Bilder anfragen", "/kontakt/?w=pferde"), button("WhatsApp", WA, outline=True)),
              cls="rd-hero", align="full", bg={"url": M[489]["url"], "id": 489}),
        group(h("Preise für Reiter/innen", 2), table(["Was", "Preis"], [
            ["Einzelbild digital (volle Auflösung)", "15–25 € je nach Prüfung"], ["Bilderpaket: alle Bilder eines Ritts", "59 €"],
            ["Fine-Art-Print 20×30 / 30×45", "39 € / 69 €"], ["Leinwand 60×90", "189 €"]]),
            p("Gemäß § 19 UStG wird keine Umsatzsteuer berechnet. Bezahlung bar oder per Überweisung.", "rd-small"),
            cls="rd-section", align="wide"),
        group(h("Nächste Turniere", 2),
              p("Noch keine Termine veröffentlicht. Folge mir auf Instagram oder frag per WhatsApp, welche Turniere ich als Nächstes fotografiere.", "rd-note"),
              cls="rd-section rd-tint", align="full"),
        group(p("Für Veranstalter", "rd-eyebrow"), h("Professionelle Fotos für euer Turnier, ohne Kosten", 2),
              ul(["Kostenlose Fotobegleitung bei Turnieren ab ca. 250 Starts (kleinere Turniere: 150 € Pauschale)",
                  "20 Bilder für eure Social-Media-Kanäle und Presse inklusive",
                  "Ein Gratis-Bild für jede/n Prüfungssieger/in",
                  "Verkauf an die Reiter/innen komplett über mich"]),
              buttons(button("Turnier anfragen", "/kontakt/?w=pferde")),
              cls="rd-section", align="wide"),
    ]))

    add(slug="dein-event", title="Deine Bilder", template=T, parent="pferde", content="\n\n".join([
        group(p("Deine Bilder", "rd-eyebrow", align="center"), h("Hier sind deine Bilder.", 1, align="center"),
              p("Gib das Passwort ein, das du von mir bekommen hast. Dann kannst du alle Bilder ansehen und in voller Auflösung herunterladen.", "rd-lead", align="center"),
              code_form("Passwort", "Passwort eingeben"),
              cls="rd-section rd-event-hero", align="full"),
        group(h("So geht's", 2, align="center"),
              event_types([("1", "Passwort eingeben", "Du bekommst es von mir per WhatsApp oder E-Mail.", ["Groß- und Kleinschreibung ist egal"]),
                           ("2", "Bilder ansehen", "Alle Bilder deines Shootings, nach Kapiteln sortiert.", ["Favoriten mit dem Herz markieren"]),
                           ("3", "Herunterladen", "Einzeln oder alle auf einmal als ZIP.", ["Volle Auflösung, ohne Wasserzeichen"])]),
              cls="rd-section rd-tint", align="full"),
        group(h("Passwort verloren?", 2, align="center"),
              p("Kein Problem. Schreib mir kurz mit Datum und Name, ich schicke es dir erneut.", align="center"),
              buttons(button("WhatsApp", WA), button("Kontakt", "/kontakt/?w=pferde", outline=True), cls="is-content-justification-center"),
              cls="rd-section", align="wide"),
    ]))
    add(slug="deine-bilder", title="Deine Bilder", template=T, parent="pferde",
        content=group(raw("<!-- wp:shortcode -->\n[rd_gallery]\n<!-- /wp:shortcode -->"), cls="rd-section rd-gal-page", align="full"))

    add(slug="ablauf-faq", title="Ablauf & FAQ Pferde", template=T, parent="pferde", content="\n\n".join([
        group(p("Ablauf &amp; FAQ", "rd-eyebrow"), h("Gut vorbereitet zum Shooting", 1), cls="rd-section", align="wide"),
        group(h("Checkliste", 2), ul(["Pferd gründlich putzen, Mähne &amp; Schweif verlesen, Hufe fetten",
                                       "Sauberes, gut sitzendes Halfter oder Trense (Leder wirkt am schönsten)",
                                       "Outfits in ruhigen Farben, die zur Fellfarbe passen. Keine großen Logos",
                                       "Eine helfende Person für Ohren &amp; Aufmerksamkeit (Futtertüte, Rascheln)",
                                       "Location: ruhige Wiese, Allee, Waldrand oder eure Halle. Ich berate dich gern"]),
              details("Wann ist das beste Licht?", p("Die Stunde vor Sonnenuntergang (Golden Hour) oder ein leicht bewölkter Tag.")),
              details("Mein Pferd ist unruhig, geht das trotzdem?", p("Ja. Wir nehmen uns Zeit, machen Pausen und arbeiten im Tempo deines Pferdes.")),
              details("Wie bekomme ich die Bilder?", p("Per Download-Link in voller Auflösung, plus Web-Versionen für Social Media.")),
              details("Wann bezahle ich?", p("Persönlich beim Shooting, bar oder per Überweisung. Online wird nichts bezahlt.")),
              cls="rd-section", align="wide"),
    ]))

    # ================================================================== WEDDING WORLD
    T = "rd-hochzeit"
    ph = lambda txt="Hochzeitsbild folgt": group(p(txt, align="center"), cls="rd-placeholder")
    add(slug="hochzeit", title="Hochzeitsfotografie", template=T, parent=None, content="\n\n".join([
        group(hero_video("hochzeit"),
              p("Hochzeitsfotografie · Land- &amp; Pferdehochzeiten · Südpfalz", "rd-eyebrow"),
              h("Euer Tag.<br><em>Für immer.</em>", 1),
              p("Natürliche, ungestellte Hochzeitsreportagen: die Tränen beim Ja-Wort, das Lachen der Gäste, der letzte Tanz.", "rd-lead"),
              buttons(button("Verfügbarkeit &amp; Preis", "#verfuegbarkeit"), button("Pakete &amp; Preise", "/hochzeit/pakete-preise/", outline=True)),
              cls="rd-hero rd-hero-boho rd-hero-video", align="full", bg=poster_bg("hochzeit")),
        group(p("Verfügbarkeit &amp; Preis", "rd-eyebrow", align="center"),
              h("Bin ich an <em>eurem Tag</em> frei?", 2, align="center"),
              p("Datum eingeben und sofort sehen, ob ich frei bin, mit allen Paketen und Preisen.", align="center"),
              raw("<!-- wp:shortcode -->\n[rd_wedding_check]\n<!-- /wp:shortcode -->"),
              cls="rd-section rd-tint rd-wcheck-section", align="full", anchor="verfuegbarkeit"),
        group(stats([(72, "h", "Sneak Peek", "Die ersten Lieblingsbilder habt ihr schon wenige Tage nach der Hochzeit."),
                     (9, "Std", "Ganztags dabei", "Vom Getting Ready bis zur Party, ungestellt und zurückhaltend."),
                     (24, "h", "Schnelle Antwort", "Auf jede Anfrage melde ich mich innerhalb eines Tages."),
                     (1, "Paar", "Pro Tag", "Ich fotografiere nur eine Hochzeit am Tag. Eure.")]),
              cls="rd-section rd-stats-section", align="full"),
        group(marquee(["Standesamt", "Freie Trauung", "Boho", "Scheunenhochzeit", "Getting Ready", "Erster Tanz", "Paarshooting"]), cls="rd-marquee-wrap", align="full", layout="default"),
        group(p("Einblicke", "rd-eyebrow", align="center"), h("Echte <em>Gefühle</em>, kein Gestell", 2, align="center"),
              gallery([stock_img("boho-paar-felsen", "Brautpaar auf Felsen"), stock_img("boho-brautstrauss", "Boho-Brautstrauß"),
                       stock_img("boho-paar-sonnenuntergang", "Brautpaar im Abendlicht"), stock_img("boho-tischdeko", "Boho-Tischdeko"),
                       stock_img("boho-paar-feld", "Hände des Brautpaars"), stock_img("boho-trockenblumen", "Trockenblumen")],
                      cls="rd-gallery rd-mosaic"),
              p("Symbolbilder von Unsplash. Eigene Hochzeitsreportagen folgen.", "rd-small", align="center"),
              cls="rd-section", align="full"),
        group(p("Pakete", "rd-eyebrow", align="center"), h("Für jede Hochzeit das <em>passende</em> Paket", 2, align="center"),
              columns(*WEDDING_CARDS("/hochzeit/pakete-preise/", "Details"), cls="rd-cards rd-cards-2"),
              p("Bezahlt wird persönlich, nicht online. Gebucht wird nach einem Kennenlernen.", "rd-small", align="center"),
              cls="rd-section rd-tint", align="full"),
        group(p("Lasst uns reden", "rd-eyebrow", align="center"), h("Erzählt mir <em>von euch</em>", 2, align="center"),
              p("Euer Datum ist noch frei? Schreibt mir, ich melde mich innerhalb von 24 Stunden.", align="center"),
              buttons(button("Verfügbarkeit prüfen", "#verfuegbarkeit"), button("WhatsApp", WA, outline=True), cls="is-content-justification-center"),
              cls="rd-band rd-parallax rd-band-boho", align="full", bg=stock("boho-paar-sonnenuntergang", "50% 40%")),
    ]))

    add(slug="pakete-preise", title="Hochzeit – Pakete & Preise", template=T, parent="hochzeit", content="\n\n".join([
        group(p("Pakete &amp; Preise", "rd-eyebrow"), h("Transparent. <em>Ohne versteckte Kosten.</em>", 1),
              p("Erst lernen wir uns persönlich kennen, dann bekommt ihr euren Vertrag. Mit der Anzahlung von 30 % ist euer Tag fest reserviert, der Rest ist 4 Wochen vor der Hochzeit fällig. Bezahlt wird persönlich, nicht online.", "rd-lead"),
              buttons(button("Termin anfragen", "/kontakt/?w=hochzeit"), button("WhatsApp", WA, outline=True)),
              cls="rd-hero rd-hero-short rd-hero-boho", align="full", bg=stock("boho-paar-feld", "50% 50%")),
        group(columns(*WEDDING_CARDS("/kontakt/?w=hochzeit", "Termin anfragen", long=True), cls="rd-cards rd-cards-2"),
            p("Nebensaison (November–März) und Montag–Donnerstag: <strong>10 % Rabatt</strong> auf alle Pakete.", "rd-note"),
            cls="rd-section rd-tint", align="full"),
        group(h("Extras", 2), table(["Extra", "Preis"], [
            ["Zusätzliche Stunde", "190 €"], ["Zweitfotograf/in", "590 €"], ["Drohnenaufnahmen", "249 €"],
            ["Express-Lieferung (2 Wochen)", "190 €"], ["Fotobox mit 30 Fine-Art-Prints", "249 €"],
            ["Hochzeitsalbum 30×30, 40 Seiten", "590 €"], ["Elternalbum 20×20", "149 €"],
            ["Verlobungs-/Paarshooting", "290 € (190 € mit Hochzeit)"]]),
            cls="rd-section", align="wide"),
        group(h("Anfahrt", 2), travel_table("hochzeit"), cls="rd-section", align="wide"),
        group(h("So läuft's", 2), columns(
            column(h("Anfragen", 3), p("Datum &amp; Location schicken. Ich prüfe sofort, ob ich an eurem Tag frei bin.")),
            column(h("Kennenlernen", 3), p("Wir treffen uns persönlich, bei einem Kaffee oder an eurer Location: Ablauf, Wünsche, Paket.")),
            column(h("Vertrag", 3), p("Nach dem Treffen bekommt ihr euren Vertrag. In Ruhe lesen, unterschreiben, 30 % persönlich anzahlen. Euer Datum ist fest.")),
            column(h("Genießen", 3), p("Sneak Peek in 72 h, komplette Galerie in 4–6 Wochen.")),
            cls="rd-steps"), cls="rd-section rd-tint", align="full"),
        group(h("Häufige Fragen", 2),
              details("Warum erst ein persönliches Treffen?", p("Bei einer Hochzeit müsst ihr euch bei eurem Fotografen wohlfühlen. Deshalb buche ich Hochzeiten nur nach einem persönlichen Kennenlernen, nicht online. So könnt ihr alle Fragen stellen, bevor ihr unterschreibt.")),
              details("Was passiert, wenn ihr absagen müsst?", p("Bis 6 Monate vor der Hochzeit behalte ich die Anzahlung (30 %), bis 8 Wochen vorher 50 %, danach 80 % des Paketpreises. Weist ihr nach, dass mir ein geringerer Schaden entstanden ist, zahlt ihr nur diesen. Alles steht transparent im Vertrag.")),
              details("Und wenn du krank wirst?", p("Für den Notfall organisiere ich eine/n erfahrene/n Kollegin/Kollegen als Ersatz. Das ist im Vertrag geregelt.")),
              details("Wie viele Bilder bekommen wir?", p("Alle gelungenen Bilder des Tages, sorgfältig ausgewählt und bearbeitet, in Farbe und ausgewählte in Schwarzweiß.")),
              details("Wie bezahlen wir?", p("Persönlich bei mir: Anzahlung bei Vertragsschluss, Rest 4 Wochen vor der Hochzeit, bar oder per Überweisung. Online wird nichts bezahlt.")),
              details("Warum keine Mehrwertsteuer?", p("Als Kleinunternehmer gemäß § 19 UStG wird keine Umsatzsteuer berechnet. Alle Preise sind Endpreise.")),
              cls="rd-section", align="wide"),
    ]))

    add(slug="paarshootings", title="Verlobungs- & Paarshootings", template=T, parent="hochzeit", content="\n\n".join([
        group(p("Paarshootings", "rd-eyebrow"), h("Nur ihr zwei. <em>Und das Licht.</em>", 1),
              p("Im Weinberg, am Rhein oder im Pfälzerwald, vor oder nach der Hochzeit.", "rd-lead"),
              cls="rd-hero rd-hero-short rd-hero-boho", align="full", bg=stock("boho-paar-sonnenuntergang", "50% 30%")),
        group(columns(
            card("Verlobung / Paar", "290 €", ["ca. 1 Stunde", "ca. 50 bearbeitete Bilder", "perfekt für Save-the-Date", "mit Hochzeitsbuchung nur 190 €"], "/kontakt/?w=hochzeit", "Termin anfragen"),
            card("After-Wedding", "390 €", ["ca. 2 Stunden", "ca. 80 bearbeitete Bilder", "im Hochzeitsoutfit, ohne Zeitdruck"], "/kontakt/?w=hochzeit", "Termin anfragen"),
            cls="rd-cards"), cls="rd-section rd-tint", align="full"),
    ]))

    add(slug="alben-wandbilder", title="Alben & Wandbilder", template=T, parent="hochzeit", content="\n\n".join([
        group(p("Alben &amp; Wandbilder", "rd-eyebrow"), h("Erinnerungen <em>zum Anfassen</em>", 1),
              p("Handgefertigte Fine-Art-Alben und Wandbilder in Galeriequalität, für Hochzeiten und Pferdemenschen.", "rd-lead"),
              cls="rd-section", align="wide"),
        group(table(["Produkt", "Preis"], [
            ["Hochzeitsalbum 30×30 cm, 40 Seiten, Leinen/Leder, Layout inkl.", "590 €"], ["Weitere Doppelseite", "15 €"],
            ["Elternalbum 20×20 cm", "149 €"], ["Pferdealbum 20×20 cm, 20 Seiten", "149 €"], ["Pferdealbum 30×30 cm, 30 Seiten", "349 €"],
            ["Fine-Art-Print 20×30 / 30×45 / 40×60", "39 € / 69 € / 99 €"], ["Leinwand 60×90", "189 €"], ["Acryl / Alu-Dibond 60×90", "269 €"]]),
            p("Bestellung persönlich oder per Nachricht, Bezahlung bei Abholung oder per Überweisung. Versand innerhalb Deutschlands 6,90 €, ab 150 € versandkostenfrei.", "rd-small"),
            buttons(button("Album oder Wandbild anfragen", "/kontakt/")),
            cls="rd-section", align="wide"),
    ]))

    wedding_event = lambda: "\n\n".join([
        group(p("Eure Bilder", "rd-eyebrow", align="center"), h("Hier sind <em>eure Bilder.</em>", 1, align="center"),
              p("Gebt das Passwort ein, das ihr von mir bekommen habt. Dann könnt ihr alle Bilder ansehen und in voller Auflösung herunterladen.", "rd-lead", align="center"),
              code_form("Passwort", "Passwort eingeben"),
              cls="rd-section rd-event-hero", align="full"),
        group(h("So geht's", 2, align="center"),
              event_types([("1", "Passwort eingeben", "Ihr bekommt es von mir per WhatsApp oder E-Mail.", ["Groß- und Kleinschreibung ist egal"]),
                           ("2", "Bilder ansehen", "Euer ganzer Tag, nach Kapiteln sortiert.", ["Favoriten mit dem Herz markieren"]),
                           ("3", "Herunterladen", "Einzeln oder alle auf einmal als ZIP.", ["Volle Auflösung, ohne Wasserzeichen"])]),
              cls="rd-section rd-tint", align="full"),
        group(h("Passwort verloren?", 2, align="center"),
              p("Schreibt mir kurz mit Hochzeitsdatum und Namen, ich schicke es euch erneut.", align="center"),
              buttons(button("WhatsApp", WA), button("Kontakt", "/kontakt/?w=hochzeit", outline=True), cls="is-content-justification-center"),
              cls="rd-section", align="wide"),
    ])
    add(slug="eure-bilder", title="Eure Bilder", template=T, parent="hochzeit",
        content=group(raw("<!-- wp:shortcode -->\n[rd_gallery]\n<!-- /wp:shortcode -->"), cls="rd-section rd-gal-page", align="full"))
    add(slug="euer-event", title="Eure Bilder", template=T, parent="hochzeit", content=wedding_event())
    add(slug="gaestegalerie", title="Gästegalerie", template=T, parent="hochzeit", content=wedding_event())

    add(slug="echte-hochzeiten", title="Echte Hochzeiten", template=T, parent="hochzeit", content="\n\n".join([
        group(p("Echte Hochzeiten", "rd-eyebrow"), h("Geschichten, <em>die bleiben</em>", 1),
              p("Hier erzähle ich bald ausgewählte Hochzeiten als ganze Geschichte, vom Getting Ready bis zur Party.", "rd-lead"),
              cls="rd-section", align="wide"),
        group(gallery([stock_img("boho-paar-felsen", "Brautpaar auf Felsen"), stock_img("boho-brautstrauss", "Boho-Brautstrauß"),
                       stock_img("boho-paar-sonnenuntergang", "Brautpaar im Abendlicht"), stock_img("boho-tischdeko", "Boho-Tischdeko"),
                       stock_img("boho-paar-feld", "Hände des Brautpaars"), stock_img("boho-trockenblumen", "Trockenblumen")],
                      cls="rd-gallery rd-mosaic"),
              p("Symbolbilder von Unsplash. Eigene Hochzeitsreportagen folgen.", "rd-small", align="center"),
              cls="rd-section", align="full"),
    ]))

    add(slug="ablauf-faq-hochzeit", title="Ablauf & FAQ Hochzeit", template=T, parent="hochzeit", content="\n\n".join([
        group(p("Ablauf &amp; FAQ", "rd-eyebrow"), h("Entspannt <em>durch den Tag</em>", 1), cls="rd-section", align="wide"),
        group(h("Tipps für euren Zeitplan", 2), ul([
            "Plant 30–45 Minuten fürs Paarshooting ein, gern zur Golden Hour",
            "Gruppenfotos direkt nach der Trauung, mit vorbereiteter Liste und einer Person, die ruft",
            "Getting Ready bei Tageslicht in einem aufgeräumten Raum",
            "Regenplan? Kein Problem: Schirme habe ich dabei, und Regenbilder sind oft die schönsten"]),
            details("Wann sollten wir buchen?", p("Für Samstage im Mai–September am besten 9–15 Monate vorher.")),
            details("Fotografierst du auch freie Trauungen und Destination Weddings?", p("Ja, sehr gern. Anfahrt nach Zonen, Destination auf Anfrage.")),
            cls="rd-section", align="wide"),
    ]))

    # ================================================================== SHARED
    T = "rd-main"
    add(slug="verfuegbarkeit", title="Freie Termine", template=T, parent=None, content="\n\n".join([
        cal_block(CAL, "pferde"),
        group(buttons(button("Pferde-Shooting anfragen", "/kontakt/?w=pferde"), button("Hochzeit anfragen", "/hochzeit/pakete-preise/", outline=True),
                      cls="is-content-justification-center"),
              cls="rd-section", align="wide"),
    ]))

    add(slug="gutscheine", title="Gutscheine", template=T, parent=None, content="\n\n".join([
        group(p("Gutscheine", "rd-eyebrow"), h("Verschenke Erinnerungen", 1),
              p("Für ein Pferde-Shooting, ein Paarshooting oder einen Wunschbetrag. Als PDF zum Ausdrucken innerhalb von 24 Stunden, 3 Jahre gültig.", "rd-lead"),
              cls="rd-section", align="wide"),
        group(columns(
            card("Pony-Shooting", "149 €", ["45 Minuten, 5 Bilder", "als Gutschein-PDF"], PR["gutschein-pony"]["url"], "Verschenken"),
            card("Warmblut-Shooting", "289 €", ["90 Minuten, 12 Bilder", "als Gutschein-PDF"], PR["gutschein-warmblut"]["url"], "Verschenken", popular=True),
            card("Wunschbetrag", "ab 50 €", ["50 / 100 / 150 / 250 €", "für alle Shootings &amp; Produkte"], "/produkt-kategorie/gutscheine/", "Betrag wählen"),
            cls="rd-cards"), cls="rd-section", align="wide"),
    ]))

    add(slug="ueber-mich", title="Über mich", template=T, parent=None, content="\n\n".join([
        group(columns(
            column(img(m(477), "Pferd und Mensch im Blütenhain")),
            column(p("Über mich", "rd-eyebrow"), h("Hallo, ich bin Rui.", 1),
                   p("Pferde sind mein Alltag: Mit unserem eigenen Hof, Reitbetrieb und unserer Reitschule in der Südpfalz verbringe ich jeden Tag mit ihnen. "
                     "Genau das bringe ich in meine Fotografie mit. Ich weiß, wie Pferde ticken, bleibe ruhig, wenn es mal unruhig wird, und arbeite in ihrem Tempo."),
                   p("Ich fotografiere Pferde mit ihren Menschen und Familien, Turniere und Stalltage, und Hochzeiten, am liebsten auf dem Land und gern auch mit Pferd."),
                   p("Meine Basis ist Germersheim. Ich bin in der Südpfalz, in der Rhein-Neckar-Region, rund um Karlsruhe und bis ins Rhein-Main-Gebiet unterwegs "
                     "und komme mit meiner Kamera zu euch."),
                   buttons(button("Pferdefotografie", "/pferde/"), button("Hochzeitsfotografie", "/hochzeit/", outline=True))),
        ), cls="rd-section", align="wide"),
        group(p("Was mir wichtig ist", "rd-eyebrow", align="center"), h("Ehrliche Bilder, entspannte Shootings", 2, align="center"),
              columns(
                  column(h("Pferdekompetenz", 3), p("Eigener Reitbetrieb, sicherer Umgang mit Pferden. Dein Pferd steht im Mittelpunkt, nicht die Kamera.")),
                  column(h("Faire Festpreise", 3), p("Klare Pakete ohne versteckte Kosten. Bezahlt wird persönlich, vor Ort.")),
                  column(h("Aus der Region", 3), p("Verwurzelt in der Südpfalz und vernetzt mit Reitvereinen, Ställen und Reitschul-Familien.")),
                  cls="rd-usps"),
              cls="rd-section rd-tint", align="full"),
    ]))

    add(slug="kontakt", title="Kontakt", template=T, parent=None, content="\n\n".join([
        group(p("Kontakt", "rd-eyebrow"), h("Lass uns sprechen", 1),
              p("Frage, Terminanfrage oder Turnier? Schreib mir. Ich antworte innerhalb von 24 Stunden.", "rd-lead"),
              buttons(button("WhatsApp: 0173 8505311", WA)),
              raw('<!-- wp:jetpack/contact-form {"subject":"Anfrage über ruidodat.com","customThankyou":"message","customThankyouMessage":"Danke! Ich melde mich innerhalb von 24 Stunden."} -->\n'
                  '<!-- wp:jetpack/field-name {"label":"Name","required":true} /-->\n\n'
                  '<!-- wp:jetpack/field-email {"label":"E-Mail","required":true} /-->\n\n'
                  '<!-- wp:jetpack/field-telephone {"label":"Telefon"} /-->\n\n'
                  '<!-- wp:jetpack/field-select {"label":"Worum geht es?","required":true,"options":["Pferde-Shooting","Familie mit Pferd","Stalltag","Turnier / Pferdeevent","Betrieb (Hufschmied, Trainer, Stall …)","Hochzeit","Sonstiges"]} /-->\n\n'
                  '<!-- wp:jetpack/field-date {"label":"Wunschdatum / Hochzeitsdatum"} /-->\n\n'
                  '<!-- wp:jetpack/field-text {"label":"Ort / Location"} /-->\n\n'
                  '<!-- wp:jetpack/field-textarea {"label":"Nachricht","required":true} /-->\n\n'
                  '<!-- wp:jetpack/field-consent {"consentType":"explicit","implicitConsentMessage":"","explicitConsentMessage":"Ich bin einverstanden, dass meine Angaben zur Bearbeitung der Anfrage gespeichert werden (siehe Datenschutzerklärung)."} /-->\n\n'
                  '<!-- wp:jetpack/button {"element":"button","text":"Absenden"} /-->\n'
                  '<!-- /wp:jetpack/contact-form -->'),
              cls="rd-section", align="wide"),
    ]))

    legal_note = lambda what: p(f"⚠️ <strong>{what} folgt.</strong> Bitte rechtssicheren Text einfügen, z. B. über den AGB-Service der IT-Recht Kanzlei oder des Händlerbunds (automatische Updates).", "rd-note")
    add(slug="impressum", title="Impressum", template=T, parent=None, content=group(
        h("Impressum", 1), p("Angaben gemäß § 5 DDG"),
        p("Rui Dodat · Rui Dodat Fotografie<br>Kaltenbacher Hof 0<br>67482 Freimersheim<br>Deutschland"),
        p('Telefon: 0173 8505311<br>E-Mail: <a href="mailto:ruidodat@gmail.com">ruidodat@gmail.com</a>'),
        p("Umsatzsteuer: Kleinunternehmer gemäß § 19 UStG, daher keine USt-IdNr."),
        p("Verbraucherstreitbeilegung: Ich bin nicht bereit und nicht verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen."),
        h("Bildnachweise", 2),
        p("Alle Pferdefotos: Rui Dodat. Symbolbilder Hochzeit (Unsplash-Lizenz): " + ", ".join(
            f'<a href="https://unsplash.com/photos/{v["unsplash"]}">{v["credit"]}</a>' for v in STOCK.values()) + "."),
        cls="rd-section", align="wide"))
    add(slug="datenschutz", title="Datenschutzerklärung", template=T, parent=None, content=group(h("Datenschutzerklärung", 1), legal_note("Datenschutzerklärung"), cls="rd-section", align="wide"))
    add(slug="agb", title="AGB", template=T, parent=None, content=group(h("Allgemeine Geschäftsbedingungen", 1), legal_note("AGB-Text"),
        p("Wichtige Punkte für die AGB: Wetter-Verschiebung, Stornoregeln (14 Tage), Hochzeit nur nach persönlichem Vorgespräch mit Storno-Staffel 30/50/80 %, Nutzungsrechte (privat), Gutscheine 3 Jahre gültig.", "rd-small"),
        cls="rd-section", align="wide"))
    add(slug="widerruf", title="Widerrufsbelehrung", template=T, parent=None, content=group(h("Widerrufsbelehrung", 1), legal_note("Widerrufsbelehrung"), cls="rd-section", align="wide"))
    add(slug="cookie-richtlinie-eu", title="Cookie-Richtlinie (EU)", template=T, parent=None, content=group(
        h("Cookie-Richtlinie (EU)", 1),
        raw('<!-- wp:shortcode -->\n[cmplz-document type="cookie-statement" region="eu"]\n<!-- /wp:shortcode -->'),
        cls="rd-section", align="wide"))
    add(slug="versand-zahlung", title="Versand & Zahlung", template=T, parent=None, content=group(
        h("Versand &amp; Zahlung", 1),
        ul(["Shootings: Bilder kommen digital per E-Mail oder Download-Link",
            "Alben, Prints, Wandbilder: Versand innerhalb Deutschlands 6,90 €, ab 150 € versandkostenfrei",
            "Lieferzeit Alben &amp; Wandbilder: ca. 2–3 Wochen nach Freigabe",
            "Zahlung: persönlich vor Ort beim Termin (bar oder Überweisung). Auf dieser Website wird nichts verkauft oder bezahlt."]),
        p("Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.", "rd-small"),
        cls="rd-section", align="wide"))
    return pages
