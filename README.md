# Persian Origins Plugin

Provides a bilingual front-end experience for PersianOrigins.com: language switching (EN/FA), per-language font selection, theme (light/dark), content direction (LTR/RTL) scoping, reading progress, story navigation, and a small cookie notice.

The plugin is built to be theme-agnostic, a11y-friendly, and performant (no heavy frameworks, lazy CSS, font-display: swap).

## Shortcodes Documentation

The plugin registers three shortcodes for bilingual content management.

---

### [language_switch]

Renders the language switcher UI with flag icons and toggle functionality.

#### Syntax

```
[language_switch mode="inline" class="" link_class="" outer_class=""]
```

#### Attributes

| Attribute     | Type   | Default  | Description                                                 |
| ------------- | ------ | -------- | ----------------------------------------------------------- |
| `mode`        | string | `inline` | Display mode: `inline` or `floating`                        |
| `class`       | string | `''`     | Additional CSS classes for the inner wrapper                |
| `link_class`  | string | `''`     | CSS classes for the language link elements                  |
| `outer_class` | string | Auto     | CSS classes for the outer wrapper (auto-generated if empty) |

#### Examples

**Basic usage:**

```
[language_switch]
```

**Floating mode:**

```
[language_switch mode="floating"]
```

**With custom classes:**

```
[language_switch class="my-custom-class" link_class="custom-link"]
```

**With outer wrapper class:**

```
[language_switch outer_class="my-wrapper-class"]
```

#### Notes

- Respects current language context
- Swaps flag images in DOM order for consistent stacking
- Links to opposite language page or safe fallback
- Works in pages, posts, and shortcode-enabled widgets
- Styled by plugin's global CSS
- Auto-assigns wrapper classes based on mode if `outer_class` is empty

---

### [continue_reading]

Displays a "Continue Reading" button that directs users to the next unread post in a category.

#### Syntax

```
[continue_reading category="" class=""]
```

#### Attributes

| Attribute  | Type       | Default  | Description                           |
| ---------- | ---------- | -------- | ------------------------------------- |
| `category` | string/int | Required | Category slug or ID                   |
| `class`    | string     | `''`     | Additional CSS classes for the button |

#### Examples

**With category slug:**

```
[continue_reading category="tutorials"]
```

**With category ID:**

```
[continue_reading category="42"]
```

**With custom styling:**

```
[continue_reading category="guides" class="btn-primary btn-lg"]
```

#### Behavior

- Tracks read posts via localStorage with cookie fallback
- Returns empty string if category parameter is missing or invalid
- Accepts both category slug and numeric ID
- Calculates next unread post within specified category
- Reading progress is per-browser (not server-side)
- May point to first post if all posts are read

---

### [po_categories]

Renders a responsive grid of categories with bilingual titles, descriptions, and optional images.

#### Syntax

```
[po_categories taxonomy="category" include="" exclude="" hide_empty="false"
               number="" orderby="name" order="ASC" columns="3"
               image_size="medium" parent=""]
```

#### Attributes

| Attribute    | Type   | Default    | Description                                 |
| ------------ | ------ | ---------- | ------------------------------------------- |
| `taxonomy`   | string | `category` | Taxonomy to query                           |
| `include`    | string | `''`       | Comma-separated term IDs to include         |
| `exclude`    | string | `''`       | Comma-separated term IDs to exclude         |
| `hide_empty` | string | `false`    | Whether to hide categories with no posts    |
| `number`     | int    | `''`       | Maximum number of categories to display     |
| `orderby`    | string | `name`     | Sort field (name, count, slug, etc.)        |
| `order`      | string | `ASC`      | Sort order: `ASC` or `DESC`                 |
| `columns`    | int    | `3`        | Number of grid columns (1-6)                |
| `image_size` | string | `medium`   | WordPress image size slug                   |
| `parent`     | int    | `''`       | Parent term ID (use `0` for top-level only) |

#### Examples

**Default 3-column grid:**

```
[po_categories]
```

**4 columns with 12 items:**

```
[po_categories columns="4" number="12"]
```

**Specific categories with thumbnails:**

```
[po_categories include="5,12,18" image_size="thumbnail"]
```

**Top-level categories by post count:**

```
[po_categories parent="0" orderby="count" order="DESC"]
```

**Include specific categories:**

```
[po_categories include="3,7,15,22"]
```

**Exclude specific categories:**

```
[po_categories exclude="1,9"]
```

**Custom taxonomy:**

```
[po_categories taxonomy="custom_tax" columns="2"]
```

#### Output Structure

Each category card displays:

