<?php
/**
 * Reading progress tracking.
 *
 * @package PersianOrigins
 */

defined('ABSPATH') || exit;

class Persian_Origins_Reading_Progress {

    private $cookie_prefix = 'po_last_read_';
    private $read_cookie_prefix = 'po_read_posts_';

    /**
     * Cached read posts lookups per category.
     *
     * @var array<int, array>
     */
    private $read_cache = [];

    /**
     * Cached progress calculations per category.
     *
     * @var array<int, array>
     */
    private $progress_cache = [];

    public function register(): void {
        add_action('template_redirect', [$this, 'track_progress']);
        add_action('loop_start', [$this, 'maybe_render_category_continue_button']);
        add_filter('the_content', [$this, 'prepend_progress_bar'], 5);
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
                $category_id = (int) $category->term_id;
                $this->store_last_read($category_id, (int) $post_id);
                $this->mark_post_as_read($category_id, (int) $post_id);
            }
        }
    }

    private function store_last_read(int $category_id, int $post_id): void {
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), $this->get_user_meta_key($category_id), $post_id);
            return;
        }

        $expires = time() + MONTH_IN_SECONDS;
        $cookie  = $this->cookie_prefix + $category_id;
        $path    = defined('COOKIEPATH') ? (string) COOKIEPATH : '/';
        $domain  = defined('COOKIE_DOMAIN') ? (string) COOKIE_DOMAIN : '';

        setcookie($cookie, (string) $post_id, $expires, $path, $domain, is_ssl(), true);
        $_COOKIE[$cookie] = (string) $post_id;
    }

    private function mark_post_as_read(int $category_id, int $post_id): void {
        if (is_user_logged_in()) {
            $key        = $this->get_user_read_posts_key($category_id);
            $read_posts = get_user_meta(get_current_user_id(), $key, true);
            if (!is_array($read_posts)) {
                $read_posts = [];
            }

            if (!in_array($post_id, $read_posts, true)) {
                $read_posts[] = $post_id;
                update_user_meta(get_current_user_id(), $key, $read_posts);
            }
        } else {
            $read_posts = $this->get_guest_read_posts($category_id);
            if (!in_array($post_id, $read_posts, true)) {
                $read_posts[] = $post_id;
                $this->store_guest_read_posts($category_id, $read_posts);
            }
        }

        unset($this->read_cache[$category_id], $this->progress_cache[$category_id]);
    }

    private function store_guest_read_posts(int $category_id, array $post_ids): void {
        $post_ids = array_values(array_unique(array_map('absint', $post_ids)));
        $post_ids = array_filter($post_ids);
        if (empty($post_ids)) {
            return;
        }

        $cookie_name = $this->read_cookie_prefix . $category_id;
        $max_entries = apply_filters('persian_origins_guest_read_posts_limit', 200, $category_id);
        if ($max_entries > 0 && count($post_ids) > $max_entries) {
            $post_ids = array_slice($post_ids, -1 * $max_entries);
        }

        $value   = implode(',', $post_ids);
        $expires = time() + MONTH_IN_SECONDS;
        $path    = defined('COOKIEPATH') ? (string) COOKIEPATH : '/';
        $domain  = defined('COOKIE_DOMAIN') ? (string) COOKIE_DOMAIN : '';

        setcookie($cookie_name, $value, $expires, $path, $domain, is_ssl(), true);
        $_COOKIE[$cookie_name] = $value;
    }

    private function get_user_meta_key(int $category_id): string {
        return '_last_read_' . $category_id;
    }

    private function get_user_read_posts_key(int $category_id): string {
        return '_read_posts_' . $category_id;
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
            echo '<div class="po-continue-reading">' . $markup . '</div>';
        }
    }

    public function prepend_progress_bar($content) {
        if (!is_singular('post') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $categories = get_the_category();
        if (empty($categories)) {
            return $content;
        }

        $primary = apply_filters('persian_origins_primary_category', $categories[0], $categories);
        if (!($primary instanceof \WP_Term)) {
            $primary = $categories[0];
        }

        $category_id = (int) $primary->term_id;
        $markup      = $this->build_progress_bar_markup($category_id);

        if (!$markup) {
            return $content;
        }

        return $markup . $content;
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

        $term  = get_term($category_id, 'category');
        $label = esc_html__('Continue Reading', 'persian-origins');
        if ($term instanceof \WP_Term) {
            $label = sprintf(
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
        $totals       = [];
        $read_counts  = [];

        foreach ($categories as $category) {
            if ($category instanceof \WP_Term) {
                $category_id     = (int) $category->term_id;
                $category_ids[]  = $category_id;
                $totals[$category_id]      = $this->get_category_total_posts($category_id);
                $progress                  = $this->get_category_progress($category_id);
                $read_counts[$category_id] = $progress['read_count'];
            }
        }

        if (empty($category_ids)) {
            return [];
        }

        return [
            'postId'     => (int) $post_id,
            'categories' => $category_ids,
            'maxAge'     => MONTH_IN_SECONDS,
            'totals'     => $totals,
            'readCounts' => $read_counts,
        ];
    }

    private function get_category_progress(int $category_id): array {
        if (isset($this->progress_cache[$category_id])) {
            return $this->progress_cache[$category_id];
        }

        $total = $this->get_category_total_posts($category_id);
        $read  = $this->get_read_posts_for_current_user($category_id);

        $count      = count($read);
        $percentage = 0;
        if ($total > 0 && $count > 0) {
            $percentage = (int) round(($count / $total) * 100);
            $percentage = min(100, max(0, $percentage));
        }

        $progress = [
            'total'      => $total,
            'read_count' => min($count, $total),
            'percentage' => $percentage,
        ];

        $this->progress_cache[$category_id] = $progress;

        return $progress;
    }

    private function build_progress_bar_markup(int $category_id): string {
        $progress = $this->get_category_progress($category_id);
        if (empty($progress['total'])) {
            return '';
        }

        $term = get_term($category_id, 'category');
        if ($term instanceof \WP_Term) {
            $label = sprintf(
                esc_html__('Reading progress for %s', 'persian-origins'),
                esc_html($term->name)
            );
        } else {
            $label = esc_html__('Reading progress', 'persian-origins');
        }
        $percentage      = (int) $progress['percentage'];
        $percentage_text = number_format_i18n($percentage) . '%';
        $read_count      = (int) $progress['read_count'];
        $total           = (int) $progress['total'];

        $markup  = '<div class="po-progress-bar" data-category="' . esc_attr($category_id) . '" data-total="' . esc_attr($total) . '" data-read="' . esc_attr($read_count) . '">';
        $markup .= '<div class="po-progress-bar__meta">';
        $markup .= '<span class="po-progress-bar__label">' . $label . '</span>';
        $markup .= '<span class="po-progress-bar__percent">' . esc_html($percentage_text) . '</span>';
        $markup .= '</div>';
        $markup .= '<div class="po-progress-bar__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr($percentage) . '">';
        $markup .= '<div class="po-progress-bar__fill" style="width:' . esc_attr($percentage) . '%"></div>';
        $markup .= '</div>';
        $markup .= '<div class="po-progress-bar__counts" aria-live="polite">';
        $markup .= '<span class="po-progress-bar__numbers">' . esc_html(sprintf('%d / %d', $read_count, $total)) . '</span>';
        $markup .= '</div>';
        $markup .= '</div>';

        return (string) apply_filters('persian_origins_progress_bar_markup', $markup, $progress, $category_id);
    }

    private function get_category_total_posts(int $category_id): int {
        $term = get_term($category_id, 'category');
        if ($term instanceof \WP_Term) {
            return (int) $term->count;
        }

        return 0;
    }

    private function get_read_posts_for_current_user(int $category_id): array {
        if (isset($this->read_cache[$category_id])) {
            return $this->read_cache[$category_id];
        }

        if (is_user_logged_in()) {
            $raw = get_user_meta(get_current_user_id(), $this->get_user_read_posts_key($category_id), true);
        } else {
            $raw = $this->get_guest_read_posts($category_id);
        }

        if (!is_array($raw)) {
            $raw = array_filter(array_map('absint', (array) $raw));
        }

        $post_ids = array_values(array_unique(array_map('absint', (array) $raw)));
        if (empty($post_ids)) {
            $this->read_cache[$category_id] = [];
            return [];
        }

        $existing = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'post__in'       => $post_ids,
            'orderby'        => 'post__in',
            'no_found_rows'  => true,
            'tax_query'      => [
                [
                    'taxonomy' => 'category',
                    'terms'    => $category_id,
                    'field'    => 'term_id',
                ],
            ],
        ]);

        $existing = array_values(array_unique(array_map('absint', $existing)));
        $this->read_cache[$category_id] = $existing;

        return $existing;
    }

    private function get_guest_read_posts(int $category_id): array {
        $cookie = $this->read_cookie_prefix . $category_id;
        if (empty($_COOKIE[$cookie])) {
            return [];
        }

        $raw = sanitize_text_field(wp_unslash($_COOKIE[$cookie]));
        if ('' === $raw) {
            return [];
        }

        $parts = array_map('absint', explode(',', $raw));
        $parts = array_filter($parts);

        return array_values(array_unique($parts));
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
