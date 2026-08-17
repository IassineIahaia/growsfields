<?php
/**
 * oEmbed (embeddable URL) field type.
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
 * Class OEmbed
 *
 * ACF-compatible 'oembed' field type: stores a single embeddable URL string
 * (e.g. a YouTube/Vimeo/Twitter link) — ACF's own oEmbed field literally
 * just stores the submitted URL, nothing more. The actual embed HTML
 * generation/caching is a WordPress core `wp_oembed_get()` concern handled
 * at render time, not something `sanitize()` should attempt: fetching a URL
 * to confirm it actually embeds successfully would mean an HTTP request
 * inside `sanitize()`, which is inappropriate for a value-sanitization
 * boundary (slow, network-dependent, and not something a save request should
 * block on). Storage-wise this is nearly identical to {@see URL}, but ACF
 * treats oEmbed as a distinct field type with a different editor UI (a live
 * embed preview as the URL is typed), so it stays a distinct class here too
 * rather than an alias. Not seen in this project's `acf-json` exports;
 * modelled on ACF Pro's documented oEmbed field options (`width`, `height`).
 *
 * @package Growsfields
 */
class OEmbed extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'oembed';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'oEmbed';
	}

	/**
	 * Sanitizes a submitted oEmbed value.
	 *
	 * `esc_url_raw()` strips characters and schemes (e.g. `javascript:`)
	 * that are not valid/safe in a URL meant for storage — same reasoning
	 * {@see URL::sanitize()} documents. No attempt is made to verify the URL
	 * actually resolves to an embeddable resource; see the class docblock
	 * for why that check does not belong in `sanitize()`.
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
				// Embed preview dimensions passed to wp_oembed_get() at
				// render time. Pass-through only, not evaluated here.
				'width'  => $this->get_option( 'width', '' ),
				'height' => $this->get_option( 'height', '' ),
			)
		);
	}
}
