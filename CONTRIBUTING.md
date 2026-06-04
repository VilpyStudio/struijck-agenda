# Ontwikkelaars-handleiding

Korte referentie om de plugin te onderhouden en uit te breiden. De `README.md` is voor de gebruiker; dit bestand is voor wie aan de code werkt.

## Snel starten

```bash
git clone https://github.com/VilpyStudio/struijck-agenda.git
cd struijck-agenda
```

Of, als je 'm al hebt:

```bash
cd ~/Downloads/struijck-agenda && git pull
```

De plugin draait zonder build-stap — `php`/`js`/`css` lopen rechtstreeks. Test in een lokale WordPress (LocalWP / DDEV) of upload de zip in een staging-site.

## Architectuur

```
struijck-agenda.php             Bootstrap: versie, constants, require_once, init-hooks
includes/
  class-post-types.php          CPT struijck_activiteit + taxonomies struijck_zaal
                                (met "mag dubbel verhuurd worden" term-meta) en
                                struijck_huurder; admin-notice voor pending
                                aanvragen; cascade-delete bij huurder verwijderen
  class-meta-fields.php         Meta-keys _struijck_* + get_activity_meta()
  class-recurring.php           Recurring engine: expanded occurrences over een
                                date range, honors weekday-mask + exceptions
  class-rest-api.php            REST endpoints:
                                  GET  /occurrences  (start, end, zaal)
                                  GET  /zalen
                                  POST /request      (publieke aanvraag)
  class-ical.php                iCal-feed; UID-domein komt uit home_url()
  class-github-updater.php      Lichte updater (publieke repo, geen token),
                                 + "Controleer op nieuwe versies" in plugin meta-row
admin/
  class-admin-calendar.php      Maand-planner: AJAX endpoints, conflictdetectie,
                                term-meta UI voor zalen
  calendar.js / calendar.css    Grid, modals, huurder-combobox, tijd-dropdowns
public/
  class-shortcode.php           Shortcode + asset-enqueue, bouwt config + zalen-
                                lijst voor de JS (incl. allowDouble)
  class-elementor-widget.php    Elementor-widget rondom de shortcode + Style-
                                controls die op de --sa-* CSS-vars aansluiten
  js/agenda.js                  Week/Maand/Lijst views, dag-modal, aanvraag-
                                formulier (availability-aware)
  css/agenda.css                Frontend-styling met --sa-* CSS-vars
.github/workflows/release.yml   Bouwt struijck-agenda.zip bij elke v*.*.* tag
```

## Een nieuwe versie releasen

1. Bump beide plekken in `struijck-agenda.php`:
   ```php
    * Version:     1.x.y
   define( 'STRUIJCK_AGENDA_VERSION', '1.x.y' );
   ```
2. (Optioneel) Voeg een sectie toe in `CHANGELOG.md`.
3. Commit + push naar `main`.
4. Tag en push:
   ```bash
   git tag v1.x.y
   git push origin v1.x.y
   ```
5. De Action bouwt automatisch `struijck-agenda.zip` en hangt 'm aan de release.
6. Sites krijgen de update via **Plugins → Controleer op nieuwe versies** (de link zit in de plugin-rij).

Semver-leidraad:
- **patch** (1.x.Y): bugfix, kleine tweak.
- **minor** (1.X.0): nieuwe feature, backward compatible.
- **major** (X.0.0): breaking change (DB, shortcode-API, REST-routes).

## Design system

Alle kleuren/afrondingen zijn CSS-variabelen, ingesteld op `.struijck-agenda` en overrulebaar via de Elementor Stijl-tab.

| Var                 | Default     | Rol                       |
| ------------------- | ----------- | ------------------------- |
| `--sa-text`         | `#1a3d3a`   | Donker teal — tekst       |
| `--sa-text-soft`    | `#6b7280`   | Subtitels / secundair     |
| `--sa-accent`       | `#e8643c`   | Warm oranje — accent      |
| `--sa-bg`           | `#ffffff`   | Witte kaart-achtergrond   |
| `--sa-bg-soft`      | `#faf3eb`   | Crème surface             |
| `--sa-border`       | `#ece3d6`   | Subtiele randen           |
| `--sa-time-color`   | `#1a3d3a`   | Tijden in rooster         |
| `--sa-radius`       | `18px`      | Grote afronding (kaart)   |
| `--sa-radius-sm`    | `999px`     | Pill-afronding (knoppen)  |
| `--sa-request-bg`   | `accent`    | Aanvraag-knop achtergrond |

`--sa-accent-soft` wordt automatisch afgeleid via `color-mix(in srgb, var(--sa-accent) 12%, transparent)` voor hover-tints.

## Niet-vanzelfsprekende valkuilen

- **Sitethema's stylen élke `<button>`** — destruijck.nl forceerde een gekleurde stijl op alle knoppen (ook hover). Daarom gebruiken `.sa-nav-btn`, `.sa-filter-pill` en `.sa-tab` `!important` op visuele identity-properties (background, border, border-radius, padding). Houd dit bij refactors. Test op een ander thema voor je het weghaalt.
- **Geen WP-nonce op `/request`** — een page-embedded nonce is onbetrouwbaar onder full-page caching (LiteSpeed e.d.). Bescherming = honeypot + per-IP throttle + strikte validatie + handmatige goedkeuring.
- **Ontvanger-e-mail server-side** — komt uit `get_option('struijck_request_email')`. Wordt geset via `update_option` als de Elementor-widget de `email` attr meegeeft. Vertrouw de waarde **nooit** uit de request body (relay-risk).
- **iCal-UID-domein** — uit `home_url()`, nooit hardgecodeerd. Voor client-isolation.
- **Geen client-domein in deliverables** — controleer bij toevoegingen of er niet per ongeluk een andere klant z'n domein insluipt.
- **Huurder verwijderen = boekingen weg** — `pre_delete_term` cascade-delete is permanent (geen prullenbak). Vermeld dit in UI als je 'm uitbreidt.

## Site-context destruijck.nl

- Elementor Pro actief, plugin draait via de Struijck Agenda-widget.
- LiteSpeed Cache geïnstalleerd. Bij een release: purgen of even uitzetten tijdens testen.
- SMTP2GO als mailrelay — `wp_mail` loopt automatisch mee zodra de SMTP2GO-plugin geconfigureerd is. Zet "Van e-mail" op een adres op een geverifieerd domein.

## Memory voor Claude

Studio Vilpy heeft een project-memory (`~/.claude/projects/-Users-basdewildt/memory/project_struijck_agenda.md`) met dezelfde architectuur-samenvatting plus site-context. Bij een nieuwe Claude-sessie wordt die automatisch geladen — zeg "we werken verder aan Struijck Agenda" en je hoeft niets opnieuw uit te leggen.
