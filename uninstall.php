<?php
/**
 * Fires when the plugin is deleted via the WP admin "Delete" action.
 *
 * This does NOT run on simple deactivation, only on uninstall. Keep this
 * file free of any class/function dependencies from src/, since it may
 * run in isolation.
 *
 * @package Growsfields
 */

// Exit if accessed directly (outside of the WordPress uninstall process).
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Phase 1 cleanup: only the options actually created so far.
 *
 * Later phases will need to extend this file as more gets added, e.g.
 * custom post types (Phase 5), field-group storage, and options-page
 * settings will all need their own cleanup here once they exist.
 *
 * Not handling multisite (get_sites()/switch_to_blog()) for now — single
 * site is enough at this stage.
 */
delete_option( 'gs_version' );
delete_option( 'gs_activated_at' );
