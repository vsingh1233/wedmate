<?php

$current_id = get_the_ID();

$query = new WP_Query([
    'post_type' => 'venues',
    'posts_per_page' => 3,
    'post__not_in' => [$current_id]
]);

if ($query->have_posts()) :
?>

<section class="venue-related">

    <div class="venue-related-wrap">

        <h2 class="venue-related-title">
            Other venues you may like
        </h2>

        <div class="venue-related-grid">

            <?php while ($query->have_posts()) : $query->the_post(); ?>

                <?php

                $id = get_the_ID();

                $location = get_the_terms(
                    $id,
                    'location'
                );

                $rating = get_post_meta(
                    $id,
                    'rating',
                    true
                );

                ?>

                <a href="<?php the_permalink(); ?>"
                   class="venue-related-card">

                    <div class="venue-related-image">

                        <?php
                        if (has_post_thumbnail()) {
                            the_post_thumbnail('large');
                        }
                        ?>

                    </div>

                    <div class="venue-related-content">

                        <div class="venue-related-category"></div>

                        <h3 class="venue-related-name">
                            <?php the_title(); ?>
                        </h3>

                        <div class="venue-related-meta">

                            <?php if (!empty($location) && !is_wp_error($location)) : ?>

                                <span>

                                    <i class="fas fa-map-marker-alt"></i>

                                    <?php echo esc_html($location[0]->name); ?>

                                </span>

                            <?php endif; ?>


                            <?php if ($rating) : ?>

                                <span>

                                    <i class="fas fa-star"></i>

                                    <?php echo esc_html($rating); ?>

                                </span>

                            <?php endif; ?>

                        </div>

                    </div>

                </a>

            <?php endwhile; ?>

        </div>

    </div>

</section>

<?php

wp_reset_postdata();

endif;
?>