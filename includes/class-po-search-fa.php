<?php
// FA-aware search via hooks (no template edits, no synonyms).
// - AND across words (core-like)
// - Searches core (title/excerpt/content) + FA metas (_po_*)
// - If query has Persian letters, show FA title/excerpt (page & SearchWP Live Ajax)

if (!defined('ABSPATH')) exit;

/** ───────── Helpers (guarded) ───────── **/

if (!function_exists('po_is_fa_text')) {
    function po_is_fa_text(string $s): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $s);
    }
}

if (!function_exists('po_normalize_fa')) {
    // Arabic → Persian letters; strip ZWNJ/Tatweel/diacritics; convert all FA/AR digits → ASCII
    function po_normalize_fa(string $s): string
    {
        $s = strtr($s, [
            'ي' => 'ی',
            'ى' => 'ی',
            'ك' => 'ک',
            "‌" => '',
            'ـ' => '',
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
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
        ]);
        return preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $s) ?? $s;
    }
}

if (!function_exists('po_like')) {
    function po_like(string $s): string
    {
        global $wpdb;
        return '%' . $wpdb->esc_like($s) . '%';
    }
}

if (!function_exists('po_token_variants')) {
    // Return minimal robust variants for one token
    function po_token_variants(string $w): array
    {
        $norm  = po_normalize_fa($w);
        $arish = strtr($w,   ['ی' => 'ي', 'ک' => 'ك']);
        $arish_norm = strtr($norm, ['ی' => 'ي', 'ک' => 'ك']);
        // Also Arabic-Indic digits version of normalized token
        $to_ar_digits = ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩'];
        $ar_digits = strtr($norm, $to_ar_digits);

        $candidates = [$w, $norm, $arish, $arish_norm, $ar_digits];
        $seen = [];
        $out = [];
        foreach ($candidates as $c) {
            if ($c === '' || isset($seen[$c])) continue;
            $seen[$c] = true;
            $out[] = $c;
        }
        return $out;
    }
}

if (!function_exists('po_query_token_sets')) {
    // Split into words (AND across), produce variants per word (OR within)
    function po_query_token_sets(string $term, int $max_tokens = 6, int $max_variants = 5): array
    {
        $term  = trim(wp_strip_all_tags($term));
        if ($term === '') return [];
        $parts = preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [$term];
        $parts = array_slice($parts, 0, $max_tokens);

        $sets = [];
        foreach ($parts as $w) {
            $v = array_slice(po_token_variants($w), 0, $max_variants);
            if ($v) $sets[] = $v;
        }
        return $sets;
    }
}

/** ───────── Search short-circuit ───────── **/

