<?php
/**
 * Boolean toggle/checkbox field type.
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
 * Class TrueFalse
 *
 * ACF-compatible 'true_false' field type: stores a single boolean. Kept as
 * slug `true_false` matching this project's `acf-json` exports (e.g.
 * `field_6488b4fed3e89` / "Toon titel") for a smooth Phase 3 migration.
 *
 * @package Growsfields
 */
class TrueFalse extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'true_false';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'True / False';
	}

	/**
	 * Sanitizes a submitted boolean value.
	 *
	 * Protects against the common bug class of a naive `(bool)` cast on a
	 * string-typed submission: in PHP, `(bool) '0'` is `false` but
	 * `(bool) 'false'` is `true` (any non-empty string is truthy), so a
	 * literal string `'false'` coming from a form field, REST body, or
	 * block attribute would otherwise be stored as `true`. Instead this
	 * explicitly whitelists the values that should be treated as `true`
	 * (`true`, `1`, `'1'`, `'true'`, `'on'` — the shapes a checkbox/toggle
	 * realistically submits) and treats everything else, including the
	 * string `'false'`, as `false`.
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return bool Sanitized boolean.
	 */
	public function sanitize( $value ) {
		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );

			return in_array( $value, array( '1', 'true', 'on' ), true );
		}

		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return 1 === (int) $value;
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function default_value() {
		return (bool) $this->get_option( 'default_value', false );
	}

	/**
	 * {@inheritDoc}
	 */
	public function to_js_schema(): array {
		return array_merge(
			$this->base_js_schema(),
			array(
				'ui'          => (bool) $this->get_option( 'ui', false ),
				'ui_on_text'  => $this->get_option( 'ui_on_text', '' ),
				'ui_off_text' => $this->get_option( 'ui_off_text', '' ),
			)
		);
	}
}
