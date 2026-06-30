<?php
/**
 * Reveille – per-project configuration page.
 *
 * Reachable via Manage → Reveille → Configuration. Lists every project in the
 * system with its activation mode / interval and the number of currently
 * sleeping tickets.
 *
 * @package Reveille
 * @license MIT
 */

require_api( 'authentication_api.php' );
require_api( 'access_api.php' );
require_api( 'config_api.php' );
require_api( 'database_api.php' );
require_api( 'form_api.php' );
require_api( 'html_api.php' );
require_api( 'lang_api.php' );
require_api( 'print_api.php' );
require_api( 'project_api.php' );
require_api( 'string_api.php' );

auth_reauthenticate();
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

/* -------------------------------------------------------------------------- *
 *  Handle the save action.
 * -------------------------------------------------------------------------- */
$f_action = gpc_get_string( 'action', '' );

if ( $f_action === 'update' ) {
	form_security_validate( 'plugin_Reveille_config_update' );

	$t_table = reveille_config_table();

	# The form submits parallel arrays keyed by project id.
	$f_modes  = gpc_get_string_array( 'mode', array() );
	$f_vals   = gpc_get_string_array( 'interval_val', array() );
	$f_units  = gpc_get_string_array( 'interval_unit', array() );

	$t_valid_modes = array( 'year', 'interval' );
	$t_valid_units = array( 'days', 'weeks', 'months' );

	foreach ( $f_modes as $t_project_id => $t_mode ) {
		$t_project_id = (int)$t_project_id;
		if ( $t_project_id <= 0 ) {
			continue;
		}

		$t_mode = in_array( $t_mode, $t_valid_modes, true ) ? $t_mode : 'interval';
		$t_unit = isset( $f_units[$t_project_id] ) && in_array( $f_units[$t_project_id], $t_valid_units, true )
			? $f_units[$t_project_id]
			: 'days';
		$t_val  = isset( $f_vals[$t_project_id] ) ? (int)$f_vals[$t_project_id] : 90;
		if ( $t_val < 0 ) {
			$t_val = 0;
		}

		# Upsert: delete + insert keeps it portable across MySQL / PgSQL.
		db_query( 'DELETE FROM ' . $t_table . ' WHERE project_id = ' . $t_project_id );

		$t_query = 'INSERT INTO ' . $t_table
			. ' ( project_id, mode, interval_val, interval_unit ) VALUES ('
			. ' ' . db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param() . ' )';
		db_query( $t_query, array( $t_project_id, $t_mode, $t_val, $t_unit ) );
	}

	form_security_purge( 'plugin_Reveille_config_update' );
	print_successful_redirect( plugin_page( 'config', true ) );
	exit;
}

/* -------------------------------------------------------------------------- *
 *  Collect data for rendering.
 * -------------------------------------------------------------------------- */
$t_deferred = reveille_deferred_status();

# Sleeping ticket counts per project.
$t_bug_table = db_get_table( 'bug' );
$t_counts = array();
$t_result = db_query( 'SELECT project_id, COUNT(*) AS cnt FROM ' . $t_bug_table
	. ' WHERE status = ' . $t_deferred . ' GROUP BY project_id' );
while ( $t_row = db_fetch_array( $t_result ) ) {
	$t_counts[(int)$t_row['project_id']] = (int)$t_row['cnt'];
}

# All projects, ordered by name.
$t_project_table = db_get_table( 'project' );
$t_projects = array();
$t_result = db_query( 'SELECT id, name FROM ' . $t_project_table . ' ORDER BY name' );
while ( $t_row = db_fetch_array( $t_result ) ) {
	$t_projects[] = $t_row;
}

/* -------------------------------------------------------------------------- *
 *  Render.
 * -------------------------------------------------------------------------- */
