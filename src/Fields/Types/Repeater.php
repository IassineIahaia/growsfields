<?php
/**
 * Repeater (repeatable rows of nested sub-fields) field type.
 *
 * @package Growsfields
 */

namespace Growsfields\Fields\Types;

use Growsfields\Fields\FieldType;
use Growsfields\Fields\FieldTypeRegistry;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Repeater
 *
 * ACF-compatible 'repeater' field type: a repeatable set of "rows", each row
 * an associative array keyed by sub-field name. Kept as slug `repeater`
 * matching this project's `acf-json` exports (e.g. `field_67bc28bf1c92b` /
 * "Menu's" on the Options page field group) for a smooth Phase 3 migration.
 *
 * Deliberate v1 restriction — no nested repeaters:
 * A Repeater's own `sub_fields` may not themselves be of type `repeater`.
 * This class throws `InvalidArgumentException` at construction time if any
 * configured sub-field is a repeater. This is NOT a technical limitation —
 * nothing about the sanitize()/to_js_schema() recursion below would
 * actually break if a sub-field were itself a Repeater — it is a deliberate
 * scope cut for this batch (see `plugin-blocos-checklist-v2.md`, Fase 2,
 * Repeater: "Decidir já: limite de profundidade (ex.: repeater não pode
 * conter repeater, para simplificar) — perguntar ao utilizador se aceita
 * essa limitação"). That question was never actually put to the user
 * before this class was written, so this defaults to the conservative "no
 * nesting" answer rather than silently allowing arbitrary depth. Notably,
 * this project's own real `acf-json` data
 * (`themes/starter-2026-iassine/acf-json/group_67bc28be09501.json`, field
 * "Menu's" / `menus`) *does* contain a two-level-deep repeater (`menus` ->
 * `menu_items`, both type `repeater`) — so this restriction will need
 * lifting (or that specific field group will need reshaping) before that
 * field group can round-trip through this engine unchanged. Revisit once
 * the user is actually asked and this is no longer an open question.
 *
 * Security: this class is orchestration, not a sanitization boundary in its
 * own right. `sanitize()` loops rows and sub-fields and, for each cell,
 * delegates to that sub-field's own `FieldType::sanitize()` — the exact
 * same method already trusted as the security boundary for that type when
 * it is used as a top-level field. Repeater's own added responsibilities
 * are: building the right `FieldType` per sub-field via the registry,
 * filling in defaults for missing cells, and dropping any row key that
 * doesn't correspond to a configured sub-field (so a tampered submission
 * can't smuggle arbitrary extra keys into stored data). Repeater never
 * sanitizes a cell value itself — see {@see FieldType::sanitize()}.
 *
 * @package Growsfields
 */
class Repeater extends FieldType {

	/**
	 * Registry used to instantiate `FieldType` objects for `sub_fields`
	 * entries. Injected via the constructor (rather than looked up from a
	 * global/singleton) so this class stays unit-testable with a
	 * purpose-built registry.
	 *
	 * @var FieldTypeRegistry
	 */
	private FieldTypeRegistry $registry;

	/**
	 * Sub-field `FieldType` instances, keyed by sub-field name, built once
	 * at construction time from the `sub_fields` config. Built eagerly
	 * (not lazily on first `sanitize()`/`to_js_schema()` call) so a
	 * malformed `sub_fields` entry (missing/unknown type, nested repeater)
	 * fails fast at the point this field is constructed — e.g. when
	 * `FieldGroupLoader` (Phase 3) builds it from a field group's JSON —
	 * rather than at the point it first happens to be used.
	 *
	 * @var array<string, FieldType>
	 */
	private array $sub_field_types = array();

