<?php
if (!defined('ABSPATH')) exit;

class Persian_Origins_Category_Meta
{
    const TAXONOMY     = 'category';
    // Keep only FA + image
    const META_NAME_FA = 'po_name_fa';
    const META_DESC_FA = 'po_desc_fa';
    const META_IMG_ID  = 'po_image_id';

    public static function init()
    {
        add_action(self::TAXONOMY . '_add_form_fields',  [__CLASS__, 'add_fields']);
        add_action(self::TAXONOMY . '_edit_form_fields', [__CLASS__, 'edit_fields'], 10, 2);
        add_action('created_' . self::TAXONOMY, [__CLASS__, 'save_meta']);
        add_action('edited_' . self::TAXONOMY,  [__CLASS__, 'save_meta']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin']);
    }

    public static function enqueue_admin($hook)
    {
        if ($hook !== 'edit-tags.php' && $hook !== 'term.php') return;
        if (!isset($_GET['taxonomy']) || $_GET['taxonomy'] !== self::TAXONOMY) return;

        wp_enqueue_media();
        wp_add_inline_script('jquery-core', <<<JS
        jQuery(function($){
          $(document).on('click', '.po-cat-image-select', function(e){
            e.preventDefault();
            var frame = wp.media({ title: 'Select Category Image', multiple: false });
            frame.on('select', function(){
              var a = frame.state().get('selection').first().toJSON();
              $('#po_image_id').val(a.id);
              $('#po_image_preview').html('<img src="'+(a.sizes?.thumbnail?.url || a.url)+'" style="max-width:100px;height:auto;border:1px solid #ddd;border-radius:6px;" />');
            });
            frame.open();
          });
          $(document).on('click', '.po-cat-image-remove', function(e){
            e.preventDefault();
            $('#po_image_id').val('');
            $('#po_image_preview').empty();
          });
        });
        JS);
    }

    // Add screen (no EN fields)
    public static function add_fields()
    { ?>
        <div class="form-field">
            <label for="po_name_fa">نام (فارسی)</label>
            <input type="text" name="<?php echo esc_attr(self::META_NAME_FA); ?>" id="po_name_fa" value="" />
            <p class="description">عنوان فارسی دسته‌بندی (برای نمایش در حالت زبان فارسی).</p>
        </div>
        <div class="form-field">
            <label for="po_desc_fa">توضیحات (فارسی)</label>
            <textarea name="<?php echo esc_attr(self::META_DESC_FA); ?>" id="po_desc_fa" rows="4"></textarea>
            <p class="description">توضیحات فارسی دسته‌بندی.</p>
        </div>
        <div class="form-field">
            <label>Image</label>
            <div id="po_image_preview"></div>
            <input type="hidden" name="<?php echo esc_attr(self::META_IMG_ID); ?>" id="po_image_id" value="" />
            <p>
                <button class="button po-cat-image-select">Select Image</button>
                <button class="button po-cat-image-remove">Remove</button>
            </p>
        </div>
    <?php }

    // Edit screen (no EN fields)
    public static function edit_fields($term)
    {
        $name_fa = get_term_meta($term->term_id, self::META_NAME_FA, true);
        $desc_fa = get_term_meta($term->term_id, self::META_DESC_FA, true);
        $img_id  = (int) get_term_meta($term->term_id, self::META_IMG_ID, true);
        $thumb   = $img_id ? wp_get_attachment_image($img_id, 'thumbnail', false, ['style' => 'max-width:100px;height:auto;border:1px solid #ddd;border-radius:6px;']) : '';
    ?>
        <tr class="form-field">
            <th scope="row"><label for="po_name_fa">نام (فارسی)</label></th>
            <td>
                <input type="text" name="<?php echo esc_attr(self::META_NAME_FA); ?>" id="po_name_fa" value="<?php echo esc_attr($name_fa); ?>" class="regular-text" />
                <p class="description">برای زبان فارسی؛ انگلیسی را در فیلدهای پیش‌فرض بالا وارد کنید.</p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="po_desc_fa">توضیحات (فارسی)</label></th>
            <td>
                <textarea name="<?php echo esc_attr(self::META_DESC_FA); ?>" id="po_desc_fa" rows="4" class="large-text"><?php echo esc_textarea($desc_fa); ?></textarea>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label>Image</label></th>
            <td>
                <div id="po_image_preview"><?php echo $thumb ?: ''; ?></div>
                <input type="hidden" name="<?php echo esc_attr(self::META_IMG_ID); ?>" id="po_image_id" value="<?php echo esc_attr($img_id); ?>" />
                <p>
                    <button class="button po-cat-image-select">Select Image</button>
                    <button class="button po-cat-image-remove">Remove</button>
                </p>
            </td>
        </tr>
<?php
    }

    public static function save_meta($term_id)
    {
        // Only FA + image
        $fields = [
            self::META_NAME_FA => true,
            self::META_DESC_FA => true,
            self::META_IMG_ID  => true,
        ];

        foreach ($fields as $key => $_) {
            if (!isset($_POST[$key])) continue;
            $val = $_POST[$key];

            if ($key === self::META_IMG_ID) {
                $val = (int) $val;
            } elseif ($key === self::META_DESC_FA) {
                $val = wp_kses_post(wp_unslash($val));
            } else {
                $val = sanitize_text_field($val);
            }
            update_term_meta($term_id, $key, $val);
        }
    }
}

Persian_Origins_Category_Meta::init();


add_filter('get_the_archive_title', function ($title) {
    if (!is_category()) return $title;
    $term = get_queried_object();
    if (!$term || is_wp_error($term)) return $title;

    $name_en = $term->name;
    $name_fa = get_term_meta($term->term_id, Persian_Origins_Category_Meta::META_NAME_FA, true);
    if (!$name_fa) $name_fa = $name_en;

    return '<span class="po-text--en">' . esc_html($name_en) . '</span>'
        . '<span class="po-text--fa">' . esc_html($name_fa) . '</span>';
}, 20);

add_filter('get_the_archive_description', function ($desc) {
    if (!is_category()) return $desc;
    $term = get_queried_object();
    if (!$term || is_wp_error($term)) return $desc;

    $desc_en = term_description($term->term_id, $term->taxonomy);
    $desc_fa = get_term_meta($term->term_id, Persian_Origins_Category_Meta::META_DESC_FA, true);
    if (!$desc_fa) $desc_fa = $desc_en;

    return '<div class="po-text--en">' . wp_kses_post(wpautop($desc_en)) . '</div>'
        . '<div class="po-text--fa">' . wp_kses_post(wpautop($desc_fa)) . '</div>';
}, 20);
