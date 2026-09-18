<?php get_header(); ?>

<div id="main-content" class="venues-page">

    <!-- HERO -->
    <section class="venues-hero">

        <div class="venues-hero-inner">

            <span class="venues-label">
                Wedding Venues
            </span>

            <h1 class="venues-title">
                Where the <span>aisle</span> meets the address.
            </h1>

            <p class="venues-subtitle">
                From lake palaces in Udaipur to beachfront resorts in Goa and grand banquet halls in Delhi — every venue verified for capacity, pricing and quality.
            </p>

        </div>

    </section>

    <!-- FILTERS -->
    <section class="venue-filter-section">

        <div class="venue-filter-wrap">

            <!-- SEARCH -->
            <input 
                type="text" 
                id="venue-search-input" 
                placeholder="Search venue, city or type..."
            >

            <!-- LOCATION -->
            <select id="venue-location-filter">

                <option value="">All</option>

                <?php

                $locations = get_terms([
                    'taxonomy' => 'location',
                    'hide_empty' => true
                ]);

                foreach ($locations as $location) {

                    echo '<option value="' . $location->slug . '">'
                        . $location->name .
                    '</option>';

                }

                ?>

            </select>

            <span id="venue-count"></span>

        </div>

    </section>


    <!-- GRID -->
    <section id="venue-results" class="venue-grid">

        <?php

        $query = new WP_Query([
            'post_type' => 'venues',
            'posts_per_page' => -1
        ]);

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


    <!-- CTA -->
    <section class="venues-cta">

        <div class="venues-cta-inner">

            <h2>
                List your venue on WedMate
            </h2>

            <p>
                Reach India’s most discerning wedding audience.
            </p>

            <a href="#" class="venues-cta-btn">
                Contact To Be Listed
            </a>

        </div>

    </section>

</div>


<script>

document.addEventListener("DOMContentLoaded", function () {

    const search   = document.getElementById('venue-search-input');

    const location = document.getElementById('venue-location-filter');

    const results  = document.getElementById('venue-results');

    const countEl  = document.getElementById('venue-count');


    // ================= FETCH =================
    function fetchVenues() {

        const formData = new URLSearchParams();

        formData.append('action', 'venue_filter');

        formData.append('search', search.value);

        formData.append('location', location.value);


        results.style.opacity = "0.5";


        fetch('<?php echo admin_url("admin-ajax.php"); ?>', {

            method: 'POST',

            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },

            body: formData.toString()

        })

        .then(res => res.text())

        .then(data => {

            results.innerHTML = data;

            results.style.opacity = "1";

            let count = results.querySelectorAll('.venue-card').length;

            countEl.textContent =
                count + (count === 1 ? ' venue' : ' venues');

        });

    }


    // ================= SEARCH =================
    let debounceTimer;

    search.addEventListener('keyup', function () {

        clearTimeout(debounceTimer);

        debounceTimer = setTimeout(() => {

            fetchVenues();

        }, 400);

    });


    // ================= LOCATION =================
    location.addEventListener(
    'change',
    fetchVenues
);


    // ================= INITIAL COUNT =================
    let initialCount = results.querySelectorAll('.venue-card').length;

    countEl.textContent =
        initialCount + (initialCount === 1 ? ' venue' : ' venues');

});

</script>

<?php get_footer(); ?>