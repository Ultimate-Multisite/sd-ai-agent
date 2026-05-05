# Block Themes (Full Site Editing)

## When to Use

This skill is auto-loaded when the active WordPress theme is a **block theme** (one that supports Full Site Editing — Twenty Twenty-Four/Five, Frost, Ollie, etc.). Use it whenever the user asks about templates, template parts, the Site Editor, `theme.json`, global styles, block patterns, or "site-wide" layout changes.

If the active theme is classic (no `templates/` directory, no `theme.json`), use the `classic-themes` skill instead — different concepts apply.

## How to confirm the active theme is a block theme

Use `sd-ai-agent/site-info` (preferred) or wp-cli:

```bash
wp option get template       # Active theme directory
wp option get stylesheet     # Active child theme directory (or same as template)
```

Programmatic check inside PHP: `wp_is_block_theme()`. A block theme has:
- `templates/` directory containing `.html` files
- `parts/` directory for header/footer/sidebar parts
- `theme.json` at the theme root

## Key Concepts

### Block Themes vs Classic Themes

| Aspect | Block theme | Classic theme |
|---|---|---|
| Templates | `.html` files in `templates/` | `.php` files in theme root |
| Template parts | `.html` files in `parts/` | `header.php`, `footer.php`, `sidebar.php` |
| Configuration | `theme.json` | `functions.php` + `add_theme_support()` |
| Header/footer editing | Site Editor | Customizer / template files |
| Global styles UI | Yes (Site Editor → Styles) | Customizer (limited) |

### Template Hierarchy

Block themes follow the standard WordPress template hierarchy, but with HTML files:

- `templates/index.html` — Default template (always required)
- `templates/single.html` — Single post
- `templates/page.html` — Single page
- `templates/archive.html` — Archive pages
- `templates/category.html`, `templates/tag.html`, `templates/author.html` — Taxonomy archives
- `templates/search.html` — Search results
- `templates/404.html` — Not found
- `templates/front-page.html` — Static front page
- `templates/home.html` — Posts page

### Template Parts

Reusable sections in `parts/`:

- `parts/header.html` — Site header
- `parts/footer.html` — Site footer
- `parts/sidebar.html` — Sidebar (if used)
- `parts/comments.html` — Comments area

Template parts are referenced inside templates via `wp:template-part`:

```html
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->
```

## Available Tools

- `sd-ai-agent/list-block-templates` — List all templates with slugs and descriptions
- `sd-ai-agent/list-block-patterns` — Browse patterns for page creation and templates
- `sd-ai-agent/parse-block-content` — Inspect template structure
- `sd-ai-agent/create-block-content` / `sd-ai-agent/validate-block-content` — Build/check block markup before saving

## theme.json Overview

`theme.json` controls global styles AND editor settings. Two top-level keys: `settings` (what users can do) and `styles` (default appearance).

### Settings

```json
{
  "settings": {
    "color": { "palette": [ { "slug": "primary", "color": "#1a1a1a", "name": "Primary" } ] },
    "typography": { "fontFamilies": [ ], "fontSizes": [ ] },
    "spacing": { "spacingSizes": [ { "slug": "50", "size": "1rem", "name": "1" } ] },
    "layout": { "contentSize": "720px", "wideSize": "1200px" }
  }
}
```

### Styles

```json
{
  "styles": {
    "color": { "background": "#ffffff", "text": "#1a1a1a" },
    "typography": { "fontFamily": "var(--wp--preset--font-family--inter)" },
    "elements": { "link": { "color": { "text": "var(--wp--preset--color--primary)" } } }
  }
}
```

### Custom Templates

Define custom page templates in theme.json so they appear in the editor's template picker:

```json
{
  "customTemplates": [
    { "name": "blank", "title": "Blank", "postTypes": [ "page" ] },
    { "name": "landing", "title": "Landing Page", "postTypes": [ "page" ] }
  ]
}
```

The corresponding `templates/blank.html` and `templates/landing.html` files must exist.

## Block Patterns and FSE

- Page-creation patterns appear in the modal when creating a new page (`blockTypes`: `core/post-content`)
- Template patterns can be inserted in the Site Editor
- Use `sd-ai-agent/list-block-patterns` to discover available patterns
- Synced patterns (formerly "reusable blocks") are stored as `wp_block` post type

## Typical Workflows

### Inspect current theme templates

1. Use `sd-ai-agent/list-block-templates` to see all templates and overrides.
2. Use `sd-ai-agent/parse-block-content` on a template's content to analyse structure.

### Add a section site-wide

For elements that appear on every page (announcement bar, banner, footer CTA), edit the relevant **template part** (e.g. `parts/header.html`) rather than each template individually.

### Find patterns for page building

1. Use `sd-ai-agent/list-block-patterns` with a relevant category (`featured`, `header`, `footer`, etc.).
2. Review pattern content for suitable layouts.
3. Adapt the pattern's block markup using `sd-ai-agent/create-block-content`.

### Override a parent theme template (child theme)

Place a same-named `.html` file in the child theme's `templates/` or `parts/` directory. WordPress prefers the child version.

## Verification

After editing templates or `theme.json`:

1. Visit the front-end and confirm the change rendered.
2. Check the Site Editor (`/wp-admin/site-editor.php`) — it should reflect the saved state.
3. If `theme.json` settings appear ignored, clear the WordPress object cache (`wp cache flush`) and hard-refresh.
4. For child theme overrides, confirm the active stylesheet is the child (`wp option get stylesheet`).
