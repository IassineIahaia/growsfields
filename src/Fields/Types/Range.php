<?php
/**
 * Range (slider) field type.
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
 * Class Range
 *
 * ACF-compatible 'range' field type: a slider input over a numeric value.
 * Storage and validation are the same as {@see Number} (a range slider is,
 * under the hood, still just a bounded numeric value) but this is kept as
 * its own class rather than a thin Number subclass because ACF itself ships
 * `range` as a fully separate field type with its own identity, and — unlike
 * Number, where `min`/`max` are optional bounds — a slider is meaningless
 * without them, so this class defaults `min`/`max` to `0`/`100` rather than
 * leaving them blank. Not seen in this project's `acf-json` exports; modelled
 * on ACF Pro's documented Range field options (`min`, `max`, `step`,
 * `prepend`, `append`).
 *
 * @package Growsfields
 */
class Range extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'range';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Range';
	}

	/**
	 * Sanitizes a submitted range value.
	 *
	 * Same reasoning as {@see Number::sanitize()}: `is_numeric()` is
	 * checked before casting so a non-numeric submission cannot be
	 * misread as a real `0`, and the result is clamped into `min`/`max`.
	 * Unlike Number, `min`/`max` here always have a value (defaulting to
	 * `0`/`100` via {@see self::get_bound()}), since an unbounded slider
	 * has no meaningful UI — so clamping always applies, not only when the
	 * options happen to be configured.
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return float A numeric value clamped into `min`/`max`, or the
	 *                default value.
	 */
	public function sanitize( $value ) {
		if ( ! is_numeric( $value ) ) {
			return $this->default_value();
		}

		$number = (float) $value;
		$min    = $this->get_bound( 'min', 0 );
		$max    = $this->get_bound( 'max', 100 );

		if ( $number < $min ) {
			$number = $min;
		}

		if ( $number > $max ) {
			$number = $max;
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
				'min'  => $this->get_bound( 'min', 0 ),
				'max'  => $this->get_bound( 'max', 100 ),
				// Step, like Number, only affects the slider's UI
				// granularity and is not re-enforced in sanitize().
				'step' => $this->get_option( 'step', 1 ),
			)
		);
	}

	/**
	 * Reads `min`/`max` from config, falling back to `$default` when the
	 * option is missing or not numeric — a slider needs both bounds to
	 * render at all, so (unlike Number) this class never leaves them
	 * blank.
	 *
	 * @param string $key     Either 'min' or 'max'.
	 * @param float  $default Fallback bound.
	 * @return float
	 */
	private function get_bound( string $key, float $default ): float {
		$value = $this->get_option( $key, '' );

		return is_numeric( $value ) ? (float) $value : $default;
	}
}
