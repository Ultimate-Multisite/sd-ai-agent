# Kadence Theme

## When to Use

This skill is auto-loaded when the active theme (or active parent theme) is **Kadence Theme** or a Kadence child theme. Use it whenever the user asks about the Kadence header builder, footer builder, customizer panels, theme hooks, page layouts, or "Kadence settings."

For Kadence Blocks (the plugin) markup rules, use the `kadence-blocks` skill. For generic classic-theme work, use `classic-themes`.

## How to confirm Kadence is active

```bash
wp option get template       # Returns "kadence" if Kadence is the parent theme
wp option get stylesheet     # Returns the active child theme directory (or "kadence")
```

Programmatic check: `'kadence' === wp_get_theme()->get_template()`. Many sites use a Kadence child theme — its `stylesheet` is the child slug, but `template` is still `kadence`.

## Theme type

Kadence Theme is a **classic theme** with strong block editor integration. It uses:
- PHP templates (`index.php`, `single.php`, `page.php`, etc.) — not FSE templates
- Customizer for site-wide settings (header, footer, colors, typography)
- A partial `theme.json` to feed editor presets
- Hooks (`kadence_*` actions/filters) instead of template parts

Treat structural questions as classic-theme work; treat editor presets and palettes as `theme.json`-driven.

## Header / Footer Builder

Kadence ships a drag-and-drop header builder accessible via **Customizer → Header**. The header is split into rows (top, main, bottom) and slots (left, center, right). Common elements:

- Site identity (logo, title, tagline)
- Primary navigation
- Secondary navigation
- Search
- Account / login icons
- Buttons (CTA)
- HTML / shortcode blocks
- Social icons
- Mobile-only and desktop-only variants

The footer builder works the same way, exposed under **Customizer → Footer**. It supports up to six widget areas plus top/bottom rows.

For most user requests like "add a CTA to the header," "change menu position," or "show a phone number in the top bar," steer them to **Customizer → Header / Footer** rather than editing PHP.

## Customizer panels of note

- **General → Site Identity** — logo, title, tagline, favicon
- **General → Layout** — content width, sidebar layout (default for posts vs pages)
- **General → Colors** — global palette (matches `theme.json` palette)
- **General → Typography** — font families, sizes, weights for headings/body
- **General → Buttons** — global button colors and styles
- **Header → Header Builder**
- **Footer → Footer Builder**
- **Posts/Pages → Layout** — per-post-type layout overrides
- **Performance** — preload, font loading, lazy load
- **Custom CSS** — global custom CSS (also available via Site Editor → Customize CSS in newer versions)

## Hook system

Kadence exposes do_action / apply_filters hooks where users typically want to insert custom content. The most useful:

| Hook | Fires |
|---|---|
| `kadence_before_header` / `kadence_after_header` | Around the header |
| `kadence_before_main_content` / `kadence_after_main_content` | Around the main content area |
| `kadence_before_footer` / `kadence_after_footer` | Around the footer |
| `kadence_single_before_entry_title` | Inside single post, before title |
| `kadence_single_after_entry_content` | Inside single post, after content |
| `kadence_archive_before_loop` / `kadence_archive_after_loop` | In archive templates |

Use these from a child theme's `functions.php` instead of overriding template files when possible.

```php
add_action( 'kadence_before_main_content', function() {
    if ( is_page( 'pricing' ) ) {
        echo '<div class="announcement-bar">Limited-time offer ends Friday.</div>';
    }
} );
```

## Page Layout System

Kadence offers per-page layout settings via a metabox on each post/page. Options include:

- **Layout** — Normal, Full Width, Narrow, Fullwidth Unrestricted
- **Title** — Above content, hide, custom
- **Sidebar** — Left, right, or none
- **Content Style** — Boxed, Unboxed
- **Vertical Padding** — Default, hide, custom
- **Transparent Header** — Yes/No

These override the global Customizer defaults for that one post/page. Helpful when building landing pages.

## Pro vs Free

The free Kadence theme covers most of the above. **Kadence Pro** adds:
- Header/footer expansion options (sticky configurations, transparent header, mobile-specific menus)
- Hooked elements (visual UI for inserting content at hook points)
- Conditional element display (show/hide by URL, post type, user role)
- Mega menus
- WooCommerce design enhancements

When the user asks for a feature that requires Pro, say so plainly rather than implementing a workaround.

## Child theme guidance

Kadence ships a starter child theme. Use it (or `wp scaffold child-theme kadence-child --parent_theme=kadence`) for any custom CSS, PHP, or template overrides. The child theme's `functions.php` runs in addition to the parent's — never copy parent code into the child.

For style-only tweaks, prefer **Customizer → Custom CSS** over the child theme's `style.css` so changes survive theme switches less abruptly and are surfaced where the user expects them.

## Common requests and where to handle them

| Request | Where to do it |
|---|---|
| Change header logo / menu | Customizer → Header → drag elements |
| Add a button to the header | Customizer → Header → drop a Button element into a slot |
| Site-wide color change | Customizer → General → Colors |
| Hide title on a specific page | Edit page → Kadence Layout sidebar → Title → Hide |
| Add announcement bar | Customizer → Header → Top Row → HTML element (or Pro: Hooked Elements) |
| Inject CTA before footer | Child theme `functions.php` + `kadence_after_main_content` hook (or Pro: Hooked Elements) |
| Custom blog archive layout | Customizer → Posts → Archives, then use Kadence Blocks for content |

## Verification

1. After Customizer changes, check the live preview before publishing.
2. After child-theme PHP changes, reload the affected page and watch for fatal errors. Run in staging when possible.
3. Confirm `wp option get stylesheet` returns the expected (parent or child) theme.
4. Customizer settings are stored in `theme_mods_{stylesheet}` — to inspect: `wp option get theme_mods_$(wp option get stylesheet)`.