add_filter('posts_pre_query', function ($return, WP_Query $q) {
    // Only main search or SearchWP Live AJAX
    $is_live_ajax =
        (function_exists('doing_action') &&
            (doing_action('wp_ajax_searchwp_live_search') || doing_action('wp_ajax_nopriv_searchwp_live_search')))
        || (isset($_REQUEST['action']) && $_REQUEST['action'] === 'searchwp_live_search');

    $is_front_search = $q->is_search() && $q->is_main_query();
    if (!$is_front_search && !$is_live_ajax) return $return;
    if (is_admin() && !wp_doing_ajax())     return $return;

    // Term (?s= or swpquery)
    $term = (string) $q->get('s');
    if ($term === '' && isset($_REQUEST['swpquery'])) {
        $term = (string) wp_unslash($_REQUEST['swpquery']);
    }
    $term = trim(wp_strip_all_tags($term));
    if ($term === '') return $return;

    $is_fa_query = po_is_fa_text($term);
    $token_sets  = po_query_token_sets($term);
    if (empty($token_sets)) return $return;

    global $wpdb;

    // Constraints
    $post_types = $q->get('post_type');
    if (empty($post_types))         $post_types = ['post', 'page'];
    elseif (is_string($post_types)) $post_types = [$post_types];

    $post_status = $q->get('post_status');
    if (empty($post_status))         $post_status = ['publish'];
    elseif (is_string($post_status)) $post_status = [$post_status];

    $in_types = implode(',', array_fill(0, count($post_types), '%s'));
    $in_stats = implode(',', array_fill(0, count($post_status), '%s'));

    // Meta keys we care about
    $meta_keys = ['_po_title_fa', '_po_content_fa', '_po_title_fa_norm', '_po_content_fa_norm'];
    $in_meta   = "'" . implode("','", array_map('esc_sql', $meta_keys)) . "'";

    // Build AND over tokens; for each token we OR across variants & fields.
    // Use EXISTS for meta to avoid row explosion.
    $and_blocks = [];
    $params = [];

    foreach ($token_sets as $variants) {
        $ors = [];

        foreach ($variants as $v) {
            $like = po_like($v);
            $ors[] = 'p.post_title   LIKE %s';
            $ors[] = 'p.post_excerpt LIKE %s';
            $ors[] = 'p.post_content LIKE %s';
            array_push($params, $like, $like, $like);
        }

        // meta EXISTS with OR of variants
        $meta_or = [];
        foreach ($variants as $v) {
            $meta_or[] = 'pm.meta_value LIKE %s';
            $params[]  = po_like($v);
        }
        $ors[] = "EXISTS (
            SELECT 1 FROM {$wpdb->postmeta} pm
            WHERE pm.post_id = p.ID
              AND pm.meta_key IN ($in_meta)
              AND (" . implode(' OR ', $meta_or) . ")
        )";

        $and_blocks[] = '(' . implode(' OR ', $ors) . ')';
    }

    $and_sql = implode(' AND ', $and_blocks);

    // Optional: simple relevance by title hit first (kept stable by date desc)
    $sql = "
        SELECT DISTINCT p.ID
        FROM {$wpdb->posts} p
        WHERE p.post_type IN ($in_types)
          AND p.post_status IN ($in_stats)
          AND ( $and_sql )
        ORDER BY p.post_date DESC
    ";

    $prepared = $wpdb->prepare($sql, array_merge($post_types, $post_status, $params));
    $ids = $wpdb->get_col($prepared);

    if (empty($ids)) return $return;

    // Pagination
    $per_page = (int) ($q->get('posts_per_page') ?: get_option('posts_per_page') ?: 10);
    if ($per_page <= 0) $per_page = 10;
    $paged  = max(1, (int) ($q->get('paged') ?: $q->get('page') ?: 1));
    $total  = count($ids);
    $slice  = array_slice($ids, ($paged - 1) * $per_page, $per_page);

    // FA output swap (filterable)
    $is_fa_output = $is_fa_query && apply_filters('po_is_fa', true);

    $posts = [];
    foreach ($slice as $id) {
        $p = get_post($id);
        if (!$p) continue;

        if ($is_fa_output) {
            $t = get_post_meta($id, '_po_title_fa', true);
            if ($t !== '') $p->post_title = $t;

            $c = get_post_meta($id, '_po_content_fa', true);
            if ($c !== '') $p->post_excerpt = wp_strip_all_tags(wp_trim_words($c, 30));
        }
        $posts[] = $p;
    }

    // Totals (also set properties some themes inspect)
    $q->found_posts   = $total;
    $q->max_num_pages = (int) ceil($total / $per_page);
    $q->posts         = $posts;
    $q->post_count    = count($posts);

    return $posts; // short-circuit
}, 99999, 2);

// Make sure typical constraints exist
add_action('pre_get_posts', function ($q) {
    if ($q->is_search() && $q->is_main_query()) {
        if (!$q->get('post_type'))   $q->set('post_type',  ['post', 'page']);
        if (!$q->get('post_status')) $q->set('post_status', ['publish']);
    }
}, 5);

// Keep normalized copy of FA metas for faster matching
add_action('save_post', function ($post_id) {
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;
    $t = (string) get_post_meta($post_id, '_po_title_fa',   true);
    $c = (string) get_post_meta($post_id, '_po_content_fa', true);
    update_post_meta($post_id, '_po_title_fa_norm',   po_normalize_fa($t));
    update_post_meta($post_id, '_po_content_fa_norm', po_normalize_fa($c));
}, 20);
