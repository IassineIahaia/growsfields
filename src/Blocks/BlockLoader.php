<?php
/**
 * Registers every native block under `blocks/*\/block.json` with attributes
 * derived from the fields engine, and renders them.
 *
 * @package Growsfields
 */

namespace Growsfields\Blocks;

use Growsfields\Fields\FieldGroupLoader;
use Growsfields\Fields\FieldType;
use Growsfields\Fields\FieldTypeRegistry;
use Growsfields\Fields\LocationResolver;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BlockLoader
 *
 * Bridges the fields engine (`Growsfields\Fields\*`) to WordPress's native
 * block registration API. Each `blocks/{slug}/block.json` on disk is a
 * static skeleton (name/title/category/icon/...) with no `attributes` key —
 * this class computes that missing `attributes` schema at registration time
 * by resolving whichever field group(s) target that block (via
 * {@see LocationResolver}) and mapping each field's PHP-level
 * {@see FieldType::default_value()} shape onto a WP block attribute type,
 * then hands both `attributes` and a shared `render_callback` to
 * `register_block_type()`.
 *
 * --- SCOPE BOUNDARY ---
 *
 * This class does not know anything about any ONE block's markup — that is
 * each block's own `render.php` (`blocks/{slug}/render.php`), included by
 * {@see self::render_block()} with `$attributes` in scope, exactly the way
 * this project's real theme (ACF-based) already does for its own blocks
 * (ACF's `renderTemplate` convention). It also does not decide which field
 * TYPES exist or how a submitted value is sanitized — that is
 * `FieldTypeRegistry`/`FieldType::sanitize()`'s job — and it does not decide
 * whether a field group applies to a given block — that is
 * `LocationResolver`'s job. This class's only two responsibilities are (1)
 * turning a resolved group's fields into a WP attributes schema, and (2)
 * dispatching a render to the right `render.php`.
 *
 * --- HOOK: `init` ---
 *
 * `register_block_type()` must run on `init` (or a hook that has already
 * fired by `init`) — this is WordPress's own documented requirement for all
 * block registration, core and third-party alike, and every core block
 * (`wp-includes/blocks.php`) is itself registered on `init` via
 * `create_initial_blocks()` on that same hook. `plugins_loaded` (used
 * elsewhere in this plugin, e.g. `Plugin::boot()`) runs too early: many of
 * the translation/i18n and block-category APIs `register_block_type()`
 * touches are not guaranteed ready until `init`, and third-party plugins
 * that filter block registration (e.g. `block_type_metadata`) may not have
 * attached their own `init` callbacks yet if this ran any earlier. `boot()`
 * therefore only constructs this class and attaches {@see self::register_blocks()}
 * to `init` — it does not call it directly.
 *
 * --- WHY THIS CLASS PARSES `block.json` ITSELF, BEFORE CALLING
 * `register_block_type()` — JUDGMENT CALL ---
 *
 * `register_block_type( $dir, $args )` needs the FINAL `attributes` array
 * already built by the time it's called (see below for how `$args` merges
 * with the file). But building that array requires knowing the block's own
 * `name` (e.g. `growsfields/hero`) first, since that's the context value
 * `LocationResolver::resolve()` matches a field group's `location` against
 * — and `register_block_type()` does not hand this class a "before you
 * commit, here's the name" callback to hook into.
 *
 * Two ways to get that name ahead of time were available: (a) parse
 * `block.json` directly, or (b) assume the block's slug equals its
 * directory's basename (true for every block today — `blocks/hero/` is
 * `growsfields/hero`) and skip parsing. (b) was rejected: it is an
 * assumption about a THIRD file's content (the directory name) standing in
 * for what `block.json`'s own `name` key actually says, and nothing enforces
 * that the two stay in sync — a renamed directory or a copy-pasted
 * `block.json` with a stale `name` would silently resolve the WRONG field
 * group (or none) with no error surfaced anywhere. (a) costs one extra
 * `json_decode()` per block per request (the same file `register_block_type()`
 * itself will decode a moment later — WP core does not expose a way to reuse
 * that parse) but is the one source of truth for what `name` actually says,
 * consistent with this project's other loader (`FieldGroupLoader`'s own
 * `discover_and_parse()`), which also treats each JSON file as needing its
 * own isolated, defensive read rather than trusting a filename/directory
 * convention. A malformed/unreadable `block.json` is skipped for THAT block
 * only (see {@see self::read_block_name()}) — one broken block must not stop
 * every other block from registering, the same "isolate the failure" stance
 * `FieldGroupLoader` takes for field groups.
 *
 * --- ATTRIBUTE TYPE INFERENCE — DELIBERATE DESIGN DECISION ---
 *
 * {@see self::infer_attribute_type()} maps a field's
 * `FieldType::default_value()` PHP shape onto a WP block attribute `type`
 * purely by inspecting that PHP value's own type at runtime (`is_bool()`,
 * `is_int()`/`is_float()`, `is_string()`, `is_array()` + list-vs-associative)
 * — it is NOT a hardcoded `field-type-slug => attribute-type` lookup table.
 * This is deliberate: there are 34 registered field types
 * (`FieldTypeRegistry::register_builtin_types()`) and a parallel slug-keyed
 * table would need to be extended by hand every time a new field type is
 * added, with no compiler/test enforcement tying the two together — it is
 * exactly the kind of table that silently drifts out of sync (a new field
 * type ships, nobody remembers to add its row here, and its block attribute
 * falls back to some wrong default). Inferring from the actual PHP shape
 * `default_value()` already, unavoidably, returns is automatically correct
 * for every current and future field type without this class needing to
 * know anything about any specific one.
 *
 * The four buckets, in the order checked:
 * - `bool` → `'boolean'`.
 * - `int`/`float` → `'number'` (WP's block attribute schema does not
 *   distinguish integer vs. float; `'number'` covers both, same as core
 *   blocks do for e.g. `columns`).
 * - `string` → `'string'`.
 * - `array` → `'array'` if it is a LIST (sequential integer keys from 0,
 *   e.g. a would-be Repeater/Gallery/Checkbox default), `'object'` if it is
 *   associative (e.g. Link's `{url, title, target}` shape, Google Map's
 *   `{lat, lng, address}` shape). PHP 8.1+ has `array_is_list()` built in,
 *   but this project's `composer.json` targets `"php": ">=8.0"` (checked
 *   before writing this), so {@see self::is_list_array()} reimplements the
 *   same check by hand for 8.0 compatibility.
 * - Anything else (in practice: `null`, the only other shape a well-behaved
 *   `default_value()` implementation might plausibly return) falls back to
 *   `'string'` — the least presumptive WP attribute type for an unknown
 *   shape, rather than throwing and taking down attribute computation for
 *   every OTHER field on the same block over one field type's unexpected
 *   return value.
 *
 * --- FIELD NAME COLLISION ACROSS MULTIPLE RESOLVED GROUPS — JUDGMENT CALL ---
 *
 * `LocationResolver::resolve()` can return more than one matching group for
 * a block (not for Hero today — its one group excludes every other block's
 * shared "Block options" group — but a future block could plausibly have
 * both a block-specific group AND a shared group both apply). Groups are
 * walked in the order `resolve()` already sorted them (`menu_order`
 * ascending), and within each group, fields are walked in that group's own
 * authored `fields[]` order. If two resolved groups both define a field with
 * the same `name`, the LATER group in that walk (higher `menu_order`) wins —
 * a plain PHP array key assignment, no special merge logic. This mirrors how
 * `array_merge()`/later-wins is already the norm elsewhere in this codebase
 * (e.g. `FieldType::__construct()`'s own config merge) and keeps the
 * behaviour predictable (`menu_order` is the one explicit, author-controlled
 * ordering signal `LocationResolver` exposes) rather than silently picking
 * whichever group happened to be iterated last for some less obvious reason.
 *
 * --- MISSING `render.php` — NOT A FATAL ERROR ---
 *
 * {@see self::render_block()} returns `''` when `blocks/{slug}/render.php`
 * does not exist for the block currently being rendered, rather than
 * warning/fatal-erroring. This is deliberate: at the time this class first
 * shipped, only Hero has a `render.php` — the other 5 scaffolded blocks
 * (`cta`, `body`, `headerimage`, `overview`, `default-block`) do not yet, and
 * a missing template for one of them must not break registration or
 * rendering for every OTHER block. An HTML comment (e.g.
 * `<!-- growsfields: no render.php for "hero" -->`) was considered instead of
 * a bare empty string, since it would be more discoverable while debugging —
 * rejected because it would leak an implementation detail (this plugin's own
 * on-disk file layout) into every visitor's page source for a case that, in
 * production, should simply never happen once every block ships its
 * template; a developer debugging a genuinely missing template has faster,
 * more direct tools available (checking the filesystem, `WP_DEBUG` logging)
 * than reading rendered HTML comments.
 *
 * @package Growsfields
 */
class BlockLoader {

	/**
	 * Absolute path to the directory to glob for `{slug}/block.json`
	 * sub-directories, trailing slash not required. Defaults to
	 * `GS_PATH . 'blocks/'` (the real production location) but is accepted
	 * as a constructor argument so this class stays testable against a
	 * throwaway directory without needing to fake `GS_PATH` — same pattern
	 * `FieldGroupLoader::__construct()` already uses for its own directory.
	 *
	 * @var string
	 */
	private string $blocks_directory;

	/**
	 * Shared loader used to resolve every block's field groups. Constructed
	 * once here (never per-block) so its own lazy parse-and-build
	 * (`FieldGroupLoader::ensure_loaded()`) only ever runs a single time per
	 * request no matter how many blocks this class registers.
	 *
	 * @var FieldGroupLoader
	 */
	private FieldGroupLoader $field_group_loader;

	/**
	 * Shared resolver used to match each block's context against every
	 * loaded field group's `location`. Stateless, but constructed once here
	 * to follow the same "build collaborators once, not per block" pattern
	 * as {@see self::$field_group_loader}.
	 *
	 * @var LocationResolver
	 */
	private LocationResolver $location_resolver;

	/**
	 * Constructor.
	 *
	 * Follows {@see FieldGroupLoader::__construct()}'s own pattern of
	 * accepting optional collaborators with sensible production defaults, so
	 * this class stays independently testable (a throwaway blocks directory,
	 * a `FieldGroupLoader` pointed at throwaway field-group JSON) without
	 * needing to fake `GS_PATH` or touch the real `field-groups/`/`blocks/`
	 * directories.
	 *
	 * @param string|null           $blocks_directory   Directory to glob for
	 *                                                    `{slug}/block.json`
	 *                                                    sub-directories.
	 *                                                    Defaults to
	 *                                                    `GS_PATH . 'blocks/'`
	 *                                                    when omitted (and
	 *                                                    `GS_PATH` is
	 *                                                    defined).
	 * @param FieldGroupLoader|null $field_group_loader Shared field group
	 *                                                    loader. Falls back
	 *                                                    to a fresh
	 *                                                    `FieldGroupLoader`
	 *                                                    (itself backed by a
	 *                                                    fresh
	 *                                                    `FieldTypeRegistry`)
	 *                                                    when omitted — the
	 *                                                    "shared
	 *                                                    FieldTypeRegistry →
	 *                                                    FieldGroupLoader →
	 *                                                    LocationResolver,
	 *                                                    built once" chain
	 *                                                    this class is
	 *                                                    responsible for
	 *                                                    assembling.
	 * @param LocationResolver|null $location_resolver  Shared location
	 *                                                    resolver. Falls back
	 *                                                    to a fresh
	 *                                                    `LocationResolver`
	 *                                                    when omitted
	 *                                                    (stateless, so a
	 *                                                    fresh instance is
	 *                                                    always equivalent).
	 */
	public function __construct(
		?string $blocks_directory = null,
		?FieldGroupLoader $field_group_loader = null,
		?LocationResolver $location_resolver = null
	) {
		$this->blocks_directory  = $blocks_directory ?? ( defined( 'GS_PATH' ) ? GS_PATH . 'blocks/' : '' );
		$this->field_group_loader = $field_group_loader ?? new FieldGroupLoader( null, new FieldTypeRegistry() );
		$this->location_resolver  = $location_resolver ?? new LocationResolver();
	}

	/**
	 * Registers every `blocks/{slug}/block.json` found under
	 * {@see self::$blocks_directory} with WordPress, on `init` (see class
	 * docblock, "HOOK: `init`").
	 *
	 * For each block directory: reads the static `name` straight off its
	 * `block.json` (see class docblock, "WHY THIS CLASS PARSES `block.json`
	 * ITSELF"), computes its `attributes` schema via
	 * {@see self::compute_attributes_for_block()}, then calls
	 * `register_block_type( $block_dir, $args )` — passing the DIRECTORY
	 * (not the parsed metadata) so WordPress core still does its own
	 * `block.json` read/parse/i18n/script-registration pass exactly as it
	 * would for any other metadata-driven block; only `attributes` and
	 * `render_callback` come from `$args` (see "REGISTER_BLOCK_TYPE ARGS
	 * MERGE" below).
	 *
	 * A block directory whose `block.json` cannot be read/parsed, or has no
	 * `name`, is skipped — see {@see self::read_block_name()} — without
	 * affecting any other block directory.
	 *
	 * --- REGISTER_BLOCK_TYPE ARGS MERGE — VERIFIED, NOT ASSUMED ---
	 *
	 * Confirmed directly against this environment's own WordPress core
	 * source (`wp-includes/blocks.php`), not from memory or documentation
	 * alone:
	 * - `register_block_type( $block_type, $args )` (line ~782): when
	 *   `$block_type` is a string and `file_exists( $block_type )` (true for
	 *   a directory containing `block.json`), it delegates to
	 *   `register_block_type_from_metadata( $block_type, $args )`.
	 * - `register_block_type_from_metadata()` (line ~460) reads and decodes
	 *   `block.json` into `$metadata`, builds a `$settings` array from a
	 *   fixed `$property_mappings` list (`name`, `title`, `category`,
	 *   `icon`, `attributes`, `supports`, ... — everything Hero's
	 *   `block.json` currently declares), and then, at line 605:
	 *   `$settings = array_merge( $settings, $args );` — `$args` (this
	 *   class's `attributes` and `render_callback`) is merged IN OVER the
	 *   metadata-derived settings. Since Hero's `block.json` declares no
	 *   `attributes` key at all today, there is nothing to override yet, but
	 *   the mechanism is confirmed: whatever this class passes in `$args`
	 *   always wins on a key-by-key basis, while every key `$args` does NOT
	 *   set (`title`, `name`, `category`, `icon`, `keywords`, `supports`,
	 *   `textdomain`, ...) is left exactly as `block.json` declares it. No
	 *   hand-merging of the JSON is needed or performed by this class.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		foreach ( $this->discover_block_dirs() as $block_dir ) {
			$block_name = $this->read_block_name( $block_dir );

			if ( '' === $block_name ) {
				continue;
			}

			register_block_type(
				$block_dir,
				array(
					'attributes'      => $this->compute_attributes_for_block( $block_name ),
					'render_callback' => array( $this, 'render_block' ),
				)
			);
		}
	}

	/**
	 * Computes the WP block `attributes` schema for one block, WITHOUT the
	 * side effect of registering anything — exposed publicly and separately
	 * from {@see self::register_blocks()} specifically so it is independently
	 * unit-testable (see the plugin's smoke test), and so
	 * `register_blocks()` has a single, non-duplicated place this logic
	 * lives.
	 *
	 * Resolves every field group targeting `$block_name` (context
	 * `array( 'block' => $block_name )`, via
	 * {@see LocationResolver::resolve()}), then walks each resolved group's
	 * top-level `fields` (in the group's own authored order, groups
	 * themselves already sorted by `menu_order` ascending) building one
	 * `array( 'type' => ..., 'default' => ... )` entry per field name — see
	 * class docblock, "ATTRIBUTE TYPE INFERENCE" and "FIELD NAME COLLISION"
	 * for the two judgment calls this involves.
	 *
	 * @param string $block_name Fully-namespaced block name, e.g.
	 *                             `'growsfields/hero'`.
	 * @return array<string, array{type: string, default: mixed}> Keyed by
	 *                                                               field
	 *                                                               name.
	 */
	public function compute_attributes_for_block( string $block_name ): array {
		$context      = array( 'block' => $block_name );
		$field_groups = $this->field_group_loader->get_field_groups();
		$resolved     = $this->location_resolver->resolve( $field_groups, $context );

		$attributes = array();

		foreach ( $resolved as $group ) {
			$fields = isset( $group['fields'] ) && is_array( $group['fields'] ) ? $group['fields'] : array();

			foreach ( $fields as $name => $field_type ) {
				if ( ! $field_type instanceof FieldType ) {
					continue;
				}

				// A field with no `name` (e.g. `tab`/`message` — SCHEMA.md
				// §2.1: these carry no stored value at all, `name` is left
				// `""` by convention) has nothing to expose as a block
				// attribute. Without this guard, every such field would
				// collide on a single meaningless `''` attribute key, the
				// last one processed silently overwriting any before it.
				if ( '' === $name ) {
					continue;
				}

				$default = $field_type->default_value();

				$attributes[ $name ] = array(
					'type'    => $this->infer_attribute_type( $default ),
					'default' => $default,
				);
			}
		}

		return $attributes;
	}

	/**
	 * `render_callback` for every block this class registers. Determines
	 * which block is being rendered from `$block->name` (e.g.
	 * `growsfields/hero` → slug `hero`), locates
	 * `blocks/{slug}/render.php`, and `include`s it with `$attributes`
	 * available as a local variable inside that file — mirroring ACF's own
	 * `renderTemplate` convention, which this project's real theme blocks
	 * already follow, so the pattern is familiar to whoever migrates each
	 * block's markup next.
	 *
	 * Output is captured via `ob_start()`/`ob_get_clean()` rather than
	 * letting the included file's output land directly wherever WordPress
	 * happens to be buffering (or not) at render time — this method's return
	 * value is exactly, and only, what `render.php` produced.
	 *
	 * Returns `''` if no `render.php` exists for the resolved slug — see
	 * class docblock, "MISSING `render.php`".
	 *
	 * @param array<string, mixed> $attributes Block attributes, matching the
	 *                                           schema
	 *                                           {@see self::compute_attributes_for_block()}
	 *                                           built for this block.
	 * @param string                $content    Block inner content (unused by
	 *                                           every current Growsfields
	 *                                           block, which are all
	 *                                           attribute-driven, not
	 *                                           InnerBlocks-based — accepted
	 *                                           only because it is part of
	 *                                           WP's fixed `render_callback`
	 *                                           signature).
	 * @param \WP_Block             $block      The block instance being
	 *                                           rendered.
	 * @return string
	 */
	public function render_block( array $attributes, string $content, \WP_Block $block ): string {
		$slug = $this->slug_from_block_name( (string) $block->name );

		if ( '' === $slug ) {
			return '';
		}

		$render_file = rtrim( $this->blocks_directory, '/\\' ) . '/' . $slug . '/render.php';

		if ( ! file_exists( $render_file ) ) {
			return '';
		}

		ob_start();
		include $render_file;

		return (string) ob_get_clean();
	}

	/**
	 * Globs {@see self::$blocks_directory} for every `{slug}/block.json`,
	 * returning the containing directory path for each (not the file path
	 * itself — {@see self::register_blocks()} passes the DIRECTORY to
	 * `register_block_type()`).
	 *
	 * @return array<int, string> Absolute directory paths.
	 */
	private function discover_block_dirs(): array {
		if ( '' === $this->blocks_directory ) {
			return array();
		}

		$pattern = rtrim( $this->blocks_directory, '/\\' ) . '/*/block.json';
		$files   = glob( $pattern );

		if ( false === $files ) {
			return array();
		}

		return array_map( 'dirname', $files );
	}

	/**
	 * Reads and parses one block directory's `block.json`, returning its
	 * declared `name`, or `''` if the file is unreadable, not valid JSON, or
	 * has no `name` — see class docblock, "WHY THIS CLASS PARSES
	 * `block.json` ITSELF". A `''` result means the caller
	 * ({@see self::register_blocks()}) skips this block directory entirely,
	 * isolating the failure to just that one block.
	 *
	 * @param string $block_dir Absolute path to the block's own directory.
	 * @return string
	 */
	private function read_block_name( string $block_dir ): string {
		$file = rtrim( $block_dir, '/\\' ) . '/block.json';

		$contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- trusted local plugin file, not a remote/user-supplied URL.

		if ( false === $contents ) {
			return '';
		}

		$decoded = json_decode( $contents, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return '';
		}

		return isset( $decoded['name'] ) ? (string) $decoded['name'] : '';
	}

	/**
	 * Extracts the `{slug}` portion of a fully-namespaced block name, e.g.
	 * `'growsfields/hero'` → `'hero'`, used to locate that block's
	 * `render.php` on disk. A name with no `/` (malformed) returns `''`.
	 *
	 * @param string $block_name Fully-namespaced block name.
	 * @return string
	 */
	private function slug_from_block_name( string $block_name ): string {
		if ( false === strpos( $block_name, '/' ) ) {
			return '';
		}

		$parts = explode( '/', $block_name );

		return (string) end( $parts );
	}

	/**
	 * Infers a WP block attribute `type` from the actual PHP shape of a
	 * field's `default_value()` — see class docblock, "ATTRIBUTE TYPE
	 * INFERENCE" for the full reasoning and the four buckets.
	 *
	 * @param mixed $default_value Whatever {@see FieldType::default_value()}
	 *                               returned for one field.
	 * @return string One of `'boolean'`, `'number'`, `'string'`, `'array'`,
	 *                  `'object'`.
	 */
	private function infer_attribute_type( $default_value ): string {
		if ( is_bool( $default_value ) ) {
			return 'boolean';
		}

		if ( is_int( $default_value ) || is_float( $default_value ) ) {
			return 'number';
		}

		if ( is_string( $default_value ) ) {
			return 'string';
		}

		if ( is_array( $default_value ) ) {
			return $this->is_list_array( $default_value ) ? 'array' : 'object';
		}

		// Anything else (in practice: null) — see class docblock's
		// "ATTRIBUTE TYPE INFERENCE" closing paragraph for why 'string' is
		// the safe fallback here rather than throwing.
		return 'string';
	}

	/**
	 * Back-compat equivalent of PHP 8.1's `array_is_list()` — this project's
	 * `composer.json` targets `"php": ">=8.0"` (checked before writing this
	 * method), one minor version below `array_is_list()`'s introduction, so
	 * it cannot be used directly. Standard reimplementation: an array is a
	 * "list" if its keys are exactly `0, 1, 2, ..., count() - 1` in that
	 * order; the empty array is a list by definition (matching
	 * `array_is_list()`'s own documented behaviour for `[]`).
	 *
	 * @param array<mixed> $array Array to check.
	 * @return bool
	 */
	private function is_list_array( array $array ): bool {
		if ( array() === $array ) {
			return true;
		}

		return array_keys( $array ) === range( 0, count( $array ) - 1 );
	}
}
