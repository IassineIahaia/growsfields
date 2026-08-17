<?php
/**
 * Radio button field type.
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
 * Class Radio
 *
 * ACF-compatible 'radio' field type: a single choice out of a fixed set of
 * `choices` (value => label). Kept as slug `radio` matching this project's
 * `acf-json` exports (e.g. `field_6488bc50051f0` / "Kies overzicht", whose
 * `choices` are `{"none": "Geen", "project": "Projecten"}`) for a smooth
 * Phase 3 migration.
 *
 * @package Growsfields
 */
class Radio extends FieldType {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'radio';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Radio Button';
	}

	/**
	 * Sanitizes a submitted radio value.
	 *
	 * `choices` is authored in the field group config and, in principle,
	 * ships to the browser for rendering the radio inputs — a client
	 * cannot be trusted to only ever submit one of the *offered* values, so
	 * this method re-validates the submission against the field's own
	 * `choices` keys server-side rather than trusting that a submitted
	 * value came from an unmodified radio group. A value that is not a
	 * registered choice key falls back to the field's default value rather
	 * than being stored as-is.
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return string A valid `choices` key, or the default value.
	 */
	public function sanitize( $value ) {
		$choices = $this->get_choices();
		$value   = sanitize_text_field( (string) $value );

		if ( array_key_exists( $value, $choices ) ) {
			return $value;
		}

		return $this->default_value();
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
				'choices' => $this->get_choices(),
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
