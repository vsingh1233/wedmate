<?php
/** Run only from PHP CLI after a database backup: php .../migrate.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('DISABLE_WP_CRON', true);
require dirname(__DIR__,3).'/wp-load.php';
require_once ABSPATH.'wp-admin/includes/plugin.php';
if (get_option('wml_migrated_v1')) { echo "Migration already completed.\n"; exit; }
if (!function_exists('wml_register')) require __DIR__.'/wedmate-listings.php';
wml_register();
global $wpdb;
$legacy = $wpdb->get_results("SELECT ID, post_type, post_name, post_status FROM {$wpdb->posts} WHERE post_type IN ('vendors','venues')");
$snapshot = ['created'=>gmdate('c'),'posts'=>$legacy,'post_types'=>get_option('cptui_post_types'),'taxonomies'=>get_option('cptui_taxonomies'),'story_credits'=>[]];
foreach (get_posts(['post_type'=>'stories','numberposts'=>-1,'post_status'=>'any']) as $story) $snapshot['story_credits'][$story->ID] = get_post_meta($story->ID,'selected_vendors',true);
// Preserve the original registration and IDs for an auditable, reversible migration.
update_option('wml_migration_snapshot', $snapshot, false);
$categories = ['wedding-venues'=>'Wedding Venues','makeup-artists'=>'Makeup Artists'];
foreach ($categories as $slug=>$name) if (!term_exists($slug,'vendor_category')) wp_insert_term($name,'vendor_category',['slug'=>$slug]);
foreach (['banquet-halls'=>'Banquet Halls','hotels'=>'Hotels','resorts'=>'Resorts','palaces'=>'Palaces','farmhouses'=>'Farmhouses','lawns'=>'Lawns'] as $slug=>$name) if (!term_exists($slug,'venue_type')) wp_insert_term($name,'venue_type',['slug'=>$slug]);
$venue_term = get_term_by('slug','wedding-venues','vendor_category');
foreach ($legacy as $post) {
    update_post_meta($post->ID,'_wml_previous_post_type',$post->post_type);
    update_post_meta($post->ID,'_wml_previous_url',get_permalink($post->ID));
    if ($post->post_type === 'venues') wp_set_object_terms($post->ID,(int)$venue_term->term_id,'vendor_category',true);
    // Change only the post type: Divi content, galleries, metadata and relationship IDs stay intact.
    if ($wpdb->update($wpdb->posts,['post_type'=>'listings'],['ID'=>$post->ID]) === false) throw new RuntimeException('Failed to migrate ID '.$post->ID);
    clean_post_cache($post->ID);
}
$taxonomies = get_option('cptui_taxonomies',[]);
foreach ($taxonomies as &$taxonomy) {
    if (isset($taxonomy['object_types'])) {
        $types = $taxonomy['object_types'];
        if (array_intersect($types,['vendors','venues'])) $types[] = 'listings';
        $taxonomy['object_types'] = array_values(array_unique(array_diff($types,['vendors','venues'])));
    }
}
unset($taxonomy);
update_option('cptui_taxonomies',$taxonomies);
$types = get_option('cptui_post_types',[]);
unset($types['vendors'],$types['venues']);
update_option('cptui_post_types',$types);
unregister_post_type('vendors');
unregister_post_type('venues');
foreach (['vendor_category','location','vendor_tag'] as $taxonomy) {
    if (!taxonomy_exists($taxonomy)) continue;
    register_taxonomy_for_object_type($taxonomy,'listings');
    $terms = get_terms(['taxonomy'=>$taxonomy,'hide_empty'=>false,'fields'=>'tt_ids']);
    if (!is_wp_error($terms)) wp_update_term_count_now($terms,$taxonomy);
}
$result = activate_plugin('wedmate-listings/wedmate-listings.php');
if (is_wp_error($result)) throw new RuntimeException($result->get_error_message());
// Category assignments are deliberately preserved; corrections can be made in the new editor.
$menu_snapshot = [];
foreach (get_posts(['post_type'=>'nav_menu_item','numberposts'=>-1,'post_status'=>'any']) as $item) {
    $type = get_post_meta($item->ID,'_menu_item_type',true);
    $object = get_post_meta($item->ID,'_menu_item_object',true);
    $url = get_post_meta($item->ID,'_menu_item_url',true);
    if (($type === 'post_type_archive' && in_array($object,['vendors','venues'],true)) || in_array(rtrim($url,'/'),[home_url('/vendor'),home_url('/venue'),home_url('/venues')],true)) {
        $menu_snapshot[$item->ID] = get_post_meta($item->ID);
        $venue = $object === 'venues' || preg_match('#/venues?/?$#',$url);
        update_post_meta($item->ID,'_menu_item_type','custom');
        update_post_meta($item->ID,'_menu_item_object','custom');
        update_post_meta($item->ID,'_menu_item_url',wml_directory_url('',$venue ? 'wedding-venues' : ''));
    } elseif ($type === 'post_type' && in_array($object,['vendors','venues'],true)) {
        $menu_snapshot[$item->ID] = get_post_meta($item->ID);
        update_post_meta($item->ID,'_menu_item_object','listings');
    }
}
update_option('wml_original_menu_meta',$menu_snapshot,false);
if (function_exists('YoastSEO')) foreach ($legacy as $post) {
    YoastSEO()->classes->get(\Yoast\WP\SEO\Builders\Indexable_Builder::class)->build_for_id_and_type($post->ID,'post');
}
global $wp_rewrite;
unset($wp_rewrite->extra_rules_top['^vendors/([^/]+)/([^/]+)/?$']);
wml_routes();
flush_rewrite_rules(false);
// Rebuild once on the next request with the plugin loaded in its normal hook order.
update_option('wml_flush_routes',1,false);
update_option('wml_migrated_v1',gmdate('c'),false);
if (class_exists('WPSEO_Sitemaps_Cache')) WPSEO_Sitemaps_Cache::clear();
foreach ($snapshot['story_credits'] as $id=>$credits) if (get_post_meta($id,'selected_vendors',true) !== $credits) throw new RuntimeException('Story credits changed: '.$id);
echo wp_json_encode(['migrated'=>count($legacy),'ids'=>wp_list_pluck($legacy,'ID'),'story_credits_preserved'=>true],JSON_PRETTY_PRINT)."\n";
