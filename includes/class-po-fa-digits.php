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
        add_filter('navigation_markup_template',      [$this, 'convert_html_text_only'], 12);
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
        if ($this->language_switcher && method_exists($this->language_switcher, 'get_current_lang')) {
            $lang = (string) $this->language_switcher->get_current_lang();
            if ($lang) return ($lang === 'fa');
        }
        if (!$is_fa) {
            $is_fa = (stripos($wp_locale, 'fa_') === 0 || $wp_locale === 'fa' || stripos($wp_locale, 'fa') !== false);
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
        if (!$this->is_fa_active() || !is_string($html) || $html === '') return $html;

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

        return $result;
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
