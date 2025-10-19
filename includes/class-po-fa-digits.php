<?php
if (!defined('ABSPATH')) exit;

/**
 * Convert visible digits to Persian when FA is active.
 * - Skips tags, <script>/<style>/<code>/<pre>/… contents
 * - Masks HTML entities (e.g., &#1234;, &#xF099;) so they don't break
 * - Uses letters-only placeholders to avoid digit conversion collisions
 */
final class Persian_Origins_FA_Digits
{
    private $language_switcher;
    private $use_page_buffer = true;

    public function __construct($language_switcher = null)
    {
        $this->language_switcher = $language_switcher;
    }

    public function register(): void
    {
        add_action('init', [$this, 'attach_filters']);

        $this->use_page_buffer = apply_filters('po_fa_digits_use_page_buffer', $this->use_page_buffer);
        if ($this->use_page_buffer) {
            add_action('template_redirect', [$this, 'start_buffer'], 12);
        }

        add_filter('po_use_fa_digits', [$this, 'filter_use_fa_digits'], 10, 2);
        add_filter('paginate_links', [$this, 'ensure_ascii_digits_in_href'], 20);
        add_filter('get_pagenum_link', [$this, 'normalize_url_digits'], 99, 2);
        add_filter('request', [$this, 'normalize_request_paged'], 0);
        add_filter('redirect_canonical', [$this, 'normalize_redirect_canonical'], 10, 2);
        add_action('plugins_loaded', [$this, 'maybe_redirect_fa_paged_in_uri'], 0);
    }

    public function attach_filters(): void
    {
        add_filter('the_title',                       [$this, 'convert_plain'], 12);
        add_filter('pre_get_document_title',          [$this, 'convert_plain'], 12);
        add_filter('document_title_parts',            function ($parts) {
            return is_array($parts) ? array_map([$this, 'convert_plain'], $parts) : $parts;
        }, 12);

        add_filter('the_content',                     [$this, 'convert_html_text_only'], 12);
        add_filter('the_excerpt',                     [$this, 'convert_html_text_only'], 12);
        add_filter('comment_text',                    [$this, 'convert_html_text_only'], 12);
        add_filter('widget_text',                     [$this, 'convert_html_text_only'], 12);

        add_filter('date_i18n',                       [$this, 'convert_plain'], 12);
        add_filter('the_time',                        [$this, 'convert_plain'], 12);
        add_filter('get_the_time',                    [$this, 'convert_plain'], 12);
        add_filter('the_date',                        [$this, 'convert_plain'], 12);
        add_filter('get_the_date',                    [$this, 'convert_plain'], 12);
        add_filter('human_time_diff',                 [$this, 'convert_plain'], 12);

        add_filter('number_format_i18n',              [$this, 'convert_plain'], 12);
        add_filter('wp_sprintf',                      [$this, 'convert_plain'], 12);
        add_filter('wp_sprintf_l',                    [$this, 'convert_plain'], 12);

        add_filter('the_author',                      [$this, 'convert_plain'], 12);
        add_filter('get_the_author_display_name',     [$this, 'convert_plain'], 12);

        add_filter('wp_get_attachment_image_attributes', function ($attr) {
            if (!$this->is_fa_active() || !is_array($attr)) return $attr;
            if (isset($attr['alt']))   $attr['alt']   = $this->convert_plain($attr['alt']);
            if (isset($attr['title'])) $attr['title'] = $this->convert_plain($attr['title']);
            return $attr;
        }, 12);

        add_filter('paginate_links',                  [$this, 'convert_html_text_only'], 12);
        // add_filter('navigation_markup_template',      [$this, 'convert_html_text_only'], 12);
        add_filter('get_archives_link',               [$this, 'convert_html_text_only'], 12);
        add_filter('wp_list_categories',              [$this, 'convert_html_text_only'], 12);
        add_filter('wp_list_pages',                   [$this, 'convert_html_text_only'], 12);

        add_filter('comments_number',                 [$this, 'convert_plain'], 12);
        add_filter('get_comments_number_text',        [$this, 'convert_plain'], 12);

        add_filter('nav_menu_item_title',             [$this, 'convert_plain'], 12, 4);
        add_filter('widget_title',                    [$this, 'convert_plain'], 12);

        // WooCommerce (if present)
        add_filter('woocommerce_get_price_html',               [$this, 'convert_html_text_only'], 12);
        add_filter('woocommerce_cart_item_price',              [$this, 'convert_html_text_only'], 12);
        add_filter('woocommerce_cart_item_subtotal',           [$this, 'convert_html_text_only'], 12);
        add_filter('woocommerce_cart_subtotal',                [$this, 'convert_html_text_only'], 12);
        add_filter('woocommerce_cart_totals_order_total_html', [$this, 'convert_html_text_only'], 12);
        add_filter('woocommerce_get_formatted_order_total',    [$this, 'convert_plain'], 12);
    }

