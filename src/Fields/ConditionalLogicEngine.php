<?php
/**
 * Evaluates a field's `conditional_logic` config against a set of field values.
 *
 * @package Growsfields
 */

namespace Growsfields\Fields;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ConditionalLogicEngine
 *
 * Evaluates a single field's `conditional_logic` config (see
 * `field-groups/SCHEMA.md` §4 — an OR-of-AND array of
 * `{field, operator, value}` rules) against a "values map" of other fields'
 * current values, and decides whether that field is currently visible.
 *
 * --- SCOPE BOUNDARY ---
 *
 * This class does exactly one job: given one field's raw `conditional_logic`
 * array (the shape {@see FieldType::get_conditional_logic()} already stores
 * and round-trips, unevaluated) plus a values map, whether that field is
 * visible right now. It is NOT:
 *
 * - A source of field values. It never reads `$_POST`, a REST request body,
 *   postmeta, block attributes, or any other live WP state itself — a CALLER
 *   (a meta box save/render handler, a REST controller, a block's `edit.js`
 *   companion PHP, Phase 6-B's builder preview) builds the values map from
 *   wherever those values actually live and hands it in. See "VALUES MAP
 *   SHAPE" below.
 * - `LocationResolver`. That class decides which field GROUP applies to a
 *   rendering context (block/post-type/options-page/...); this class decides
 *   whether one FIELD, already known to be part of an applicable group, is
 *   shown or hidden. The two are unrelated OR-of-AND rule systems that
 *   happen to share JSON envelope syntax (outer array = OR, inner array =
 *   AND) but compare fundamentally different things — location rules compare
 *   fixed context parameters (`block`, `post_type`, ...); conditional logic
 *   rules compare a referenced FIELD's user-entered VALUE, which is why the
 *   operator vocabulary here (§4.2) is richer than location's (§3.2).
 * - A sanitizer. Values in the map handed to {@see self::is_visible()} may
 *   ultimately trace back to request input via some caller (see "SECURITY /
 *   TRUST NOTE" below), but sanitizing them is that caller's job, not this
 *   class's — this class only ever compares values, never stores, echoes, or
 *   passes any of them to a WordPress API.
 *
 * --- VALUES MAP SHAPE ---
 *
 * A "values map" is a plain `array<string field_key, mixed value>` — the
 * field's OWN `key` (never its `name`, see SCHEMA.md §4.1 and "FIELD
 * REFERENCE BY KEY" below) mapped to that field's current value, in whatever
 * shape that field type's own `sanitize()`/`default_value()` produces (a
 * plain scalar for most types, an array for a multi-select/checkbox, a
 * nested array of rows for a repeater, etc.).
 *
 * A flat, globally-keyed map (rather than a nested tree mirroring the field
 * group's own nesting) is the deliberate shape here, for one direct reason:
 * field keys are documented as GLOBALLY unique across the whole
 * `field-groups/` directory (SCHEMA.md §1.1), specifically so `clone` and
 * `conditional_logic.field` references can resolve unambiguously wherever
 * the referenced key actually lives. A flat map is the natural, ambiguity-
 * free representation of "the current value of the field with this key,
 * whatever context it happens to render in" — the same reasoning
 * `LocationResolver`'s flat `array<string, string>` context map follows for
 * its own, differently-shaped lookup.
 *
 * {@see self::build_value_map()} is a convenience helper for the common case
 * — a loaded group's own TOP-LEVEL `fields` (the
 * `array<string name, FieldType>` shape {@see FieldGroupLoader::get_field_groups()}
 * produces) plus a caller-supplied `array<string name, mixed>` of current
 * values keyed by NAME (the natural shape values arrive in from postmeta,
 * block attributes, or a REST request body, all of which are keyed by field
 * `name`, never `key`) — turning that pair into the `key => value` map this
 * class actually evaluates against. See "BUILD_VALUE_MAP SCOPE — JUDGMENT
 * CALL" below for why this helper deliberately does NOT walk into
 * Repeater/Group/FlexibleContent sub-fields.
 *
 * A caller is always free to build the values map by hand instead (or in
 * addition) — nothing about {@see self::is_visible()} requires it to have
 * come from {@see self::build_value_map()}. This is how a caller evaluating
 * conditional logic for fields INSIDE one repeater row (see the same
 * section below) supplies a values map, since this class has no way to
 * derive that map's contents itself.
 *
 * --- CRITICAL DEFAULT REVERSAL FROM `LocationResolver`: VISIBLE UNLESS
 * RESTRICTED ---
 *
 * `LocationResolver` defaults to non-match whenever it is uncertain, on the
 * stated reasoning that a field GROUP silently appearing somewhere it was
 * never meant to (a false positive) is worse than one failing to appear
 * where it should (a false negative, immediately obvious on screen).
 *
 * Conditional Logic inverts the baseline entirely. Per SCHEMA.md §2.1,
 * `conditional_logic` defaults to `[]` on EVERY field, and ACF Pro's own
 * real, documented behaviour is that a field with no conditional logic rules
 * at all is ALWAYS visible — the presence of rules is what introduces
 * restriction, not their absence. Applying `LocationResolver`'s "default to
 * non-match" instinct here verbatim would mean every field in every one of
 * this project's real field groups (all of which currently carry
 * `conditional_logic: []`, per `field-groups/example-migrated-group.json`)
 * would silently render as permanently hidden — the exact opposite of every
 * real field group's actual, intended behaviour today. So the one stated
 * principle this class follows throughout is the mirror image of
 * `LocationResolver`'s: default to VISIBLE, and only let an AFFIRMATIVELY
 * CONFIRMED restricting rule take that visibility away. Every specific
 * judgment call below is an application of this one rule, not an unrelated
 * ad-hoc choice — named here once so the individual decisions read as
 * consequences, not coincidences.
 *
 * --- EMPTY OUTER ARRAY vs. EMPTY INNER GROUP — JUDGMENT CALL (BOTH REVERSED
 * FROM `LocationResolver`) ---
 *
 * - `conditional_logic: []` (empty OUTER array, zero OR-groups at all): per
 *   SCHEMA.md §2.1's own documented default and ACF Pro's real behaviour,
 *   this means "no restriction at all" — the field is ALWAYS visible.
 *   {@see self::is_visible()} checks for this FIRST and returns `true`
 *   immediately, mirroring how `LocationResolver::matches()` checks its own
 *   empty-outer-array case first and returns `false` — same code shape,
 *   opposite documented answer, for the reason explained above.
 * - `conditional_logic: [[]]` (one inner AND-group that is itself empty — a
 *   non-empty outer array containing zero rules): SCHEMA.md does not address
 *   this shape, and ACF Pro's own authoring UI never actually produces it
 *   (a rule group always requires at least one rule to exist once created).
 *   Formally, "AND of zero rules" is vacuously true — there is nothing
 *   inside an empty group that could fail to hold. `LocationResolver`
 *   deliberately rejects this vacuous-truth reading for ITS OWN reasons (an
 *   empty AND-group there would make a location rule silently match every
 *   context in the system, the worst possible false positive for that
 *   class). Those reasons do not transfer here, and in fact point the other
 *   way: refusing vacuous truth for THIS class would mean a single stray
 *   empty rule-group object (e.g. left over from a Phase 6-B builder UI bug
 *   that lets a rule group be saved with its one rule deleted) makes that
 *   whole OR-branch NEVER match, and if it is the field's ONLY OR-branch,
 *   the field becomes PERMANENTLY INVISIBLE — with no way for an editor to
 *   even open that field's own UI again to fix the broken rule, since the
 *   field that would let them edit its conditional logic is the very field
 *   that vanished. That failure mode (data silently un-editable, no
 *   recovery path through the same UI) is worse, for a per-field visibility
 *   feature, than the alternative (an extra field visible that arguably
 *   shouldn't be, which costs an editor nothing beyond noticing an unused
 *   input). This class therefore takes the formally correct vacuous-truth
 *   reading: an empty inner AND-group MATCHES (contributes a "visible" vote
 *   to its OR-branch), consistent with the "default to visible" philosophy
 *   stated above — there is no confirmed restricting condition inside it, so
 *   nothing restricts visibility via that branch.
 *
 * --- MISSING VALUES-MAP KEY — JUDGMENT CALL ---
 *
 * When a rule's `field` key is not present in the `$values` map passed to
 * {@see self::is_visible()} at all (`array_key_exists()` fails), the rule is
 * treated as NOT holding — i.e. that one rule fails, which (per AND
 * semantics) fails its whole enclosing rule-group, unless another OR-branch
 * matches instead. This is NOT a "default to visible" case despite the
 * overall philosophy above: a SPECIFIC rule that names a field this class
 * has no data for is a CONCRETE condition that cannot be affirmatively
 * confirmed true, and per the same symmetric reasoning `LocationResolver`
 * applies to its own missing-context case, "unknown" is treated as neither
 * "equal" nor "not equal" — every operator below requires the referenced key
 * to actually be present in `$values` before it can be evaluated at all. The
 * distinction that matters: an EMPTY rule LIST (no condition authored at
 * all) defaults to visible; a rule that IS authored but cannot be resolved
 * against the data on hand defaults to "this condition is not met" — because
 * once a human has deliberately written "show this field when field X
 * equals Y", failing to find field X's value is a real, reportable data gap
 * (a caller building an incomplete values map, or a genuinely stale
 * cross-group reference — see next section), not an absence of intent.
 *
 * A field with NO submitted/stored value yet still HAS a value under this
 * reasoning (its own type's `default_value()`) — see
 * {@see self::build_value_map()}, which fills exactly that in. "Missing from
 * the map" therefore only really arises from a key this class was never told
 * about at all, not from an ordinary unset-but-known field.
 *
 * --- UNRECOGNIZED `operator` — JUDGMENT CALL ---
 *
 * SCHEMA.md §4.2 documents six operators (see {@see self::SUPPORTED_OPERATORS}),
 * while noting ACF Pro's real UI also offers `==empty`/`!=empty` for some
 * field types, explicitly left unsupported by this design pass. Same
 * reasoning as the missing-key case just above: an operator this class does
 * not understand is a rule that was deliberately authored but cannot be
 * evaluated, so it is treated as NOT holding, never as "matches everything"
 * (which would silently reveal a field that should have stayed
 * conditionally hidden — a real information-disclosure-shaped bug, not just
 * a cosmetic one) and never as a thrown exception (one malformed rule on one
 * field must not prevent every OTHER field's visibility from being decided —
 * the same "isolate the failure" stance `LocationResolver` and
 * `FieldGroupLoader` both already take).
 *
 * --- FIELD REFERENCE BY KEY, AND THE CROSS-GROUP REFERENCE QUESTION —
 * JUDGMENT CALL, NEEDS REVIEW BEFORE LATER-PHASE WIRING ---
 *
 * SCHEMA.md §4.1 confirms (from real data, not inferred) that a rule's
 * `field` holds another field's `key`, never its `name` — this class reads
 * `$rule['field']` and looks it up directly in `$values` (a key-keyed map),
 * with no name-based fallback of any kind.
 *
 * SCHEMA.md §4.1 also flags, without resolving, that one real example
 * (`group_6488bc50dbae3.json`'s "Kies overzicht" field,
 * `conditional_logic: [[{"field":"field_67ed36dd41608","operator":"==","value":"overview"}]]`)
 * references a key that does not belong to that field's own group at all —
 * confirmed by re-checking that file's five fields' own keys
 * (`field_695b80d41a5dc`, `field_67d0031a8d17f`, `field_6488bc50051f0`,
 * `field_695d1200bb26f`, `field_695d1322d9ed4`), none of which is
 * `field_67ed36dd41608`. SCHEMA.md explicitly hands this decision to
 * whoever builds this engine.
 *
 * **Decision: this class imposes NO group-membership restriction on
 * `field` references at all.** {@see self::is_visible()} has no notion of
 * "which group is this field in" in the first place — it only ever knows
 * about the flat `$values` map handed to it, and (per SCHEMA.md §1.1) field
 * keys are globally unique, so nothing about the key format itself
 * distinguishes an in-group reference from a cross-group one. Enforcing a
 * group boundary here would require this class to accept and reason about
 * group membership, which belongs to `FieldGroupLoader`
 * (`array<string, FieldType>` keyed by name, `array<string, array>` keyed by
 * group key) — pulling that concern into this class would be the same
 * scope violation as `LocationResolver` deciding which group applies to a
 * context, which its own docblock explicitly disclaims.
 *
 * The PRACTICAL consequence, though, is exactly what SCHEMA.md's flag
 * anticipates needs review: {@see self::build_value_map()} (the expected,
 * common way a caller builds `$values`) only ever populates keys for the
 * fields actually passed to it — normally one group's own top-level fields.
 * A caller that builds `$values` that way for `group_6488bc50dbae3` will
 * NOT have an entry for `field_67ed36dd41608` (it belongs to some other,
 * unknown group), so per "MISSING VALUES-MAP KEY" above, the "Kies
 * overzicht" field's one rule will always evaluate to "not met" and that
 * field will always be hidden under such a caller — UNLESS a caller
 * deliberately builds a wider values map spanning multiple field groups
 * (e.g. every field group active on the same options page, or a caller that
 * merges several groups' `build_value_map()` outputs together), in which
 * case the reference resolves normally like any other. This mirrors exactly
 * how `LocationResolver` flags its own `acf/` vs `growsfields/` prefix gap:
 * a real, concrete, immediate behavioural consequence of a deliberate
 * scoping decision, not a bug, but one that needs a conscious answer from
 * whoever wires this class into Phase 4/5/6 rendering — specifically,
 * whether that caller should assemble a values map scoped to one group only
 * (this reference will never resolve, field always hidden) or scoped more
 * widely (it can resolve, if the OTHER group's field is also loaded/visible
 * at the same time). Confirm the intended caller scope before relying on
 * this reference resolving.
 *
 * --- BUILD_VALUE_MAP SCOPE — JUDGMENT CALL ---
 *
 * {@see self::build_value_map()} only walks the TOP-LEVEL entries of the
 * `array<string name, FieldType>` it is given — it does NOT recurse into a
 * Repeater's, Group's, or FlexibleContent's own nested sub-fields. Two
 * independent reasons, either one sufficient on its own:
 *
 * 1. Those three classes expose no public accessor to their internally-built
 *    nested `FieldType` objects (`Repeater::$sub_field_types` and
 *    `FlexibleContent::$layouts` are `private`; `Group::$sub_field_types` is
 *    `protected`, reachable only by its own `Clone_` subclass) — by design,
 *    per their own docblocks, since nothing outside those classes has ever
 *    needed one before. Reaching into them via reflection to work around
 *    that would be exactly the kind of encapsulation break this project's
 *    other Phase 3 classes go out of their way to avoid.
 * 2. Even given such access, "the current value of a sub-field" is not a
 *    single well-defined thing for these types the way it is for a
 *    top-level field: a Repeater's sub-fields have one value PER ROW (row 0's
 *    `title` and row 1's `title` are different values under the same
 *    `name`, and — per SCHEMA.md's own real "Menu's" example — potentially
 *    the same sub-field `key` reused structurally across rows depending on
 *    how a caller materializes rows), and ACF Pro's own real Conditional
 *    Logic UI only ever lets a sub-field's rules reference OTHER sub-fields
 *    within that SAME row, never a global/flattened value. Silently
 *    picking "row 0's value" (or any other row) to flatten into a global
 *    `key => value` map would be actively wrong for every row but the one
 *    guessed at, which is worse than not attempting it at all.
 *
 * A caller evaluating conditional logic for a field INSIDE one repeater row
 * builds that row's OWN values map by hand (reading the row's raw
 * `sub_fields` config for keys — available via `get_config()['sub_fields']`
 * on the parent Repeater/Group/FlexibleContent field, the same raw shape
 * {@see FieldGroupLoader}'s own `index_fields()` walks — matched against
 * that one row's own submitted/stored values) and calls
 * {@see self::is_visible()} directly with it; this class does not attempt
 * to guess that per-row context automatically. Flagged here as a known,
 * deliberate gap for whoever wires nested-field conditional logic into a
 * later phase, not an oversight.
 *
 * --- OPERATOR SEMANTICS (SCHEMA.md §4.2) ---
 *
 * `==` / `!=`: string-cast equality/inequality (`(string) $field_value === $rule_value`),
 * same plain-comparison spirit as `LocationResolver`'s own `==`/`!=`. When
 * the referenced field's current value is an ARRAY (a checkbox/multi-select
 * field), neither `==` nor `!=` can be affirmatively confirmed against a
 * single scalar `value` string, so BOTH return `false` — symmetric
 * non-match, same reasoning as the missing-key case above, and a deliberate
 * nudge toward `==contains` (SCHEMA.md's own documented operator for exactly
 * that shape) rather than silently guessing at array-to-scalar coercion.
 *
 * `>` / `<`: numeric comparison via `is_numeric()` on BOTH the referenced
 * field's value and the rule's `value` string, cast to `float` only when
 * both pass; either side failing `is_numeric()` (including any array value)
 * makes the rule NOT match — never throws, never coerces a non-numeric
 * string to `0` the way a bare `(float)` cast would (which could otherwise
 * make `"latest" > "0"` silently evaluate as `0 > 0`, a dangerous silent
 * coercion the task brief explicitly warns against).
 *
 * `==pattern`: ACF's own wildcard match, `*` meaning "zero or more of any
 * character", case-sensitive, matched against the referenced field's value
 * cast to string (an array value never matches — a glob pattern against a
 * list of selected values has no well-defined meaning, same non-match
 * reasoning as `==`/`!=` above). Implemented via {@see self::wildcard_to_regex()},
 * a small hand-rolled `preg_quote()` + `*` -> `.*` translator — NOT
 * `fnmatch()`, which per the PHP manual is unavailable on Windows platforms
 * (this project's own dev environment) except under Cygwin, so relying on
 * it here would make this class silently behave differently (or error)
 * depending on the host OS it happens to run on. `preg_quote()` protects
 * every other regex metacharacter in the authored pattern from being
 * accidentally interpreted, so only `*` carries wildcard meaning, matching
 * ACF's own real, narrow wildcard vocabulary (no `?`, no character classes).
 *
 * `==contains`: substring match (`strpos()`, case-sensitive — no real
 * project data or ACF documentation consulted here settles case-sensitivity
 * either way, so this class matches `==pattern`'s explicit case-sensitivity
 * for internal consistency rather than guessing at a different rule) when
 * the referenced field's value is a string (or any non-array scalar, cast
 * to string first); array-MEMBERSHIP check (`in_array()` after casting both
 * the rule's `value` and each array element to string, so e.g. a checkbox
 * choice value stored as `"1"` matches a rule value of `"1"` regardless of
 * either side's exact PHP type) when the referenced field's value is itself
 * an array — SCHEMA.md's own documented "checkbox/multi-select 'is one of'"
 * use case.
 *
 * --- SECURITY / TRUST NOTE ---
 *
 * Like `LocationResolver`, the `conditional_logic` configs this class
 * evaluates are trusted, developer-authored data (from `field-groups/*.json`
 * via `FieldType::get_conditional_logic()`), not raw user input. The
 * `$values` map passed to {@see self::is_visible()} — and, one step further
 * back, the `$values_by_name` map passed to {@see self::build_value_map()}
 * — is built by a CALLER, and depending on that caller, values inside it
 * could ultimately trace back to request data (e.g. a REST controller
 * evaluating conditional logic against just-submitted, not-yet-saved
 * values). This class does NO sanitization of any value it compares —
 * every comparison is a plain PHP string/numeric/array comparison, nothing
 * is echoed, nothing is used in a SQL query or file path, and no value is
 * ever passed to any WordPress API by this class. Any caller deriving a
 * values map from raw request input is responsible for its own
 * sanitization before constructing that map — this class is not, and does
 * not attempt to be, the security boundary for that input. (In the more
 * common case where `$values` comes from {@see self::build_value_map()}
 * reading already-loaded `FieldType::default_value()` / caller-supplied
 * already-sanitized stored values, this concern mostly does not arise —
 * called out anyway for the REST-controller-style caller where it does.)
 *
 * @package Growsfields
 */
class ConditionalLogicEngine {

	/**
	 * The exhaustive `operator` vocabulary this class understands
	 * (SCHEMA.md §4.2). A rule naming any other `operator` never matches —
	 * see class docblock, "UNRECOGNIZED `operator`".
	 *
	 * Public so a future caller (e.g. Phase 6-B's Conditional Logic UI,
	 * which needs to offer the same vocabulary as a dropdown) can
	 * introspect it rather than duplicating this list, mirroring
	 * `LocationResolver::SUPPORTED_PARAMS`.
	 *
	 * @var array<int, string>
	 */
	public const SUPPORTED_OPERATORS = array(
		'==',
		'!=',
		'>',
		'<',
		'==pattern',
		'==contains',
	);

	private const OPERATOR_EQUALS       = '==';
	private const OPERATOR_NOT_EQUALS   = '!=';
	private const OPERATOR_GREATER_THAN = '>';
	private const OPERATOR_LESS_THAN    = '<';
	private const OPERATOR_PATTERN      = '==pattern';
	private const OPERATOR_CONTAINS     = '==contains';

	/**
	 * Whether one field is currently visible given its own
	 * `conditional_logic` config and a values map describing other fields'
	 * current values.
	 *
	 * Outer array = OR (visible if ANY inner AND-group's conditions all
	 * hold); inner array = AND (a group's conditions all must hold). See
	 * class docblock for the empty-array edge cases — BOTH answered
	 * differently from `LocationResolver`: `[]` always returns `true`
	 * (no restriction authored at all); `[[]]`'s empty inner group is
	 * vacuously true (contributes a "visible" vote via that OR-branch).
	 *
	 * @param array<int, array<int, array{field?: string, operator?: string, value?: string}>> $conditional_logic
	 *        OR-of-AND rule groups, SCHEMA.md §4 shape, exactly as
	 *        {@see FieldType::get_conditional_logic()} returns.
	 * @param array<string, mixed> $values Values map, see class docblock,
	 *                                      "VALUES MAP SHAPE".
	 * @return bool
	 */
	public function is_visible( array $conditional_logic, array $values ): bool {
		// SCHEMA.md §2.1 / ACF Pro's own real behaviour: no rules authored
		// at all means no restriction — always visible. See class
		// docblock, "CRITICAL DEFAULT REVERSAL" and "EMPTY OUTER ARRAY".
		if ( array() === $conditional_logic ) {
			return true;
		}

		foreach ( $conditional_logic as $and_group ) {
			if ( ! is_array( $and_group ) ) {
				continue;
			}

			if ( $this->and_group_matches( $and_group, $values ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Builds a `key => value` map (see class docblock, "VALUES MAP SHAPE")
	 * from a loaded group's own top-level fields plus a caller-supplied
	 * `name => value` map.
	 *
	 * A field present in `$fields` but absent from `$values_by_name` falls
	 * back to that field's own `default_value()` — it is NOT omitted from
	 * the result, since an unset-but-known field still has a real value
	 * (its default) that conditional logic should evaluate against; see
	 * class docblock, "MISSING VALUES-MAP KEY" for why that is different
	 * from a key this method was never told about at all. Deliberately does
	 * NOT recurse into Repeater/Group/FlexibleContent sub-fields — see class
	 * docblock, "BUILD_VALUE_MAP SCOPE".
	 *
	 * @param array<string, FieldType> $fields         Top-level fields,
	 *                                                   keyed by name — the
	 *                                                   same shape one entry
	 *                                                   of
	 *                                                   {@see FieldGroupLoader::get_field_groups()}'s
	 *                                                   `fields` carries.
	 * @param array<string, mixed>     $values_by_name Current values keyed
	 *                                                   by field NAME (e.g.
	 *                                                   postmeta, block
	 *                                                   attributes) — the
	 *                                                   caller's own,
	 *                                                   already-loaded data.
	 * @return array<string, mixed> `key => value`, ready for
	 *                                {@see self::is_visible()}.
	 */
	public function build_value_map( array $fields, array $values_by_name ): array {
		$map = array();

		foreach ( $fields as $field ) {
			if ( ! $field instanceof FieldType ) {
				continue;
			}

			$config = $field->get_config();
			$key    = isset( $config['key'] ) ? (string) $config['key'] : '';

			if ( '' === $key ) {
				continue;
			}

			$name = $field->get_name();

			$map[ $key ] = array_key_exists( $name, $values_by_name )
				? $values_by_name[ $name ]
				: $field->default_value();
		}

		return $map;
	}

	/**
	 * Whether every rule in one inner AND-group holds against `$values`.
	 *
	 * An empty `$rules` array (the `[[]]` edge case) deliberately returns
	 * `true` here — vacuous truth, the opposite answer from
	 * `LocationResolver::and_group_matches()`. See class docblock, "EMPTY
	 * OUTER ARRAY vs. EMPTY INNER GROUP".
	 *
	 * @param array<int, array{field?: string, operator?: string, value?: string}> $rules  One inner
	 *                                                                                        AND-group's
	 *                                                                                        rules.
	 * @param array<string, mixed>                                                  $values Values map.
	 * @return bool
	 */
	private function and_group_matches( array $rules, array $values ): bool {
		if ( array() === $rules ) {
			return true;
		}

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || ! $this->rule_matches( $rule, $values ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether one rule holds against `$values`.
	 *
	 * See class docblock for every default applied here: a rule with no
	 * `field`, or a `field` key absent from `$values`, never matches
	 * ("MISSING VALUES-MAP KEY"); an unrecognized `operator` never matches
	 * ("UNRECOGNIZED `operator`"); see "OPERATOR SEMANTICS" for the exact
	 * behaviour of each of the six supported operators.
	 *
	 * @param array{field?: string, operator?: string, value?: string} $rule   One rule.
	 * @param array<string, mixed>                                      $values Values map.
	 * @return bool
	 */
	private function rule_matches( array $rule, array $values ): bool {
		$field_key = isset( $rule['field'] ) ? (string) $rule['field'] : '';
		$operator  = isset( $rule['operator'] ) ? (string) $rule['operator'] : '';
		$rule_value = isset( $rule['value'] ) ? (string) $rule['value'] : '';

		if ( '' === $field_key || ! array_key_exists( $field_key, $values ) ) {
			return false;
		}

		$field_value = $values[ $field_key ];

		switch ( $operator ) {
			case self::OPERATOR_EQUALS:
				return true === $this->values_equal( $field_value, $rule_value );

			case self::OPERATOR_NOT_EQUALS:
				return false === $this->values_equal( $field_value, $rule_value );

			case self::OPERATOR_GREATER_THAN:
			case self::OPERATOR_LESS_THAN:
				return $this->numeric_comparison_matches( $field_value, $rule_value, $operator );

			case self::OPERATOR_PATTERN:
				return $this->pattern_matches( $field_value, $rule_value );

			case self::OPERATOR_CONTAINS:
				return $this->value_contains( $field_value, $rule_value );

			default:
				return false;
		}
	}

	/**
	 * Three-way equality check: `true` (confirmed equal), `false` (confirmed
	 * not equal), or... there is no third PHP `bool` value, so this returns
	 * `null` for "cannot be confirmed either way" (an array field value),
	 * which {@see self::rule_matches()} treats as "not equal" for `==` AND
	 * as "not not-equal" for `!=` — i.e. neither operator matches. See class
	 * docblock, "OPERATOR SEMANTICS", `==`/`!=`.
	 *
	 * @param mixed  $field_value Referenced field's current value.
	 * @param string $rule_value  Rule's `value`, already cast to string.
	 * @return bool|null
	 */
	private function values_equal( $field_value, string $rule_value ): ?bool {
		if ( is_array( $field_value ) ) {
			return null;
		}

		return (string) $field_value === $rule_value;
	}

	/**
	 * `>` / `<` numeric comparison. Both sides must independently pass
	 * `is_numeric()` before any comparison happens — see class docblock,
	 * "OPERATOR SEMANTICS", `>`/`<`, for why this never falls back to a bare
	 * `(float)` cast.
	 *
	 * @param mixed  $field_value Referenced field's current value.
	 * @param string $rule_value  Rule's `value`, already cast to string.
	 * @param string $operator    `>` or `<`.
	 * @return bool
	 */
	private function numeric_comparison_matches( $field_value, string $rule_value, string $operator ): bool {
		$field_number = $this->numeric_value_or_null( $field_value );
		$rule_number  = $this->numeric_value_or_null( $rule_value );

		if ( null === $field_number || null === $rule_number ) {
			return false;
		}

		return self::OPERATOR_GREATER_THAN === $operator
			? $field_number > $rule_number
			: $field_number < $rule_number;
	}

	/**
	 * Casts `$value` to `float` when it is genuinely numeric (an `int`, a
	 * `float`, or a `string` for which `is_numeric()` is `true`), or returns
	 * `null` otherwise (including for any array value) — the "never coerce
	 * unpredictably" guard {@see self::numeric_comparison_matches()} relies
	 * on.
	 *
	 * @param mixed $value Value to test.
	 * @return float|null
	 */
	private function numeric_value_or_null( $value ): ?float {
		if ( is_int( $value ) || is_float( $value ) ) {
			return (float) $value;
		}

		if ( is_string( $value ) && is_numeric( $value ) ) {
			return (float) $value;
		}

		return null;
	}

	/**
	 * `==pattern` wildcard match. See class docblock, "OPERATOR SEMANTICS",
	 * `==pattern`.
	 *
	 * @param mixed  $field_value Referenced field's current value.
	 * @param string $pattern     Rule's `value`, treated as a `*`-wildcard
	 *                             pattern.
	 * @return bool
	 */
	private function pattern_matches( $field_value, string $pattern ): bool {
		if ( is_array( $field_value ) ) {
			return false;
		}

		return 1 === preg_match( $this->wildcard_to_regex( $pattern ), (string) $field_value );
	}

	/**
	 * Translates a `*`-wildcard pattern into an anchored, case-sensitive PCRE
	 * pattern. Deliberately NOT `fnmatch()` — see class docblock,
	 * "OPERATOR SEMANTICS", `==pattern`, for why (unavailable on Windows).
	 * `preg_quote()` escapes every other regex metacharacter first, so `*`
	 * is the only character carrying special meaning, matching ACF's own
	 * narrow real wildcard vocabulary.
	 *
	 * @param string $pattern Wildcard pattern, e.g. `'acf/*'`.
	 * @return string Delimited PCRE pattern, ready for `preg_match()`.
	 */
	private function wildcard_to_regex( string $pattern ): string {
		$escaped = preg_quote( $pattern, '#' );
		$escaped = str_replace( '\*', '.*', $escaped );

		return '#^' . $escaped . '$#';
	}

	/**
	 * `==contains` substring/array-membership match. See class docblock,
	 * "OPERATOR SEMANTICS", `==contains`.
	 *
	 * @param mixed  $field_value Referenced field's current value.
	 * @param string $needle      Rule's `value` — substring to look for, or
	 *                             the element being membership-tested.
	 * @return bool
	 */
	private function value_contains( $field_value, string $needle ): bool {
		if ( is_array( $field_value ) ) {
			foreach ( $field_value as $item ) {
				if ( is_array( $item ) ) {
					continue;
				}

				if ( (string) $item === $needle ) {
					return true;
				}
			}

			return false;
		}

		return false !== strpos( (string) $field_value, $needle );
	}
}
