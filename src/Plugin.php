<?php
/**
 * Main plugin bootstrap class.
 *
 * @package Growsfields
 */

namespace Growsfields;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 */
class Plugin {

	/**
	 * Returns the plugin version, used purely to confirm autoload works.
	 *
	 * @return string
	 */
	public function version(): string {
		return GS_VERSION;
	}

	/**
	 * Boots the plugin on "plugins_loaded". No external plugin dependency
	 * is required (the fields engine is built into this plugin).
	 *
	 * @return void
	 */
	public function boot(): void {
		// Intentionally empty for now. Populated starting Phase 2.
	}

	/**
	 * Runs on plugin activation. Keep this light: flags and a rewrite
	 * flush only. Heavy setup belongs in boot(), not here.
	 *
	 * @return void
	 */
	public function activate(): void {
		update_option( 'gs_version', GS_VERSION );
		update_option( 'gs_activated_at', time() );

		flush_rewrite_rules();
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		flush_rewrite_rules();
	}
}
