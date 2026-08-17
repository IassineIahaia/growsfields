<?php
/**
 * Clone (re-embeds another field/group's already-resolved sub-fields) field
 * type.
 *
 * @package Growsfields
 */

namespace Growsfields\Fields\Types;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Clone_
 *
 * ACF-compatible 'clone' field type. Named `Clone_` (trailing underscore)
 * rather than `Clone` because `clone` is a reserved word in PHP and cannot
 * be used as a class name; {@see self::get_type()} still returns the real
 * slug `'clone'`.
 *
 * --- SCOPE BOUNDARY (read this before touching this class in Phase 3) ---
 *
 * ACF Pro's real Clone field works by referencing OTHER field groups/fields
 * by *key* — its own config carries a `clone` option listing field/group
 * keys, e.g. `array( 'group_abc123', 'field_def456' )` — and resolving
 * those keys into concrete field definitions at load/render time. That
 * resolution requires reading field-group JSON by key, which is the job of
 * `FieldGroupLoader` (Phase 3), and does not exist yet in this codebase.
 * This class deliberately does NOT build that resolver, does NOT stub a
 * fake one, and does NOT invent any other key-lookup mechanism.
 *
 * Instead, this class operates purely at the `FieldType` engine level
 * (Phase 2): it assumes that BY THE TIME a `Clone_` instance is
 * constructed, something else has already turned whatever key-based
 * `clone` reference config existed in the original field-group JSON into a
 * concrete, resolved `sub_fields` array — the exact same shape
 * {@see Repeater} and {@see Group} take (`array( array( 'type' => ..., 'name' => ..., ... ), ... )`),
 * exactly as if those fields had been hand-authored inline on this field.
 * This mirrors how ACF itself actually works internally: key resolution
 * happens once, at field-group-load time, not inside the field type's own
 * runtime sanitize/schema logic.
 *
 * Phase 3's `FieldGroupLoader` is therefore responsible for producing a
 * fully-resolved `sub_fields` array (reading the referenced field group(s)
 * or field(s) by key, copying their definitions in) BEFORE handing config
 * to `FieldTypeRegistry::make( 'clone', $config )`. This class has no
 * awareness of, and no need to be changed for, whatever key format or
 * lookup strategy Phase 3 ends up using — its contract is only ever
 * "`sub_fields` is already a valid, resolved sub-field config array".
 *
 * --- RELATIONSHIP TO Group ---
 *
 * Once `sub_fields` are given, a Clone field's only real behavioural
 * difference from {@see Group} is the `display` option (`'group'` or
 * `'seamless'`, ACF's real option name/values) — and that option is a
 * *wrapping* choice applied AFTER computing the same underlying
 * `sub_field_name => value` map Group already knows how to compute
 * (validating/building sub-field `FieldType`s, per-key sanitize-or-default,
 * dropping unconfigured keys). Rather than duplicating that computation,
 * `Clone_` extends {@see Group} and reuses its protected
 * `sanitize_sub_field_values()` / `sub_fields_schema()` helpers (and
 * inherits its constructor and `default_value()` unchanged), only adding
 * the wrap/unwrap step on top:
 *
 * - `'group'` (the default): behaves exactly like Group for the
 *   *computation*, then additionally nests the resulting map under this
 *   field's own name — `array( $this->get_name() => array( sub_field_name => value, ... ) )`
 *   — so this field's own single slot in a stored record is a nested
 *   object, same as a real Group field's slot would be.
 * - `'seamless'`: the flat `sub_field_name => value` map is returned as-is,
 *   with no wrapping — ACF's real distinguishing behaviour, where a
 *   seamless clone's fields appear to "merge into" the parent structure
 *   instead of nesting under the clone field's own name.
 *
 * This choice was evaluated the same way {@see PageLink}'s relationship to
 * {@see PostObject} was: extending only makes sense when the subclass is a
 * genuine behavioural specialisation, not a coincidental resemblance.
 * Unlike PageLink/PostObject (where the different branch changed the
 * stored value's *type*, and grafting it onto the parent would have left
 * nothing to usefully inherit), Clone's `display` branch changes only how
 * the *same already-computed* map gets wrapped — a thin, mechanical step on
 * top of identical sub-field validation/build/sanitize logic. Duplicating
 * that logic here instead of inheriting it would mean keeping two copies
 * of the sub-field loop in sync for no benefit, so `Clone_ extends Group`
 * was the right call.
 *
 * Note that full ACF "seamless" behaviour — a cloned sub-field's value
 * becoming its OWN independent top-level meta key on the parent record,
 * with no reference to this field's own name anywhere in storage — is a
 * placement decision made by whatever assembles a field group's full set of
 * values into a storable record (Phase 3, not built yet). A single
 * `FieldType::sanitize()` call only ever returns "the value for this one
 * field"; it has no visibility into, or control over, how a caller keys
 * that value against everything else in the record. What this class
 * guarantees at the Phase 2 level is that its return value is already
 * shaped correctly for either path: the flat map a seamless-aware Phase 3
 * assembler needs to splice into the parent record, or (wrapped) the value
 * an assembler can key by `$this->get_name()` exactly like it would for any
 * other field, Group included.
 *
 * @package Growsfields
 */
class Clone_ extends Group {

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'clone';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Clone';
	}

	/**
	 * Sanitizes a submitted clone value.
	 *
	 * Computes the same `sub_field_name => value` map {@see Group::sanitize()}
	 * would (delegating each sub-field to its own `FieldType::sanitize()` —
	 * see {@see Group::sanitize_sub_field_values()} for the security
	 * framing, identical here), then applies the `display`-dependent
	 * wrap/unwrap step described in the class docblock.
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return array<string, mixed> `'seamless'`: the flat sub-field map. `'group'`
	 *                                (default): that same map nested one
	 *                                level under `$this->get_name()`.
	 */
	public function sanitize( $value ) {
		$fields = $this->sanitize_sub_field_values( $value );

		if ( 'seamless' === $this->get_display() ) {
			return $fields;
		}

		return array( $this->get_name() => $fields );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Same wrap/unwrap treatment as {@see self::sanitize()}, applied to
	 * {@see Group::default_value()}'s map of each sub-field's own default.
	 */
	public function default_value() {
		$defaults = parent::default_value();

		if ( 'seamless' === $this->get_display() ) {
			return $defaults;
		}

		return array( $this->get_name() => $defaults );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Deliberately does not call `parent::to_js_schema()` — Group's
	 * `layout` option (`block`/`table`/`row`) has no meaning for a Clone
	 * field and would be a misleading key to round-trip here. `sub_fields`
	 * reuses {@see Group::sub_fields_schema()}; `display` is added so the
	 * editor (Phase 4) and a future Phase 3 assembler both know which
	 * wrapping behaviour to expect from {@see self::sanitize()}.
	 */
	public function to_js_schema(): array {
		return array_merge(
			$this->base_js_schema(),
			array(
				'display'    => $this->get_display(),
				'sub_fields' => $this->sub_fields_schema(),
			)
		);
	}

	/**
	 * Normalizes the configured `display` option to one of ACF's two real
	 * values, falling back to `'group'` (ACF's own default) for anything
	 * else, including a missing/malformed option.
	 *
	 * @return string `'group'` or `'seamless'`.
	 */
	private function get_display(): string {
		return 'seamless' === $this->get_option( 'display', 'group' ) ? 'seamless' : 'group';
	}
}
