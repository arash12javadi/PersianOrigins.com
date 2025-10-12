<?php

/**
 * Shortcodes for the plugin.
 *
 * @package PersianOrigins
 */

defined('ABSPATH') || exit;

class Persian_Origins_Shortcodes
{

    private $language_switcher;
    private $reading_progress;

    public function __construct(Persian_Origins_Language_Switcher $language_switcher, Persian_Origins_Reading_Progress $reading_progress)
    {
        $this->language_switcher = $language_switcher;
        $this->reading_progress  = $reading_progress;
    }

    public function register(): void
    {
        add_shortcode('language_switch', [$this, 'render_language_switch']);
        add_shortcode('continue_reading', [$this, 'render_continue_reading']);
    }

    public function render_language_switch($atts = []): string
    {
        $atts = shortcode_atts(
            [
                'mode'        => 'inline', // inline | floating
                'class'       => '',
                'link_class'  => '',
                'outer_class' => '',       // lets you add a marker on the outer wrapper
            ],
            $atts,
            'language_switch'
        );

        // Map mode -> wrapper class (inner container)
        $variant_class = ($atts['mode'] === 'floating')
            ? 'po-language-switch--floating'
            : 'po-language-switch--inline';

        return $this->language_switcher->get_switch_markup([
            'wrapper_class' => $this->sanitize_class_attribute(trim($variant_class . ' ' . $atts['class'])),
            'link_class'    => $this->sanitize_class_attribute($atts['link_class']),
            'outer_class'   => $this->sanitize_class_attribute(
                $atts['outer_class'] ?: (
                    $atts['mode'] === 'floating'
                    ? 'po-switch-wrap--shortcode' // distinguish shortcode floaters
                    : 'po-switch-wrap--inline'
                )
            ),
        ]);
    }


    public function render_continue_reading($atts = []): string
    {
        $atts = shortcode_atts(
            [
                'category' => '',
                'class'    => '',
            ],
            $atts,
            'continue_reading'
        );

        if ('' === $atts['category']) {
            return '';
        }

        $term = get_term_by('slug', sanitize_title($atts['category']), 'category');

        if (!$term || is_wp_error($term)) {
            $term_id = absint($atts['category']);
            if ($term_id) {
                $term = get_term($term_id, 'category');
            }
        }

        if (!$term || is_wp_error($term)) {
            return '';
        }

        return $this->reading_progress->build_continue_button(
            (int) $term->term_id,
            [
                'class' => $this->sanitize_class_attribute($atts['class']),
            ]
        );
    }

    private function sanitize_class_attribute(string $class): string
    {
        $classes = preg_split('/\s+/', trim($class));
        if (!$classes) {
            return '';
        }

        $sanitized = array_map('sanitize_html_class', $classes);
        $sanitized = array_filter($sanitized);

        return implode(' ', $sanitized);
    }
}


class Persian_Origins_Categories_Shortcode
{
    public static function init()
    {
        add_shortcode('po_categories', [__CLASS__, 'render']);
        // Minimal CSS for layout + language toggle via body class
        add_action('wp_enqueue_scripts', [__CLASS__, 'styles']);
    }

