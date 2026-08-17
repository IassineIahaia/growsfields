<?php
/**
 * Relationship (multi post-select) field type.
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
 * Class Relationship
 *
 * ACF-compatible 'relationship' field type: always stores an array of post
 * IDs — unlike {@see PostObject}/{@see PageLink}, there is no `multiple`
 * toggle, Relationship is inherently multi-value, which is ACF's own
 * distinction between the two. Per-ID validation is the same rule as
 * `PostObject` (existence, non-trashed, optional `post_type` allow-list). Not
 * seen in this project's `acf-json` exports; modelled on ACF Pro's documented
 * Relationship field options (`post_type`, `taxonomy`, `filters`, `min`,
 * `max`, `return_format`).
 *
 * @package Growsfields
 */
class Relationship extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'relationship';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Relationship';
	}

	/**
	 * Sanitizes a submitted relationship value.
	 *
	 * `$value` is expected to be an array of post IDs; a `$value` that is not
	 * an array at all (a tampered submission, or simply an unset field) is
	 * not trusted to be partially valid and becomes an empty array rather
	 * than being coerced. Each entry is validated via
	 * {@see self::is_valid_post_id()} (existence, trashed status, and an
	 * optional `post_type` allow-list — same rule as
	 * {@see PostObject::is_valid_post_id()}); invalid entries are dropped
	 * individually rather than rejecting the whole submission.
	 *
	 * When a `max` selection-count option is configured (> 0), IDs beyond it
	 * are truncated — same pattern as {@see Repeater::sanitize()}'s row-count
	 * `max`. `min` is deliberately NOT enforced here — same reasoning
	 * `Repeater` documents for its own `min`: fabricating selections to
	 * satisfy a minimum is a UI/validation-message concern for a later
	 * phase, not something this method should silently invent data for. See
	 * {@see self::to_js_schema()}, where `min` is round-tripped for the
	 * editor to enforce instead.
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return int[] A filtered, `max`-truncated array of valid post IDs.
	 */
	public function sanitize( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$valid = array();

		foreach ( $value as $entry ) {
			$id = absint( $entry );

			if ( $id > 0 && $this->is_valid_post_id( $id ) ) {
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
				'post_type'     => $this->get_allowed_post_types(),
				'taxonomy'      => (array) $this->get_option( 'taxonomy', array() ),
				// ACF's search-box filter toggles ('search', 'post_type',
				// 'taxonomy') shown above the picker. Presentation-only,
				// evaluated by the editor UI, not enforced here.
				'filters'       => (array) $this->get_option( 'filters', array( 'search' ) ),
				'min'           => (int) $this->get_option( 'min', 0 ),
				'max'           => (int) $this->get_option( 'max', 0 ),
				'return_format' => $this->get_option( 'return_format', 'object' ),
			)
		);
	}

	/**
	 * Whether `$id` identifies a real, non-trashed post of an allowed post
	 * type. Identical validation to {@see PostObject::is_valid_post_id()} —
	 * kept local rather than shared, see the cross-cutting note in
	 * `PostObject`/`PageLink` on why these small ID-validation helpers are
	 * duplicated per-class instead of extracted into a shared trait.
	 *
	 * @param int $id Post ID to validate.
	 * @return bool
	 */
	private function is_valid_post_id( int $id ): bool {
		if ( null === get_post( $id ) ) {
			return false;
		}

		$status = get_post_status( $id );

		if ( false === $status || 'trash' === $status ) {
			return false;
		}

		$allowed_post_types = $this->get_allowed_post_types();

		if ( array() !== $allowed_post_types && ! in_array( get_post_type( $id ), $allowed_post_types, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * The configured `post_type` allow-list, normalized to an array. See
	 * {@see PostObject::get_allowed_post_types()}.
	 *
	 * @return string[]
	 */
	private function get_allowed_post_types(): array {
		$post_type = $this->get_option( 'post_type', array() );

		if ( is_array( $post_type ) ) {
			return array_values( array_filter( array_map( 'strval', $post_type ) ) );
		}

		return '' !== (string) $post_type ? array( (string) $post_type ) : array();
	}
}
