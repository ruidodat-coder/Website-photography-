# ruidodat.com: Website Plan (Horse + Wedding Photography)

Status: draft v1 · 2026-10-01
Current site: ruidodat.com is a self-hosted WordPress site with Jetpack ("Rui Dodat – Professional Horse Photographer"). It was last updated in 2023.

---

## 1. Core idea

There is one brand and two separate worlds. The home page is a split screen, and visitors pick a world right away:

```
┌──────────────────────────┬──────────────────────────┐
│   HORSE PHOTOGRAPHY      │   WEDDING PHOTOGRAPHY    │
│   (full-bleed photo,     │   (full-bleed photo,     │
│    warm/earthy, dynamic) │    light/airy, romantic) │
│   [ Enter → ]            │   [ Enter → ]            │
└──────────────────────────┴──────────────────────────┘
        small strip: Gift vouchers · About · Contact
```

The two worlds share the logo, the cart/checkout, the legal pages and the "About me" page. Each world has its own colour mood, menu, prices and shop.

- **Horse world** ("Equine"): dark, earthy tones (deep green/brown, sand, off-white). It uses strong motion photography and a bold sans-serif headline font.
- **Wedding world**: light and airy (ivory, blush/sage, soft grey). It uses an elegant serif headline font, a lot of white space and slow fades.

Separating the worlds matters because the clients don't overlap. A rider does not want to scroll through brides, and a couple does not care about show jumping. It also helps SEO: "Pferdefotograf + city" and "Hochzeitsfotograf + city" each get their own landing page.

Language: German first, with English as a second language. Customers are in Germany, and the English version also covers expat couples and international riders.

---

## 2. Sitemap

```
/                              Split home (choose world)
│
├── /pferde/                   HORSE WORLD
│   ├── Home (hero video/slider, 3 USPs, featured packages, reviews, CTA "Shooting buchen")
│   ├── Portfolio
│   │   ├── Pferdeportraits (Freiheit / Black Background / Golden Hour)
│   │   ├── Pferd & Reiter
│   │   ├── Sport / Action
│   │   └── Fohlen & Zucht (breeder/sales photos)
│   ├── Shootings & Preise      → packages with "Jetzt buchen & bezahlen"
│   ├── Stalltag / Gruppenshooting → sign-up for barn group days (pre-paid per horse)
│   ├── Turnierfotografie
│   │   ├── Turnierfotos finden  → event galleries (search by date / start no. / class)
│   │   ├── Turnierkalender      → upcoming events I cover + presale flat-rate
│   │   └── Für Veranstalter     → offer for show organisers
│   ├── Shop                    → prints, wall art, albums, vouchers
│   ├── Ablauf & FAQ            → preparation guide (grooming, outfits, weather rule)
│   └── Kontakt
│
├── /hochzeit/                 WEDDING WORLD
│   ├── Home (hero, philosophy, featured weddings, reviews, CTA "Termin prüfen")
│   ├── Portfolio / Echte Hochzeiten (full wedding stories, blog-style)
│   ├── Pakete & Preise         → packages + online date check + deposit payment
│   ├── Paarshootings           → engagement / after-wedding (fully pre-paid)
│   ├── Alben & Wandbilder      → album configurator, parent albums, prints
│   ├── Gästegalerie            → password-protected galleries, guests can buy prints
│   ├── Ablauf & FAQ            → timeline tips, rain plan, contract info
│   └── Kontakt / Anfrage
│
├── /gutscheine/               Gift vouchers (both worlds, very strong sales item for horses)
├── /ueber-mich/               About (shared)
├── /warenkorb, /kasse         Shared WooCommerce cart and checkout
└── Legal: Impressum, Datenschutz, AGB, Widerruf, Versand & Zahlung
```

---

## 3. Shops and booking: how they work

