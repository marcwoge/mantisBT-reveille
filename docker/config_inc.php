<?php
/**
 * MantisBT configuration for the Reveille Docker test environment.
 *
 * NOTE: Local test setup only. Secrets come from docker/.env via docker-compose.yml.
 */

# --- Database -------------------------------------------------------------
$g_hostname      = 'db';
$g_db_type       = 'mysqli';
$g_database_name = 'bugtracker';
$g_db_username   = 'mantis';
$g_db_password   = (string) getenv('MANTIS_DB_PASSWORD');

# --- General --------------------------------------------------------------
$g_default_timezone = 'Europe/Berlin';
# Set MANTIS_CRYPTO_MASTER_SALT in docker/.env (>= 16 characters).
$g_crypto_master_salt = (string) getenv('MANTIS_CRYPTO_MASTER_SALT');

# Show the installer / upgrade page when needed.
$g_admin_checks = ON;

# --- Reveille: register the "deferred" status (15) ------------------------
# Reveille puts tickets to sleep in this status. Colour #e8eaf6 per the spec.
$g_status_enum_string = '10:new,20:feedback,30:acknowledged,40:confirmed,50:assigned,15:deferred,80:resolved,90:closed';
$g_status_colors['deferred'] = '#e8eaf6';

# Allow MantisBT to render the status label as "deferred".
$g_status_enum_workflow = array();
