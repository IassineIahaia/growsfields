<?php
/**
 * Main plugin bootstrap class.
 *
 * @package Growsfields
 */

namespace Growsfields;

use Growsfields\Blocks\BlockLoader;
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
	 * Registers every native block under `blocks/*\/block.json` and dispatches
	 * their rendering. Instantiated in {@see self::boot()}; its own
	 * block-registration method is hooked to `init` there rather than called
	 * directly, since `register_block_type()` must run on `init` (see
	 * `BlockLoader`'s own class docblock, "HOOK: init").
	 *
	 * @var BlockLoader
	 */
	private BlockLoader $block_loader;

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
		$this->block_loader = new BlockLoader();

		add_filter( 'block_categories_all', array( $this, 'register_block_category' ) );
		add_action( 'init', array( $this->block_loader, 'register_blocks' ) );
	}

	/**
	 * Prepends the plugin's own block category ("Growsfields") to the
	 * block editor's category list, so the native blocks registered under
	 * `blocks/` (Phase 4) have somewhere to group themselves instead of
	 * falling back to a generic core category.
	 *
	 * Uses `block_categories_all`, the current filter name — its
	 * predecessor `block_categories` (no `$editor_context` parameter) was
	 * deprecated in WP 5.8 in favour of this one. The theme's own ACF
	 * blocks that this plugin's blocks are modelled on never registered a
	 * category of their own (they used core's built-in "formatting"/"text"
	 * categories), so there is no prior title/icon to carry over here.
	 *
	 * @param array<int, array{slug: string, title: string, icon: string|null}> $categories Existing block categories.
	 * @return array<int, array{slug: string, title: string, icon: string|null}> Categories with "growsfields" prepended.
	 */
	public function register_block_category( array $categories ): array {
		return array_merge(
			array(
				array(
					'slug'  => 'growsfields',
					'title' => __( 'Growsfields', 'growsfields' ),
					'icon'  => null,
				),
			),
			$categories
		);
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
