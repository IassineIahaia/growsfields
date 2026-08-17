<?php
/**
 * Group (single non-repeating set of nested sub-fields) field type.
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
 * Class Group
 *
 * ACF-compatible 'group' field type: a *single*, always-present set of
 * nested sub-fields, displayed together as a visual sub-section. Structurally
 * this is {@see Repeater} with exactly one row that can never be added,
 * removed, or reordered — the stored value is one associative array keyed by
 * sub-field name (`sub_field_name => sanitized_value`), not a list of rows.
 * There is no row concept here at all.
 *
 * Unlike Repeater, a Group's own `sub_fields` are NOT restricted from
 * containing another `repeater` or another `group`. Nesting a Repeater
 * inside a Group (or a Group inside a Group) is normal, common ACF usage and
 * structurally different from a Repeater directly containing another
 * Repeater, which is the actual restriction {@see Repeater} enforces on
 * itself (see that class's docblock — "CONFIRMED, 2026-08-17"). This class
 * therefore imposes no extra type restriction of its own beyond "the
 * sub-field's type must be a registered field type" — when a configured
 * sub-field happens to be a `repeater`, building it via
 * {@see FieldTypeRegistry::make()} instantiates a real {@see Repeater},
 * whose own constructor already enforces its own no-nested-repeater rule
 * against *its* `sub_fields`; this class does not re-implement that check
 * and does not need to.
 *
 * Security: like {@see Repeater}, this class is orchestration, not a
 * sanitization boundary in its own right. `sanitize()` walks the configured
 * sub-fields and, for each one, delegates to that sub-field's own
 * `FieldType::sanitize()` — the exact same method already trusted as the
 * security boundary for that type when used as a top-level field. This
 * class's own added responsibilities are: building the right `FieldType` per
 * sub-field via the registry, filling in defaults for missing keys, and
 * dropping any submitted key that doesn't correspond to a configured
 * sub-field (so a tampered submission can't smuggle arbitrary extra keys
 * into stored data). It never sanitizes a value itself — see
 * {@see FieldType::sanitize()}.
 *
 * @package Growsfields
 */
class Group extends FieldType {

	/**
	 * Registry used to instantiate `FieldType` objects for `sub_fields`
	 * entries. See {@see Repeater::$registry} for why this is injected
	 * rather than looked up from a global/singleton.
	 *
	 * @var FieldTypeRegistry
	 */
	private FieldTypeRegistry $registry;

	/**
	 * Sub-field `FieldType` instances, keyed by sub-field name, built once
	 * at construction time from the `sub_fields` config. Built eagerly (not
	 * lazily) so a malformed `sub_fields` entry fails fast at construction
	 * time — see {@see Repeater::$sub_field_types} for the same reasoning.
	 *
	 * Declared `protected` (not `private`) rather than exposed only via a
	 * getter, because {@see Clone_} extends this class specifically to
	 * reuse this exact map when building its own `sub_fields` JS schema —
	 * see that class's docblock.
	 *
	 * @var array<string, FieldType>
	 */
	protected array $sub_field_types = array();

	/**
	 * Constructor.
	 *
	 * Same shape and reasoning as {@see Repeater::__construct()}: a second,
	 * optional `$registry` parameter (rather than changing
	 * `FieldType::__construct()`'s signature) so a Group can build its own
	 * sub-field `FieldType` instances, defaulting to a fresh
	 * `FieldTypeRegistry` when the caller doesn't already hold a shared one.
	 * Eagerly validates and builds `$this->sub_field_types` here (see
	 * {@see self::build_sub_field_types()}).
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
	 * @throws \InvalidArgumentException If a `sub_fields` entry is missing a
	 *                                    `type` or names an unregistered
	 *                                    type. (Unlike Repeater, a
	 *                                    `repeater` or `group` sub-field is
	 *                                    explicitly allowed — see class
	 *                                    docblock.)
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
		return 'group';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Group';
	}

	/**
	 * Sanitizes a submitted group value.
	 *
	 * `$value` is expected to be a single associative array keyed by
	 * sub-field name — there is no row concept, unlike {@see Repeater}. A
	 * `$value` that is not an array at all (a tampered submission, or simply
	 * an unset field) is not trusted to be partially valid — it is treated
	 * as an empty set, which in practice means every configured sub-field
	 * falls back to its own default (see {@see self::sanitize_sub_field_values()}).
	 *
	 * @param mixed $value Raw value as submitted (expected: an associative
	 *                      array keyed by sub-field name).
	 * @return array<string, mixed> Sanitized sub-field values.
	 */
	public function sanitize( $value ) {
		return $this->sanitize_sub_field_values( $value );
	}

