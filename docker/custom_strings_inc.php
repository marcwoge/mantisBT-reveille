<?php
/**
 * MantisBT custom language strings for the Reveille "deferred" status.
 *
 * MantisBT builds the "Change Status" page (bug_change_status_page.php) using
 * per-status language strings <status>_bug_title and <status>_bug_button. These
 * exist for the built-in statuses but must be supplied for the custom
 * "deferred" status, otherwise MantisBT emits:
 *   APPLICATION WARNING #300: String "deferred_bug_title" not found.
 *
 * This file is loaded by MantisBT's core (lang_api) after each language, with
 * $g_active_language set to the current language – so the strings can be
 * localised.
 */

switch( $g_active_language ) {
	case 'german':
		$s_deferred_bug_title  = 'Ticket zurückstellen';
		$s_deferred_bug_button = 'Ticket zurückstellen';
		break;
	default:
		$s_deferred_bug_title  = 'Defer this ticket';
		$s_deferred_bug_button = 'Defer ticket';
}
