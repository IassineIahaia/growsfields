<?php
/**
 * Taxonomy (term select) field type.
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
 * Class Taxonomy
 *
 * ACF-compatible 'taxonomy' field type: stores either a single term ID or an
 * array of term IDs. ACF Pro's own field has a `field_type` option
 * (`checkbox` | `multi_select` | `select` | `radio`) that picks the editor
 * widget, where `checkbox`/`multi_select` imply a multi-value stored shape
 * and `select`/`radio` imply single-value — rather than re-deriving
 * single/multi from the widget choice on every read, this class exposes a
 * direct `multiple` bool option (same shape as {@see PostObject}/
 * {@see Select}) and only falls back to deriving it from `field_type` when
 * `multiple` itself is not explicitly set — see {@see self::is_multiple()}.
 * `field_type` is still carried through {@see self::to_js_schema()}
 * unchanged, since the editor UI needs it to know which widget to render.
 * Not seen in this project's `acf-json` exports; modelled on ACF Pro's
 * documented Taxonomy field options (`taxonomy`, `field_type`, `allow_null`,
 * `add_term`, `save_terms`, `load_terms`, `return_format`, `multiple`).
 *
 * `add_term` (ACF Pro's "allow new terms to be created inline" option) and
 * `save_terms`/`load_terms` (whether selections also sync to the post's
 * native taxonomy relationships, vs. living only in field-meta storage) are
 * save-flow/integration concerns for a later phase's post-save orchestration,
 * not something a value-level `sanitize()` can act on from an ID alone —
 * they are round-tripped into `to_js_schema()` only, same treatment
 * `Repeater::to_js_schema()` gives `sub_fields`-adjacent config it doesn't
 * itself evaluate.
 *
 * @package Growsfields
 */
class Taxonomy extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'taxonomy';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Taxonomy';
	}

	/**
	 * Sanitizes a submitted taxonomy value.
	 *
	 * A submitted ID cannot be trusted to identify a real term — let alone
	 * one belonging to this field's configured taxonomy — so each entry is
	 * validated via {@see self::is_valid_term_id()} rather than merely cast
	 * with `absint()`. This protects against a tampered submission smuggling
	 * an ID for a deleted term, or a real term from an unrelated taxonomy,
	 * into stored data.
	 *
	 * Branches on {@see self::is_multiple()}, same pattern as
	 * {@see Select::sanitize()}:
	 * - When true, `$value` is expected to be an array; each entry is
	 *   validated independently and invalid entries are simply dropped
	 *   rather than rejecting the whole submission. Non-array input becomes
	 *   an empty array.
	 * - When false, the submitted value must resolve to a single valid term
	 *   ID, or the field's default value is returned instead.
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return int[]|int A filtered array of valid term IDs (when multi), or
	 *                     a single valid term ID / the default value.
	 */
	public function sanitize( $value ) {
		if ( $this->is_multiple() ) {
			if ( ! is_array( $value ) ) {
				return array();
			}

			$valid = array();

			foreach ( $value as $entry ) {
				$id = absint( $entry );

				if ( $id > 0 && $this->is_valid_term_id( $id ) ) {
					$valid[] = $id;
				}
			}

			return $valid;
		}

		$id = absint( $value );

		if ( $id > 0 && $this->is_valid_term_id( $id ) ) {
			return $id;
		}

		return $this->default_value();
	}

	/**
	 * {@inheritDoc}
	 */
	public function default_value() {
		return $this->get_option( 'default_value', $this->is_multiple() ? array() : 0 );
	}

	/**
	 * {@inheritDoc}
	 */
	public function to_js_schema(): array {
		return array_merge(
			$this->base_js_schema(),
			array(
				'taxonomy'       => $this->get_option( 'taxonomy', 'category' ),
				// checkbox|multi_select|select|radio — picks the editor
				// widget; see class docblock for its relation to `multiple`.
				'field_type'     => $this->get_option( 'field_type', 'checkbox' ),
				'multiple'       => $this->is_multiple(),
				'allow_null'     => (bool) $this->get_option( 'allow_null', false ),
				// Save-flow/integration concerns, not sanitize() concerns —
				// see class docblock.
				'add_term'       => (bool) $this->get_option( 'add_term', true ),
				'save_terms'     => (bool) $this->get_option( 'save_terms', false ),
				'load_terms'     => (bool) $this->get_option( 'load_terms', false ),
				'return_format'  => $this->get_option( 'return_format', 'object' ),
			)
		);
	}

	/**
	 * Whether this field instance stores multiple term IDs.
	 *
	 * Honours an explicit `multiple` option first; when it is not set at
	 * all, falls back to deriving it from `field_type` — `checkbox` and
	 * `multi_select` are ACF's own multi-value widgets, `select` and `radio`
	 * are single-value. `checkbox` is both ACF's own default `field_type`
	 * and the default assumed here, so an unconfigured field defaults to
	 * multi-value, matching ACF Pro's own out-of-the-box behaviour.
	 *
	 * @return bool
	 */
	protected function is_multiple(): bool {
		$multiple = $this->get_option( 'multiple', null );

		if ( null !== $multiple ) {
			return (bool) $multiple;
		}

		return in_array( $this->get_option( 'field_type', 'checkbox' ), array( 'checkbox', 'multi_select' ), true );
	}

	/**
	 * Whether `$id` identifies a real term, and — when a `taxonomy` option
	 * is configured — that the term belongs to that taxonomy.
	 *
	 * `get_term( $id )` returns a `WP_Term` on success, or `null`/`WP_Error`
	 * for a non-existent ID or invalid input; only the `WP_Term` case is
	 * accepted. When a `taxonomy` is configured, the term's own `taxonomy`
	 * property must match it exactly — a real term ID that happens to belong
	 * to a different taxonomy (e.g. a `post_tag` term submitted against a
	 * field configured for `category`) is not a valid selection for this
	 * field.
	 *
	 * @param int $id Term ID to validate.
	 * @return bool
	 */
	protected function is_valid_term_id( int $id ): bool {
		$term = get_term( $id );

		if ( ! ( $term instanceof \WP_Term ) ) {
			return false;
		}

		$taxonomy = (string) $this->get_option( 'taxonomy', '' );

		if ( '' !== $taxonomy && $term->taxonomy !== $taxonomy ) {
			return false;
		}

		return true;
	}
}
