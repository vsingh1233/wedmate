<?php get_header(); ?>

<div id="main-content">

    <?php

    $current_term = get_queried_object();

    /* =========================================================
       VENUE SEO DATA
    ========================================================= */

    $seo_key = 'venue-' . $current_term->slug;

    $seo_data = wedmate_dynamic_seo_data();

   $current_seo = [];

if (!empty($seo_data[$seo_key])) {

    $current_seo = $seo_data[$seo_key];

}

    ?>

    <!-- HERO -->
    <section class="venues-hero">

        <span class="venues-label">
            Wedding Venues
        </span>

        <h1 class="venues-title">

            <?php

            if (!empty($current_seo['h1'])) {

                echo esc_html($current_seo['h1']);

            } else {

                echo 'Wedding Venues in ' . esc_html($current_term->name);

            }

            ?>

        </h1>

    </section>


    <!-- VENUE GRID -->
    <section id="venue-results" class="venue-grid">

        <?php

        $query = new WP_Query(array(

            'post_type' => 'venues',

            'posts_per_page' => 12,

            'tax_query' => array(
                array(
                    'taxonomy' => 'location',
                    'field' => 'slug',
                    'terms' => $current_term->slug
                )
            )

        ));

        if ($query->have_posts()) :

            while ($query->have_posts()) :
                $query->the_post();

                get_template_part(
                    'template-parts/venue-card'
                );

            endwhile;

            wp_reset_postdata();

        else :

            echo '<p>No venues found.</p>';

        endif;

        ?>

    </section>

</div>

<?php get_footer(); ?>