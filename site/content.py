"""Page content (German). build(M, PR) -> list of page dicts.
M: media id -> {"id","url"} ; PR: product key -> {"url","price"}"""
from blocks import *

WA = "https://wa.me/491738505311"


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


def travel_table(world):
    rows = [
        ["1 · bis 30 km", "Speyer, Landau, Karlsruhe, Bruchsal, Hockenheim, Kandel", "inklusive"],
        ["2 · 30–60 km", "Mannheim, Heidelberg, Ludwigshafen, Neustadt, Rastatt, Pforzheim", "25 €" if world == "pferde" else "inklusive"],
        ["3 · 60–100 km", "Kaiserslautern, Pirmasens, Baden-Baden, Heilbronn, Worms", "49 €"],
        ["4 · über 100 km", "Stuttgart, Frankfurt, Saarland …", "49 € + 0,40 €/km ab km 100 (hin & zurück)"
         + ("<br>+ Übernachtung (max. 120 €) ab 200 km" if world == "hochzeit" else "")],
    ]
    return table(["Zone (ab Germersheim)", "Beispiele", "Anfahrt"], rows)


def build(M, PR):
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
            cls="rd-half rd-half-hochzeit")),
        cls="rd-split", align="full")
    bar = group(p('<a href="/gutscheine/">Gutscheine</a> <a href="/ueber-mich/">Über mich</a> '
                  '<a href="/kontakt/">Kontakt</a> <a href="/impressum/">Impressum</a> <a href="/datenschutz/">Datenschutz</a>',
                  align="center"), cls="rd-splitbar", align="full")
    add(slug="home", title="Rui Dodat Fotografie", template="rd-blank", parent=None, content=home + "\n\n" + bar)

    # ================================================================== HORSE WORLD
    T = "rd-pferde"
    pferde = "\n\n".join([
        group(p("Pferdefotografie · Südpfalz &amp; Umgebung", "rd-eyebrow"),
              h("Dein Pferd.<br>Eure Geschichte.", 1),
              p("Ruhige, pferdeerfahrene Shootings: Portraits, Pferd &amp; Reiter, Black Background und Turnierfotografie, "
                "rund um Germersheim, Speyer, Landau und Karlsruhe.", "rd-lead"),
              buttons(button("Shooting buchen", "/pferde/shootings-preise/"),
                      button("Turnierfotos", "/pferde/turnierfotografie/", outline=True)),
              cls="rd-hero", align="full", bg={"url": M[478]["url"], "id": 478, "pos": "50% 45%"}),
        group(columns(
            column(h("Ruhig &amp; pferdeerfahren", 3), p("Ich arbeite mit Geduld und im Tempo deines Pferdes, ohne Stress.")),
            column(h("Fertig in 10 Tagen", 3), p("Deine Online-Auswahlgalerie ist innerhalb von 10 Tagen da. Express in 48 h möglich.")),
            column(h("Faire Festpreise", 3), p("Alles vorab online bezahlt. Anfahrt bis 30 km inklusive, Wetter-Verschiebung kostenlos.")),
            cls="rd-usps"), cls="rd-section", align="wide"),
        group(h("Portfolio", 2, align="center"),
              gallery([(m(479), "Grauer Wallach im Blütenhain"), (m(481), "Haflinger Portrait"), (m(483), "Pferd vor schwarzem Hintergrund"),
                       (m(480), "Portrait im Frühling"), (m(322), "Reiterin mit Schimmel"), (m(477), "Reiterin kniet bei ihrem Pferd")]),
              buttons(button("Ganzes Portfolio", "/pferde/portfolio/", outline=True), cls="is-content-justification-center"),
              cls="rd-section", align="full"),
        group(p("Shootings", "rd-eyebrow", align="center"), h("Finde dein Paket", 2, align="center"),
              columns(
                  card("Pony", "149 €", ["45 Minuten", "1 Pferd", "5 bearbeitete Bilder"], PR["pony"]["url"]),
                  card("Warmblut", "289 €", ["90 Minuten", "Pferd &amp; Reiter, 2 Outfits", "12 bearbeitete Bilder"], PR["warmblut"]["url"], popular=True),
                  card("Kaltblut", "479 €", ["2,5 Stunden, Golden Hour", "25 Bilder + alle weiteren", "Fine-Art-Print 30×45"], PR["kaltblut"]["url"]),
                  cls="rd-cards"),
              p('<a href="/pferde/shootings-preise/">Alle Preise, Extras &amp; Stalltage →</a>', align="center"),
              cls="rd-section rd-tint", align="full"),
        group(columns(
            column(p("Turnierfotografie", "rd-eyebrow"), h("Deine Ritte. Am selben Abend online.", 2),
                   p("Ich fotografiere Dressur- und Springturniere in der Region. Finde deine Bilder nach Startnummer oder sichere dir vorab die Turnier-Flatrate."),
                   buttons(button("Turnierfotos &amp; Kalender", "/pferde/turnierfotografie/"))),
            column(gallery([(m(489), "Dressurprüfung"), (m(485), "Siegerehrung"), (m(487), "Pony-Prüfung"), (m(484), "Glückwunsch nach dem Ritt")])),
        ), cls="rd-section rd-dark", align="full"),
        group(h("Das perfekte Geschenk", 2, align="center"),
              p("Gutscheine für Shootings oder Wunschbetrag, sofort als PDF zum Ausdrucken.", align="center"),
              buttons(button("Gutschein verschenken", "/gutscheine/"), cls="is-content-justification-center"),
              cls="rd-section", align="wide"),
    ])
    add(slug="pferde", title="Pferdefotografie", template=T, parent=None, content=pferde)

    add(slug="portfolio", title="Portfolio Pferde", template=T, parent="pferde", content="\n\n".join([
        group(p("Portfolio", "rd-eyebrow"), h("Pferde &amp; ihre Menschen", 1), cls="rd-section", align="wide"),
        group(h("Pferd &amp; Reiter", 2),
              gallery([(m(475), "Reiterin im Blütenhain"), (m(476), "Reiterin mit Schimmel"), (m(479), "Schimmel unter dem Sattel"),
                       (m(322), "Reiterin mit Schimmel im Wald"), (m(320), "Schimmel in der Bahn"), (m(327), "Reiterin mit Kuh und Pferd")]),
              h("Portraits &amp; Black Background", 2),
              gallery([(m(481), "Haflinger Portrait"), (m(483), "Portrait vor Schwarz mit Kranz"), (m(482), "Palomino vor Schwarz"), (m(480), "Schimmel Portrait")]),
              h("Turnier", 2),
              gallery([(m(489), "Dressurprüfung"), (m(486), "Siegerehrung"), (m(487), "Pony-Prüfung"), (m(488), "Vorbereitung am Abreiteplatz")]),
              cls="rd-section", align="wide"),
    ]))

    add(slug="shootings-preise", title="Shootings & Preise", template=T, parent="pferde", content="\n\n".join([
        group(p("Shootings &amp; Preise", "rd-eyebrow"), h("Ehrliche Festpreise. Vorab online bezahlt.", 1),
              p("Wähle dein Paket, bezahle online und gib deine Wunschtermine an. Bei schlechtem Wetter verschieben wir kostenlos.", "rd-lead"),
              cls="rd-section", align="wide"),
        group(columns(
            card("Pony", "149 €", ["ca. 45 Minuten", "1 Pferd", "5 bearbeitete Bilder", "Online-Galerie"], PR["pony"]["url"]),
            card("Warmblut", "289 €", ["ca. 90 Minuten", "Pferd &amp; Reiter, 2 Outfits", "12 bearbeitete Bilder", "Online-Auswahlgalerie"], PR["warmblut"]["url"], popular=True),
            card("Kaltblut", "479 €", ["ca. 2,5 Stunden", "Golden Hour oder Black Background", "25 Bilder + alle weiteren basis-bearbeitet", "Fine-Art-Print 30×45"], PR["kaltblut"]["url"]),
            cls="rd-cards"), cls="rd-section rd-tint", align="full"),
        group(h("Spezial-Shootings", 2), columns(
            card("Black Background Mini", "119 €", ["ca. 30 Minuten", "4 Studio-Portraits", "mobiles Studio am Stall"], PR["black-background"]["url"]),
            card("Fohlen- &amp; Verkaufsfotos", "169 €", ["Exterieur + Bewegung", "8 Bilder", "Lieferung in 48 h"], PR["verkaufsfotos"]["url"]),
            card("Stalltag (Gruppe)", "89 € / Pferd", ["ab 5 Pferden", "30 Min., 3 Bilder pro Pferd", "Organisator/in gratis"], "/pferde/stalltag/", "Mehr erfahren"),
            cls="rd-cards"), cls="rd-section", align="wide"),
        group(h("Extras", 2), table(["Extra", "Preis"], [
            ["Zusätzliches bearbeitetes Bild", "25 €"], ["Bilderpaket 5 / 10", "99 € / 179 €"], ["Alle Bilder (basis-bearbeitet)", "249 €"],
            ["Weiteres Pferd", "49 €"], ["Weitere Person", "25 €"], ["Drohnenaufnahmen", "59 €"], ["Express-Lieferung 48 h", "49 €"]]),
            p('Extras kannst du direkt mitbuchen oder nach dem Shooting <a href="/produkt-kategorie/pferde-extras/">im Shop nachkaufen</a>.', "rd-small"),
            cls="rd-section", align="wide"),
        group(h("Anfahrt", 2), travel_table("pferde"),
              p('Anfahrt Zone 2 und 3 kannst du <a href="' + PR["anfahrt-z2"]["url"] + '">hier</a> bzw. <a href="' + PR["anfahrt-z3"]["url"] + '">hier</a> mitbuchen. Beim Stalltag wird die Anfahrt auf alle Pferde aufgeteilt.', "rd-small"),
              cls="rd-section", align="wide"),
        group(h("So läuft's", 2), columns(
            column(h("Buchen", 3), p("Paket wählen, online bezahlen und 2–3 Wunschtermine angeben.")),
            column(h("Vorbereiten", 3), p("Du bekommst eine Checkliste: Putzen, Outfits, Location, Uhrzeit fürs beste Licht.")),
            column(h("Shooten", 3), p("Ganz entspannt, im Tempo deines Pferdes. Helfer zum Aufmerksam-Machen sind Gold wert.")),
            column(h("Auswählen", 3), p("In 10 Tagen ist deine Online-Galerie da. Lieblingsbilder auswählen, fertig!")),
            cls="rd-steps"), cls="rd-section rd-tint", align="full"),
        group(h("Gut zu wissen", 2),
              details("Was passiert bei schlechtem Wetter?", p("Wir verschieben kostenlos auf einen neuen Termin.")),
              details("Kann ich stornieren?", p("Bis 14 Tage vor dem Termin: Erstattung abzüglich 40 € Bearbeitungsgebühr, oder der volle Betrag als Gutschein. Danach wandeln wir den Betrag in einen Gutschein um (3 Jahre gültig).")),
              details("Darf ich die Bilder online teilen?", p("Ja, für private Zwecke und Social Media gern, mit Verlinkung. Gewerbliche Nutzung (z. B. Verkaufsanzeigen von Händlern) bitte vorher anfragen.")),
              details("Warum keine Mehrwertsteuer?", p("Als Kleinunternehmer gemäß § 19 UStG wird keine Umsatzsteuer berechnet. Alle Preise sind Endpreise.")),
              cls="rd-section", align="wide"),
    ]))

    add(slug="stalltag", title="Stalltag – Gruppenshooting", template=T, parent="pferde", content="\n\n".join([
        group(p("Stalltag", "rd-eyebrow"), h("Ein Tag. Ein Stall. Viele glückliche Pferdemenschen.", 1),
              p("Organisiere einen Shooting-Tag an deinem Stall: Jede/r bucht und bezahlt den eigenen Platz online. Du als Organisator/in bekommst dein Shooting gratis.", "rd-lead"),
              cls="rd-hero rd-hero-short", align="full", bg={"url": M[327]["url"], "id": 327}),
        group(columns(
            column(h("Für Teilnehmer", 3), ul(["89 € pro Pferd", "ca. 30 Minuten", "3 bearbeitete Bilder", "weitere Bilder nachkaufbar (25 € / Bild, 5 für 99 €)"]),
                   buttons(button("Platz buchen", PR["stalltag"]["url"]))),
            column(h("Für Organisator/innen", 3), ul(["ab 5 Pferden", "dein eigenes Shooting gratis", "Anfahrt wird auf alle geteilt", "ich stelle dir einen Aushang (PDF) für den Stall bereit"]),
                   buttons(button("Stalltag anfragen", "/kontakt/", outline=True))),
            cls="rd-cards"), cls="rd-section", align="wide"),
    ]))

    add(slug="turnierfotografie", title="Turnierfotografie", template=T, parent="pferde", content="\n\n".join([
        group(p("Turnierfotografie", "rd-eyebrow"), h("Jeder Ritt. Jede Schleife.", 1),
              p("Alle Starter werden fotografiert, die Bilder sind meist noch am selben Abend online. Suche nach Startnummer, Prüfung oder Uhrzeit.", "rd-lead"),
              buttons(button("Turnierfotos finden", "#galerien"), button("Flatrate vorbestellen", PR["turnier-flat-vvk"]["url"], outline=True)),
              cls="rd-hero", align="full", bg={"url": M[489]["url"], "id": 489}),
        group(h("Preise für Reiter/innen", 2), table(["Produkt", "Preis"], [
            ["Einzelbild digital (volle Auflösung)", "12,90 €"], ["3 Bilder", "29,90 €"], ["6 Bilder (beliebt)", "49,90 €"],
            ["Alle Bilder eines Ritts", "59 €"],
            ["<strong>Turnier-Flatrate</strong>: alle Bilder deines Pferdes, alle Tage", "119 €, <strong>im Vorverkauf 89 €</strong>"],
            ["Zweites Pferd (Flatrate)", "+49 €"],
            ["Print 13×18 / 20×30 / 30×45", "9,90 € / 19,90 € / 34,90 €"], ["Leinwand 40×60", "89 €"]]),
            buttons(button("Flatrate vorbestellen (89 €)", PR["turnier-flat-vvk"]["url"])),
            cls="rd-section", align="wide"),
        group(h("Turnierkalender &amp; Galerien", 2), p("Hier erscheinen die nächsten Turniere und die Galerien zum Bestellen.", ""),
              p("Noch keine Termine veröffentlicht. Folge mir auf Instagram oder frag per WhatsApp, welche Turniere ich als Nächstes fotografiere.", "rd-note"),
              cls="rd-section rd-tint", align="full"),
        group(p("Für Veranstalter", "rd-eyebrow"), h("Professionelle Fotos für euer Turnier, ohne Kosten", 2),
              ul(["Kostenlose Fotobegleitung bei Turnieren ab ca. 250 Starts (kleinere Turniere: 150 € Pauschale)",
                  "20 Bilder für eure Social-Media-Kanäle und Presse inklusive",
                  "Ein 15-€-Fotogutschein für jede/n Prüfungssieger/in",
                  "Bilder am selben Abend online, Verkauf komplett über mich"]),
              buttons(button("Turnier anfragen", "/kontakt/")),
              cls="rd-section", align="wide"),
    ]))

    add(slug="ablauf-faq", title="Ablauf & FAQ Pferde", template=T, parent="pferde", content="\n\n".join([
        group(p("Ablauf &amp; FAQ", "rd-eyebrow"), h("Gut vorbereitet zum Shooting", 1), cls="rd-section", align="wide"),
        group(h("Checkliste", 2), ul(["Pferd gründlich putzen, Mähne &amp; Schweif verlesen, Hufe fetten",
                                       "Sauberes, gut sitzendes Halfter oder Trense (Leder wirkt am schönsten)",
                                       "Outfits in ruhigen Farben, die zur Fellfarbe passen. Keine großen Logos",
                                       "Eine helfende Person für Ohren &amp; Aufmerksamkeit (Futtertüte, Rascheln)",
                                       "Location: ruhige Wiese, Allee, Waldrand oder eure Halle. Ich berate dich gern"]),
              details("Wann ist das beste Licht?", p("Die Stunde vor Sonnenuntergang (Golden Hour) oder ein leicht bewölkter Tag.")),
              details("Mein Pferd ist unruhig, geht das trotzdem?", p("Ja. Wir nehmen uns Zeit, machen Pausen und arbeiten im Tempo deines Pferdes.")),
              details("Wie bekomme ich die Bilder?", p("Über eine private Online-Galerie zum Download in voller Auflösung, plus Web-Versionen für Social Media.")),
              cls="rd-section", align="wide"),
    ]))

    # ================================================================== WEDDING WORLD
    T = "rd-hochzeit"
    ph = lambda txt="Hochzeitsbild folgt": group(p(txt, align="center"), cls="rd-placeholder")
    add(slug="hochzeit", title="Hochzeitsfotografie", template=T, parent=None, content="\n\n".join([
        group(p("Hochzeitsfotografie · Südpfalz · Speyer · Karlsruhe", "rd-eyebrow"),
              h("Euer Tag.<br><em>Für immer.</em>", 1),
              p("Natürliche, ungestellte Hochzeitsreportagen: die Tränen beim Ja-Wort, das Lachen der Gäste, der letzte Tanz.", "rd-lead"),
              buttons(button("Pakete &amp; Preise", "/hochzeit/pakete-preise/"), button("Termin anfragen", "/kontakt/", outline=True)),
              cls="rd-hero rd-hero-soft", align="full"),
        group(columns(
            column(h("Ungestellt &amp; ehrlich", 3), p("Ich begleite euch zurückhaltend und fange echte Momente ein, statt alles zu inszenieren.")),
            column(h("Sneak Peek in 72 h", 3), p("Die ersten Lieblingsbilder habt ihr schon wenige Tage nach der Hochzeit.")),
            column(h("Gästegalerie", 3), p("Eure Gäste sehen die Bilder in einer privaten Galerie und können sich Abzüge bestellen.")),
            cls="rd-usps"), cls="rd-section", align="wide"),
        group(columns(column(ph()), column(ph()), column(ph())), cls="rd-section", align="wide"),
        group(p("Pakete", "rd-eyebrow", align="center"), h("Für jede Hochzeit das <em>passende</em> Paket", 2, align="center"),
              columns(
                  card("Herzstück", "1.890 €", ["7 Stunden", "ca. 450 Bilder", "Sneak Peek &amp; Gästegalerie"], "/hochzeit/pakete-preise/", "Details"),
                  card("Für Immer", "2.690 €", ["10 Stunden", "ca. 700 Bilder", "Paarshooting inklusive"], "/hochzeit/pakete-preise/", "Details", popular=True),
                  card("Grenzenlos", "3.790 €", ["12 Stunden, 2 Fotografen", "Album + 2 Elternalben", "Paarshooting inklusive"], "/hochzeit/pakete-preise/", "Details"),
                  cls="rd-cards"),
              cls="rd-section rd-tint", align="full"),
        group(h("Erzählt mir von euch", 2, align="center"),
              p("Euer Datum ist noch frei? Schreibt mir, ich melde mich innerhalb von 24 Stunden.", align="center"),
              buttons(button("Verfügbarkeit prüfen", "/kontakt/"), button("WhatsApp", WA, outline=True), cls="is-content-justification-center"),
              cls="rd-section", align="wide"),
    ]))

    add(slug="pakete-preise", title="Hochzeit – Pakete & Preise", template=T, parent="hochzeit", content="\n\n".join([
        group(p("Pakete &amp; Preise", "rd-eyebrow"), h("Transparent. <em>Ohne versteckte Kosten.</em>", 1),
              p("Datum prüfen lassen, 30 % anzahlen und euer Tag ist fest reserviert. Der Rest ist 4 Wochen vor der Hochzeit fällig.", "rd-lead"),
              cls="rd-section", align="wide"),
        group(columns(
            card("Ja-Wort", "890 €", ["Standesamt, 2,5 Stunden", "ca. 150 Bilder", "Online-Galerie"], PR["hz-jawort"]["url"], "Anzahlung 267 €"),
            card("Herzstück", "1.890 €", ["7 Stunden (Trauung bis Eröffnungstanz)", "ca. 450 Bilder", "Sneak Peek in 72 h", "Gästegalerie"], PR["hz-herzstueck"]["url"], "Anzahlung 567 €"),
            card("Für Immer", "2.690 €", ["10 Stunden", "ca. 700 Bilder", "Paarshooting inklusive", "Sneak Peek &amp; Gästegalerie"], PR["hz-fuerimmer"]["url"], "Anzahlung 807 €", popular=True),
            card("Grenzenlos", "3.790 €", ["12 Stunden, 2 Fotografen", "Paarshooting inklusive", "Album 30×30, 40 Seiten", "2 Elternalben"], PR["hz-grenzenlos"]["url"], "Anzahlung 1.137 €"),
            cls="rd-cards"),
            p("Nebensaison (November–März) und Montag–Donnerstag: <strong>10 % Rabatt</strong> auf alle Pakete.", "rd-note"),
            cls="rd-section rd-tint", align="full"),
        group(h("Extras", 2), table(["Extra", "Preis"], [
            ["Zusätzliche Stunde", "190 €"], ["Zweitfotograf/in", "590 €"], ["Drohnenaufnahmen", "249 €"],
            ["Express-Lieferung (2 Wochen)", "190 €"], ["Fotobox mit 30 Fine-Art-Prints", "249 €"],
            ["Hochzeitsalbum 30×30, 40 Seiten", "590 €"], ["Elternalbum 20×20", "149 €"],
            ['<a href="/hochzeit/paarshootings/">Verlobungs-/Paarshooting</a>', "290 € (190 € mit Hochzeit)"]]),
            cls="rd-section", align="wide"),
        group(h("Anfahrt", 2), travel_table("hochzeit"), cls="rd-section", align="wide"),
        group(h("So läuft's", 2), columns(
            column(h("Anfragen", 3), p("Datum &amp; Location schicken, ich prüfe sofort die Verfügbarkeit.")),
            column(h("Reservieren", 3), p("30 % Anzahlung online, Vertrag per E-Mail. Euer Datum ist fest.")),
            column(h("Kennenlernen", 3), p("Videocall oder Kaffee: Ablauf, Wünsche, Familienfotos-Liste.")),
            column(h("Genießen", 3), p("Sneak Peek in 72 h, komplette Galerie in 4–6 Wochen.")),
            cls="rd-steps"), cls="rd-section rd-tint", align="full"),
        group(h("Häufige Fragen", 2),
              details("Was passiert, wenn ihr absagen müsst?", p("Die Anzahlung reserviert den Tag exklusiv für euch und ist daher nicht erstattbar. Alle weiteren Regelungen stehen transparent im Vertrag.")),
              details("Und wenn du krank wirst?", p("Für den Notfall organisiere ich eine/n erfahrene/n Kollegin/Kollegen als Ersatz. Das ist im Vertrag geregelt.")),
              details("Wie viele Bilder bekommen wir?", p("Je nach Paket 150 bis 700+ sorgfältig ausgewählte und bearbeitete Bilder, in Farbe und ausgewählte in Schwarzweiß.")),
              details("Warum keine Mehrwertsteuer?", p("Als Kleinunternehmer gemäß § 19 UStG wird keine Umsatzsteuer berechnet. Alle Preise sind Endpreise.")),
              cls="rd-section", align="wide"),
    ]))

    add(slug="paarshootings", title="Verlobungs- & Paarshootings", template=T, parent="hochzeit", content="\n\n".join([
        group(p("Paarshootings", "rd-eyebrow"), h("Nur ihr zwei. <em>Und das Licht.</em>", 1),
              p("Im Weinberg, am Rhein oder im Pfälzerwald, vor oder nach der Hochzeit.", "rd-lead"), cls="rd-section", align="wide"),
        group(columns(
            card("Verlobung / Paar", "290 €", ["ca. 1 Stunde", "ca. 50 bearbeitete Bilder", "perfekt für Save-the-Date", "mit Hochzeitsbuchung nur 190 €"], PR["paar"]["url"]),
            card("After-Wedding", "390 €", ["ca. 2 Stunden", "ca. 80 bearbeitete Bilder", "im Hochzeitsoutfit, ohne Zeitdruck"], PR["afterwedding"]["url"]),
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
            p("Versand innerhalb Deutschlands 6,90 €, ab 150 € versandkostenfrei.", "rd-small"),
            buttons(button("Zum Shop", "/produkt-kategorie/alben-wandbilder/")),
            cls="rd-section", align="wide"),
    ]))

    add(slug="gaestegalerie", title="Gästegalerie", template=T, parent="hochzeit", content="\n\n".join([
        group(p("Gästegalerie", "rd-eyebrow"), h("Ihr wart dabei? <em>Hier sind die Bilder.</em>", 1),
              p("Das Brautpaar hat euch einen Link und ein Passwort gegeben. Damit kommt ihr in die private Galerie und könnt Abzüge und Downloads bestellen.", "rd-lead"),
              p("Kein Link bekommen? Fragt das Brautpaar oder schreibt mir.", "rd-note"),
              buttons(button("Kontakt", "/kontakt/", outline=True)),
              cls="rd-section", align="wide"),
    ]))

    add(slug="echte-hochzeiten", title="Echte Hochzeiten", template=T, parent="hochzeit", content="\n\n".join([
        group(p("Echte Hochzeiten", "rd-eyebrow"), h("Geschichten, <em>die bleiben</em>", 1),
              p("Hier erzähle ich bald ausgewählte Hochzeiten als ganze Geschichte, vom Getting Ready bis zur Party.", "rd-lead"),
              columns(column(ph()), column(ph())), cls="rd-section", align="wide"),
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
            column(group(p("Portrait von Rui folgt", align="center"), cls="rd-placeholder")),
            column(p("Über mich", "rd-eyebrow"), h("Hallo, ich bin Rui.", 1),
                   p("[Text folgt: ein paar persönliche Sätze über dich, deinen Weg zur Fotografie, deine Liebe zu Pferden und was dir bei Hochzeiten wichtig ist.]"),
                   p("Ich lebe in Germersheim und fotografiere in der Südpfalz, der Rhein-Neckar-Region und rund um Karlsruhe."),
                   buttons(button("Pferdefotografie", "/pferde/"), button("Hochzeitsfotografie", "/hochzeit/", outline=True))),
        ), cls="rd-section", align="wide"),
    ]))

    add(slug="kontakt", title="Kontakt", template=T, parent=None, content="\n\n".join([
        group(p("Kontakt", "rd-eyebrow"), h("Lass uns sprechen", 1),
              p("Frage, Terminanfrage oder Turnier? Schreib mir. Ich antworte innerhalb von 24 Stunden.", "rd-lead"),
              buttons(button("WhatsApp: 0173 8505311", WA)),
              raw('<!-- wp:jetpack/contact-form {"subject":"Anfrage über ruidodat.com","customThankyou":"message","customThankyouMessage":"Danke! Ich melde mich innerhalb von 24 Stunden."} -->\n'
                  '<!-- wp:jetpack/field-name {"label":"Name","required":true} /-->\n\n'
                  '<!-- wp:jetpack/field-email {"label":"E-Mail","required":true} /-->\n\n'
                  '<!-- wp:jetpack/field-telephone {"label":"Telefon"} /-->\n\n'
                  '<!-- wp:jetpack/field-select {"label":"Worum geht es?","required":true,"options":["Pferde-Shooting","Stalltag","Turnier (Veranstalter)","Turnierfotos","Hochzeit","Paarshooting","Gutschein","Sonstiges"]} /-->\n\n'
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
        p("Rui Dodat · Rui Dodat Fotografie<br>[Straße und Hausnummer]<br>76726 Germersheim<br>Deutschland"),
        p("Telefon: 0173 8505311<br>E-Mail: [E-Mail-Adresse]"),
        p("Umsatzsteuer: Kleinunternehmer gemäß § 19 UStG, daher keine USt-IdNr."),
        p("Verbraucherstreitbeilegung: Ich bin nicht bereit und nicht verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen."),
        cls="rd-section", align="wide"))
    add(slug="datenschutz", title="Datenschutzerklärung", template=T, parent=None, content=group(h("Datenschutzerklärung", 1), legal_note("Datenschutzerklärung"), cls="rd-section", align="wide"))
    add(slug="agb", title="AGB", template=T, parent=None, content=group(h("Allgemeine Geschäftsbedingungen", 1), legal_note("AGB-Text"),
        p("Wichtige Punkte für die AGB: Wetter-Verschiebung, Stornoregeln (14 Tage), Anzahlung Hochzeit nicht erstattbar, Nutzungsrechte (privat), Gutscheine 3 Jahre gültig.", "rd-small"),
        cls="rd-section", align="wide"))
    add(slug="widerruf", title="Widerrufsbelehrung", template=T, parent=None, content=group(h("Widerrufsbelehrung", 1), legal_note("Widerrufsbelehrung"), cls="rd-section", align="wide"))
    add(slug="versand-zahlung", title="Versand & Zahlung", template=T, parent=None, content=group(
        h("Versand &amp; Zahlung", 1),
        ul(["Shootings, Gutscheine und Downloads: kein Versand, digital per E-Mail",
            "Alben, Prints, Wandbilder: Versand innerhalb Deutschlands 6,90 €, ab 150 € versandkostenfrei",
            "Lieferzeit Alben &amp; Wandbilder: ca. 2–3 Wochen nach Freigabe",
            "Zahlung: Überweisung (Vorkasse), weitere Zahlungsarten folgen"]),
        p("Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.", "rd-small"),
        cls="rd-section", align="wide"))
    return pages