    public static function styles()
    {
        $css = <<<CSS
        /* Neutral helpers only — no grid-template-columns here */
        .po-cat-card{border-radius:.75rem;overflow:hidden}
        .po-cat-card__media img{display:block;width:100%;height:auto}
        .po-cat-card__body{padding:.75rem 1rem}
        .po-cat-title{font-weight:600;margin:0 0 .35rem}
        .po-cat-desc{margin:0;color:var(--po-muted,#555)}
        body.po-lang-en .po-text--fa{display:none}
        body.po-lang-fa .po-text--en{display:none}
        .po-readmore{font-weight:600;text-decoration:underline}
        CSS;
        wp_register_style('po-cats-inline', false);
        wp_enqueue_style('po-cats-inline');
        wp_add_inline_style('po-cats-inline', $css);
    }


    private static function trim_like_excerpt(string $text, string $more_html = ''): string
    {
        $len = (int) apply_filters('excerpt_length', 55);

        // Fallback to theme’s excerpt_more if no custom "more" HTML was given
        if ($more_html === '') {
            $more_html = apply_filters('excerpt_more', ' &hellip;');
        }

        $trimmed = wp_trim_words(wp_strip_all_tags($text), $len, $more_html);

        return wp_kses_post(wpautop($trimmed));
    }


    public static function render($atts)
    {
        $atts = shortcode_atts([
            'taxonomy'   => 'category',
            'include'    => '',      // comma-separated term IDs
            'exclude'    => '',
            'hide_empty' => 'false',
            'number'     => '',      // limit
            'orderby'    => 'name',
            'order'      => 'ASC',
            'columns'    => '3',
            'image_size' => 'medium',
            'parent'     => '',      // e.g. parent=0 for top-level
        ], $atts, 'po_categories');

        $args = [
            'taxonomy'   => $atts['taxonomy'],
            'hide_empty' => filter_var($atts['hide_empty'], FILTER_VALIDATE_BOOLEAN),
            'orderby'    => sanitize_key($atts['orderby']),
            'order'      => (strtoupper($atts['order']) === 'DESC') ? 'DESC' : 'ASC',
        ];
        if ($atts['include']) $args['include'] = array_map('intval', explode(',', $atts['include']));
        if ($atts['exclude']) $args['exclude'] = array_map('intval', explode(',', $atts['exclude']));
        if ($atts['number']  !== '') $args['number'] = (int) $atts['number'];
        if ($atts['parent']  !== '') $args['parent'] = (int) $atts['parent'];

        $terms = get_terms($args);
        if (is_wp_error($terms) || empty($terms)) return '';

        $cols = max(1, min(6, (int) $atts['columns']));
        $img_size = sanitize_key($atts['image_size']);

        ob_start();
        echo '<div class="po-cat-grid cols-' . esc_attr($cols) . '">';

        foreach ($terms as $term) {
            // EN from core fields
            $name_en = $term->name;
            $desc_en = term_description($term->term_id, $term->taxonomy);

            // FA from our custom meta
            $name_fa = get_term_meta($term->term_id, Persian_Origins_Category_Meta::META_NAME_FA, true);
            $desc_fa = get_term_meta($term->term_id, Persian_Origins_Category_Meta::META_DESC_FA, true);

            // Fallbacks for FA if empty
            $name_fa_safe = $name_fa ?: $name_en;
            $desc_fa_safe = $desc_fa ?: $desc_en;

            // Image (optional)
            $img_id  = (int) get_term_meta($term->term_id, Persian_Origins_Category_Meta::META_IMG_ID, true);

            $link = get_term_link($term);
            if (is_wp_error($link)) $link = '#';

            echo '<article class="po-cat-card">';
            // Image
            if ($img_id) {
                echo '<a class="po-cat-card__media" href="' . esc_url($link) . '">';
                echo wp_get_attachment_image($img_id, $img_size, false, [
                    'alt' => esc_attr($name_en),
                    'loading' => 'lazy',
                    'decoding' => 'async',
                ]);
                echo '</a>';
            }

            echo '<div class="po-cat-card__body">';
            // Title (both langs, toggled by body class)
            echo '<h3 class="po-cat-title">';
            echo '<a href="' . esc_url($link) . '">';
            echo '<span class="po-text--en">' . esc_html($name_en) . '</span>';
            echo '<span class="po-text--fa">' . esc_html($name_fa_safe) . '</span>';
            echo '</a></h3>';

            // Description (both langs) — trimmed like excerpts with localized "read more"
            if (!empty($desc_en) || !empty($desc_fa_safe)) {
                echo '<div class="po-cat-desc entry-summary">';

                // Build localized "read more" anchors
                $more_en = ' <a class="po-readmore" href="' . esc_url($link) . '">' . esc_html__('Read more', 'persian-origins') . '</a>';
                // Persian text; you can also wrap it with a translation function if you add it to your .po file
                $more_fa = ' <a class="po-readmore po-readmore--fa" href="' . esc_url($link) . '">بیشتر بخوانید</a>';

                if (!empty($desc_en)) {
                    echo '<div class="po-text--en">' . self::trim_like_excerpt($desc_en, $more_en) . '</div>';
                }
                if (!empty($desc_fa_safe)) {
                    echo '<div class="po-text--fa">' . self::trim_like_excerpt($desc_fa_safe, $more_fa) . '</div>';
                }

                echo '</div>';
            }


            echo '</div></article>';
        }

        echo '</div>';
        return ob_get_clean();
    }
}

Persian_Origins_Categories_Shortcode::init();
