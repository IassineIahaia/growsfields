<?php
/**
 * Checkbox (multi-select) field type.
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
 * Class Checkbox
 *
 * ACF-compatible 'checkbox' field type: a group of checkboxes over a fixed
 * set of `choices` (value => label), storing an array of the selected keys
 * — the multi-value counterpart to {@see Radio}, structurally the same as
 * {@see Select} with `multiple` forced on. Not seen in this project's
 * `acf-json` exports; modelled on ACF Pro's documented Checkbox field
 * options (`choices`, `default_value`, `allow_custom`, `save_custom`,
 * `toggle`, `layout`).
 *
 * `save_custom` (ACF Pro's option to additionally persist custom values
 * back into the field's own `choices` list, so they appear as pre-defined
 * options for future edits) is a field-*definition* mutation, not a
 * per-submission sanitize concern — it belongs to Phase 6-B's field group
 * builder/save flow, not this class, so it is intentionally omitted here.
 *
 * @package Growsfields
 */
class Checkbox extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'checkbox';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Checkbox';
	}

	/**
	 * Sanitizes a submitted checkbox value.
	 *
	 * `$value` is expected to be an array of selected keys; non-array input
	 * is not coerced (e.g. into a single-entry array) and instead becomes
	 * an empty array, since a checkbox group submitting a scalar is not a
	 * shape this field ever legitimately produces.
	 *
	 * When `allow_custom` is off (the default), every entry is validated
	 * against the field's own `choices` keys — same reasoning as
	 * {@see Select::sanitize()}: `choices` ships to the browser, so a
	 * submission cannot be trusted to only contain *offered* values, and
	 * invalid entries are dropped individually rather than rejecting the
	 * whole submission.
	 *
	 * When `allow_custom` is on (ACF Pro's "Allow Custom Values" option,
	 * which lets an editor type a value not in the pre-defined `choices`),
	 * the `choices`-membership check is skipped entirely and every entry is
	 * instead run through `sanitize_text_field()` — this is a
	 * simplification of ACF Pro's own behaviour (which also offers
	 * `save_custom` to persist new custom values back into the field
	 * definition); here, a custom entry is simply trusted as free text for
	 * storage, with no attempt to auto-register it as a future choice.
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return string[] A filtered array of selected values.
	 */
	public function sanitize( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		if ( $this->get_option( 'allow_custom', false ) ) {
			return array_values(
				array_map(
					static function ( $entry ) {
						return sanitize_text_field( (string) $entry );
					},
					$value
				)
			);
		}

		$choices = $this->get_choices();
		$valid   = array();

		foreach ( $value as $entry ) {
			$entry = sanitize_text_field( (string) $entry );

			if ( array_key_exists( $entry, $choices ) ) {
				$valid[] = $entry;
			}
		}

		return $valid;
	}

	/**
	 * {@inheritDoc}
	 */
	public function default_value() {
		return (array) $this->get_option( 'default_value', array() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function to_js_schema(): array {
		return array_merge(
			$this->base_js_schema(),
			array(
				'choices'      => $this->get_choices(),
				'allow_custom' => (bool) $this->get_option( 'allow_custom', false ),
				// ACF's "select all" convenience checkbox above the group.
				// Presentation only, evaluated by the editor UI.
				'toggle'       => (bool) $this->get_option( 'toggle', false ),
				'layout'       => $this->get_option( 'layout', 'vertical' ),
			)
		);
	}

	/**
	 * The registered `value => label` choices for this field instance.
	 *
	 * @return array<string, string>
	 */
	protected function get_choices(): array {
		return (array) $this->get_option( 'choices', array() );
	}
}
