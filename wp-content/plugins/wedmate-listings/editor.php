<?php
defined('ABSPATH') || exit;

function wml_fields() {
    return [
        'common' => ['short_description'=>'Short description','rating'=>'Rating','reviews'=>'Review count','price'=>'Display price (preserve currency and unit)','phone'=>'Phone','email'=>'Email','website'=>'Website','address'=>'Address','years'=>'Years in business','weddings'=>'Weddings completed','booking_timeline'=>'Booking timeline','services'=>'Services (one per line)','portfolio_gallery'=>'Portfolio image IDs'],
        'wedding-venues' => ['tagline'=>'Venue tagline','guests'=>'Guest capacity','rooms'=>'Rooms','per_plate'=>'Price per plate','venue_overview'=>'Venue overview','function_spaces'=>'Function spaces (one per line)','amenities'=>'Amenities (one per line)','verified_text'=>'Verification note','venue_gallery'=>'Venue image IDs'],
        'photography' => ['photography_style'=>'Photography styles','photography_package'=>'Photography package details','delivery_timeline'=>'Delivery timeline'],
        'makeup-artists' => ['makeup_services'=>'Makeup services','makeup_package'=>'Bridal makeup package details','trial_policy'=>'Trial policy'],
    ];
}
add_action('add_meta_boxes', function() {
    add_meta_box('wml_details','Listing details','wml_editor','listings','normal','high');
    add_meta_box('wml_story_team','Wedding team roles and order','wml_story_editor','stories','normal','default');
});
function wml_editor($post) {
    wp_nonce_field('wml_save', 'wml_nonce');
    echo '<p>Choose service categories and cities in the sidebar. Wedding venues share the same directory, with their own fields and profile design.</p>';
    $labels = ['common'=>'Business details','wedding-venues'=>'Wedding venue details','photography'=>'Photography details','makeup-artists'=>'Makeup artist details'];
    foreach (wml_fields() as $group=>$fields) {
        $term = get_term_by('slug', $group, 'vendor_category');
        echo '<section class="wml-field-group" data-term="'.esc_attr($term ? $term->term_id : '').'"><h3>'.esc_html($labels[$group]).'</h3>';
        foreach ($fields as $key=>$label) {
            $value = get_post_meta($post->ID, $key, true);
            echo '<p><label for="wml-'.esc_attr($key).'"><strong>'.esc_html($label).'</strong></label><br>';
            if (in_array($key, ['short_description','services','address','venue_overview','function_spaces','amenities','photography_package','makeup_services','makeup_package'], true)) {
                echo '<textarea class="widefat" rows="3" id="wml-'.esc_attr($key).'" name="wml['.esc_attr($key).']">'.esc_textarea($value).'</textarea>';
            } else {
                echo '<input class="widefat" type="text" id="wml-'.esc_attr($key).'" name="wml['.esc_attr($key).']" value="'.esc_attr($value).'">';
            }
            if (str_ends_with($key, '_gallery')) echo '<button type="button" class="button wml-gallery" data-target="wml-'.esc_attr($key).'">Choose images</button>';
            echo '</p>';
        }
        echo '</section>';
    }
    foreach (['verified_vendor'=>'Verified business','verified_venue'=>'Verified venue'] as $key=>$label) {
        echo '<p><label><input type="checkbox" name="wml['.esc_attr($key).']" value="1" '.checked(get_post_meta($post->ID,$key,true), '1', false).'> '.esc_html($label).'</label></p>';
    }
}
add_action('admin_enqueue_scripts', function() {
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'listings') {
        wp_enqueue_media();
        wp_enqueue_script('wml-editor', plugins_url('editor.js', __FILE__), ['jquery'], '1.0.0', true);
    }
});
add_action('save_post_listings', function($id) {
    if (wp_is_post_revision($id) || wp_is_post_autosave($id) || !current_user_can('edit_post', $id)
        || empty($_POST['wml_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wml_nonce'])), 'wml_save')
        || !isset($_POST['wml']) || !is_array($_POST['wml'])) return;
    $data = wp_unslash($_POST['wml']);
    foreach (wml_fields() as $fields) foreach ($fields as $key=>$label) {
        if (!isset($data[$key]) || !is_scalar($data[$key])) continue;
        $value = sanitize_textarea_field($data[$key]);
        if ($key === 'email') $value = sanitize_email($value);
        if ($key === 'website') $value = esc_url_raw($value);
        if (str_ends_with($key, '_gallery')) {
            $ids = array_filter(array_map('absint', explode(',', $value)), fn($image) => wp_attachment_is_image($image));
            $value = implode(',', array_unique($ids));
        }
        update_post_meta($id, $key, $value);
    }
    foreach (['verified_vendor','verified_venue'] as $key) update_post_meta($id, $key, empty($data[$key]) ? '' : '1');
});

function wml_story_editor($post) {
    wp_nonce_field('wml_team', 'wml_team_nonce');
    $selected = (array)get_post_meta($post->ID,'selected_vendors',true);
    $roles = (array)get_post_meta($post->ID,'wml_team_roles',true);
    echo '<p>Select businesses, including venues, in Wedding Team above. Set the credit role and display order here. Lower numbers appear first.</p><table class="widefat"><thead><tr><th>Business</th><th>Role in this wedding</th><th>Order</th></tr></thead><tbody>';
    foreach (get_posts(['post_type'=>'listings','numberposts'=>-1,'orderby'=>'title','order'=>'ASC']) as $listing) {
        $position = array_search($listing->ID,$selected);
        echo '<tr><td>'.esc_html($listing->post_title).'</td><td><input aria-label="Role for '.esc_attr($listing->post_title).'" name="wml_team_role['.$listing->ID.']" value="'.esc_attr($roles[$listing->ID] ?? '').'" placeholder="e.g. Wedding venue, Bridal makeup"></td><td><input aria-label="Order for '.esc_attr($listing->post_title).'" type="number" min="0" name="wml_team_order['.$listing->ID.']" value="'.esc_attr($position === false ? 100 : $position + 1).'" style="width:75px"></td></tr>';
    }
    echo '</tbody></table>';
}
// The child theme saves selected_vendors first; preserve that field for existing story templates.
add_action('save_post', function($id) {
    if (get_post_type($id) !== 'stories' || wp_is_post_revision($id) || wp_is_post_autosave($id)
        || !current_user_can('edit_post',$id) || empty($_POST['wml_team_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wml_team_nonce'])), 'wml_team')) return;
    $selected = array_values(array_filter(array_map('absint', (array)get_post_meta($id,'selected_vendors',true)), fn($listing) => get_post_type($listing) === 'listings'));
    $orders = isset($_POST['wml_team_order']) && is_array($_POST['wml_team_order']) ? $_POST['wml_team_order'] : [];
    usort($selected, fn($a,$b) => absint($orders[$a] ?? 100) <=> absint($orders[$b] ?? 100));
    $roles = [];
    foreach ($selected as $listing) $roles[$listing] = sanitize_text_field(wp_unslash($_POST['wml_team_role'][$listing] ?? ''));
    update_post_meta($id, 'selected_vendors', array_unique($selected));
    update_post_meta($id, 'wml_team_roles', $roles);
}, 30);
