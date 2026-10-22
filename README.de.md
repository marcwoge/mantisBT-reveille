# Reveille

[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://www.php.net/)
[![MantisBT](https://img.shields.io/badge/MantisBT-2.x-F0F0F0.svg)](https://www.mantisbt.org/)
[![Lizenz: MIT](https://img.shields.io/badge/Lizenz-MIT-yellow.svg)](LICENSE)
[![Gepflegt](https://img.shields.io/badge/Gepflegt-ja-brightgreen.svg)](https://github.com/marcwoge/mantisBT-reveille)

> **Lege jedes Ticket schlafen – und wecke es genau dann, wenn es zählt.**

Reveille ist ein MantisBT-Plugin, das Tickets in einen „schlafenden" Zustand
(`deferred` / *zurückgestellt*) versetzt und sie zu einem konfigurierbaren
Zeitpunkt automatisch wieder aufweckt – woraufhin der normale MantisBT-Workflow
übernimmt.

Es tut genau eine Sache: **täglich jedes `deferred`-Ticket prüfen und es, sobald
es seine Aktivierungsschwelle erreicht hat, wieder auf *neu/offen* setzen.**

🇬🇧 *English version: [README.md](README.md)*

## Anwendungsfälle

Reveille ist bewusst generisch gehalten und passt überall, wo ein Ticket
temporär inaktiv sein und später wieder auftauchen soll:

- **Wiederkehrende Prüf- und Wartungsfristen** – z. B. ein
  Betriebsmittelkataster, in dem jedes Betriebsmittel eine `due_date`
  (Prüffälligkeit) trägt.
- **Saisonale Aufgaben**, die nur zu bestimmten Zeiten relevant sind.
- **Vertragliche Review-Termine.**
- **Regulatorische Wiedervorlagen.**
- Jedes Ticket nach dem Motto *„nicht jetzt, aber definitiv später".*

### Primärer Anwendungsfall (Betriebsmittelkataster)

Betriebsmittel werden über die REST-API (z. B. via Apache NiFi) in MantisBT
eingepflegt – inklusive `due_date`. Nach bestandener Prüfung setzt das externe
System das Ticket auf `deferred` und trägt die neue `due_date` ein. Reveille
weckt das Ticket zur richtigen Zeit wieder auf, sodass der normale Workflow
erneut greift.

## Funktionsweise

```
              externes System (NiFi, Skript, …)            Reveille-Cron (täglich)
  Ticket ----> Status = deferred + due_date setzen ----> weckt Ticket, sobald
                                                          today >= activate_on
                                                          -> Status = neu/offen
```

Die Wiedervorlage (`activate_on`) wird pro Projekt berechnet (siehe
[Konfiguration](#konfiguration)).

## Voraussetzungen

- MantisBT 2.x (empfohlen: **2.28.4 oder neuer**, behebt CVE-2026-47156)
- PHP 7.4+
- Keine externen Abhängigkeiten (kein Composer, kein NPM)

## Der Status `deferred`

Reveille benötigt einen eigenen Status **`deferred` (ID 15, Farbe `#e8eaf6`,
deutsch: „zurückgestellt")**. Ein Plugin kann keinen Status selbst registrieren,
daher ergänzen Sie ihn in Ihrer `config_inc.php`:

```php
// Status „deferred" (ID 15) für Reveille registrieren.
$g_status_enum_string = '10:new,20:feedback,30:acknowledged,40:confirmed,50:assigned,15:deferred,80:resolved,90:closed';

// Farbe für den Status (helles Indigo).
$g_status_colors['deferred'] = '#e8eaf6';
```

Fehlt der Status, zeigt Reveille auf seinen Admin-Seiten und bei der Installation
eine deutliche Warnung. Das Plugin lässt sich trotzdem installieren – ergänzen
Sie den Status einfach, sobald es passt.

> Die obige `status_enum_string` ist ein Beispiel; fügen Sie den Eintrag
> `15:deferred` in Ihre bestehende Konfiguration ein, statt sie zu überschreiben.

### Status-Beschriftungen für die Statuswechsel-Seite

MantisBT baut seine **Statuswechsel-Seite** aus Sprachstrings pro Status
(`<status>_bug_title` und `<status>_bug_button`). Für die eingebauten Status
existieren diese; für den eigenen Status `deferred` müssen sie ergänzt werden –
sonst erscheint beim Zurückstellen eines Tickets
`APPLICATION WARNING #300: Zeichenkette „deferred_bug_title" nicht gefunden.`

Ergänzen Sie sie in `config/custom_strings_inc.php` (Datei anlegen, falls noch
nicht vorhanden):

```php
<?php
switch( $g_active_language ) {
    case 'german':
        $s_deferred_bug_title  = 'Ticket zurückstellen';
        $s_deferred_bug_button = 'Ticket zurückstellen';
        break;
    default:
        $s_deferred_bug_title  = 'Defer this ticket';
        $s_deferred_bug_button = 'Defer ticket';
}
```

## Installation

### Manuell

1. Den Ordner [`Reveille/`](Reveille) aus diesem Repository nach
   `<mantis>/plugins/Reveille` kopieren (der Ordnername **muss** `Reveille`
   lauten). Das Release liefert diesen Ordner zusätzlich als fertiges
   `Reveille/`-Zip zum direkten Ablegen.
2. Den Status `deferred` in der `config_inc.php` ergänzen (siehe oben).
3. Als Administrator anmelden → **Verwaltung → Plugins verwalten → Installieren**
   *Reveille*. Dabei wird die Tabelle `mantis_reveille_config` angelegt.

### Docker

Eine vollständige Wegwerf-Umgebung (MantisBT + MySQL) liegt unter
[`docker/`](docker/). Siehe [docker/README_DOCKER.md](docker/README_DOCKER.md):

```bash
cd docker
docker compose up -d --build
# danach den einmaligen Installer + Seed wie in README_DOCKER.md beschrieben ausführen
```

## Konfiguration

**Verwaltung → Reveille → Reveille-Konfiguration** listet jedes Projekt mit
seiner eigenen Aktivierungsregel:

| Modus | Verhalten |
|-------|-----------|
| `interval` | Ticket aktiviert **X Tage / Wochen / Monate vor** der `due_date`. |
| `year` | Ticket aktiviert am **1. Januar des Fälligkeitsjahres**. |

Die Intervallfelder werden automatisch ausgeblendet, wenn der Modus `year` ist.
Jede Zeile zeigt außerdem, wie viele Tickets im Projekt aktuell schlafen.
Projekte ohne eigene Konfiguration nutzen den Standard **interval / 90 / Tage**.

Die Konfiguration wird in `mantis_reveille_config` gespeichert:

```sql
CREATE TABLE IF NOT EXISTS `mantis_reveille_config` (
    `project_id`    INT          NOT NULL PRIMARY KEY,
    `mode`          ENUM('year','interval') NOT NULL DEFAULT 'interval',
    `interval_val`  INT          NOT NULL DEFAULT 90,
    `interval_unit` ENUM('days','weeks','months') NOT NULL DEFAULT 'days'
);
```

> Das Plugin legt die Tabelle über seine `schema()`-Migration an und nutzt dabei
> Ihren konfigurierten DB-Tabellen-Präfix (Standard `mantis_reveille_config`).
> Die `ENUM`-Spalten werden aus Portabilitätsgründen als kurze `VARCHAR`s
> abgebildet; die erlaubten Werte werden in der Oberfläche erzwungen.

## Dashboard

**Verwaltung → Reveille → Reveille-Dashboard** listet alle schlafenden Tickets
mit Projekt, Ticket-ID, Zusammenfassung, `due_date`, berechneter Wiedervorlage
und verbleibenden Tagen. Sortiert nach Wiedervorlage, farblich abgestuft:

| Farbe | Tage bis Aktivierung |
|-------|----------------------|
| 🟢 grün | > 90 |
| 🟡 gelb | < 90 |
| 🟠 orange | < 30 |
| 🔴 rot | < 7 (oder bereits fällig) |

## Cron einrichten

Den CLI-Cron einmal täglich ausführen:

```cron
0 6 * * * php /pfad/zu/mantis/plugins/Reveille/scripts/reveille_cron.php
```

Optionen:

```bash
# Zeigt, was passieren würde, ändert nichts:
php scripts/reveille_cron.php --dry-run

# Echter Lauf:
php scripts/reveille_cron.php
```

Bei jeder Reaktivierung trägt Reveille eine interne Notiz ein:

```
[Reveille] Ticket automatically reactivated. Due date: YYYY-MM-DD
```

> Der Cron schreibt seine Änderungen unter einem Administrator-Konto. Er meldet
> sich über den vertrauenswürdigen Skript-Login von MantisBT standardmäßig als
> `administrator` an; setzen Sie die Umgebungsvariable `REVEILLE_USER`, um ein
> anderes Admin-Konto zu verwenden.

## Status extern setzen (REST-API)

Jedes externe System kann ein Ticket schlafen legen, indem es den Status auf
`deferred` setzt, z. B.:

```http
PATCH /api/rest/issues/{id}
Content-Type: application/json

{
  "status": { "name": "deferred" }
}
```

In Kombination mit dem Setzen der `due_date` steuern Sie, wann Reveille das
Ticket zur Wiedervorlage wieder aufweckt.

## Was Reveille *nicht* tut

- Kein eigenes E-Mail-/Reminder-System (MantisBT-Reminder bleiben unverändert).
- Kein Hook nach Statuswechsel (ein externes System setzt den Status per API).
- Keine Änderungen am MantisBT-Core.
- Keine Composer-/NPM-Abhängigkeiten.
- Kein UI zum manuellen Zurückstellen (das machen Sie selbst oder ein externes
  System).

## Mitwirken

Issues und Pull Requests sind willkommen. Bitte halten Sie das Plugin
abhängigkeitsfrei und PHP-7.4-kompatibel.

## Lizenz

MIT © Marc-Philipp Woge. Siehe [LICENSE](LICENSE).
