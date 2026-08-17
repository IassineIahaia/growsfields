<?php
/**
 * Shared date/time format validation for Date Picker, Date Time Picker, and
 * Time Picker field types.
 *
 * @package Growsfields
 */

namespace Growsfields\Fields\Types;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait ValidatesDateTimeFormat
 *
 * {@see DatePicker}, {@see DateTimePicker}, and {@see TimePicker} all
 * validate a submitted value the same way — "does this string represent a
 * real, existing calendar date/time in the field's configured storage
 * format" — differing only in which format string they check against and
 * what the fallback default looks like. That is genuinely the same piece of
 * logic three times, not three related-but-different validations, so it is
 * factored into a trait (rather than a shared base class, since these three
 * field types have no other behaviour in common and already each extend
 * `FieldType` directly) instead of hand-rolling a slightly different regex
 * per class. `DateTime::createFromFormat()` plus
 * `DateTime::getLastErrors()` is used rather than a regex because a regex
 * can only check that a string is digit/separator-shaped (e.g. it would
 * happily accept `'20260230'` as "looks like Ymd") — it has no notion of
 * which months have how many days, or of leap years. `DateTime` itself
 * enforces real calendar validity.
 *
 * @package Growsfields
 */
trait ValidatesDateTimeFormat {

	/**
	 * Checks whether `$value` is a real date/time matching `$format`
	 * exactly (e.g. rejects `'20260230'` — 30 February does not exist —
	 * even though it is digit-shaped and the same length as a valid `Ymd`
	 * value).
	 *
	 * @param string $value  Raw string to validate.
	 * @param string $format A `DateTime::createFromFormat()`-compatible
	 *                        format string, e.g. `'Ymd'`, `'Y-m-d H:i:s'`,
	 *                        `'H:i:s'`.
	 * @return bool True when `$value` parses as a real, valid date/time in
	 *               `$format` with no warnings or errors.
	 */
	protected function matches_datetime_format( string $value, string $format ): bool {
		if ( '' === $value ) {
			return false;
		}

		$date = \DateTime::createFromFormat( $format, $value );

		if ( false === $date ) {
			return false;
		}

		$errors = \DateTime::getLastErrors();

		// PHP < 8.2 returns an array with 'warning_count'/'error_count'
		// keys; PHP >= 8.2 returns false when there are none. Both shapes
		// are handled so this trait works across the version range WP
		// itself still supports.
		if ( false === $errors ) {
			return true;
		}

		return 0 === $errors['warning_count'] && 0 === $errors['error_count'];
	}
}
