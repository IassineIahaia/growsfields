<?php
/**
 * Link (compound URL/title/target) field type.
 *
 * @package Growsfields
 */

namespace Growsfields\Fields\Types;

use Growsfields\Fields\FieldType;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Link
 *
 * ACF-compatible 'link' field type: a compound value with `url`, `title`,
 * and `target` keys (ACF's own runtime shape for this field type — the
 * `acf-json` field group config itself only carries editor-time options
 * like `return_format`, not a sample value, but the stored/returned shape
 * is this three-key array). Kept as slug `link` matching this project's
 * `acf-json` exports (e.g. `field_695d1322d9ed4` / "Button") for a smooth
 * Phase 3 migration.
 *
 * @package Growsfields
 */
class Link extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'link';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Link';
	}

	/**
	 * Sanitizes a submitted link value.
	 *
	 * Each sub-key is sanitized with the WordPress function appropriate to
	 * what it holds: `esc_url_raw()` for `url` (safe to store, protects
	 * against `javascript:`-style and otherwise malformed URLs),
	 * `sanitize_text_field()` for `title`, and `target` is constrained to
	 * an explicit whitelist (`''` or `'_blank'`) rather than passed
	 * through, since it is the one sub-value that changes browser
	 * behaviour (opening a new tab/window) and an attacker-controlled value
	 * here has no legitimate use beyond that whitelist. A `$value` that is
	 * not an array at all (a tampered submission, or simply an unset
	 * field) is not trusted to be partially valid — it is rejected
	 * wholesale in favour of the field's empty shape rather than attempting
	 * to coerce it.
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return array{url: string, title: string, target: string} Sanitized
	 *                                                             link
	 *                                                             array.
	 */
	public function sanitize( $value ) {
		if ( ! is_array( $value ) ) {
			return $this->empty_link();
		}

		$target = isset( $value['target'] ) ? (string) $value['target'] : '';

		return array(
			'url'    => isset( $value['url'] ) ? esc_url_raw( (string) $value['url'] ) : '',
			'title'  => isset( $value['title'] ) ? sanitize_text_field( (string) $value['title'] ) : '',
			'target' => '_blank' === $target ? '_blank' : '',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function default_value() {
		return $this->empty_link();
	}

	/**
	 * {@inheritDoc}
	 */
	public function to_js_schema(): array {
		return array_merge(
			$this->base_js_schema(),
			array(
				'return_format' => $this->get_option( 'return_format', 'array' ),
			)
		);
	}

	/**
	 * The empty/unset shape for a Link value.
	 *
	 * @return array{url: string, title: string, target: string}
	 */
	private function empty_link(): array {
		return array(
			'url'    => '',
			'title'  => '',
			'target' => '',
		);
	}
}
