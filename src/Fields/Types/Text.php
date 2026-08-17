<?php
/**
 * Single-line text field type.
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
 * Class Text
 *
 * ACF-compatible 'text' field type: a single-line text input. Kept as slug
 * `text` (matching the historical ACF slug seen in this project's
 * `acf-json` field group exports, e.g. `field_67d0031a8d17f` /
 * "Titel") so Phase 3's field group migration can map straight across
 * without a slug translation table.
 *
 * @package Growsfields
 */
class Text extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'text';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Text';
	}

	/**
	 * Sanitizes a submitted text value.
	 *
	 * Runs the raw value through `sanitize_text_field()` (strips tags,
	 * extra whitespace, and control characters) so callers never persist
	 * unescaped/unstripped input, then truncates to `maxlength` when that
	 * option is set. Truncation uses `mb_substr()` rather than discarding
	 * the value outright, matching ACF's own behaviour of clipping instead
	 * of rejecting an over-length submission.
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return string Sanitized, length-constrained value.
	 */
	public function sanitize( $value ) {
		$value = sanitize_text_field( (string) $value );

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
			)
		);
	}
}