### 3.1 Pre-paid shooting booking (both worlds)
The best pattern from the research: a package page, then a calendar slot, then a short questionnaire, then payment. The confirmation includes the contract/AGB and a preparation PDF.

| | Horse shoots | Wedding |
|---|---|---|
| Payment at booking | **100 % pre-paid** | **30 % deposit** (secures the date), 70 % due 4 weeks before |
| Calendar | Free slots (e.g. weekends plus golden-hour weekday slots) | Date availability check (one wedding per day) |
| Weather | Free rescheduling for bad weather | Rain plan, no rescheduling |
| Cancellation | >14 days: refund minus €40 fee or full value as voucher. <14 days: voucher only | Deposit is non-refundable. Staged fees in the AGB |

Couple and engagement shoots are 100 % pre-paid, like horse shoots.

### 3.2 Event photography
**Horse shows (Turniere).** Almost every German show photographer works the same way: shoot every rider, upload the same evening, and riders find their photos by **start number / class / time**.
- **Presale "Turnier-Flatrate"**: the rider pays before the show and gets all photos of their horse. This means guaranteed revenue. Sanwald Fotografie does this at €190–219.
- **Pay per photo** after the show, with bundle discounts.
- **Organisers** get free coverage (or a small fee for small shows) plus social-media photos. Class winners get a photo voucher (Kleuver, Heinz). Vouchers bring buyers into the shop.

**Weddings.** The wedding itself is the "event". There are two extra revenue streams:
- A **guest gallery**: guests can buy prints from it (Pictrs and profi-fotos-online support this).
- **Parent albums**.

### 3.3 Product shop (prints, albums, wall art)
Products are fine-art prints, canvas, acrylic, and albums for both worlds. Orders go to a print lab with drop-shipping (e.g. WhiteWall, Saal Digital Professional or Pixum Pro), so I don't hold any stock.

### 3.4 Recommended tech
Keep the existing WordPress and rebuild it. The domain, hosting and SEO history stay.

| Function | Tool | Why |
|---|---|---|
| Theme | A block theme (e.g. Kadence or Blocksy) with two colour "styles" | Fast and modern. Each world gets its own style variation |
| Shop + checkout | **WooCommerce** + German Market (or Germanized) | Legally required German checkout: price display, VAT notice, cancellation policy |
| Booking + pre-payment | **WooCommerce Bookings** or **Amelia** | Calendar slots, deposits, full pre-payment, reminders |
| Payments | Stripe (cards, Klarna, SEPA) + PayPal | Klarna/PayPal matter a lot in Germany |
| Event photo sales | **Pictrs** (phase 1), linked from /turnierfotos | Most German show photographers use it. It has start-number galleries, print fulfilment and guest galleries. No need to build this ourselves |
| Client/wedding galleries | Pictrs or Pixieset | Password galleries, favourites, guest shop |
| Vouchers | PW Gift Cards (WooCommerce) | Printable PDF voucher, redeemable for shoots and products |
| Legal texts | IT-Recht Kanzlei or Händlerbund AGB service | Automatic updates of AGB, Widerruf and Datenschutz |

Access for me later: an **Application Password** (WordPress → Users → Profile) is enough to work through the WordPress REST API. As an alternative, Jetpack AI/Complete enables MCP access for this site.

---

## 4. Inspiration: sites researched (Germany)