    public function normalize_url_digits($url, $pagenum = null)
    {
        if (!$this->is_fa_active() || !is_string($url) || $url === '') return $url;

        // First fix percent-encoded Persian/Arabic-Indic digits
        $url = $this->percent_encoded_fa_digits_to_ascii($url);
        // Then fix any raw Unicode digits (just in case)
        $url = $this->fa_to_latin_digits($url);

        return $url;
    }


    public function ensure_ascii_digits_in_href($html)
    {
        if (!$this->is_fa_active() || $html === '' || $html === null) return $html;

        if (is_array($html)) {
            foreach ($html as $i => $frag) {
                if (is_string($frag) && $frag !== '') {
                    $html[$i] = $this->restore_ascii_in_url_attributes($frag);
                }
            }
            return $html;
        }

        if (is_string($html)) {
            return $this->restore_ascii_in_url_attributes($html);
        }

        return $html;
    }


    public function start_buffer(): void
    {
        if (!$this->is_fa_active()) return;
        if (is_admin() || is_feed() || (defined('REST_REQUEST') && REST_REQUEST) || wp_doing_ajax()) return;
        ob_start([$this, 'buffer_callback']);
    }

    public function buffer_callback(string $html): string
    {
        return $this->convert_html_text_only($html);
    }

    private function is_fa_active(): bool
    {
        $wp_locale = function_exists('get_locale') ? get_locale() : 'en_US';
        return (bool) apply_filters('po_use_fa_digits', false, $wp_locale);
    }

    public function filter_use_fa_digits(bool $is_fa, string $wp_locale): bool
    {
        // 1) Explicit switcher
        if ($this->language_switcher && method_exists($this->language_switcher, 'get_current_lang')) {
            $lang = (string) $this->language_switcher->get_current_lang();
            if ($lang === 'fa') return true;
            if ($lang === 'en') return false;
        }

        // 2) Fallbacks your switcher probably already sets
        if (!empty($_COOKIE['po_preferred_language']) && $_COOKIE['po_preferred_language'] === 'fa') return true;
        if (!empty($_GET['lang']) && $_GET['lang'] === 'fa') return true;

        // 3) RTL fallback (only if you want RTL pages to always show Persian digits)
        if (function_exists('is_rtl') && is_rtl()) return true;

        // 4) Locale fallback
        if (!$is_fa) {
            $is_fa = (stripos($wp_locale, 'fa') !== false);
        }
        return $is_fa;
    }

    public function convert_plain($s)
    {
        if (!$this->is_fa_active() || !is_string($s) || $s === '') return $s;
        return $this->latin_to_fa_digits($s);
    }


