<?php
/**
 * Shortcodes for the plugin.
 *
 * @package PersianOrigins
 */

defined('ABSPATH') || exit;

class Persian_Origins_Shortcodes {

    private $language_switcher;
    private $reading_progress;

    public function __construct(Persian_Origins_Language_Switcher $language_switcher, Persian_Origins_Reading_Progress $reading_progress) {
        $this->language_switcher = $language_switcher;
        $this->reading_progress  = $reading_progress;
    }

    public function register(): void {
        add_shortcode('language_switch', [$this, 'render_language_switch']);
        add_shortcode('continue_reading', [$this, 'render_continue_reading']);
    }

    public function render_language_switch($atts = []): string {
        $atts = shortcode_atts(
            [
                'class'      => '',
                'link_class' => '',
            ],
            $atts,
            'language_switch'
        );

        return $this->language_switcher->get_switch_markup([
            'wrapper_class' => $this->sanitize_class_attribute($atts['class']),
            'link_class'    => $this->sanitize_class_attribute($atts['link_class']),
        ]);
    }

    public function render_continue_reading($atts = []): string {
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

    private function sanitize_class_attribute(string $class): string {
        $classes = preg_split('/\s+/', trim($class));
        if (!$classes) {
            return '';
        }

        $sanitized = array_map('sanitize_html_class', $classes);
        $sanitized = array_filter($sanitized);

        return implode(' ', $sanitized);
    }
}