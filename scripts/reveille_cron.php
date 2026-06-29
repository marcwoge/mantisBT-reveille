<?php
/**
 * Reveille – CLI cron runner.
 *
 * Walks every ticket in the "deferred" status across all projects, computes its
 * activation date from the project configuration and reactivates the ones whose
 * activation threshold has been reached.
 *
 * Usage:
 *   php plugins/Reveille/scripts/reveille_cron.php [--dry-run]
 *
 *   --dry-run   Show what would happen without changing anything.
 *
 * Recommended crontab entry:
 *   0 6 * * * php /path/to/mantis/plugins/Reveille/scripts/reveille_cron.php
 *
 * @package Reveille
 * @license MIT
 */

# ---------------------------------------------------------------------------
# CLI guard – refuse to run from a web request.
# ---------------------------------------------------------------------------
if ( php_sapi_name() !== 'cli' ) {
	http_response_code( 403 );
	die( "reveille_cron.php must be run from the command line.\n" );
}

# ---------------------------------------------------------------------------
# Argument parsing.
# ---------------------------------------------------------------------------
$g_dry_run = false;
foreach ( $argv as $t_arg ) {
	if ( $t_arg === '--dry-run' ) {
		$g_dry_run = true;
	} elseif ( $t_arg === '--help' || $t_arg === '-h' ) {
		echo "Reveille cron runner\n";
		echo "Usage: php reveille_cron.php [--dry-run]\n";
		echo "  --dry-run   Show what would happen without changing anything.\n";
		exit( 0 );
	}
}

# ---------------------------------------------------------------------------
# Bootstrap MantisBT core. The script lives in
#   <mantis>/plugins/Reveille/scripts/reveille_cron.php
# so the MantisBT root is three directories up. We talk to the database via the
# core APIs directly and do not depend on the plugin layer being loaded.
# ---------------------------------------------------------------------------
require_once( dirname( __DIR__, 3 ) . DIRECTORY_SEPARATOR . 'core.php' );

# ---------------------------------------------------------------------------
# Self-contained helpers (the main plugin file is not loaded in CLI context).
# ---------------------------------------------------------------------------

/**
 * Logs a message to stdout with an ISO timestamp.
 * @param string $p_message
 * @return void
 */
function reveille_log( $p_message ) {
	echo '[' . date( 'Y-m-d H:i:s' ) . '] ' . $p_message . "\n";
}

/**
 * Configuration table name, honouring the configured DB prefix.
 * @return string
 */
function reveille_cron_config_table() {
	return config_get_global( 'db_table_prefix' ) . '_reveille_config';
}

/**
 * Per-project configuration with default fallback (interval / 90 / days).
 * @param int $p_project_id
 * @return array
 */
function reveille_cron_get_project_config( $p_project_id ) {
	static $s_cache = array();
	$p_project_id = (int)$p_project_id;

	if ( isset( $s_cache[$p_project_id] ) ) {
		return $s_cache[$p_project_id];
	}

	$t_default = array( 'mode' => 'interval', 'interval_val' => 90, 'interval_unit' => 'days' );

	$t_query  = 'SELECT mode, interval_val, interval_unit FROM '
		. reveille_cron_config_table() . ' WHERE project_id = ' . $p_project_id;
	$t_result = db_query( $t_query );
	$t_row    = db_fetch_array( $t_result );

	$t_cfg = $t_row ? array(
		'mode'          => $t_row['mode'],
		'interval_val'  => (int)$t_row['interval_val'],
		'interval_unit' => $t_row['interval_unit'],
	) : $t_default;

	$s_cache[$p_project_id] = $t_cfg;
	return $t_cfg;
}

/**
 * Activation timestamp for a ticket, or false when no due date is set.
 * @param int    $p_due_date_ts
 * @param string $p_mode
 * @param int    $p_interval_val
 * @param string $p_interval_unit
 * @return int|false
 */
function reveille_cron_compute_activate_on( $p_due_date_ts, $p_mode, $p_interval_val, $p_interval_unit ) {
	$p_due_date_ts = (int)$p_due_date_ts;
	if ( $p_due_date_ts <= 0 ) {
		return false;
	}
	if ( $p_mode === 'year' ) {
		return mktime( 0, 0, 0, 1, 1, (int)date( 'Y', $p_due_date_ts ) );
	}
	$t_units = array( 'days' => 'days', 'weeks' => 'weeks', 'months' => 'months' );
	$t_unit  = isset( $t_units[$p_interval_unit] ) ? $t_units[$p_interval_unit] : 'days';
	return strtotime( '-' . (int)$p_interval_val . ' ' . $t_unit, $p_due_date_ts );
}

