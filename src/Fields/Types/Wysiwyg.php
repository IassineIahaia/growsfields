<?php
/**
 * WYSIWYG (rich text editor) field type.
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
 * Class Wysiwyg
 *
 * ACF-compatible 'wysiwyg' field type: rich HTML content edited through
 * TinyMCE. Kept as slug `wysiwyg` matching this project's `acf-json`
 * exports (e.g. `field_6488ab19ee2b1` / "Body", which uses
 * `toolbar: "full"` and `media_upload: 1`) for a smooth Phase 3 migration.
 *
 * @package Growsfields
 */
class Wysiwyg extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'wysiwyg';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Wysiwyg Editor';
	}

	/**
	 * Sanitizes submitted rich-text HTML.
	 *
	 * Runs the raw value through `wp_kses_post()`, the same allowlist
	 * WordPress uses for post content, so arbitrary/unsafe HTML (script
	 * tags, event handler attributes, disallowed elements) can never be
	 * persisted through this field while still allowing the markup a rich
	 * text editor legitimately produces (paragraphs, links, formatting,
	 * embeds it is allowed to emit).
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return string Sanitized HTML, safe to store and later output.
	 */
	public function sanitize( $value ) {
		return wp_kses_post( (string) $value );
	}

	/**
	 * {@inheritDoc}
	 */
	public function default_value() {
		return $this->get_option( 'default_value', '' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function to_js_schema(): array {
		return array_merge(
			$this->base_js_schema(),
			array(
				'toolbar'      => $this->get_option( 'toolbar', 'full' ),
				'media_upload' => (bool) $this->get_option( 'media_upload', true ),
			)
		);
	}
}
