<?php

/**
 * Dual-language content (EN/FA) for posts, pages, and categories.
 *
 * Stores Persian (fa) title/content in post meta and category term meta,
 * and swaps output on the frontend when the active language is Persian.
 */

defined('ABSPATH') || exit;

class Persian_Origins_Content
{

    /** @var Persian_Origins_Language_Switcher */
    private $language_switcher;

    /** Post meta keys (Persian fields) */
    const META_TITLE_FA   = '_po_title_fa';
    const META_CONTENT_FA = '_po_content_fa';
    const META_EXCERPT_FA = '_po_excerpt_fa'; // optional, used if provided

    /** Term meta keys for categories (Persian fields) */
    const TERM_NAME_FA = '_po_term_name_fa';
    const TERM_DESC_FA = '_po_term_desc_fa';

    /** Post types to support */
    private $post_types = ['post', 'page'];

    public function __construct(Persian_Origins_Language_Switcher $language_switcher)
    {
        $this->language_switcher = $language_switcher;
    }

    public function register(): void
    {
        // Post meta registration (REST-safe, sanitization)
        add_action('init', [$this, 'register_post_meta']);

        // Meta box UI
        add_action('add_meta_boxes', [$this, 'add_translation_metabox']);
        add_action('save_post', [$this, 'save_translation_metabox'], 10, 2);

        // Frontend title/content/excerpt swap
        add_filter('the_title', [$this, 'filter_the_title'], 10, 2);
        add_filter('the_content', [$this, 'filter_the_content'], 1);
        add_filter('get_the_excerpt', [$this, 'filter_the_excerpt'], 10, 2);

        // Also fix the <title> tag for singular screens
        add_filter('document_title_parts', [$this, 'filter_document_title_parts']);

        // Categories: fields in admin + save
        add_action('category_add_form_fields', [$this, 'render_category_add_fields']);
        add_action('category_edit_form_fields', [$this, 'render_category_edit_fields']);
        add_action('created_category', [$this, 'save_category_meta']);
        add_action('edited_category', [$this, 'save_category_meta']);

        // Categories: swap the shown name/description on the frontend
        add_filter('single_cat_title', [$this, 'filter_single_cat_title']);
        add_filter('category_description', [$this, 'filter_category_description']);

        // (Optional) make wp_list_categories() output FA names on frontend
        add_filter('list_cats', [$this, 'filter_list_cats'], 10, 2);
    }

    /* ---------------------------
     * Post meta (registration)
     * --------------------------- */
    public function register_post_meta(): void
    {
        $args_title = [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'single'            => true,
            'show_in_rest'      => true,
            'auth_callback'     => [$this, 'can_edit_post_meta'],
        ];
        $args_content = [
            'type'              => 'string',
            'sanitize_callback' => [$this, 'sanitize_post_content'],
            'single'            => true,
            'show_in_rest'      => true,
            'auth_callback'     => [$this, 'can_edit_post_meta'],
        ];

        foreach ($this->post_types as $pt) {
            register_post_meta($pt, self::META_TITLE_FA, $args_title);
            register_post_meta($pt, self::META_CONTENT_FA, $args_content);
            register_post_meta($pt, self::META_EXCERPT_FA, $args_title);
        }
    }

    public function can_edit_post_meta($allowed, $meta_key, $post_id, $user_id, $cap, $caps): bool
    {
        return current_user_can('edit_post', $post_id);
    }

    public function sanitize_post_content($value)
    {
        return wp_kses_post($value);
    }

    /* ---------------------------
     * Meta box UI (posts/pages)
     * --------------------------- */
    public function add_translation_metabox(): void
    {
        foreach ($this->post_types as $pt) {
            add_meta_box(
                'po_translation_box',
                __('Persian Translation (FA)', 'persian-origins'),
                [$this, 'render_translation_metabox'],
                $pt,
                'normal',
                'high'
            );
        }
    }