# ---------------------------------------------------------------------------
# Configuration constants. These mirror the plugin's config() defaults; they
# are read directly because the plugin config layer is bypassed in CLI mode.
# ---------------------------------------------------------------------------
$g_deferred_status = (int)config_get( 'plugin_Reveille_deferred_status', 15 );
$g_active_status   = (int)config_get( 'plugin_Reveille_active_status', 10 );

# ---------------------------------------------------------------------------
# Main run.
# ---------------------------------------------------------------------------
reveille_log( 'Reveille cron started' . ( $g_dry_run ? ' (DRY-RUN)' : '' ) . '.' );
reveille_log( 'Looking for tickets in status ' . $g_deferred_status . ' (deferred).' );

$t_bug_table = db_get_table( 'bug' );
$t_query = 'SELECT id, project_id, summary, due_date FROM ' . $t_bug_table
	. ' WHERE status = ' . $g_deferred_status . ' ORDER BY project_id, id';
$t_result = db_query( $t_query );

$t_today        = strtotime( 'today' );
$t_total        = 0;
$t_reactivated  = 0;
$t_skipped      = 0;

if ( !$g_dry_run ) {
	# A logged-in user is required so bugnotes and history are attributed
	# correctly. auth_attempt_script_login() with no password is the trusted
	# script-login mechanism shipped with MantisBT.
	$t_script_user = getenv( 'REVEILLE_USER' );
	if ( $t_script_user === false || $t_script_user === '' ) {
		$t_script_user = 'administrator';
	}
	if ( !auth_attempt_script_login( $t_script_user ) ) {
		reveille_log( 'ERROR: could not log in as "' . $t_script_user
			. '". Set REVEILLE_USER to a valid administrator account.' );
		exit( 1 );
	}
}

while ( $t_bug = db_fetch_array( $t_result ) ) {
	$t_total++;
	$t_bug_id   = (int)$t_bug['id'];
	$t_project  = (int)$t_bug['project_id'];
	$t_due_date = (int)$t_bug['due_date'];

	$t_cfg = reveille_cron_get_project_config( $t_project );
	$t_activate_on = reveille_cron_compute_activate_on(
		$t_due_date, $t_cfg['mode'], $t_cfg['interval_val'], $t_cfg['interval_unit']
	);

	if ( $t_activate_on === false ) {
		reveille_log( sprintf(
			'  #%d (project %d): SKIP – no due date set.',
			$t_bug_id, $t_project
		) );
		$t_skipped++;
		continue;
	}

	$t_due_str  = date( 'Y-m-d', $t_due_date );
	$t_act_str  = date( 'Y-m-d', $t_activate_on );

	if ( $t_today >= $t_activate_on ) {
		if ( $g_dry_run ) {
			reveille_log( sprintf(
				'  #%d (project %d): WOULD reactivate (due %s, activate_on %s).',
				$t_bug_id, $t_project, $t_due_str, $t_act_str
			) );
		} else {
			bug_set_field( $t_bug_id, 'status', $g_active_status );
			$t_note = '[Reveille] Ticket automatically reactivated. Due date: ' . $t_due_str;
			bugnote_add(
				$t_bug_id,
				$t_note,
				'0:00',
				/* private */ true
			);
			reveille_log( sprintf(
				'  #%d (project %d): reactivated -> status %d (due %s, activate_on %s).',
				$t_bug_id, $t_project, $g_active_status, $t_due_str, $t_act_str
			) );
		}
		$t_reactivated++;
	} else {
		$t_days = (int)floor( ( $t_activate_on - $t_today ) / 86400 );
		reveille_log( sprintf(
			'  #%d (project %d): still sleeping (activate_on %s, %d day(s) left).',
			$t_bug_id, $t_project, $t_act_str, $t_days
		) );
	}
}

reveille_log( sprintf(
	'Done. %d deferred ticket(s) examined, %d %s, %d skipped.',
	$t_total,
	$t_reactivated,
	$g_dry_run ? 'would be reactivated' : 'reactivated',
	$t_skipped
) );

exit( 0 );
