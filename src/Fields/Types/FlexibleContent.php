<?php
/**
 * Flexible Content (repeatable rows, each a named layout of sub-fields)
 * field type.
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
 * Class FlexibleContent
 *
 * ACF-compatible 'flexible_content' field type: like {@see Repeater}, a
 * repeatable set of rows — but each row is an instance of one of several
 * named **layouts**, and different rows can be different layouts. Each
 * layout carries its own, independent `sub_fields` set. Config shape
 * mirrors ACF Pro's own documented Flexible Content structure — a `layouts`
 * option, an array of layout definitions:
 *
 *     'layouts' => array(
 *         array(
 *             'name'       => 'text_block',
 *             'label'      => 'Text Block',
 *             'sub_fields' => array( array( 'type' => 'wysiwyg', 'name' => 'body', ... ) ),
 *         ),
 *         array(
 *             'name'       => 'image_block',
 *             'label'      => 'Image Block',
 *             'sub_fields' => array( array( 'type' => 'image', 'name' => 'photo', ... ) ),
 *         ),
 *     ),
 *
 * Stored value shape is an array of rows, each row an associative array
 * carrying which layout produced it plus that layout's own sanitized
 * fields:
 *
 *     array(
 *         array( 'layout' => 'text_block', 'body' => '...' ),
 *         array( 'layout' => 'image_block', 'photo' => 123 ),
 *     )
 *
 * — this is ACF's real stored shape for Flexible Content (a `layout` key
 * alongside the row's own fields, not a nested wrapper).
 *
 * Security: same orchestration framing as {@see Repeater} and {@see Group}
 * — this class is not a sanitization boundary in its own right. For a row
 * whose layout IS recognized, `sanitize()` delegates each field to that
 * layout's own sub-field's `FieldType::sanitize()`, exactly like one
 * Repeater row. The one behaviour this class adds beyond Repeater/Group:
 * for a row whose `layout` key does NOT match any configured layout, the
 * *entire row* is dropped rather than partially sanitized. This is
 * deliberately different from Repeater's per-cell default-filling — a
 * missing cell in a Repeater row is one unknown value inside an otherwise
 * known shape, and can safely be defaulted; an unrecognized layout name
 * means the entire *shape* of the row (which keys it should even have) is
 * unknown, so there is nothing safe to sanitize against and no default
 * shape to fill in. Guessing a layout, or defaulting to some fixed layout,
 * would silently reinterpret a tampered/stale submission as if the editor
 * had chosen that layout, which this class refuses to do.
 *
 * @package Growsfields
 */
class FlexibleContent extends FieldType {

	/**
	 * Registry used to instantiate `FieldType` objects for each layout's
	 * `sub_fields` entries. See {@see Repeater::$registry}.
	 *
	 * @var FieldTypeRegistry
	 */
	private FieldTypeRegistry $registry;

	/**
	 * Sub-field `FieldType` instances, keyed first by layout name and then
	 * by sub-field name, built once at construction time from the
	 * `layouts` config. Built eagerly for the same fail-fast reasoning as
	 * {@see Repeater::$sub_field_types}.
	 *
	 * @var array<string, array<string, FieldType>>
	 */
	private array $layouts = array();

	/**
	 * Each layout's human-readable label, keyed by layout name. Kept
	 * separate from {@see self::$layouts} because that map's declared shape
	 * (`layout_name => (sub_field_name => FieldType)`) has no room for a
	 * label alongside the sub-field map.
	 *
	 * @var array<string, string>
	 */
	private array $layout_labels = array();

