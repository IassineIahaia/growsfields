<?php
/**
 * Button group field type.
 *
 * @package Growsfields
 */

namespace Growsfields\Fields\Types;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ButtonGroup
 *
 * ACF-compatible 'button_group' field type: a single choice out of a fixed
 * set of `choices`, rendered as a row of buttons instead of radio inputs.
 * Functionally this is identical to {@see Radio} — same stored-value shape
 * (a single `choices` key) and identical server-side validation rules — ACF
 * itself treats Button Group as "Radio Button with different UI", not a
 * field type with different semantics. Rather than duplicate Radio's
 * sanitize()/default_value() logic, this class extends {@see Radio} and
 * overrides only `get_type()`/`get_type_label()` (identity) plus
 * `to_js_schema()` (to add the `layout` option Button Group has and Radio
 * does not) — inheritance is the cleaner choice here specifically because
 * there genuinely is no behavioural difference to express, only an
 * identity and a presentation option. Not seen in this project's `acf-json`
 * exports; modelled on ACF Pro's documented Button Group field options
 * (`choices`, `default_value`, `allow_null`, `layout`).
 *
 * @package Growsfields
 */
class ButtonGroup extends Radio {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'button_group';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Button Group';
	}

	/**
	 * {@inheritDoc}
	 */
	public function to_js_schema(): array {
		return array_merge(
			parent::to_js_schema(),
			array(
				// horizontal|vertical — presentation only.
				'layout' => $this->get_option( 'layout', 'horizontal' ),
			)
		);
	}
}
