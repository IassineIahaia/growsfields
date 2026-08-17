# Growsfields field group JSON schema

**Status:** design document for Phase 3's first checklist item ("JSON schema for
field groups"). Nothing in `src/` reads this format yet — that's the next
checklist item, `FieldGroupLoader`. This document is the contract that loader
will be built against.

**Location:** one file per field group, at `field-groups/<key>.json`, mirroring
the one-file-per-group convention already used by
`themes/starter-2026-iassine/acf-json/*.json`. `FieldGroupLoader` will
`glob( GS_PATH . 'field-groups/*.json' )`.

**Relationship to ACF's own export format:** this format is deliberately
*inspired by* ACF's own `acf-json` export shape (per
`plugin-blocos-checklist-v2.md`, Fase 3), not byte-compatible with it. Where a
key/shape is identical to ACF's, it's called out as such, grounded in what the
real files in `themes/starter-2026-iassine/acf-json/*.json` and ACF Pro's own
documented behaviour actually contain. Where this schema drops, renames, or
simplifies something ACF does, that's flagged explicitly.

---

## 1. Top-level field group object

```jsonc
{
  "key": "group_6488bc50dbae3",
  "title": "Block: Overzicht",
  "fields": [ /* array of field objects, see §2 */ ],
  "location": [ /* array of array of rule objects, see §3 */ ],
  "menu_order": 0,
  "position": "normal",
  "style": "default",
  "label_placement": "top",
  "instruction_placement": "label",
  "hide_on_screen": [],
  "active": true,
  "description": ""
}
```

