<?php
defined('ABSPATH') || exit;

function wml_submission_url() { return home_url('/submit-listing/'); }
function wml_submission_fields() {
    return [
        'business_name'=>['Business name','text',true,150],
        'owner_name'=>['Your name','text',true,100],
        'owner_email'=>['Your email (for our review team only)','email',true,190],
        'owner_phone'=>['Your phone (for our review team only)','tel',true,40],
        'phone'=>['Public business phone','tel',true,40],
        'email'=>['Public business email','email',false,190],
        'website'=>['Business website','url',false,300],
        'portfolio_url'=>['Portfolio or photo-gallery link','url',false,300],
        'address'=>['Business address / area','text',true,300],
        'short_description'=>['About your business','textarea',true,4000],
        'services'=>['Services offered (one per line)','textarea',true,2000],
        'price'=>['Starting price and unit (e.g. ₹50,000 per event)','text',true,150],
        'years'=>['Years in business','text',false,50],
        'booking_timeline'=>['Booking timeline','text',false,200],
        'guests'=>['Guest capacity','text',false,100],
        'rooms'=>['Number of rooms','text',false,100],
        'per_plate'=>['Price per plate','text',false,100],
        'function_spaces'=>['Function spaces','textarea',false,1500],
        'amenities'=>['Amenities','textarea',false,1500],
    ];
}
function wml_submission_photo_limit() { return min(2 * MB_IN_BYTES, wp_max_upload_size()); }
function wml_submission_token() {
    $token = wp_generate_uuid4(); $time = time();
    return ['token'=>$token,'issued'=>$time,'signature'=>hash_hmac('sha256',$token.'|'.$time,wp_salt('nonce'))];
}
function wml_submission_rates($email) {
    return [
        'wml_ip_'.hash_hmac('sha256',$_SERVER['REMOTE_ADDR'] ?? 'unknown',wp_salt('auth')) => HOUR_IN_SECONDS,
        'wml_email_'.hash_hmac('sha256',strtolower($email),wp_salt('auth')) => DAY_IN_SECONDS,
    ];
}
/** All public submissions pass through this allowlist; clients cannot choose IDs, authors or status. */
function wml_accept_submission($raw, $files = []) {
    $errors = new WP_Error();
    $scalar = fn($key) => isset($raw[$key]) && is_scalar($raw[$key]) ? trim((string)$raw[$key]) : '';
    $token = $scalar('token'); $issued = (int)$scalar('issued');
    if (!wp_verify_nonce($scalar('nonce'),'wml_submit_listing') || !preg_match('/^[a-f0-9-]{36}$/D',$token)
        || $issued > time() || $issued < time()-2*HOUR_IN_SECONDS
        || !hash_equals(hash_hmac('sha256',$token.'|'.$issued,wp_salt('nonce')),$scalar('signature'))) {
        return new WP_Error('session','Your form has expired. Please review your details and submit again.');
    }
    if ($scalar('company_fax')) return new WP_Error('spam','We could not accept this submission. Please try again.');
    $lock = 'wml_submission_'.hash('sha256',$token);
    $existing = get_option($lock);
    if ($existing && !empty($existing['id']) && get_post((int)$existing['id'])) return (int)$existing['id'];
    $data = [];
    foreach (wml_submission_fields() as $key=>$field) {
        [$label,$type,$required,$max] = $field;
        $value = $scalar($key);
        if ($required && $value === '') $errors->add($key,$label.' is required.');
        if (mb_strlen($value) > $max) $errors->add($key,$label.' is too long.');
        if ($value && $type === 'email' && !is_email($value)) $errors->add($key,'Enter a valid email for '.$label.'.');
        if ($value && $type === 'url' && (!filter_var($value,FILTER_VALIDATE_URL) || !in_array(strtolower((string)wp_parse_url($value,PHP_URL_SCHEME)),['http','https'],true))) $errors->add($key,$label.' must be a full http:// or https:// link.');
        if ($value && $type === 'tel' && strlen(preg_replace('/\D/','',$value)) < 7) $errors->add($key,'Enter a valid phone number for '.$label.'.');
        $data[$key] = $type === 'textarea' ? sanitize_textarea_field($value) : sanitize_text_field($value);
        if ($type === 'email') $data[$key] = sanitize_email($value);
        if ($type === 'url') $data[$key] = esc_url_raw($value,['http','https']);
    }
    $category = get_term_by('slug',sanitize_title($scalar('category')),'vendor_category');
    if (!$category) $errors->add('category','Choose a service category.');
    $city = get_term_by('slug',sanitize_title($scalar('city')),'location');
    if (!$city || !in_array($city->term_id,wp_list_pluck(wml_cities(),'term_id'),true)) $errors->add('city','Choose a city from the list.');
    if ($scalar('consent') !== '1') $errors->add('consent','Please confirm that you are authorised to submit these business details.');
    $photos = [];
    if (!empty($files['name'])) {
        if (!is_array($files['name']) || count($files['name']) > 3) $errors->add('photos','Please choose no more than three photos.');
        else foreach ($files['name'] as $i=>$name) {
            $error = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE) continue;
            $tmp = $files['tmp_name'][$i] ?? '';
            $size = $files['size'][$i] ?? 0;
            if (!is_string($name) || !is_string($tmp) || !is_numeric($size) || !is_int($error)) { $errors->add('photos','Please select your photos again.'); continue; }
            if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmp) || $size > wml_submission_photo_limit()) { $errors->add('photos','One of your photos could not be uploaded or exceeds the size limit. Please select your photos again.'); continue; }
            $info = wp_getimagesize($tmp);
            $type = wp_check_filetype_and_ext($tmp,$name,['jpg|jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp']);
            if (!$info || !$type['ext'] || !in_array($info['mime'],['image/jpeg','image/png','image/webp'],true) || $info[0]*$info[1] > 24000000) { $errors->add('photos','Photos must be JPG, PNG or WebP images under 24 megapixels.'); continue; }
            $photos[] = ['name'=>sanitize_file_name($name),'tmp_name'=>$tmp,'type'=>$info['mime'],'size'=>$size,'error'=>UPLOAD_ERR_OK];
        }
    }
    if ($errors->has_errors()) return $errors;
    foreach (wml_submission_rates($data['owner_email']) as $key=>$duration) if ((int)get_transient($key) >= 3) return new WP_Error('rate','You have reached the submission limit. Please try again later.');
    if (!add_option($lock,['created'=>time()], '', false)) return new WP_Error('busy','This submission is already being processed. Please wait before trying again.');
    $id = 0; $attachments = [];
    // Legacy theme save hooks read top-level POST fields; never let a public request feed them.
    $original_post = $_POST;
    $_POST = [];
    try {
        $id = wp_insert_post(wp_slash(['post_type'=>'listings','post_status'=>'draft','post_author'=>0,'post_title'=>$data['business_name']]),true);
        if (is_wp_error($id)) throw new RuntimeException('We could not save your listing. Please try again.');
        foreach (['vendor_category'=>$category,'location'=>$city] as $taxonomy=>$term) {
            $assigned = wp_set_object_terms($id,[(int)$term->term_id],$taxonomy);
            if (is_wp_error($assigned)) throw new RuntimeException('We could not save your listing category or city. Please try again.');
        }
        foreach ($data as $key=>$value) {
            if ($key === 'business_name') continue;
            $meta = str_starts_with($key,'owner_') || $key === 'portfolio_url' ? '_wml_'.$key : $key;
            update_post_meta($id,$meta,wp_slash($value));
        }
        if ($category->slug === 'wedding-venues') update_post_meta($id,'venue_overview',wp_slash($data['short_description']));
        update_post_meta($id,'_wml_source','owner_form');
        update_post_meta($id,'_wml_submitted_at',gmdate('c'));
        update_post_meta($id,'_wml_consent','Business representative confirmed authority and permission to publish submitted business details and photos.');
        if ($photos) {
            require_once ABSPATH.'wp-admin/includes/file.php';
            require_once ABSPATH.'wp-admin/includes/media.php';
            require_once ABSPATH.'wp-admin/includes/image.php';
            foreach ($photos as $photo) {
                $_FILES['wml_photo'] = $photo;
                $attachment = media_handle_upload('wml_photo',$id,[],['test_form'=>false,'mimes'=>['jpg|jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp']]);
                if (is_wp_error($attachment)) throw new RuntimeException('A photo could not be saved. Please reselect your photos and try again.');
                $attachments[] = $attachment;
            }
            unset($_FILES['wml_photo']);
            set_post_thumbnail($id,$attachments[0]);
            update_post_meta($id,$category->slug === 'wedding-venues' ? 'venue_gallery' : 'portfolio_gallery',implode(',',$attachments));
        }
        $saved = wp_update_post(['ID'=>$id,'post_status'=>'pending'],true);
        if (is_wp_error($saved)) throw new RuntimeException('We could not submit your listing for review. Please try again.');
        update_option($lock,['created'=>time(),'id'=>$id],false);
        wp_schedule_single_event(time()+DAY_IN_SECONDS,'wml_clear_submission_lock',[$lock]);
        foreach (wml_submission_rates($data['owner_email']) as $key=>$duration) set_transient($key,(int)get_transient($key)+1,$duration);
        return $id;
    } catch (Throwable $exception) {
        foreach ($attachments as $attachment) wp_delete_attachment($attachment,true);
        if (is_int($id) && $id) wp_delete_post($id,true);
        delete_option($lock);
        return new WP_Error('save','Your listing was not submitted. Please reselect any photos and try again.');
    } finally {
        $_POST = $original_post;
    }
}
add_action('wml_clear_submission_lock',function($key) { if (str_starts_with($key,'wml_submission_')) delete_option($key); });

