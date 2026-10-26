"""WooCommerce catalogue. Prices are final prices (Kleinunternehmer, § 19 UStG)."""

KU = "<p><small>Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.</small></p>"

BOOKING_HOW = (
    "<h4>So läuft die Buchung</h4><ol>"
    "<li>Paket in den Warenkorb legen und bezahlen.</li>"
    "<li>An der Kasse im Feld <strong>„Wunschtermine &amp; Ort“</strong> 2–3 Termine und den Stall/PLZ angeben.</li>"
    "<li>Du bekommst innerhalb von 48 Stunden die Terminbestätigung und eine Checkliste zur Vorbereitung.</li></ol>"
    "<p><strong>Wetter:</strong> Bei schlechtem Wetter verschieben wir kostenlos. "
    "<strong>Anfahrt:</strong> bis 30 km ab Germersheim inklusive, darüber nach Zonen (siehe Preisseite).</p>"
)

CATS = {
    "pferde-shootings": "Pferde-Shootings",
    "pferde-extras": "Extras Pferde-Shootings",
    "turnierfotos": "Turnierfotos",
    "paarshootings": "Paarshootings",
    "alben-wandbilder": "Alben & Wandbilder",
    "gutscheine": "Gutscheine",
    "anfahrt": "Anfahrt",
}

# key, name, price, category, virtual, short description, long description
# Not offered right now (galleries / online sales parked): kept as drafts in the shop.
DRAFT = {"turnier-flat-vvk", "turnier-flat", "turnier-zweitpferd", "gutschein-50", "gutschein-100", "gutschein-150",
         "gutschein-250", "gutschein-pony", "gutschein-warmblut", "paar", "afterwedding", "album-hochzeit", "album-eltern"}

