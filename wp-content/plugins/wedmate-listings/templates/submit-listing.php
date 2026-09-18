<?php
defined('ABSPATH') || exit;
get_header();
$values = $GLOBALS['wml_submission_values'] ?? [];
$errors = $GLOBALS['wml_submission_errors'] ?? null;
$receipt = isset($_GET['received']) && is_scalar($_GET['received']) ? sanitize_text_field(wp_unslash($_GET['received'])) : '';
$received = $receipt && get_transient('wml_receipt_'.hash('sha256',$receipt));
$token = wml_submission_token();
$value = fn($key) => isset($values[$key]) && is_scalar($values[$key]) ? (string)$values[$key] : '';
function wml_form_field($key,$values) {
    [$label,$type,$required,$max] = wml_submission_fields()[$key];
    $value = isset($values[$key]) && is_scalar($values[$key]) ? $values[$key] : '';
    echo '<div class="wml-form-field'.($type === 'textarea' ? ' wml-full' : '').'"><label for="wml-input-'.esc_attr($key).'">'.esc_html($label).($required ? ' <span aria-hidden="true">*</span>' : '').'</label>';
    $attributes = ' id="wml-input-'.esc_attr($key).'" name="submission['.esc_attr($key).']" maxlength="'.absint($max).'"'.($required ? ' required' : '');
    if ($type === 'textarea') echo '<textarea'.$attributes.' rows="4">'.esc_textarea($value).'</textarea>';
    else echo '<input'.$attributes.' type="'.esc_attr($type).'" value="'.esc_attr($value).'">';
    echo '</div>';
}
?>
<main id="main-content" class="wml-submission">
<div class="wml-form-wrap">
    <nav class="wml-breadcrumb" aria-label="Breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>">Home</a> / <a href="<?php echo esc_url(wml_directory_url()); ?>">Vendors</a> / List your business</nav>
    <?php if ($received): ?>
    <section class="wml-form-success" role="status"><p class="wml-eyebrow">THANK YOU</p><h1>Your listing has been submitted</h1><p>Our team will review your details and photos before publishing. Your business is not listed publicly yet.</p><p>If we need more information, we’ll use the private contact details you provided.</p><a class="wml-submit-button" href="<?php echo esc_url(wml_directory_url()); ?>">Explore Wedmate</a></section>
    <?php else: ?>
    <header class="wml-form-header"><p class="wml-eyebrow">GROW YOUR WEDDING BUSINESS</p><h1>List your business on Wedmate</h1><p>Wedding venue or wedding professional? Tell us about your business. Our team reviews every submission before it goes live.</p></header>
    <ol class="wml-form-steps"><li><span>1</span> Share your details</li><li><span>2</span> We review your listing</li><li><span>3</span> Approved listings go live</li></ol>
    <?php if ($errors): ?><div class="wml-form-errors" role="alert" tabindex="-1"><h2>Please check your submission</h2><ul><?php foreach ($errors->get_error_messages() as $message): ?><li><?php echo esc_html($message); ?></li><?php endforeach; ?></ul><?php if (!empty($_FILES['photos']['name'])): ?><p>If you want to include photos, please select them again. You can also submit without photos.</p><?php endif; ?></div><?php endif; ?>
    <form class="wml-owner-form" method="post" action="<?php echo esc_url(wml_submission_url()); ?>" enctype="multipart/form-data">
        <input type="hidden" name="submission[nonce]" value="<?php echo esc_attr(wp_create_nonce('wml_submit_listing')); ?>">
        <?php foreach ($token as $key=>$item): ?><input type="hidden" name="submission[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($item); ?>"><?php endforeach; ?>
        <div class="wml-honeypot" aria-hidden="true"><label>Leave this field blank<input type="text" name="submission[company_fax]" value="" tabindex="-1" autocomplete="off"></label></div>
        <p class="wml-required-note">Fields marked * are required.</p>
        <fieldset><legend>Business details</legend><div class="wml-form-grid">
            <?php wml_form_field('business_name',$values); ?>
            <div class="wml-form-field"><label for="wml-input-category">Service category <span aria-hidden="true">*</span></label><select id="wml-input-category" name="submission[category]" required><option value="">Choose a category</option><?php foreach (get_terms(['taxonomy'=>'vendor_category','hide_empty'=>false]) as $term): ?><option value="<?php echo esc_attr($term->slug); ?>" <?php selected($value('category'),$term->slug); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select></div>
            <div class="wml-form-field"><label for="wml-input-city">City <span aria-hidden="true">*</span></label><select id="wml-input-city" name="submission[city]" required><option value="">Choose a city</option><?php foreach (wml_cities() as $term): ?><option value="<?php echo esc_attr($term->slug); ?>" <?php selected($value('city'),$term->slug); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select></div>
            <?php foreach (['address','short_description','services','price','years','booking_timeline'] as $field) wml_form_field($field,$values); ?>
        </div></fieldset>
        <fieldset id="wml-venue-fields"><legend>Venue details</legend><p>For wedding venues only. Other businesses can skip this section.</p><div class="wml-form-grid"><?php foreach (['guests','rooms','per_plate','function_spaces','amenities'] as $field) wml_form_field($field,$values); ?></div></fieldset>
        <fieldset><legend>Public contact details</legend><p>These details will appear on your business profile after approval.</p><div class="wml-form-grid"><?php foreach (['phone','email','website'] as $field) wml_form_field($field,$values); ?></div></fieldset>
        <fieldset><legend>Photos and portfolio</legend><p>Add up to three photos in JPG, PNG or WebP format (<?php echo esc_html(size_format(wml_submission_photo_limit())); ?> each). The first photo will be your cover image. You can also share a link to your portfolio.</p><div class="wml-form-grid"><div class="wml-form-field wml-full"><label for="wml-photos">Business photos (optional)</label><input id="wml-photos" name="photos[]" type="file" multiple accept="image/jpeg,image/png,image/webp" data-max-bytes="<?php echo esc_attr(wml_submission_photo_limit()); ?>"><button id="wml-remove-photos" type="button" hidden>Remove photos</button><p id="wml-photo-feedback" aria-live="polite"></p></div><?php wml_form_field('portfolio_url',$values); ?></div></fieldset>
        <fieldset><legend>Your contact details</legend><p>For the Wedmate review team only. These details will not appear on your public listing.</p><div class="wml-form-grid"><?php foreach (['owner_name','owner_email','owner_phone'] as $field) wml_form_field($field,$values); ?></div></fieldset>
        <label class="wml-consent"><input type="checkbox" name="submission[consent]" value="1" required <?php checked($value('consent'),'1'); ?>><span>I am authorised to represent this business, the information is accurate, and I have permission to share these photos. I agree that Wedmate may publish the business details and photos after review.</span></label>
        <button class="wml-submit-button" type="submit">Submit for review</button>
        <p class="wml-form-note">Submitting does not publish your listing. Every business is reviewed before it appears on Wedmate.</p>
    </form>
    <?php endif; ?>
</div>
</main>
<?php get_footer(); ?>
