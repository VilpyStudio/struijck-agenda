# Changelog

Alle noemenswaardige wijzigingen aan deze plugin. Versienummers volgen [Semver](https://semver.org/lang/nl/).

## [1.14.2] — 2026-05-16
- **Fix:** op mobiel brak "tot HH:MM" af naar twee regels in de week-agenda. Eindtijd staat nu `nowrap` en de tijd-kolom is iets breder, zodat "tot 10:00" altijd op één regel blijft.

## [1.14.1] — 2026-05-16
- **Fix iOS:** datumveld krijgt weer `appearance:none` (lost overflow op), maar zonder geforceerde hoogte — niet meer te hoog, niet meer buiten de rand.
- **UX:** Week/Maand-switcher vervangen door één compact knopje dat de doel-weergave toont (`⇆ Maand` of `⇆ Week`).

## [1.14.0] — 2026-05-16
- **Fix:** eerste poging om iOS-datumveld te fixen + introductie van een kleine Week/Maand-switcher.

## [1.13.0] — 2026-05-15
- **Fix kritiek:** "Beveiligingscontrole mislukt" opgelost. Full-page caching maakte de WP-nonce in gecachte HTML onbetrouwbaar. Nonce-eis verwijderd; bescherming is nu honeypot + per-IP throttle (5/uur) + strikte validatie + handmatige goedkeuring.
- **Nieuw:** instellingen op de Elementor-widget voor ontvanger-e-mail (server-side opgeslagen via `struijck_request_email` option) en redirect-URL na een gelukte aanvraag.
- **Fix:** datum-/tijdvelden hardened tegen iOS-overflow; modal-breedte geklemd op viewport.

## [1.12.0] — 2026-05-15
- **Nieuw:** aanvraag-formulier toont **live beschikbaarheid** — al geboekte tijden in de gekozen zaal/datum worden uitgeschakeld in de start- en eindtijd-dropdowns. Hint "Al bezet: HH:MM–HH:MM" verschijnt eronder. Werkt niet bij zalen die dubbel verhuurd mogen worden of bij "Geen voorkeur".
- **Nieuw:** Elementor Style-sectie **"Aanvraagformulier"** (knop-kleur + label-typografie).

## [1.11.0] — 2026-05-15
- **Nieuw:** publieke datum-/tijd-aanvragen.
  - Knop **"Datum/tijd aanvragen"** boven het rooster + knop **"Deze dag aanvragen"** in het dag-paneel.
  - REST endpoint `POST /wp-json/struijck-agenda/v1/request` met dezelfde conflictcontrole als de admin (mits zaal niet dubbel mag).
  - Aanvragen worden opgeslagen als **"in afwachting"** in de kalender + **e-mail** naar de beheerder met edit-link.
  - **Admin-melding** met aantal aanvragen die op goedkeuring wachten.
  - **Spam:** honeypot + WP-nonce + per-IP throttle.
  - Elementor-/shortcode-toggle "Aanvragen toestaan".

## [1.10.0] — 2026-05-15
- **Nieuw:** maand-cel klikbaar/tikbaar → opent paneel met **álle** boekingen van die dag (lost "+3"-onbereikbaar op en toont op mobiel de tijden i.p.v. alleen stippen).
- **Fix:** maand-cel-uitlijning — events zitten nu in `.sa-mday__events` met gereserveerde hoogte, dus dag-nummers blijven op één lijn ongeacht of er boekingen zijn.

## [1.9.0] — 2026-05-15
- **Layout:** dag-weergave (week) vult op desktop/tablet automatisch meerdere kolommen (auto-fit, ~310px) — drukke dag blijft compact en overzichtelijk i.p.v. één lange lijst. Mobiel blijft enkele kolom.

## [1.8.3] — 2026-05-15
- **Fix:** sitethema bleef knop-stijl forceren ondanks specificiteit; nav/filter/tab visuals gehardend met `!important` (achtergrond, vorm, padding). Pills blijven nu rond op hover.
- **Fix:** filter/Vandaag wist de container niet meer naar "Laden…" — oude inhoud blijft staan, dimt kort, en wordt in één keer vervangen. Geen page-sprong meer. Card heeft `min-height` tegen inklappen.

## [1.8.2] — 2026-05-15
- **Wijziging:** Week/Maand/Lijst-switcher verwijderd van de frontend; weergave is alleen nog via de Elementor-widget instelbaar.
- **Fix:** hover-bug — mijn eigen reset zette `border-radius`/`padding` op `:hover`, waardoor pills vierkant werden. Reset raakt nu alleen thema-achtergrond/-schaduw aan.
- **Mobiel:** rijen veel compacter (kleinere tijden/titels, strakkere padding) zodat een drukke planning niet eindeloos scrollt.

## [1.8.1] — 2026-05-15
- **Mobiel:** geen side-scroll meer. Week wordt verticale dag-agenda (alle 7 dagen onder elkaar); maand een compact stippen-grid dat past op schermbreedte.
- **Fix:** thema-button-reset dekt nu ook `:hover/:focus/:active` met hogere specificiteit.

## [1.8.0] — 2026-05-15
- **Nieuw:** Maand- en Lijst-weergave geïmplementeerd (de Elementor "Standaard weergave" werkte voorheen niet — alleen Week bestond). Schakelaar Week/Maand/Lijst in de kop; klik op een maand-dag → springt naar die week.
- **Fix:** sitethema dwong oranje knop-stijl door; reset op alle knoppen binnen `.struijck-agenda` toegevoegd. Clean tab-look hersteld.

## [1.7.0] — 2026-05-15
- **Styling:** kaart-schaduw, hover/active/today-states, accent-getinte hovers (`color-mix`), `:focus-visible`, tabular cijfers in tijden.
- **Mobiel:** dag-tabs als snap-scrollende strip met verborgen scrollbar; actieve dag scrollt automatisch in beeld.

## [1.6.3] — 2026-05-15
- **Fix:** boeking opslaan waarbij eindtijd ≤ starttijd wordt geweigerd (server- én client-side).
- **Nieuw:** huurder verwijderen verwijdert automatisch alle boekingen van die huurder (`pre_delete_term`).

## [1.6.2] — 2026-05-15
- **Admin mobiel:** maand-weergave als compacte stippen (geen side-scroll); tik op een dag → bestaande dag-modal met lijst.

## [1.6.1] — 2026-05-15
- **Roll back:** crème hero/padding/zware schaduw uit de admin-look (brak mobiel). Terug naar een nuchter scherm, maar mét sterke responsive regels (modal bottom sheet, 16px inputs).

## [1.6.0] — 2026-05-15
- **Admin-look gelijk aan publiek rooster:** crème surface, oranje eyebrow + grote teal titel, één zwevende witte kaart. "Geen zaal" uit de legenda gehaald (boeking heeft altijd een zaal door Sporthal-default).

## [1.5.2] — 2026-05-15
- **"Controleer op nieuwe versies"-link** verhuisd naar de plugin meta-regel (naast versie/auteur).
- **Default zaal = Sporthal:** lege "Kies een zaal…"-optie verwijderd.

## [1.5.1] — 2026-05-15
- **Nieuw:** handmatige **"Controleer op nieuwe versies"**-link in de plugin-rij + cache-flush + admin-notice. Lost het "geen update zichtbaar" probleem op (WP/onze 6h release-cache).

## [1.5.0] — 2026-05-15
- **Admin design-system:** crème/teal/oranje doorgevoerd in toolbar, modal, formuliervelden, today-highlight; combobox met zichtbare chevron, klik-opent, mobiele bottom-sheet.
- **Elementor Style-controls** repareerd: de oude 2 kleurkiezers zetten niet-bestaande CSS-vars. Nu Kleuren-/Vormgeving-/Typografie-secties die wél op de echte `--sa-*` variabelen aansluiten.

## [1.4.0] — 2026-05-15
- **Nieuw:** custom huurder-combobox (filter, toetsenbord, vrije invoer). Native datalist verwijderd.
- **Nieuw:** per-zaal vinkje **"Mag dubbel verhuurd worden"** + conflictdetectie bij opslaan (sporthal blokkeert overlappende tijden; kantine mag dubbel).
- **Nieuw:** kleurcodering pills per zaal + legenda boven de kalender.

## [1.3.0] — 2026-05-15
- **Nieuw:** beheerbare huurders-lijst (`struijck_huurder` taxonomy, auto-aangroeiend) + datalist-input.
- **Nieuw:** start-/eindtijd als 30-min dropdowns (06:00–23:30), legacy-waardes blijven kiesbaar.
- **Rebrand:** auteur → Studio Vilpy; alle `zaanhaven.nl`-referenties verwijderd (incl. iCal-UID-domein, dat komt nu uit `home_url()`).

## [1.2.1] — 2026-05-15
- **Fix:** `ltrim($key, '_struijck_')` brak meta-keys (strip karakter-set i.p.v. prefix). Vervangen door `substr()`.

## [1.2.0] — 2026-05-15
- **Nieuw:** lichte GitHub Releases self-updater + `.github/workflows/release.yml` die bij een `v*.*.*` tag automatisch `struijck-agenda.zip` als release-asset bouwt.
