<?php
/**
 * Reveille – Schedule any ticket to sleep, wake it up exactly when it matters.
 *
 * MantisBT plugin main class. Holds the manifest, the schema migration, the
 * configuration defaults and the management-menu hooks.
 *
 * @package   Reveille
 * @author    Marc-Philipp Woge
 * @license   MIT
 * @link      https://github.com/marcwoge/reveille
 *
 * MantisBT only ever loads a single class file per plugin and expects it to be
 * named after the plugin directory ("Reveille.php") and to contain the class
 * "<PluginName>Plugin" ("ReveillePlugin"). For that reason the register() logic
 * and the event handlers that the task brief originally sketched into a second
 * "ReverillePlugin.php" file live here as well – a second file would never be
 * picked up by the MantisBT plugin loader and would be dead code.
 */

# Refuse to run outside of the MantisBT context.
if ( !defined( 'MANTIS_VERSION' ) ) {
	die( 'Reveille is a MantisBT plugin and cannot be called directly.' );
}

/* -------------------------------------------------------------------------- *
 *  Shared helper functions (file scope).
 *
 *  These are defined at the top level of the main plugin file so that every
 *  plugin page (config.php, dashboard.php) can reuse them – MantisBT requires
 *  this file on every web request, which makes the functions globally
 *  available. The CLI cron runner does NOT load this file and therefore ships
 *  its own self-contained copies.
 * -------------------------------------------------------------------------- */

if ( !function_exists( 'reveille_config_table' ) ) {
	/**
	 * Fully qualified name of the Reveille configuration table.
	 * Honours the configured database table prefix so the plugin keeps working
	 * on installations that customise it. With the MantisBT default prefix
	 * ("mantis") this resolves to "mantis_reveille_config".
	 *
	 * @return string
	 */
	function reveille_config_table() {
		return config_get_global( 'db_table_prefix' ) . '_reveille_config';
	}
}

if ( !function_exists( 'reveille_deferred_status' ) ) {
	/**
	 * Status code that marks a "sleeping" ticket. Configurable, default 15.
	 * @return int
	 */
	function reveille_deferred_status() {
		return (int)plugin_config_get( 'deferred_status', 15 );
	}
}

if ( !function_exists( 'reveille_active_status' ) ) {
	/**
	 * Status code a ticket is set to when it is woken up. Default 10 (new).
	 * @return int
	 */
	function reveille_active_status() {
		return (int)plugin_config_get( 'active_status', 10 );
	}
}

if ( !function_exists( 'reveille_deferred_status_is_registered' ) ) {
	/**
	 * Whether the deferred status code is present in the status enum string.
	 * File-scope helper so plugin pages can check it without instantiating the
	 * plugin class (whose constructor requires a basename argument).
	 * @return bool
	 */
	function reveille_deferred_status_is_registered() {
		$t_status = reveille_deferred_status();
		$t_enum   = config_get( 'status_enum_string' );
		return (bool)preg_match( '/(^|,)\s*' . $t_status . '\s*:/', $t_enum );
	}
}

if ( !function_exists( 'reveille_get_project_config' ) ) {
	/**
	 * Returns the Reveille configuration for a project, falling back to sensible
	 * defaults (interval / 90 / days) when no row exists yet.
	 *
	 * @param int $p_project_id
	 * @return array{mode:string,interval_val:int,interval_unit:string}
	 */
	function reveille_get_project_config( $p_project_id ) {
		$t_default = array(
			'mode'          => 'interval',
			'interval_val'  => 90,
			'interval_unit' => 'days',
		);

		$t_table = reveille_config_table();
		$t_query = 'SELECT mode, interval_val, interval_unit FROM ' . $t_table
			. ' WHERE project_id = ' . (int)$p_project_id;
		$t_result = db_query( $t_query );
		$t_row = db_fetch_array( $t_result );

		if ( !$t_row ) {
			return $t_default;
		}

		return array(
			'mode'          => $t_row['mode'],
			'interval_val'  => (int)$t_row['interval_val'],
			'interval_unit' => $t_row['interval_unit'],
		);
	}
}