| Key                      | Type              | Required | Default     | Notes |
|---------------------------|-------------------|----------|-------------|-------|
| `key`                     | `string`          | yes      | —           | Unique group ID. See §1.1 for format. |
| `title`                   | `string`          | yes      | —           | Admin-facing group name (Phase 6-B list table, block editor debug). |
| `fields`                  | `array<field>`    | yes      | `[]`        | See §2. Top-level list; nesting (Repeater/Group/FlexibleContent/Clone) happens *inside* a field object, not here. |
| `location`                | `array<array<rule>>` | yes   | `[]`        | OR-of-AND rule groups. See §3. An empty array means the group never applies (matches ACF's own behaviour of a group with no location rules). |
| `menu_order`               | `int`             | no       | `0`         | When multiple field groups match the same location, controls display/render order (lower first) — same meaning as ACF Pro's own option. Real example: `group_69380e4dc49ac` ("Block options") uses `100` to render after every block-specific group. |
| `position`                 | `"normal"\|"side"\|"acf_after_title"` | no | `"normal"` | Where a meta-box-driven use of this group renders (Phase 5, native meta boxes) — passed straight to `add_meta_box()`'s `$context` argument. Not meaningful for block/options-page uses of the same group; loaders for those contexts ignore it. |
| `style`                    | `"default"\|"seamless"` | no  | `"default"` | `default` = group renders in its own titled metabox/section; `seamless` = fields blend into the surrounding UI with no box/title. Matches ACF Pro's own option. |
| `label_placement`          | `"top"\|"left"`   | no       | `"top"`     | Field label position within this group's own rendering. Matches ACF Pro. |
| `instruction_placement`    | `"label"\|"field"` | no      | `"label"`   | Whether a field's `instructions` text renders under the label or under the input. Matches ACF Pro. |
| `hide_on_screen`           | `array<string>`   | no       | `[]`        | Screen elements to hide when this group is active on a post edit screen (e.g. `"permalink"`, `"the_content"`, `"excerpt"`, `"discussion"`, `"comments"`, `"revisions"`, `"slug"`, `"author"`, `"format"`, `"page_attributes"`, `"featured_image"`, `"categories"`, `"tags"`, `"send-trackbacks"` — ACF Pro's own real vocabulary). Pure pass-through: nothing in Phase 3 evaluates this; it's for Phase 5's `MetaBoxRenderer` to read later. **Normalization note:** ACF's own raw export shows this as an empty *string* `""` when unset (see every real file in `acf-json/`) rather than an empty array — this schema normalizes it to always be an array (`[]` when empty), which is a deliberate divergence from ACF's raw export shape, flagged here. |
| `active`                   | `bool`            | no       | `true`      | Whether this group is currently enabled at all — `FieldGroupLoader` should skip inactive groups entirely (never hand them to `LocationResolver`), matching ACF's own behaviour of `"active": false` meaning "exists on disk, does nothing". |
| `description`              | `string`          | no       | `""`        | Free-text admin note about the group's purpose, for Phase 6-B's list table. **Not** one of the checklist's named top-level keys — added because it's present in every real `acf-json` file and costs nothing; see judgment calls list. |

### 1.1 `key` format — JUDGMENT CALL

**Decision:** keep ACF's own convention exactly — `group_` followed by 13
lowercase hex characters, e.g. `group_6488bc50dbae3`. Same convention for
field keys (§2.1): `field_` + 13 lowercase hex characters.

**Reasoning:**
- Every real field group already has a key in this shape (all 7 files in
  `acf-json/`), and every real field already has one too. Keeping the format
  means the 7-group migration item (Phase 3's last checklist line) can copy
  `key` values across largely verbatim instead of needing a translation
  table — the exact reasoning `Text.php`'s docblock already gives for keeping
  the `text` type slug unchanged from ACF's own.
- The key is genuinely opaque (used only as a stable ID for `clone`
  references and, per the `conditional_logic.field` decision in §4, as the
  reference format for conditional rules) — nothing about *how* it's
  generated matters to any consumer, so there's no cost to matching ACF's
  shape and a real benefit (migration ease, familiarity for anyone who's used
  ACF).
- Uniqueness scope: **globally unique across the whole `field-groups/`
  directory**, both for group keys and field keys (not just unique within one
  group/file) — matching ACF's own real behaviour (ACF field keys are
  globally unique across a whole site, since among other things they end up
  as the value of a `_<meta_key>` reference stored in postmeta). This matters
  most for `clone` (§5), which resolves a key that could point at a field
  defined in a *different* file.

**Needs review:** the exact generation algorithm (e.g. `substr( uniqid(), 0,
13 )` vs. something else) isn't decided here — that's an implementation
detail for `FieldGroupLoader`/Phase 6-B's save flow, not a schema concern.
This document only fixes the *shape* (`^(group|field)_[a-f0-9]{13}$`) so
validation can be written against it.

---

## 2. Field object shape

Every entry in `fields[]` (and, recursively, every entry in a `sub_fields[]`
or a layout's `sub_fields[]`, see §2.3–§2.5) is a field object. It has a fixed
set of **universal keys** — the ones `FieldType`'s own `DEFAULT_CONFIG` and
constructor guarantee every field type reads the same way — plus whatever
**type-specific keys** that field's concrete `FieldType` subclass reads via
`get_option()`.

### 2.1 Universal keys

Grounded directly in `src/Fields/FieldType.php`'s `DEFAULT_CONFIG` constant
and constructor:

| Key                  | Type     | Required | Default        | Notes |
|-----------------------|----------|----------|----------------|-------|
| `key`                 | `string` | yes      | —              | Unique field ID, `field_` + 13 hex chars. See §1.1. Not itself read by `FieldType` (the class has no notion of its own key) — it exists for `clone` references (§5) and `conditional_logic.field` (§4) to point at. |
| `type`                | `string` | yes      | —              | One of the 34 slugs registered in `FieldTypeRegistry::register_builtin_types()` (§2.6). Selects which `FieldType` subclass `FieldTypeRegistry::make()` constructs. |
| `name`                | `string` | yes      | `""`           | Storage/attribute key this field's value is kept under. Must be unique *within its own field list* (top-level `fields[]`, or one `sub_fields[]`/layout, independently — the same `name` can be reused across different repeaters/groups without collision, since each has its own storage namespace). |
| `label`               | `string` | yes      | `""`           | Admin-facing label. |
| `instructions`        | `string` | no       | `""`           | Help text shown to the editor. |
| `required`            | `bool`   | no       | `false`        | |
| `wrapper`              | `object` | no      | `{"width":""}` | Layout wrapper. `width` is the only key `FieldType`'s own default guarantees (a string percentage like `"33"`, `"50"`, `"66"`, or `""` for auto-width, matching ACF's own convention of unitless numeric strings). `class`/`id` are *not* part of the guaranteed default, but `FieldType::get_wrapper()` returns whatever object was configured verbatim, so a field JSON may still include them for future use by the block-editor's `InspectorControls` layout (Phase 4) — they're simply not defaulted or relied upon by anything yet. |
| `conditional_logic`   | `array<array<rule>>` | no | `[]`  | OR-of-AND rules, same shape as `location` at the syntax level but rules of a different vocabulary. See §4. **Shape only** — no engine evaluates this yet; `FieldType::get_conditional_logic()` only stores and round-trips it. |

A field whose type is `tab` or `message` carries no stored value at all
(`sanitize()` always returns `null` for both) — `name` is conventionally left
`""` for a `tab` (see the real `acf-json` tab fields, e.g.
`field_695cb7930ba3e` in `group_6488ab198a731.json`), since a tab is a purely
visual marker, not a piece of data.

### 2.2 Type-specific keys

Everything else in a field object is specific to that field's `type` and gets
passed straight through to that type's constructor as part of `$field_config`
— `FieldType::get_option( $key, $default )` is how each subclass reads its
own keys, falling back to its own hard-coded default when a key is absent.
**This schema does not re-enumerate every type-specific key for all 34
types** (that would just be a copy of `src/Fields/Types/*.php`, which is the
authoritative source) — instead, §2.3–§2.6 below give four worked examples
covering the four structurally different shapes a field object can take, and
§2.6 lists all 34 type slugs with a one-line pointer to their option set.

### 2.3 Worked example — simple type (`text`)

Field key `field_67d0031a8d17f`, real data from
`group_6488bc50dbae3.json` ("Block: Overzicht" / "Titel"), unchanged:

```json
{
  "key": "field_67d0031a8d17f",
  "type": "text",
  "name": "title",
  "label": "Titel",
  "instructions": "",
  "required": false,
  "wrapper": { "width": "" },
  "conditional_logic": [],
  "default_value": "",
  "maxlength": "",
  "placeholder": ""
}
```

`maxlength`/`placeholder`/`default_value` are `Text`'s own option keys
(`Types/Text.php`, `to_js_schema()` and `sanitize()`) — `maxlength` truncates
via `mb_substr()` when set to a positive int, `default_value` is what
`default_value()` returns when the field has never been set.

### 2.4 Worked example — choice type (`radio`)

Field key `field_6488bc50051f0`, real data from the same file ("Kies
overzicht"), unchanged:

```json
{
  "key": "field_6488bc50051f0",
  "type": "radio",
  "name": "choose_overview",
  "label": "Kies overzicht",
  "instructions": "",
  "required": false,
  "wrapper": { "width": "" },
  "conditional_logic": [],
  "choices": { "none": "Geen", "project": "Projecten" },
  "default_value": "none"
}
```

`choices` (`value => label` map) and `default_value` are `Radio`'s own option
keys (`Types/Radio.php`) — `sanitize()` re-validates a submitted value
against `choices`' own keys server-side and falls back to `default_value()`
for anything not a registered key, never trusting the client to have only
submitted an offered value.

### 2.5 Worked example — compound/nested type (`repeater`)

Not present as a simple (non-nested) example anywhere in the 7 real files —
the one real repeater (`menus` in `group_67bc28be09501.json`) is itself
two-level-deep, which `Repeater` (per its own class docblock and the
2026-08-17 user decision) explicitly refuses to construct. This worked
example takes real field names/labels from that same "Menu's" repeater's
*inner* level (`menu_title`, `menu_link`) but flattens it to one level, to
demonstrate what a **valid** repeater looks like under this engine's actual
constraint:

```json
{
  "key": "field_67bc28bf1c92b",
  "type": "repeater",
  "name": "menu_items",
  "label": "Menu items",
  "instructions": "",
  "required": false,
  "wrapper": { "width": "" },
  "conditional_logic": [],
  "layout": "table",
  "min": 0,
  "max": 0,
  "collapsed": "",
  "button_label": "Nieuwe regel",
  "sub_fields": [
    {
      "key": "field_67d00d9b366bf",
      "type": "text",
      "name": "menu_title",
      "label": "Menu Titel",
      "instructions": "",
      "required": false,
      "wrapper": { "width": "" },
      "conditional_logic": [],
      "default_value": "",
      "maxlength": "",
      "placeholder": ""
    },
    {
      "key": "field_67bc29291c92d",
      "type": "link",
      "name": "menu_link",
      "label": "Menu link",
      "instructions": "",
      "required": false,
      "wrapper": { "width": "" },
      "conditional_logic": [],
      "return_format": "array"
    }
  ]
}
```

`layout`/`min`/`max`/`collapsed`/`button_label` and `sub_fields` are
`Repeater`'s own option keys (`Types/Repeater.php`). `sub_fields` is an
`array<field>` — recursively the exact same field-object shape as §2.1–§2.2,
which is what lets `FieldTypeRegistry::make( 'text', ... )` construct each
sub-field the same way it constructs a top-level one. `Repeater`'s
constructor throws `InvalidArgumentException` if any `sub_fields` entry is
itself `type: "repeater"` — see §7's note on the "Menu's" migration.

### 2.6 Worked example — relational type (`post_object`)

Not present in any real `acf-json` file (per `Types/PostObject.php`'s own
docblock: "Not seen in this project's `acf-json` exports"). This example is
invented for this document, grounded in `PostObject.php`'s real option
defaults and in the plugin's own domain (`PROGRESS.md` Phase 5: "Migrate
Project CPT"):

```json
{
  "key": "field_6a10c9f4b2e01",
  "type": "post_object",
  "name": "related_project",
  "label": "Related Project",
  "instructions": "",
  "required": false,
  "wrapper": { "width": "" },
  "conditional_logic": [],
  "post_type": ["project"],
  "multiple": false,
  "allow_null": true,
  "return_format": "object",
  "ui": true
}
```

`post_type` (allow-list, normalized internally to an array whether the JSON
supplies a string or an array — see `PostObject::get_allowed_post_types()`),
`multiple`, `allow_null`, `return_format`, `ui` are `PostObject`'s own option
keys. `sanitize()` re-validates a submitted ID against `get_post()` /
`get_post_status()` / the `post_type` allow-list every time, never trusting a
submitted ID to already be a real, non-trashed post of an allowed type.

### 2.7 All 34 registered type slugs

Every slug below is registered in `FieldTypeRegistry::register_builtin_types()`
and is a valid `fields[].type` / `sub_fields[].type` value. See the named
class in `src/Fields/Types/` for that type's own option keys.

| Slug | Class | Slug | Class |
|---|---|---|---|
| `text` | `Text` | `post_object` | `PostObject` |
| `textarea` | `Textarea` | `page_link` | `PageLink` |
| `true_false` | `TrueFalse` | `relationship` | `Relationship` |
| `radio` | `Radio` | `taxonomy` | `Taxonomy` |
| `color_picker` | `ColorPicker` | `user` | `User` |
| `tab` | `Tab` | `google_map` | `GoogleMap` |
| `link` | `Link` | `oembed` | `OEmbed` |
| `image` | `Image` | `gallery` | `Gallery` |
| `wysiwyg` | `Wysiwyg` | `group` | `Group` |
| `repeater` | `Repeater` | `flexible_content` | `FlexibleContent` |
| `number` | `Number` | `clone` | `Clone_` |
| `range` | `Range` | | |
| `email` | `Email` | | |
| `url` | `URL` | | |
| `password` | `Password` | | |
| `select` | `Select` | | |
| `checkbox` | `Checkbox` | | |
| `button_group` | `ButtonGroup` | | |
| `message` | `Message` | | |
| `file` | `File` | | |
| `date_picker` | `DatePicker` | | |
| `date_time_picker` | `DateTimePicker` | | |
| `time_picker` | `TimePicker` | | |

Two extra nested shapes worth flagging explicitly, both grounded directly in
their class:

- **`group`** and **`flexible_content`** also carry `sub_fields`
  (`group`) / `layouts` (`flexible_content`, itself an array of
  `{name, label, sub_fields}` — see `FlexibleContent.php`'s class docblock
  for the exact worked shape), recursively the same field-object shape as
  §2.1–§2.2. Unlike `repeater`, a `group`'s `sub_fields` — and a
  `flexible_content` layout's `sub_fields` — are **not** restricted from
  containing another `repeater` or `group` (`Group.php`'s docblock is
  explicit that only `Repeater`'s own no-nested-repeater rule applies to
  *itself*, not to anything nesting *it*).
- **`clone`** does **not** carry `sub_fields` directly in *authored* JSON —
  see §5, this is the one type whose schema shape genuinely depends on a
  decision Phase 3's `FieldGroupLoader` hasn't been designed yet, flagged
  there rather than resolved here.

---

## 3. `location` — full shape

```json
"location": [
  [
    { "param": "block", "operator": "==", "value": "acf/hero" }
  ],
  [
    { "param": "post_type", "operator": "==", "value": "project" },
    { "param": "page_template", "operator": "!=", "value": "templates/blank.php" }
  ]
]
```

- **Outer array = OR.** The field group applies if **any** inner group of
  rules matches.
- **Inner array = AND.** A group matches only if **every** rule inside it
  matches.
- This is exactly ACF Pro's own real location-rule structure — confirmed
  against real project data. `group_69380e4dc49ac.json` ("Block options") has
  one OR-group containing **three** AND'd rules
  (`block == all` AND `block != acf/headerimage` AND `block != acf/hero`);
  every other real file has one OR-group with a single rule. No real file in
  this project currently has more than one OR-group, but the shape is
  designed for it from the start per the user's confirmed decision
  (`PROGRESS.md`: "`LocationResolver` (combinable AND/OR rules, ACF Pro
  parity)").
- Each rule is `{ "param": string, "operator": string, "value": string }` —
  same three keys, same names, as ACF's own real rule objects.

### 3.1 `param` vocabulary

Scoped to exactly what this project's checklist and the user's confirmed
decision name — **not** ACF Pro's full real vocabulary (which also includes
things like `post_category`, `post_format`, `current_user`, `widget`,
`nav_menu`, `comment`, etc.). `LocationResolver` (the next-but-one Phase 3
checklist item) only needs to resolve these six:

| `param` | `value` meaning | Source |
|---|---|---|
| `block` | A block name, e.g. `"acf/hero"`, or the literal `"all"` (ACF's own "any block" wildcard — real usage in `group_69380e4dc49ac.json`). | Checklist example, confirmed in real data. |
| `post_type` | A post type slug, e.g. `"project"`. | Checklist example. |
| `options_page` | An options page slug, e.g. `"growskills-extra"`. | Checklist example, confirmed in real data (`group_67bc28be09501.json`). |
| `page_template` | A template file path, e.g. `"templates/blank.php"`, or ACF's own `"default"` for the standard template. | User's confirmed decision (`PROGRESS.md` Phase 3 line). |
| `post_status` | A post status slug, e.g. `"publish"`, `"draft"`. | User's confirmed decision. |
| `user_role` | A WP role slug, e.g. `"administrator"`. | User's confirmed decision. |

This vocabulary is not closed at the schema level (a rule with an
unrecognized `param` is still syntactically valid JSON) — it's `
LocationResolver`'s job, not this document's, to decide whether to error or
silently never-match on an unknown `param`.

### 3.2 `operator` vocabulary

`==` and `!=` only. This isn't a simplification particular to this project —
ACF Pro's own real Location Rules UI only ever offers "Is equal to" / "Is not
equal to" for every rule, regardless of `param` (unlike Conditional Logic,
§4, which has a much richer operator set because it compares against
user-entered field *values*, not fixed context parameters). Confirmed in
every real rule in every one of the 7 files — only `==`/`!=` appear.

---

## 4. `conditional_logic` — full shape

```json
"conditional_logic": [
  [
    { "field": "field_695d1200bb26f", "operator": "==", "value": "latest" }
  ]
]
```

Same OR-of-AND envelope as `location` (§3): outer array = OR, inner array =
AND. **This section defines the shape this data is authored/stored in only —
no engine evaluates it yet.** `FieldType::get_conditional_logic()` (already
implemented, Phase 2) only stores and round-trips this array through
`to_js_schema()`; a rules-evaluation engine is a separate, later Phase 3
checklist item ("Conditional Logic engine") and is explicitly out of scope
here.

### 4.1 `field` — references by KEY, not name — CONFIRMED BY REAL DATA

**Decision:** `field` holds another field's `key` (e.g. `"field_695d1200bb26f"`),
never its `name`.

This isn't a judgment call in the way §1.1 is — it's directly confirmed by
real project data. `group_6488bc50dbae3.json` ("Block: Overzicht") has two
real `conditional_logic` entries:

```json
"conditional_logic": [[{ "field": "field_695d1200bb26f", "operator": "==", "value": "latest" }]]
```

on the "Button" field, referencing the "Amount" field's `key`
(`field_695d1200bb26f`) — not its `name` (`amount`). And:

```json
"conditional_logic": [[{ "field": "field_67ed36dd41608", "operator": "==", "value": "overview" }]]
```

on the "Kies overzicht" field. Both are unambiguously key-shaped strings, not
field names. This matches ACF Pro's own real, documented behaviour: the
Conditional Logic UI always stores the *key* of the field being compared
against, specifically so the rule survives the referenced field being
relabeled or renamed later.

**One thing worth flagging for review, not a design decision:** the second
example's key (`field_67ed36dd41608`) does not belong to any field inside
`group_6488bc50dbae3.json` itself — it's not one of that group's own five
fields. This is either a stale/orphaned reference left over from copying a
field group in the ACF admin UI (a real-world data-quality issue, not a
schema question), or it legitimately points at a field in a *different* field
group that ACF's admin UI happened to allow selecting at authoring time. This
schema doesn't take a position on whether `conditional_logic.field` may
reference a field outside the current group — flagged here so it's a
conscious decision for whoever builds the Conditional Logic engine, not
something silently inherited from copying this one real example.

### 4.2 `operator` vocabulary

Grounded in ACF Pro's own real, documented Conditional Logic operators. This
project's checklist/user-decision text names a specific subset that this
schema commits to:

| Operator | Meaning |
|---|---|
| `==` | Equal to |
| `!=` | Not equal to |
| `>` | Greater than (numeric comparison) |
| `<` | Less than (numeric comparison) |
| `==pattern` | Matches pattern (wildcard `*` support, ACF's own) |
| `==contains` | Value contains (substring/array-membership, ACF's own — used for e.g. checkbox/multi-select "is one of") |

ACF Pro's real UI also offers `==empty`/`!=empty` for some field types; this
schema doesn't rule those out (an unrecognized-but-plausible operator string
is still syntactically valid JSON, same open-vocabulary stance as §3.1's
`param`), but only the six above are treated as "supported" by this design
pass — the eventual Conditional Logic engine decides the final list.

---

## 5. `clone` fields — authored shape vs. resolved shape

A `clone` field's *authored* JSON (what a human or Phase 6-B's builder writes
to `field-groups/*.json`) is **not** the same shape `Clone_.php`'s
constructor expects. `Clone_` (per its own class docblock's "SCOPE BOUNDARY"
section) assumes `sub_fields` is already a fully resolved array of field
objects by the time it's constructed — it has no key-lookup logic of its own,
deliberately.

Authored shape (what lives in the JSON file), using ACF Pro's own real
option name for the reference list:

```json
{
  "key": "field_6a20d1a5c3f02",
  "type": "clone",
  "name": "shared_cta",
  "label": "Shared CTA",
  "instructions": "",
  "required": false,
  "wrapper": { "width": "" },
  "conditional_logic": [],
  "clone": ["group_67d0098cf2948"],
  "display": "seamless"
}
```

- `clone`: `array<string>` — a list of field or group **keys** to pull
  sub-fields from. This key name and shape (array of keys, mixing field-level
  and group-level references) is ACF Pro's own real option — not invented for
  this project.
- `display`: `"group"|"seamless"`, `Clone_`'s own real option
  (`Clone_::get_display()`), already implemented in Phase 2 and unchanged
  here.
- **No `sub_fields` key** in the authored shape — it doesn't exist until
  something resolves `clone`'s key references into concrete field objects.

**This document deliberately does not design that resolution mechanism** —
per the task brief and per `Clone_.php`'s own docblock ("Phase 3's
`FieldGroupLoader` is therefore responsible for producing a fully-resolved
`sub_fields` array... BEFORE handing config to
`FieldTypeRegistry::make( 'clone', $config )`"), that's `FieldGroupLoader`'s
job, not this schema document's. Flagging it here explicitly so it isn't
mistaken for a decision this document quietly made by omission — see §7 and
the judgment-calls list in the accompanying report.

---

## 6. Filename / one-group-per-file convention

`field-groups/<key>.json`, e.g. `field-groups/group_6488bc50dbae3.json` — the
file's own top-level `key` value should match its filename (minus `.json`),
matching the convention already used in `acf-json/`. `FieldGroupLoader` can
therefore validate a loaded file's declared `key` against its filename as a
cheap sanity check (not designed here, just noted as available).

---

## 7. What `FieldGroupLoader` (next checklist item) will need to do

Not designed here — this is a pointer for whoever picks up that item next,
so the boundary between "this schema doc" and "that loader" stays where the
task brief drew it:

1. `glob()` `field-groups/*.json`, parse each file as JSON.
2. For each field group, walk `fields[]`. For any field whose `type`
   constructor expects a nested field-list option (`repeater` →
   `sub_fields`, `group` → `sub_fields`, `flexible_content` → each layout's
   `sub_fields`), recursively turn those sub-arrays into the same field-object
   shape first — they're just more of this same schema, one level down.
3. Instantiate each field via `FieldTypeRegistry::make( $field['type'],
   $field )` (passing a single shared `FieldTypeRegistry` instance down
   through recursive calls, per `Repeater`'s/`Group`'s own constructor
   docblocks, rather than letting each nested field build its own).
4. **For `clone` fields specifically:** resolve the authored `clone` key-list
   option (§5) into a concrete, resolved `sub_fields` array *before* calling
   `FieldTypeRegistry::make( 'clone', $config )` — per `Clone_.php`'s
   documented SCOPE BOUNDARY. The resolution mechanism itself (how a key
   list becomes concrete field objects — inline lookup across all loaded
   groups? a pre-built key → field-object index? recursive clone-of-a-clone
   handling?) is that item's decision to make, not this document's.
5. Skip/report field groups whose `active` is `false` (§1) before handing
   anything to `LocationResolver`.
6. Surface a clear error (not a silent skip) when a field group references an
   unregistered `type` — `FieldTypeRegistry::make()` already throws
   `InvalidArgumentException` for this; the loader's job is to let that
   propagate with enough context (which file, which field) to be
   debuggable, not swallow it.
