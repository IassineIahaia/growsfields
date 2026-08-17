<?php
/**
 * Gallery (multi-image) field type.
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
 * Class Gallery
 *
 * ACF-compatible 'gallery' field type: stores an array of image attachment
 * IDs — always multi-value, unlike {@see Image}, which has no
 * single/multiple toggle. Per-ID validation is the same rule as
 * {@see Image::sanitize()} (`wp_attachment_is_image()`), but applied as a
 * filtered array: an invalid entry is dropped from the array, consistent
 * with how {@see Checkbox}/multi-{@see Select} drop invalid entries rather
 * than rejecting the whole submission — Image's single-value "fall back to
 * 0" pattern does not apply here since there is no single value to fall
 * back on. Not seen in this project's `acf-json` exports; modelled on ACF
 * Pro's documented Gallery field options (`min`, `max`, `insert`, `library`,
 * `return_format`).
 *
 * @package Growsfields
 */
class Gallery extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'gallery';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Gallery';
	}

	/**
	 * Sanitizes a submitted gallery value.
	 *
	 * `$value` is expected to be an array of attachment IDs; a `$value` that
	 * is not an array at all (a tampered submission, or simply an unset
	 * field) is not trusted to be partially valid and becomes an empty array
	 * rather than being coerced. `absint()` alone only guarantees a
	 * non-negative integer — same gap {@see Image::sanitize()} closes — so
	 * each entry is additionally checked with `wp_attachment_is_image()` and
	 * dropped (not replaced with a placeholder) when it does not identify a
	 * real, existing image attachment.
	 *
	 * When a `max` selection-count option is configured (> 0), IDs beyond it
	 * are truncated — same pattern as {@see Repeater::sanitize()}'s
	 * row-count `max` and {@see Relationship::sanitize()}'s selection-count
	 * `max`. `min` is deliberately NOT enforced here, same reasoning: see
	 * {@see self::to_js_schema()}, where it is round-tripped for the editor
	 * to enforce instead.
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return int[] A filtered, `max`-truncated array of valid image
	 *                attachment IDs.
	 */
	public function sanitize( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$valid = array();

		foreach ( $value as $entry ) {
			$id = absint( $entry );

			if ( $id > 0 && ( ! function_exists( 'wp_attachment_is_image' ) || wp_attachment_is_image( $id ) ) ) {
				$valid[] = $id;
			}
		}

		$max = (int) $this->get_option( 'max', 0 );

		if ( $max > 0 && count( $valid ) > $max ) {
			$valid = array_slice( $valid, 0, $max );
		}

		return $valid;
	}

	/**
	 * {@inheritDoc}
	 */
	public function default_value() {
		return (array) $this->get_option( 'default_value', array() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function to_js_schema(): array {
		return array_merge(
			$this->base_js_schema(),
			array(
				'min'           => (int) $this->get_option( 'min', 0 ),
				'max'           => (int) $this->get_option( 'max', 0 ),
				// append|prepend — where a newly added image lands relative
				// to the existing set. Presentation/UI ordering concern
				// only, evaluated by the editor UI.
				'insert'        => $this->get_option( 'insert', 'append' ),
				// all|uploadedTo — restricts the media library picker, same
				// option as File::to_js_schema()'s `library`.
				'library'       => $this->get_option( 'library', 'all' ),
				'return_format' => $this->get_option( 'return_format', 'array' ),
			)
		);
	}
}