layout_page_header( plugin_lang_get( 'config_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<?php if ( !reveille_deferred_status_is_registered() ) { ?>
	<div class="alert alert-warning">
		<strong><?php echo plugin_lang_get( 'plugin_title' ); ?>:</strong>
		<?php echo plugin_lang_get( 'status_warning' ); ?>
	</div>
<?php } ?>

<form action="<?php echo plugin_page( 'config' ); ?>" method="post">
<?php echo form_security_field( 'plugin_Reveille_config_update' ); ?>
<input type="hidden" name="action" value="update" />

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-moon-o"></i>
			<?php echo plugin_lang_get( 'config_title' ); ?>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-striped">
		<thead>
			<tr>
				<th><?php echo plugin_lang_get( 'col_project' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_mode' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_interval' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_unit' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'col_sleeping' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $t_projects as $t_project ) {
			$t_pid = (int)$t_project['id'];
			$t_cfg = reveille_get_project_config( $t_pid );
			$t_is_year = ( $t_cfg['mode'] === 'year' );
			$t_count = isset( $t_counts[$t_pid] ) ? $t_counts[$t_pid] : 0;
		?>
			<tr>
				<td><?php echo string_display_line( $t_project['name'] ); ?></td>
				<td>
					<select name="mode[<?php echo $t_pid; ?>]"
							class="input-sm reveille-mode"
							data-pid="<?php echo $t_pid; ?>">
						<option value="interval" <?php echo $t_cfg['mode'] === 'interval' ? 'selected="selected"' : ''; ?>>
							<?php echo plugin_lang_get( 'mode_interval' ); ?>
						</option>
						<option value="year" <?php echo $t_is_year ? 'selected="selected"' : ''; ?>>
							<?php echo plugin_lang_get( 'mode_year' ); ?>
						</option>
					</select>
				</td>
				<td>
					<input type="number" min="0"
						name="interval_val[<?php echo $t_pid; ?>]"
						value="<?php echo (int)$t_cfg['interval_val']; ?>"
						class="input-sm reveille-interval-val reveille-interval-<?php echo $t_pid; ?>"
						style="width:6em;<?php echo $t_is_year ? 'display:none;' : ''; ?>" />
				</td>
				<td>
					<select name="interval_unit[<?php echo $t_pid; ?>]"
						class="input-sm reveille-interval-unit reveille-interval-<?php echo $t_pid; ?>"
						style="<?php echo $t_is_year ? 'display:none;' : ''; ?>">
						<option value="days"   <?php echo $t_cfg['interval_unit'] === 'days'   ? 'selected="selected"' : ''; ?>><?php echo plugin_lang_get( 'unit_days' ); ?></option>
						<option value="weeks"  <?php echo $t_cfg['interval_unit'] === 'weeks'  ? 'selected="selected"' : ''; ?>><?php echo plugin_lang_get( 'unit_weeks' ); ?></option>
						<option value="months" <?php echo $t_cfg['interval_unit'] === 'months' ? 'selected="selected"' : ''; ?>><?php echo plugin_lang_get( 'unit_months' ); ?></option>
					</select>
				</td>
				<td class="center"><span class="badge"><?php echo $t_count; ?></span></td>
			</tr>
		<?php } ?>
		<?php if ( empty( $t_projects ) ) { ?>
			<tr><td colspan="5" class="center"><?php echo plugin_lang_get( 'no_projects' ); ?></td></tr>
		<?php } ?>
		</tbody>
	</table>
	</div>
	</div>

	<div class="widget-toolbox padding-8 clearfix">
		<input type="submit" class="btn btn-primary btn-white btn-round"
			value="<?php echo plugin_lang_get( 'btn_save' ); ?>" />
	</div>
	</div>
</div>
</form>
</div>

<script type="text/javascript">
/* Hide the interval inputs when the mode is "year". */
(function () {
	function toggle( select ) {
		var pid = select.getAttribute( 'data-pid' );
		var hide = ( select.value === 'year' );
		var fields = document.querySelectorAll( '.reveille-interval-' + pid );
		for ( var i = 0; i < fields.length; i++ ) {
			fields[i].style.display = hide ? 'none' : '';
		}
	}
	var selects = document.querySelectorAll( '.reveille-mode' );
	for ( var i = 0; i < selects.length; i++ ) {
		selects[i].addEventListener( 'change', function ( e ) { toggle( e.target ); } );
		toggle( selects[i] );
	}
})();
</script>

<?php
layout_page_end();
