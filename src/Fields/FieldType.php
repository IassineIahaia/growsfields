<?php
/**
 * Abstract contract every concrete field type must implement.
 *
 * @package Growsfields
 */

namespace Growsfields\Fields;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FieldType
 *
 * Base class for every field type in the fields engine (Text, Textarea,
 * Repeater, Gallery, ...). A concrete subclass represents both:
 *
 * - The *type* itself (identity: a slug and a human label, plus the
 *   behaviour shared by every instance of that type: sanitizing, default
 *   value, JS schema shape).
 * - One *instance* of that type as configured in a field group definition
 *   (name, label, instructions, required, type-specific options). Instance
 *   config is passed into the constructor as `$field_config`, the same
 *   shape `FieldGroupLoader` (Phase 3) will read from a field group's JSON
 *   `fields[]` entries.
 *
 * Identity (`get_type()` / `get_type_label()`) is deliberately implemented
 * as abstract methods rather than class constants or constructor
 * parameters:
 * - PHP does not support "abstract const" on an abstract class, so
 *   constants would not be enforced at the language level — a subclass
 *   could simply forget to redeclare them and silently inherit the wrong
 *   identity.
 * - Constructor parameters would make identity a per-instance concern,
 *   when it is actually a per-class concern (a `Text` field is always
 *   type `text`; that should not depend on how it was constructed). It
 *   would also force `FieldTypeRegistry` (Phase 2, next item) to pass
 *   redundant values every time it needs to know what a class is before
 *   instantiating it for a real field.
 * - Abstract methods are enforced by PHP itself (a concrete subclass that
 *   omits them is a fatal error), are cheap to call, and read naturally
 *   at call sites (`$field->get_type()`).
 *
 * @package Growsfields
 */
abstract class FieldType {

	/**
	 * Per-instance field configuration, as loaded from a field group's
	 * `fields[]` entry (Phase 3). Always contains at least the keys in
	 * {@see self::DEFAULT_CONFIG}; concrete subclasses read their own
	 * extra keys (e.g. Text's `maxlength`, Select's `choices`) out of
	 * this same array via {@see self::get_option()}.
	 *
	 * @var array<string, mixed>
	 */
	protected array $config;

	/**
	 * Default values for the configuration keys every field instance
	 * carries regardless of type. Concrete subclasses are free to add
	 * their own keys on top of these via the constructor's
	 * `$field_config` argument; these are only the keys the base class
	 * itself guarantees to exist (and therefore the keys
	 * {@see self::base_js_schema()} can safely read).
	 *
	 * @var array<string, mixed>
	 */
	private const DEFAULT_CONFIG = array(
		'name'              => '',
		'label'             => '',
		'instructions'      => '',
		'required'          => false,
		'wrapper'           => array( 'width' => '' ),
		// Placeholder only: round-tripped into to_js_schema() so Phase 3's
		// conditional logic engine and Phase 6-B's builder UI have
		// somewhere to read/write it. Nothing in this class evaluates it.
		'conditional_logic' => array(),
	);

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $field_config Per-instance field
	 *                                            configuration, as it will
	 *                                            later be loaded from a
	 *                                            field group JSON entry
	 *                                            (Phase 3's
	 *                                            `FieldGroupLoader`).
	 *                                            Missing common keys fall
	 *                                            back to
	 *                                            {@see self::DEFAULT_CONFIG};
	 *                                            unknown keys are kept
	 *                                            as-is for subclasses to
	 *                                            read via
	 *                                            {@see self::get_option()}.
	 */
	public function __construct( array $field_config = array() ) {
		$this->config = array_merge( self::DEFAULT_CONFIG, $field_config );
	}

	/**
	 * The type slug, e.g. 'text', 'repeater'. Stable, machine-readable,
	 * used as the key in `FieldTypeRegistry` and stored in field group
	 * JSON / `to_js_schema()` output so the editor knows which React
	 * input to render.
	 *
	 * @return string
	 */
	abstract public function get_type(): string;

	/**
	 * Human-readable label for the *type* (not the instance), e.g.
	 * 'Text', 'Repeater'. Used in admin UI such as the field-type picker
	 * (Phase 6-B).
	 *
	 * @return string
	 */
	abstract public function get_type_label(): string;

	/**
	 * Sanitizes a raw submitted value into the form that gets stored.
	 *
	 * This is the security boundary for this field type: implementations
	 * MUST NOT trust `$value` as-is and MUST run it through an
	 * appropriate WordPress sanitization/escaping function for the data
	 * it represents (e.g. `sanitize_text_field()` for Text,
	 * `wp_kses_post()` for Wysiwyg, `absint()` for an attachment ID on
	 * Image). Callers (meta box save handlers, REST controllers, block
	 * attribute updates) rely on this method alone to make a value safe
	 * to persist — do not assume anything upstream has already sanitized
	 * it.
	 *
	 * @param mixed $value Raw value as submitted (e.g. from $_POST, a
	 *                      REST request body, or a block attribute).
	 * @return mixed Sanitized value, safe to persist.
	 */
	abstract public function sanitize( $value );

