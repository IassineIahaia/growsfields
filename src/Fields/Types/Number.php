<?php
/**
 * Number field type.
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
 * Class Number
 *
 * ACF-compatible 'number' field type: stores a single numeric value (integer
 * or decimal — ACF's own Number field does not distinguish between the two,
 * it simply stores whatever numeric value was entered). Not seen in this
 * project's `acf-json` exports; this implementation is modelled on ACF Pro's
 * documented Number field options (`min`, `max`, `step`, `placeholder`).
 *
 * @package Growsfields
 */
class Number extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'number';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Number';
	}

	/**
	 * Sanitizes a submitted number value.
	 *
	 * `(float) $value` on a non-numeric string (e.g. `'abc'`) silently
	 * evaluates to `0`, which is indistinguishable from a genuinely
	 * submitted zero — that would let garbage input masquerade as valid
	 * data. This method checks `is_numeric()` first and falls back to
	 * {@see self::default_value()} for anything that fails it, rather than
	 * casting blindly. A value that passes is cast to `float` (int-valued
	 * floats round-trip through PHP/JSON cleanly, and ACF's own Number
	 * field does not distinguish int vs. float storage) and then clamped
	 * into `min`/`max` when either option is configured, so a submission
	 * cannot bypass bounds the field author configured in the UI.
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return float|int|string A numeric value within `min`/`max` (when
	 *                            configured), or the default value.
	 */
	public function sanitize( $value ) {
		if ( ! is_numeric( $value ) ) {
			return $this->default_value();
		}

		$number = (float) $value;

		$min = $this->get_option( 'min', '' );
		$max = $this->get_option( 'max', '' );

		if ( is_numeric( $min ) && $number < (float) $min ) {
			$number = (float) $min;
		}

		if ( is_numeric( $max ) && $number > (float) $max ) {
			$number = (float) $max;
		}

		return $number;
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
				'min'         => $this->get_option( 'min', '' ),
				'max'         => $this->get_option( 'max', '' ),
				// Step is a UI nicety (controls the input's increment
				// buttons / keyboard stepping) — not enforced server-side
				// in sanitize(), since a value that skips a step is not a
				// data-integrity or security concern the way an out-of-range
				// value is.
				'step'        => $this->get_option( 'step', '' ),
				'placeholder' => $this->get_option( 'placeholder', '' ),
			)
		);
	}
}
