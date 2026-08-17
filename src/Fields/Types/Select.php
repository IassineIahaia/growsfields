<?php
/**
 * Select (dropdown) field type.
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
 * Class Select
 *
 * ACF-compatible 'select' field type: a dropdown over a fixed set of
 * `choices` (value => label), same shape as {@see Radio}, plus a `multiple`
 * option that switches the stored value between a single string and an
 * array of strings. Not seen in this project's `acf-json` exports; modelled
 * on ACF Pro's documented Select field options (`choices`, `default_value`,
 * `allow_null`, `multiple`, `ui`, `ajax`, `placeholder`). `ajax` (remote
 * choice loading) is a runtime/loading concern for the Phase 6-B builder and
 * a future REST endpoint, not something `sanitize()` needs to know about, so
 * it is intentionally omitted here.
 *
 * @package Growsfields
 */
class Select extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'select';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Select';
	}

	/**
	 * Sanitizes a submitted select value.
	 *
	 * `choices` ships to the browser for rendering the dropdown, so — same
	 * reasoning as {@see Radio::sanitize()} — a submission cannot be
	 * trusted to only ever contain an *offered* value and is re-validated
	 * against the field's own `choices` keys server-side.
	 *
	 * Branches on the `multiple` option:
	 * - When true, `$value` is expected to be an array; each entry is
	 *   validated independently and invalid entries are simply dropped
	 *   rather than rejecting the whole submission — a multi-select
	 *   naturally allows some entries to be valid and others tampered
	 *   with, and discarding just the bad ones matches how a checkbox-like
	 *   multi-value input should degrade. Non-array input becomes an empty
	 *   array.
	 * - When false, single-value validation is identical to Radio: the
	 *   submitted value must be a registered `choices` key, or the field's
	 *   default value is returned instead.
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return string[]|string A filtered array of valid `choices` keys
	 *                          (when `multiple`), or a single valid
	 *                          `choices` key / the default value.
	 */
	public function sanitize( $value ) {
		$choices = $this->get_choices();

		if ( $this->get_option( 'multiple', false ) ) {
			if ( ! is_array( $value ) ) {
				return array();
			}

			$valid = array();

			foreach ( $value as $entry ) {
				$entry = sanitize_text_field( (string) $entry );

				if ( array_key_exists( $entry, $choices ) ) {
					$valid[] = $entry;
				}
			}

			return $valid;
		}

		$value = sanitize_text_field( (string) $value );

		if ( array_key_exists( $value, $choices ) ) {
			return $value;
		}

		return $this->default_value();
	}

	/**
	 * {@inheritDoc}
	 */
	public function default_value() {
		return $this->get_option( 'default_value', $this->get_option( 'multiple', false ) ? array() : '' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function to_js_schema(): array {
		return array_merge(
			$this->base_js_schema(),
			array(
				'choices'     => $this->get_choices(),
				'multiple'    => (bool) $this->get_option( 'multiple', false ),
				// ACF's "allow null" option — whether an empty selection is
				// permitted. Pass-through only, evaluated by the editor UI,
				// not enforced here (this class already falls back to
				// default_value() for anything invalid, which covers the
				// "no valid selection" case regardless of this option).
				'allow_null'  => (bool) $this->get_option( 'allow_null', false ),
				// ACF's "Stylized UI" toggle (renders a Select2-style
				// searchable dropdown instead of a native <select>).
				// Presentation only.
				'ui'          => (bool) $this->get_option( 'ui', false ),
				'placeholder' => $this->get_option( 'placeholder', '' ),
			)
		);
	}

	/**
	 * The registered `value => label` choices for this field instance.
	 *
	 * @return array<string, string>
	 */
	protected function get_choices(): array {
		return (array) $this->get_option( 'choices', array() );
	}
}
