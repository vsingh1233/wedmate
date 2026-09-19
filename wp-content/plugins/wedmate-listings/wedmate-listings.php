<?php
/**
 * Plugin Name: Wedmate Listings
 * Description: Unified wedding directory, city routes and story relationships.
 * Version: 1.0.0
 */
defined('ABSPATH') || exit;

function wml_register() {
    register_post_type('listings', [
        'labels' => ['name' => 'Listings', 'singular_name' => 'Listing', 'add_new_item' => 'Add Listing', 'edit_item' => 'Edit Listing'],
        'public' => true, 'show_in_rest' => true, 'menu_icon' => 'dashicons-store',
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
        'has_archive' => false, 'rewrite' => false,
        'taxonomies' => ['location', 'vendor_category', 'vendor_tag', 'venue_type'],
    ]);
    foreach (['location', 'vendor_category', 'vendor_tag'] as $taxonomy) {
        if (taxonomy_exists($taxonomy)) register_taxonomy_for_object_type($taxonomy, 'listings');
    }
    register_taxonomy('venue_type', 'listings', [
        'labels' => ['name' => 'Venue Types', 'singular_name' => 'Venue Type'],
        'public' => false, 'show_ui' => false, 'show_admin_column' => false,
        'show_in_rest' => false, 'hierarchical' => true, 'rewrite' => false,
    ]);
}
add_action('init', 'wml_register', 20);
add_action('after_setup_theme', function() {
    remove_action('init','wedmate_vendor_location_rewrite');
    remove_action('init','wedmate_venue_location_rewrite');
}, 99);

function wml_is_venue($id) { return has_term('wedding-venues', 'vendor_category', $id); }
function wml_listing_city($id) {
    $locations = get_the_terms($id, 'location');
    if (!$locations || is_wp_error($locations)) return null;
    // Keep the canonical city stable when a listing has several locations.
    usort($locations, fn($a, $b) => $a->term_id <=> $b->term_id);
    $cities = [];
    foreach (wml_cities() as $city) $cities[$city->term_id] = $city;
    foreach ($locations as $location) {
        foreach (array_merge([$location->term_id], get_ancestors($location->term_id, 'location', 'taxonomy')) as $term_id) {
            if (isset($cities[$term_id])) return $cities[$term_id];
        }
    }
    return null;
}
function wml_cities() {
    $terms = get_terms(['taxonomy' => 'location', 'hide_empty' => false]);
    $reserved = ['vendors','vendor','venue','venues','wedding-venues','stories','category','tag','author','page','feed','search','wp-json','wp-admin','all'];
    return is_wp_error($terms) ? [] : array_values(array_filter($terms, function($term) use ($reserved) {
        return !$term->parent && !in_array($term->slug, $reserved, true) && !get_page_by_path($term->slug, OBJECT, 'page');
    }));
}
function wml_routes() {
    add_rewrite_rule('^vendors/?$', 'index.php?wm_directory=1', 'top');
    add_rewrite_rule('^vendors/page/([0-9]+)/?$', 'index.php?wm_directory=1&paged=$matches[1]', 'top');
    add_rewrite_rule('^vendors/all/([^/]+)(?:/page/([0-9]+))?/?$', 'index.php?wm_directory=1&wm_category=$matches[1]&paged=$matches[2]', 'top');
    $cities = implode('|', array_map(fn($t) => preg_quote($t->slug, '#'), wml_cities()));
    if ($cities) {
        add_rewrite_rule('^('.$cities.')(?:/page/([0-9]+))?/?$', 'index.php?wm_directory=1&wm_city=$matches[1]&paged=$matches[2]', 'top');
        add_rewrite_rule('^('.$cities.')/([^/]+)(?:/page/([0-9]+))?/?$', 'index.php?wm_directory=1&wm_city=$matches[1]&wm_category=$matches[2]&paged=$matches[3]', 'top');
        // Register the city pagination rule last so it takes precedence over category matching.
        add_rewrite_rule('^('.$cities.')/page/([0-9]+)/?$', 'index.php?wm_directory=1&wm_city=$matches[1]&paged=$matches[2]', 'top');
    }
    add_rewrite_rule('^(vendor|wedding-venues)/([^/]+)/?$', 'index.php?post_type=listings&name=$matches[2]', 'top');
    add_rewrite_rule('^(vendor|wedding-venues)/([^/]+)/([^/]+)/?$', 'index.php?post_type=listings&name=$matches[3]', 'top');
    add_rewrite_rule('^venue/([^/]+)/?$', 'index.php?post_type=listings&name=$matches[1]', 'top');
    add_rewrite_rule('^vendors/(?!all/|page/)([^/]+)/([^/]+)/?$', 'index.php?vendor_category=$matches[1]&location_filter=$matches[2]', 'top');
    add_rewrite_rule('^venues/([^/]+)/?$', 'index.php?location=$matches[1]', 'top');
}
add_action('init', 'wml_routes', 30);
add_filter('query_vars', function($vars) { return array_merge($vars, ['wm_directory','wm_city','wm_category']); });
function wml_schedule_rewrite($term = 0, $tt = 0, $taxonomy = '') {
    if ($taxonomy === 'location') update_option('wml_flush_routes', 1, false);
}
add_action('created_term', 'wml_schedule_rewrite', 10, 3);
add_action('edited_term', 'wml_schedule_rewrite', 10, 3);
add_action('delete_term', 'wml_schedule_rewrite', 10, 3);
add_action('save_post_page', function() { update_option('wml_flush_routes',1,false); });
add_action('init', function() {
    if (get_option('wml_flush_routes') || get_option('wml_routes_version') !== '2') {
        flush_rewrite_rules(false);
        delete_option('wml_flush_routes');
        update_option('wml_routes_version', '2', false);
    }
}, 99);

