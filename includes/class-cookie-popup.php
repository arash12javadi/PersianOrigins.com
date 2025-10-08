<?php
if (!defined('ABSPATH')) exit;

/** Detect current language (prefer plugin switcher, fallback to WP locale) */
if (!function_exists('po_get_current_lang')) {
    function po_get_current_lang(): string
    {
        if (function_exists('persian_origins_plugin')) {
            $plugin = persian_origins_plugin();
            if ($plugin && method_exists($plugin, 'get_language_switcher')) {
                $ls = $plugin->get_language_switcher();
                if ($ls && method_exists($ls, 'get_current_language')) {
                    $lang = $ls->get_current_language(); // 'fa' or 'en'
                    if ($lang) return $lang === 'fa' ? 'fa' : 'en';
                }
            }
        }
        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
        return (strpos($locale, 'fa') === 0) ? 'fa' : 'en';
    }
}

class Persian_Origins_Cookie_Popup
{
    // THEME groups (leave them alone)
    const THEME_GROUP_TEXT = 'ajdwp_theme_options_group_text';
    const THEME_GROUP_LINK = 'ajdwp_theme_options_group_link';

    // THEME option names (EN / legacy)
    const OPT_TEXT_EN = 'cookie_popup_text';
    const OPT_LINK_EN = 'cookie_popup_link';
    const OPT_ENABLE  = 'cookies_popup_enabled';

    // PLUGIN FA option names
    const OPT_TEXT_FA = 'cookie_popup_text_fa';
    const OPT_LINK_FA = 'cookie_popup_link_fa';

    // PLUGIN FA groups (separate to avoid clobbering)
    const FA_GROUP_TEXT = 'po_cookie_text_group';
    const FA_GROUP_LINK = 'po_cookie_link_group';

    // Theme settings page slug
    const THEME_SETTINGS_PAGE = 'AJDWP_Theme_Options';

    public function register(): void
    {
        // Register FA options in their OWN groups
        add_action('admin_init', [$this, 'register_fa_settings']);

        // Inject FA forms onto the theme page (no theme edits)
        add_action('admin_footer', [$this, 'inject_fa_forms_into_theme_settings']);

        // Only override on FRONT-END to avoid masking admin inputs
        add_action('init', function () {
            if (!is_admin()) {
                add_filter('pre_option_' . self::OPT_TEXT_EN, [$this, 'maybe_override_text_for_fa']);
                add_filter('pre_option_' . self::OPT_LINK_EN, [$this, 'maybe_override_link_for_fa']);
            }
        });

        // Render stays in the theme; we only swap option values via filters above.
    }

    /** Register FA fields so options.php will save them without touching EN */
    public function register_fa_settings(): void
    {
        register_setting(self::FA_GROUP_TEXT, self::OPT_TEXT_FA);
        register_setting(self::FA_GROUP_LINK, self::OPT_LINK_FA);
    }

    /**
     * Inject Persian forms under the existing EN block, but bind them to FA groups
     * so saving FA won't clear EN, and saving EN won't clear FA.
     */
    public function inject_fa_forms_into_theme_settings(): void
    {
        // Only on that theme settings page
        $is_theme_settings = isset($_GET['page']) && $_GET['page'] === self::THEME_SETTINGS_PAGE;
        if (!$is_theme_settings) return;

        $text_fa = get_option(self::OPT_TEXT_FA, '');
        $link_fa = get_option(self::OPT_LINK_FA, '');

        ob_start(); ?>
        <div id="po-cookie-fa-block">
            <hr>
            <h3 style="margin:10px 0;"><?php echo esc_html__('تنظیمات پیام کوکی (فارسی)', 'persian-origins'); ?></h3>

            <!-- Persian (FA) text - uses FA group -->
            <form method="post" action="options.php" style="margin-top:12px">
                <?php settings_fields(self::FA_GROUP_TEXT); ?>
                <label for="po_cookie_popup_text_fa"><?php echo esc_html__('متن پیام کوکی (FA):', 'persian-origins'); ?></label>
                <br>
                <textarea id="po_cookie_popup_text_fa"
                    name="<?php echo esc_attr(self::OPT_TEXT_FA); ?>"
                    rows="5"
                    cols="50"
                    class="cookie_popup_text"><?php echo esc_textarea($text_fa); ?></textarea>
                <br><br>
                <input type="submit" class="button-primary" value="<?php echo esc_attr__('ذخیره متن (FA)', 'persian-origins'); ?>">
            </form>

            <!-- Persian (FA) link - uses FA group -->
            <form method="post" action="options.php" style="margin-top:12px">
                <?php settings_fields(self::FA_GROUP_LINK); ?>
                <label for="po_cookie_popup_link_fa"><?php echo esc_html__('لینک «اطلاعات بیشتر» (FA):', 'persian-origins'); ?></label>
                <br>
                <input type="text"
                    id="po_cookie_popup_link_fa"
                    name="<?php echo esc_attr(self::OPT_LINK_FA); ?>"
                    value="<?php echo esc_attr($link_fa); ?>"
                    class="regular-text">
                <br><br>
                <input type="submit" class="button-primary" value="<?php echo esc_attr__('ذخیره لینک (FA)', 'persian-origins'); ?>">
            </form>

            <p style="opacity:.7; margin-top:10px">
                <?php echo esc_html__('نکته: وقتی زبان سایت/پلاگین روی فارسی باشد، متن و لینک فارسی در پاپ‌آپ استفاده می‌شود.', 'persian-origins'); ?>
            </p>
        </div>

        <script>
            (function() {
                // append FA block right after the existing cookie settings container if present
                var target = document.getElementById('cookie-popup-settings');
                var fa = document.getElementById('po-cookie-fa-block');
                if (target && fa) target.insertAdjacentElement('afterend', fa);
            })();
        </script>
<?php
        echo ob_get_clean();
    }

    /** Front-end: override EN text with FA when Persian is active */
    public function maybe_override_text_for_fa($pre)
    {
        if (po_get_current_lang() === 'fa') {
            $fa = get_option(self::OPT_TEXT_FA, '');
            if ($fa !== '') return $fa;
        }
        return false; // let WP fetch the actual EN option
    }

    /** Front-end: override EN link with FA when Persian is active */
    public function maybe_override_link_for_fa($pre)
    {
        if (po_get_current_lang() === 'fa') {
            $fa = get_option(self::OPT_LINK_FA, '');
            if ($fa !== '') return $fa;
        }
        return false; // let WP fetch the actual EN option
    }
}
