<?php
/**
 * Post Object (relational) field type.
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
 * Class PostObject
 *
 * ACF-compatible 'post_object' field type: stores either a single post ID or
 * an array of post IDs depending on a `multiple` option — same single/array
 * branching shape as {@see Select}. Not seen in this project's `acf-json`
 * exports; modelled on ACF Pro's documented Post Object field options
 * (`post_type`, `taxonomy`, `allow_null`, `multiple`, `return_format`, `ui`).
 *
 * `return_format` (object|id) only affects how a *consumer* of the field
 * (block render, REST response) presents the value — same reasoning
 * {@see Image} documents for its own `return_format` — the stored value is
 * always the post ID(s) regardless of that option. `taxonomy` (ACF's option
 * to additionally filter the picker's choices by taxonomy term) is a
 * query-time UI concern for whatever builds the picker's results, not a
 * server-side validation rule this class can meaningfully re-check from an ID
 * alone, so it is round-tripped into {@see self::to_js_schema()} only, not
 * enforced in {@see self::sanitize()} — same treatment `ui`/`ajax` get in
 * {@see Select}.
 *
 * @package Growsfields
 */
class PostObject extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'post_object';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Post Object';
	}

	/**
	 * Sanitizes a submitted post object value.
	 *
	 * A submitted ID cannot be trusted to identify a real, non-trashed post —
	 * let alone one of an allowed post type — so each entry is validated via
	 * {@see self::is_valid_post_id()} (existence, trashed status, and an
	 * optional `post_type` allow-list) rather than merely cast with
	 * `absint()`. This protects against a tampered submission smuggling an ID
	 * for a deleted, trashed, or wrong-post-type post into stored data.
	 *
	 * Branches on the `multiple` option, same pattern as
	 * {@see Select::sanitize()}:
	 * - When true, `$value` is expected to be an array; each entry is
	 *   validated independently and invalid entries are simply dropped
	 *   rather than rejecting the whole submission. Non-array input becomes
	 *   an empty array.
	 * - When false, the submitted value must resolve to a single valid post
	 *   ID, or the field's default value is returned instead.
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return int[]|int A filtered array of valid post IDs (when `multiple`),
	 *                     or a single valid post ID / the default value.
	 */
	public function sanitize( $value ) {
		if ( $this->get_option( 'multiple', false ) ) {
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

			return $valid;
		}

		$id = absint( $value );

		if ( $id > 0 && $this->is_valid_post_id( $id ) ) {
			return $id;
		}

		return $this->default_value();
	}

	/**
	 * {@inheritDoc}
	 */
	public function default_value() {
		return $this->get_option( 'default_value', $this->get_option( 'multiple', false ) ? array() : 0 );
	}

	/**
	 * {@inheritDoc}
	 */
	public function to_js_schema(): array {
		return array_merge(
			$this->base_js_schema(),
			array(
				'post_type'     => $this->get_allowed_post_types(),
				// ACF's "Filter by Taxonomy" option — narrows the picker's
				// results by term. Query-time UI concern only, see class
				// docblock; not enforced in sanitize().
				'taxonomy'      => (array) $this->get_option( 'taxonomy', array() ),
				'multiple'      => (bool) $this->get_option( 'multiple', false ),
				'allow_null'    => (bool) $this->get_option( 'allow_null', false ),
				'return_format' => $this->get_option( 'return_format', 'object' ),
				// ACF's "Stylized UI" toggle. Presentation only.
				'ui'            => (bool) $this->get_option( 'ui', true ),
			)
		);
	}

	/**
	 * Whether `$id` identifies a real, non-trashed post of an allowed post
	 * type.
	 *
	 * `get_post( $id )` returning `null` covers non-existent/deleted IDs.
	 * `get_post_status( $id )` being `false` or `'trash'` additionally
	 * excludes posts that technically still exist as rows but are not
	 * legitimate targets for a relational field to point at. When a
	 * `post_type` option is configured (a single slug or an array of allowed
	 * slugs), `get_post_type( $id )` must additionally be in that allow-list;
	 * when it is not configured, any real post type is accepted.
	 *
	 * @param int $id Post ID to validate.
	 * @return bool
	 */
	protected function is_valid_post_id( int $id ): bool {
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
	 * The configured `post_type` allow-list, normalized to an array.
	 *
	 * ACF's own `post_type` option accepts either a single slug (string) or
	 * a list of slugs; this normalizes both shapes to an array so
	 * {@see self::is_valid_post_id()} and {@see self::to_js_schema()} don't
	 * each need to repeat the `is_array()` check. An empty array means "any
	 * post type is allowed", not "no post type is allowed".
	 *
	 * @return string[]
	 */
	protected function get_allowed_post_types(): array {
		$post_type = $this->get_option( 'post_type', array() );

		if ( is_array( $post_type ) ) {
			return array_values( array_filter( array_map( 'strval', $post_type ) ) );
		}

		return '' !== (string) $post_type ? array( (string) $post_type ) : array();
	}
}