function wml_directory_url($city = '', $category = '', $page = 1) {
    $path = $city ? $city : 'vendors';
    if ($category) $path .= ($city ? '/' : '/all/').$category;
    if ($page > 1) $path .= '/page/'.absint($page);
    return home_url('/'.$path.'/');
}
add_filter('post_type_link', function($url, $post) {
    if ($post->post_type !== 'listings' || !$post->post_name) return $url;
    $city = wml_listing_city($post->ID);
    return home_url('/'.(wml_is_venue($post->ID) ? 'wedding-venues' : 'vendor').'/'.($city ? $city->slug.'/' : '').$post->post_name.'/');
}, 10, 2);
add_filter('term_link', function($url, $term, $taxonomy) {
    if ($taxonomy === 'vendor_category') return wml_directory_url('', $term->slug);
    if ($taxonomy === 'location' && in_array($term->term_id, wp_list_pluck(wml_cities(), 'term_id'), true)) return wml_directory_url($term->slug);
    return $url;
}, 10, 3);

// Keep existing homepage shortcodes and related-item templates working during the transition.
add_action('pre_get_posts', function($query) {
    $type = $query->get('post_type');
    if (in_array($type, ['vendors', 'venues'], true)) {
        $query->set('post_type', 'listings');
        $tax = $query->get('tax_query') ?: [];
        $constraint = ['taxonomy'=>'vendor_category','field'=>'slug','terms'=>['wedding-venues'],'operator'=>$type === 'venues' ? 'IN' : 'NOT IN'];
        $query->set('tax_query', $tax ? ['relation'=>'AND',$tax,$constraint] : [$constraint]);
    }
    if (is_admin() || !$query->is_main_query() || !$query->get('wm_directory')) return;
    $query->set('post_type', 'listings');
    $query->set('posts_per_page', 12);
    $query->set('post_status', 'publish');
    $query->set('orderby', 'title');
    $query->set('order', 'ASC');
    $query->is_home = false;
    $query->is_archive = true;
    $query->is_post_type_archive = true;
    $tax = ['relation'=>'AND'];
    foreach (['wm_city'=>'location', 'wm_category'=>'vendor_category'] as $key=>$taxonomy) {
        $slug = $query->get($key);
        if (!$slug) continue;
        if (!get_term_by('slug', $slug, $taxonomy)) { $query->set('wm_invalid', true); $query->set('post__in', [0]); }
        $tax[] = ['taxonomy'=>$taxonomy,'field'=>'slug','terms'=>[$slug]];
    }
    $query->set('tax_query', $tax);
    if (isset($_GET['q']) && is_scalar($_GET['q'])) $query->set('s', sanitize_text_field(wp_unslash($_GET['q'])));
}, 20);

