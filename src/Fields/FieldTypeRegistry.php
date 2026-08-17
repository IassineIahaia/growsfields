<?php
/**
 * Registry of available field types.
 *
 * @package Growsfields
 */

namespace Growsfields\Fields;

use Growsfields\Fields\Types;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FieldTypeRegistry
 *
 * Maps a field type slug (e.g. `'text'`) to the fully-qualified class name
 * that implements it (e.g. `Growsfields\Fields\Types\Text::class`) —
 * deliberately class names, not instances. A `FieldType` object also carries
 * per-instance config (see {@see FieldType::__construct()}), so a single
 * shared instance per type would not work: every field in a field group
 * needs its own object built from that field's own config. The registry's
 * job is only to know *which class* answers for a slug; {@see self::make()}
 * is what turns that into a real, per-field object.
 *
 * This class is not a security boundary. It does not read, sanitize, or
 * output any user-submitted value — that responsibility stays entirely on
 * `FieldType::sanitize()` for the concrete type instance handling the data
 * (see the docblock on {@see FieldType::sanitize()}).
 *
 * @package Growsfields
 */
class FieldTypeRegistry {

	/**
	 * Slug => fully-qualified class name map, built lazily by
	 * {@see self::get_map()} on first use and cached here for the
	 * lifetime of this registry instance.
	 *
	 * @var array<string, class-string<FieldType>>|null
	 */
	private ?array $types = null;

	/**
	 * Registers a field type.
	 *
	 * Validates that `$class_name` actually extends {@see FieldType} via
	 * `is_subclass_of()`, and throws on mismatch rather than logging and
	 * ignoring the bad registration. A class that fails this check is a
	 * programmer error (a typo'd class name, a class that forgot to
	 * `extends FieldType`, or a third-party integration that misused the
	 * `gs_register_field_type` filter) — not a runtime condition callers
	 * should ever expect and route around. Failing loudly surfaces the
	 * mistake immediately, in development, at the point it was made;
	 * logging-and-ignoring would instead let a field type silently vanish
	 * from the registry, and the resulting "unknown field type" failure
	 * would surface much later, far from its actual cause (e.g. deep in
	 * `FieldGroupLoader` or the block editor, when a field group tries to
	 * use it).
	 *
	 * @param string $type       Field type slug, e.g. `'text'`.
	 * @param string $class_name Fully-qualified class name implementing
	 *                            the type, e.g. `Types\Text::class`.
	 * @return void
	 * @throws \InvalidArgumentException If `$class_name` does not extend
	 *                                    {@see FieldType}.
	 */
	public function register( string $type, string $class_name ): void {
		if ( ! is_subclass_of( $class_name, FieldType::class ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'Growsfields: cannot register field type "%1$s" — "%2$s" does not extend %3$s.',
					$type,
					$class_name,
					FieldType::class
				)
			);
		}

		// Ensure the map is built (and the gs_register_field_type filter
		// has run) before we add to it, otherwise a call to register()
		// that happens before get_map()'s first run would just get
		// clobbered by the filtered result on the next get_map() call.
		$this->types = $this->get_map();

