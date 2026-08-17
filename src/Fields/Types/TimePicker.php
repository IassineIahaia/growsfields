<?php
/**
 * Time picker field type.
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
 * Class TimePicker
 *
 * ACF-compatible 'time_picker' field type: stores a time-only string. ACF's
 * own default storage format for this field is `H:i:s` — this class
 * matches that default. Not seen in this project's `acf-json` exports;
 * modelled on ACF Pro's documented Time Picker field options
 * (`display_format`, `return_format`).
 *
 * @package Growsfields
 */
class TimePicker extends FieldType {
	use ValidatesDateTimeFormat;

	/**
	 * ACF's own default storage format for this field type.
	 *
	 * @var string
	 */
	private const DEFAULT_STORAGE_FORMAT = 'H:i:s';

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'time_picker';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Time Picker';
	}

	/**
	 * Sanitizes a submitted time value.
	 *
	 * Same reasoning as {@see DatePicker::sanitize()}: validates the
	 * submission is a real, existing time in the field's configured
	 * storage format (`return_format`, defaulting to ACF's own `H:i:s`,
	 * e.g. rejecting an out-of-range value like `'25:61:00'`) via
	 * {@see ValidatesDateTimeFormat::matches_datetime_format()}, rather
	 * than trusting anything time-shaped. A value that fails falls back to
	 * {@see self::default_value()} (empty string).
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return string A validated time string in the storage format, or the
	 *                 default value.
	 */
	public function sanitize( $value ) {
		$value  = trim( (string) $value );
		$format = (string) $this->get_option( 'return_format', self::DEFAULT_STORAGE_FORMAT );

		if ( $this->matches_datetime_format( $value, $format ) ) {
			return $value;
		}

		return $this->default_value();
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
				'return_format'  => $this->get_option( 'return_format', self::DEFAULT_STORAGE_FORMAT ),
				// Display-only formatting option — affects how the picker
				// renders, not what gets validated/stored.
				'display_format' => $this->get_option( 'display_format', 'g:i a' ),
			)
		);
	}
}
