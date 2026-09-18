<?php

$id = get_the_ID();

$link = get_permalink($id);

$location = get_the_terms($id, 'location');


// ================= META =================

$rating = get_post_meta($id, 'rating', true);

$reviews = get_post_meta($id, 'reviews', true);

$price = get_post_meta($id, 'price', true);

$guests = get_post_meta($id, 'guests', true);

$short_description = get_post_meta(
    $id,
    'short_description',
    true
);

$verified = get_post_meta(
    $id,
    'verified_venue',
    true
);

?>

<a href="<?php echo esc_url($link); ?>" class="venue-card-link">

    <div class="venue-card">

        <!-- IMAGE -->
        <div class="venue-img">

            <div class="venue-img-inner">

                <?php

                if (has_post_thumbnail($id)) {

                    echo get_the_post_thumbnail(
                        $id,
                        'large',
                        ['loading' => 'lazy']
                    );

                }

                ?>

            </div>


            <!-- VERIFIED -->
            <?php if ($verified) : ?>

                <span class="venue-badge">

                    <i class="fas fa-star"></i>

                    VERIFIED

                </span>

            <?php endif; ?>


            <!-- RATING -->
            <?php if ($rating) : ?>

                <span class="venue-rating">

                    ★ <?php echo esc_html($rating); ?>

                </span>

            <?php endif; ?>

        </div>


        <!-- CONTENT -->
        <div class="venue-content">

            <!-- CATEGORY -->
            <span class="venue-cat">

                Wedding Venue

            </span>


            <!-- TITLE -->
            <h3 class="venue-title">

                <?php echo esc_html(get_the_title($id)); ?>

            </h3>


            <!-- DESCRIPTION -->
            <p>

                <?php

                if ($short_description) {

                    echo esc_html($short_description);

                } else {

                    echo esc_html(get_the_excerpt($id));

                }

                ?>

            </p>


            <!-- META -->
            <div class="venue-meta">

                <!-- LOCATION -->
                <?php

                if (!empty($location) && !is_wp_error($location)) {

                    echo '<span class="venue-location">
                            <i class="fas fa-map-marker-alt"></i> '
                            . esc_html($location[0]->name) .
                         '</span>';

                }

                ?>


            <!-- PRICE -->
<?php if ($price) : ?>

    <div class="venue-price">

        <i class="fas fa-indian-rupee-sign"></i>

        <?php echo esc_html($price); ?>

    </div>

<?php endif; ?>

            </div>


            

        </div>

    </div>

</a>