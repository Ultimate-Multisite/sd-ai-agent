# Kadence Blocks

## When to Use

This skill is auto-loaded when the **Kadence Blocks** plugin is active. Use it whenever generating, editing, or debugging block markup that involves any `kadence/*` block — `kadence/rowlayout`, `kadence/column`, `kadence/advancedheading`, `kadence/advancedbtn`, `kadence/singlebtn`, `kadence/infobox`, `kadence/icon`, `kadence/spacer`, `kadence/accordion`, `kadence/tabs`, `kadence/form`, `kadence/posts`, etc. Also triggered by classes like `kt-adv-heading`, `kt-inside-inner-col`, `kb-section-dir-horizontal`, `kt-highlight`, or attributes like `kbVersion`, `colLayout`, `uniqueID`.

For non-Kadence (`wp:*`) block markup, use the `gutenberg-blocks` skill. For Kadence Theme-specific work (header builder, customizer hooks), use the `kadence-theme` skill.

> Source: structural rules adapted from [brianspringman/kadence-claude-skill](https://github.com/brianspringman/kadence-claude-skill) (MIT). Tested against Kadence Blocks 3.6.x. Verify against your installed version when in doubt.

## Quick diagnostic — symptom → fix

| Symptom or error | Fix |
|---|---|
| `Block validation: Expected tag name 'h2', instead saw 'hN'` | Add `"level":N` to the `kadence/advancedheading` attributes — see "Headings" §Rule 1. |
| Text renders **vertically** (one letter per line) in the editor | Column is missing horizontal-direction attrs — see "Columns" §Rule 1. |
| `Block validation failed` on a rowlayout | Wrapper-div mismatch on top-level rowlayout — see "Rowlayout" §Rule 3. |
| Inline CSS appears in `wp:html` blocks instead of Kadence layout | Replace with nested `kadence/rowlayout` + `kadence/column` — never use `wp:html` for layout. |
| `text-wrap:balance` (or other style) on heading tag fails validation | Move accent styling into `<mark class="kt-highlight">`, never `style=` on the heading tag. |
| Padding/typography ignored at runtime | Use array format (`[t,r,b,l]` for padding, `[desktop,tablet,mobile]` for sizes), not flat props. |
| Buttons render with wrong wrapper HTML | Use self-closing `<!-- wp:kadence/singlebtn {…} /-->` — let Kadence's save() generate the link. |
| Column appears unexpectedly empty or doubled | Don't pad with empty columns — change `colLayout` to match the actual column count. |
| Background image at wrong size/position | Don't set `bgImgSize`/`bgImgPosition` unless overriding defaults — Kadence infers them. |

## Core rules (always apply)

1. **Use Kadence blocks for layout, never `wp:html` with inline CSS.** Sections, rows, columns, grids, heroes → nested `kadence/rowlayout` + `kadence/column`.
2. **Every `kadence/rowlayout` MUST define `colLayout`** — `"equal"`, `"left-half"`, `"left-golden"`, `"right-golden"`, `"three-grid"`, `"four-grid"`. Missing → broken layout.
3. **Every `kadence/advancedheading` MUST define `"level"`** matching the rendered tag (`"level":1` for `<h1>`). Default is 2; mismatch fails validation.
4. **Every `kadence/column` must include all three horizontal-direction pieces** (or text renders vertically in the editor):
   - `"direction":["horizontal","",""]`
   - `"justifyContent":[null,"",""]`
   - The class `kb-section-dir-horizontal` on the rendered `<div>`
5. **Use array format for spatial/responsive attributes**:
   - Padding: `"padding":[0,48,80,48]` ✓ (top, right, bottom, left). Never `"topPadding":0,"bottomPadding":80,…`
   - Font size: `"fontSize":[128,null,null]` ✓ (desktop, tablet, mobile). `null` inherits from previous breakpoint.
   - Border width: `"borderWidth":["","","",""]`
6. **Always include `"uniqueID"` and `"kbVersion":2`** on every Kadence block. `uniqueID` must be unique within the post.
7. **Keep attributes minimal.** Don't redeclare defaults (e.g. omit `"bgImgSize":"cover"` — `cover` is already the default).
8. **Top-level rowlayouts have NO wrapper divs** between the block comment and the first child column comment. Nested rowlayouts (inside a column) DO get the wrapper divs — Kadence's `save()` includes them as inner content.
9. **`kadence/advancedheading` blocks must NOT have a `"content"` attribute.** Text lives in the rendered HTML tag only.
10. **For colored italic accent text in headings, use `<mark class="kt-highlight">`** with inline `style="background:transparent;color:#XXX;font-style:italic"`. Never put `style=""` on the heading tag itself.
11. **Use named `colLayout`, not `customRowWidth`,** when a named layout fits. Remove `"customRowWidth":[null,"",""]` whenever using a named layout.
12. **Only emit columns that contain content.** Don't pad rowlayouts with empty columns to "balance" — adjust `colLayout` instead.
13. **Use `kadence/advancedheading` for eyebrow/label text**, not styled `wp:paragraph`. Eyebrows are typically `level:3` with brand color.
14. **Never nest `<p>` tags.** A paragraph block must render exactly one `<p>`.

## Block-specific reference

### Rowlayout

```html
<!-- wp:kadence/rowlayout {"uniqueID":"hero_row","colLayout":"left-golden","verticalAlignment":"middle","padding":[120,48,120,48],"kbVersion":2} -->
<!-- wp:kadence/column {"borderWidth":["","","",""],"uniqueID":"col_hero_left","direction":["horizontal","",""],"justifyContent":[null,"",""],"kbVersion":2} -->
<div class="wp-block-kadence-column kadence-columncol_hero_left kb-section-dir-horizontal"><div class="kt-inside-inner-col">
  <!-- left column content -->
</div></div>
<!-- /wp:kadence/column -->

<!-- wp:kadence/column {"borderWidth":["","","",""],"uniqueID":"col_hero_right","direction":["horizontal","",""],"justifyContent":[null,"",""],"kbVersion":2} -->
<div class="wp-block-kadence-column kadence-columncol_hero_right kb-section-dir-horizontal"><div class="kt-inside-inner-col">
  <!-- right column content -->
</div></div>
<!-- /wp:kadence/column -->
<!-- /wp:kadence/rowlayout -->
```

`colLayout` values:

| Value | Meaning | Typical use |
|---|---|---|
| `"equal"` | Equal-width columns | Default multi-column |
| `"left-half"` | 50% / 50% | Even two-column |
| `"left-golden"` | ~40% / ~60% | Image left, text right |
| `"right-golden"` | ~60% / ~40% | Text left, image right |
| `"two-grid"` | Two equal grid cols | Card grids |
| `"three-grid"` | Three equal cols | Three-up cards |
| `"four-grid"` | Four equal cols | Logo bars, feature grids |

For a one-column row, use `"colLayout":"equal"` with `"columns":1`.

Padding: `"padding":[top,right,bottom,left]` — e.g. `"padding":[120,48,120,48]`. Responsive variant: `"tabletPadding":[80,32,80,32],"mobilePadding":[48,16,48,16]`.

Background image (set only what differs from defaults):

```json
"bgImg":"/wp-content/uploads/2026/04/hero.jpg","bgImgID":30
```

### Advanced heading

```html
<!-- wp:kadence/advancedheading {"level":1,"uniqueID":"home_h1","fontSize":[88,60,40],"color":"#1A1A1A","typography":"Fraunces","kbVersion":2} -->
<h1 class="kt-adv-headinghome_h1 wp-block-kadence-advancedheading" data-kb-block="kb-adv-headinghome_h1">Welcome.</h1>
<!-- /wp:kadence/advancedheading -->
```

Level → tag mapping:

| `"level"` | Rendered tag | Use |
|---|---|---|
| `1` | `<h1>` | Page title (one per page) |
| `2` | `<h2>` | Section headings (default) |
| `3` | `<h3>` | Eyebrow/label, subsections |
| `4`–`6` | `<h4>`–`<h6>` | Deeper hierarchy |

**Required classes on the rendered tag**:
- `kt-adv-heading{uniqueID}` (no separator)
- `wp-block-kadence-advancedheading`
- `data-kb-block="kb-adv-heading{uniqueID}"`

**Accent text in headings** — use `<mark class="kt-highlight">`:

```html
<h2 class="kt-adv-headingsection_h2 wp-block-kadence-advancedheading" data-kb-block="kb-adv-headingsection_h2">Building something? <mark style="background:transparent;color:#E8852B;font-style:italic" class="kt-highlight">Let's talk.</mark></h2>
```

The `background:transparent` prevents the default yellow `<mark>` background. `font-style:italic` is preferred over wrapping in `<em>` (would nest semantically).

**Eyebrow / label text** — use `level:3` heading, NOT a styled paragraph:

```html
<!-- wp:kadence/advancedheading {"level":3,"uniqueID":"about_eyebrow","color":"#e8852b","kbVersion":2} -->
<h3 class="kt-adv-headingabout_eyebrow wp-block-kadence-advancedheading" data-kb-block="kb-adv-headingabout_eyebrow">- PRACTICE - EST. 2008</h3>
<!-- /wp:kadence/advancedheading -->
```

### Column

Required structure:

```html
<div class="wp-block-kadence-column kadence-column{uniqueID} kb-section-dir-horizontal">
  <div class="kt-inside-inner-col">
    <!-- column content -->
  </div>
</div>
```

Column count must match `colLayout`:

| `colLayout` | Required column count |
|---|---|
| `"equal"` + `"columns":1` | 1 |
| `"left-half"`, `"left-golden"`, `"right-golden"` | 2 |
| `"equal"` (default) | matches `"columns":N`, default 2 |
| `"three-grid"` | 3 |
| `"four-grid"` | 4 |

For top/middle/bottom alignment, set `"verticalAlignment"` on the parent rowlayout — values `"top"`, `"middle"`, `"bottom"`.

### Buttons

Wrapper + child structure. Self-closing children:

```html
<!-- wp:kadence/advancedbtn {"uniqueID":"hero_ctas"} -->
<div class="wp-block-kadence-advancedbtn kb-buttons-wrap kb-btnshero_ctas">
<!-- wp:kadence/singlebtn {"uniqueID":"cta_primary","text":"Get Started","link":"/start","color":"#fff","background":"#1a1a1a"} /-->
<!-- wp:kadence/singlebtn {"uniqueID":"cta_secondary","text":"Learn More","link":"/about","inheritStyles":"outline"} /-->
</div>
<!-- /wp:kadence/advancedbtn -->
```

Wrapper requires three classes: `wp-block-kadence-advancedbtn kb-buttons-wrap kb-btns{uniqueID}` (no separator before uniqueID).

Common `singlebtn` attributes: `text`, `link`, `color`, `background`, `inheritStyles` (`"outline"`, `"ghost"`, `"basic"`), `target`, `noFollow`, `sponsored`, `download`, `iconSide`, `icon`. Set only what differs from theme defaults.

Multiple buttons → ONE wrapper, multiple `singlebtn` children. Don't wrap each in its own `advancedbtn`.

## Verifying against your version

Kadence's attribute schemas evolve across releases. When in doubt, the authoritative source is the plugin's own JS:

1. **Live editor**: Build the block in the WordPress editor, save the post, then view via `/wp-json/wp/v2/posts/<id>?context=edit` or use the editor's "Copy" menu. That output matches the validator.
2. **Plugin source**: `wp-content/plugins/kadence-blocks/includes/blocks/` — each block's PHP render and JSON schema.
3. **GitHub**: <https://github.com/stellarwp/kadence-blocks>

If a rule here conflicts with what your installed version produces, trust your version.

## Verifying generated markup

1. Run `sd-ai-agent/validate-block-content` first — catches structural issues.
2. Paste the markup into a draft and save. Look for "This block contains unexpected or invalid content."
3. Click "Attempt Block Recovery" — Kadence shows a diff between what you generated and what it expected. The diff usually points at the offending attribute directly.
4. View the page in a new tab and check the browser console for warnings.