    public function render_translation_metabox(\WP_Post $post): void
    {
        $title_fa   = get_post_meta($post->ID, self::META_TITLE_FA, true);
        $content_fa = get_post_meta($post->ID, self::META_CONTENT_FA, true);
        $excerpt_fa = get_post_meta($post->ID, self::META_EXCERPT_FA, true);

        wp_nonce_field('po_save_translation_' . $post->ID, 'po_translation_nonce');
?>
        <p>
            <label for="po_title_fa"><strong><?php esc_html_e('Persian Title', 'persian-origins'); ?></strong></label><br>
            <input type="text" id="po_title_fa" name="po_title_fa" value="<?php echo esc_attr($title_fa); ?>" style="width:100%;">
        </p>
        <p>
            <label for="po_content_fa"><strong><?php esc_html_e('Persian Content', 'persian-origins'); ?></strong></label>
            <?php
            // Use WP editor for a better UX (works in classic editor; in block editor it’s just meta storage)
            wp_editor(
                $content_fa,
                'po_content_fa',
                [
                    'textarea_name' => 'po_content_fa',
                    'textarea_rows' => 8,
                    'media_buttons' => true,
                ]
            );
            ?>
        </p>
        <p>
            <label for="po_excerpt_fa"><strong><?php esc_html_e('Persian Excerpt (optional)', 'persian-origins'); ?></strong></label><br>
            <textarea id="po_excerpt_fa" name="po_excerpt_fa" rows="3" style="width:100%;"><?php echo esc_textarea($excerpt_fa); ?></textarea>
        </p>
        <p class="description">
            <?php esc_html_e('English content stays in the default Title/Content fields. Persian versions are stored here and shown on the frontend when language is Persian.', 'persian-origins'); ?>
        </p>
    <?php
    }

