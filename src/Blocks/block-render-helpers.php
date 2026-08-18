<?php
/**
 * Bare (non-namespaced) helper functions shared by `blocks/*\/render.php`
 * template files.
 *
 * Render templates are included directly by
 * {@see \Growsfields\Blocks\BlockLoader::render_block()}, exactly the way
 * this project's real theme includes its own ACF `render.php` files — they
 * are plain PHP files, not classes, and are not autoloaded/namespaced. A
 * bare global function here matches that same convention (and matches how
 * the real theme's own `get_block_classes()` — see below — is itself a bare
 * global function, not a class method), rather than introducing a namespaced
 * static helper class that every render.php would need an extra `use`
 * statement for.
 *
 * @package Growsfields
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'gs_block_classes' ) ) {
	/**
	 * Computes the shared "block options" wrapper class string and inline
	 * background-color style used by several of this plugin's render.php
	 * templates (currently `body` and `cta`; every other block that resolves
	 * the "Block options" field group — see `field-groups/group_69380e4dc49ac.json`
	 * — can use it too).
	 *
	 * WHY THIS EXISTS (ACF-independence): the real theme this plugin migrates
	 * defines an equivalent helper,
	 * `starter-2026-iassine/includes/blocks-css-classes.php`'s
	 * `get_block_classes()`, but that function calls ACF's own `get_field()`
	 * internally for `spacing`/`bg_color`/`text_color`. This plugin has no ACF
	 * dependency at all — `get_field()` does not exist once ACF is
	 * deactivated/absent, so the theme's own helper would fatal (or, if ACF
	 * happens to still be active alongside this plugin, would simply read the
	 * wrong data source). This function is this plugin's own equivalent: it
	 * reads `spacing`/`bg_color`/`text_color` straight out of the block
	 * `$attributes` array {@see \Growsfields\Blocks\BlockLoader} already
	 * resolved via the fields engine, instead of calling `get_field()`.
	 *
	 * Ported from, and byte-faithful in its output shape to,
	 * `starter-2026-iassine/includes/blocks-css-classes.php`'s
	 * `get_block_classes()` (read-only ground truth — diff against that file
	 * directly if the theme's own version ever changes). This deliberately
	 * reproduces that original's own redundant behaviour: when `$spacing`
	 * is truthy, the `$classes` line ALWAYS appends a second
	 * `' with-{name}-{spacing}'` fragment on top of the `no-`/`with-` prefix
	 * fragment, even when `$spacing === 'none'` — so `spacing = 'none'`
	 * produces `' no-margin with-margin-none'`, not just `' no-margin'`. This
	 * is real, currently-live output the theme's CSS presumably already
	 * targets; it is not "fixed" here. Likewise `esc_attr()` is called on
	 * `$name_spacing`/`$spacing` here (inside this helper), exactly like the
	 * original, and the caller is still expected to wrap the returned
	 * `classes` string in `esc_attr()` again when emitting the class
	 * attribute — same double-escaping structure as the original, harmless
	 * but intentionally not "simplified" away.
	 *
	 * @param array<string, mixed> $attributes Block attributes, as resolved by
	 *                                          {@see \Growsfields\Blocks\BlockLoader::compute_attributes_for_block()}.
	 *                                          `spacing`/`bg_color`/`text_color`
	 *                                          are each read defensively via
	 *                                          `isset()` + type-cast (this
	 *                                          project's established style for
	 *                                          reading `$attributes` in a
	 *                                          render.php — see
	 *                                          `blocks/hero/render.php`) since
	 *                                          not every block resolves the
	 *                                          "Block options" group.
	 * @return array{classes: string, bg_color: string} `classes` is a
	 *                                                    space-prefixed
	 *                                                    (possibly empty)
	 *                                                    string of CSS class
	 *                                                    name fragments;
	 *                                                    `bg_color` is either
	 *                                                    `''` or a full
	 *                                                    `background-color: {value};`
	 *                                                    inline style
	 *                                                    declaration.
	 */
	function gs_block_classes( array $attributes ): array {
		$classes      = '';
		$bg_color     = '';
		$name_spacing = 'margin';

		$spacing        = isset( $attributes['spacing'] ) ? (string) $attributes['spacing'] : '';
		$bg_color_field = isset( $attributes['bg_color'] ) ? (string) $attributes['bg_color'] : '';
		$text_color     = isset( $attributes['text_color'] ) ? (string) $attributes['text_color'] : '';

		if ( $bg_color_field ) {
			$bg_color = 'background-color: ' . $bg_color_field . ';';
		}

		if ( 'light' === $text_color ) {
			$classes .= ' light-text';
		}

		if ( $spacing ) {
			if ( $bg_color_field ) {
				$name_spacing = 'padding';
			}
			$classes .= ( 'none' === $spacing ? ' no-' . esc_attr( $name_spacing ) : ' with-' . esc_attr( $name_spacing ) );
			$classes .= ( $spacing ? ' with-' . esc_attr( $name_spacing ) . '-' . esc_attr( $spacing ) : '' );
		}

		return array(
			'classes'  => $classes,
			'bg_color' => $bg_color,
		);
	}
}