P = [
    # --- Horse shoots ---
    ("pony", "Shooting „Schritt“", 199, "pferde-shootings", True,
     "bis zu 60 Minuten · 1 Pferd · 5 bearbeitete Bilder · Bezahlung vor Ort",
     "<p>Der kurze Einstieg: ein entspanntes Shooting mit Portraits und Bewegung.</p>"
     "<ul><li>bis zu 60 Minuten Shooting</li><li>1 Pferd</li><li>5 bearbeitete Bilder (digital, volle Auflösung)</li><li>Weitere Bilder jederzeit nachkaufbar</li><li>Bezahlung persönlich beim Shooting</li></ul>"),
    ("warmblut", "Shooting „Trab“", 349, "pferde-shootings", True,
     "bis zu 2 Stunden · Pferd & Reiter · 12 bearbeitete Bilder · Bezahlung vor Ort",
     "<p>Das beliebteste Paket: genug Zeit für Portraits, Pferd & Reiter und Bewegung, ganz ohne Stress.</p>"
     "<ul><li>bis zu 2 Stunden Shooting</li><li>Pferd & Reiter, 2 Outfits möglich</li><li>12 bearbeitete Bilder (digital, volle Auflösung)</li><li>Vorbereitungs-Checkliste</li><li>Bezahlung persönlich beim Shooting</li></ul>"),
    ("kaltblut", "Shooting „Galopp“", 549, "pferde-shootings", True,
     "bis zu 3 Stunden · Pferd & ganze Familie · 25 bearbeitete Bilder + Wandbild · Bezahlung vor Ort",
     "<p>Für alle, die es ganz besonders wollen: die ganze Familie mit ihrem Pferd.</p>"
     "<ul><li>bis zu 3 Stunden Shooting</li><li>Pferd & ganze Familie</li><li>25 aufwendig bearbeitete Bilder</li><li>1 Wandbild inklusive</li><li>Bezahlung persönlich beim Shooting</li></ul>"),
    ("black-background", "Black Background", 199, "pferde-shootings", True,
     "bis zu 45 Minuten · 4 Portraits vor schwarzem Hintergrund · ab 3 Pferden 150 € pro Pferd",
     "<p>Ausdrucksstarke Studio-Portraits direkt an deinem Stall, mit mobilem schwarzem Hintergrund.</p><ul><li>bis zu 45 Minuten, inkl. Auf- und Abbau des mobilen Studios</li><li>4 bearbeitete Portraits</li></ul>"),
    ("verkaufsfotos", "Fohlen- & Verkaufsfotos", 169, "pferde-shootings", True,
     "Exterieur + Bewegung · 8 Bilder · Lieferung in 48 h",
     "<p>Professionelle Bilder für Verkaufsanzeigen und Zuchtdokumentation: korrekt aufgestelltes Exterieur und Bewegung in allen Gangarten.</p><ul><li>8 bearbeitete Bilder</li><li>Lieferung innerhalb von 48 Stunden</li></ul>"),
    ("stalltag", "Stalltag: Platz pro Pferd", 89, "pferde-shootings", True,
     "Gruppenshooting · bis zu 30 Minuten · 3 Bilder · ab 5 Pferden",
     "<p>Ein Shooting-Tag an eurem Stall. Ab 5 Pferden findet der Stalltag statt, die Anfahrt wird geteilt. Das Shooting der Organisatorin/des Organisators ist kostenlos.</p>"
     "<ul><li>bis zu 30 Minuten pro Pferd</li><li>3 bearbeitete Bilder</li><li>weitere Bilder nachkaufbar</li></ul><p>Bitte Stall, Datum und Name des Pferdes in den Bestell-Anmerkungen angeben.</p>"),
    # --- Horse extras ---
    ("extra-bild", "Zusätzliches Bild", 25, "pferde-extras", True, "1 weiteres bearbeitetes Bild aus deiner Galerie", ""),
    ("bilder-5", "Bilderpaket 5", 99, "pferde-extras", True, "5 weitere bearbeitete Bilder", ""),
    ("bilder-10", "Bilderpaket 10", 179, "pferde-extras", True, "10 weitere bearbeitete Bilder", ""),
    ("bilder-alle", "Alle Bilder", 249, "pferde-extras", True, "Alle gelungenen Bilder deines Shootings (basis-bearbeitet)", ""),
    ("extra-pferd", "Weiteres Pferd", 49, "pferde-extras", True, "Ein weiteres Pferd im selben Shooting", ""),
    ("extra-person", "Weitere Person", 25, "pferde-extras", True, "Eine weitere Person im selben Shooting", ""),
    ("drohne", "Drohnenaufnahmen", 59, "pferde-extras", True, "Luftaufnahmen (wo erlaubt)", ""),
    ("express", "Express-Lieferung 48 h", 49, "pferde-extras", True, "Deine Bilder innerhalb von 48 Stunden", ""),
    # --- Travel ---
    ("anfahrt-z2", "Anfahrt Zone 2 (30–60 km), Pferde", 25, "anfahrt", True, "z. B. Mannheim, Heidelberg, Neustadt, Rastatt", ""),
    ("anfahrt-z3", "Anfahrt Zone 3 (60–100 km)", 49, "anfahrt", True, "z. B. Kaiserslautern, Pirmasens, Baden-Baden, Heilbronn", ""),
    # --- Shows ---
    ("turnier-flat-vvk", "Turnier-Flatrate (Vorverkauf)", 89, "turnierfotos", True,
     "Alle Fotos deines Pferdes auf dem Turnier · vor dem Turnier gebucht",
     "<p>Buche vor dem Turnier und erhalte <strong>alle Fotos deines Pferdes</strong> aller Prüfungen und Tage als Download, inklusive Siegerehrung.</p>"
     "<p>Bitte im Feld „Anmerkungen zur Bestellung“ angeben: <strong>Turnier, Name des Pferdes, Reiter/in, Startnummer(n)</strong>.</p>"),
    ("turnier-flat", "Turnier-Flatrate (nach dem Turnier)", 119, "turnierfotos", True,
     "Alle Fotos deines Pferdes auf dem Turnier", "<p>Wie der Vorverkauf, nur nach dem Turnier gebucht.</p>"),
    ("turnier-zweitpferd", "Turnier-Flatrate: zweites Pferd", 49, "turnierfotos", True, "Für ein weiteres Pferd auf demselben Turnier", ""),
    # --- Couple shoots ---
    ("paar", "Verlobungs- / Paarshooting", 290, "paarshootings", True,
     "1 Stunde · ca. 50 bearbeitete Bilder", "<p>Perfekt für Save-the-Date-Karten, oder einfach, um sich vor der Kamera wohlzufühlen. Mit einer Hochzeitsbuchung nur 190 € (Gutscheincode erhaltet ihr mit dem Vertrag).</p>"),
    ("afterwedding", "After-Wedding-Shooting", 390, "paarshootings", True,
     "2 Stunden · ca. 80 bearbeitete Bilder", "<p>Ohne Zeitdruck, an eurem Lieblingsort, gern im Brautkleid im Weinberg oder am Rhein.</p>"),
    # --- Albums & wall art (physical) ---
    ("album-hochzeit", "Hochzeitsalbum 30×30 cm, 40 Seiten", 590, "alben-wandbilder", False,
     "Leinen oder Leder · Layout inklusive", "<p>Handgefertigtes Fine-Art-Album, Layout durch mich. Weitere Doppelseiten je 15 €.</p>"),
    ("album-eltern", "Elternalbum 20×20 cm", 149, "alben-wandbilder", False, "Kopie eures Hochzeitsalbums im kleinen Format", ""),
    ("album-pferd-s", "Pferdealbum 20×20 cm, 20 Seiten", 149, "alben-wandbilder", False, "Deine Lieblingsbilder als Fotobuch", ""),
    ("album-pferd-l", "Pferdealbum 30×30 cm, 30 Seiten", 349, "alben-wandbilder", False, "Großes Fine-Art-Album", ""),
    ("print-20x30", "Fine-Art-Print 20×30 cm", 39, "alben-wandbilder", False, "Museumspapier, matt", ""),
    ("print-30x45", "Fine-Art-Print 30×45 cm", 69, "alben-wandbilder", False, "Museumspapier, matt", ""),
    ("print-40x60", "Fine-Art-Print 40×60 cm", 99, "alben-wandbilder", False, "Museumspapier, matt", ""),
    ("leinwand-60x90", "Leinwand 60×90 cm", 189, "alben-wandbilder", False, "Auf Keilrahmen, fertig zum Aufhängen", ""),
    ("acryl-60x90", "Acryl/Alu-Dibond 60×90 cm", 269, "alben-wandbilder", False, "Brillante Farben, moderne Optik", ""),
    # --- Vouchers ---
    ("gutschein-50", "Gutschein 50 €", 50, "gutscheine", True, "Einlösbar für alle Shootings und Produkte · 3 Jahre gültig", ""),
    ("gutschein-100", "Gutschein 100 €", 100, "gutscheine", True, "Einlösbar für alle Shootings und Produkte · 3 Jahre gültig", ""),
    ("gutschein-150", "Gutschein 150 €", 150, "gutscheine", True, "Einlösbar für alle Shootings und Produkte · 3 Jahre gültig", ""),
    ("gutschein-250", "Gutschein 250 €", 250, "gutscheine", True, "Einlösbar für alle Shootings und Produkte · 3 Jahre gültig", ""),
    ("gutschein-pony", "Gutschein Pferde-Shooting „Pony“", 149, "gutscheine", True, "Das komplette Pony-Shooting als Geschenk", ""),
    ("gutschein-warmblut", "Gutschein Pferde-Shooting „Warmblut“", 289, "gutscheine", True, "Das komplette Warmblut-Shooting als Geschenk", ""),
]

VOUCHER_NOTE = ("<p>Du erhältst den Gutschein als schön gestaltetes PDF zum Ausdrucken innerhalb von 24 Stunden per E-Mail. "
                "Name der beschenkten Person bitte in den Bestell-Anmerkungen angeben.</p>")


def description(key, cat, long):
    if cat == "pferde-shootings":
        return long + BOOKING_HOW + KU
    if cat == "paarshootings":
        return long + BOOKING_HOW.replace("Paket", "Shooting").replace("den Stall/PLZ", "euren Wunschort") + KU
    if cat == "gutscheine":
        return long + VOUCHER_NOTE + KU
    return long + KU

# product photos (existing media library images) and single-booking categories
IMAGES = {"pony": 481, "warmblut": 475, "kaltblut": 483, "black-background": 482, "verkaufsfotos": 480,
          "stalltag": 327, "turnier-flat-vvk": 489, "turnier-flat": 489, "turnier-zweitpferd": 487,
          "gutschein-pony": 481, "gutschein-warmblut": 475}
SINGLE = {"pferde-shootings", "paarshootings", "turnierfotos"}
