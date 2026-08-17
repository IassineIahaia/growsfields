<?php
/**
 * File (attachment) field type.
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
 * Class File
 *
 * ACF-compatible 'file' field type: stores an attachment ID, same as
 * {@see Image} but for any attachment type, not only images — that is the
 * entire distinction ACF itself draws between the two field types. Not seen
 * in this project's `acf-json` exports; modelled on ACF Pro's documented
 * File field options (`return_format`, `library`, `min_size`, `max_size`,
 * `mime_types`). `min_size`/`max_size`/`mime_types` are upload-time
 * constraints enforced by the media uploader UI/endpoint at upload time, not
 * something a post-hoc `sanitize()` on an already-uploaded attachment ID can
 * meaningfully re-check, so they are intentionally omitted here (same
 * reasoning `Image` applies by omitting `min_width`/`max_width`/etc.).
 *
 * @package Growsfields
 */
class File extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'file';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'File';
	}

	/**
	 * Sanitizes a submitted file value into an attachment ID.
	 *
	 * `absint()` alone only guarantees a non-negative integer — it does not
	 * guarantee that integer actually identifies an existing attachment.
	 * Unlike {@see Image::sanitize()}, there is no `wp_attachment_is_file()`
	 * equivalent in WordPress core to call (attachments cover every
	 * mime type, so "is a file" is not a distinct condition the way "is an
	 * image" is) — instead this method loads the post directly via
	 * `get_post()` and checks `get_post_type() === 'attachment'`, which is
	 * the general-purpose way to confirm an ID is a real, existing
	 * attachment of any kind. An ID for a non-existent post, a deleted
	 * attachment, or a post of any other type falls back to `0` (empty).
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return int A valid attachment ID, or 0.
	 */
	public function sanitize( $value ) {
		$id = absint( $value );

		if ( $id <= 0 ) {
			return 0;
		}

		if ( null === get_post( $id ) || 'attachment' !== get_post_type( $id ) ) {
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
				// all|uploadedTo — restricts the media library picker.
				// Presentation/UI concern only.
				'library'       => $this->get_option( 'library', 'all' ),
			)
		);
	}
}