- **Optional featured image** (if set via category meta)
- **Bilingual title** (English from core, Persian from meta with fallback)
- **Bilingual description** (English from core, Persian from meta with fallback)
- **Automatic language toggling** via body classes (`po-lang-en` / `po-lang-fa`)

#### Bilingual Behavior

- English content uses standard WordPress category fields
- Persian content uses custom meta fields:
  - `po_cat_name_fa` for Persian title
  - `po_cat_desc_fa` for Persian description
  - `po_cat_img_id` for featured image
- Falls back to English if Persian content is missing
- Language visibility controlled by body class CSS

#### Styling

The shortcode includes minimal built-in CSS for:

- Responsive grid layout (1-6 columns)
- Card structure and spacing
- Language-specific content visibility
- Customize appearance using CSS variables:
  - `--po-card-bg` (default: `#f7f7f7`)
  - `--po-border` (default: `#e5e5e5`)
  - `--po-muted` (default: `#555`)

#### Notes

- Returns empty string if no categories found
- Images are lazy-loaded for performance
- All category names and descriptions are properly escaped
- Links point to category archive pages
- Respects WordPress taxonomy hierarchy

---

### [site_settings]

Renders a site settings panel with controls for theme, fonts, and text size preferences.

#### Syntax

```
[site_settings class="" mode="full"]
```

#### Attributes

| Attribute | Type   | Default | Description                                                           |
| --------- | ------ | ------- | --------------------------------------------------------------------- |
| `class`   | string | `''`    | Additional CSS classes for the wrapper                                |
| `mode`    | string | `full`  | Display mode: `full` (with toggle button) or `panel` (always visible) |

#### Examples

**Basic usage (with toggle button):**

```
[site_settings]
```

**Always visible panel:**

```
[site_settings mode="panel"]
```

**With custom class:**

```
[site_settings class="my-custom-settings"]
```

**Panel-only mode with class:**

```
[site_settings mode="panel" class="sidebar-settings"]
```

#### Features

The site settings panel includes three control sections:

##### 1. Theme Selector

- Toggle between light and dark themes
- Preference saved in cookies (`po_site_theme`)
- Applies `.po-theme-light` or `.po-theme-dark` body class

##### 2. Font Selector

- Separate font controls for English and Persian
- Shows/hides based on current language context
- Options include:
  - System default
  - Custom fonts loaded from plugin's `assets/fonts/` directory
- Supports multiple font weights and styles (regular, bold, italic)
- Preferences saved in cookies (`po_font_en`, `po_font_fa`)
- Applies language-specific font classes to body

##### 3. Text Size Controls

- Increase text size (A+)
- Decrease text size (A−)
- Reset to default (Reset button)
- Live output display showing current percentage
- Accessible controls with ARIA labels

#### Display Modes

**Full Mode (default):**

- Includes a toggle button with gear icon (⚙)
- Panel is initially hidden
- Click button to show/hide settings
- Ideal for header/sidebar integration

**Panel Mode:**

- No toggle button
- Panel is always visible
- Useful for dedicated settings pages
- Suitable for embedding in page content

#### Technical Details

**Font System:**

- Automatically loads fonts from `/assets/fonts/en/` and `/assets/fonts/fa/`
- Supports WOFF and WOFF2 formats
- Generates `@font-face` declarations dynamically
- Font weights detected from filenames (thin, light, regular, medium, bold, etc.)
- Font styles detected from filenames (italic detection)

**Cookie Storage:**

- Theme: `po_site_theme` (values: `light`, `dark`)
- English font: `po_font_en` (value: font slug or `system`)
- Persian font: `po_font_fa` (value: font slug or `system`)
- Cookie expiration: 1 year

**Body Classes Applied:**

- Theme: `.po-theme-light` or `.po-theme-dark`
- Language: `.po-lang-en` or `.po-lang-fa`
- Direction: `.po-dir-ltr` or `.po-dir-rtl`
- Font: `.po-font-{language}-{slug}` (e.g., `.po-font-en-open-sans`)

**Accessibility:**

- Proper ARIA labels and attributes
- Keyboard accessible controls
- Live regions for dynamic updates
- Semantic HTML structure

#### Integration Notes

- A floating version is automatically rendered in `wp_footer` (priority 20)
- Shortcode version adds `.po-site-settings--shortcode` class
- Panel-only mode adds `.po-site-settings--panel-only` class
- Each instance has a unique ID to prevent conflicts
- Works seamlessly with the language switcher
- Font changes apply immediately via body classes
- Theme changes trigger instant visual updates

#### Styling

The component uses CSS variables for easy customization:

- Custom styling can target `.po-site-settings` wrapper
- Panel styles use `.po-site-settings__panel`
- Button styles use `.po-site-settings__btn`
- Select dropdowns use `.po-site-settings__select`

