<?php
/**
 * Server-side render template for the `growsfields/default-block` block.
 *
 * @package Growsfields
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DEV SCAFFOLD — NO BACKING FIELD GROUP TODAY.
 *
 * Mirrors the real theme's own framing for this block
 * (`starter-2026-iassine/blocks/default-block/block.json`'s description:
 * "Copy this to create a new blocks. Or use 'npm run new:block [block-name]'
 * to create a new block."). No `field-groups/*.json` in this plugin targets
 * `growsfields/default-block` — confirmed against every real, migrated group
 * — matching the real theme's own `acf-json/` export, which likewise never
 * had a field group targeting `acf/default-block`. That means
 * `BlockLoader::compute_attributes_for_block('growsfields/default-block')`
 * only ever picks up the shared "Block options" fields (`spacing`/
 * `bg_color`/`text_color`, via `field-groups/group_69380e4dc49ac.json`'s
 * `block==all` wildcard, which does not exclude this block) — `show_title`,
 * `body`, and `button` below are NEVER populated by anything a site editor
 * can set, so the guard below always evaluates false and this block renders
 * nothing, exactly matching the real theme's current behaviour. This is not
 * a bug to fix here; do not add a field group for this block (see the
 * maintainer's own note on why that would invent content the source project
 * never had).
 *
 * Included by {@see \Growsfields\Blocks\BlockLoader::render_block()} with
 * `$attributes` already in scope as a local variable — see
 * `blocks/hero/render.php` for the full explanation of this convention. Do
 * not redeclare `$attributes` here; a missing key is guarded with `isset()`
 * below purely for defensive robustness.
 *
 * Field-by-field mapping from the real theme's ACF template
 * (`starter-2026-iassine/blocks/default-block/render.php`, read-only ground
 * truth — identical structure to `body`'s own template, just
 * `class="default-block"` instead of `class="body-text"`):
 * - `get_field('show_title')` -> `$attributes['show_title']` (bool).
 * - `get_field('body')`       -> `$attributes['body']`       (string,
 *   WYSIWYG-shaped/already-sanitized when present — see `body/render.php`'s
 *   own docblock note; echoed directly, same trust level).
 * - `get_field('button')`     -> `$attributes['button']`     (array
 *   `{url, title, target}`).
 * - `get_block_classes()`     -> `gs_block_classes( $attributes )`.
 *
 * @var array{show_title?: bool, body?: string, button?: array{url?: string, title?: string, target?: string}, spacing?: string, bg_color?: string, text_color?: string} $attributes
 */

$show_title = isset( $attributes['show_title'] ) ? (bool) $attributes['show_title'] : false;
$body       = isset( $attributes['body'] ) ? (string) $attributes['body'] : '';
$button     = isset( $attributes['button'] ) && is_array( $attributes['button'] ) ? $attributes['button'] : array();

// Same visibility guard as the original: render when either the title is
// toggled on, OR there is body content — matching
// `if ($show_title || !empty($body)) :`. In practice, per the dev-scaffold
// note above, neither of these attributes is ever populated today, so this
// is always false.
if ( ! $show_title && empty( $body ) ) {
	return;
}

$block_data = gs_block_classes( $attributes );
$button_url = isset( $button['url'] ) ? (string) $button['url'] : '';
?>
<section class="default-block<?php echo esc_attr( $block_data['classes'] ); ?>"<?php echo $block_data['bg_color'] ? ' style="' . esc_attr( $block_data['bg_color'] ) . '"' : ''; ?>>
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
