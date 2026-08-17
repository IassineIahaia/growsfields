<?php
/**
 * URL field type.
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
 * Class URL
 *
 * ACF-compatible 'url' field type: stores a single URL string. Not seen in
 * this project's `acf-json` exports; modelled on ACF Pro's documented URL
 * field options (`placeholder`).
 *
 * @package Growsfields
 */
class URL extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'url';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Link URL';
	}

	/**
	 * Sanitizes a submitted URL value.
	 *
	 * `esc_url_raw()` strips characters and schemes (e.g. `javascript:`)
	 * that are not valid/safe in a URL meant for storage, so a submitted
	 * value can never end up persisted as an executable/unsafe string.
	 * Unlike Email's `is_email()` step, ACF's own URL field does not
	 * additionally require the result to resolve or be "well-formed" beyond
	 * what `esc_url_raw()` already guarantees, so no further validation is
	 * layered on top here.
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return string A sanitized URL string, or an empty string.
	 */
	public function sanitize( $value ) {
		return esc_url_raw( (string) $value );
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
