# Growsfields — Progress

Tracks implementation status against `plugin-blocos-checklist-v2.md` (replaces the original ACF-Pro-dependent checklist as of the Phase 1 pivot). Updated after every approved item.

> **2026-08-17 — data-loss incident and recovery:** an assistant-suggested `wp plugin uninstall growsfields` command deleted the entire plugin folder, including `.git` (all commit history, never yet pushed to GitHub). Restored from the user's manual backup, which predated that command. All source files were recovered intact; only the git history itself and the not-yet-committed `uninstall.php` were lost (the latter was recreated from conversation record). Git repo was re-initialized from scratch as a single recovery commit — old commit-by-commit history is gone. See `HANDOFF-PROMPT.md` for the full note.

## Phase 0 — Setup
- [x] Confirm plugin name (`growsfields`), prefix (`gs_`), text domain (`growsfields`)
- [x] Git repo initialized, `.gitignore`, base folder structure

## Phase 1 — Plugin foundation
- [x] Plugin header (`growsfields.php`)
- [x] Constants (`GS_PATH`, `GS_URL`, `GS_VERSION`)
- [x] Composer autoload (PSR-4)
- [x] Revert ACF Pro dependency check (plugin no longer requires ACF Pro)
- [x] Activation / deactivation hooks
- [x] `uninstall.php` (recreated after 2026-08-17 data-loss incident, re-tested in isolation via `wp eval-file` harness — no files deleted, options cleaned correctly)

## Phase 2 — Field Types engine (full ACF Pro parity, confirmed 2026-08-17 — not just the 10 types originally in use)
- [x] `FieldType` abstract contract
- [x] `FieldTypeRegistry`
- [x] Batch A-1 — simple/medium types already confirmed in use: Text, Textarea, TrueFalse, Radio, ColorPicker, Tab, Link, Image, Wysiwyg
- [x] Batch A-2 — Repeater (no nested repeaters, CONFIRMED by user 2026-08-17 — the real "Menu's" field group in `acf-json` has a two-level-deep repeater; Phase 3 will reshape that specific field group instead of adding nesting support to the engine)
- [x] Batch B — remaining "simple" types: Number, Range, Email, URL, Password, Select, Checkbox, Button Group, Message, File, Date Picker, Date Time Picker, Time Picker
- [x] Batch C — relational types: Post Object, Page Link, Relationship, Taxonomy, User, Google Map, oEmbed, Gallery
- [x] Batch D — complex layout types: Group, Flexible Content, Clone

**Phase 2 complete: 34 field types implemented (Batch A-1/A-2/B/C/D), `FieldType` contract, `FieldTypeRegistry` with `gs_register_field_type` filter.**

## Phase 3 — Field Groups engine
- [x] JSON schema for field groups (`field-groups/`) — see `field-groups/SCHEMA.md`, confirmed by user 2026-08-17
- [x] `FieldGroupLoader`
- [x] `LocationResolver` (combinable AND/OR rules, ACF Pro parity) — 21 isolated test cases pass (wildcard `all`, `[]` vs `[[]]`, missing-context `==`/`!=`, unknown operator/param, OR/AND, `resolve()` sort + malformed-entry isolation, real `example-migrated-group.json`). **Known gap, confirmed by user 2026-08-18:** no `acf/*`→`growsfields/*` block-name normalization — deferred to the "migrate 7 existing field groups" item below, not fixed at the call site or inside this class.
- [x] Conditional Logic engine (`src/Fields/ConditionalLogicEngine.php`) — 34 independent test cases pass (all 6 operators incl. array-value edge cases, `[]` vs `[[]]` reversed defaults vs. `LocationResolver`, missing-key/unknown-operator non-match, real Button/`amount` case, real cross-group "Kies overzicht"/`field_67ed36dd41608` case). **Known gap, confirmed by user 2026-08-18, mirrors `LocationResolver`'s prefix gap:** whether a caller's `values map` should be scoped to one field group or widened across simultaneously-active groups is not yet decided — determines whether that one real cross-group `conditional_logic.field` reference ever resolves. Deferred to Phase 4/5/6 wiring.
- [x] Migrate 7 existing field groups to new format (`field-groups/group_*.json`, `example-migrated-group.json` removed) — 28 independent test cases pass against the real `FieldGroupLoader`/`LocationResolver` and the actual files on disk. `acf/*` block-name prefixes rewritten to `growsfields/*` throughout (confirmed zero leftover `acf/` references). "Menu's" (`group_67bc28be09501`) restructured: outer `menus` field changed from `repeater` to `flexible_content` with one layout (`"menu"`) containing `menu_title` (text) + `menu_items` (valid, non-nested repeater) — confirmed by user 2026-08-18. Known pre-existing data quirk carried through unchanged: "Kies overzicht" field's `conditional_logic` still references `field_67ed36dd41608`, which belongs to no loaded group (SCHEMA.md §4.1's flagged orphaned reference).

