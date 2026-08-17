<?php
/**
 * Google Map (compound lat/lng/address) field type.
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
 * Class GoogleMap
 *
 * ACF-compatible 'google_map' field type: a compound value with `lat`,
 * `lng`, `address`, and `zoom` keys — ACF's own runtime shape for this field
 * type, same "compound array" approach {@see Link} takes for `url`/`title`/
 * `target`. Not seen in this project's `acf-json` exports; modelled on ACF
 * Pro's documented Google Map field options (`center_lat`, `center_lng`,
 * `zoom`, `height`).
 *
 * @package Growsfields
 */
class GoogleMap extends FieldType {

	/**
	 * Default map zoom level used when a submitted `zoom` is missing or
	 * invalid. Matches ACF Pro's own documented default.
	 */
	private const DEFAULT_ZOOM = 14;

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'google_map';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Google Map';
	}

	/**
	 * Sanitizes a submitted Google Map value.
	 *
	 * A `$value` that is not an array at all (a tampered submission, or
	 * simply an unset field) is not trusted to be partially valid — it is
	 * rejected wholesale in favour of the field's empty shape, same pattern
	 * as {@see Link::sanitize()}.
	 *
	 * `lat`/`lng` are not blindly cast: same discipline
	 * {@see Number::sanitize()} applies, `is_numeric()` is checked first so
	 * a non-numeric submission cannot silently become `0` (which would be
	 * indistinguishable from a genuine coordinate of `0`) via
	 * {@see self::sanitize_coordinate()}, which additionally rejects a
	 * numeric value outside real-world geographic bounds (latitude -90..90,
	 * longitude -180..180) rather than clamping it — an out-of-range
	 * coordinate is not a "too big" version of a real one, it is not a real
	 * coordinate at all, so it falls back to an empty value rather than
	 * being silently clamped into range.
	 *
	 * `address` is free text and sanitized with `sanitize_text_field()`.
	 * `zoom` is cast with `absint()`, same as an attachment ID elsewhere in
	 * this engine, falling back to {@see self::DEFAULT_ZOOM} when missing or
	 * non-positive.
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return array{lat: float|string, lng: float|string, address: string, zoom: int}
	 *               Sanitized Google Map array.
	 */
	public function sanitize( $value ) {
		if ( ! is_array( $value ) ) {
			return $this->empty_map();
		}

		$zoom = isset( $value['zoom'] ) ? absint( $value['zoom'] ) : 0;

		return array(
			'lat'     => $this->sanitize_coordinate( $value['lat'] ?? '', -90.0, 90.0 ),
			'lng'     => $this->sanitize_coordinate( $value['lng'] ?? '', -180.0, 180.0 ),
			'address' => isset( $value['address'] ) ? sanitize_text_field( (string) $value['address'] ) : '',
			'zoom'    => $zoom > 0 ? $zoom : self::DEFAULT_ZOOM,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function default_value() {
		return $this->empty_map();
	}

	/**
	 * {@inheritDoc}
	 */
	public function to_js_schema(): array {
		return array_merge(
			$this->base_js_schema(),
			array(
				// Initial map center/zoom shown before a value is picked —
				// presentation-only, evaluated by the editor UI.
				'center_lat' => $this->get_option( 'center_lat', '' ),
				'center_lng' => $this->get_option( 'center_lng', '' ),
				'zoom'       => (int) $this->get_option( 'zoom', self::DEFAULT_ZOOM ),
				// Map height in px within the edit screen. Presentation only.
				'height'     => $this->get_option( 'height', '' ),
			)
		);
	}

	/**
	 * Sanitizes one coordinate value against `[$min, $max]`.
	 *
	 * @param mixed $value Raw coordinate value as submitted.
	 * @param float $min   Minimum valid value (inclusive).
	 * @param float $max   Maximum valid value (inclusive).
	 * @return float|string A valid float coordinate, or `''` when the input
	 *                        was non-numeric or out of range.
	 */
	private function sanitize_coordinate( $value, float $min, float $max ) {
		if ( ! is_numeric( $value ) ) {
			return '';
		}

		$number = (float) $value;

		if ( $number < $min || $number > $max ) {
			return '';
		}

		return $number;
	}

	/**
	 * The empty/unset shape for a Google Map value.
	 *
	 * @return array{lat: string, lng: string, address: string, zoom: int}
	 */
	private function empty_map(): array {
		return array(
			'lat'     => '',
			'lng'     => '',
			'address' => '',
			'zoom'    => self::DEFAULT_ZOOM,
		);
	}
}
