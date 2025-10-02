<?php

/**
 * Language switcher logic.
 *
 * @package PersianOrigins
 */

defined('ABSPATH') || exit;

class Persian_Origins_Language_Switcher
{

    private $cookie_name = 'po_preferred_language';
    private $valid = ['en', 'fa'];
    private $rendered_auto = false;

    public function register(): void
    {
        add_filter('body_class', [$this, 'filter_body_class']);

        add_filter('gettext', [$this, 'translate_text'], 10, 3);

        // Show floating button in footer
        add_action('wp_footer', [$this, 'render_floating_switch'], 19);

        // Handle switching before template loads
        add_action('template_redirect', [$this, 'maybe_handle_language_switch']);
    }

    public function translate_text($translated, $text, $domain): string
    {
        $lang        = $this->get_current_language();
        $dictionary  = Persian_Origins_Translations::dictionary();

        if (isset($dictionary[$lang][$text])) {
            return $dictionary[$lang][$text];
        }

        return $translated;
    }


    public function render_floating_switch(): void
    {
        echo $this->get_switch_markup([
            'wrapper_class' => 'po-language-switch--floating',
        ]);
    }

    public function output_dir_inline_script(): void
    {
?>
        <script>
            (function() {
                try {
                    var m = document.documentElement;
                    var isFa = document.cookie.indexOf('po_preferred_language=fa') > -1 ||
                        (new URLSearchParams(location.search)).get('lang') === 'fa';
                    m.setAttribute('dir', isFa ? 'rtl' : 'ltr');
                } catch (e) {}
            })();
        </script>
<?php
    }

    public function filter_language_attributes($output, $doctype)
    {
        $dir = ($this->get_current_language() === 'fa') ? 'rtl' : 'ltr';
        // strip any existing dir attr then append ours
        $output = preg_replace('/\sdir=("|\')(rtl|ltr)\1/i', '', $output);
        return trim($output . ' dir="' . esc_attr($dir) . '"');
    }


    public function filter_body_class(array $classes): array
    {
        $lang = $this->get_current_language();
        $classes[] = 'po-lang-' . $lang;
        $classes[] = ($lang === 'fa') ? 'po-dir-rtl' : 'po-dir-ltr';
        return $classes;
    }


    public function render_auto_switch(): void
    {
        if ($this->rendered_auto || is_admin() || is_feed() || is_embed() || wp_doing_ajax()) {
            return;
        }

        if (!apply_filters('persian_origins_show_auto_language_switch', true)) {
            return;
        }

        $markup = $this->get_switch_markup([
            'wrapper_class' => 'po-language-switch--auto',
        ]);

        if (!$markup) {
            return;
        }

        echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $this->rendered_auto = true;
    }

    public function get_switch_markup(array $args = []): string
    {
        $defaults = [
            'wrapper_class' => '',
            'link_class'    => '',
        ];
        $args = wp_parse_args($args, $defaults);

        $wrapper_class = $this->sanitize_class_attribute('po-language-switch ' . $args['wrapper_class']);
        $link_class    = $this->sanitize_class_attribute('po-language-switch__link ' . $args['link_class']);

        $current_lang = $this->determine_context_language();
        $target_lang  = ($current_lang === 'fa') ? 'en' : 'fa';

        // If singular, try to fetch a translation target
        $target_post_id = 0;
        if (is_singular()) {
            $target_post_id = $this->get_translation_post_id(get_queried_object_id());
        }

        // Always guarantee a working redirect URL
        $fallback_url = $target_post_id ? get_permalink($target_post_id) : $this->get_current_url();
        if (empty($fallback_url)) {
            $fallback_url = home_url('/');
        }

        $switch_url = $this->build_switch_url($target_lang, $target_post_id, $fallback_url);

        if (empty($switch_url)) {
            return ''; // If nothing to link to, bail early
        }

        // Labels
        $current_label = ($current_lang === 'fa')
            ? __('Persian', 'persian-origins')
            : __('English', 'persian-origins');

        $target_label = ($target_lang === 'fa')
            ? __('Switch to Persian', 'persian-origins')
            : __('Switch to English', 'persian-origins');

        // Markup
        $markup = sprintf(
            '<div class="%1$s" data-current-lang="%2$s">
            <span class="po-language-switch__current">%3$s</span>
            <a class="%4$s" href="%5$s">%6$s</a>
        </div>',
            esc_attr($wrapper_class),
            esc_attr($current_lang),
            esc_html(sprintf(__('Current: %s', 'persian-origins'), $current_label)),
            esc_attr($link_class),
            esc_url($switch_url),
            esc_html($target_label)
        );

        return (string) apply_filters(
            'persian_origins_language_switch_markup',
            $markup,
            $current_lang,
            $target_lang,
            $target_post_id
        );
    }