#### Example Use Cases

**In a sidebar widget:**

```
[site_settings mode="panel" class="widget-settings"]
```

**In page content with toggle:**

```
[site_settings class="content-settings"]
```

**Dedicated settings page:**

```
[site_settings mode="panel"]
```

---

### Bilingual Content

- **English:** Uses term's native `name` and `description`
- **Persian:** Pulls from custom term meta (`name_fa`, `desc_fa`) with EN fallback
- **Language visibility:** Controlled by body classes
  - `body.po-lang-en .po-text--fa { display:none }`
  - `body.po-lang-fa .po-text--en { display:none }`

### Images

- Retrieves featured image from term meta
- Uses `wp_get_attachment_image()` with specified size
- Falls back gracefully if no image exists

---

## Styling

### CSS Classes Available

**Language Switch:**

- `.po-language-switch` - Root wrapper
- Custom classes via `class` and `link_class` attributes

**Continue Reading:**

- `.po-continue-reading` - Button wrapper
- Custom classes via `class` attribute

**Categories Grid:**

- `.po-cat-grid` - Grid container
- `.po-cat-grid.cols-N` - Column variants (N = 1-6)
- `.po-cat-card` - Individual card
- `.po-cat-card__media` - Image wrapper
- `.po-cat-card__body` - Content wrapper
- `.po-cat-title` - Title heading
- `.po-cat-desc` - Description wrapper
- `.po-text--en` - English text
- `.po-text--fa` - Persian text

---

## Best Practices

1. **Sanitization:** All attributes are sanitized automatically
2. **Invalid Input:** Returns empty string instead of errors
3. **Custom CSS:** Add theme overrides after plugin styles
4. **Class Attributes:** Use space-separated valid class names
5. **Language Toggle:** Both languages render; body class controls visibility

---

## Common Use Cases

**Sidebar language switcher:**

```
[language_switch class="sidebar-widget"]
```

**Category archive with continue reading:**

```
[po_categories category="articles" columns="3"]
[continue_reading category="articles" class="mt-4"]
```

**Featured categories homepage:**

```
[po_categories include="5,7,9,12" columns="4" image_size="large" hide_empty="true"]
```

**Tag cloud alternative:**

```
[po_categories taxonomy="post_tag" orderby="count" order="DESC" number="20" columns="5"]
```

## Features

### Language switcher (EN ⇄ FA)

- Front-end floating switch with a compact flag tab that slides a panel in from the left.
- Keeps its position fixed on the left for both LTR/RTL.
- Redirects to the matching translation (if available) or safely falls back.
- Persists user preference in cookie/user meta.
- Adds semantic classes to `<body>` (e.g. `po-lang-fa`, `po-dir-rtl`).

### Per-language fonts (dynamic @font-face)

- Auto-discovers fonts in `assets/fonts/en/*` and `assets/fonts/fa/*` (WOFF/WOFF2).
- Detects weight/style from filename (e.g. Bold, Italic).
- Generates `@font-face` + rules on the fly; applies via:
  - `body.po-lang-<lang>.po-font-<lang>-<slug>`
  - `article[data-font-<lang>="<slug>"]` (fine-grained & previews)
- Per-language font selection UI inside the Site Settings panel.
- Cookies: `po_font_en`, `po_font_fa`.

### Theme switch (light/dark)

- Respects system preference, persists theme (`localStorage` + cookie `po_theme`).
- Applies early to prevent FOUC, mirrors on `<body>` as `po-theme-<light|dark>` and on articles via `data-theme`.

### Direction control (RTL/LTR)

- Only flips post content to RTL when Persian is active (`po-dir-rtl` on body).
- Keeps UI widgets (language switch, settings panel) LTR to avoid mirrored UX.

### Reading progress & story navigation

- Tracks read posts per category (via `localStorage` + cookie), draws simple progress bars.
- A "previous/next" story navigation component with mirrored layout for FA.

### Cookie popup

- Small, theme-neutral cookie notice (class included & registered).

### A11y & UX niceties

- Focus rings, ESC to close settings, click-outside to dismiss, ARIA labels, keyboard-navigable toggle.

## Requirements

- WordPress 6.x+
- PHP 7.4+ (8.x recommended)
- Theme that calls `wp_head()`/`wp_footer()` properly

## Installation

1. Copy the plugin folder to `wp-content/plugins/persian-origins/`.
2. Ensure your fonts live under:
   ```
   assets/fonts/en/<your-font-folder>/*.woff, *.woff2
   assets/fonts/fa/<your-font-folder>/*.woff, *.woff2
   ```
