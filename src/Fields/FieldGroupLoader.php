<?php
/**
 * Discovers, parses, and instantiates field groups from `field-groups/*.json`.
 *
 * @package Growsfields
 */

namespace Growsfields\Fields;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FieldGroupLoader
 *
 * Reads every `field-groups/*.json` file (see `field-groups/SCHEMA.md` for
 * the full contract this class parses against), resolves `clone` field
 * key-references into concrete `sub_fields`, and instantiates each active
 * group's fields into real {@see FieldType} objects via a single shared
 * {@see FieldTypeRegistry}.
 *
 * --- SCOPE BOUNDARY ---
 *
 * This class does NOT decide whether a field group applies to the current
 * rendering context (block, post type, options page, ...) — that is
 * `LocationResolver`'s job, a separate, later Phase 3 checklist item. This
 * class only exposes each loaded group's raw `location` config as data (see
 * {@see self::get_field_groups()}) for `LocationResolver` to consume. It also
 * does not evaluate `conditional_logic` — that stays exactly what
 * {@see FieldType::get_conditional_logic()} already does: stored and
 * round-tripped, unevaluated (the "Conditional Logic engine" checklist item).
 *
 * --- SECURITY NOTE ---
 *
 * `field-groups/*.json` are trusted, developer-authored files read from
 * disk, not user/request input — there is no `sanitize()`-boundary concern
 * here the way a runtime field *value* has one. But "trusted" does not mean
 * "infinitely trusted": per `Repeater`'s/`Group`'s own precedent of treating
 * a hand-edited field group JSON as no more trustworthy an input than a form
 * submission (see their `default_value()` docblocks), malformed or even
 * hostile JSON in one file must not crash the whole loader or take down
 * every other valid field group — see {@see self::load()} and
 * {@see self::get_errors()} for how a per-file or per-group failure is
 * isolated and surfaced instead of propagating.
 *
 * --- EAGER VS LAZY LOADING ---
 *
 * Deliberately LAZY: parsing, clone resolution, and `FieldType` instantiation
 * for every field group only happen on the first call to
 * {@see self::get_field_groups()} / {@see self::get_field_group()} /
 * {@see self::get_errors()} — not eagerly in the constructor.
 *
 * This mirrors {@see FieldTypeRegistry::get_map()}'s own reasoning rather
 * than diverging from it, even though the trigger is different. A
 * field-group JSON file itself has no third-party registration/hook-ordering
 * dependency the way `FieldTypeRegistry`'s `gs_register_field_type` filter
 * does — but this loader's own work is NOT independent of that filter, since
 * every field it builds goes through {@see FieldTypeRegistry::make()}. That
 * means this loader inherits the exact same ordering dependency one level
 * removed. If `FieldGroupLoader` eagerly parsed and instantiated in its own
 * constructor, and something constructs a `FieldGroupLoader` early (e.g. from
 * `Plugin::boot()` on `plugins_loaded`, the same hook that constructs the
 * shared `FieldTypeRegistry` today), that eager construction would force the
 * registry's own lazy map-build to happen at that same early moment —
 * potentially before some other plugin's `plugins_loaded` callback (which
 * might register a custom field type via `gs_register_field_type`) has run,
 * silently breaking any field group that uses that type. Deferring this
 * loader's own work to first actual use keeps it a passive consumer of
 * whatever the registry's map looks like at the moment a field group is
 * genuinely needed (block render, admin screen, REST call) — unambiguously
 * after every plugin's `plugins_loaded` has completed — rather than
 * reintroducing the exact race `FieldTypeRegistry`'s own laziness exists to
 * avoid. A `FieldGroupLoader` that is constructed but never asked for a
 * field group also never pays the file I/O / parsing / instantiation cost,
 * same benefit `FieldTypeRegistry::get_map()` calls out for itself.
 *
 * --- CLONE RESOLUTION ---
 *
 * `clone` is the one field type whose authored JSON shape is not directly
 * constructible (see `Clone_`'s own "SCOPE BOUNDARY" docblock): its `clone`
 * option is a list of field/group *keys*, not a `sub_fields` array. This
 * class resolves that key list into a concrete `sub_fields` array as a
 * distinct pass — {@see self::resolve_clones()} — that runs after every file
 * has been parsed, so a clone can reference a field/group defined in a
 * DIFFERENT file than the one being processed. A key => field-object(s)
 * index (see {@see self::build_key_index()}) is built once across every
 * successfully parsed group (including inactive ones — an `active:false`
 * group's own field/key definitions are still valid raw data on disk; that
 * flag governs whether the group itself is ever handed to a location-
 * matching consumer, not whether its individual fields remain valid clone-
 * reference targets for some other, active group), then used to resolve
 * every clone field's `clone` list wherever it appears — including nested
 * inside a repeater's/group's `sub_fields` or a flexible_content layout's
 * `sub_fields`, not just a group's top-level `fields[]`.
 *
 * A reference to an unknown key (a stale/broken clone reference) skips just
 * that one key, with a recorded, non-fatal error — the same "isolate the
 * failure, keep going" stance the rest of this class takes.
 *
 * A DIRECT clone-of-clone (a clone referencing a key that resolves to another clone
 * field) is deliberately NOT supported. Resolving it
 * (silently producing an empty/partial clone) would produce quietly wrong
 * data — a field that looks resolved but is missing sub-fields, with no
 * signal to whoever authored the JSON. Throwing a hard exception for the
 * whole load would violate this class's own "one bad group doesn't take down
 * the rest" design. So this class takes the middle path already established
 * for every other error case here: the one offending key reference is
 * skipped (not the whole clone field, not the whole group), with a clear,
 * recorded error naming both the clone field and the clone-of-clone key it
 * pointed at, so the gap is visible rather than silent. This only rejects a
 * DIRECT clone-of-clone (the referenced key itself resolves to a field of
 * type `clone`) — an INDIRECT nested clone (a referenced key resolves to a
 * `group`/`repeater`/`flexible_content` field whose OWN `sub_fields`, at any
 * depth, contain a *different* clone field) is resolved recursively: see
 * {@see self::resolve_clone_field()}'s `$resolving` parameter, which threads
 * a set of keys currently being resolved through that recursion so a genuine
 * cycle (clone A pulls in a group containing clone B, which references A
 * again) is detected and that one reference skipped with a recorded error,
 * instead of recursing forever.
 *
 * --- ERROR HANDLING ---
 *
 * Every non-fatal problem encountered while loading — invalid JSON in one
 * file, a `key`/filename mismatch, a duplicate group key, an unresolvable
 * clone reference, an entire group failing to build because one of its
 * fields names an unregistered type — is recorded as a plain string message
 * via a private `record_error()` helper, which does two things: appends the
 * message to {@see self::get_errors()}'s array, and fires the
 * `gs_field_group_load_error` action (`do_action( 'gs_field_group_load_error', $file, $message_or_exception )`)
 * so other code (an admin notice, a logger) can react without this class
 * needing to know about either. An unregistered field type specifically
 * still throws `\InvalidArgumentException` from
 * {@see FieldTypeRegistry::make()} exactly as documented — this class lets
 * that exception propagate out of {@see self::build_group_fields()}
 * wrapped with context (which file, which group, which field name/key/type)
 * via exception chaining (`$previous`), but catches that wrapped exception
 * one level up in {@see self::load()} so ONE malformed group's field never
 * crashes the loader or blocks any other group from loading successfully.
 *
 * @package Growsfields
 */
class FieldGroupLoader {

	/**
	 * Absolute path to the directory to glob for `*.json` field group
	 * files, trailing slash not required. Defaults to
	 * `GS_PATH . 'field-groups/'` (the real production location) but is
	 * accepted as a constructor argument so this class stays testable
	 * against a throwaway directory without needing to fake `GS_PATH`.
	 *
	 * @var string
	 */
	private string $directory;

	/**
	 * Single shared registry used to instantiate every field, in every
	 * group, across this whole loader — never a fresh registry per group
	 * or per field. Passing this same instance down is what lets
	 * `Repeater`/`Group`/`FlexibleContent` build their own nested
	 * `FieldType` instances without each one re-running the
	 * `gs_register_field_type` filter separately (see those classes' own
	 * constructor docblocks).
	 *
	 * @var FieldTypeRegistry
	 */
	private FieldTypeRegistry $registry;

	/**
	 * Loaded, active field groups, keyed by group key, built lazily by
	 * {@see self::ensure_loaded()}. `null` until the first load; an empty
	 * array is a legitimate "loaded, but nothing to show" result, so `null`
	 * (not empty-array) is the "not loaded yet" sentinel.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private ?array $groups = null;

	/**
	 * Non-fatal error messages accumulated during the last
	 * {@see self::ensure_loaded()} run.
	 *
	 * @var array<int, string>
	 */
	private array $errors = array();

	/**
	 * Absolute file path each successfully parsed group's key was read
	 * from, used only to give error messages a file name to point at.
	 *
	 * @var array<string, string>
	 */
	private array $files_by_key = array();

	/**
	 * Constructor.
	 *
	 * @param string|null            $directory Directory to glob for
	 *                                            `*.json` field group files.
	 *                                            Defaults to
	 *                                            `GS_PATH . 'field-groups/'`
	 *                                            when omitted (and `GS_PATH`
	 *                                            is defined).
	 * @param FieldTypeRegistry|null $registry  Shared registry used to
	 *                                            build every field. Falls
	 *                                            back to a fresh
	 *                                            `FieldTypeRegistry` when
	 *                                            omitted.
	 */
	public function __construct( ?string $directory = null, ?FieldTypeRegistry $registry = null ) {
		$this->directory = $directory ?? ( defined( 'GS_PATH' ) ? GS_PATH . 'field-groups/' : '' );
		$this->registry  = $registry ?? new FieldTypeRegistry();
	}

	/**
	 * Every loaded, active field group, keyed by group key. Triggers
	 * loading on first call (see class docblock, "EAGER VS LAZY LOADING").
	 *
	 * Each entry:
	 *
	 *     array(
	 *         'key'                   => string,
	 *         'title'                 => string,
	 *         'location'              => array<int, array<int, array{param: string, operator: string, value: string}>>,
	 *         'menu_order'            => int,
	 *         'position'              => string,
	 *         'style'                 => string,
	 *         'label_placement'       => string,
	 *         'instruction_placement' => string,
	 *         'hide_on_screen'        => array<int, string>,
	 *         'description'           => string,
	 *         'fields'                => array<string, FieldType>, // keyed by field name
	 *     )
	 *
	 * `location` is passed through verbatim, unresolved — see class docblock
	 * SCOPE BOUNDARY. A group with `active: false` never appears here (see
	 * class docblock, "ERROR HANDLING" / `active` handling in
	 * {@see self::load()}), and neither does a group that failed to build
	 * (its failure is instead recorded, see {@see self::get_errors()}).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_field_groups(): array {
		$this->ensure_loaded();

		return $this->groups;
	}

	/**
	 * One loaded, active field group by its `key`, or `null` if no such
	 * active group was successfully loaded (unknown key, inactive, or it
	 * failed to build — see {@see self::get_errors()} to distinguish the
	 * latter two).
	 *
	 * @param string $key Group key, e.g. `'group_6488bc50dbae3'`.
	 * @return array<string, mixed>|null Same shape as one entry of
	 *                                     {@see self::get_field_groups()}.
	 */
	public function get_field_group( string $key ): ?array {
		$this->ensure_loaded();

		return $this->groups[ $key ] ?? null;
	}

	/**
	 * Non-fatal problems encountered while loading (invalid JSON, a
	 * `key`/filename mismatch, a duplicate group key, an unresolvable clone
	 * reference, an entire group failing to build). Every message here
	 * describes something that was skipped, not something that crashed the
	 * loader — see class docblock, "ERROR HANDLING".
	 *
	 * @return array<int, string>
	 */
	public function get_errors(): array {
		$this->ensure_loaded();

		return $this->errors;
	}

	/**
	 * Loads on first call only; every subsequent call is a no-op that
	 * reuses the already-built `$this->groups`/`$this->errors`.
	 *
	 * @return void
	 */
	private function ensure_loaded(): void {
		if ( null !== $this->groups ) {
			return;
		}

		$this->groups = array();
		$this->errors = array();

		$this->load();
	}

	/**
	 * Runs the full load: discover + parse every file, build the clone
	 * key-index, resolve every clone field's `clone` list against it, then
	 * build `FieldType` instances for every active group's top-level
	 * fields — skipping (with a recorded error) any single group whose
	 * fields fail to build, without affecting any other group.
	 *
	 * @return void
	 */
	private function load(): void {
		$raw_groups = $this->discover_and_parse();
		$key_index  = $this->build_key_index( $raw_groups );
		$raw_groups = $this->resolve_clones( $raw_groups, $key_index );

		foreach ( $raw_groups as $group_key => $group ) {
			$active = ! isset( $group['active'] ) || (bool) $group['active'];

			if ( ! $active ) {
				continue;
			}

			$file           = $this->files_by_key[ $group_key ] ?? '';
			$fields_config  = is_array( $group['fields'] ?? null ) ? $group['fields'] : array();

			try {
				$fields = $this->build_group_fields( $fields_config, $group_key, $file );
			} catch ( \InvalidArgumentException $e ) {
				$this->record_error( $e->getMessage(), $file, $e );
				continue;
			}

			$this->groups[ $group_key ] = array(
				'key'                   => $group_key,
				'title'                 => isset( $group['title'] ) ? (string) $group['title'] : '',
				'location'              => isset( $group['location'] ) && is_array( $group['location'] ) ? $group['location'] : array(),
				'menu_order'            => isset( $group['menu_order'] ) ? (int) $group['menu_order'] : 0,
				'position'              => isset( $group['position'] ) ? (string) $group['position'] : 'normal',
				'style'                 => isset( $group['style'] ) ? (string) $group['style'] : 'default',
				'label_placement'       => isset( $group['label_placement'] ) ? (string) $group['label_placement'] : 'top',
				'instruction_placement' => isset( $group['instruction_placement'] ) ? (string) $group['instruction_placement'] : 'label',
				'hide_on_screen'        => isset( $group['hide_on_screen'] ) && is_array( $group['hide_on_screen'] ) ? $group['hide_on_screen'] : array(),
				'description'           => isset( $group['description'] ) ? (string) $group['description'] : '',
				'fields'                => $fields,
			);
		}
	}

	/**
	 * Discovers `*.json` files in {@see self::$directory} and parses each
	 * one, isolating failures per file:
	 *
	 * - Unreadable file, invalid JSON, or a non-object top-level value:
	 *   recorded error, file skipped.
	 * - Missing/empty `key`: recorded error, file skipped (nothing to index
	 *   it under).
	 * - `key` not matching the filename (SCHEMA.md §6): recorded as a
	 *   WARNING only — loading continues under the declared `key`, since
	 *   `key` (not filename) is the one true, globally-unique identity used
	 *   for clone/`conditional_logic` references (SCHEMA.md §1.1). A
	 *   filename mismatch is a cheap sanity check, not something worth
	 *   treating as fatal — the file is still perfectly loadable data.
	 * - Duplicate `key` across two files: the second file is skipped with a
	 *   recorded error (first-loaded wins) — keys must be globally unique
	 *   (SCHEMA.md §1.1), so there is no correct way to merge two files
	 *   claiming the same identity.
	 *
	 * `fields` is normalized to an array (defaulting to `array()`) here so
	 * every downstream step can assume it exists and is iterable.
	 *
	 * @return array<string, array<string, mixed>> Parsed group data, keyed
	 *                                               by declared group `key`.
	 */
	private function discover_and_parse(): array {
		$raw_groups = array();

		if ( '' === $this->directory ) {
			return $raw_groups;
		}

		$pattern = rtrim( $this->directory, '/\\' ) . '/*.json';
		$files   = glob( $pattern );

		if ( false === $files ) {
			return $raw_groups;
		}

		foreach ( $files as $file ) {
			$contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- trusted local plugin file, not a remote/user-supplied URL.

			if ( false === $contents ) {
				$this->record_error(
					sprintf( 'Growsfields: could not read field group file "%s" — file skipped.', basename( $file ) ),
					$file
				);
				continue;
			}

			$decoded = json_decode( $contents, true );

			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
				$this->record_error(
					sprintf(
						'Growsfields: field group file "%1$s" contains invalid JSON (%2$s) — file skipped.',
						basename( $file ),
						json_last_error_msg()
					),
					$file
				);
				continue;
			}

			$key = isset( $decoded['key'] ) ? (string) $decoded['key'] : '';

			if ( '' === $key ) {
				$this->record_error(
					sprintf( 'Growsfields: field group file "%s" has no "key" — file skipped.', basename( $file ) ),
					$file
				);
				continue;
			}

			$expected_key = basename( $file, '.json' );

			if ( $key !== $expected_key ) {
				$this->record_error(
					sprintf(
						'Growsfields: field group file "%1$s" declares key "%2$s", which does not match its filename (SCHEMA.md §6) — loading anyway under the declared key.',
						basename( $file ),
						$key
					),
					$file
				);
			}

			if ( isset( $raw_groups[ $key ] ) ) {
				$this->record_error(
					sprintf(
						'Growsfields: duplicate field group key "%1$s" — "%2$s" ignored, already loaded from "%3$s".',
						$key,
						basename( $file ),
						basename( $this->files_by_key[ $key ] )
					),
					$file
				);
				continue;
			}

			$decoded['fields'] = isset( $decoded['fields'] ) && is_array( $decoded['fields'] ) ? $decoded['fields'] : array();

			$raw_groups[ $key ]          = $decoded;
			$this->files_by_key[ $key ] = $file;
		}

		return $raw_groups;
	}

	/**
	 * Builds a single key => field-object(s) index across every
	 * successfully parsed group (active or not — see class docblock,
	 * "CLONE RESOLUTION"), used by {@see self::resolve_clones()}.
	 *
	 * A group key indexes to that entire group's own top-level `fields[]`
	 * array. A field key — at ANY nesting depth (top-level, or inside a
	 * repeater's/group's `sub_fields`, or a flexible_content layout's
	 * `sub_fields`) — indexes to a single-entry array containing just that
	 * one field object, matching the shape a `clone` field's resolved
	 * `sub_fields` needs either way (SCHEMA.md §5: "a list of field or group
	 * keys ... resolve a group key to that entire group's own top-level
	 * fields[] array ... and a field key to that one field's own object
	 * wrapped as a single-entry sub_fields array").
	 *
	 * @param array<string, array<string, mixed>> $raw_groups Parsed groups,
	 *                                                          keyed by
	 *                                                          group key.
	 * @return array<string, array<int, array<string, mixed>>> key => list
	 *                                                           of field
	 *                                                           objects.
	 */
	private function build_key_index( array $raw_groups ): array {
		$index = array();

		foreach ( $raw_groups as $group_key => $group ) {
			$fields = is_array( $group['fields'] ?? null ) ? $group['fields'] : array();

			$index[ $group_key ] = $fields;

			$this->index_fields( $fields, $index );
		}

		return $index;
	}

	/**
	 * Recursively adds one index entry per field `key` found in `$fields`
	 * (and, recursively, inside their `sub_fields`/`layouts[].sub_fields`)
	 * to `$index`, by reference.
	 *
	 * @param array<int, array<string, mixed>>                       $fields Field objects to index.
	 * @param array<string, array<int, array<string, mixed>>>        $index  Index being built, by reference.
	 * @return void
	 */
	private function index_fields( array $fields, array &$index ): void {
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			if ( isset( $field['key'] ) && is_string( $field['key'] ) && '' !== $field['key'] ) {
				$index[ $field['key'] ] = array( $field );
			}

			if ( isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
				$this->index_fields( $field['sub_fields'], $index );
			}

			if ( 'flexible_content' === ( $field['type'] ?? '' ) && isset( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
				foreach ( $field['layouts'] as $layout ) {
					if ( is_array( $layout ) && isset( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
						$this->index_fields( $layout['sub_fields'], $index );
					}
				}
			}
		}
	}

	/**
	 * Resolves every `clone` field's `clone` key-list into a concrete
	 * `sub_fields` array, across every parsed group, at any nesting depth.
	 * See class docblock, "CLONE RESOLUTION".
	 *
	 * @param array<string, array<string, mixed>>                $raw_groups Parsed groups, keyed by group key.
	 * @param array<string, array<int, array<string, mixed>>>    $key_index  Index built by {@see self::build_key_index()}.
	 * @return array<string, array<string, mixed>> `$raw_groups` with every
	 *                                               `clone` field's
	 *                                               `sub_fields` patched in.
	 */
	private function resolve_clones( array $raw_groups, array $key_index ): array {
		foreach ( $raw_groups as $group_key => $group ) {
			$fields = is_array( $group['fields'] ?? null ) ? $group['fields'] : array();

			$raw_groups[ $group_key ]['fields'] = $this->resolve_clones_in_fields( $fields, $key_index, $group_key );
		}

		return $raw_groups;
	}

	/**
	 * Walks one field list (a group's top-level `fields[]`, or any nested
	 * `sub_fields`), patching every `clone` field it finds and recursing
	 * into any nested `sub_fields`/`layouts[].sub_fields` along the way.
	 *
	 * @param array<int, array<string, mixed>>                 $fields    Field objects to walk.
	 * @param array<string, array<int, array<string, mixed>>>  $key_index Index built by {@see self::build_key_index()}.
	 * @param string                                             $group_key Owning group's key, for error messages only.
	 * @param array<string, bool>                                $resolving Clone reference keys currently being resolved
	 *                                                                       higher up the call stack, used to detect a
	 *                                                                       circular clone reference. See
	 *                                                                       {@see self::resolve_clone_field()}.
	 * @return array<int, array<string, mixed>>
	 */
	private function resolve_clones_in_fields( array $fields, array $key_index, string $group_key, array $resolving = array() ): array {
		foreach ( $fields as $i => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$type = isset( $field['type'] ) ? (string) $field['type'] : '';

			if ( 'clone' === $type ) {
				$fields[ $i ] = $this->resolve_clone_field( $field, $key_index, $group_key, $resolving );
				continue;
			}

			if ( isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
				$field['sub_fields'] = $this->resolve_clones_in_fields( $field['sub_fields'], $key_index, $group_key, $resolving );
			}

			if ( 'flexible_content' === $type && isset( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
				foreach ( $field['layouts'] as $li => $layout ) {
					if ( is_array( $layout ) && isset( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
						$layout['sub_fields']    = $this->resolve_clones_in_fields( $layout['sub_fields'], $key_index, $group_key, $resolving );
						$field['layouts'][ $li ] = $layout;
					}
				}
			}

			$fields[ $i ] = $field;
		}

		return $fields;
	}

	/**
	 * Resolves one `clone` field's authored `clone` key-list (SCHEMA.md §5)
	 * into a concrete `sub_fields` array, injected into (replacing any
	 * pre-existing `sub_fields` key on) the returned field config.
	 *
	 * An unknown/unresolvable key is skipped with a recorded error (the
	 * rest of the `clone` list still resolves normally). A key that
	 * resolves directly to another `clone` field is also skipped with a
	 * recorded error (see class docblock, "CLONE RESOLUTION" — this direct
	 * case is a deliberate limitation, not a bug). A key that resolves to a
	 * field whose OWN `sub_fields` contain a *different*, nested clone field
	 * is instead resolved recursively — see the `$resolving` parameter below.
	 *
	 * @param array<string, mixed>                              $field     The `clone` field's own config.
	 * @param array<string, array<int, array<string, mixed>>>  $key_index Index built by {@see self::build_key_index()}.
	 * @param string                                             $group_key Owning group's key, for error messages only.
	 * @param array<string, bool>                                $resolving Clone reference keys currently being resolved
	 *                                                                       higher up the call stack. Every `$ref_key`
	 *                                                                       this method is about to resolve is checked
	 *                                                                       against this set first — a key already
	 *                                                                       present means a circular clone reference
	 *                                                                       (e.g. clone A pulls in a group containing
	 *                                                                       clone B, which references A again), which
	 *                                                                       is recorded as an error and that one
	 *                                                                       reference skipped, instead of recursing
	 *                                                                       forever. Each `$ref_key` is added to the
	 *                                                                       set before recursing into its own nested
	 *                                                                       clones.
	 * @return array<string, mixed> `$field` with `sub_fields` set.
	 */
	private function resolve_clone_field( array $field, array $key_index, string $group_key, array $resolving = array() ): array {
		$clone_keys = isset( $field['clone'] ) && is_array( $field['clone'] ) ? $field['clone'] : array();
		$field_key  = isset( $field['key'] ) ? (string) $field['key'] : '(no key)';
		$sub_fields = array();

		foreach ( $clone_keys as $ref_key ) {
			$ref_key = is_string( $ref_key ) ? $ref_key : '';

			if ( '' === $ref_key || ! isset( $key_index[ $ref_key ] ) ) {
				$this->record_error(
					sprintf(
						'Growsfields: clone field "%1$s" in group "%2$s" references unknown key "%3$s" — reference skipped.',
						$field_key,
						$group_key,
						'' === $ref_key ? '(invalid)' : $ref_key
					)
				);
				continue;
			}

			if ( isset( $resolving[ $ref_key ] ) ) {
				$this->record_error(
					sprintf(
						'Growsfields: clone field "%1$s" in group "%2$s" references key "%3$s", which forms a circular clone reference — reference skipped.',
						$field_key,
						$group_key,
						$ref_key
					)
				);
				continue;
			}

			foreach ( $key_index[ $ref_key ] as $resolved_field ) {
				if ( ! is_array( $resolved_field ) ) {
					continue;
				}

				if ( 'clone' === ( $resolved_field['type'] ?? '' ) ) {
					$this->record_error(
						sprintf(
							'Growsfields: clone field "%1$s" in group "%2$s" references another clone field ("%3$s", via key "%4$s") directly — clone-of-clone is not supported, reference skipped.',
							$field_key,
							$group_key,
							isset( $resolved_field['key'] ) ? (string) $resolved_field['key'] : '(unknown)',
							$ref_key
						)
					);
					continue;
				}

				// Recursively resolve any clone field nested inside the
				// resolved field's own sub_fields/layouts (e.g. $ref_key
				// points at a group whose sub_fields include a different
				// clone field), rather than leaving it as an unresolved
				// clone-key-list stub. $ref_key is added to the resolving
				// set for this recursive call only, so a cycle back to it
				// is caught above instead of recursing forever.
				$resolved_list = $this->resolve_clones_in_fields(
					array( $resolved_field ),
					$key_index,
					$group_key,
					$resolving + array( $ref_key => true )
				);

				$sub_fields[] = $resolved_list[0];
			}
		}

		$field['sub_fields'] = $sub_fields;

		return $field;
	}

	/**
	 * Builds one `FieldType` instance per entry in `$fields_config` (a
	 * group's top-level `fields[]`, already clone-resolved), keyed by
	 * field name, via the single shared {@see self::$registry}.
	 *
	 * `Repeater`/`Group`/`FlexibleContent`'s own constructors already
	 * recursively build their OWN `sub_fields`/`layouts[].sub_fields` via
	 * this same shared registry (see their constructor docblocks) — this
	 * method does not, and must not, manually recurse into those; it only
	 * ever calls `FieldTypeRegistry::make()` once per TOP-LEVEL entry in
	 * `$fields_config`.
	 *
	 * @param array<int, array<string, mixed>> $fields_config Clone-resolved
	 *                                                          field objects.
	 * @param string                            $group_key     Owning group's
	 *                                                          key, for
	 *                                                          error context
	 *                                                          only.
	 * @param string                            $file          Owning file's
	 *                                                          absolute
	 *                                                          path, for
	 *                                                          error context
	 *                                                          only.
	 * @return array<string, FieldType> Keyed by field name.
	 * @throws \InvalidArgumentException Wrapping (via exception chaining)
	 *                                    whatever `FieldTypeRegistry::make()`
	 *                                    threw for an unregistered type,
	 *                                    with the group/file/field context
	 *                                    added — see class docblock,
	 *                                    "ERROR HANDLING".
	 */
	private function build_group_fields( array $fields_config, string $group_key, string $file ): array {
		$built = array();

		foreach ( $fields_config as $field_config ) {
			$field_config = is_array( $field_config ) ? $field_config : array();
			$type         = isset( $field_config['type'] ) ? (string) $field_config['type'] : '';

			try {
				$field_type = $this->registry->make( $type, $field_config );
			} catch ( \InvalidArgumentException $e ) {
				throw new \InvalidArgumentException(
					sprintf(
						'Growsfields: field group "%1$s" (%2$s) — field "%3$s" (key "%4$s", type "%5$s") could not be built: %6$s',
						$group_key,
						'' !== $file ? basename( $file ) : '(unknown file)',
						isset( $field_config['name'] ) ? (string) $field_config['name'] : '(no name)',
						isset( $field_config['key'] ) ? (string) $field_config['key'] : '(no key)',
						'' === $type ? '(missing)' : $type,
						$e->getMessage()
					),
					0,
					$e
				);
			}

			$built[ $field_type->get_name() ] = $field_type;
		}

		return $built;
	}

	/**
	 * Records one non-fatal problem: appends `$message` to
	 * {@see self::$errors} and fires `gs_field_group_load_error` so other
	 * code (an admin notice, a logger) can react without this class
	 * depending on either.
	 *
	 * @param string          $message   Human-readable error message.
	 * @param string          $file      Absolute path of the file the
	 *                                    problem relates to, or `''` when
	 *                                    not file-specific (e.g. a clone
	 *                                    reference error).
	 * @param \Throwable|null $exception The underlying exception, when one
	 *                                    exists (JSON errors and duplicate
	 *                                    keys don't have one; a rethrown
	 *                                    `InvalidArgumentException` does).
	 *                                    Passed to the action as-is when
	 *                                    given, falling back to `$message`
	 *                                    itself otherwise.
	 * @return void
	 */
	private function record_error( string $message, string $file = '', ?\Throwable $exception = null ): void {
		$this->errors[] = $message;

		/**
		 * Fires when FieldGroupLoader encounters a non-fatal problem while
		 * loading a field group.
		 *
		 * @param string               $file                Absolute path of
		 *                                                    the file
		 *                                                    involved, or
		 *                                                    `''`.
		 * @param string|\Throwable    $message_or_exception The error
		 *                                                    message, or the
		 *                                                    underlying
		 *                                                    exception when
		 *                                                    one exists.
		 */
		do_action( 'gs_field_group_load_error', $file, $exception ?? $message );
	}
}
