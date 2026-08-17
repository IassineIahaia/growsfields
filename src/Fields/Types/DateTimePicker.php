<?php
/**
 * Date time picker field type.
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
 * Class DateTimePicker
 *
 * ACF-compatible 'date_time_picker' field type: stores a combined
 * date-and-time string. ACF's own default storage format for this field is
 * `Y-m-d H:i:s` — this class matches that default. Not seen in this
 * project's `acf-json` exports; modelled on ACF Pro's documented Date Time
 * Picker field options (`display_format`, `return_format`, `first_day`).
 *
 * @package Growsfields
 */
class DateTimePicker extends FieldType {
	use ValidatesDateTimeFormat;

	/**
	 * ACF's own default storage format for this field type.
	 *
	 * @var string
	 */
	private const DEFAULT_STORAGE_FORMAT = 'Y-m-d H:i:s';

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'date_time_picker';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Date Time Picker';
	}

	/**
	 * Sanitizes a submitted date-time value.
	 *
	 * Same reasoning as {@see DatePicker::sanitize()}: validates the
	 * submission is a real, existing calendar date and time in the field's
	 * configured storage format (`return_format`, defaulting to ACF's own
	 * `Y-m-d H:i:s`) via
	 * {@see ValidatesDateTimeFormat::matches_datetime_format()}, rather than
	 * trusting anything date/time-shaped. A value that fails falls back to
	 * {@see self::default_value()} (empty string).
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return string A validated date-time string in the storage format, or
	 *                 the default value.
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
				// Display-only formatting/UI options — they affect how the
				// picker renders, not what gets validated/stored.
				'display_format' => $this->get_option( 'display_format', 'F j, Y g:i a' ),
				'first_day'      => (int) $this->get_option( 'first_day', 1 ),
			)
		);
	}
}
