<?php
/**
 * User (relational) field type.
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
 * Class User
 *
 * ACF-compatible 'user' field type: stores either a single user ID or an
 * array of user IDs depending on a `multiple` option — same single/array
 * branching shape as {@see PostObject}. Not seen in this project's
 * `acf-json` exports; modelled on ACF Pro's documented User field options
 * (`role`, `allow_null`, `multiple`, `return_format`).
 *
 * @package Growsfields
 */
class User extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'user';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'User';
	}

	/**
	 * Sanitizes a submitted user value.
	 *
	 * A submitted ID cannot be trusted to identify a real user — let alone
	 * one holding an allowed role — so each entry is validated via
	 * {@see self::is_valid_user_id()} rather than merely cast with
	 * `absint()`. This protects against a tampered submission smuggling an
	 * ID for a deleted user, or a real user outside the configured `role`
	 * allow-list, into stored data.
	 *
	 * Branches on the `multiple` option, same pattern as
	 * {@see PostObject::sanitize()}:
	 * - When true, `$value` is expected to be an array; each entry is
	 *   validated independently and invalid entries are simply dropped
	 *   rather than rejecting the whole submission. Non-array input becomes
	 *   an empty array.
	 * - When false, the submitted value must resolve to a single valid user
	 *   ID, or the field's default value is returned instead.
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return int[]|int A filtered array of valid user IDs (when `multiple`),
	 *                     or a single valid user ID / the default value.
	 */
	public function sanitize( $value ) {
		if ( $this->get_option( 'multiple', false ) ) {
			if ( ! is_array( $value ) ) {
				return array();
			}

			$valid = array();

			foreach ( $value as $entry ) {
				$id = absint( $entry );

				if ( $id > 0 && $this->is_valid_user_id( $id ) ) {
					$valid[] = $id;
				}
			}

			return $valid;
		}

		$id = absint( $value );

		if ( $id > 0 && $this->is_valid_user_id( $id ) ) {
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
				'role'          => $this->get_allowed_roles(),
				'multiple'      => (bool) $this->get_option( 'multiple', false ),
				'allow_null'    => (bool) $this->get_option( 'allow_null', false ),
				'return_format' => $this->get_option( 'return_format', 'array' ),
			)
		);
	}

	/**
	 * Whether `$id` identifies a real user and, when a `role` allow-list is
	 * configured, that the user holds at least one of the allowed roles.
	 *
	 * `get_userdata( $id )` returns `false` for a non-existent user ID; only
	 * a real `WP_User` result is accepted. When `role` is configured (a
	 * single role slug or an array of allowed slugs), the user's own
	 * `roles` array must intersect it — a real, existing user who simply
	 * does not hold any of the allowed roles is not a valid selection for
	 * this field. An empty allow-list means "any role is allowed", not "no
	 * role is allowed".
	 *
	 * @param int $id User ID to validate.
	 * @return bool
	 */
	protected function is_valid_user_id( int $id ): bool {
		$user = get_userdata( $id );

		if ( false === $user ) {
			return false;
		}

		$allowed_roles = $this->get_allowed_roles();

		if ( array() === $allowed_roles ) {
			return true;
		}

		return array() !== array_intersect( $allowed_roles, (array) $user->roles );
	}

	/**
	 * The configured `role` allow-list, normalized to an array.
	 *
	 * ACF's own `role` option accepts either a single role slug (string) or
	 * a list of slugs; this normalizes both shapes to an array, same pattern
	 * as {@see PostObject::get_allowed_post_types()}. An empty array means
	 * "any role is allowed".
	 *
	 * @return string[]
	 */
	protected function get_allowed_roles(): array {
		$role = $this->get_option( 'role', array() );

		if ( is_array( $role ) ) {
			return array_values( array_filter( array_map( 'strval', $role ) ) );
		}

		return '' !== (string) $role ? array( (string) $role ) : array();
	}
}
