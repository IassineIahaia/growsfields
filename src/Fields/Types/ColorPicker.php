<?php
/**
 * Color picker field type.
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
 * Class ColorPicker
 *
 * ACF-compatible 'color_picker' field type: stores a hex color string (and
 * optionally an rgba() string when `enable_opacity` is on). Kept as slug
 * `color_picker` matching this project's `acf-json` exports (e.g.
 * `field_695b81848396b` / "Background color", which has `enable_opacity`
 * set to `0` — every instance in this project's field groups has opacity
 * disabled, so plain hex is the common case, but the option itself is
 * still respected per-instance) for a smooth Phase 3 migration.
 *
 * @package Growsfields
 */
class ColorPicker extends FieldType {

	/**
	 * Matches a 3- or 6-digit hex color, with or without a leading '#'.
	 *
	 * @var string
	 */
	private const HEX_PATTERN = '/^#?(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/';

	/**
	 * Matches an rgba() color function, e.g. 'rgba(0,0,0,0.5)'.
	 *
	 * @var string
	 */
	private const RGBA_PATTERN = '/^rgba\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(0|1|0?\.\d+)\s*\)$/i';

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'color_picker';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Color Picker';
	}

	/**
	 * Sanitizes a submitted color value.
	 *
	 * `sanitize_text_field()` alone would happily let `"not-a-color"`
	 * through unchanged — it strips tags/whitespace but has no notion of
	 * what a valid color looks like. This method instead validates the
	 * value against a real hex-color pattern (3 or 6 digits, leading '#'
	 * optional) and, only when the field's `enable_opacity` option is on,
	 * also accepts an `rgba(r, g, b, a)` string. Anything that matches
	 * neither shape is rejected and the field's default value is stored
	 * instead, so a tampered or malformed submission can never end up
	 * persisted as a "color".
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return string A validated hex (or, when enabled, rgba()) color
	 *                 string, or the default value.
	 */
	public function sanitize( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return $this->default_value();
		}

		if ( 1 === preg_match( self::HEX_PATTERN, $value ) ) {
			return $value;
		}

		if ( $this->get_option( 'enable_opacity', false ) && 1 === preg_match( self::RGBA_PATTERN, $value ) ) {
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
				'enable_opacity' => (bool) $this->get_option( 'enable_opacity', false ),
			)
		);
	}
}
