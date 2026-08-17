<?php
/**
 * Tab (visual grouping) field type.
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
 * Class Tab
 *
 * ACF-compatible 'tab' field type: a purely visual marker that starts a
 * new tab group in the editor UI for the fields that follow it in the same
 * field group — it does not itself represent stored data. Kept as slug
 * `tab` matching this project's `acf-json` exports (e.g.
 * `field_695cb7930ba3e` / "Content", which carries `placement: "top"` and
 * `endpoint: 0`) for a smooth Phase 3 migration.
 *
 * @package Growsfields
 */
class Tab extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'tab';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Tab';
	}

	/**
	 * Tab fields never store a value — there is nothing to sanitize.
	 *
	 * A naive implementer copying another field type's `sanitize()` could
	 * easily assume Tab needs the same treatment as, say, Text; it does
	 * not, because ACF's Tab (and this class) is a layout marker consumed
	 * only by the editor UI, never persisted as field data. Returning
	 * `null` unconditionally, regardless of `$value`, makes that explicit:
	 * whatever is "submitted" for a tab field (normally nothing at all) is
	 * discarded.
	 *
	 * @param mixed $value Raw value as submitted (ignored).
	 * @return null Always null — Tab fields carry no stored value.
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
	 */
	public function to_js_schema(): array {
		return array_merge(
			$this->base_js_schema(),
			array(
				'placement' => $this->get_option( 'placement', 'top' ),
				'endpoint'  => (bool) $this->get_option( 'endpoint', false ),
			)
		);
	}
}