    public function convert_html_text_only($html)
    {
        // If FA mode isn't active or empty input, bail.
        if (!$this->is_fa_active() || $html === '' || $html === null) {
            return $html;
        }

        // If paginate_links() (or others) passed an ARRAY of fragments, convert each one.
        if (is_array($html)) {
            foreach ($html as $i => $frag) {
                if (is_string($frag) && $frag !== '') {
                    $html[$i] = $this->convert_html_text_only($frag); // recurse on string path
                }
            }
            return $html;
        }

        // Non-strings: leave untouched.
        if (!is_string($html)) {
            return $html;
        }

        // If it contains sprintf placeholders like %1$s, don't touch it.
        if (preg_match('/%\d+\$[bcdeEfFgGosuxX]/', $html)) {
            return $html;
        }

        // ── (0) Mask HTML entities so things like &#xF099; or &#169; don't get mangled
        $entity_placeholders = [];
        $e = 0;
        $html = preg_replace_callback('/&#(?:x[0-9A-Fa-f]+|\d+);/', function ($m) use (&$entity_placeholders, &$e) {
            $key = $this->makePlaceholder('POENT', $e++); // letters-only
            $entity_placeholders[$key] = $m[0];
            return $key;
        }, $html);

        // ── (1) Extract blocks to keep intact (script/style/code/pre/kbd/samp/var)
        $block_placeholders = [];
        $b = 0;
        foreach (['script', 'style', 'code', 'pre', 'kbd', 'samp', 'var'] as $tag) {
            $html = preg_replace_callback(
                "#<{$tag}\\b[^>]*>.*?</{$tag}>#is",
                function ($m) use (&$block_placeholders, &$b) {
                    $key = $this->makePlaceholder('POBLOCK', $b++); // letters-only
                    $block_placeholders[$key] = $m[0];
                    return $key;
                },
                $html
            );
        }

        // ── (2) Protect HTML tags so we only touch text nodes
        $TAG_KEY = $this->makePlaceholder('POTAG', 0); // stable key with letters only
        $tags = [];
        $html = preg_replace_callback(
            '#</?[^>]+>#',
            function ($m) use (&$tags, $TAG_KEY) {
                $tags[] = $m[0];
                return $TAG_KEY;
            },
            $html
        );

        // ── (3) Convert digits in text chunks
        $parts = explode($TAG_KEY, $html);
        foreach ($parts as $k => $chunk) {
            $parts[$k] = $this->latin_to_fa_digits($chunk);
        }

        // Re-join with original tags
        $result = '';
        $t = 0;
        foreach ($parts as $chunk) {
            $result .= $chunk;
            if ($t < count($tags)) {
                $result .= $tags[$t++];
            }
        }

        // ── (4) Restore masked entities
        if ($entity_placeholders) {
            $result = strtr($result, $entity_placeholders);
        }

        // ── (5) Restore protected blocks
        if ($block_placeholders) {
            $result = strtr($result, $block_placeholders);
        }

        // ── (6) VERY IMPORTANT: keep ASCII digits inside URL attributes (handles raw and %DB%B2 forms)
        $result = $this->restore_ascii_in_url_attributes($result);

        return $result;
    }

    public function normalize_request_paged(array $qv): array
    {
        // Normalize "paged" (used by archives), and "page" (used on static pages)
        foreach (['paged', 'page'] as $key) {
            if (isset($qv[$key]) && $qv[$key] !== '') {
                $v = (string) $qv[$key];

                // 1) Convert percent-encoded Persian/Arabic-Indic digits → ASCII
                $v = $this->percent_encoded_fa_digits_to_ascii($v);

                // 2) Convert raw Unicode Persian/Arabic-Indic digits → ASCII
                $v = $this->fa_to_latin_digits($v);

                // 3) Keep digits only (defensive), cast to int
                $v = preg_replace('/\D+/', '', $v);
                $qv[$key] = $v === '' ? 0 : (int) $v;
            }
        }
        return $qv;
    }

    public function normalize_redirect_canonical($redirect_url, $requested_url)
    {
        if (!$redirect_url || !$this->is_fa_active()) return $redirect_url;

        $fixed = $this->percent_encoded_fa_digits_to_ascii($redirect_url);
        $fixed = $this->fa_to_latin_digits($fixed);

        return $fixed;
    }