3. Activate **Persian Origins Plugin** from WP Admin → Plugins.

## How it works (architecture)

### Bootstrapping (Persian_Origins_Plugin)

Defines constants:

- `PERSIAN_ORIGINS_PLUGIN_FILE`, `PERSIAN_ORIGINS_PLUGIN_DIR`, `PERSIAN_ORIGINS_PLUGIN_URL`, version.

Loads component classes:

- `class-language-switcher.php`
- `class-site-settings.php`
- `class-story-navigation.php`
- `class-reading-progress.php`
- `class-shortcodes.php`
- `class-po-translations.php`
- `class-content.php`
- `class-menu-translation.php`
- `class-excerpt-translation.php`
- `class-po-category-meta.php`
- `class-cookie-popup.php`

Registers assets:

- **CSS**: base, language switch, story, site settings, direction rules, FA overrides, Font Awesome utils.
- **JS**: `assets/js/frontend.js` (+ localized data).

### Language switching (Persian_Origins_Language_Switcher)

#### Locale control

Hooks `locale` filter to use `fa_IR` for Persian on the front-end (keeps wp-admin untouched).

#### Dynamic gettext

Hooks `gettext` and uses a simple dictionary (`Persian_Origins_Translations::dictionary()`).

#### Body classes

Adds `po-lang-<fa|en>`, `po-dir-<rtl|ltr>` to `<body>`.

#### Floating switch UI

Printed in `wp_footer`. Markup highlights:

- Outer wrapper: `.po-switch-wrap.is-fa|.is-en` (drives which flag appears on top).
- Tab button with circular flag overlap (FA/EN).
- Sliding panel with "Current: …" and the switch link.

#### Switch URL

Uses `po_switch_language=<fa|en>` + `_po_lang_nonce` (nonce is present in code—enable in prod) and `po_redirect` (base64).

#### Persistence

- **Logged-in**: user meta `_preferred_language`.
- **Guests**: cookie `po_preferred_language` (also sets `po_lang` for legacy compatibility).

#### Translation mapping

If viewing a singular post, attempts to find a translation via `_translation_of` post meta (both directions supported).

⚠️ **Security note**: the nonce check in `maybe_handle_language_switch()` had been commented out for testing. Now it is re-enable before going live.

#### Filters

- `persian_origins_language_switch_markup( $markup, $current_lang, $target_lang, $target_post_id )` – Customize rendered switch.
- `persian_origins_show_auto_language_switch` – Gate auto switch rendering (if you use it).

### Site settings (Persian_Origins_Site_Settings)

#### Fonts metadata

Scans `assets/fonts/{en,fa}/*` folders, builds variants (weight/style detection from filenames), produces `@font-face` and scoping rules:

**Body-level** (only when the matching language is active):

```css
body.po-lang-<lang>.po-font-<lang>-<slug> (+ *)
```

applies font globally for that language.

**Article-level**:

```css
article[data-font-<lang>="<slug>"] (+ *)
```

used by JS for previews and fine targeting.

#### CSS generation

Inline style appended to the `po-fa-overrides` handle, ordered after base styles.

#### Panel UI

Floating gear button opens a panel with:

- Theme selector (light/dark)
- Language-specific font dropdowns (only one visible at a time)
- Text size controls (A− / % / A+ / Reset) using `--po-font-size` on `<html>`

#### Cookies

- **Theme**: `po_theme` (plus system preference fallback).
- **Fonts**: `po_font_en`, `po_font_fa` (values match folder slugs).

#### Body classes

Adds `po-theme-<light|dark>` and active language classes. If a non-system font is selected, adds the font's class `po-font-<lang>-<slug>`.

#### Filters

- `persian_origins_font_options( $options )` – Alter how fonts are labeled in the dropdown (e.g. Persian names for FA).

### Front-end script (assets/js/frontend.js)

#### Theme early paint

Reads `localStorage`/cookie → sets `<html data-theme>` and `body.po-theme-*` before DOM ready.

#### Site settings init

- Sets initial theme + font per language.
- Wires the gear toggle, applies click-outside and Esc to close.
- Syncs the visible font group with current language.

#### Font application

- Body class is updated (`po-font-<lang>-<slug>`).
- Each article receives `data-font-<lang>="<slug>"`.

#### Text size

Stores a % in `localStorage` (`po_font_size_percent`) and sets `--po-font-size` on `<html>`.

#### Reading progress

Tracks read post IDs per category (`localStorage` + cookie), updates progress bar DOM.

#### Language cookie helper

Writes `po_preferred_language` (and legacy `po_lang`) so PHP code stays in sync.