    public function save_translation_metabox(int $post_id, \WP_Post $post): void
    {
        // Basic safety: autosave/revision/nonces/caps
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if (!isset($_POST['po_translation_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['po_translation_nonce'])), 'po_save_translation_' . $post_id)) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // Save fields
        if (isset($_POST['po_title_fa'])) {
            update_post_meta($post_id, self::META_TITLE_FA, sanitize_text_field(wp_unslash($_POST['po_title_fa'])));
        }
        if (isset($_POST['po_content_fa'])) {
            update_post_meta($post_id, self::META_CONTENT_FA, wp_kses_post(wp_unslash($_POST['po_content_fa'])));
        }
        if (isset($_POST['po_excerpt_fa'])) {
            update_post_meta($post_id, self::META_EXCERPT_FA, sanitize_text_field(wp_unslash($_POST['po_excerpt_fa'])));
        }
    }

    /* ---------------------------
     * Frontend swapping (posts)
     * --------------------------- */
    private function is_fa(): bool
    {
        return $this->language_switcher->get_current_language() === 'fa';
    }

    public function filter_the_title($title, $post_id)
    {
        if (!is_admin() && $this->is_fa() && $post_id) {
            $title_fa = get_post_meta($post_id, self::META_TITLE_FA, true);
            if (!empty($title_fa)) {
                return $title_fa;
            }
        }
        return $title;
    }

    public function filter_the_content($content)
    {
        if (!is_admin() && $this->is_fa() && is_singular()) {
            $post_id    = get_the_ID();
            $content_fa = $post_id ? get_post_meta($post_id, self::META_CONTENT_FA, true) : '';
            if (!empty($content_fa)) {
                // Important: return raw FA content through the normal content filters (shortcodes, embeds, etc.)
                // To avoid recursion, run minimal filters manually:
                remove_filter('the_content', [$this, 'filter_the_content'], 1);
                $processed = apply_filters('the_content', $content_fa);
                add_filter('the_content', [$this, 'filter_the_content'], 1);
                return $processed;
            }
        }
        return $content;
    }

    public function filter_the_excerpt($excerpt, $post)
    {
        if (!is_admin() && $this->is_fa() && $post instanceof \WP_Post) {
            $fa = get_post_meta($post->ID, self::META_EXCERPT_FA, true);
            if (!empty($fa)) {
                return $fa;
            }
        }
        return $excerpt;
    }

    public function filter_document_title_parts(array $parts): array
    {
        if (!is_admin() && $this->is_fa() && is_singular()) {
            $post_id = get_queried_object_id();
            if ($post_id) {
                $title_fa = get_post_meta($post_id, self::META_TITLE_FA, true);
                if (!empty($title_fa)) {
                    $parts['title'] = $title_fa;
                }
            }
        }
        return $parts;
    }

    /* ---------------------------
     * Categories (term meta)
     * --------------------------- */
    public function render_category_add_fields(): void
    {
    ?>
        <div class="form-field">
            <label for="po_term_name_fa"><?php esc_html_e('Persian Name (FA)', 'persian-origins'); ?></label>
            <input type="text" name="po_term_name_fa" id="po_term_name_fa" value="">
        </div>
        <div class="form-field">
            <label for="po_term_desc_fa"><?php esc_html_e('Persian Description (FA)', 'persian-origins'); ?></label>
            <textarea name="po_term_desc_fa" id="po_term_desc_fa" rows="4"></textarea>
        </div>
    <?php
    }

    public function render_category_edit_fields(\WP_Term $term): void
    {
        $name_fa = get_term_meta($term->term_id, self::TERM_NAME_FA, true);
        $desc_fa = get_term_meta($term->term_id, self::TERM_DESC_FA, true);
    ?>
        <tr class="form-field">
            <th scope="row"><label for="po_term_name_fa"><?php esc_html_e('Persian Name (FA)', 'persian-origins'); ?></label></th>
            <td><input type="text" name="po_term_name_fa" id="po_term_name_fa" value="<?php echo esc_attr($name_fa); ?>" class="regular-text"></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="po_term_desc_fa"><?php esc_html_e('Persian Description (FA)', 'persian-origins'); ?></label></th>
            <td><textarea name="po_term_desc_fa" id="po_term_desc_fa" rows="4" class="large-text"><?php echo esc_textarea($desc_fa); ?></textarea></td>
        </tr>
<?php
    }

    public function save_category_meta(int $term_id): void
    {
        // Capability check
        if (!current_user_can('manage_categories')) return;

        if (isset($_POST['po_term_name_fa'])) {
            update_term_meta($term_id, self::TERM_NAME_FA, sanitize_text_field(wp_unslash($_POST['po_term_name_fa'])));
        }
        if (isset($_POST['po_term_desc_fa'])) {
            update_term_meta($term_id, self::TERM_DESC_FA, wp_kses_post(wp_unslash($_POST['po_term_desc_fa'])));
        }
    }

    public function filter_single_cat_title($title)
    {
        if (!is_admin() && $this->is_fa() && is_category()) {
            $term_id = get_queried_object_id();
            $name_fa = $term_id ? get_term_meta($term_id, self::TERM_NAME_FA, true) : '';
            if (!empty($name_fa)) {
                return $name_fa;
            }
        }
        return $title;
    }

    public function filter_category_description($desc)
    {
        if (!is_admin() && $this->is_fa() && is_category()) {
            $term_id = get_queried_object_id();
            $desc_fa = $term_id ? get_term_meta($term_id, self::TERM_DESC_FA, true) : '';
            if (!empty($desc_fa)) {
                return $desc_fa;
            }
        }
        return $desc;
    }

    /**
     * Make wp_list_categories() output the Persian name in frontend lists.
     * The filter receives the category name string, and the category object.
     */
    public function filter_list_cats($cat_name, $cat_object)
    {
        if (!is_admin() && $this->is_fa() && $cat_object instanceof \WP_Term) {
            $name_fa = get_term_meta($cat_object->term_id, self::TERM_NAME_FA, true);
            if (!empty($name_fa)) {
                return $name_fa;
            }
        }
        return $cat_name;
    }
}
