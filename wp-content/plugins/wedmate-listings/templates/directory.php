<?php
defined('ABSPATH') || exit;
get_header();
$city = get_query_var('wm_city');
$category = get_query_var('wm_category');
$categories = get_terms(['taxonomy'=>'vendor_category','hide_empty'=>false]);
$search = isset($_GET['q']) && is_scalar($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
global $wp_query;
?>
<main id="main-content" class="wml-directory">
    <div class="wml-container">
        <nav class="wml-breadcrumb" aria-label="Breadcrumb"><a href="<?php echo esc_url(home_url('/')); ?>">Home</a> / <a href="<?php echo esc_url(wml_directory_url()); ?>">Vendors</a><?php if ($city): ?> / <a href="<?php echo esc_url(wml_directory_url($city)); ?>"><?php echo esc_html(get_term_by('slug',$city,'location')->name); ?></a><?php endif; ?></nav>
        <header class="wml-heading"><p class="wml-eyebrow">YOUR WEDDING TEAM STARTS HERE</p><h1><?php echo esc_html(wml_heading()); ?></h1><p>Discover wedding venues and the people who bring your celebration to life.</p></header>
        <form class="wml-filters" method="get" action="<?php echo esc_url(wml_directory_url()); ?>">
            <label>City<select name="browse_city"><option value="">All cities</option><?php foreach (wml_cities() as $term): ?><option value="<?php echo esc_attr($term->slug); ?>" <?php selected($city,$term->slug); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select></label>
            <label>Service<select name="browse_category"><option value="">All services</option><?php foreach ($categories as $term): ?><option value="<?php echo esc_attr($term->slug); ?>" <?php selected($category,$term->slug); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; ?></select></label>
            <label>Search<input name="q" type="search" placeholder="Business name or keyword" value="<?php echo esc_attr($search); ?>"></label>
            <button type="submit">Find vendors</button>
        </form>
        <nav class="wml-categories" aria-label="Service categories"><a class="<?php echo !$category ? 'active' : ''; ?>" href="<?php echo esc_url(wml_directory_url($city)); ?>">All services</a><?php foreach ($categories as $term): ?><a class="<?php echo $category === $term->slug ? 'active' : ''; ?>" href="<?php echo esc_url(wml_directory_url($city,$term->slug)); ?>"><?php echo esc_html($term->name); ?></a><?php endforeach; ?></nav>
        <p class="wml-result-count"><?php echo esc_html(sprintf(_n('%s listing','%s listings',$wp_query->found_posts),number_format_i18n($wp_query->found_posts))); ?></p>
        <div class="wml-grid">
        <?php while (have_posts()): the_post(); $id = get_the_ID(); $cities = get_the_terms($id,'location'); $services = get_the_terms($id,'vendor_category'); ?>
            <article class="wml-card"><a class="wml-card-image" href="<?php the_permalink(); ?>"><?php if (has_post_thumbnail()): the_post_thumbnail('large'); else: ?><span>Wedmate</span><?php endif; ?></a><div class="wml-card-body"><p class="wml-card-category"><?php echo esc_html($services && !is_wp_error($services) ? implode(' · ',wp_list_pluck($services,'name')) : 'Wedding professional'); ?></p><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><p><?php echo esc_html($cities && !is_wp_error($cities) ? implode(', ',wp_list_pluck($cities,'name')) : 'Location to be confirmed'); ?></p><?php $price = get_post_meta($id,'price',true); if ($price): ?><p class="wml-price"><?php echo esc_html($price); ?></p><?php endif; ?><a class="wml-profile-link" href="<?php the_permalink(); ?>">View <?php echo wml_is_venue($id) ? 'venue' : 'profile'; ?> <span aria-hidden="true">→</span></a></div></article>
        <?php endwhile; ?>
        </div>
        <?php if (!$wp_query->post_count): ?><div class="wml-empty"><h2>No listings found</h2><p>Try another service or city, or clear your search.</p><a href="<?php echo esc_url(wml_directory_url()); ?>">Explore all vendors</a></div><?php endif; ?>
        <nav class="wml-pagination" aria-label="Listing pages"><?php echo paginate_links(['base'=>str_replace('999999','%#%',wml_directory_url($city,$category,999999)),'format'=>'','current'=>max(1,get_query_var('paged')),'total'=>$wp_query->max_num_pages,'add_args'=>array_filter(['q'=>$search])]); ?></nav>
        <section class="wml-city-links"><h2>Explore by city</h2><?php foreach (wml_cities() as $term): ?><a href="<?php echo esc_url(wml_directory_url($term->slug,$category)); ?>"><?php echo esc_html($term->name); ?></a><?php endforeach; ?></section>
        <section class="wml-owner-cta"><h2>Own a wedding business?</h2><p>Share your business with couples planning their celebration.</p><a href="<?php echo esc_url(wml_submission_url()); ?>">Submit your listing for review →</a></section>
    </div>
</main>
<?php get_footer(); ?>