if ( !function_exists( 'reveille_compute_activate_on' ) ) {
	/**
	 * Computes the activation timestamp for a ticket.
	 *
	 *  - mode "year":     1 January 00:00 of the due date's year.
	 *  - mode "interval": <interval_val> <interval_unit> before the due date.
	 *
	 * @param int    $p_due_date_ts  Unix timestamp of the due date (<= 1 = unset;
	 *                               MantisBT stores unset dates as the null date 1).
	 * @param string $p_mode         "year" | "interval"
	 * @param int    $p_interval_val
	 * @param string $p_interval_unit "days" | "weeks" | "months"
	 * @return int|false Activation timestamp, or false when no due date is set.
	 */
	function reveille_compute_activate_on( $p_due_date_ts, $p_mode, $p_interval_val, $p_interval_unit ) {
		$p_due_date_ts = (int)$p_due_date_ts;
		# MantisBT's "null date" (no due date set) is the timestamp 1, not 0.
		if ( $p_due_date_ts <= 1 ) {
			return false;
		}

		if ( $p_mode === 'year' ) {
			$t_year = (int)date( 'Y', $p_due_date_ts );
			return mktime( 0, 0, 0, 1, 1, $t_year );
		}

		# interval mode
		$t_units = array( 'days' => 'days', 'weeks' => 'weeks', 'months' => 'months' );
		$t_unit  = isset( $t_units[$p_interval_unit] ) ? $t_units[$p_interval_unit] : 'days';
		$t_val   = (int)$p_interval_val;

		return strtotime( '-' . $t_val . ' ' . $t_unit, $p_due_date_ts );
	}
}

/* -------------------------------------------------------------------------- *
 *  Plugin class
 * -------------------------------------------------------------------------- */

require_once( config_get_global( 'class_path' ) . 'MantisPlugin.class.php' );

/**
 * @noinspection PhpUnused – instantiated by the MantisBT plugin loader.
 */
class ReveillePlugin extends MantisPlugin {

	/**
	 * Plugin manifest.
	 * @return void
	 */
	function register() {
		# Literal strings: the plugin language is not yet loaded during
		# registration, so plugin_lang_get() would return the raw key here.
		$this->name        = 'Reveille';
		$this->description  = 'Schedule any ticket to sleep – wake it up exactly when it matters.';
		$this->page         = 'config';

		$this->version  = '1.0.0';
		$this->requires = array(
			'MantisCore' => '2.0.0',
		);

		$this->author  = 'Marc-Philipp Woge';
		$this->contact = 'marc.woge@googlemail.com';
		$this->url     = 'https://github.com/marcwoge/reveille';
	}

	/**
	 * Default configuration values.
	 * @return array
	 */
	function config() {
		return array(
			# Status that marks a ticket as "sleeping" (deferred). See README for
			# the matching config_inc.php snippet that registers status 15.
			'deferred_status' => 15,
			# Status a ticket is reactivated to (10 = new / open).
			'active_status'   => 10,
		);
	}

	/**
	 * Event hooks.
	 * @return array
	 */
	function hooks() {
		return array(
			'EVENT_MENU_MANAGE' => 'menu_manage',
		);
	}

	/**
	 * Adds the Reveille entries to the "Manage" menu.
	 * @return array
	 */
	function menu_manage() {
		return array(
			'<a href="' . plugin_page( 'config' ) . '">'
				. plugin_lang_get( 'menu_config' ) . '</a>',
			'<a href="' . plugin_page( 'dashboard' ) . '">'
				. plugin_lang_get( 'menu_dashboard' ) . '</a>',
		);
	}

	/**
	 * Schema migration. Creates the per-project configuration table.
	 *
	 * The table name honours the configured DB prefix (default
	 * "mantis_reveille_config"). ENUM columns from the brief are represented as
	 * short VARCHARs here to stay portable across the databases MantisBT
	 * supports; the allowed values are enforced at the application level.
	 *
	 * @return array
	 */
	function schema() {
		return array(
			# 0
			array( 'CreateTableSQL', array( reveille_config_table(), "
				project_id      I       NOTNULL PRIMARY,
				mode            C(10)   NOTNULL DEFAULT \" 'interval' \",
				interval_val    I       NOTNULL DEFAULT '90',
				interval_unit   C(10)   NOTNULL DEFAULT \" 'days' \"
				" ) ),
		);
	}

	/**
	 * Installation guard. Warns – but does not block – when the "deferred"
	 * status (15) has not been registered in config_inc.php yet, because that
	 * registration cannot be performed from inside a plugin.
	 *
	 * @return bool Always true; the status can be added after installation.
	 */
	function install() {
		if ( !$this->deferred_status_is_registered() ) {
			# A non-fatal, highly visible warning. The plugin still installs.
			echo '<div class="alert alert-warning" style="margin:1em 0;padding:1em;'
				. 'border:2px solid #e0a800;background:#fff3cd;color:#664d03;">'
				. '<strong>Reveille:</strong> '
				. plugin_lang_get( 'status_warning' )
				. '</div>';
		}
		return true;
	}

	/**
	 * Whether the deferred status code is present in the status enum.
	 * @return bool
	 */
	function deferred_status_is_registered() {
		return reveille_deferred_status_is_registered();
	}
}
