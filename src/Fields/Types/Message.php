<?php
/**
 * Message (instructional text) field type.
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
 * Class Message
 *
 * ACF-compatible 'message' field type: a purely informational block of
 * author-authored text/HTML shown in the editor UI — like {@see Tab}, it
 * does not itself represent stored data. Not seen in this project's
 * `acf-json` exports; modelled on ACF Pro's documented Message field
 * options (`message`, `new_lines`, `esc_html`).
 *
 * @package Growsfields
 */
class Message extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'message';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Message';
	}

	/**
	 * Message fields never store a value — there is nothing to sanitize.
	 *
	 * Same reasoning as {@see Tab::sanitize()}: ACF's Message (and this
	 * class) is instructional content consumed only by the editor UI,
	 * never persisted as field data. Returning `null` unconditionally,
	 * regardless of `$value`, makes that explicit: whatever is "submitted"
	 * for a message field (normally nothing at all) is discarded.
	 *
	 * @param mixed $value Raw value as submitted (ignored).
	 * @return null Always null — Message fields carry no stored value.
	 */
	public function sanitize( $value ) {
		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function default_value() {
		return null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * The `message` option is the field author's own instructional
	 * content, which should ultimately be rendered through
	 * `wp_kses_post()` wherever it is output as HTML. That sanitization
	 * does not happen here: `to_js_schema()` is a describe-the-field
	 * method, not this class's security boundary (unlike `sanitize()`,
	 * which handles untrusted end-user submissions) — the actual "who
	 * sanitizes admin-authored field-group content before it reaches the
	 * browser" responsibility belongs to Phase 3's `FieldGroupLoader`,
	 * which loads and will own escaping for every author-supplied string in
	 * a field group's JSON, not just this one field type's `message` key.
	 * `message` is passed through as-is here, same as every other
	 * pass-through option on other field types.
	 */
	public function to_js_schema(): array {
		return array_merge(
			$this->base_js_schema(),
			array(
				'message'   => $this->get_option( 'message', '' ),
				// wpautop|br|'' — ACF's "New Lines" option controlling how
				// line breaks in `message` are converted to HTML. UI/render
				// concern only.
				'new_lines' => $this->get_option( 'new_lines', 'wpautop' ),
			)
		);
	}
}
