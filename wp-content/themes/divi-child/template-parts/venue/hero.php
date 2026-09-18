<?php

$id = get_the_ID();


// ================= META =================

$rating = get_post_meta($id, 'rating', true);

$reviews = get_post_meta($id, 'reviews', true);

$price = get_post_meta($id, 'price', true);

$guests = get_post_meta($id, 'guests', true);

$tagline = get_post_meta($id, 'tagline', true);

$verified = get_post_meta(
    $id,
    'verified_venue',
    true
);

$location = get_the_terms($id, 'location');

?>


<!-- HERO IMAGE -->
<section class="venue-hero">

    <?php

    if (has_post_thumbnail()) {

        the_post_thumbnail('full');

    }

    ?>

</section>



<!-- FLOATING CARD -->
<section class="venue-header-card">

    <!-- LEFT -->
    <div class="venue-header-left">


        <!-- TOP -->
        <div class="venue-top-line">

    <?php if ($verified) : ?>
        <span class="venue-badge">
            <i class="fas fa-check-circle"></i>
            VERIFIED
        </span>
    <?php endif; ?>

</div>

<h1><?php the_title(); ?></h1>

<?php if ($tagline) : ?>
    <div class="venue-tagline">
        <?php echo esc_html($tagline); ?>
    </div>
<?php endif; ?>

<div class="venue-meta">

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

            <?php if ($reviews) : ?>
                (<?php echo esc_html($reviews); ?> reviews)
            <?php endif; ?>
        </span>
    <?php endif; ?>

    <?php if ($guests) : ?>
        <span>
            <i class="fas fa-users"></i>
            <?php echo esc_html($guests); ?>
        </span>
    <?php endif; ?>

    <?php if ($price) : ?>
        <span>
            <?php echo esc_html($price); ?>
        </span>
    <?php endif; ?>

</div>


    
    </div>


    <!-- RIGHT -->
    <div class="venue-header-right">

        <a href="#venue-contact"
           class="venue-btn-primary">

            <i class="fas fa-paper-plane"></i>

            Enquire Now

        </a>


        <a href="#"
           class="venue-btn-secondary">

            <i class="far fa-bookmark"></i>

            Save Venue

        </a>

    </div>

</section>