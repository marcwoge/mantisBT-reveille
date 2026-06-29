-- ---------------------------------------------------------------------------
-- Reveille – demo seed data.
--
-- Run this ONCE, AFTER the MantisBT web installer has created the schema and
-- the administrator account (see README_DOCKER.md). It creates:
--   * 3 projects  (Electrical, HVAC, IT Equipment)
--   * 1 category per project
--   * 10 tickets in status 15 (deferred) with a spread of due dates so the
--     cron dry-run has something to show immediately.
--
-- All due dates are relative to "now", so the demo never goes stale. With the
-- default configuration (interval / 90 days) any ticket due within the next
-- 90 days is reactivated on the next cron run.
-- ---------------------------------------------------------------------------

-- Clean previous demo rows (ids 1-3 / 1-10) so the seed is repeatable.
DELETE FROM mantis_bug_table        WHERE id BETWEEN 1 AND 10;
DELETE FROM mantis_bug_text_table   WHERE id BETWEEN 1 AND 10;
DELETE FROM mantis_category_table   WHERE id BETWEEN 1 AND 3;
DELETE FROM mantis_project_table    WHERE id BETWEEN 1 AND 3;

-- --- Projects --------------------------------------------------------------
INSERT INTO mantis_project_table
    (id, name, status, enabled, view_state, access_min, file_path, description, category_id, inherit_global)
VALUES
    (1, 'Electrical',   10, 1, 10, 10, '', 'Electrical operating equipment register',  1, 1),
    (2, 'HVAC',         10, 1, 10, 10, '', 'Heating, ventilation and air conditioning', 1, 1),
    (3, 'IT Equipment', 10, 1, 10, 10, '', 'IT hardware inventory',                     1, 1);

-- --- Categories (one per project) -----------------------------------------
INSERT INTO mantis_category_table (id, project_id, user_id, name, status)
VALUES
    (1, 1, 0, 'Inspection', 0),
    (2, 2, 0, 'Inspection', 0),
    (3, 3, 0, 'Inspection', 0);

-- --- Bug text rows ---------------------------------------------------------
INSERT INTO mantis_bug_text_table (id, description, steps_to_reproduce, additional_information)
VALUES
    (1,  'Recurring inspection of operating equipment.', '', ''),
    (2,  'Recurring inspection of operating equipment.', '', ''),
    (3,  'Recurring inspection of operating equipment.', '', ''),
    (4,  'Recurring inspection of operating equipment.', '', ''),
    (5,  'Recurring inspection of operating equipment.', '', ''),
    (6,  'Recurring inspection of operating equipment.', '', ''),
    (7,  'Recurring inspection of operating equipment.', '', ''),
    (8,  'Recurring inspection of operating equipment.', '', ''),
    (9,  'Recurring inspection of operating equipment.', '', ''),
    (10, 'Recurring inspection of operating equipment.', '', '');

-- --- Tickets (status 15 = deferred) ---------------------------------------
-- Columns spelled out so MySQL strict mode is happy.
-- due_date is a Unix timestamp; offsets are days from "now".
INSERT INTO mantis_bug_table
    (id, project_id, reporter_id, handler_id, duplicate_id, priority, severity,
     reproducibility, status, resolution, projection, category_id,
     date_submitted, last_updated, eta, bug_text_id, os, os_build, platform,
     version, fixed_in_version, build, profile_id, view_state, summary,
     sponsorship_total, sticky, target_version, due_date)
VALUES
    -- Electrical (project 1) -------------------------------------------------
    (1, 1, 1, 0, 0, 30, 50, 70, 15, 10, 10, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 10, 1,
        '', '', '', '', '', '', 0, 10, 'Thermography inspection switchboard A1',
        0, 0, '', UNIX_TIMESTAMP() + (10  * 86400)),
    (2, 1, 1, 0, 0, 30, 50, 70, 15, 10, 10, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 10, 2,
        '', '', '', '', '', '', 0, 10, 'RCD test portable tools, hall 2',
        0, 0, '', UNIX_TIMESTAMP() + (30  * 86400)),
    (3, 1, 1, 0, 0, 30, 50, 70, 15, 10, 10, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 10, 3,
        '', '', '', '', '', '', 0, 10, 'Insulation measurement main feeder',
        0, 0, '', UNIX_TIMESTAMP() + (85  * 86400)),
    (4, 1, 1, 0, 0, 30, 50, 70, 15, 10, 10, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 10, 4,
        '', '', '', '', '', '', 0, 10, 'Emergency lighting functional test',
        0, 0, '', UNIX_TIMESTAMP() + (110 * 86400)),
    -- HVAC (project 2) -------------------------------------------------------
    (5, 2, 1, 0, 0, 30, 50, 70, 15, 10, 10, 2, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 10, 5,
        '', '', '', '', '', '', 0, 10, 'Filter change air handling unit 3',
        0, 0, '', UNIX_TIMESTAMP() + (60  * 86400)),
    (6, 2, 1, 0, 0, 30, 50, 70, 15, 10, 10, 2, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 10, 6,
        '', '', '', '', '', '', 0, 10, 'Refrigerant leak test chiller B',
        0, 0, '', UNIX_TIMESTAMP() + (150 * 86400)),
    (7, 2, 1, 0, 0, 30, 50, 70, 15, 10, 10, 2, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 10, 7,
        '', '', '', '', '', '', 0, 10, 'Fire damper inspection riser north',
        0, 0, '', UNIX_TIMESTAMP() + (200 * 86400)),
    -- IT Equipment (project 3) ----------------------------------------------
    (8, 3, 1, 0, 0, 30, 50, 70, 15, 10, 10, 3, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 10, 8,
        '', '', '', '', '', '', 0, 10, 'UPS battery capacity test rack 12',
        0, 0, '', UNIX_TIMESTAMP() + (89  * 86400)),
    (9, 3, 1, 0, 0, 30, 50, 70, 15, 10, 10, 3, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 10, 9,
        '', '', '', '', '', '', 0, 10, 'PAT test laptops finance department',
        0, 0, '', UNIX_TIMESTAMP() + (300 * 86400)),
    (10, 3, 1, 0, 0, 30, 50, 70, 15, 10, 10, 3, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 10, 10,
        '', '', '', '', '', '', 0, 10, 'Server room earth bonding check',
        0, 0, '', UNIX_TIMESTAMP() + (400 * 86400));
