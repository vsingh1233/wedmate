<?php

$id = get_the_ID();

$overview = get_post_meta($id, 'venue_overview', true);

$function_spaces = get_post_meta($id, 'function_spaces', true);

$amenities = get_post_meta($id, 'amenities', true);

$gallery = get_post_meta($id, 'venue_gallery', true);

$verified = get_post_meta($id, 'verified_venue', true);

$verified_text = get_post_meta($id, 'verified_text', true);

$price = get_post_meta($id, 'price', true);

$rooms = get_post_meta($id, 'rooms', true);

$per_plate = get_post_meta($post->ID, 'per_plate', true);

$rating = get_post_meta($id, 'rating', true);

$address = get_post_meta($id, 'address', true);

$phone = get_post_meta($id, 'phone', true);

$email = get_post_meta($id, 'email', true);

$website = get_post_meta($id, 'website', true);

?>

<div class="wm-layout-wrap">

    <div class="wm-layout-grid">

        <!-- LEFT -->
        <main class="wm-main">

            <?php if ($overview) : ?>

                <section class="venue-about">

                    <div class="venue-sec-head">

                        <h2 class="venue-sec-title">
                            About the venue
                        </h2>

                    </div>

                    <div class="venue-about-content">

                        <?php echo wpautop($overview); ?>

                    </div>

                </section>

            <?php endif; ?>


            <?php

            if (!empty($function_spaces)) :

                $spaces = array_filter(
                    array_map(
                        'trim',
                        explode("\n", $function_spaces)
                    )
                );

            ?>

                <section class="venue-features">

                    <h2 class="venue-sec-title">
                        Function spaces
                    </h2>

                    <div class="venue-features-grid">

                        <?php foreach ($spaces as $space) : ?>

                            <div class="venue-feature-card">

                                <span class="venue-feature-icon">
                                    <i class="fas fa-door-open"></i>
                                </span>

                                <span class="venue-feature-name">
                                    <?php echo esc_html($space); ?>
                                </span>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endif; ?>


            <?php

            if (!empty($amenities)) :

                $amenity_items = array_filter(
                    array_map(
                        'trim',
                        explode("\n", $amenities)
                    )
                );

            ?>

                <section class="venue-features">

                    <h2 class="venue-sec-title">
                        Amenities
                    </h2>

                    <div class="venue-features-grid">

                        <?php foreach ($amenity_items as $item) : ?>

                            <div class="venue-feature-card">

                                <span class="venue-feature-icon">
                                    <i class="fas fa-check"></i>
                                </span>

                                <span class="venue-feature-name">
                                    <?php echo esc_html($item); ?>
                                </span>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endif; ?>


            <?php

            if (!empty($gallery)) :

                $gallery_ids = array_filter(
                    explode(',', $gallery)
                );

            ?>

                <section class="venue-gallery">

                    <h2 class="venue-sec-title">
                        Gallery
                    </h2>

                    <div class="venue-gallery-grid">

                        <?php foreach ($gallery_ids as $image_id) : ?>

                            <div class="venue-gallery-item">

                                <?php
                                echo wp_get_attachment_image(
                                    $image_id,
                                    'large'
                                );
                                ?>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endif; ?>


            <?php if ($verified == '1') : ?>

                <section class="venue-verified">

                    <div class="venue-verified-top">

                        <div class="venue-verified-icon">

                            <i class="fas fa-shield-alt"></i>

                        </div>

                        <h3>
                            WedMate verified
                        </h3>

                    </div>

                    <p>

                        <?php echo esc_html($verified_text); ?>

                    </p>

                </section>

            <?php endif; ?>

        </main>


        <!-- RIGHT -->
        <aside class="wm-sidebar">

            <div class="venue-glance-card">

                <h3 class="venue-side-title">
                    At a glance
                </h3>

                <div class="venue-glance-list">

                    <?php if ($price) : ?>
                        <div class="venue-glance-row">
                            <span>Per plate</span>
                            <strong><?php echo esc_html($price); ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if ($rooms) : ?>
                        <div class="venue-glance-row">
                            <span>Rooms</span>
                            <strong><?php echo esc_html($rooms); ?></strong>
                        </div>
                    <?php endif; ?>

                    <?php if ($per_plate) : ?>
                        <div class="venue-glance-row">
                            <span>Per plate</span>
                            <strong><?php echo esc_html($per_plate); ?> onwards</strong>
                        </div>
                    <?php endif; ?>

                    <?php if ($rating) : ?>
                        <div class="venue-glance-row">
                            <span>Rating</span>

                            <strong class="venue-rating-text">
                                <i class="fas fa-star"></i>
                                <?php echo esc_html($rating); ?>
                            </strong>
                        </div>
                    <?php endif; ?>

                </div>

            </div>


            <div class="venue-contact-card">

                <h3 class="venue-side-title">
                    Contact
                </h3>

                <div class="venue-contact-list">

                    <?php if ($address) : ?>

                        <div class="venue-contact-item">

                            <div class="venue-contact-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>

                            <span>
                                <?php echo esc_html($address); ?>
                            </span>

                        </div>

                    <?php endif; ?>


                    <?php if ($email) : ?>

                        <div class="venue-contact-item">

                            <div class="venue-contact-icon">
                                <i class="far fa-envelope"></i>
                            </div>

                            <span>
                                <?php echo esc_html($email); ?>
                            </span>

                        </div>

                    <?php endif; ?>


                    <?php if ($phone) : ?>

                        <div class="venue-contact-item">

                            <div class="venue-contact-icon">
                                <i class="fas fa-phone-alt"></i>
                            </div>

                            <span>
                                <?php echo esc_html($phone); ?>
                            </span>

                        </div>

                    <?php endif; ?>


                    <?php if ($website) : ?>

                        <div class="venue-contact-item">

                            <div class="venue-contact-icon">
                                <i class="fas fa-globe"></i>
                            </div>

                            <span>
                                <?php echo esc_html($website); ?>
                            </span>

                        </div>

                    <?php endif; ?>

                </div>

            </div>


            <div class="venue-quote-card">

                <h3 class="venue-quote-title">
                    Get a quote
                </h3>

                <p class="venue-quote-text">
                    Tell us your wedding date and guest count — we’ll forward your enquiry directly to the venue.
                </p>

                <a href="/contact"
                   class="venue-quote-btn">

                    Send enquiry

                </a>

            </div>

        </aside>

    </div>

</div>