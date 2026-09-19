<?php
defined('ABSPATH') || exit;
get_header();
while (have_posts()): the_post();
$listing_id = get_the_ID();
$breadcrumb_city = wml_listing_city($listing_id);
$terms = get_the_terms($listing_id, 'vendor_category');
?>
<main id="main-content" class="wml-profile">
    <nav class="wml-container wml-breadcrumb" aria-label="Breadcrumb">
        <a href="<?php echo esc_url(wml_directory_url()); ?>">Vendors</a>
        <?php if ($breadcrumb_city): ?>
            / <a href="<?php echo esc_url(wml_directory_url($breadcrumb_city->slug)); ?>"><?php echo esc_html($breadcrumb_city->name); ?></a>
        <?php endif; ?>
        <?php if ($terms && !is_wp_error($terms)): ?>
            / <a href="<?php echo esc_url(wml_directory_url($breadcrumb_city ? $breadcrumb_city->slug : '', $terms[0]->slug)); ?>"><?php echo esc_html($terms[0]->name); ?></a>
        <?php endif; ?>
        / <span aria-current="page"><?php the_title(); ?></span>
    </nav>
    <?php if (wml_is_venue($listing_id)): ?>
        <div class="venue-single"><?php get_template_part('template-parts/venue/hero'); get_template_part('template-parts/venue/content'); get_template_part('template-parts/venue/related'); ?></div>
    <?php else: ?>
        <?php echo do_shortcode('[vendor_single][vendor_content_layout][related_vendors]'); ?>
    <?php endif; ?>
    <?php
    $details = [];
    foreach (['photography','makeup-artists'] as $group) if (has_term($group,'vendor_category',$listing_id)) {
        foreach (wml_fields()[$group] as $key=>$label) if ($value=get_post_meta($listing_id,$key,true)) $details[$label]=$value;
    }
    if ($details): ?><section class="wml-container wml-extra"><h2>Services and packages</h2><dl><?php foreach ($details as $label=>$value): ?><dt><?php echo esc_html($label); ?></dt><dd><?php echo nl2br(esc_html($value)); ?></dd><?php endforeach; ?></dl></section><?php endif; ?>
    <?php
    $stories = new WP_Query(['post_type'=>'stories','posts_per_page'=>6,'meta_query'=>[['key'=>'selected_vendors','value'=>'i:'.$listing_id.';','compare'=>'LIKE']]]);
    if ($stories->have_posts()): ?>
    <section class="wml-container wml-featured-stories"><h2>Featured in real weddings</h2><div class="wml-grid"><?php while ($stories->have_posts()): $stories->the_post(); ?><article class="wml-card"><a class="wml-card-image" href="<?php the_permalink(); ?>"><?php the_post_thumbnail('large'); ?></a><div class="wml-card-body"><h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3></div></article><?php endwhile; ?></div></section>
    <?php endif; wp_reset_postdata(); ?>
</main>
<?php endwhile; get_footer(); ?>
