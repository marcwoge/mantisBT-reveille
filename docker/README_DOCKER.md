# Reveille – Docker Quickstart

A throwaway MantisBT 2.x + MySQL 8 environment for developing and testing the
Reveille plugin. The repository root is live-mounted into the container, so you
edit the plugin on your host and just refresh the browser.

> **Heads-up:** MantisBT always requires a one-time schema installation (it
> generates the schema itself; it cannot be pre-baked into MySQL). That is the
> only manual click in this setup. Everything else is automated.

## 1. Start the stack

```bash
cd docker
docker compose up -d --build
```

- MantisBT: <http://localhost:8080>
- MySQL: internal only (no host port)

## 2. Run the MantisBT installer (once)

Open <http://localhost:8080/admin/install.php> and click **Install/Upgrade
Database**. The database credentials are already supplied via the mounted
`config_inc.php` (db `bugtracker`, user `mantis`, password `mantis`), so you can
just submit the form.

This creates the schema and the default administrator account
(`administrator` / `root`).

> After a successful install, MantisBT recommends removing the `admin/`
> directory. For a throwaway demo you can ignore that.

## 3. Install the Reveille plugin

Log in as `administrator` / `root`, then go to **Manage → Manage Plugins** and
**Install** *Reveille*. This runs the schema migration and creates the
`mantis_reveille_config` table.

## 4. Seed the demo data

Load the 3 projects + 10 sleeping tickets:

```bash
docker compose exec -T db mysql -umantis -pmantis bugtracker < init.sql
```

## 5. See it work

Dashboard of sleeping tickets:
**Manage → Reveille → Reveille Dashboard**, or directly
<http://localhost:8080/plugin.php?page=Reveille/dashboard>

Run the cron in dry-run mode (produces output immediately against the seed
data):

```bash
docker compose exec app php /var/www/html/plugins/Reveille/scripts/reveille_cron.php --dry-run
```

Run it for real (reactivates due tickets and adds a Reveille bugnote):

```bash
docker compose exec app php /var/www/html/plugins/Reveille/scripts/reveille_cron.php
```

## Reset

```bash
docker compose down -v   # also drops the MySQL volume
```
