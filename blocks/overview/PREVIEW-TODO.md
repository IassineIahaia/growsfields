This block still needs a real `preview.jpg` (or `.png`), used as the block
inserter's hover preview (referenced from `block.json` via an `example`
attribute once one is added).

Capturing a real preview requires the block to actually render, which depends
on:

- Phase 3's field-group JSON schema being locked in.
- This block's `attributes` (in `block.json`), `edit.js`, and `render.php`
  being implemented against that schema (Phase 4).

Out of scope for this scaffolding pass. Delete this file once a real
`preview.jpg` has been captured and committed.
