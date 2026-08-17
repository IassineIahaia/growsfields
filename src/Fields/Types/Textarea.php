<?php
/**
 * Multi-line text field type.
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
 * Class Textarea
 *
 * ACF-compatible 'textarea' field type: a multi-line plain-text input.
 * Kept as slug `textarea` matching this project's `acf-json` exports (e.g.
 * `field_68e629058b9ad` / "Text") for a smooth Phase 3 migration.
 *
 * @package Growsfields
 */
class Textarea extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'textarea';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Text Area';
	}

	/**
	 * Sanitizes a submitted textarea value.
	 *
	 * Runs the raw value through `sanitize_textarea_field()` (strips tags
	 * and control characters while preserving line breaks, unlike
	 * `sanitize_text_field()`) so callers never persist unescaped/unstripped
	 * multi-line input, then truncates to `maxlength` when that option is
	 * set. `rows` is a display hint only (textarea height in the editor UI)
	 * and has no bearing on sanitization.
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return string Sanitized, length-constrained value.
	 */
	public function sanitize( $value ) {
		$value = sanitize_textarea_field( (string) $value );

		$maxlength = $this->get_option( 'maxlength', '' );

		if ( '' !== $maxlength && null !== $maxlength && (int) $maxlength > 0 ) {
			$value = mb_substr( $value, 0, (int) $maxlength );
		}

		return $value;
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
				'maxlength'   => $this->get_option( 'maxlength', '' ),
				'placeholder' => $this->get_option( 'placeholder', '' ),
				'rows'        => $this->get_option( 'rows', '' ),
			)
		);
	}
}
