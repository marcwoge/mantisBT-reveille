<?php
/**
 * MantisBT configuration for the Reveille Docker test environment.
 *
 * NOTE: These are throwaway demo credentials. Never reuse them in production.
 */

# --- Database -------------------------------------------------------------
$g_hostname      = 'db';
$g_db_type       = 'mysqli';
$g_database_name = 'bugtracker';
$g_db_username   = 'mantis';
$g_db_password   = 'mantis';

# --- General --------------------------------------------------------------
$g_default_timezone = 'Europe/Berlin';
# Demo salt only – generate a fresh one for any real installation.
$g_crypto_master_salt = 'REVEILLE_DOCKER_DEMO_SALT_CHANGE_ME_0123456789ABCDEF';

# Show the installer / upgrade page when needed.
$g_admin_checks = ON;

# --- Reveille: register the "deferred" status (15) ------------------------
# Reveille puts tickets to sleep in this status. Colour #e8eaf6 per the spec.
$g_status_enum_string = '10:new,20:feedback,30:acknowledged,40:confirmed,50:assigned,15:deferred,80:resolved,90:closed';
$g_status_colors['deferred'] = '#e8eaf6';

# Allow MantisBT to render the status label as "deferred".
$g_status_enum_workflow = array();