	/**
	 * The value to use when this field has never been set.
	 *
	 * @return mixed
	 */
	abstract public function default_value();

	/**
	 * Describes this field for JS/React consumption. The generic
	 * `edit.js` (Phase 4) and the field-group builder (Phase 6-B) read
	 * this to know what input to render and how to configure it.
	 *
	 * Concrete implementations should start from
	 * {@see self::base_js_schema()} and merge in their own type-specific
	 * keys (e.g. Text's `maxlength`/`placeholder`, Select's `choices`)
	 * rather than reassembling the common envelope by hand.
	 *
	 * @return array<string, mixed> Plain associative array, safe to pass
	 *                               through `wp_json_encode()`.
	 */
	abstract public function to_js_schema(): array;

	/**
	 * Assembles the keys every field type's JS schema needs to carry,
	 * regardless of type: identity, instance labelling, and the
	 * cross-cutting placeholders (`conditional_logic`, `wrapper`) that
	 * later phases build engines around. Concrete `to_js_schema()`
	 * implementations call this and merge their type-specific keys on
	 * top, e.g.:
	 *
	 *     public function to_js_schema(): array {
	 *         return array_merge(
	 *             $this->base_js_schema(),
	 *             array(
	 *                 'maxlength'   => $this->get_option( 'maxlength' ),
	 *                 'placeholder' => $this->get_option( 'placeholder', '' ),
	 *             )
	 *         );
	 *     }
	 *
	 * @return array<string, mixed>
	 */
	protected function base_js_schema(): array {
		return array(
			'type'              => $this->get_type(),
			'type_label'        => $this->get_type_label(),
			'name'              => $this->get_name(),
			'label'             => $this->get_label(),
			'instructions'      => $this->get_instructions(),
			'required'          => $this->is_required(),
			'wrapper'           => $this->get_wrapper(),
			'conditional_logic' => $this->get_conditional_logic(),
			'default_value'     => $this->default_value(),
		);
	}

	/**
	 * The field instance's name (used as the meta/attribute key it is
	 * stored under).
	 *
	 * @return string
	 */
	public function get_name(): string {
		return (string) $this->config['name'];
	}

	/**
	 * The field instance's admin-facing label (not to be confused with
	 * {@see self::get_type_label()}, which labels the type itself).
	 *
	 * @return string
	 */
	public function get_label(): string {
		return (string) $this->config['label'];
	}

	/**
	 * Help text shown to the editor alongside the field.
	 *
	 * @return string
	 */
	public function get_instructions(): string {
		return (string) $this->config['instructions'];
	}

	/**
	 * Whether this field instance is required.
	 *
	 * @return bool
	 */
	public function is_required(): bool {
		return (bool) $this->config['required'];
	}

	/**
	 * Layout wrapper config (currently just `width`; kept as an array so
	 * later phases can add `class`/`id` without a shape change).
	 *
	 * @return array<string, mixed>
	 */
	public function get_wrapper(): array {
		return (array) $this->config['wrapper'];
	}

	/**
	 * Raw conditional logic rules, as they will be authored in a field
	 * group's JSON and edited by the Phase 6-B builder UI. This class
	 * only stores and round-trips this value through
	 * {@see self::to_js_schema()} — no evaluation happens here. Phase 3's
	 * conditional logic engine is responsible for interpreting it.
	 *
	 * @return array<int|string, mixed>
	 */
	public function get_conditional_logic(): array {
		return (array) $this->config['conditional_logic'];
	}

	/**
	 * Full merged per-instance configuration (common keys plus whatever
	 * type-specific keys were passed to the constructor). Intended for
	 * callers that need the raw shape wholesale, e.g. a future
	 * `FieldGroupLoader` re-serializing a field back to JSON, or a
	 * complex type like `Repeater` reading its `sub_fields` entry.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return $this->config;
	}

	/**
	 * Reads a single type-specific option out of the instance config,
	 * e.g. `$this->get_option( 'maxlength' )` inside a Text field type.
	 * Use this from subclasses instead of reaching into `$this->config`
	 * directly, so a missing key falls back to `$default` consistently.
	 *
	 * @param string $key     Config key to read.
	 * @param mixed  $default Value to return when the key is not set.
	 * @return mixed
	 */
	protected function get_option( string $key, $default = null ) {
		return array_key_exists( $key, $this->config ) ? $this->config[ $key ] : $default;
	}
}
