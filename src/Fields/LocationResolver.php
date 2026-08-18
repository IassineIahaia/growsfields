<?php
/**
 * Resolves which field groups apply to a given rendering/editing context.
 *
 * @package Growsfields
 */

namespace Growsfields\Fields;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LocationResolver
 *
 * Evaluates a field group's `location` config (see `field-groups/SCHEMA.md`
 * §3 — an OR-of-AND array of `{param, operator, value}` rules) against a
 * "context" array describing what is currently being rendered/edited, and
 * decides whether that group applies.
 *
 * --- SCOPE BOUNDARY ---
 *
 * This class does exactly one job: given group `location` configs (the shape
 * {@see FieldGroupLoader::get_field_groups()} produces, unresolved and
 * verbatim) plus a context, which groups apply and in what order. It is NOT:
 *
 * - A block-rendering or admin-screen-detection framework. Figuring out
 *   "what block is being rendered right now" / "what post type is the
 *   current admin screen" is a CALLER's job (Phase 4's block render
 *   callback, Phase 5's `MetaBoxRenderer`, Phase 6's `OptionsPageRenderer`)
 *   — this class only ever receives a context that has already been built,
 *   it never inspects `$_GET`, `get_current_screen()`, global `$post`, or
 *   any other live WP state itself.
 * - The Conditional Logic engine. `conditional_logic` (SCHEMA.md §4)
 *   governs per-FIELD visibility inside an already-resolved group and is a
 *   separate, later Phase 3 checklist item — this class never reads that
 *   key.
 *
 * --- CONTEXT SHAPE ---
 *
 * A context is a plain `array<string, string>` carrying zero or more of the
 * six supported params (see {@see self::SUPPORTED_PARAMS}):
 * `block`, `post_type`, `options_page`, `page_template`, `post_status`,
 * `user_role`. A caller sets only whichever keys are actually relevant to
 * what it is resolving — e.g. a block-render caller reasonably sets only
 * `block`, not all six padded out with empty strings. Deliberately not a
 * dedicated value object: the shape is a single flat string=>string map with
 * no behaviour of its own, so a plain array carries it with no ceremony and
 * stays trivially constructible by any future caller (Phase 4/5/6) without
 * this class needing to export a class for them to depend on.
 *
 * Presence vs. absence of a key is meaningful (see §"MISSING CONTEXT VALUE"
 * below) — `array_key_exists()` is used throughout, not `isset()`, so a
 * context that deliberately sets a param to `''` is still treated as
 * "known, empty" rather than "unknown", though no rule in practice matches
 * against an empty string success case except by explicit `!=` design.
 *
 * --- OVERALL DESIGN PHILOSOPHY: DEFAULT TO NON-MATCH WHEN UNCERTAIN ---
 *
 * Every judgment call below (missing context value, unrecognized `operator`,
 * unrecognized `param`, an empty inner AND-group) is resolved the same way:
 * when this class cannot affirmatively confirm a rule or rule-group holds,
 * it treats that rule/group as NOT matching, rather than assuming it does.
 * The reasoning is the same in every case: a field group silently appearing
 * somewhere it was never meant to (a false positive) is a much worse and
 * much harder to notice bug than a field group failing to appear somewhere
 * it should (a false negative, which is immediately obvious to whoever is
 * looking at the screen it didn't show up on). This single stated principle
 * is what every specific default below follows from — it is called out once
 * here so the individual decisions read as applications of one rule, not as
 * unrelated ad-hoc choices.
 *
 * --- MISSING CONTEXT VALUE — JUDGMENT CALL ---
 *
 * When a rule's `param` is one of the six supported params but the context
 * array passed to {@see self::matches()} / {@see self::resolve()} simply
 * does not carry a value for it (e.g. evaluating a `user_role` rule against
 * a context that was only given `block`), the rule is treated as NOT
 * matching. A rule cannot be confirmed true against information that was
 * never supplied — "unknown" is not "equal" and (per the stated philosophy
 * above) is also deliberately not treated as "not equal" either, since a
 * `!=` rule silently matching every context that simply forgot to populate
 * that one param would be just as much a false positive as `==` doing the
 * same. Both operators require the context to actually carry a value for
 * `param` before either can be evaluated at all.
 *
 * --- UNRECOGNIZED `operator` — JUDGMENT CALL ---
 *
 * SCHEMA.md §3.2 documents exactly two operators, `==` and `!=`. Any other
 * string in a rule's `operator` key is syntactically valid JSON but not
 * something this class understands. Per the philosophy above, an
 * unrecognized operator makes the rule NOT match — never "matches
 * everything" (which would be a dangerous silent-failure mode: a typo'd
 * operator like `=` or `eq` would make a field group appear literally
 * everywhere) and never a thrown exception (a single malformed rule in one
 * field group's `location` should not be able to break every OTHER field
 * group's resolution in the same `resolve()` call — the same "isolate the
 * failure" stance `FieldGroupLoader` already takes for malformed JSON).
 *
 * --- UNRECOGNIZED `param` — JUDGMENT CALL ---
 *
 * SCHEMA.md §3.1 explicitly leaves the `param` vocabulary open at the schema
 * level and hands the "what to do about an unrecognized one" decision to
 * this class. Same reasoning and same answer as the operator case just
 * above: a rule naming a `param` this class does not know how to evaluate
 * (e.g. ACF Pro's own `post_category`, `current_user`, `nav_menu`, none of
 * which this project's vocabulary includes per SCHEMA.md §3.1) can never be
 * confirmed to match, so it does not. {@see self::SUPPORTED_PARAMS} is the
 * exhaustive allow-list this is checked against.
 *
 * --- EMPTY `location` vs. EMPTY INNER GROUP — JUDGMENT CALL ---
 *
 * Two different empty-array shapes need two different, deliberately
 * DIFFERENT answers:
 *
 * - `location: []` (empty OUTER array, zero AND-groups at all): SCHEMA.md
 *   §1 states this explicitly — "An empty array means the group never
 *   applies" — so this is not a judgment call, just a guarded requirement.
 *   {@see self::matches()} checks for this FIRST and returns `false`
 *   immediately, before any iteration, specifically to avoid the classic
 *   "empty array of ORs" trap: an empty `foreach` that never finds a
 *   matching branch also never explicitly returns `true`, so it would
 *   naturally fall through to `false` anyway in this implementation — but
 *   the explicit early check makes the documented rule visible in the code
 *   itself rather than being an accidental emergent property of the loop
 *   shape, which matters because the OTHER empty-array case below resolves
 *   the opposite way a naive reader might expect.
 * - `location: [[]]` (one inner AND-group that is itself empty — a
 *   non-empty outer array containing zero rules): SCHEMA.md does not
 *   address this shape at all, and it is NOT covered by the §1 rule above
 *   (the outer array here is non-empty). Formally, "AND of zero rules" is
 *   vacuously true, which would make this inner group — and therefore the
 *   whole group, since OR needs only one matching branch — match ANY
 *   context whatsoever. This class deliberately does NOT take the formally
 *   correct vacuous-truth reading: per the stated philosophy above, an
 *   empty rule list inside a real outer array is far more likely to be
 *   leftover/malformed authoring data (e.g. every rule was deleted from a
 *   group in a hand-edited JSON file, or a future builder UI bug produced
 *   an empty rule row) than a deliberate "match everything" wildcard — and
 *   "this field group now silently applies to literally every block, post
 *   type, and options page in the system" is exactly the kind of dangerous,
 *   hard-to-notice false positive this class exists to avoid. An empty
 *   inner AND-group is therefore treated as NEVER matching, the same as an
 *   inner group containing an unrecognized rule.
 *
 * --- `block` PARAM'S "all" WILDCARD — GROUNDED IN REAL DATA, NOT A GUESS ---
 *
 * SCHEMA.md §3.1 documents that `value: "all"` for `param: "block"` is
 * ACF Pro's own real "any block" wildcard, confirmed in real project data
 * (`group_69380e4dc49ac.json`, "Block options": `block == all` AND
 * `block != acf/headerimage` AND `block != acf/hero` — i.e. "applies to
 * every block except these two"). Taken as plain string equality, `block ==
 * "all"` would never match any real block name and that real field group
 * would never render anywhere, which is not ACF's actual, documented
 * behaviour of that rule. This class special-cases exactly that one
 * combination — `param === 'block'`, `value === 'all'`, `operator === '=='`
 * — to mean "matches whenever the context declares ANY block value at all"
 * (i.e. `$context['block']` is present and non-empty), rather than literal
 * string equality against a block named `"all"`.
 *
 * This special case is scoped NARROWLY on purpose: only `==` is special-
 * cased, because that is the only operator the wildcard is ever seen paired
 * with in the one confirmed real example. `block != "all"` is NOT given
 * special meaning here — it falls through to plain string inequality
 * (matches unless the context's block value is literally the string
 * `"all"`, which in practice is always true for any real block name) since
 * there is no real, confirmed example of `!=` combined with the wildcard to
 * ground a decision either way. Flagged here as its own small judgment call,
 * separate from the four the task brief named explicitly, in case a future
 * real field group turns out to need `block != "all"` to mean something
 * else.
 *
 * --- `acf/` vs `growsfields/` BLOCK NAME PREFIX — JUDGMENT CALL, NEEDS
 * REVIEW BEFORE PHASE 4 WIRING ---
 *
 * The one real migrated field group on disk today
 * (`field-groups/example-migrated-group.json`) has
 * `location: [[{"param":"block","operator":"==","value":"acf/overview"}]]`
 * — the OLD ACF block-name prefix. Every real native block registered so
 * far (`blocks/*\/block.json`, Phase 4 prep work) is namespaced
 * `growsfields/{slug}` instead (e.g. `growsfields/overview`), NOT `acf/`.
 * These two facts do not match each other today.
 *
 * **This class does NO normalization of the `block` param's prefix.**
 * `block == "acf/overview"` is compared via plain string equality against
 * whatever string a caller puts in `$context['block']` — if a Phase 4 block
 * render callback (built later, out of scope here) passes
 * `'block' => 'growsfields/overview'`, that will NOT match a rule written
 * as `"acf/overview"`. Concretely: **as things stand right now, resolving
 * `example-migrated-group.json`'s location against a context built from the
 * real, currently-registered `growsfields/overview` block would return "no
 * match"**, even though that field group is clearly the one intended for
 * that block.
 *
 * This was a deliberate choice among the alternatives, not an oversight:
 *
 * 1. *(rejected)* Normalize/strip any `{namespace}/` prefix inside this
 *    class before comparing, so `acf/overview` and `growsfields/overview`
 *    (or literally any-namespace/overview) are treated as the same block.
 *    Rejected because it would silently paper over what is really a data
 *    migration problem with permanent, always-on runtime logic — every
 *    single rule evaluation, forever, would carry a special case that only
 *    exists because of one specific historical rename. It would also widen
 *    matching in a way `==`/`!=` (SCHEMA.md §3.2: plain string equality,
 *    no other semantics beyond the one confirmed `"all"` wildcard above)
 *    does not otherwise have anywhere else in this class, and could mask a
 *    genuine future authoring mistake (e.g. a rule that really is meant to
 *    reference a *different* plugin's block sharing the same slug).
 * 2. *(chosen)* Do nothing special here; treat this as exactly what
 *    PROGRESS.md's own Phase 3 checklist already names as a distinct,
 *    dedicated later item — "Migrate 7 existing field groups to new
 *    format" — whose job includes rewriting `acf/*` block-name values to
 *    `growsfields/*` in the JSON itself, a one-time data fix rather than a
 *    permanent runtime special case. This keeps `LocationResolver` a
 *    faithful, boring implementation of exactly what SCHEMA.md §3 documents
 *    (plain string equality, one confirmed wildcard), with no hidden
 *    string-rewriting behaviour a future reader of this class would have to
 *    discover by surprise.
 *
 * **Needs review:** this is flagged here explicitly, per this project's own
 * established practice for judgment calls (see e.g. `PageLink.php`'s
 * `allow_external`, `FieldGroupLoader`'s clone-of-clone handling), because
 * it has a real, immediate consequence: NOTHING will match on any real
 * block today until either (a) the migration checklist item rewrites the
 * `acf/` prefixes to `growsfields/`, or (b) whoever wires this class into
 * Phase 4's block render path (out of scope here) decides to normalize the
 * prefix at the CALL SITE when building the context (e.g. translate the
 * live block name back to its old `acf/` form before constructing
 * `$context['block']`, rather than this class translating rule values
 * forward). Option (a) is the one this implementation assumes; confirm
 * before Phase 4 wiring begins.
 *
 * --- SECURITY / TRUST NOTE ---
 *
 * Like {@see FieldGroupLoader}, the `location` configs this class evaluates
 * are trusted, developer-authored data (from `field-groups/*.json` via
 * `FieldGroupLoader::get_field_groups()`), not raw user input. But the
 * `$context` array passed to {@see self::resolve()} / {@see self::matches()}
 * is built by a CALLER, and depending on that caller, one or more of its
 * values could ultimately trace back to request data (a concrete, realistic
 * example: an admin-screen caller reading `post_type` from `$_GET['post_type']`
 * to build its context). This class does NO sanitization of `$context`
 * values whatsoever — every comparison is a plain PHP string equality
 * check (`===`/`!==`), nothing is echoed, nothing is used in a SQL query or
 * file path, and no value from `$context` is ever passed to any WordPress
 * API by this class. Any caller that derives a context value from raw
 * request input is responsible for its own sanitization (e.g.
 * `sanitize_key()`/`sanitize_text_field()`) BEFORE constructing the context
 * array handed to this class — this class is not, and does not attempt to
 * be, the security boundary for that input.
 *
 * @package Growsfields
 */
class LocationResolver {

	/**
	 * The exhaustive `param` vocabulary this class understands (SCHEMA.md
	 * §3.1). A rule naming any other `param` never matches — see class
	 * docblock, "UNRECOGNIZED `param`".
	 *
	 * Public so a future caller (e.g. Phase 6-B's Location Rules UI, which
	 * needs to offer the same vocabulary as a dropdown) can introspect it
	 * rather than duplicating this list.
	 *
	 * @var array<int, string>
	 */
	public const SUPPORTED_PARAMS = array(
		'block',
		'post_type',
		'options_page',
		'page_template',
		'post_status',
		'user_role',
	);

	/**
	 * The exhaustive `operator` vocabulary this class understands
	 * (SCHEMA.md §3.2). A rule naming any other `operator` never matches —
	 * see class docblock, "UNRECOGNIZED `operator`".
	 */
	private const OPERATOR_EQUALS     = '==';
	private const OPERATOR_NOT_EQUALS = '!=';

	/**
	 * Filters `$field_groups` down to only the ones whose `location`
	 * matches `$context`, sorted by `menu_order` ascending (SCHEMA.md §1:
	 * "lower first").
	 *
	 * Pure filter + sort — every matching group's own array entry is
	 * returned exactly as given (same shape
	 * {@see FieldGroupLoader::get_field_groups()} produces: `key`, `title`,
	 * `location`, `menu_order`, `position`, `style`, `label_placement`,
	 * `instruction_placement`, `hide_on_screen`, `description`, `fields`),
	 * nothing stripped or transformed. String keys (group keys) are
	 * preserved via a stable sort (`uasort()`, stable since PHP 8.0) rather
	 * than re-indexed, so a caller can still look a matched group up by its
	 * `key` after calling this method.
	 *
	 * @param array<string, array<string, mixed>> $field_groups Field groups,
	 *                                                            same shape
	 *                                                            {@see
	 *                                                            FieldGroupLoader::get_field_groups()}
	 *                                                            returns.
	 *                                                            Each entry
	 *                                                            must carry
	 *                                                            `location`
	 *                                                            (array) and
	 *                                                            `menu_order`
	 *                                                            (int) —
	 *                                                            both are
	 *                                                            defaulted
	 *                                                            (`[]` / `0`)
	 *                                                            when absent
	 *                                                            so a
	 *                                                            malformed
	 *                                                            single
	 *                                                            entry does
	 *                                                            not break
	 *                                                            the whole
	 *                                                            call.
	 * @param array<string, string>                $context      See class
	 *                                                            docblock,
	 *                                                            "CONTEXT
	 *                                                            SHAPE".
	 * @return array<string, array<string, mixed>> Matching groups only,
	 *                                               keyed by group key,
	 *                                               sorted by `menu_order`
	 *                                               ascending.
	 */
	public function resolve( array $field_groups, array $context ): array {
		$matched = array();

		foreach ( $field_groups as $group_key => $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			$location = isset( $group['location'] ) && is_array( $group['location'] ) ? $group['location'] : array();

			if ( $this->matches( $location, $context ) ) {
				$matched[ $group_key ] = $group;
			}
		}

		uasort(
			$matched,
			static function ( array $a, array $b ): int {
				$order_a = isset( $a['menu_order'] ) ? (int) $a['menu_order'] : 0;
				$order_b = isset( $b['menu_order'] ) ? (int) $b['menu_order'] : 0;

				return $order_a <=> $order_b;
			}
		);

		return $matched;
	}

	/**
	 * Whether a single field group's `location` config matches `$context`.
	 *
	 * Convenience entry point for a caller that already has one group's
	 * `location` in hand and does not need the whole filter+sort pipeline
	 * {@see self::resolve()} runs — exposes the core group-level match
	 * logic directly.
	 *
	 * Outer array = OR (matches if ANY inner AND-group matches); inner
	 * array = AND (an inner group matches only if EVERY rule inside it
	 * matches). See class docblock for the empty-array edge cases (`[]`
	 * never matches; `[[]]`'s empty inner group also never matches, a
	 * deliberate departure from vacuous-truth logic).
	 *
	 * @param array<int, array<int, array{param?: string, operator?: string, value?: string}>> $location OR-of-AND
	 *                                                                                                      rule
	 *                                                                                                      groups,
	 *                                                                                                      SCHEMA.md
	 *                                                                                                      §3
	 *                                                                                                      shape.
	 * @param array<string, string>                                                              $context  See class
	 *                                                                                                      docblock,
	 *                                                                                                      "CONTEXT
	 *                                                                                                      SHAPE".
	 * @return bool
	 */
	public function matches( array $location, array $context ): bool {
		// SCHEMA.md §1: an empty outer array never matches. Guarded
		// explicitly first — see class docblock, "EMPTY `location` vs.
		// EMPTY INNER GROUP".
		if ( array() === $location ) {
			return false;
		}

		foreach ( $location as $and_group ) {
			if ( ! is_array( $and_group ) ) {
				continue;
			}

			if ( $this->and_group_matches( $and_group, $context ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether every rule in one inner AND-group matches `$context`.
	 *
	 * An empty `$rules` array (the `[[]]` edge case) deliberately returns
	 * `false`, not `true` — see class docblock, "EMPTY `location` vs.
	 * EMPTY INNER GROUP".
	 *
	 * @param array<int, array{param?: string, operator?: string, value?: string}> $rules   One inner
	 *                                                                                        AND-group's
	 *                                                                                        rules.
	 * @param array<string, string>                                                $context See class
	 *                                                                                        docblock,
	 *                                                                                        "CONTEXT
	 *                                                                                        SHAPE".
	 * @return bool
	 */
	private function and_group_matches( array $rules, array $context ): bool {
		if ( array() === $rules ) {
			return false;
		}

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || ! $this->rule_matches( $rule, $context ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether one rule matches `$context`.
	 *
	 * See class docblock for the defaults applied here: an unrecognized
	 * `param` (not in {@see self::SUPPORTED_PARAMS}) never matches; a
	 * `param` for which `$context` carries no value at all never matches;
	 * an unrecognized `operator` (not `==`/`!=`) never matches; and
	 * `param === 'block'`, `value === 'all'`, `operator === '=='` is the one
	 * documented wildcard, handled before the general operator switch.
	 *
	 * @param array{param?: string, operator?: string, value?: string} $rule    One rule.
	 * @param array<string, string>                                     $context See class docblock,
	 *                                                                            "CONTEXT SHAPE".
	 * @return bool
	 */
	private function rule_matches( array $rule, array $context ): bool {
		$param    = isset( $rule['param'] ) ? (string) $rule['param'] : '';
		$operator = isset( $rule['operator'] ) ? (string) $rule['operator'] : '';
		$value    = isset( $rule['value'] ) ? (string) $rule['value'] : '';

		if ( ! in_array( $param, self::SUPPORTED_PARAMS, true ) ) {
			return false;
		}

		if ( ! array_key_exists( $param, $context ) ) {
			return false;
		}

		$context_value = (string) $context[ $param ];

		// ACF's own "any block" wildcard — see class docblock, "`block`
		// PARAM'S "all" WILDCARD". Scoped to == only; == is the only
		// operator this is confirmed to appear with in real data.
		if ( 'block' === $param && 'all' === $value && self::OPERATOR_EQUALS === $operator ) {
			return '' !== $context_value;
		}

		switch ( $operator ) {
			case self::OPERATOR_EQUALS:
				return $context_value === $value;

			case self::OPERATOR_NOT_EQUALS:
				return $context_value !== $value;

			default:
				return false;
		}
	}
}
