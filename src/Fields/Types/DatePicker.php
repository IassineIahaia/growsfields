<?php
/**
 * Date picker field type.
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
 * Class DatePicker
 *
 * ACF-compatible 'date_picker' field type: stores a date-only string. ACF's
 * own default storage format for this field is `Ymd` (no separators,
 * deliberately different from the `display_format` shown to the editor, so
 * stored values sort/compare correctly as plain strings) — this class
 * matches that default. Not seen in this project's `acf-json` exports;
 * modelled on ACF Pro's documented Date Picker field options
 * (`display_format`, `return_format`, `first_day`).
 *
 * @package Growsfields
 */
class DatePicker extends FieldType {
	use ValidatesDateTimeFormat;

	/**
	 * ACF's own default storage format for this field type.
	 *
	 * @var string
	 */
	private const DEFAULT_STORAGE_FORMAT = 'Ymd';

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'date_picker';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Date Picker';
	}

	/**
	 * Sanitizes a submitted date value.
	 *
	 * A digit-shaped string is not necessarily a real date — `'20260230'`
	 * (30 February) matches a naive `Ymd` regex but does not exist on any
	 * calendar. This method validates the submission against the field's
	 * configured storage format (`return_format`, defaulting to ACF's own
	 * `Ymd`) using {@see ValidatesDateTimeFormat::matches_datetime_format()},
	 * which parses via `DateTime` and checks for real calendar validity, not
	 * just shape. A value that fails falls back to
	 * {@see self::default_value()} (empty string).
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return string A validated date string in the storage format, or the
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
				// Display-only formatting/UI options — they affect how the
				// picker renders, not what gets validated/stored.
				'display_format' => $this->get_option( 'display_format', 'F j, Y' ),
				'first_day'      => (int) $this->get_option( 'first_day', 1 ),
			)
		);
	}
}
