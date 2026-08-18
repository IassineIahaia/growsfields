<?php
/**
 * Server-side render template for the `growsfields/body` block.
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
 * (`starter-2026-iassine/blocks/body/render.php`, read-only ground truth):
 * - `get_field('show_title')` -> `$attributes['show_title']` (bool).
 * - `get_field('body')`       -> `$attributes['body']`       (string, already
 *   `wp_kses_post()`-sanitized HTML at save time — see
 *   `Growsfields\Fields\Types\Wysiwyg::sanitize()` — same trust level the
 *   original `<?= $body; ?>` already assumed for ACF's own `get_field('body')`
 *   return value, so it is echoed directly here too, without a second
 *   `wp_kses_post()` pass; the original never re-sanitized at output time
 *   either).
 * - `get_field('button')`     -> `$attributes['button']`     (array
 *   `{url, title, target}`).
 * - `get_block_classes()`     -> `gs_block_classes( $attributes )` — the
 *   original helper calls ACF's `get_field()` internally (ACF dependency this
 *   plugin does not have); {@see gs_block_classes()} in
 *   `src/Blocks/block-render-helpers.php` is this plugin's own equivalent,
 *   reading `spacing`/`bg_color`/`text_color` from `$attributes` instead (see
 *   that function's own docblock for the full byte-faithful port reasoning).
 *
 * @var array{show_title?: bool, body?: string, button?: array{url?: string, title?: string, target?: string}, spacing?: string, bg_color?: string, text_color?: string} $attributes
 */

$show_title = isset( $attributes['show_title'] ) ? (bool) $attributes['show_title'] : false;
$body       = isset( $attributes['body'] ) ? (string) $attributes['body'] : '';
$button     = isset( $attributes['button'] ) && is_array( $attributes['button'] ) ? $attributes['button'] : array();

// Same visibility guard as the original: render when either the title is
// toggled on, OR there is body content — matching the original's
// `if ($show_title || !empty($body)) :`.
if ( ! $show_title && empty( $body ) ) {
	return;
}

$block_data = gs_block_classes( $attributes );
$button_url = isset( $button['url'] ) ? (string) $button['url'] : '';
?>
<section class="body-text<?php echo esc_attr( $block_data['classes'] ); ?>"<?php echo $block_data['bg_color'] ? ' style="' . esc_attr( $block_data['bg_color'] ) . '"' : ''; ?>>
	<div class="wrapper">
		<div class="body-text-text text-container">
			<?php if ( $show_title ) : ?>
				<h1><?php echo esc_html( get_the_title() ); ?></h1>
			<?php endif; ?>
			<?php
			/*
			 * $body is already wp_kses_post()-sanitized HTML at save time
			 * (Wysiwyg::sanitize()) — same trust level as post_content
			 * itself, echoed directly here exactly like the original
			 * template's own `<?= $body; ?>`.
			 */
			echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already wp_kses_post()-sanitized at save time, see Wysiwyg::sanitize().
			?>
			<?php if ( '' !== $button_url ) : ?>
				<div><a class="btn" href="<?php echo esc_url( $button_url ); ?>"><?php echo esc_html( isset( $button['title'] ) ? (string) $button['title'] : '' ); ?></a></div>
			<?php endif; ?>
		</div>
	</div>
</section>
<?php
