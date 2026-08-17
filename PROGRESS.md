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
- [ ] Batch A-1 — simple/medium types already confirmed in use: Text, Textarea, TrueFalse, Radio, ColorPicker, Tab, Link, Image, Wysiwyg
- [ ] Batch A-2 — Repeater (complex: nested sub-fields, done last/separately per original checklist, depends on `FieldTypeRegistry::make()` to instantiate its sub-fields)
- [ ] Batch B — remaining "simple" types: Number, Range, Email, URL, Password, Select, Checkbox, Button Group, Message, File, Date Picker, Date Time Picker, Time Picker
- [ ] Batch C — relational types: Post Object, Page Link, Relationship, Taxonomy, User, Google Map, oEmbed, Gallery
- [ ] Batch D — complex layout types: Group, Flexible Content, Clone

## Phase 3 — Field Groups engine
- [ ] JSON schema for field groups (`field-groups/`)
- [ ] `FieldGroupLoader`
- [ ] `LocationResolver` (combinable AND/OR rules, ACF Pro parity)
- [ ] Conditional Logic engine
- [ ] Migrate 7 existing field groups to new format

## Phase 4 — Native Gutenberg blocks
- [ ] hero
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
