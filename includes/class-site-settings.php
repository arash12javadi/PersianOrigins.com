<?php

/**
 * Floating site settings (theme, fonts, direction).
 *
 * @package PersianOrigins
 */
defined('ABSPATH') || exit;

class Persian_Origins_Site_Settings
{

    private $language_switcher;

    /**
     * Structured font metadata grouped by language and slug.
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    private $fonts = [
        'en' => [],
        'fa' => [],
    ];

    private $theme_cookie       = 'po_site_theme';
    private $font_cookie_prefix = 'po_font_';
    private $cookie_max_age;

    public function __construct(Persian_Origins_Language_Switcher $language_switcher)
    {
        $this->language_switcher = $language_switcher;
        $this->cookie_max_age    = YEAR_IN_SECONDS;
    }

    public function register(): void
    {
        $this->load_fonts_metadata();

        add_filter('body_class', [$this, 'filter_body_class']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'], 20);
        add_action('wp_footer', [$this, 'render_panel'], 20);
    }

    public function get_script_data(): array
    {
        $current_language = $this->get_current_language();

        return [
            'theme'           => $this->get_theme_preference(),
            'currentFonts'    => [
                'en' => $this->get_font_preference('en'),
                'fa' => $this->get_font_preference('fa'),
            ],
            'fontOptions'     => $this->build_font_options(),
            'currentLanguage' => $current_language,
            'rtl'             => $this->is_rtl(),
            'cookieMaxAge'    => $this->cookie_max_age,
        ];
    }

    private function get_theme_preference(): string
    {
        $theme = isset($_COOKIE[$this->theme_cookie])
            ? sanitize_key(wp_unslash($_COOKIE[$this->theme_cookie]))
            : '';

        return in_array($theme, ['dark', 'light'], true) ? $theme : 'light';
    }

    private function get_font_preference(string $language): string
    {
        $cookie_name = $this->font_cookie_prefix . $language;
        $stored      = isset($_COOKIE[$cookie_name]) ? sanitize_key(wp_unslash($_COOKIE[$cookie_name])) : '';

        if ('system' === $stored || '' === $stored) {
            return 'system';
        }

        if (isset($this->fonts[$language][$stored])) {
            return $stored;
        }

        return 'system';
    }

    private function get_current_language(): string
    {
        $language = $this->language_switcher->get_current_language();

        return $language ? sanitize_key($language) : 'en';
    }

    private function is_rtl(): bool
    {
        return 'fa' === $this->get_current_language();
    }

    private function load_fonts_metadata(): void
    {
        $base_path = trailingslashit(PERSIAN_ORIGINS_PLUGIN_DIR) . 'assets/fonts';

        foreach (array_keys($this->fonts) as $language) {
            $language_path = trailingslashit($base_path) . $language;
            if (!is_dir($language_path)) {
                continue;
            }

            $font_directories = glob(trailingslashit($language_path) . '*', GLOB_ONLYDIR);
            if (!$font_directories) {
                continue;
            }

            foreach ($font_directories as $font_directory) {
                $folder      = basename($font_directory);
                $slug        = sanitize_title($folder);
                $label       = $this->format_font_label($folder);
                $family_name = $this->format_font_family($folder);

                $variant_map = [];
                $files       = glob(trailingslashit($font_directory) . '*.{woff,woff2}', GLOB_BRACE);
                if (!$files) {
                    continue;
                }

                foreach ($files as $file) {
                    $ext      = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $basename = pathinfo($file, PATHINFO_FILENAME);

                    if (!in_array($ext, ['woff', 'woff2'], true)) {
                        continue;
                    }

                    $variant_key = strtolower($basename);
                    if (!isset($variant_map[$variant_key])) {
                        $variant_map[$variant_key] = [
                            'weight'  => $this->detect_font_weight($basename),
                            'style'   => $this->detect_font_style($basename),
                            'sources' => [],
                        ];
                    }

                    $relative = str_replace('\\', '/', ltrim(str_replace(PERSIAN_ORIGINS_PLUGIN_DIR, '', $file), '/'));
                    $variant_map[$variant_key]['sources'][$ext] = $relative;
                }

                if (empty($variant_map)) {
                    continue;
                }

                $variants = [];
                foreach ($variant_map as $variant) {
                    $sources = [];
                    foreach (['woff2', 'woff'] as $ext_key) {
                        if (!isset($variant['sources'][$ext_key])) {
                            continue;
                        }

                        $url    = esc_url_raw(PERSIAN_ORIGINS_PLUGIN_URL . ltrim($variant['sources'][$ext_key], '/'));
                        $format = ('woff2' === $ext_key) ? 'woff2' : 'woff';
                        $sources[] = "url('{$url}') format('{$format}')";
                    }

                    if (empty($sources)) {
                        continue;
                    }

                    $variants[] = [
                        'src'    => implode(', ', $sources),
                        'weight' => $variant['weight'],
                        'style'  => $variant['style'],
                    ];
                }

                if (empty($variants)) {
                    continue;
                }

                $this->fonts[$language][$slug] = [
                    'slug'     => $slug,
                    'label'    => $label,
                    'family'   => $family_name,
                    'variants' => $variants,
                    'class'    => 'po-font-' . $language . '-' . $slug,
                ];
            }

            if (!empty($this->fonts[$language])) {
                uasort(
                    $this->fonts[$language],
                    static function ($a, $b) {
                        return strcmp($a['label'], $b['label']);
                    }
                );
            }
        }
    }

    private function format_font_label(string $folder): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $folder));
    }

    private function format_font_family(string $folder): string
    {
        return str_replace(['_', '-'], ' ', $folder);
    }

    private function detect_font_weight(string $name): int
    {
        $name = strtolower($name);

        $map = [
            'thin'       => 100,
            'hairline'   => 100,
            'extralight' => 200,
            'ultralight' => 200,
            'light'      => 300,
            'book'       => 350,
            'regular'    => 400,
            'normal'     => 400,
            'medium'     => 500,
            'semibold'   => 600,
            'demibold'   => 600,
            'bold'       => 700,
            'extrabold'  => 800,
            'ultrabold'  => 800,
            'black'      => 900,
            'heavy'      => 900,
        ];

        foreach ($map as $keyword => $weight) {
            if (false !== strpos($name, $keyword)) {
                return $weight;
            }
        }

        return 400;
    }

    private function detect_font_style(string $name): string
    {
        return (false !== stripos($name, 'italic')) ? 'italic' : 'normal';
    }

    // private function build_font_options(): array
    // {
    //     $options = [];

    //     foreach (array_keys($this->fonts) as $language) {
    //         $options[$language]   = [];
    //         $options[$language][] = [
    //             'slug'  => 'system',
    //             'label' => __('System default', 'persian-origins'),
    //         ];

    //         foreach ($this->fonts[$language] as $slug => $font) {
    //             $options[$language][] = [
    //                 'slug'  => $slug,
    //                 'label' => $font['label'],
    //             ];
    //         }
    //     }

    //     return $options;
    // }

    private function build_font_options(): array
    {
        $options = [];

        // Persian label overrides by slug
        $fa_labels = [
            'vazir'         => 'وزیر',
            'vazir-bold'    => 'وزیر بولد',
            'sahel'         => 'ساحل',
            'sahel-bold'    => 'ساحل بولد',
            'samim'         => 'صمیم',
            'samim-bold'    => 'صمیم بولد',
            'shabnam'       => 'شبنم',
            'shabnam-bold'  => 'شبنم بولد',
            'tanha'         => 'تنها',
            'parastoo'      => 'پرستو',
            'parastoo-bold' => 'پرستو بولد',
            // add more if you add new folders/slugs
        ];

        foreach (array_keys($this->fonts) as $language) {
            $options[$language]   = [];
            $options[$language][] = [
                'slug'  => 'system',
                'label' => __('System default', 'persian-origins'),
            ];

            foreach ($this->fonts[$language] as $slug => $font) {
                $label = $font['label'];

                // If Persian, override with our Persian names when available
                if ($language === 'fa' && isset($fa_labels[$slug])) {
                    $label = $fa_labels[$slug];
                }

                $options[$language][] = [
                    'slug'  => $slug,
                    'label' => $label,
                ];
            }
        }

        // Keep this so you can still tweak labels with a filter if needed
        $options = apply_filters('persian_origins_font_options', $options);

        return $options;
    }


    public function filter_body_class(array $classes): array
    {
        $theme    = $this->get_theme_preference();
        $language = $this->get_current_language();
        $font     = $this->get_font_preference($language);

        $classes[] = 'po-theme-' . $theme;
        $classes[] = 'po-lang-' . $language;
        $classes[] = $this->is_rtl() ? 'po-dir-rtl' : 'po-dir-ltr';

        if ('system' !== $font && isset($this->fonts[$language][$font])) {
            $classes[] = $this->fonts[$language][$font]['class'];
        }

        return array_values(array_unique($classes));
    }

    public function enqueue_assets(): void
    {
        if (empty($this->fonts['en']) && empty($this->fonts['fa'])) {
            return;
        }

        // Ensure the base handle exists (main plugin enqueues this already).
        if (!wp_style_is('po-fa-overrides', 'enqueued')) {
            wp_enqueue_style(
                'po-fa-overrides',
                PERSIAN_ORIGINS_PLUGIN_URL . 'assets/css/fa.overrides.css',
                ['po-base'],
                Persian_Origins_Plugin::VERSION
            );
        }

        $css = $this->generate_font_face_css();
        if ($css) {
            wp_add_inline_style('po-fa-overrides', $css);
        }
    }



    private function generate_font_face_css(): string
    {
        $css = '';

        foreach ($this->fonts as $language => $fonts) {
            foreach ($fonts as $font) {
                $family = $this->escape_css_string($font['family']); // e.g., "Open Sans"

                // --- 1) @font-face blocks ---
                foreach ($font['variants'] as $variant) {
                    $style  = ($variant['style'] === 'italic') ? 'italic' : 'normal';
                    $weight = (int) $variant['weight'];

                    $css .= '@font-face{';
                    $css .= 'font-family:"' . $family . '";';
                    $css .= 'font-style:' . $style . ';';
                    $css .= 'font-weight:' . $weight . ';';
                    $css .= 'font-display:swap;';
                    $css .= 'src:' . $variant['src'] . ';';
                    $css .= '}';
                }

                // --- 2) Apply rules ---
                $slug     = $font['slug'];          // folder -> slug (e.g. Open_Sans -> open-sans)
                $class    = $font['class'];         // e.g. po-font-en-open-sans
                $fallback = ($language === 'fa')
                    ? '"Tahoma","Arial",sans-serif'
                    : '"Helvetica Neue",Arial,sans-serif';

                /* A) BODY class — only when that language is active.
             *    This prevents FA body rules from overriding EN pages and vice-versa.
             */
                $css .= 'body.po-lang-' . $language . '.' . $class . ','
                    .  'body.po-lang-' . $language . '.' . $class . ' *'
                    .  '{font-family:"' . $family . '",' . $fallback . ' !important;}';

