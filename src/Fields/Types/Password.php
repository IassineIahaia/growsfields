<?php
/**
 * Password field type.
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
 * Class Password
 *
 * ACF-compatible 'password' field type: a masked (`type="password"`) text
 * input. Not seen in this project's `acf-json` exports; modelled on ACF
 * Pro's documented Password field options (`placeholder`, `prepend`,
 * `append`).
 *
 * IMPORTANT — this class provides no cryptographic security whatsoever. ACF
 * Pro's own Password field is, despite the name, nothing more than a text
 * input whose value is masked in the browser UI (e.g. for storing an API
 * key or a simple page-gate password); it does not hash, encrypt, or
 * otherwise protect the stored value, and neither does this class. The
 * value round-trips through `sanitize()` as plain text, exactly like Text.
 * Do not use this field type to store a real WordPress user credential or
 * anything that needs to be hashed at rest — a caller with that requirement
 * must hash/encrypt the value itself before persisting it; this field type
 * is not that boundary.
 *
 * @package Growsfields
 */
class Password extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'password';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Password';
	}

	/**
	 * Sanitizes a submitted password value.
	 *
	 * Runs the raw value through `sanitize_text_field()` (strips tags,
	 * extra whitespace, and control characters), exactly like Text — this
	 * is plain-text sanitization only, not hashing or encryption; see the
	 * class docblock.
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return string Sanitized, plain-text value.
	 */
	public function sanitize( $value ) {
		return sanitize_text_field( (string) $value );
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