### Horse photography and show photography (16)
| Site | What's good / what we take |
|---|---|
| [Sophie Reuter](https://sophie-reuter-photography.de/preise/) (München) | Clear 2-tier packages, group shoots per participant, à-la-carte photo bundles, drone add-on |
| [Petra Eckerl](https://www.petraeckerl.com/pferde/preise) (München) | Low entry price (€75 for 3 images) plus upsell image bundles (8/13/18/23). Group rate €49/horse. Black-background style as its own product |
| [Monika Bogner](https://www.mbogner-photography.com/info/preise/) (Bayern) | Emotional package names, add-on bundles, vouchers valid 3 years, fine-art boxes |
| [Jana Knabe](https://www.janaknabe.de/preise/pferde/) (Köln) | Bronze/Silver/Gold with prints and USB. Organiser gets free images for group shoots. "Aktionskalender" for themed shoots |
| [Karina Lührig](https://www.karinaluehrigphotographie.de/pferdefotografie-preise) (Hannover) | Small/Big packages (€229/€319) |
| [Birga Greiss](https://www.birgagreissfotografie.de/preise-pferdefotografie) | Horse-breed package names (Shetty / Holsteiner / Shire). Cheap "basic-edited" bulk images. Instalments |
| [Andreas Bremer](https://andreas-bremer-photography.de/pferdefotografie/) (Lübeck) | €100 deposit at booking, outfit rental and styling add-on |
| [pferde-fotoshooting.de](https://pferde-fotoshooting.de/fotoshooting-preise) | Market overview: €65–520, standard €200–250. Blog guides for SEO |
| [Lightwork](https://lightwork.photo/) | Professional show-photo platform. Rider search, user accounts. Bundles: 1 = €39.95 / 4 = €69.95 (bestseller) |
| [Janina Sanwald](https://www.fotografie-sanwald.de/shootings/turnierfotografie-fuer-reitturniere-erleben-sie-die-faszination-des-reitsports/angebot-turnierbilder-rabatt-turnierfotos/) | Per-show flat rate €190 (all photos of 1 horse), same-day link, plus prints/poster tiers |
| [Patrick Au](https://www.patrickau-photography.de/turnierfotos/) | Web file €5 / print file €20. Prints from €7. Volume discounts. (Ordering by email is outdated, so we don't copy that) |
| [P&O Fotografie](https://www.pictrs.com/pundofotografie?l=de) | Pictrs shop, photos online the same evening, galleries by year |
| [Clara Heinz](https://www.claraheinz.de/turnierfotografie) | Pictrs, photos sorted by start number, storytelling about emotion in sport |
| [Christina Kleuver](https://fotografie-christinakleuver.com/turnierfotografie/) | Shop vouchers as class prizes for organisers |
| [Rotfuchs](https://www.pictrs.com/rotfuchs?l=de) | Fast search plus direct download |
| [sb-fotografien](https://sb-fotografien.web.profi-fotos-online.com/) | profi-fotos-online shop as the alternative to Pictrs |

### Wedding photography (12)
| Site | What's good / what we take |
|---|---|
| [Anja Menzel](https://www.anjamenzelfotografie.de/preise/hochzeitsfotografie-preise/) (Berlin) | 3 clear tiers €950 / €1,600 / €2,550, photo box included, deposit plus rest before delivery |
| [Photogenika](https://muenchen-hochzeitsfotografen.de/preise/) (München) | 5 named packages up to €3,900. Album €599, parent album €99. **−€200 weekday/off-season** |
| [Lichtraum](https://lichtraum-euskirchen.de/blog/hochzeitsfotograf-kosten-preise) (NRW) | €1,790 / €2,490 / €2,990. Album €690 (30×30, 40 pages). Transparent pricing blog for SEO |
| [Light Hunters](https://light-hunters.com/blog/hochzeitsfotografie-kosten) | €795–2,995. Photo+video combo discount, off-season discount |
| [Brian Lorenzo](https://www.brianlorenzo.com/hochzeitsfotograf-preise-vergleich-2026/) | One clear 6h package for €1,550. "No hidden costs" positioning |
| [Marco Schwarz](https://www.schwarz-bild.de/was-kosten-hochzeitsfotografen/) | Deposit 20–40 %. Fine-art albums from €600 |
| [Kerim Kelmendi](https://www.kerimkelmendi.de/post/was-kostet-ein-hochzeitsfotograf) | Small hour-based tiers (1.5h €300 to full day €2,000+) |
| [Maii Studio](https://www.maii.studio/de/post/hochzeitsfotograf-videograf-preise-2026) | Photo+video bundles ~25 % cheaper |
| [Lichtecht](https://www.lichtecht-hochzeitsfotografie.de/preise/) (Sachsen) | Explains the editing workload (10h shoot ≈ 25h editing) |
| [Lovebird Weddings](https://lovebird-weddings.de/preise/) (Heilbronn) | 4 named packages €900–3,250. **Guest gallery and short couple video included**. "Popular" badge |
| [Jenia Symonds](https://www.jeniasymonds.com/preise/) | Sneak peek within 1–3 days. Engagement €199, or €150 with a wedding booking |
| [Nadja Osieka](https://nadjaosieka.de/preise-paarshooting-und-verlobungsshooting/) | Engagement shoot €299 for 5 images, "all images" upsell |

Market data: [pix.wedding](https://www.pix.wedding/hochzeitsfotograf-kosten-2026-preise-guide) gives a German average of about €1,656, and a full day costs about €1,940. Deposits are normally 20–40 %, with the rest due 2–4 weeks before the wedding.

### Best ideas we adopt
1. A split home page, so each world stays clean (unique among the researched sites).
2. A low entry price plus upsell image bundles (Eckerl, Bogner). This is the strongest horse revenue model.
3. Group/barn days priced per horse, with free images for the organiser (Knabe, Reuter).
4. A presale show flat rate plus pay-per-photo bundles plus winner vouchers (Sanwald, Lightwork, Kleuver).
5. 3–4 named wedding packages with a "most popular" badge in the middle (Lovebird, Photogenika).
6. Weekday/off-season discounts for weddings (Photogenika, Light Hunters).
7. A sneak peek within 72h, and a guest gallery with a shop (Symonds, Lovebird).
8. Transparent price pages plus "what does it cost" blog posts for SEO (Lichtraum, Lorenzo).
9. A gift voucher in the main menu. It is the #1 product for horse owners (Christmas, birthdays).

---

## 5. Proposed prices (2026/27)

These sit at the **upper mid-market**. That is premium enough to look professional, with a cheap entry point to win new clients. Prices are gross. If you're a Kleinunternehmer (§19 UStG), keep them as they are and add "gem. §19 UStG keine MwSt."

### 5.1 Horse shootings (100 % pre-paid)
| Package | Price | Includes |
|---|---|---|
| **Pony** (Mini) | **€149** | 45 min, 1 horse, 5 edited images (digital, full res.) |
| **Warmblut** (Classic) ⭐ most popular | **€289** | 90 min, horse + rider, 2 outfits, 12 edited images, online selection gallery |
| **Kaltblut** (Signature) | **€479** | 2.5 h, golden hour or black background, 25 edited images, all other images basic-edited, 1 fine-art print 30×45 |
| **Stalltag** (group day) | **€89 / horse** | min. 5 horses, 30 min each, 3 images. Organiser's shoot free |
| **Black Background Mini** | €119 | 30 min, 4 studio-style portraits on black |
| **Fohlen / Verkaufsfotos** | €169 | Conformation + movement photos for sales ads, 8 images, delivery in 48h |

Add-ons: extra image €25 · bundles of 5 images €99 / 10 €179 / all images €249 · extra horse +€49 · extra person +€25 · drone +€59 · 48h express +€49
Travel: 30 km free, then €0.40/km.

### 5.2 Horse shows (Turnierfotografie)
| Product | Price |
|---|---|
| Single digital photo (full res.) | €12.90 |
| Bundle of 3 photos | €29.90 |
| Bundle of 6 photos ⭐ | €49.90 |
| All photos of one ride (round/test) | €59 |
| **Turnier-Flatrate**: all photos of 1 horse at the show, all days | €119 (presale before the show: **€89**) |
| Second horse at the same show | +€49 |
| Print 13×18 / 20×30 / 30×45 | €9.90 / €19.90 / €34.90 |
| Canvas 40×60 | €89 |

Organisers: free coverage for shows with more than about 250 starts (I earn from photo sales). Small shows: a €150 flat fee. Each class winner gets a €15 shop voucher (paid by me as marketing). The organiser gets 20 social-media images.

### 5.3 Wedding packages (30 % deposit at booking)
| Package | Price | Includes |
|---|---|---|
| **Ja-Wort** (civil ceremony) | **€890** | 2.5 h, ~150 images, online gallery |
| **Herzstück** | **€1,890** | 7 h (ceremony → first dance), ~450 images, sneak peek in 72h, guest gallery |
| **Für Immer** ⭐ most popular | **€2,690** | 10 h, ~700 images, engagement shoot included, sneak peek, guest gallery |
| **Grenzenlos** | **€3,790** | 12 h, second photographer, engagement shoot, album 30×30 (40 pages), 2 parent albums |

Add-ons: extra hour €190 · second photographer €590 · drone €249 · express delivery (2 weeks) €190 · photo box with 30 fine-art prints €249
Off-season (Nov–Mar) and Mon–Thu: **−10 %**
Travel: 50 km free, then €0.40/km, plus an overnight stay if over 250 km.

### 5.4 Couple shoots (100 % pre-paid)
| Shoot | Price |
|---|---|
| Engagement / couple shoot, 1 h, ~50 images | €290 (€190 with a wedding booking) |
| After-wedding shoot, 2 h, ~80 images | €390 |

### 5.5 Albums and products (both worlds)
| Product | Price |
|---|---|
| Wedding album 30×30, 40 pages, linen/leather | €590 (+€15 per extra double page) |
| Parent album 20×20 (copy of the main album) | €149 |
| Horse album 20×20, 20 pages | €149 |
| Horse album 30×30, 30 pages | €349 |
| Fine-art print 20×30 / 30×45 / 40×60 | €39 / €69 / €99 |
| Canvas 60×90 | €189 |
| Acrylic (AluDibond) 60×90 | €269 |
| Gift voucher | any amount from €50, or per package; valid 3 years |

Expected margins: lab cost is about 25–35 % of the sale price for prints and albums. For shoots, editing time is the main cost.

---

## 6. Legal must-haves (Germany)
- Impressum, a DSGVO privacy policy and cookie consent (Borlabs or Complianz).
- AGB that cover cancellation, weather, copyright/usage rights (private use only) and the model release.
- **Widerrufsrecht**: digital downloads need an explicit checkbox where the customer waives withdrawal. Bookings for a specific date are exempt under §312g BGB (leisure services with a fixed date). Have a lawyer or the legal-text service confirm this.
- Show photos: the organiser agreement must allow photography and sale. Add a note on the shop page.
- Prices shown with VAT info and shipping costs (PAngV).

---

## 7. Roadmap
1. **Phase 0 – Decisions** (you): confirm prices and package names, region/home base, Kleinunternehmer yes or no, and whether to use Pictrs.
2. **Phase 1 – Foundation**: give me an Application Password for ruidodat.com. I make a backup and a staging copy, install the theme and plugins, and build the split home page plus both worlds.
3. **Phase 2 – Booking and shop**: booking calendar with pre-payment and deposit, vouchers, print/album products, and connection to the print lab.
4. **Phase 3 – Events**: Pictrs account, show calendar, presale flat rate, organiser page.
5. **Phase 4 – Content and SEO**: portfolio uploads, 3 "what does it cost" blog posts per world, Google Business profiles, reviews.
6. **Launch**, then monthly reviews of what sells.

## 8. What I need from you
- 20–40 of your best horse images and any wedding images you have (or a plan for building a wedding portfolio).
- Home base or city for travel pricing and SEO.
- Logo and font/colour preferences (or I propose them).
- Business status: Kleinunternehmer or VAT registered.
- WordPress Application Password once you're ready.