		$this->types[ $type ] = $class_name;
	}

	/**
	 * Returns the class name registered for a field type slug.
	 *
	 * @param string $type Field type slug, e.g. `'text'`.
	 * @return class-string<FieldType>|null The registered class name, or
	 *                                        null if `$type` is not
	 *                                        registered.
	 */
	public function get( string $type ): ?string {
		$map = $this->get_map();

		return $map[ $type ] ?? null;
	}

	/**
	 * Whether a field type slug is registered.
	 *
	 * @param string $type Field type slug.
	 * @return bool
	 */
	public function is_registered( string $type ): bool {
		return null !== $this->get( $type );
	}

	/**
	 * Returns the full slug => class name map, e.g. for Phase 6-B's
	 * "choose a field type" admin dropdown.
	 *
	 * @return array<string, class-string<FieldType>>
	 */
	public function all(): array {
		return $this->get_map();
	}

	/**
	 * Instantiates the field type registered for `$type`, configured with
	 * `$field_config`. This is the method most other code should call
	 * (`FieldGroupLoader`, the block editor schema endpoint, the Phase
	 * 6-B builder) rather than combining {@see self::get()} with a manual
	 * `new $class( $field_config )` — it centralizes the "unknown type"
	 * error handling in one place instead of every call site.
	 *
	 * @param string               $type         Field type slug.
	 * @param array<string, mixed> $field_config Per-instance field
	 *                                            configuration, passed
	 *                                            through to the field
	 *                                            type's constructor.
	 * @return FieldType
	 * @throws \InvalidArgumentException If `$type` is not registered.
	 */
	public function make( string $type, array $field_config = array() ): FieldType {
		$class_name = $this->get( $type );

		if ( null === $class_name ) {
			throw new \InvalidArgumentException(
				sprintf( 'Growsfields: unknown field type "%s".', $type )
			);
		}

		return new $class_name( $field_config );
	}

	/**
	 * Builds (or returns the already-built) slug => class name map.
	 *
	 * The `gs_register_field_type` filter fires here, lazily, on first
	 * use rather than eagerly in `Plugin::boot()`. `boot()` runs on
	 * `plugins_loaded`, which is early enough that not every plugin
	 * wanting to hook `gs_register_field_type` (or add its own field
	 * types) is guaranteed to have registered its callback yet depending
	 * on load order; deferring the filter to the moment the map is first
	 * actually needed (a field group is loaded, the schema endpoint is
	 * hit, the builder UI asks for the list) guarantees every other
	 * plugin has had its own `plugins_loaded` callback — and any later
	 * hook it uses to attach the filter — run first. It also means a
	 * registry that is never used (e.g. a request that touches no field
	 * groups at all) never pays the cost of building the map.
	 *
	 * @return array<string, class-string<FieldType>>
	 */
	private function get_map(): array {
		if ( null === $this->types ) {
			$this->types = (array) apply_filters( 'gs_register_field_type', $this->register_builtin_types() );
		}

		return $this->types;
	}

	/**
	 * Built-in type registrations.
	 *
	 * Batch A-1 (Text, Textarea, TrueFalse, Radio, ColorPicker, Tab, Link,
	 * Image, Wysiwyg) plus Batch A-2 (Repeater) plus Batch B (Number,
	 * Range, Email, URL, Password, Select, Checkbox, Button Group, Message,
	 * File, Date Picker, Date Time Picker, Time Picker) plus Batch C
	 * (Post Object, Page Link, Relationship, Taxonomy, User, Google Map,
	 * oEmbed, Gallery) plus Batch D (Group, Flexible Content, Clone). Batch
	 * D was the final Phase 2 checklist item — Phase 2 (Field Types engine)
	 * is now complete: 34 built-in field types.
	 *
	 * @return array<string, class-string<FieldType>>
	 */
	private function register_builtin_types(): array {
		return array(
			'text'              => Types\Text::class,
			'textarea'          => Types\Textarea::class,
			'true_false'        => Types\TrueFalse::class,
			'radio'             => Types\Radio::class,
			'color_picker'      => Types\ColorPicker::class,
			'tab'               => Types\Tab::class,
			'link'              => Types\Link::class,
			'image'             => Types\Image::class,
			'wysiwyg'           => Types\Wysiwyg::class,
			'repeater'          => Types\Repeater::class,
			'number'            => Types\Number::class,
			'range'             => Types\Range::class,
			'email'             => Types\Email::class,
			'url'               => Types\URL::class,
			'password'          => Types\Password::class,
			'select'            => Types\Select::class,
			'checkbox'          => Types\Checkbox::class,
			'button_group'      => Types\ButtonGroup::class,
			'message'           => Types\Message::class,
			'file'              => Types\File::class,
			'date_picker'       => Types\DatePicker::class,
			'date_time_picker'  => Types\DateTimePicker::class,
			'time_picker'       => Types\TimePicker::class,
			'post_object'       => Types\PostObject::class,
			'page_link'         => Types\PageLink::class,
			'relationship'      => Types\Relationship::class,
			'taxonomy'          => Types\Taxonomy::class,
			'user'              => Types\User::class,
			'google_map'        => Types\GoogleMap::class,
			'oembed'            => Types\OEmbed::class,
			'gallery'           => Types\Gallery::class,
			'group'             => Types\Group::class,
			'flexible_content'  => Types\FlexibleContent::class,
			'clone'             => Types\Clone_::class,
		);
	}
}