                /* B) ARTICLE attribute — JS writes data-font-<lang>="<slug>".
             *    Works for previews and fine-grained targeting inside content.
             */
                $css .= 'article[data-font-' . $language . '="' . $slug . '"],'
                    .  'article[data-font-' . $language . '="' . $slug . '"] *'
                    .  '{font-family:"' . $family . '",' . $fallback . ' !important;}';
            }
        }

        return $css;
    }




    private function escape_css_string(string $value): string
    {
        return str_replace(['"', '\''], '', $value);
    }

    public function render_panel(): void
    {
        $current_language = $this->get_current_language();
        $current_theme    = $this->get_theme_preference();
        $current_fonts    = [
            'en' => $this->get_font_preference('en'),
            'fa' => $this->get_font_preference('fa'),
        ];
        $font_options     = $this->build_font_options();
?>
        <div class="po-site-settings" data-current-language="<?php echo esc_attr($current_language); ?>">
            <button type="button" class="po-site-settings__toggle" aria-expanded="false" aria-controls="po-site-settings-panel">
                <span class="po-site-settings__toggle-icon" aria-hidden="true">&#9881;</span>
                <span class="po-site-settings__toggle-label"><?php esc_html_e('Site settings', 'persian-origins'); ?></span>
            </button>
            <div class="po-site-settings__panel" id="po-site-settings-panel" hidden>
                <div class="po-site-settings__section">
                    <label for="po-site-settings-theme" class="po-site-settings__label"><?php esc_html_e('Theme', 'persian-origins'); ?></label>
                    <select id="po-site-settings-theme" class="po-site-settings__select">
                        <option value="light" <?php selected('light', $current_theme); ?>><?php esc_html_e('Light', 'persian-origins'); ?></option>
                        <option value="dark" <?php selected('dark', $current_theme); ?>><?php esc_html_e('Dark', 'persian-origins'); ?></option>
                    </select>
                </div>
                <div class="po-site-settings__section">
                    <span class="po-site-settings__label"><?php esc_html_e('Font', 'persian-origins'); ?></span>
                    <?php foreach ($font_options as $language => $options) : ?>
                        <div class="po-site-settings__group" data-language="<?php echo esc_attr($language); ?>" <?php echo ($language === $current_language) ? '' : 'hidden'; ?>>
                            <label for="po-site-settings-font-<?php echo esc_attr($language); ?>" class="po-site-settings__sub-label">
                                <?php echo ('fa' === $language)
                                    ? esc_html__('Persian font', 'persian-origins')
                                    : esc_html__('English font', 'persian-origins'); ?>
                            </label>
                            <select
                                id="po-site-settings-font-<?php echo esc_attr($language); ?>"
                                class="po-site-settings__select po-site-settings__select--font"
                                data-language="<?php echo esc_attr($language); ?>">
                                <?php foreach ($options as $option) : ?>
                                    <option value="<?php echo esc_attr($option['slug']); ?>" <?php selected($option['slug'], $current_fonts[$language]); ?>>
                                        <?php echo esc_html($option['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="po-site-settings__section">
                    <span class="po-site-settings__label" id="po-font-size-label"><?php esc_html_e('Text size', 'persian-origins'); ?></span>
                    <div class="po-site-settings__font-size-controls" role="group" aria-labelledby="po-font-size-label">
                        <button type="button" class="po-site-settings__btn po-font-size--decrease" aria-label="<?php esc_attr_e('Decrease text size', 'persian-origins'); ?>">A−</button>
                        <output id="po-font-size-output" class="po-site-settings__font-size-output" aria-live="polite">100%</output>
                        <button type="button" class="po-site-settings__btn po-font-size--increase" aria-label="<?php esc_attr_e('Increase text size', 'persian-origins'); ?>">A+</button>
                        <button type="button" class="po-site-settings__btn po-font-size--reset" aria-label="<?php esc_attr_e('Reset text size', 'persian-origins'); ?>">
                            <?php esc_html_e('Reset', 'persian-origins'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

<?php
    }
}
