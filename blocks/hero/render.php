<?php
/**
 * Server-side render template for the `growsfields/hero` block.
 *
 * @package Growsfields
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Included by {@see \Growsfields\Blocks\BlockLoader::render_block()} with
 * `$attributes` already in scope as a local variable — mirroring ACF's own
 * `renderTemplate` convention this project's real theme already uses for its
 * own blocks (see `starter-2026-iassine/blocks/hero/render.php`, the
 * read-only ACF-based ground truth this file is migrated from). Do not
 * redeclare `$attributes` here; a missing key is guarded with `isset()`
 * below purely for defensive robustness (e.g. this file being included
 * directly by a future test/tool without going through `render_block()`).
 *
 * Field-by-field mapping from the real theme's ACF template:
 * - `get_field('image')`  -> `$attributes['image']`  (int attachment ID —
 *   NOT a URL; see note below, this is the one behavioural difference from
 *   the original ACF field).
 * - `get_field('title')`  -> `$attributes['title']`  (string).
 * - `get_field('text')`   -> `$attributes['text']`   (string, plain text —
 *   see note below on the `wp_kses_post()` vs `esc_html()` decision).
 * - `get_field('button')` -> `$attributes['button']` (array
 *   `{url, title, target}`).
 *
 * `get_field('align_image')` is DELIBERATELY NOT reproduced. It was
 * confirmed dead in the real, currently-live theme (the field no longer
 * exists anywhere in `acf-json/`, so `get_field('align_image')` there always
 * returns `false`), meaning the original template's `--valign` inline style
 * always fell back to its hardcoded `50%` default in production. This
 * template reproduces that observed real-world behaviour directly by
 * hardcoding `--valign: 50%` rather than inventing a new `align_image`
 * attribute/field for a control that has never actually been wired up to
 * anything a site editor could set.
 *
 * @var array{image?: int, title?: string, text?: string, button?: array{url?: string, title?: string, target?: string}} $attributes
 */

$image_id = isset( $attributes['image'] ) ? (int) $attributes['image'] : 0;
$title    = isset( $attributes['title'] ) ? (string) $attributes['title'] : '';
$text     = isset( $attributes['text'] ) ? (string) $attributes['text'] : '';
$button   = isset( $attributes['button'] ) && is_array( $attributes['button'] ) ? $attributes['button'] : array();

// Same visibility guard as the original: only render when both an image and
// a title are set. `empty( 0 )` is true, so a never-set/removed image (which
// this plugin's Image::default_value()/sanitize() both represent as the int
// `0`, never null/false) is correctly treated as "no image" here, matching
// the original's `!empty( get_field( 'image' ) )` for its own empty state
// (an empty string from ACF's `return_format: "url"`).
if ( empty( $image_id ) || '' === $title ) {
	return;
}

// This plugin's own Image field type ALWAYS stores/returns the attachment
// ID (see Types\Image's class docblock: "The stored value is always the
// attachment ID regardless of return_format") — unlike the original ACF
// field, which used `return_format: "url"` and so `get_field('image')`
// returned a URL string directly. `wp_get_attachment_image_url()` resolves
// that ID to an actual URL for the <img src>; 'full' matches the original's
// effective behaviour (ACF's own `url` return format returns the full-size
// attachment URL — `preview_size` in the original field config was only an
// *admin editor* preview hint, it never affected the returned/rendered
// value).
$image_url = wp_get_attachment_image_url( $image_id, 'full' );

// A stale/deleted attachment ID (sanitized to a non-zero int at save time,
// but since deleted from the media library) would make wp_get_attachment_image_url()
// return false here even though $image_id itself passed the guard above —
// treat that the same as "no image" rather than rendering a broken <img src="">.
if ( ! $image_url ) {
	return;
}

$button_url = isset( $button['url'] ) ? (string) $button['url'] : '';
?>
<section class="hero with-shape">
	<div class="hero-image cover-image" style="--valign: 50%;">
		<img src="<?php echo esc_url( $image_url ); ?>" <?php echo function_exists( 'theme_image_attrs' ) ? theme_image_attrs( $image_id, 'hero', 'hero-image' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme_image_attrs() itself esc_attr()s every attribute value it builds; see includes/image-optim.php. ?> />
	</div>
	<div class="wrapper">
		<div class="hero-text">
			<h1><?php echo esc_html( strtoupper( $title ) ); ?></h1>
			<?php if ( '' !== $text ) : ?>
				<?php
				/*
				 * The original theme template ran this through
				 * wp_kses_post(). Hero's real field (field_68e629058b9ad /
				 * "Text") is a `textarea` field, and this plugin's own
				 * Textarea::sanitize() runs sanitize_textarea_field() at
				 * save time, which STRIPS all HTML tags — so by the time
				 * this template runs, $text is guaranteed already-plain
				 * text (with line breaks only, no markup ever survives to
				 * be stored). wp_kses_post() on guaranteed-plain-text input
				 * would be harmless but is not quite correct: KSES's job is
				 * to allow a safelist of HTML through, not to HTML-entity-
				 * escape plain text, so a literal "&"/"<"/">" a user typed
				 * could pass through un-entity-encoded and produce
				 * technically-invalid HTML. esc_html() is the semantically
				 * correct call now that this field can never legitimately
				 * carry markup — it entity-escapes exactly the characters
				 * that need it and nothing more.
				 */
				?>
				<div class="text"><?php echo esc_html( $text ); ?></div>
			<?php endif; ?>
			<?php if ( '' !== $button_url ) : ?>
				<div><a class="btn" href="<?php echo esc_url( $button_url ); ?>"><?php echo esc_html( isset( $button['title'] ) ? (string) $button['title'] : '' ); ?></a></div>
			<?php endif; ?>
		</div>
	</div>
</section>
<?php
// Note: the original template never adds target="_blank" to this <a> even
// though ACF's link array carries a `target` key — that omission is
// reproduced faithfully here (not a bug for this migration to fix), see the
// task brief's own note on this.