	/**
	 * {@inheritDoc}
	 *
	 * An associative array of every configured sub-field's own
	 * `default_value()` — never a `default_value` config option override
	 * like {@see Repeater::default_value()} has, since a Group has no
	 * top-level "whole field" concept to override; only its individual
	 * sub-fields do.
	 */
	public function default_value() {
		$defaults = array();

		foreach ( $this->sub_field_types as $sub_field_name => $sub_field_type ) {
			$defaults[ $sub_field_name ] = $sub_field_type->default_value();
		}

		return $defaults;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Adds ACF's Group `layout` option (`block` / `table` / `row`), passed
	 * through as-is with `block` as the default. `sub_fields` is each
	 * configured sub-field's own `to_js_schema()` output (built via the
	 * registry), same recursive approach as {@see Repeater::to_js_schema()},
	 * so the generic editor (Phase 4) can recursively render nested inputs
	 * without its own copy of this class's sub-field-building logic.
	 */
	public function to_js_schema(): array {
		return array_merge(
			$this->base_js_schema(),
			array(
				'layout'     => $this->get_option( 'layout', 'block' ),
				'sub_fields' => $this->sub_fields_schema(),
			)
		);
	}

	/**
	 * Computes the sanitized `sub_field_name => value` map shared by
	 * {@see self::sanitize()} and reused by {@see Clone_::sanitize()}.
	 *
	 * For each configured sub-field: its value is sanitized through that
	 * sub-field's own `sanitize()` when `$value` (coerced to an array, or
	 * treated as empty when not one) contains a matching key, or filled with
	 * that sub-field's own `default_value()` when the key is missing. Any
	 * key in `$value` that doesn't correspond to a configured sub-field is
	 * silently dropped, not stored — the same tamper-resistance
	 * {@see Repeater::sanitize()} applies per row, applied here to the one
	 * (implicit) row a Group always has.
	 *
	 * @param mixed $value Raw value as submitted.
	 * @return array<string, mixed>
	 */
	protected function sanitize_sub_field_values( $value ): array {
		$raw    = is_array( $value ) ? $value : array();
		$result = array();

		foreach ( $this->sub_field_types as $sub_field_name => $sub_field_type ) {
			$result[ $sub_field_name ] = array_key_exists( $sub_field_name, $raw )
				? $sub_field_type->sanitize( $raw[ $sub_field_name ] )
				: $sub_field_type->default_value();
		}

		return $result;
	}

	/**
	 * Each configured sub-field's own `to_js_schema()` output, in
	 * configuration order. Shared with {@see Clone_::to_js_schema()} so it
	 * doesn't need its own copy of this loop.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function sub_fields_schema(): array {
		$schema = array();

		foreach ( $this->sub_field_types as $sub_field_type ) {
			$schema[] = $sub_field_type->to_js_schema();
		}

		return $schema;
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
	 * keyed by sub-field name. Unlike
	 * {@see Repeater::build_sub_field_types()}, a `repeater` (or `group`)
	 * sub-field type is explicitly allowed — see class docblock.
	 *
	 * @param array<int, array<string, mixed>> $sub_fields_config Raw
	 *                                                              `sub_fields`
	 *                                                              config.
	 * @return array<string, FieldType>
	 * @throws \InvalidArgumentException If an entry is missing `type` or
	 *                                    names an unregistered type.
	 */
	private function build_sub_field_types( array $sub_fields_config ): array {
		$sub_field_types = array();

		foreach ( $sub_fields_config as $sub_field_config ) {
			$sub_field_config = is_array( $sub_field_config ) ? $sub_field_config : array();
			$type             = isset( $sub_field_config['type'] ) ? (string) $sub_field_config['type'] : '';

			if ( '' === $type ) {
				throw new \InvalidArgumentException(
					'Growsfields: Group sub_fields entry is missing a "type".'
				);
			}

			if ( ! $this->registry->is_registered( $type ) ) {
				throw new \InvalidArgumentException(
					sprintf(
						'Growsfields: Group sub_fields entry names unknown field type "%s".',
						$type
					)
				);
			}

			// No extra type restriction here (unlike Repeater's no-nested-
			// repeater check) — if $type is 'repeater', the Repeater built
			// below enforces its own no-nested-repeater rule against *its*
			// sub_fields; a group is never itself the thing being nested
			// inside a repeater, so nothing here needs to re-check that.
			$sub_field_type = $this->registry->make( $type, $sub_field_config );

			$sub_field_types[ $sub_field_type->get_name() ] = $sub_field_type;
		}

		return $sub_field_types;
	}
}
