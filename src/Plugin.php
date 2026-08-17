<?php
/**
 * Main plugin bootstrap class.
 *
 * @package Growsfields
 */

namespace Growsfields;

use Growsfields\Fields\FieldTypeRegistry;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 */
class Plugin {

	/**
	 * Registry of available field types. Instantiated in {@see self::boot()};
	 * concrete field type registrations happen elsewhere (built-in types in
	 * `FieldTypeRegistry::register_builtin_types()`, third-party/late
	 * additions via the `gs_register_field_type` filter) — this class only
	 * needs to make the registry reachable.
	 *
	 * @var FieldTypeRegistry
	 */
	private FieldTypeRegistry $field_types;

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
		$this->field_types = new FieldTypeRegistry();
	}

	/**
	 * The field type registry, e.g. for `FieldGroupLoader` (Phase 3) or
	 * the Phase 6-B builder UI to look up/instantiate field types.
	 *
	 * @return FieldTypeRegistry
	 */
	public function field_types(): FieldTypeRegistry {
		return $this->field_types;
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
