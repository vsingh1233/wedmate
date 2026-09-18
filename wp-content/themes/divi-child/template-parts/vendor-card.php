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
    'verified_vendor',
    true
);

?>

<a href="<?php echo esc_url($link); ?>" class="vendor-card-link">

    <div class="vendor-card">

        <!-- IMAGE -->
        <div class="vendor-img">

            <div class="vendor-img-inner">

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

                <span class="vendor-badge">

                    <i class="fas fa-star"></i>

                    VERIFIED

                </span>

            <?php endif; ?>


            <!-- RATING -->
            <?php if ($rating) : ?>

                <span class="vendor-rating">

                    ★ <?php echo esc_html($rating); ?>

                </span>

            <?php endif; ?>

        </div>


        <!-- CONTENT -->
        <div class="vendor-content">

            <!-- CATEGORY -->
           <?php

$category = get_the_terms(
    $id,
    'vendor_category'
);

?>

<span class="vendor-cat">

    <?php

    if (!empty($category) && !is_wp_error($category)) {

        echo esc_html($category[0]->name);

    }

    ?>

</span>


            <!-- TITLE -->
            <h3 class="vendor-title">

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
            <!-- META -->
<div class="vendor-meta">

    <!-- LOCATION -->
    <?php

    if (!empty($location) && !is_wp_error($location)) {

        echo '<span class="vendor-location">
                <i class="fas fa-map-marker-alt"></i> '
                . esc_html($location[0]->name) .
             '</span>';

    }

    ?>


    <!-- PRICE -->
    <?php if ($price) : ?>

        <span class="vendor-price">

            <?php echo esc_html($price); ?>

        </span>

    <?php endif; ?>

</div>


           
        </div>

    </div>

</a>