## File/Folder structure (relevant parts)

```
assets/
  css/
    base.css
    components.language-switch.css
    components.site-settings.css
    components.story.css
    direction.css          # scopes RTL to post content, keeps widgets LTR
    fa.overrides.css       # handle for inline font CSS
    util.fontawesome.css
  fonts/
    en/
      Open_Sans/
        OpenSans-Regular.woff2
        OpenSans-Bold.woff2
        ...
    fa/
      Vazir/
        Vazir-Regular.woff2
        Vazir-Bold.woff2
        ...
  img/
    en-flag-w50.png
    fa-flag-w50.png
  js/
    frontend.js

includes/
  class-language-switcher.php
  class-site-settings.php
  class-story-navigation.php
  class-reading-progress.php
  class-shortcodes.php
  class-po-translations.php
  class-content.php
  class-menu-translation.php
  class-excerpt-translation.php
  class-po-category-meta.php
  class-cookie-popup.php

persian-origins-plugin.php   # main loader
```

## Adding fonts

Create a folder under `assets/fonts/en/` or `assets/fonts/fa/`, e.g.:

```
assets/fonts/en/open-sans/
  OpenSans-Regular.woff2
  OpenSans-Italic.woff2
  OpenSans-SemiBold.woff2
  OpenSans-Bold.woff2
```

The folder name → slug (`open-sans`), and readable family/label (spacing by `-`/`_`).

The file names should include weight/style keywords so detection works:

- Thin/Hairline → 100
- ExtraLight/UltraLight → 200
- Light → 300
- Regular/Normal → 400
- Medium → 500
- SemiBold/DemiBold → 600
- Bold → 700
- ExtraBold/UltraBold → 800
- Black/Heavy → 900
- Italic toggles `font-style: italic`

Reload — the font will appear in the Site Settings dropdown for that language.

## Body classes & attributes (for themes)

- `po-lang-en` / `po-lang-fa`
- `po-dir-ltr` / `po-dir-rtl`
- `po-theme-light` / `po-theme-dark`
- `po-font-<lang>-<slug>` (only when a non-system font is selected)

Articles get `data-font-<lang>="<slug>"` and `data-theme="<light|dark>"`.

`<html data-theme="light|dark">` for early paint and CSS theming.

## Hooks quick reference

### PHP filters:

- `persian_origins_language_switch_markup( $markup, $current_lang, $target_lang, $target_post_id )`
- `persian_origins_show_auto_language_switch` (bool)
- `persian_origins_font_options( $options )`

### WordPress core filters used:

- `locale` — switch to `fa_IR` on front-end when Persian active.
- `gettext` — simple runtime dictionary translations.
- `body_class` — language/theme/font/direction classes injection.

### JS custom event listened:

`poLangChange` — if your theme or other code dispatches `{ detail: { lang: 'en'|'fa' } }`, the settings panel will:

- Write language cookies.
- Switch visible font group.
- Re-apply the stored font for that language to articles.

## Accessibility

- Toggle buttons use `aria-expanded`.
- Panel can be closed with Esc and click outside.
- Focus outlines are preserved; hover-only interactions are mirrored with `:focus-within`.
- Language switch and settings use explicit `aria-labels`.

## Performance notes

- Fonts use `font-display: swap`.
- Inline generated CSS is attached to an already enqueued handle to minimize HTTP requests.
- All state is persisted in cookies and `localStorage`; server work is minimal.

## Security notes

- Re-enable the `_po_lang_nonce` verification in `maybe_handle_language_switch()` before production.
- All inputs (query/cookies/meta) are sanitized and escaped before output.

## Troubleshooting

**English font won't change:**  
Ensure the body-scoped rules check the active language (`body.po-lang-en.po-font-en-<slug>`). This prevents FA rules from winning due to cascade. The included generator already scopes correctly.

**Flags stacking order:**  
The top flag is controlled by `.po-switch-wrap.is-fa|.is-en`. If you customize, prefer DOM order swapping server-side (as implemented) over z-index tricks to avoid browser stacking quirks.

**Panel doesn't close on outside click:**  
Verify the JS in `initSiteSettings()` attaches document-level click and keydown (Escape) listeners, and that your theme doesn't stop propagation.

## Extending

- Add your own translations via `Persian_Origins_Translations::dictionary()` or replace it with a full i18n solution.
- Use `persian_origins_language_switch_markup` to inject custom HTML, tooltips, or analytics.
- Add more settings to the panel; follow the same JS binding pattern and persist to cookie/`localStorage` as needed.

## License

MIT (or your preferred license)

## Author

Persian Origins — [https://persianorigins.com](https://persianorigins.com)
