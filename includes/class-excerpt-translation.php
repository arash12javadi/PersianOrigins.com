<?php
if (!defined('ABSPATH')) exit;

class Persian_Origins_Excerpt_Translation
{

    public function register()
    {
        add_filter('get_the_excerpt', [$this, 'filter_excerpt'], 10, 2);
    }

    public function filter_excerpt($excerpt, $post)
    {
        // Get current language from plugin
        if (function_exists('persian_origins_plugin')) {
            $lang = persian_origins_plugin()->get_language_switcher()->get_current_language();
        } else {
            $lang = 'en';
        }

        // If Persian mode
        if ($lang === 'fa') {
            // 1. Check if a Persian excerpt is saved as meta
            $excerpt_fa = get_post_meta($post->ID, '_po_excerpt_fa', true);
            if (!empty($excerpt_fa)) {
                return $excerpt_fa;
            }

            // 2. Otherwise generate excerpt from Persian content
            $content = get_post_meta($post->ID, '_po_content_fa', true);
            if (empty($content)) {
                $content = $post->post_content; // fallback
            }

            // Strip tags and trim to ~55 words (like WP default)
            $text = wp_strip_all_tags($content);
            $words = preg_split("/\s+/", $text);
            if (count($words) > 55) {
                $words = array_slice($words, 0, 55);
                $text  = implode(" ", $words) . '…';
            }
            return $text;
        }

        // Default: return normal English excerpt
        return $excerpt;
    }
}