**Phase 3 complete.**

## Phase 4 — Native Gutenberg blocks
- [x] Block registration infrastructure (`src/Blocks/BlockLoader.php`) — computes WP block `attributes` per block from whichever field group(s) `LocationResolver` resolves for it (attribute type inferred from each field's `default_value()` PHP shape, not a hardcoded per-type table), hooks `register_block_type()` on `init`, dispatches rendering to `blocks/{slug}/render.php`. Bug found and fixed during review: `tab`/`message` fields (empty `name` by convention) were producing a stray `''` attribute key — now skipped.
- [x] hero (`blocks/hero/render.php`) — 14 independent test cases pass (attribute computation against real `field-groups/`, multi-group merge via a `cta` sanity check, full render output). `align_image` dropped (confirmed dead in real ACF data); `image` resolved from attachment ID to URL (this plugin's Image field always stores the ID, unlike the original ACF `return_format: "url"`); `esc_html()` used instead of `wp_kses_post()` for the now-guaranteed-plain-text `text` field. **Not yet manually verified in the browser** (no `edit.js` yet, so the block doesn't appear in the inserter — test via wp-admin Code Editor pasting a raw `<!-- wp:growsfields/hero {...} /-->` comment and viewing the frontend). User deferred that manual check to do later, in parallel.
- [ ] cta
- [ ] body
- [ ] headerimage
- [ ] overview
- [ ] default-block
- [ ] Generic `edit.js` driven by field group schema
- [ ] Custom block category in editor

## Phase 5 — Custom Post Types with native meta boxes
- [ ] Migrate Project CPT
- [ ] `MetaBoxRenderer`

## Phase 6 — Native Options Page
- [ ] `OptionsPageRenderer`

## Phase 6-B — Field Group Builder (admin UI, CONFIRMED, full ACF Pro admin parity as of 2026-08-17)
- [ ] `FieldGroupListTable`
- [ ] `FieldGroupEditScreen`
- [ ] React field-group-builder component (add/reorder/edit/remove fields)
- [ ] Nested sub-fields UI for Repeater / Group / Flexible Content / Clone
- [ ] Conditional Logic UI (per-field rules referencing other fields)
- [ ] Location Rules UI (combinable AND/OR groups)
- [ ] Clone field picker (pick existing field group or individual fields)
- [ ] Export/Import UI (PHP + JSON, matching ACF Pro's own screen)
- [ ] Save via REST endpoint, writes to same `field-groups/*.json` format as Phase 3
- [ ] Field name uniqueness validation

## Phase 7 — Security fixes
- [ ] ABSPATH guard everywhere
- [ ] Nonce in ajax_load_more
- [ ] Fix undefined $meta_query
- [ ] Sanitize post_type / posts_per_page / offset
- [ ] Fix array_reduce() without initial value
- [ ] Review escaping in all render.php
- [ ] Nonce/sanitization/capability checks across Phase 2-6 surfaces

## Phase 8 — Performance
- [ ] Conditional asset loading per block (has_block)
- [ ] Query caching
- [ ] Consistent lazy loading

## Phase 9 — Flexibility
- [ ] load_plugin_textdomain
- [ ] Hooks (gs_block_categories, gs_enabled_post_types, gs_register_field_type, ...)
- [ ] Plugin's own options page (distinct from Phase 6 content options page)

## Phase 10 — Automated tests
- [ ] PHP_CodeSniffer (WordPress-Extra)
- [ ] phpstan
- [ ] PHPUnit (wp-phpunit), incl. FieldType::sanitize(), LocationResolver, FieldGroupLoader

## Phase 11 — GitHub deploy
- [x] Repo created (`growsfields`, public, https://github.com/IassineIahaia/growsfields)
- [ ] readme.txt with changelog
- [x] Push main branch (moved up from Phase 11, done early on 2026-08-17 as a direct consequence of the data-loss incident above — local-only work is no longer the only copy)
- [ ] Tag/release v1.0.0
- [ ] (Optional) GitHub Actions
- [ ] Final clean-clone test (no ACF Pro installed)
