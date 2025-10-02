<?php
//---------------------- class-menu-translation.php ----------------------

defined('ABSPATH') || exit;

// Add field for Persian title in menu item editor
add_action('wp_nav_menu_item_custom_fields', function ($item_id, $item, $depth, $args) {
    $title_fa = get_post_meta($item_id, '_po_menu_title_fa', true);
?>
    <p class="field-po-menu-title-fa description description-wide">
        <label for="po-menu-title-fa-<?php echo esc_attr($item_id); ?>">
            <?php esc_html_e('Persian Title', 'persian-origins'); ?><br>
            <input type="text" id="po-menu-title-fa-<?php echo esc_attr($item_id); ?>"
                name="po_menu_title_fa[<?php echo esc_attr($item_id); ?>]"
                value="<?php echo esc_attr($title_fa); ?>" />
        </label>
    </p>
<?php
}, 10, 4);

// Save Persian title meta
add_action('wp_update_nav_menu_item', function ($menu_id, $menu_item_db_id) {
    if (isset($_POST['po_menu_title_fa'][$menu_item_db_id])) {
        update_post_meta(
            $menu_item_db_id,
            '_po_menu_title_fa',
            sanitize_text_field($_POST['po_menu_title_fa'][$menu_item_db_id])
        );
    }
}, 10, 2);

// Replace title ONLY for nav menu items when Persian is active
add_filter('the_title', function ($title, $post_id) {
    if (function_exists('persian_origins_plugin') && is_nav_menu_item($post_id)) {
        $plugin = persian_origins_plugin();
        if (method_exists($plugin, 'get_language_switcher')) {
            $lang = $plugin->get_language_switcher()->get_current_language();
            if ($lang === 'fa') {
                $title_fa = get_post_meta($post_id, '_po_menu_title_fa', true);
                if (!empty($title_fa)) {
                    return $title_fa;
                }
            }
        }
    }
    return $title;
}, 20, 2);
