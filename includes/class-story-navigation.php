<?php

/**
 * Story navigation component.
 *
 * @package PersianOrigins
 */

defined('ABSPATH') || exit;

class Persian_Origins_Story_Navigation
{
    private $language_switcher;

    public function __construct(Persian_Origins_Language_Switcher $language_switcher)
    {
        $this->language_switcher = $language_switcher;
    }


    public function register(): void
    {
        add_filter('the_content', [$this, 'append_story_navigation']);
    }

    public function append_story_navigation($content)
    {
        if (!is_singular('post') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        static $already_output = false;
        if ($already_output) {
            return $content;
        }
        $already_output = true;

        $navigation = $this->build_navigation_markup();
        if (!$navigation) {
            return $content;
        }

        return $content . $navigation;
    }

    private function build_navigation_markup(): string
    {
        $previous_post = get_adjacent_post(true, '', true);
        $next_post     = get_adjacent_post(true, '', false);

        $has_previous = $previous_post instanceof \WP_Post;
        $has_next     = $next_post instanceof \WP_Post;

        $prev_url = $has_previous ? get_permalink($previous_post) : '';
        $next_url = $has_next ? get_permalink($next_post) : '';

        $prev_classes = 'po-story-nav__button';
        $next_classes = 'po-story-nav__button';

        // Detect current language using the injected dependency
        $lang_class = ($this->language_switcher->get_current_language() === 'fa')
            ? 'po-story-nav--fa'
            : 'po-story-nav--en';

        if (!$has_previous) {
            $prev_classes .= ' is-disabled';
        }

        if (!$has_next) {
            $next_classes .= ' is-disabled';
        }


        ob_start();
?>
        <nav class="po-story-nav <?php echo esc_attr($lang_class); ?>" aria-label="<?php echo esc_attr__('Story navigation', 'persian-origins'); ?>">
            <?php if ($has_previous) : ?>
                <a class="<?php echo esc_attr($prev_classes); ?>" href="<?php echo esc_url($prev_url); ?>" rel="prev">
                    <?php esc_html_e('Previous', 'persian-origins'); ?>
                </a>
            <?php else : ?>
                <span class="<?php echo esc_attr($prev_classes); ?>" aria-disabled="true">
                    <?php esc_html_e('Previous', 'persian-origins'); ?>
                </span>
            <?php endif; ?>

            <?php if ($has_next) : ?>
                <a class="<?php echo esc_attr($next_classes); ?>" href="<?php echo esc_url($next_url); ?>" rel="next">
                    <?php esc_html_e('Next', 'persian-origins'); ?>
                </a>
            <?php else : ?>
                <span class="<?php echo esc_attr($next_classes); ?>" aria-disabled="true">
                    <?php esc_html_e('Next', 'persian-origins'); ?>
                </span>
            <?php endif; ?>
        </nav>
<?php

        return (string) ob_get_clean();
    }
}
