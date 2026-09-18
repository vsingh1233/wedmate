<?php get_header(); ?>

<?php

$current_category = get_queried_object();

$current_location = get_query_var('location_filter');


/* ===============     LOAD SEO DATA      =============== */

$seo_key = $current_category->slug;

if ($current_location) {

    $seo_key .= '-' . $current_location;

}

$seo_data = wedmate_dynamic_seo_data();

$current_seo = [];

if (!empty($seo_data[$seo_key])) {

    $current_seo = $seo_data[$seo_key];

}

$args = [
    'post_type' => 'vendors',
    'posts_per_page' => -1,
    'tax_query' => [
        [
            'taxonomy' => 'vendor_category',
            'field' => 'term_id',
            'terms' => $current_category->term_id
        ]
    ]
];

if (!empty($current_location)) {

    $args['tax_query'][] = [
        'taxonomy' => 'location',
        'field' => 'slug',
        'terms' => $current_location
    ];

}

$query = new WP_Query($args);

?>

<div class="vendors-page">

    <section class="vendors-hero">

        <div class="vendors-hero-inner">

            <span class="vendors-label">
                Vendors
            </span>

            <h1 class="vendors-title">

<?php

if (!empty($current_seo['h1'])) {

    echo esc_html($current_seo['h1']);

} else {

    echo esc_html($current_category->name);

    if ($current_location) {

        echo ' in ' . esc_html(ucfirst($current_location));

    }

}

?>

</h1>

        </div>

    </section>


    <section class="vendor-grid">

        <?php

        if ($query->have_posts()) :

            while ($query->have_posts()) :

                $query->the_post();

                get_template_part(
    'template-parts/vendor-card'
);

            endwhile;

            wp_reset_postdata();

        else :

            echo '<p>No vendors found.</p>';

        endif;

        ?>

    </section>

</div>

<?php get_footer(); ?>