    public function maybe_redirect_fa_paged_in_uri(): void
    {
        if (!$this->is_fa_active()) return;

        // Only act on frontend
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) return;

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if ($uri === '' || strpos($uri, '%') === false) return;

        // Only do work if path contains /page/%XX%YY/ kind of segment
        if (!preg_match('~/page/(?:%[A-Fa-f0-9]{2}){2}(?:/|$)~', $uri)) return;

        $fixed = $this->percent_encoded_fa_digits_to_ascii($uri);
        $fixed = $this->fa_to_latin_digits($fixed);

        if ($fixed !== $uri) {
            // Build absolute target safely
            $scheme = (is_ssl() ? 'https://' : 'http://');
            $host   = $_SERVER['HTTP_HOST'] ?? '';
            $target = $scheme . $host . $fixed;

            // 301 to the ASCII version so rewrites match
            wp_safe_redirect($target, 301);
            exit;
        }
    }


    private function latin_to_fa_digits(string $s): string
    {
        static $map = [
            '0' => '۰',
            '1' => '۱',
            '2' => '۲',
            '3' => '۳',
            '4' => '۴',
            '5' => '۵',
            '6' => '۶',
            '7' => '۷',
            '8' => '۸',
            '9' => '۹',
        ];
        return strtr($s, $map);
    }

    // Add this helper inside Persian_Origins_FA_Digits
    private function fa_to_latin_digits(string $s): string
    {
        static $map = [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
        ];
        return strtr($s, $map);
    }


    /**
     * Force ASCII digits in URL-carrying attributes (href/src/action/formaction).
     * Call this AFTER text-node digit conversion.
     */
    private function restore_ascii_in_url_attributes(string $html): string
    {
        return preg_replace_callback(
            '/\b(?:href|src|action|formaction)\s*=\s*(["\'])([^"\']*)\1/i',
            function ($m) {
                $val = $m[2];
                $val = $this->percent_encoded_fa_digits_to_ascii($val);
                $val = $this->fa_to_latin_digits($val);
                return str_replace($m[2], $val, $m[0]);
            },
            $html
        );
    }


    // Turn %DB%B0..%DB%B9 (Persian) and %D9%B0..%D9%B9 (Arabic-Indic) into ASCII 0..9
    private function percent_encoded_fa_digits_to_ascii(string $s): string
    {
        return preg_replace_callback('/%([A-Fa-f0-9]{2})%([A-Fa-f0-9]{2})/', function ($m) {
            $b1 = hexdec($m[1]);
            $b2 = hexdec($m[2]);

            // Persian digits U+06F0..U+06F9 => UTF-8: DB B0..B9
            if ($b1 === 0xDB && $b2 >= 0xB0 && $b2 <= 0xB9) {
                return chr(($b2 - 0xB0) + ord('0')); // ASCII digit
            }

            // Arabic-Indic digits U+0660..U+0669 => UTF-8: D9 B0..B9
            if ($b1 === 0xD9 && $b2 >= 0xB0 && $b2 <= 0xB9) {
                return chr(($b2 - 0xB0) + ord('0'));
            }

            return $m[0]; // leave other %XX%YY pairs alone
        }, $s);
    }


    /**
     * Make a letters-only placeholder like %%POBLOCK_AJ%%, never containing digits.
     * We encode an integer index into base-26 letters.
     */
    private function makePlaceholder(string $prefix, int $i): string
    {
        return '%%' . $prefix . '_' . $this->alphaId($i) . '%%';
    }

    /** Convert 0,1,2… → A,B,C,…, Z, AA, AB… (letters only) */
    private function alphaId(int $n): string
    {
        $n = max(0, $n);
        $s = '';
        do {
            $s = chr(65 + ($n % 26)) . $s;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);
        return $s;
    }
}
