<?php
/**
 * Reveille – dashboard of sleeping tickets.
 *
 * Lists every ticket in the "deferred" status together with its due date, the
 * computed activation date and the number of days left until activation. Rows
 * are sorted by activation date and colour-coded by urgency.
 *
 * @package Reveille
 * @license MIT
 */

require_api( 'authentication_api.php' );
require_api( 'access_api.php' );
require_api( 'config_api.php' );
require_api( 'database_api.php' );
require_api( 'html_api.php' );
require_api( 'lang_api.php' );
require_api( 'print_api.php' );
require_api( 'string_api.php' );

auth_reauthenticate();
access_ensure_global_level( config_get( 'manage_plugin_threshold' ) );

$t_deferred = reveille_deferred_status();
$t_date_fmt = config_get( 'short_date_format' );

# Collect every sleeping ticket together with its project name.
$t_bug_table     = db_get_table( 'bug' );
$t_project_table = db_get_table( 'project' );

$t_query = 'SELECT b.id, b.project_id, b.summary, b.due_date, p.name AS project_name'
	. ' FROM ' . $t_bug_table . ' b'
	. ' JOIN ' . $t_project_table . ' p ON p.id = b.project_id'
	. ' WHERE b.status = ' . $t_deferred;
$t_result = db_query( $t_query );

$t_today = strtotime( 'today' );
$t_rows  = array();

while ( $t_bug = db_fetch_array( $t_result ) ) {
	$t_pid = (int)$t_bug['project_id'];
	$t_cfg = reveille_get_project_config( $t_pid );

	$t_activate_on = reveille_compute_activate_on(
		$t_bug['due_date'], $t_cfg['mode'], $t_cfg['interval_val'], $t_cfg['interval_unit']
	);

	$t_days_left = ( $t_activate_on === false )
		? null
		: (int)floor( ( $t_activate_on - $t_today ) / 86400 );

	$t_rows[] = array(
		'id'           => (int)$t_bug['id'],
		'project'      => $t_bug['project_name'],
		'summary'      => $t_bug['summary'],
		'due_date'     => (int)$t_bug['due_date'],
		'activate_on'  => $t_activate_on,
		'days_left'    => $t_days_left,
	);
}

# Sort by activation date ascending (rows without a due date go last).
usort( $t_rows, function ( $a, $b ) {
	$a_key = ( $a['activate_on'] === false ) ? PHP_INT_MAX : $a['activate_on'];
	$b_key = ( $b['activate_on'] === false ) ? PHP_INT_MAX : $b['activate_on'];
	return $a_key <=> $b_key;
} );

/**
 * Maps the days remaining to a row colour.
 *  green  > 90 days | yellow < 90 | orange < 30 | red < 7 (or already due).
 */
function reveille_row_color( $p_days_left ) {
	if ( $p_days_left === null ) {
		return '#f5f5f5'; // grey – no due date
	}
	if ( $p_days_left < 7 ) {
		return '#f8d7da'; // red
	}
	if ( $p_days_left < 30 ) {
		return '#ffe5cc'; // orange
	}
	if ( $p_days_left < 90 ) {
		return '#fff3cd'; // yellow
	}
	return '#d4edda'; // green
}

layout_page_header( plugin_lang_get( 'dashboard_title' ) );
layout_page_begin();
?>

<div class="col-md-12 col-xs-12">
<div class="space-10"></div>

<div class="widget-box widget-color-blue2">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-bed"></i>
			<?php echo plugin_lang_get( 'dashboard_title' ); ?>
			<span class="badge"><?php echo count( $t_rows ); ?></span>
		</h4>
	</div>

	<div class="widget-body">
	<div class="widget-main no-padding">
	<div class="table-responsive">
	<table class="table table-bordered table-condensed table-hover sortable-table">
		<thead>
			<tr>
				<th><?php echo plugin_lang_get( 'col_project' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_id' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_summary' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_due_date' ); ?></th>
				<th><?php echo plugin_lang_get( 'col_activate_on' ); ?></th>
				<th class="center"><?php echo plugin_lang_get( 'col_days_left' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $t_rows as $t_row ) {
			$t_color = reveille_row_color( $t_row['days_left'] );
		?>
			<tr style="background-color:<?php echo $t_color; ?>;">
				<td><?php echo string_display_line( $t_row['project'] ); ?></td>
				<td>
					<a href="<?php echo string_get_bug_view_url( $t_row['id'] ); ?>">
						<?php echo bug_format_id( $t_row['id'] ); ?>
					</a>
				</td>
				<td><?php echo string_display_line( $t_row['summary'] ); ?></td>
				<td><?php echo $t_row['due_date'] > 0 ? date( $t_date_fmt, $t_row['due_date'] ) : '&mdash;'; ?></td>
				<td><?php echo $t_row['activate_on'] !== false ? date( $t_date_fmt, $t_row['activate_on'] ) : '&mdash;'; ?></td>
				<td class="center">
					<?php echo $t_row['days_left'] === null ? '&mdash;' : (int)$t_row['days_left']; ?>
				</td>
			</tr>
		<?php } ?>
		<?php if ( empty( $t_rows ) ) { ?>
			<tr><td colspan="6" class="center"><?php echo plugin_lang_get( 'no_sleeping_tickets' ); ?></td></tr>
		<?php } ?>
		</tbody>
	</table>
	</div>
	</div>
	</div>
</div>
</div>

<?php
layout_page_end();
