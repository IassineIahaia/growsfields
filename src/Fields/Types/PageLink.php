<?php
/**
 * Page Link (relational, with optional external URL) field type.
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
 * Class PageLink
 *
 * ACF-compatible 'page_link' field type: same post-ID storage and validation
 * as {@see PostObject} (existence, non-trashed, optional `post_type`
 * allow-list, `multiple` single/array branching), plus one behavioural
 * difference this class adds on top: an `allow_external` option. When on, a
 * submitted value that does not resolve to a valid post ID but does look
 * like a URL is accepted as a raw external URL string instead of being
 * rejected — so the stored value becomes `int|string` (a post ID, or a raw
 * URL) rather than always an int.
 *
 * NOTE on ACF Pro parity: ACF Pro's own Page Link field does not have a
 * generic "type any URL" option under this name. Its actual mechanism for a
 * non-numeric stored value is `allow_archives` ("Allow Archives URLs"),
 * which is narrower and more specific — it adds each configured post type's
 * *archive* to the picker's choices and, when one is selected, stores a
 * special string (e.g. `post_type_archive_{post_type}`), not an arbitrary
 * URL typed by the editor. `allow_external` here is a deliberate
 * generalization of that idea per this batch's design brief, not a 1:1
 * mirror of ACF's own option name or behaviour — documented explicitly so
 * this is not mistaken for an ACF-verified option.
 *
 * Not extending {@see PostObject}: the URL-fallback branch is a genuine
 * behavioural difference (a different stored value *type*, not just a
 * different label or a presentation-only option like {@see ButtonGroup}'s
 * relationship to {@see Radio}), and grafting it onto `PostObject::sanitize()`
 * would mean either overriding the whole method anyway (leaving nothing to
 * usefully inherit) or adding an `allow_external` branch to `PostObject`
 * itself, which does not have — and should not gain — that concept. This
 * class duplicates `PostObject`'s small ID-validation/allow-list helpers
 * rather than inheriting them; the duplication is a handful of lines, and
 * keeping it local avoids coupling two otherwise-independent field types
 * through a base class neither is conceptually a specialisation of. Not
 * seen in this project's `acf-json` exports; modelled on ACF Pro's
 * documented Page Link field options (`post_type`, `taxonomy`,
 * `allow_archives`, `allow_null`, `multiple`).
 *
 * @package Growsfields
 */
class PageLink extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'page_link';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Page Link';
	}

	/**
	 * Sanitizes a submitted page link value.
	 *
	 * Each entry is resolved in order: first as a post ID (existence,
	 * trashed status, and an optional `post_type` allow-list — identical
	 * rules to {@see PostObject::is_valid_post_id()}); then, only when
	 * `allow_external` is on and the ID check failed, as a raw external URL
	 * via {@see self::resolve_external_url()}. When `allow_external` is off
	 * (the default), behaviour is identical to `PostObject`: entries that
	 * are not a valid post ID are dropped (multiple) or replaced with the
	 * default value (single) — no URL fallback ever runs.
	 *
	 * Branches on the `multiple` option, same pattern as
	 * {@see PostObject::sanitize()}:
	 * - When true, `$value` is expected to be an array; each entry is
	 *   resolved independently and unresolvable entries are dropped rather
	 *   than rejecting the whole submission. Non-array input becomes an
	 *   empty array.
	 * - When false, the submitted value must resolve to a single valid post
	 *   ID or (when allowed) external URL, or the field's default value is
	 *   returned instead.
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return array<int, int|string>|int|string A filtered array of valid
	 *                                             post IDs and/or external
	 *                                             URLs (when `multiple`), or
	 *                                             a single resolved value /
	 *                                             the default value.
	 */
	public function sanitize( $value ) {
		if ( $this->get_option( 'multiple', false ) ) {
			if ( ! is_array( $value ) ) {
				return array();
			}

			$valid = array();

			foreach ( $value as $entry ) {
				$resolved = $this->resolve_entry( $entry );

				if ( null !== $resolved ) {
					$valid[] = $resolved;
				}
			}

			return $valid;
		}

		$resolved = $this->resolve_entry( $value );

		return null !== $resolved ? $resolved : $this->default_value();
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
				'post_type'      => $this->get_allowed_post_types(),
				'taxonomy'       => (array) $this->get_option( 'taxonomy', array() ),
				'multiple'       => (bool) $this->get_option( 'multiple', false ),
				'allow_null'     => (bool) $this->get_option( 'allow_null', false ),
				// Not an ACF-verified option name — see class docblock.
				'allow_external' => (bool) $this->get_option( 'allow_external', false ),
			)
		);
	}

	/**
	 * Resolves one submitted entry to either a valid post ID, an accepted
	 * external URL, or `null` when neither applies.
	 *
	 * @param mixed $entry Raw entry as submitted.
	 * @return int|string|null
	 */
	private function resolve_entry( $entry ) {
		$id = absint( $entry );

		if ( $id > 0 && $this->is_valid_post_id( $id ) ) {
			return $id;
		}

		if ( $this->get_option( 'allow_external', false ) ) {
			return $this->resolve_external_url( $entry );
		}

		return null;
	}

	/**
	 * Resolves a submitted entry to a sanitized external URL, or `null` if
	 * it does not look like one.
	 *
	 * A bare `esc_url_raw()` call would happily "sanitize" arbitrary garbage
	 * text down to an empty or mangled string, which would then look like a
	 * legitimately stored empty/placeholder value rather than a rejected
	 * one. `filter_var( ..., FILTER_VALIDATE_URL )` is used first as a
	 * shape check (does this look like a URL at all — has a scheme and
	 * host), and only a value that passes is run through `esc_url_raw()`
	 * for storage.
	 *
	 * @param mixed $entry Raw entry as submitted.
	 * @return string|null
	 */
	private function resolve_external_url( $entry ): ?string {
		$url = (string) $entry;

		if ( '' === $url || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return null;
		}

		$sanitized = esc_url_raw( $url );

		return '' !== $sanitized ? $sanitized : null;
	}

	/**
	 * Whether `$id` identifies a real, non-trashed post of an allowed post
	 * type. Identical validation to {@see PostObject::is_valid_post_id()} —
	 * see that method's docblock and this class's own docblock for why the
	 * few lines are duplicated rather than shared via inheritance.
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
