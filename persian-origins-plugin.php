<?php

/**
 * Plugin Name: Persian Origins Plugin
 * Plugin URI: https://persianorigins.com
 * Description: Provides bilingual language switching, story navigation, reading progress tracking, and frontend preferences for Persian Origins.
 * Version: 1.0.0
 * Author: Persian Origins
 * Author URI: https://persianorigins.com
 * Text Domain: persian-origins
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Persian_Origins_Plugin
{

    private static $instance;

    const VERSION = '1.0.0';

    private $language_switcher;
    private $site_settings;
    private $story_navigation;
    private $reading_progress;
    private $shortcodes;
    private $content;

    private function __construct()
    {
        $this->define_constants();
        $this->load_dependencies();
        $this->init_components();
        $this->register_hooks();
    }

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function define_constants(): void
    {
        if (!defined('PERSIAN_ORIGINS_PLUGIN_VERSION')) {
            define('PERSIAN_ORIGINS_PLUGIN_VERSION', self::VERSION);
        }

        if (!defined('PERSIAN_ORIGINS_PLUGIN_FILE')) {
            define('PERSIAN_ORIGINS_PLUGIN_FILE', __FILE__);
        }

        if (!defined('PERSIAN_ORIGINS_PLUGIN_DIR')) {
            define('PERSIAN_ORIGINS_PLUGIN_DIR', plugin_dir_path(__FILE__));
        }

        if (!defined('PERSIAN_ORIGINS_PLUGIN_URL')) {
            define('PERSIAN_ORIGINS_PLUGIN_URL', plugin_dir_url(__FILE__));
        }
    }

    private function load_dependencies(): void
    {
        require_once PERSIAN_ORIGINS_PLUGIN_DIR . 'includes/class-language-switcher.php';
        require_once PERSIAN_ORIGINS_PLUGIN_DIR . 'includes/class-story-navigation.php';
        require_once PERSIAN_ORIGINS_PLUGIN_DIR . 'includes/class-reading-progress.php';
        require_once PERSIAN_ORIGINS_PLUGIN_DIR . 'includes/class-site-settings.php';
        require_once PERSIAN_ORIGINS_PLUGIN_DIR . 'includes/class-shortcodes.php';
        require_once PERSIAN_ORIGINS_PLUGIN_DIR . 'includes/class-po-translations.php';
        require_once PERSIAN_ORIGINS_PLUGIN_DIR . 'includes/class-content.php';
        require_once PERSIAN_ORIGINS_PLUGIN_DIR . 'includes/class-menu-translation.php';
    }

    private function init_components(): void
    {
        $this->language_switcher = new Persian_Origins_Language_Switcher();
        $this->story_navigation = new Persian_Origins_Story_Navigation($this->language_switcher);
        $this->reading_progress  = new Persian_Origins_Reading_Progress();
        $this->site_settings     = new Persian_Origins_Site_Settings($this->language_switcher);
        $this->shortcodes        = new Persian_Origins_Shortcodes($this->language_switcher, $this->reading_progress);
        $this->content           = new Persian_Origins_Content($this->language_switcher);

        $this->language_switcher->register();
        $this->story_navigation->register();
        $this->reading_progress->register();
        $this->site_settings->register();
        $this->shortcodes->register();
        $this->content->register();
    }

    private function register_hooks(): void
    {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain('persian-origins', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function enqueue_assets(): void
    {
        wp_enqueue_style(
            'persian-origins-frontend',
            PERSIAN_ORIGINS_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            self::VERSION
        );

        wp_enqueue_script(
            'persian-origins-frontend',
            PERSIAN_ORIGINS_PLUGIN_URL . 'assets/js/frontend.js',
            [],
            self::VERSION,
            true
        );

        $localize_data = [
            'readingProgress' => $this->reading_progress->get_script_data(),
            'siteSettings'    => $this->site_settings->get_script_data(),
        ];

        wp_localize_script('persian-origins-frontend', 'PersianOriginsData', $localize_data);
    }

    public function get_language_switcher(): Persian_Origins_Language_Switcher
    {
        return $this->language_switcher;
    }
}

function persian_origins_plugin(): Persian_Origins_Plugin
{
    return Persian_Origins_Plugin::instance();
}


persian_origins_plugin();
