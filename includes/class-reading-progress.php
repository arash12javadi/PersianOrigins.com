<?php
/**
 * Reading progress tracking.
 *
 * @package PersianOrigins
 */

defined('ABSPATH') || exit;

class Persian_Origins_Reading_Progress {

    private $cookie_prefix = 'po_last_read_';

    public function register(): void {
        add_action('template_redirect', [$this, 'track_progress']);
        add_action('loop_start', [$this, 'maybe_render_category_continue_button']);
    }

    public function track_progress(): void {
        if (!is_singular('post') || wp_doing_ajax()) {
            return;
        }

        $post_id = get_queried_object_id();
        if (!$post_id) {
            return;
        }

        $categories = get_the_category($post_id);
        if (empty($categories)) {
            return;
        }

        foreach ($categories as $category) {
            if ($category instanceof \WP_Term) {
                $this->store_last_read((int) $category->term_id, (int) $post_id);
            }
        }
    }

    private function store_last_read(int $category_id, int $post_id): void {
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), $this->get_user_meta_key($category_id), $post_id);
            return;
        }

        $expires = time() + MONTH_IN_SECONDS;
        $cookie  = $this->cookie_prefix . $category_id;
        $path    = defined('COOKIEPATH') ? (string) COOKIEPATH : '/';
        $domain  = defined('COOKIE_DOMAIN') ? (string) COOKIE_DOMAIN : '';

        setcookie($cookie, (string) $post_id, $expires, $path, $domain, is_ssl(), true);
        $_COOKIE[$cookie] = (string) $post_id;
    }

    private function get_user_meta_key(int $category_id): string {
        return '_last_read_' . $category_id;
    }

    public function maybe_render_category_continue_button($query): void {
        if (!($query instanceof \WP_Query) || !$query->is_main_query() || !is_category() || wp_doing_ajax()) {
            return;
        }

        $category = get_queried_object();
        if (!($category instanceof \WP_Term)) {
            return;
        }

        $markup = $this->build_continue_button((int) $category->term_id);
        if ($markup) {
            echo '<div class="po-continue-reading">' . $markup . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }

    public function build_continue_button(int $category_id, array $atts = []): string {
        $active = $this->get_continue_button_markup($category_id, $atts);
        if ($active) {
            return $active;
        }

        $term = get_term($category_id, 'category');
        if ($term instanceof \WP_Term) {
            return $this->get_disabled_continue_button_markup($term, $atts);
        }

        return '';
    }

    public function get_continue_button_markup(int $category_id, array $atts = []): string {
        $defaults = [
            'class' => '',
        ];
        $atts = wp_parse_args($atts, $defaults);

        $post_id = $this->get_last_read_post_id($category_id);
        if (!$post_id) {
            return '';
        }

        $permalink = get_permalink($post_id);
        if (!$permalink) {
            return '';
        }

        $term = get_term($category_id, 'category');
        $label = esc_html__('Continue Reading', 'persian-origins');
        if ($term instanceof \WP_Term) {
            $label = sprintf(
                /* translators: %s: category name */
                esc_html__('Continue reading %s', 'persian-origins'),
                esc_html($term->name)
            );
        }

        $class = $this->sanitize_class_attribute('po-continue-reading__link ' . $atts['class']);

        return sprintf(
            '<a class="%1$s" href="%2$s">%3$s</a>',
            esc_attr($class),
            esc_url($permalink),
            $label
        );
    }

    public function get_disabled_continue_button_markup(\WP_Term $category, array $atts = []): string {
        $defaults = [
            'class' => '',
        ];
        $atts = wp_parse_args($atts, $defaults);

        $class = $this->sanitize_class_attribute('po-continue-reading__link is-disabled ' . $atts['class']);

        $label = sprintf(
            /* translators: %s: category name */
            esc_html__('Continue reading %s', 'persian-origins'),
            esc_html($category->name)
        );

        return sprintf(
            '<span class="%1$s" aria-disabled="true">%2$s</span>',
            esc_attr($class),
            $label
        );
    }

    private function get_last_read_post_id(int $category_id): int {
        if (is_user_logged_in()) {
            $saved = get_user_meta(get_current_user_id(), $this->get_user_meta_key($category_id), true);
            return $saved ? (int) $saved : 0;
        }

        $cookie = $this->cookie_prefix . $category_id;
        if (!empty($_COOKIE[$cookie])) {
            return (int) $_COOKIE[$cookie];
        }

        return 0;
    }

    public function get_script_data(): array {
        if (!is_singular('post')) {
            return [];
        }

        $post_id = get_queried_object_id();
        if (!$post_id) {
            return [];
        }

        $categories = get_the_category($post_id);
        if (empty($categories)) {
            return [];
        }

        $category_ids = [];

        foreach ($categories as $category) {
            if ($category instanceof \WP_Term) {
                $category_ids[] = (int) $category->term_id;
            }
        }

        if (empty($category_ids)) {
            return [];
        }

        return [
            'postId'     => (int) $post_id,
            'categories' => $category_ids,
            'maxAge'     => MONTH_IN_SECONDS,
        ];
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