    private function determine_context_language(): string
    {
        return $this->get_current_language();
    }

    private function build_switch_url(string $target_lang, int $target_post_id, string $redirect_url): string
    {
        $nonce = wp_create_nonce('po_switch_language');

        $args = [
            'po_switch_language' => $target_lang,
            '_po_lang_nonce'     => $nonce,
            'po_redirect'        => rawurlencode(base64_encode($redirect_url ?: home_url('/'))),
        ];

        if ($target_post_id) {
            $args['po_target'] = $target_post_id;
        }

        $current_url = $this->get_current_url();
        $current_url = remove_query_arg(array_keys($args), $current_url);

        return add_query_arg($args, $current_url);
    }

    public function maybe_handle_language_switch(): void
    {
        if (empty($_GET['po_switch_language'])) {
            return;
        }

        $language = sanitize_key(wp_unslash($_GET['po_switch_language']));
        if (!in_array($language, $this->valid, true)) {
            $language = 'en';
        }

        // TEMP: disable nonce check for testing
        // $nonce = isset($_GET['_po_lang_nonce']) ? sanitize_text_field(wp_unslash($_GET['_po_lang_nonce'])) : '';
        // if (!wp_verify_nonce($nonce, 'po_switch_language')) {
        //     wp_die(__('Invalid language switch request.', 'persian-origins'));
        // }

        $this->persist_language_preference($language);

        $redirect = home_url('/');
        if (!empty($_GET['po_redirect'])) {
            $raw     = rawurldecode(sanitize_text_field(wp_unslash($_GET['po_redirect'])));
            $decoded = base64_decode($raw, true);
            if ($decoded) {
                $redirect = esc_url_raw($decoded);
            }
        }

        wp_safe_redirect($redirect);
        exit;
    }
    private function persist_language_preference(string $language): void
    {
        if (!in_array($language, $this->valid, true)) {
            $language = 'en'; // fallback
        }

        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), '_preferred_language', $language);
        }

        $expiry        = time() + YEAR_IN_SECONDS;
        $cookie_path   = defined('COOKIEPATH') ? (string) COOKIEPATH : '/';
        $cookie_domain = defined('COOKIE_DOMAIN') ? (string) COOKIE_DOMAIN : '';

        setcookie($this->cookie_name, $language, $expiry, $cookie_path, $cookie_domain, is_ssl(), true);
        $_COOKIE[$this->cookie_name] = $language; // important: update runtime copy
    }

    public function get_current_language(): string
    {
        // Logged-in user preference first
        if (is_user_logged_in()) {
            $user_lang = get_user_meta(get_current_user_id(), '_preferred_language', true);
            if ($user_lang && in_array($user_lang, $this->valid, true)) {
                return $user_lang;
            }
        }

        // Query param
        if (!empty($_GET['lang']) && in_array($_GET['lang'], $this->valid, true)) {
            return sanitize_text_field($_GET['lang']);
        }

        // Cookie
        if (!empty($_COOKIE[$this->cookie_name]) && in_array($_COOKIE[$this->cookie_name], $this->valid, true)) {
            return sanitize_text_field($_COOKIE[$this->cookie_name]);
        }

        return 'en';
    }


    public function get_translation_post_id(int $post_id): int
    {
        $direct = (int) get_post_meta($post_id, '_translation_of', true);
        if ($direct && get_post_status($direct)) {
            return $direct;
        }

        $reverse = get_posts(
            [
                'post_type'      => get_post_type($post_id),
                'post_status'    => 'publish',
                'meta_key'       => '_translation_of',
                'meta_value'     => $post_id,
                'fields'         => 'ids',
                'posts_per_page' => 1,
                'no_found_rows'  => true,
            ]
        );

        if (!empty($reverse)) {
            return (int) $reverse[0];
        }

        return 0;
    }

    private function get_current_url(): string
    {
        global $wp;

        $base = home_url('/');
        if (isset($wp->request)) {
            $base = home_url(add_query_arg([], $wp->request));
        }

        $query = isset($_SERVER['QUERY_STRING']) ? sanitize_text_field(wp_unslash($_SERVER['QUERY_STRING'])) : '';
        if ($query) {
            $base .= '?' . $query;
        }

        return esc_url_raw($base);
    }

    public function get_post_language(int $post_id): string
    {
        $stored = get_post_meta($post_id, '_post_language', true);
        if ($stored) {
            $stored = sanitize_key($stored);
            if (in_array($stored, ['en', 'fa'], true)) {
                return $stored;
            }
        }

        $meta = get_post_meta($post_id, '_translation_of', true);
        return !empty($meta) ? 'fa' : 'en';
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