	/**
	 * Constructor.
	 *
	 * Same shape and reasoning as {@see Repeater::__construct()} /
	 * {@see Group::__construct()}. Eagerly validates and builds
	 * `$this->layouts` here (see {@see self::build_layouts()}).
	 *
	 * @param array<string, mixed>   $field_config Per-instance field
	 *                                              configuration; expected
	 *                                              to include a `layouts`
	 *                                              key shaped like the
	 *                                              class docblock's
	 *                                              example.
	 * @param FieldTypeRegistry|null $registry     Registry used to build
	 *                                              `FieldType` instances for
	 *                                              each layout's
	 *                                              `sub_fields`. Falls back
	 *                                              to a fresh
	 *                                              `FieldTypeRegistry` when
	 *                                              omitted.
	 * @throws \InvalidArgumentException If a layout is missing `name`, two
	 *                                    layouts share the same `name`, or a
	 *                                    layout's own `sub_fields` entry is
	 *                                    missing a `type` or names an
	 *                                    unregistered type.
	 */
	public function __construct( array $field_config = array(), ?FieldTypeRegistry $registry = null ) {
		parent::__construct( $field_config );

		$this->registry = $registry ?? new FieldTypeRegistry();

		$this->layouts = $this->build_layouts( $this->get_layouts_config() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type(): string {
		return 'flexible_content';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_type_label(): string {
		return 'Flexible Content';
	}

	/**
	 * Sanitizes a submitted set of flexible content rows.
	 *
	 * `$value` is expected to be a list of rows, each row an associative
	 * array with a `layout` key plus that layout's own fields. A `$value`
	 * that is not an array at all is treated as zero rows rather than
	 * coerced, same as {@see Repeater::sanitize()}.
	 *
	 * For each row: its `layout` key is read and matched against the
	 * configured layouts. If it does not match any of them, the row is
	 * dropped entirely — see the class docblock for why this differs from
	 * Repeater's per-cell defaulting. If it does match, every field
	 * configured for *that layout* ends up present in the output row: its
	 * value is sanitized through that sub-field's own `sanitize()`, or
	 * filled with that sub-field's own `default_value()` when the
	 * submitted row is missing that key. Any submitted key that doesn't
	 * correspond to a sub-field configured for the row's own layout is
	 * silently dropped (including any sub-field name that only exists on a
	 * *different* layout). The resulting row's `layout` key is always set
	 * from the matched, validated layout name — never trusted verbatim from
	 * elsewhere in the submission.
	 *
	 * When a `max` row-count option is configured (> 0), rows beyond it —
	 * counted across all layouts combined — are truncated. `min` is
	 * deliberately not enforced here, same reasoning as
	 * {@see Repeater::sanitize()}.
	 *
	 * @param mixed $value Raw value as submitted (expected: a list of
	 *                      associative row arrays, each with a `layout`
	 *                      key).
	 * @return array<int, array<string, mixed>> Sanitized rows.
	 */
	public function sanitize( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$rows = array();

		foreach ( array_values( $value ) as $raw_row ) {
			$raw_row     = is_array( $raw_row ) ? $raw_row : array();
			$layout_name = isset( $raw_row['layout'] ) ? (string) $raw_row['layout'] : '';

			if ( ! isset( $this->layouts[ $layout_name ] ) ) {
				// Unrecognized layout: the row's entire shape is unknown,
				// not just one cell — drop it rather than guess. See class
				// docblock.
				continue;
			}

			$row = array( 'layout' => $layout_name );

			foreach ( $this->layouts[ $layout_name ] as $sub_field_name => $sub_field_type ) {
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
	 * An empty set of rows, same as {@see Repeater::default_value()} —
	 * including the same "an explicitly configured `default_value` option
	 * is re-sanitized, not trusted as-is" behaviour, since a hand-edited
	 * field group JSON is no more trustworthy an input than a form
	 * submission.
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
	 * `layouts` describes every configured layout for the editor: its name,
	 * label, and its own sub-fields' `to_js_schema()` output (recursive,
	 * same approach as {@see Repeater::to_js_schema()}), so the generic
	 * editor (Phase 4) knows what each layout looks like and can render the
	 * right inputs once a row picks one. `button_label`/`min`/`max` mirror
	 * Repeater's own row-count/labelling options, applied here across all
	 * layouts combined rather than per layout.
	 */
	public function to_js_schema(): array {
		$layouts_schema = array();

		foreach ( $this->layouts as $layout_name => $sub_field_types ) {
			$sub_fields_schema = array();

			foreach ( $sub_field_types as $sub_field_type ) {
				$sub_fields_schema[] = $sub_field_type->to_js_schema();
			}

			$layouts_schema[] = array(
				'name'       => $layout_name,
				'label'      => $this->layout_labels[ $layout_name ],
				'sub_fields' => $sub_fields_schema,
			);
		}

		return array_merge(
			$this->base_js_schema(),
			array(
				'layouts'      => $layouts_schema,
				'button_label' => $this->get_option( 'button_label', 'Add Row' ),
				'min'          => (int) $this->get_option( 'min', 0 ),
				'max'          => (int) $this->get_option( 'max', 0 ),
			)
		);
	}

	/**
	 * Reads the raw `layouts` config entry as passed to the constructor.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_layouts_config(): array {
		$layouts = $this->get_option( 'layouts', array() );

		return is_array( $layouts ) ? $layouts : array();
	}

	/**
	 * Validates and builds the per-layout sub-field `FieldType` map, keyed
	 * by layout name, plus {@see self::$layout_labels}.
	 *
	 * @param array<int, array<string, mixed>> $layouts_config Raw `layouts`
	 *                                                           config.
	 * @return array<string, array<string, FieldType>>
	 * @throws \InvalidArgumentException If a layout is missing `name`, two
	 *                                    layouts share the same `name`, or a
	 *                                    layout's `sub_fields` entry is
	 *                                    invalid (see
	 *                                    {@see self::build_sub_field_types()}).
	 */
	private function build_layouts( array $layouts_config ): array {
		$layouts = array();

		foreach ( $layouts_config as $layout_config ) {
			$layout_config = is_array( $layout_config ) ? $layout_config : array();
			$name          = isset( $layout_config['name'] ) ? (string) $layout_config['name'] : '';

			if ( '' === $name ) {
				throw new \InvalidArgumentException(
					'Growsfields: Flexible Content layout entry is missing a "name".'
				);
			}

			if ( array_key_exists( $name, $layouts ) ) {
				throw new \InvalidArgumentException(
					sprintf(
						'Growsfields: Flexible Content has two layouts sharing the name "%s".',
						$name
					)
				);
			}

			$this->layout_labels[ $name ] = isset( $layout_config['label'] ) ? (string) $layout_config['label'] : $name;

			$sub_fields_config = isset( $layout_config['sub_fields'] ) && is_array( $layout_config['sub_fields'] )
				? $layout_config['sub_fields']
				: array();

			$layouts[ $name ] = $this->build_sub_field_types( $sub_fields_config, $name );
		}

		return $layouts;
	}

	/**
	 * Validates and builds one `FieldType` instance per `sub_fields` entry
	 * for a single layout, keyed by sub-field name. Same validation as
	 * {@see Group::build_sub_field_types()} (missing/unregistered type only
	 * — no extra type restriction; a `repeater` or `group` sub-field is
	 * allowed, and a `repeater` sub-field's own no-nested-repeater rule is
	 * enforced by `Repeater`'s own constructor when it is built below).
	 *
	 * @param array<int, array<string, mixed>> $sub_fields_config Raw
	 *                                                              `sub_fields`
	 *                                                              config
	 *                                                              for one
	 *                                                              layout.
	 * @param string                            $layout_name       Owning
	 *                                                              layout's
	 *                                                              name,
	 *                                                              used only
	 *                                                              for error
	 *                                                              messages.
	 * @return array<string, FieldType>
	 * @throws \InvalidArgumentException If an entry is missing `type` or
	 *                                    names an unregistered type.
	 */
	private function build_sub_field_types( array $sub_fields_config, string $layout_name ): array {
		$sub_field_types = array();

		foreach ( $sub_fields_config as $sub_field_config ) {
			$sub_field_config = is_array( $sub_field_config ) ? $sub_field_config : array();
			$type             = isset( $sub_field_config['type'] ) ? (string) $sub_field_config['type'] : '';

			if ( '' === $type ) {
				throw new \InvalidArgumentException(
					sprintf(
						'Growsfields: Flexible Content layout "%s" sub_fields entry is missing a "type".',
						$layout_name
					)
				);
			}

			if ( ! $this->registry->is_registered( $type ) ) {
				throw new \InvalidArgumentException(
					sprintf(
						'Growsfields: Flexible Content layout "%1$s" sub_fields entry names unknown field type "%2$s".',
						$layout_name,
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
