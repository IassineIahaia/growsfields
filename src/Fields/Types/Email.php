<?php
/**
 * Email field type.
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
 * Class Email
 *
 * ACF-compatible 'email' field type: stores a single email address string.
 * Not seen in this project's `acf-json` exports; modelled on ACF Pro's
 * documented Email field options (`placeholder`, `prepend`, `append`).
 *
 * @package Growsfields
 */
class Email extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'email';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Email';
	}

	/**
	 * Sanitizes a submitted email value.
	 *
	 * `sanitize_email()` strips characters that cannot legally appear in an
	 * email address, but it does not guarantee the result is a *valid*,
	 * well-formed address — it can still return a string that merely looks
	 * email-shaped (e.g. missing an '@', or an empty string once invalid
	 * characters are stripped). This method additionally validates the
	 * sanitized result with `is_email()` and falls back to
	 * {@see self::default_value()} (empty string) when it fails, so a
	 * malformed submission can never end up persisted as an "email".
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return string A validated email address, or the default value.
	 */
	public function sanitize( $value ) {
		$email = sanitize_email( (string) $value );

		if ( '' === $email || ! is_email( $email ) ) {
			return $this->default_value();
		}

		return $email;
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
				'placeholder' => $this->get_option( 'placeholder', '' ),
			)
		);
	}
}
