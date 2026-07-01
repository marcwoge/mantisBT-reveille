# Reveille

[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://www.php.net/)
[![MantisBT](https://img.shields.io/badge/MantisBT-2.x-F0F0F0.svg)](https://www.mantisbt.org/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![Maintained](https://img.shields.io/badge/Maintained-yes-brightgreen.svg)](https://github.com/marcwoge/reveille)

> **Schedule any ticket to sleep – wake it up exactly when it matters.**

Reveille is a MantisBT plugin that puts tickets into a "sleeping" (`deferred`)
state and automatically wakes them up at a configurable point in time, handing
them back to your normal MantisBT workflow.

It does exactly one thing: **once a day, check every `deferred` ticket, and if
it has reached its activation threshold, set it back to *new/open*.**

🇩🇪 *Deutsche Version: [README.de.md](README.de.md)*

## Use cases

Reveille is deliberately generic. It fits any situation where a ticket should be
temporarily inactive and resurface later:

- **Recurring inspection & maintenance intervals** – e.g. an operating-equipment
  register where each asset carries a `due_date` (inspection deadline).
- **Seasonal tasks** that only matter at a certain time of year.
- **Contractual review dates.**
- **Regulatory follow-ups.**
- Any ticket that follows the motto *"not now, but definitely later."*

### Primary scenario (operating-equipment register)

Assets are fed into MantisBT through the REST API (e.g. via Apache NiFi),
including a `due_date`. After a passed inspection the external system sets the
ticket to `deferred` and writes the new `due_date`. Reveille wakes the ticket up
at the right time so the normal workflow takes over again.

## How it works

```
              external system (NiFi, script, …)            Reveille cron (daily)
  ticket ----> sets status = deferred + due_date  ----> wakes ticket up when
                                                         today >= activate_on
                                                         -> status = new/open
```

The activation date is computed per project (see [Configuration](#configuration)).

## Requirements

- MantisBT 2.x
- PHP 7.4+
- No external dependencies (no Composer, no NPM)

## The `deferred` status

Reveille relies on a custom status **`deferred` (ID 15, colour `#e8eaf6`)**.
A plugin cannot register a status on its own, so you add it to your
`config_inc.php`:

```php
// Register the "deferred" status (id 15) used by Reveille.
$g_status_enum_string = '10:new,20:feedback,30:acknowledged,40:confirmed,50:assigned,15:deferred,80:resolved,90:closed';

// Colour for the status (light indigo).
$g_status_colors['deferred'] = '#e8eaf6';
```

If the status is missing, Reveille shows a prominent warning on its admin pages
and at install time. The plugin still installs – just add the status when ready.

> The exact `status_enum_string` above is an example; merge the `15:deferred`
> entry into your existing configuration rather than overwriting it.

### Status labels for the Change Status page

MantisBT builds its **Change Status** page from per-status language strings
(`<status>_bug_title` and `<status>_bug_button`). These exist for the built-in
statuses but must be supplied for the custom `deferred` status – otherwise you
get `APPLICATION WARNING #300: String "deferred_bug_title" not found.` when you
defer a ticket.

Add them to `config/custom_strings_inc.php` (create the file if it doesn't
exist yet):

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

### Manual

1. Copy the [`Reveille/`](Reveille) folder from this repository to
   `<mantis>/plugins/Reveille` (the folder name **must** be `Reveille`).
   The release also ships this folder as a ready-to-drop `Reveille/` zip.
2. Add the `deferred` status to `config_inc.php` (see above).
3. Log in as an administrator → **Manage → Manage Plugins → Install** *Reveille*.
   This creates the `mantis_reveille_config` table.

### Docker

A complete throwaway environment (MantisBT + MySQL) lives in
[`docker/`](docker/). See [docker/README_DOCKER.md](docker/README_DOCKER.md):

```bash
cd docker
docker compose up -d --build
# then run the one-time installer + seed as described in README_DOCKER.md
```

## Configuration

**Manage → Reveille → Reveille Configuration** lists every project with its own
activation rule:

| Mode | Behaviour |
|------|-----------|
| `interval` | Ticket activates **X days / weeks / months before** its `due_date`. |
| `year` | Ticket activates on **1 January of the `due_date`'s year**. |

The interval fields are hidden automatically when the mode is `year`. Each row
also shows how many tickets are currently sleeping in that project. Projects
without an explicit configuration fall back to **interval / 90 / days**.

Configuration is stored in `mantis_reveille_config`:

```sql
CREATE TABLE IF NOT EXISTS `mantis_reveille_config` (
    `project_id`    INT          NOT NULL PRIMARY KEY,
    `mode`          ENUM('year','interval') NOT NULL DEFAULT 'interval',
    `interval_val`  INT          NOT NULL DEFAULT 90,
    `interval_unit` ENUM('days','weeks','months') NOT NULL DEFAULT 'days'
);
```

> The plugin creates the table via its `schema()` migration using your
> configured DB table prefix (default `mantis_reveille_config`). The `ENUM`
> columns are represented as short `VARCHAR`s for cross-database portability;
> the allowed values are enforced in the UI.

## Dashboard

**Manage → Reveille → Reveille Dashboard** lists all sleeping tickets with
project, ticket id, summary, `due_date`, computed activation date and days
remaining. Rows are sorted by activation date and colour-coded:

| Colour | Days until activation |
|--------|----------------------|
| 🟢 green | > 90 |
| 🟡 yellow | < 90 |
| 🟠 orange | < 30 |
| 🔴 red | < 7 (or already due) |

## Cron setup

Run the CLI cron once a day:

```cron
0 6 * * * php /path/to/mantis/plugins/Reveille/scripts/reveille_cron.php
```

Options:

```bash
# Show what would happen, change nothing:
php scripts/reveille_cron.php --dry-run

# Run for real:
php scripts/reveille_cron.php
```

On each reactivation Reveille adds an internal bugnote:

```
[Reveille] Ticket automatically reactivated. Due date: YYYY-MM-DD
```

> The cron attributes its changes to an administrator account. It logs in via
> MantisBT's trusted script-login mechanism as `administrator` by default; set
> the `REVEILLE_USER` environment variable to use a different admin account.

## Setting the status externally (REST API)

Any external system can put a ticket to sleep by setting its status to
`deferred`, e.g.:

```http
PATCH /api/rest/issues/{id}
Content-Type: application/json

{
  "status": { "name": "deferred" }
}
```

Combine this with setting `due_date` to control when Reveille wakes it up again.

## What Reveille does *not* do

- No separate email/reminder system (MantisBT reminders are untouched).
- No hook on status change (an external system sets the status via API).
- No core modifications.
- No Composer/NPM dependencies.
- No UI to manually defer tickets (you or an external system do that).

## Contributing

Issues and pull requests are welcome. Please keep the plugin dependency-free and
PHP 7.4 compatible.

## License

MIT © Marc-Philipp Woge. See [LICENSE](LICENSE).