add_action('template_redirect', function() {
    if (!is_page('submit-listing')) return;
    nocache_headers();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
    $data = isset($_POST['submission']) && is_array($_POST['submission']) ? wp_unslash($_POST['submission']) : [];
    if (!$data && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > wp_convert_hr_to_bytes(ini_get('post_max_size'))) {
        $GLOBALS['wml_submission_errors'] = new WP_Error('size','The upload is too large. Please use fewer or smaller photos and submit again.'); return;
    }
    $result = wml_accept_submission($data,$_FILES['photos'] ?? []);
    if (is_wp_error($result)) { $GLOBALS['wml_submission_errors'] = $result; $GLOBALS['wml_submission_values'] = $data; return; }
    // The redirect contains no personal details or predictable record ID.
    $receipt = wp_generate_password(32,false,false);
    set_transient('wml_receipt_'.hash('sha256',$receipt),true,HOUR_IN_SECONDS);
    wp_safe_redirect(add_query_arg('received',$receipt,wml_submission_url()),303); exit;
}, 1);
add_filter('template_include', function($template) { return is_page('submit-listing') ? __DIR__.'/templates/submit-listing.php' : $template; },1000);
add_action('wp_enqueue_scripts', function() {
    if (!is_page('submit-listing')) return;
    wp_enqueue_style('wedmate-listings',plugins_url('listings.css',__FILE__),[],filemtime(__DIR__.'/listings.css'));
    wp_enqueue_style('wml-submission',plugins_url('submissions.css',__FILE__),['wedmate-listings'],filemtime(__DIR__.'/submissions.css'));
    wp_enqueue_script('wml-submission',plugins_url('submissions.js',__FILE__),[],filemtime(__DIR__.'/submissions.js'),true);
});
add_action('admin_menu', function() {
    add_submenu_page('edit.php?post_type=listings','Owner submissions','Owner submissions','edit_posts','edit.php?post_type=listings&post_status=pending&wml_owner_submissions=1');
});
add_action('pre_get_posts', function($query) {
    if (is_admin() && $query->is_main_query() && $query->get('post_type') === 'listings' && !empty($_GET['wml_owner_submissions'])) $query->set('meta_query',[['key'=>'_wml_source','value'=>'owner_form']]);
});
add_action('add_meta_boxes_listings', function($post) {
    if (get_post_meta($post->ID,'_wml_source',true) === 'owner_form') add_meta_box('wml_owner_review','Owner submission — review before publishing','wml_owner_review_box','listings','normal','high');
});
function wml_owner_review_box($post) {
    echo '<p><strong>Review the listing details, selected city/category and photos below. Save your edits and click Publish to approve this listing. Move to Trash to reject it.</strong></p><p>Publishing lists it automatically in the matching directories. These owner-contact details are private and do not appear on the public profile.</p><dl>';
    foreach (['owner_name'=>'Submitted by','owner_email'=>'Private contact email','owner_phone'=>'Private contact phone','submitted_at'=>'Submitted at (UTC)'] as $key=>$label) echo '<dt><strong>'.esc_html($label).'</strong></dt><dd>'.esc_html(get_post_meta($post->ID,'_wml_'.$key,true)).'</dd>';
    echo '</dl>';
    $portfolio = get_post_meta($post->ID,'_wml_portfolio_url',true);
    if ($portfolio) echo '<p><a href="'.esc_url($portfolio).'" target="_blank" rel="noopener noreferrer">Review owner’s portfolio / gallery</a></p>';
    $images = get_attached_media('image',$post->ID);
    if ($images) {
        echo '<h4>Submitted photos</h4><p>';
        foreach ($images as $image) echo '<a href="'.esc_url(wp_get_attachment_url($image->ID)).'" target="_blank" rel="noopener noreferrer" style="display:inline-block;margin:0 10px 10px 0">'.wp_get_attachment_image($image->ID,'thumbnail').'</a>';
        echo '</p>';
    }
    echo '<p>'.esc_html(get_post_meta($post->ID,'_wml_consent',true)).'</p>';
}
