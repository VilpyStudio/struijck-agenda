# Struijck Agenda

Custom WordPress plugin voor agendabeheer van sporthal De Struijck. Gemaakt voor zaanhaven.nl.

## Wat doet het?

- Beheer activiteiten via een schone WordPress admin-interface
- Ondersteunt **terugkerende afspraken** (dagelijks/wekelijks/maandelijks, op specifieke weekdagen, met einddatum en uitzonderingen)
- Beheer verschillende **zalen** als categorieën
- Toon de agenda op de site in **maand-, week- en lijstweergave**
- Frontend filter op zaal
- **iCal-export** voor Google Calendar/Outlook
- **REST API** endpoint
- **Elementor widget** + shortcode
- Geen externe dependencies, geen Pro-upsell, geen commissies

## Installeren

1. Zip de map `struijck-agenda` (rechtsklik → comprimeren naar `.zip`)
2. WordPress admin → Plugins → Nieuwe plugin → Plugin uploaden → kies de zip → Installeren → Activeren
3. In de admin sidebar verschijnt **Agenda**

## Automatische updates (privé GitHub-repo)

De plugin controleert zelf GitHub Releases van `VilpyStudio/struijck-agenda` en
toont updates in **WordPress → Plugins** net als elke andere plugin.

Omdat de repo **privé** is, heeft de site een GitHub access token nodig met
leesrechten op deze repo. Zet dit in `wp-config.php` (boven `/* That's all,
stop editing! */`):

```php
define( 'STRUIJCK_AGENDA_GITHUB_TOKEN', 'github_pat_xxxxxxxx' );
```

Gebruik bij voorkeur een **fine-grained token** met alleen
`Contents: Read-only` op de repo `VilpyStudio/struijck-agenda`. Zonder geldig
token verschijnen er geen updates (de plugin blijft gewoon werken).

Een nieuwe versie uitbrengen:

1. Verhoog `Version:` in de plugin-header én `STRUIJCK_AGENDA_VERSION` in `struijck-agenda.php`
2. Commit en push naar `main`
3. Tag pushen: `git tag v1.3.0 && git push origin v1.3.0`
4. De GitHub Action bouwt automatisch `struijck-agenda.zip` en hangt die als asset aan de release
5. Sites zien de update binnen enkele uren (of direct via "Controleer op updates")

## Gebruik

### 1. Zalen aanmaken
- Agenda → Zalen → voeg toe: bv. "Grote zaal", "Zaal 2", "Vergaderruimte"

### 2. Activiteit toevoegen
- Agenda → Nieuwe activiteit
- Titel + omschrijving invullen
- **Datum & tijd** box: start datum, start tijd, eind tijd
- **Terugkerende afspraak** box: vink aan als het een vaste activiteit is
  - Kies frequentie (wekelijks bij vaste trainingen)
  - Vink de dagen aan
  - Optioneel: einddatum
  - Optioneel: uitzonderingen (bv. feestdagen) als komma-gescheiden datums
- Kies de **Zaal** (rechterkolom)
- Publiceren

### 3. Op de site tonen

**Met shortcode:**
```
[struijck_agenda]
```

Opties:
```
[struijck_agenda view="list"]
[struijck_agenda view="week" zaal="grote-zaal" filters="no"]
```

**In Elementor:**
- Sleep de "Struijck Agenda" widget naar je pagina
- Stel weergave/zaal in via het paneel
- Stijl aanpassen via het Stijl-tabblad (hoofdkleur, accentkleur)

### 4. iCal-feed
Externe kalender-apps kunnen de feed importeren:
```
https://zaanhaven.nl/?struijck_ical=feed
```

## Styling aanpassen

De plugin gebruikt CSS-variabelen. Voeg dit toe in Elementor → Site Settings → Custom CSS om kleuren te overrulen:

```css
.struijck-agenda {
    --sa-color-primary: #1a4d8c;        /* hoofdkleur (knoppen, events) */
    --sa-color-accent: #f59e0b;          /* accent (vandaag-highlight) */
    --sa-color-primary-soft: #e7f0fa;    /* zachte achtergrond */
    --sa-radius: 8px;                    /* rondingen */
}
```

## REST API

Endpoint voor eigen integraties:
```
GET /wp-json/struijck-agenda/v1/occurrences?start=2026-01-01&end=2026-12-31&zaal=grote-zaal
GET /wp-json/struijck-agenda/v1/zalen
```

Geeft JSON terug met alle activiteit-occurrences (recurring events worden automatisch uitgerold).

## Technische details

- PHP 7.4+
- WordPress 5.8+
- Geen externe dependencies
- Vanilla JS frontend (geen jQuery vereist)
- Werkt met of zonder Elementor (Elementor-widget alleen als Elementor actief is)

## Tips

- **Terugkerende activiteiten zijn slim**: één activiteit "Volleybal training" met "wekelijks op maandag tot 31 dec 2026" geeft automatisch 52 occurrences in de agenda. Je hoeft het maar één keer in te voeren.
- **Uitzonderingen**: vakantie of feestdag? Voeg de datum toe aan het uitzonderingenveld. De activiteit verschijnt dan niet op die datum.
- **Eén activiteit aanpassen**: voor incidentele wijzigingen kun je de uitzonderingsdatum toevoegen aan de recurring activity, en een losse activiteit voor die ene dag aanmaken met de gewijzigde info.

## Backup-tip

Voordat je live gaat: maak een backup van de site (UpdraftPlus is gratis en goed). De plugin maakt aparte database-entries dus rollback is altijd mogelijk via WordPress' eigen content management.
