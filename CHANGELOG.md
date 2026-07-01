# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2026-07-01

### Fixed
- Tickets deferred **without** a due date are now left sleeping instead of being
  reactivated immediately. MantisBT stores an unset due date as the "null date"
  timestamp `1` (not `0`), which the activation check previously treated as a
  real 1970 date.

### Added
- Documented the `config/custom_strings_inc.php` labels (`deferred_bug_title`,
  `deferred_bug_button`) required so MantisBT's Change Status page has no
  `APPLICATION WARNING #300` for the custom `deferred` status. The Docker
  environment now mounts these labels.

### Changed
- Moved the plugin into a dedicated [`Reveille/`](Reveille) folder so it can be
  dropped straight into `<mantis>/plugins/`, cleanly separated from the Docker
  and CI tooling. The release now ships a plugin-only `Reveille/` zip.

## [1.0.0] - 2026-06-29

### Added
- Initial release.
- Per-project activation configuration (`interval` and `year` modes) stored in
  the `mantis_reveille_config` table, created via the plugin `schema()`
  migration.
- Admin configuration page (Manage → Reveille → Reveille Configuration) with
  per-project mode/interval inputs and a live count of sleeping tickets.
- Dashboard of sleeping tickets (Manage → Reveille → Reveille Dashboard) sorted
  by activation date with urgency colour-coding.
- CLI cron runner `scripts/reveille_cron.php` with `--dry-run` support that
  reactivates due tickets and adds a Reveille bugnote.
- English and German language files.
- Docker test environment (MantisBT 2.x + MySQL 8) with demo seed data.
- Installation guard that warns when the `deferred` status (15) is not
  registered.