function wml_heading() {
    $category = get_term_by('slug', get_query_var('wm_category'), 'vendor_category');
    $city = get_term_by('slug', get_query_var('wm_city'), 'location');
    return ($category ? $category->name : 'Wedding vendors').($city ? ' in '.$city->name : ' across India');
}
function wml_canonical() { return wml_directory_url(get_query_var('wm_city'), get_query_var('wm_category'), max(1, get_query_var('paged'))); }
add_action('template_redirect', function() {
    global $wp_query;
    if (get_query_var('wm_directory')) {
        if (isset($_GET['browse_city'], $_GET['browse_category']) && is_scalar($_GET['browse_city']) && is_scalar($_GET['browse_category'])) {
            $city = sanitize_title(wp_unslash($_GET['browse_city']));
            $category = sanitize_title(wp_unslash($_GET['browse_category']));
            if (($city && !in_array($city, wp_list_pluck(wml_cities(), 'slug'), true)) || ($category && !get_term_by('slug',$category,'vendor_category'))) {
                $wp_query->set_404(); status_header(404); return;
            }
            $args = [];
            foreach (['q'] as $key) if (!empty($_GET[$key]) && is_scalar($_GET[$key])) $args[$key] = sanitize_text_field(wp_unslash($_GET[$key]));
            wp_safe_redirect(add_query_arg($args,wml_directory_url($city,$category)),302); exit;
        }
        if ($wp_query->get('wm_invalid') || (get_query_var('paged') > 1 && !$wp_query->post_count)) {
            $wp_query->set_404(); status_header(404); return;
        }
        status_header(200);
        return;
    }
    $path = trim($GLOBALS['wp']->request, '/');
    $url = '';
    if (is_singular('listings') && !is_preview()) $url = get_permalink();
    elseif (in_array($path, ['vendor','venues','venue'], true)) $url = wml_directory_url('', $path === 'vendor' ? '' : 'wedding-venues');
    elseif (is_tax('location')) $url = wml_directory_url(get_queried_object()->slug, str_starts_with($path,'venues/') ? 'wedding-venues' : '');
    elseif (is_tax('vendor_category')) {
        $city = get_query_var('location_filter');
        if ($city && !get_term_by('slug', $city, 'location')) { $wp_query->set_404(); status_header(404); return; }
        $url = wml_directory_url($city, get_queried_object()->slug, max(1,get_query_var('paged')));
    }
    elseif (is_404() && preg_match('#^vendors/([^/]+)$#',$path,$match)) {
        $listing = get_page_by_path($match[1],OBJECT,'listings');
        if ($listing && $listing->post_status === 'publish') $url = get_permalink($listing);
    }
    if ($url && trailingslashit(home_url('/'.$path)) !== $url) { wp_safe_redirect($url, 301); exit; }
}, 5);
add_filter('redirect_canonical', fn($url) => get_query_var('wm_directory') ? false : $url);
add_filter('wpseo_canonical', function($url) {
    if (is_singular('listings')) return get_permalink();
    return get_query_var('wm_directory') && !is_404() ? wml_canonical() : $url;
}, 99);
add_filter('wpseo_opengraph_url', function($url) {
    if (is_singular('listings')) return get_permalink();
    return get_query_var('wm_directory') && !is_404() ? wml_canonical() : $url;
}, 99);
add_filter('wpseo_schema_webpage', function($data) {
    if (get_query_var('wm_directory') && !is_404()) {
        $url = wml_canonical();
        $data['@id'] = $url;
        $data['url'] = $url;
        $data['name'] = wml_heading().' | Wedmate';
        $data['@type'] = 'CollectionPage';
        unset($data['breadcrumb']);
    }
    return $data;
});
add_filter('wpseo_title', fn($title) => get_query_var('wm_directory') && !is_404() ? wml_heading().' | Wedmate' : $title, 99);
add_filter('pre_get_document_title', fn($title) => get_query_var('wm_directory') && !is_404() ? wml_heading().' | Wedmate' : $title, 99);
add_filter('wpseo_metadesc', fn($description) => get_query_var('wm_directory') && !is_404() ? 'Explore '.wml_heading().'. Compare venues and wedding professionals, view their work and discover real wedding stories.' : $description, 99);
add_filter('wpseo_robots_array', function($robots) {
    if (get_query_var('wm_directory') && !empty($_GET['q'])) $robots['index'] = 'noindex';
    return $robots;
});
add_filter('template_include', function($template) {
    if (is_404()) return $template;
    if (get_query_var('wm_directory')) return __DIR__.'/templates/directory.php';
    if (is_singular('listings')) return __DIR__.'/templates/single.php';
    return $template;
}, 999);
add_action('wp_enqueue_scripts', function() {
    if (get_query_var('wm_directory') || is_singular('listings')) wp_enqueue_style('wedmate-listings', plugins_url('listings.css', __FILE__), [], filemtime(__DIR__.'/listings.css'));
});

require __DIR__.'/editor.php';
require __DIR__.'/submissions.php';

// Yoast discovers the listing profiles itself; add the directory landing pages separately.
add_action('init', function() {
    global $wpseo_sitemaps;
    if (isset($wpseo_sitemaps)) $wpseo_sitemaps->register_sitemap('wedmate-directory','wml_sitemap');
}, 40);
add_filter('wpseo_sitemap_index_links', function($links) {
    $links[] = ['loc'=>home_url('/wedmate-directory-sitemap.xml')];
    return $links;
});
function wml_sitemap() {
    global $wpseo_sitemaps;
    $urls = [wml_directory_url()];
    $categories = get_terms(['taxonomy'=>'vendor_category','hide_empty'=>true]);
    if (is_wp_error($categories)) $categories = [];
    foreach ($categories as $category) $urls[] = wml_directory_url('', $category->slug);
    foreach (wml_cities() as $city) {
        $ids = get_posts(['post_type'=>'listings','numberposts'=>-1,'fields'=>'ids','tax_query'=>[['taxonomy'=>'location','terms'=>[$city->term_id]]]]);
        if (!$ids) continue;
        $urls[] = wml_directory_url($city->slug);
        $terms = wp_get_object_terms($ids,'vendor_category');
        if (!is_wp_error($terms)) foreach ($terms as $category) $urls[] = wml_directory_url($city->slug,$category->slug);
    }
    $xml = '';
    foreach (array_unique($urls) as $url) $xml .= '<url><loc>'.esc_xml($url).'</loc></url>';
    $wpseo_sitemaps->set_sitemap('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$xml.'</urlset>');
}