	/**
	 * Constructor.
	 *
	 * `FieldType::__construct()` takes only `$field_config`, since identity
	 * and behaviour for the other nine Batch A-1 types need nothing else.
	 * Repeater is the first type that needs to build other `FieldType`
	 * instances of its own (one per `sub_fields` entry), which requires a
	 * `FieldTypeRegistry`. Rather than changing `FieldType`'s own
	 * constructor signature — which would force every other concrete type,
	 * and every existing call site that constructs one directly, to start
	 * accepting/passing a registry they have no use for — this adds a
	 * second, optional constructor parameter local to `Repeater`. When
	 * omitted, a fresh `FieldTypeRegistry` is constructed internally so
	 * `new Repeater( $config )` keeps working standalone (tests, ad-hoc
	 * use) without forcing every caller to always wire one up explicitly.
	 * Production call sites that already hold a shared registry instance
	 * (`FieldGroupLoader` in Phase 3, the block editor schema endpoint)
	 * should pass it explicitly instead, so a field group with several
	 * Repeaters doesn't have each one silently build — and re-run the
	 * `gs_register_field_type` filter through — its own separate registry.
	 *
	 * Eagerly validates and builds `$this->sub_field_types` here (see
	 * {@see self::build_sub_field_types()}) so a malformed `sub_fields`
	 * config throws immediately, at construction time.
	 *
	 * @param array<string, mixed>   $field_config Per-instance field
	 *                                              configuration; expected
	 *                                              to include a
	 *                                              `sub_fields` key shaped
	 *                                              like
	 *                                              `array( array( 'type' => 'text', 'name' => 'title', ... ), ... )`
	 *                                              — the same shape passed
	 *                                              to
	 *                                              {@see FieldTypeRegistry::make()}.
	 * @param FieldTypeRegistry|null $registry     Registry used to build
	 *                                              `FieldType` instances
	 *                                              for `sub_fields`. Falls
	 *                                              back to a fresh
	 *                                              `FieldTypeRegistry` when
	 *                                              omitted.
	 * @throws \InvalidArgumentException If a `sub_fields` entry is missing
	 *                                    a `type`, names an unregistered
	 *                                    type, or is itself type
	 *                                    `repeater` (see class docblock).
	 */
	public function __construct( array $field_config = array(), ?FieldTypeRegistry $registry = null ) {
		parent::__construct( $field_config );

		$this->registry = $registry ?? new FieldTypeRegistry();

		$this->sub_field_types = $this->build_sub_field_types( $this->get_sub_fields_config() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'repeater';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Repeater';
	}

	/**
	 * Sanitizes a submitted set of repeater rows.
	 *
	 * `$value` is expected to be a list of rows, each row itself an
	 * associative array keyed by sub-field name. A `$value` that is not an
	 * array at all (a tampered submission, or simply an unset field) is not
	 * trusted to be partially valid — it is treated as zero rows rather
	 * than coerced.
	 *
	 * For each row, every configured sub-field ends up present in the
	 * output: its value is sanitized through that sub-field's own
	 * `sanitize()` (reusing the real sanitizer for that type rather than
	 * reimplementing it here), or — if the row is missing that sub-field's
	 * key entirely — filled with that sub-field's own `default_value()`.
	 * Any row key that doesn't correspond to a configured sub-field is
	 * silently dropped, not stored, so a tampered submission can't inject
	 * arbitrary extra keys into a row.
	 *
	 * When a `max` row-count option is configured (> 0), rows beyond it are
	 * truncated. `min` is deliberately NOT enforced here — see
	 * {@see self::to_js_schema()}, where it is round-tripped for the editor
	 * to enforce instead. Fabricating rows to satisfy a minimum is a
	 * UI/validation-message concern for a later phase, not something this
	 * method should silently invent data for.
	 *
	 * @param mixed $value Raw value as submitted (expected: a list of
	 *                      associative row arrays).
	 * @return array<int, array<string, mixed>> Sanitized rows.
	 */
	public function sanitize( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$rows = array();

		foreach ( array_values( $value ) as $raw_row ) {
			$raw_row = is_array( $raw_row ) ? $raw_row : array();
			$row     = array();

			foreach ( $this->sub_field_types as $sub_field_name => $sub_field_type ) {
				$row[ $sub_field_name ] = array_key_exists( $sub_field_name, $raw_row )
					? $sub_field_type->sanitize( $raw_row[ $sub_field_name ] )
					: $sub_field_type->default_value();
			}

			$rows[] = $row;
		}

		$max = (int) $this->get_option( 'max', 0 );

		if ( $max > 0 && count( $rows ) > $max ) {
			$rows = array_slice( $rows, 0, $max );
		}

		return $rows;
	}

	/**
	 * {@inheritDoc}
	 *
	 * An empty set of rows (`array()`), unless a `default_value` option is
	 * explicitly configured — in which case it is run through
	 * {@see self::sanitize()} exactly like any other submitted value,
	 * rather than trusted as already-sanitized config. A hand-edited field
	 * group JSON is no more trustworthy an input than a form submission.
	 */
	public function default_value() {
		$configured = $this->get_option( 'default_value', array() );

		if ( ! is_array( $configured ) || array() === $configured ) {
			return array();
		}

		return $this->sanitize( $configured );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Adds the layout/row-count/label options ACF's own repeater carries.
	 * Defaults are matched to this project's real `acf-json` usage — see
	 * `themes/starter-2026-iassine/acf-json/group_67bc28be09501.json`'s
	 * "Menu's" field (`field_67bc28bf1c92b`): `"layout": "table"`,
	 * `"min": 0`, `"max": 0`, `"collapsed": ""`. `button_label` in that
	 * same JSON is the project-specific "Nieuwe regel" rather than a
	 * generic default, so this class falls back to ACF's own stock label
	 * "Add Row" when a field group doesn't set one.
	 *
	 * `sub_fields` is each configured sub-field's own `to_js_schema()`
	 * output (built via the registry), so the generic editor (Phase 4) can
	 * recursively render nested inputs without needing its own copy of
	 * this class's sub-field-building logic.
	 */
	public function to_js_schema(): array {
		$sub_fields_schema = array();

		foreach ( $this->sub_field_types as $sub_field_type ) {
			$sub_fields_schema[] = $sub_field_type->to_js_schema();
		}

		return array_merge(
			$this->base_js_schema(),
			array(
				'layout'       => $this->get_option( 'layout', 'table' ),
				'button_label' => $this->get_option( 'button_label', 'Add Row' ),
				'collapsed'    => $this->get_option( 'collapsed', '' ),
				'min'          => (int) $this->get_option( 'min', 0 ),
				'max'          => (int) $this->get_option( 'max', 0 ),
				'sub_fields'   => $sub_fields_schema,
			)
		);
	}

	/**
	 * Reads the raw `sub_fields` config entry as passed to the constructor.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_sub_fields_config(): array {
		$sub_fields = $this->get_option( 'sub_fields', array() );

		return is_array( $sub_fields ) ? $sub_fields : array();
	}

	/**
	 * Validates and builds one `FieldType` instance per `sub_fields` entry,
	 * keyed by sub-field name.
	 *
	 * @param array<int, array<string, mixed>> $sub_fields_config Raw
	 *                                                              `sub_fields`
	 *                                                              config.
	 * @return array<string, FieldType>
	 * @throws \InvalidArgumentException If an entry is missing `type`,
	 *                                    names an unregistered type, or is
	 *                                    itself type `repeater`.
	 */
	private function build_sub_field_types( array $sub_fields_config ): array {
		$sub_field_types = array();

		foreach ( $sub_fields_config as $sub_field_config ) {
			$sub_field_config = is_array( $sub_field_config ) ? $sub_field_config : array();
			$type             = isset( $sub_field_config['type'] ) ? (string) $sub_field_config['type'] : '';

			if ( '' === $type ) {
				throw new \InvalidArgumentException(
					'Growsfields: Repeater sub_fields entry is missing a "type".'
				);
			}

			if ( 'repeater' === $type ) {
				throw new \InvalidArgumentException(
					'Growsfields: Repeater sub_fields cannot themselves be type "repeater" ' .
					'— nested repeaters are a deliberate v1 restriction pending explicit ' .
					'product confirmation (see the class docblock on Repeater), not a ' .
					'technical limitation.'
				);
			}

			if ( ! $this->registry->is_registered( $type ) ) {
				throw new \InvalidArgumentException(
					sprintf(
						'Growsfields: Repeater sub_fields entry names unknown field type "%s".',
						$type
					)
				);
			}

			$sub_field_type = $this->registry->make( $type, $sub_field_config );

			$sub_field_types[ $sub_field_type->get_name() ] = $sub_field_type;
		}

		return $sub_field_types;
	}
}
