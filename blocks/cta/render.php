<?php
/**
 * Server-side render template for the `growsfields/cta` block.
 *
 * @package Growsfields
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Included by {@see \Growsfields\Blocks\BlockLoader::render_block()} with
 * `$attributes` already in scope as a local variable — see
 * `blocks/hero/render.php` for the full explanation of this convention. Do
 * not redeclare `$attributes` here; a missing key is guarded with `isset()`
 * below purely for defensive robustness.
 *
 * Field-by-field mapping from the real theme's ACF template
 * (`starter-2026-iassine/blocks/cta/render.php`, read-only ground truth):
 * - `get_field('title')`  -> `$attributes['title']`  (string).
 * - `get_field('text')`   -> `$attributes['text']`   (string, WYSIWYG,
 *   already `wp_kses_post()`-sanitized HTML at save time — see
 *   `Growsfields\Fields\Types\Wysiwyg::sanitize()` — same trust level the
 *   original `<?= ($text ? wp_kses_post($text) : null); ?>` already assumed
 *   for its `wp_kses_post()` call; re-running it here defensively, exactly
 *   like the original does, since a second pass over already-sanitized HTML
 *   is harmless and this is the one field the maintainer's brief explicitly
 *   asked to keep defensively re-sanitized).
 * - `get_field('button')`  -> `$attributes['button']`  (array
 *   `{url, title, target}`).
 * - `get_field('image')`   -> `$attributes['image']`   (int attachment ID —
 *   NOT a URL; same behavioural difference from the original ACF field as
 *   already noted/handled in `blocks/hero/render.php`, since this plugin's
 *   own Image field type always stores/returns the attachment ID regardless
 *   of `return_format`).
 * - `get_block_classes()`  -> `gs_block_classes( $attributes )` — see
 *   `body/render.php`'s own docblock note on this, identical reasoning here.
 *
 * @var array{title?: string, text?: string, button?: array{url?: string, title?: string, target?: string}, image?: int, spacing?: string, bg_color?: string, text_color?: string} $attributes
 */

$title    = isset( $attributes['title'] ) ? (string) $attributes['title'] : '';
$text     = isset( $attributes['text'] ) ? (string) $attributes['text'] : '';
$button   = isset( $attributes['button'] ) && is_array( $attributes['button'] ) ? $attributes['button'] : array();
$image_id = isset( $attributes['image'] ) ? (int) $attributes['image'] : 0;

// This plugin's own Image field type always stores/returns the attachment
// ID (see Types\Image's class docblock), unlike the original ACF field
// (`return_format: "url"`), so it is resolved to a URL here, the same way
// blocks/hero/render.php already does for its own `image` attribute. A
// stale/deleted attachment ID (sanitized to a non-zero int at save time, but
// since deleted from the media library) makes wp_get_attachment_image_url()
// return false — normalized to '' here, matching the original ACF field's
// own empty-string return for "no image".
$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : false;
$image_url = $image_url ? (string) $image_url : '';

// Same visibility guard as the original: render when either a title is set,
// OR there is a resolvable image — matching `if ($title || $image) :`.
if ( '' === $title && '' === $image_url ) {
	return;
}

$block_data = gs_block_classes( $attributes );
$button_url = isset( $button['url'] ) ? (string) $button['url'] : '';
?>
<section class="cta<?php echo esc_attr( $block_data['classes'] ); ?>"<?php echo $block_data['bg_color'] ? ' style="' . esc_attr( $block_data['bg_color'] ) . '"' : ''; ?>>
	<div class="wrapper">
		<div class="cta-text">
			<?php if ( '' !== $title ) : ?>
				<h3><?php echo esc_html( $title ); ?></h3>
			<?php endif; ?>
			<?php if ( '' !== $text ) : ?>
				<?php
				/*
				 * $text is already wp_kses_post()-sanitized HTML at save
				 * time (Wysiwyg::sanitize()); re-running wp_kses_post() here
				 * mirrors the original template's own
				 * `wp_kses_post($text)` call exactly (defensive, harmless
				 * second pass over already-safe HTML).
				 */
				echo wp_kses_post( $text );
				?>
			<?php endif; ?>
			<?php if ( '' !== $button_url ) : ?>
				<div><a class="btn" href="<?php echo esc_url( $button_url ); ?>"><?php echo esc_html( isset( $button['title'] ) ? (string) $button['title'] : '' ); ?></a></div>
			<?php endif; ?>
		</div>
		<?php
		/*
		 * The original template renders this `<img>` unconditionally,
		 * regardless of whether an image is actually set (ACF's own empty
		 * return for this field is `''`, so `esc_url('')` there — like
		 * `esc_url( $image_url )` here when `$image_url` is `''` — simply
		 * produces `src=""`). Reproduced faithfully: no extra guard wraps
		 * this markup beyond the section-level guard above.
		 */
		?>
		<div class="cta-image cover-image">
			<img src="<?php echo esc_url( $image_url ); ?>" <?php echo function_exists( 'theme_image_attrs' ) ? theme_image_attrs( $image_id, 'content', '' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme_image_attrs() itself esc_attr()s every attribute value it builds and internally guards a falsy id (returns ''); see includes/image-optim.php. ?> />
		</div>
	</div>
</section>
<?php
