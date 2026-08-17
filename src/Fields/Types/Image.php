<?php
/**
 * Image (attachment) field type.
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
 * Class Image
 *
 * ACF-compatible 'image' field type: stores an attachment ID. Kept as slug
 * `image` matching this project's `acf-json` exports (e.g.
 * `field_6488b63421b64` / "Header image", which uses `return_format: "url"`
 * and `preview_size: "medium"`) for a smooth Phase 3 migration. The stored
 * value is always the attachment ID regardless of `return_format` — that
 * option only affects how a *consumer* of the field (block render, REST
 * response) presents the value, not what is persisted.
 *
 * @package Growsfields
 */
class Image extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'image';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Image';
	}

	/**
	 * Sanitizes a submitted image value into an attachment ID.
	 *
	 * `absint()` alone only guarantees a non-negative integer — it does
	 * not guarantee that integer actually identifies an attachment, let
	 * alone an image. A submitted ID for a post, a deleted attachment, or a
	 * non-image attachment (e.g. a PDF) would otherwise be stored and later
	 * fail wherever the field is rendered as an `<img>`. This method
	 * additionally checks `wp_attachment_is_image()` and falls back to `0`
	 * (empty) for anything that is not a real, existing image attachment.
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return int A valid image attachment ID, or 0.
	 */
	public function sanitize( $value ) {
		$id = absint( $value );

		if ( $id <= 0 ) {
			return 0;
		}

		if ( function_exists( 'wp_attachment_is_image' ) && ! wp_attachment_is_image( $id ) ) {
			return 0;
		}

		return $id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function default_value() {
		return absint( $this->get_option( 'default_value', 0 ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function to_js_schema(): array {
		return array_merge(
			$this->base_js_schema(),
			array(
				'return_format' => $this->get_option( 'return_format', 'array' ),
				'preview_size'  => $this->get_option( 'preview_size', 'medium' ),
			)
		);
	}
}
