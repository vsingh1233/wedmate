<?php
function divi_child_enqueue_styles() {
    wp_enqueue_style(
        'parent-style',
        get_template_directory_uri() . '/style.css'
    );

    wp_enqueue_style(
        'child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array('parent-style'),
        wp_get_theme()->get('Version')
    );
}
add_action('wp_enqueue_scripts', 'divi_child_enqueue_styles');



// Custom Code is start from here ---------------->

/* ========================================
   GLOBAL READ TIME FUNCTION
======================================== */


function wedmate_save_read_time($post_id) {

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (get_post_type($post_id) !== 'post') {
        return;
    }

    $content = get_post_field('post_content', $post_id);

    $content = strip_shortcodes($content);

    $content = wp_strip_all_tags($content);

    $content = preg_replace('/\s+/', ' ', $content);

    $word_count = str_word_count($content);

    $read_time = max(1, ceil($word_count / 200));

    update_post_meta(
        $post_id,
        'read_time',
        $read_time
    );
}

add_action('save_post', 'wedmate_save_read_time');


function wedmate_get_read_time($post_id = null) {

    if (!$post_id) {
        $post_id = get_the_ID();
    }

    return get_post_meta(
        $post_id,
        'read_time',
        true
    );
}


function wedmate_editors_picks_shortcode() {

    ob_start();

    $args = array(
        'post_type'      => 'post',
        'posts_per_page' => 3,
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {

        echo '<div class="editor-picks">';

        while ($query->have_posts()) {
            $query->the_post();

            $category = get_the_category();
            $cat_name = !empty($category) ? $category[0]->name : '';

            echo '<div class="post-item">';

            // Image
            if (has_post_thumbnail()) {
                echo '<a href="'.get_permalink().'">';
                the_post_thumbnail('thumbnail');
                echo '</a>';
            }

            echo '<div class="content">';

            // Category
            echo '<div class="category">'.$cat_name.'</div>';

            // Title
            echo '<h4><a href="'.get_permalink().'">'.get_the_title().'</a></h4>';

           //Read Time
            $read_time = wedmate_get_read_time($query->post->ID);

            // Meta
            echo '<div class="meta">
            <svg class="read-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            '.$read_time.' min read
            </div>';

            echo '</div>';
            echo '</div>'; 
        }

        echo '</div>';

        wp_reset_postdata();
    }

    return ob_get_clean();
}

add_shortcode('editors_picks', 'wedmate_editors_picks_shortcode');



function wedmate_featured_posts() {

    ob_start();

    $args = array(
        'post_type'      => 'post',
        'posts_per_page' => 4,
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {

        echo '<div class="featured-grid">';

        $count = 1;

        while ($query->have_posts()) {
            $query->the_post();

            $category = get_the_category();
            $cat_name = !empty($category) ? $category[0]->name : '';

            
            $read_time = wedmate_get_read_time(get_the_ID());

            echo '<div class="card">';

            echo '<a href="'.get_permalink().'">';

            // Image
            if (has_post_thumbnail()) {
                the_post_thumbnail('large');
            }

            // Category (top-left)
            echo '<span class="cat">'.$cat_name.'</span>';

            // Number (top-right)
            echo '<span class="number">'.str_pad($count, 2, "0", STR_PAD_LEFT).'</span>';

            // Overlay
            echo '<div class="overlay">';

            // Title
            echo '<h3>'.get_the_title().'</h3>';

            // Meta
            echo '<div class="meta">'.$read_time.' min read</div>';

            echo '</div>'; // overlay

            echo '</a>';
            echo '</div>';

            $count++;
        }

        echo '</div>';

        wp_reset_postdata();
    }

    return ob_get_clean();
}

add_shortcode('featured_posts', 'wedmate_featured_posts');



// latest stories --------------->

// ---------------- TAG ICON FUNCTION ----------------

function wedmate_get_tag_icon($tag_slug) {

    $icons = array(

        'fact-checked' => '<svg viewBox="0 0 24 24" fill="none" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M8 12l2 2 4-4"></path>
        </svg>',

        'expert-reviewed' => '<svg viewBox="0 0 24 24" fill="none" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M12 8v4l3 2"></path>
        </svg>',
    );

    return isset($icons[$tag_slug]) ? $icons[$tag_slug] : '';
}


// ---------------- AJAX LOAD MORE ----------------
add_action('wp_ajax_wedmate_load_more', 'wedmate_load_more');
add_action('wp_ajax_nopriv_wedmate_load_more', 'wedmate_load_more');

function wedmate_load_more() {

    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;

    $args = array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'paged'          => $page,
        'posts_per_page' => 9,
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {

        while ($query->have_posts()) {
            $query->the_post();

            $category = get_the_category();
            $cat_name = (!empty($category) && isset($category[0]->name)) ? $category[0]->name : '';
            ?>

            <a href="<?php echo esc_url(get_permalink()); ?>" class="story-card">

                <div class="story-img">
                    <?php the_post_thumbnail('large'); ?>
                </div>

                <div class="meta-top">

                    <?php if ($cat_name): ?>
                        <span class="cat"><?php echo esc_html($cat_name); ?></span>
                    <?php endif; ?>

                    <?php
                    $tags = get_the_tags();
                    if (!empty($tags) && !is_wp_error($tags)) {
                        foreach ($tags as $tag) {
                            $icon = wedmate_get_tag_icon($tag->slug);
                            echo '<span class="tag">'.$icon.' '.esc_html(strtoupper($tag->name)).'</span>';
                        }
                    }
                    ?>

                </div>

                <h3>
                    <?php echo esc_html(get_the_title()); ?>
                </h3>

                <p><?php echo esc_html(get_the_excerpt()); ?></p>

                <?php
               $read_time = wedmate_get_read_time(get_the_ID());
                ?>

                <div class="meta-bottom">

                    <?php
                    $author_id = get_the_author_meta('ID');
                    $custom_image = get_user_meta($author_id, 'author_image', true);

                    if ($custom_image) {
                        echo '<img src="'.esc_url($custom_image).'" class="author-img" />';
                    } else {
                        echo get_avatar($author_id, 24);
                    }
                    ?>

                    <span class="author"><?php echo esc_html(get_the_author()); ?></span>

                    <span class="read-time">
                        <svg class="watch-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        <?php echo $read_time; ?> min read
                    </span>

                </div>

            </a>

            <?php
        }

    } else {
        echo 'end';
    }

    wp_reset_postdata();
    wp_die();
}


// ---------------- SHORTCODE ----------------
function wedmate_latest_stories() {

    ob_start();
    ?>

    <div class="latest-header">
        <h2 class="latest-title">Latest stories</h2>
    </div>

    <div id="latest-posts" class="latest-stories"></div>

    <div class="latest-footer" style="text-align:center; margin-top:30px;">
        <button id="load-more-btn">Load More Stories</button>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {

        let page = 1;
        const container = document.getElementById('latest-posts');
        const btn = document.getElementById('load-more-btn');

        function loadPosts() {

            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=wedmate_load_more&page=' + page
            })
            .then(res => res.text())
            .then(data => {

                if (data.trim() === 'end') {
                    btn.style.display = 'none';
                    return;
                }

                container.insertAdjacentHTML('beforeend', data);
            })
            .catch(err => {
                console.log('AJAX Error:', err);
            });
        }

        // INITIAL LOAD
        loadPosts();

        // LOAD MORE
        btn.addEventListener('click', function () {
            page++;
            loadPosts();
        });

    });
    </script>

    <?php

    return ob_get_clean();
}

add_shortcode('latest_stories', 'wedmate_latest_stories');


//   Vendor section Starts ----------->



/* =========================
   HOMEPAGE FEATURED VENDORS
========================= */

function wedmate_home_vendors() {

    ob_start();

    $query = new WP_Query(array(
        'post_type' => 'vendors',
        'posts_per_page' => 3
    ));

    if ($query->have_posts()) :

    echo '<div class="wm-home-vendors">';

    while ($query->have_posts()) :
        $query->the_post();

        $id = get_the_ID();

        $rating  = get_post_meta($id, 'rating', true);
        $reviews = get_post_meta($id, 'reviews', true);

        $category = get_the_terms($id, 'vendor_category');
        $location = get_the_terms($id, 'location');
        $tags     = get_the_terms($id, 'vendor_tag');

        ?>

        <div class="wm-home-card">

            <!-- TOP -->
            <div class="wm-home-top">

                <?php
                if (!empty($tags) && !is_wp_error($tags)) :

                    $tag = strtoupper($tags[0]->name);
                ?>

                   <span class="wm-badge">
   				   <i class="fa-regular fa-circle-check"></i>
    			   <?php echo esc_html($tag); ?>
				   </span>

                <?php endif; ?>

                <?php if ($rating) : ?>

                   <div class="wm-rating">

    <i class="fas fa-star"></i>

    <?php echo esc_html($rating); ?>

    <?php if ($reviews) : ?>
        <span>(<?php echo esc_html($reviews); ?>)</span>
    <?php endif; ?>

</div>

                <?php endif; ?>

            </div>

            <!-- TITLE -->
            <h3 class="wm-title">
                <?php the_title(); ?>
            </h3>

            <!-- CATEGORY -->
            <?php if (!empty($category) && !is_wp_error($category)) : ?>

                <div class="wm-category">
                    <?php echo esc_html(strtoupper($category[0]->name)); ?>
                </div>

            <?php endif; ?>

            <!-- LOCATION -->
            <?php if (!empty($location) && !is_wp_error($location)) : ?>

                <div class="wm-location">
    <i class="fa-solid fa-location-dot"></i>
    <?php echo esc_html($location[0]->name); ?>
</div>

            <?php endif; ?>

            <!-- BUTTON -->
            <a href="<?php the_permalink(); ?>" class="wm-btn">
                View Profile
            </a>

        </div>

        <?php

    endwhile;

    echo '</div>';

    wp_reset_postdata();

    endif;

    return ob_get_clean();
}

add_shortcode('home_vendors', 'wedmate_home_vendors');




//Vendor section ends here -------->


// Venue section code starts from here for home page ------->

function wedmate_home_venues() {

    ob_start();

    $query = new WP_Query(array(
        'post_type'      => 'venues',
        'posts_per_page' => 3
    ));

    if ($query->have_posts()) :

        echo '<div class="wm-home-venues">';

        while ($query->have_posts()) :
            $query->the_post();

            $id = get_the_ID();

            $rating  = get_post_meta($id, 'rating', true);
            $price   = get_post_meta($id, 'price', true);
            $guests  = get_post_meta($id, 'guests', true);

            $location = get_the_terms($id, 'location');
            ?>

            <a href="<?php the_permalink(); ?>" class="wm-venue-card">

                <!-- IMAGE -->
                <div class="wm-venue-img">

                    <?php if (has_post_thumbnail()) : ?>
                        <?php the_post_thumbnail('large'); ?>
                    <?php endif; ?>

                    <!-- VERIFIED -->
                    <span class="wm-venue-badge">
                        <i class="fa-regular fa-circle-check"></i>
                        VERIFIED
                    </span>

                    <!-- RATING -->
                    <?php if ($rating) : ?>

                        <div class="wm-venue-rating">

                            <i class="fas fa-star"></i>

                            <?php echo esc_html($rating); ?>

                        </div>

                    <?php endif; ?>

                </div>


                <!-- CONTENT -->
                <div class="wm-venue-content">

                    <!-- TITLE -->
                    <h3 class="wm-venue-title">
                        <?php the_title(); ?>
                    </h3>


                    <!-- META -->
                    <div class="wm-venue-meta">

                        <?php if (!empty($location) && !is_wp_error($location)) : ?>

                            <div class="wm-venue-location">

                                <i class="fa-solid fa-location-dot"></i>

                                <?php echo esc_html($location[0]->name); ?>

                            </div>

                        <?php endif; ?>


                        <?php if ($guests) : ?>

                            <div class="wm-venue-guests">

                                <i class="fa-solid fa-users"></i>

                                <?php echo esc_html($guests); ?>

                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- PRICE -->
                    <?php if ($price) : ?>

                        <div class="wm-venue-price">

                            ₹<?php echo esc_html($price); ?> onwards

                        </div>

                    <?php endif; ?>

                </div>

            </a>

            <?php

        endwhile;

        echo '</div>';

        wp_reset_postdata();

    endif;

    return ob_get_clean();
}

add_shortcode('home_venues', 'wedmate_home_venues');


// Venue section code ends here for home page ------->


// Author custom code

// ADD FIELDS IN USER PROFILE

add_action('show_user_profile', 'wedmate_author_fields');
add_action('edit_user_profile', 'wedmate_author_fields');

function wedmate_author_fields($user) {
?>

<h3>Author Extra Info</h3>

<table class="form-table">

<tr>
    <th><label>Author Image URL</label></th>
    <td>
        <input type="text" name="author_image" value="<?php echo esc_attr(get_user_meta($user->ID, 'author_image', true)); ?>" class="regular-text" />
        <p class="description">Paste image URL (from Media Library)</p>
    </td>
</tr>

<tr>
    <th><label>Designation</label></th>
    <td>
        <input type="text" name="author_role" value="<?php echo esc_attr(get_user_meta($user->ID, 'author_role', true)); ?>" class="regular-text" />
        <p class="description">Example: Editor-in-Chief</p>
    </td>
</tr>

<tr>
    <th><label>Experience (Years)</label></th>
    <td>
        <input type="text" name="author_experience" value="<?php echo esc_attr(get_user_meta($user->ID, 'author_experience', true)); ?>" class="regular-text" />
        <p class="description">Example: 10+</p>
    </td>
</tr>

<tr>
    <th><label>Verified Author</label></th>
    <td>
        <input type="checkbox" name="author_verified" value="1" <?php checked(get_user_meta($user->ID, 'author_verified', true), 1); ?> />
        <span>Mark as verified</span>
    </td>
</tr>
	
	<tr><th><label>Instagram</label></th>
<td><input type="text" name="author_instagram" value="<?php echo esc_attr(get_user_meta($user->ID, 'author_instagram', true)); ?>" class="regular-text"></td></tr>

<tr><th><label>LinkedIn</label></th>
<td><input type="text" name="author_linkedin" value="<?php echo esc_attr(get_user_meta($user->ID, 'author_linkedin', true)); ?>" class="regular-text"></td></tr>

<tr><th><label>YouTube</label></th>
<td><input type="text" name="author_youtube" value="<?php echo esc_attr(get_user_meta($user->ID, 'author_youtube', true)); ?>" class="regular-text"></td></tr>

<tr><th><label>Twitter</label></th>
<td><input type="text" name="author_twitter" value="<?php echo esc_attr(get_user_meta($user->ID, 'author_twitter', true)); ?>" class="regular-text"></td></tr>

<tr><th><label>Pinterest</label></th>
<td><input type="text" name="author_pinterest" value="<?php echo esc_attr(get_user_meta($user->ID, 'author_pinterest', true)); ?>" class="regular-text"></td></tr>

<tr><th><label>Facebook</label></th>
<td><input type="text" name="author_facebook" value="<?php echo esc_attr(get_user_meta($user->ID, 'author_facebook', true)); ?>" class="regular-text"></td></tr>
	
<tr>
    <th><label>Expertise Areas</label></th>
    <td>
        <input type="text" name="author_expertise"
        value="<?php echo esc_attr(get_user_meta($user->ID, 'author_expertise', true)); ?>"
        class="regular-text" />

        <p class="description">Add comma separated (e.g. Couture, Jewellery, Bridal Beauty)</p>
    </td>
</tr>
	
	<tr>
    <th><label>Credentials</label></th>
    <td>
        <textarea name="author_credentials" rows="4" class="regular-text"><?php echo esc_textarea(get_user_meta($user->ID, 'author_credentials', true)); ?></textarea>

        <p class="description">Add one per line</p>
    </td>
</tr>

</table>

<?php
}


// SAVE FIELDS
add_action('personal_options_update', 'wedmate_save_author_fields');
add_action('edit_user_profile_update', 'wedmate_save_author_fields');

function wedmate_save_author_fields($user_id) {

    if (!current_user_can('edit_user', $user_id)) return false;

    update_user_meta($user_id, 'author_image', sanitize_text_field($_POST['author_image']));
    update_user_meta($user_id, 'author_role', sanitize_text_field($_POST['author_role']));
    update_user_meta($user_id, 'author_experience', sanitize_text_field($_POST['author_experience']));
	update_user_meta($user_id, 'author_verified', isset($_POST['author_verified']) ? 1 : 0);
	update_user_meta($user_id, 'author_instagram', sanitize_text_field($_POST['author_instagram']));
	update_user_meta($user_id, 'author_linkedin', sanitize_text_field($_POST['author_linkedin']));
	update_user_meta($user_id, 'author_youtube', sanitize_text_field($_POST['author_youtube']));
	update_user_meta($user_id, 'author_twitter', sanitize_text_field($_POST['author_twitter']));
	update_user_meta($user_id, 'author_pinterest', sanitize_text_field($_POST['author_pinterest']));
	update_user_meta($user_id, 'author_facebook', sanitize_text_field($_POST['author_facebook']));
	update_user_meta($user_id, 'author_expertise', sanitize_text_field($_POST['author_expertise']));
    update_user_meta($user_id, 'author_credentials', sanitize_textarea_field($_POST['author_credentials']));
}


// Meet our editor section

function wedmate_author_cards() {

    ob_start();

    $authors = get_users(array(
    'role__in' => array('author', 'editor'), 
    'number'   => 4,
));

    echo '<div class="author-grid">';

    foreach ($authors as $user) {

        $author_id = $user->ID;

        $image = get_user_meta($author_id, 'author_image', true);
        $role  = get_user_meta($author_id, 'author_role', true);
        $exp   = get_user_meta($author_id, 'author_experience', true);
        $verified = get_user_meta($author_id, 'author_verified', true);

        $post_count = count_user_posts($author_id);

        echo '<a href="'.esc_url(get_author_posts_url($author_id)).'" class="author-card-link">';
		echo '<div class="author-card">';

        // IMAGE
        echo '<div class="author-img-wrap">';
        if ($image) {
            echo '<img src="'.esc_url($image).'" />';
        } else {
            echo get_avatar($author_id, 200);
        }

        // VERIFIED BADGE
        if ($verified) {
    echo '<span class="verified-badge">
        <span class="icon">
             <svg viewBox="0 0 512 512" class="verified-icon">
                <path d="M256 512a256 256 0 1 1 0-512 256 256 0 1 1 0 512zm0-464a208 208 0 1 0 0 416 208 208 0 1 0 0-416zm70.7 121.9c7.8-10.7 22.8-13.1 33.5-5.3 10.7 7.8 13.1 22.8 5.3 33.5L243.4 366.1c-4.1 5.7-10.5 9.3-17.5 9.8-7 .5-13.9-2-18.8-6.9l-55.9-55.9c-9.4-9.4-9.4-24.6 0-33.9s24.6-9.4 33.9 0l36 36 105.6-145.2z"/>
            </svg>
        </span>
        VERIFIED
    </span>';
}

echo '</div>';
		

        // NAME
        echo '<h3>'.esc_html($user->display_name).'</h3>';

        // ROLE
        echo '<div class="role">'.esc_html($role).'</div>';

        // STATS
        echo '<div class="author-meta">';
        echo '<span>'.$exp.' yrs</span>';
        echo '<span>•</span>';
        echo '<span>'.$post_count.' articles</span>';
        echo '</div>';

        echo '</div>';
		echo '</a>';
    }

    echo '</div>';
	

    return ob_get_clean();
}

add_shortcode('author_cards', 'wedmate_author_cards');

function my_category_stats() {
    if (is_category()) {
        $category = get_queried_object();
        $count = $category->count;

        return '<span class="cat-count">'.$count.' stories</span>
                <span class="cat-sep">•</span>
                <span class="cat-updated">Updated weekly</span>';
    }
    return '';
}
add_shortcode('cat_stats', 'my_category_stats');



// Category Page code 



function wedmate_featured_hero() {

    ob_start();

    // GET CURRENT CATEGORY
    $category = get_queried_object();

    $args = array(
        'post_type'      => 'post',
        'posts_per_page' => 1,
    );

    //  FILTER BY CATEGORY 
    if (is_category() && !empty($category->term_id)) {
        $args['cat'] = $category->term_id;
    }

    $query = new WP_Query($args);

    if ($query->have_posts()) {

        echo '<div class="featured-hero">';

        while ($query->have_posts()) {
            $query->the_post();

            // READ TIME 
           $read_time = wedmate_get_read_time(get_the_ID());

            echo '<a href="'.get_permalink().'" class="hero-card">';

                echo '<div class="hero-image">';
                if (has_post_thumbnail()) {
                    echo get_the_post_thumbnail(get_the_ID(), 'large');
                }
                echo '</div>';

                // CONTENT
                echo '<div class="hero-content">';

                    echo '<span class="badge">FEATURED</span>';

                    echo '<h2 class="hero-title">'.get_the_title().'</h2>';

                    echo '<p class="hero-excerpt">'.get_the_excerpt().'</p>';

                    echo '<div class="hero-meta">';

                       $author_id = get_the_author_meta('ID');
						$custom_image = get_user_meta($author_id, 'author_image', true);

						if ($custom_image) {
   						 echo '<img src="'.esc_url($custom_image).'" class="author-img" />';
						  } else {
   						 echo get_avatar($author_id, 24);
								}

						echo '<span class="author">'.get_the_author().'</span>';

                        echo '<span class="dot">•</span>';
                        echo '<span class="read-time">

    <svg class="watch-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"></circle>
        <polyline points="12 6 12 12 16 14"></polyline>
    </svg>

    '.$read_time.' min

</span>';

                    echo '</div>';

                echo '</div>';

            echo '</a>';
        }

        echo '</div>';

        wp_reset_postdata();
    }

    return ob_get_clean();
}

add_shortcode('featured_hero', 'wedmate_featured_hero');


// Real wedding section of category page 

add_action('wp_ajax_wedmate_load_more_grid', 'wedmate_load_more_grid');
add_action('wp_ajax_nopriv_wedmate_load_more_grid', 'wedmate_load_more_grid');

function wedmate_load_more_grid() {

    $page    = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $exclude = isset($_POST['exclude']) ? intval($_POST['exclude']) : 0;
    $sort    = isset($_POST['sort']) ? $_POST['sort'] : 'latest';

    $args = array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 3,
        'paged'          => $page,
    );

    if ($exclude) {
        $args['post__not_in'] = array($exclude);
    }

    // SORTING
    if ($sort === 'popular') {
        $args['orderby'] = 'comment_count';
    } else {
        $args['orderby'] = 'date';
        $args['order']   = 'DESC';
    }

    $query = new WP_Query($args);

    if ($query->have_posts()) {

        while ($query->have_posts()) {
            $query->the_post();

            // READ TIME
           $read_time = wedmate_get_read_time(get_the_ID());
            ?>

            <a href="<?php echo esc_url(get_permalink()); ?>" class="story-card">

                <div class="story-img">
                    <?php the_post_thumbnail('large'); ?>
                </div>

                <div class="meta-top">
                    <?php
                    $tags = get_the_tags();
                    if (!empty($tags)) {
                        foreach ($tags as $tag) {
                            $icon = wedmate_get_tag_icon($tag->slug);
                            echo '<span class="tag">'.$icon.' '.esc_html(strtoupper($tag->name)).'</span>';
                        }
                    }
                    ?>
                </div>

                <h3 class="story-title"><?php echo esc_html(get_the_title()); ?></h3>

                <p class="story-excerpt"><?php echo esc_html(get_the_excerpt()); ?></p>

                <div class="meta-bottom">
                    <?php
                    $author_id = get_the_author_meta('ID');
                    $custom_image = get_user_meta($author_id, 'author_image', true);

                    if ($custom_image) {
                        echo '<img src="'.esc_url($custom_image).'" class="author-img" />';
                    } else {
                        echo get_avatar($author_id, 24);
                    }
                    ?>

                    <span class="author"><?php echo esc_html(get_the_author()); ?></span>
                    
                    <span class="time"><?php echo $read_time; ?> min</span>
                </div>

            </a>

            <?php
        }

    } else {
        echo 'end';
    }

    wp_reset_postdata();
    wp_die();
}


function wedmate_hybrid_grid() {

    ob_start();

    // FEATURED POST ID (exclude)
    $featured_id = 0;
    $cat_name = '';

    if (is_category()) {
        $cat = get_queried_object();
        $cat_name = $cat->name;

        $featured_query = new WP_Query(array(
            'post_type'      => 'post',
            'posts_per_page' => 1,
            'cat'            => $cat->term_id
        ));

        if ($featured_query->have_posts()) {
            $featured_query->the_post();
            $featured_id = get_the_ID();
        }

        wp_reset_postdata();
    }

    // FILTER
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest';
    ?>

    <!-- HEADER -->
    <div class="grid-header">
        <h2 class="grid-title">
            All <?php echo esc_html(strtolower($cat_name)); ?> stories
        </h2>

        <div class="grid-filter">
            <a href="?sort=latest" class="<?php echo ($sort=='latest') ? 'active' : ''; ?>">Latest</a>
            <a href="?sort=popular" class="<?php echo ($sort=='popular') ? 'active' : ''; ?>">Popular</a>
        </div>
    </div>

    <!-- GRID -->
    <div id="latest-posts" class="category-grid"></div>

    <!-- PAGINATION UI -->
    <div class="load-more-wrap">
    <button id="load-more-posts">
        Load More Stories
    </button>
</div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {

    let page = 1;
    const container = document.getElementById('latest-posts');
    const loadBtn = document.getElementById('load-more-posts');

    function loadPosts() {

        loadBtn.innerText = "Loading...";

        fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body:
                'action=wedmate_load_more_grid&page=' + page +
                '&exclude=<?php echo $featured_id; ?>' +
                '&sort=<?php echo $sort; ?>'
        })
        .then(res => res.text())
        .then(data => {

            if (data.trim() === 'end') {

                loadBtn.innerText = "No More Stories";
                loadBtn.disabled = true;
                return;
            }

            container.insertAdjacentHTML('beforeend', data);

            loadBtn.innerText = "Load More Stories";

            page++;
        });
    }

    // Initial load
    loadPosts();

    // Load more click
    loadBtn.addEventListener('click', loadPosts);

});
    </script>

    <?php

    return ob_get_clean();
}

add_shortcode('hybrid_grid', 'wedmate_hybrid_grid');


// other category section of category page

function wedmate_category_pills() {

    ob_start();

    // GET ALL CATEGORIES
    $categories = get_categories(array(
    'hide_empty' => true,
    'exclude'    => array(get_cat_ID('Uncategorized')),
));
    ?>

    <div class="category-pills-section">

        <h2 class="category-pills-title">Explore other categories</h2>

        <div class="category-pills">

            <?php foreach ($categories as $cat) : ?>

                <a href="<?php echo esc_url(get_category_link($cat->term_id)); ?>" class="pill">

                    <?php echo esc_html($cat->name); ?>

                    <span class="count">(<?php echo $cat->count; ?>)</span>

                </a>

            <?php endforeach; ?>

        </div>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode('category_pills', 'wedmate_category_pills');



//Author page code starts from here ------------>

function wedmate_author_hero() {

    ob_start();

    $author_id = get_queried_object_id();

    $image     = get_user_meta($author_id, 'author_image', true);
    $role      = get_user_meta($author_id, 'author_role', true);
    $verified  = get_user_meta($author_id, 'author_verified', true);

    $insta = get_user_meta($author_id, 'author_instagram', true);
    $linkd = get_user_meta($author_id, 'author_linkedin', true);
    $yt    = get_user_meta($author_id, 'author_youtube', true);
    $tw    = get_user_meta($author_id, 'author_twitter', true);
    $pin   = get_user_meta($author_id, 'author_pinterest', true);
    $fb    = get_user_meta($author_id, 'author_facebook', true);
    ?>

    <div class="author-hero">

        <div class="author-image-wrap">

            <?php if ($image): ?>
                <img src="<?php echo esc_url($image); ?>" class="author-image" />
            <?php else: ?>
                <?php echo get_avatar($author_id, 160); ?>
            <?php endif; ?>

            <?php if ($verified): ?>
                <span class="verified-badge-a">
    <i class="fa-regular fa-circle-check"></i>
    Verified
</span>
            <?php endif; ?>

        </div>

        <div class="author-content">

            <?php if ($role): ?>
                <span class="author-role"><?php echo esc_html($role); ?></span>
            <?php endif; ?>

            <h1 class="author-name">
                <?php echo esc_html(get_the_author_meta('display_name', $author_id)); ?>
            </h1>

            <p class="author-bio">
                <?php echo esc_html(get_the_author_meta('description', $author_id)); ?>
            </p>

           <div class="author-socials">

<?php if ($insta): ?>
<a href="<?php echo esc_url($insta); ?>" target="_blank">
    <i class="fab fa-instagram"></i>
</a>
<?php endif; ?>

<?php if ($linkd): ?>
<a href="<?php echo esc_url($linkd); ?>" target="_blank">
    <i class="fab fa-linkedin-in"></i>
</a>
<?php endif; ?>

<?php if ($yt): ?>
<a href="<?php echo esc_url($yt); ?>" target="_blank">
    <i class="fab fa-youtube"></i>
</a>
<?php endif; ?>

<?php if ($tw): ?>
<a href="<?php echo esc_url($tw); ?>" target="_blank">
    <i class="fab fa-twitter"></i>
</a>
<?php endif; ?>

<?php if ($pin): ?>
<a href="<?php echo esc_url($pin); ?>" target="_blank">
    <i class="fab fa-pinterest-p"></i>
</a>
<?php endif; ?>

<?php if ($fb): ?>
<a href="<?php echo esc_url($fb); ?>" target="_blank">
    <i class="fab fa-facebook-f"></i>
</a>
<?php endif; ?>

</div>

        </div>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode('author_hero', 'wedmate_author_hero');


// Experience section 

function wedmate_author_expertise_section() {

    ob_start();

    $author_id = get_queried_object_id();

    // GET DATA
    $experience  = get_user_meta($author_id, 'author_experience', true);
    $expertise   = get_user_meta($author_id, 'author_expertise', true);
    $credentials = get_user_meta($author_id, 'author_credentials', true);

    // POSTS COUNT
    $post_count = count_user_posts($author_id);

    // EXPERTISE ARRAY
    $expertise_list = array_filter(array_map('trim', explode(',', $expertise)));

    // CREDENTIALS ARRAY
    $credentials_list = array_filter(array_map('trim', explode("\n", $credentials)));
    ?>

    <!-- TOP STATS -->
    <div class="author-stats">

        <div class="stat-box">
            <i class="fas fa-calendar"></i>
            <div>
                <strong><?php echo esc_html($experience); ?></strong>
                <span>Years Experience</span>
            </div>
        </div>

        <div class="stat-box">
            <i class="fas fa-book-open"></i>
            <div>
                <strong><?php echo esc_html($post_count); ?></strong>
                <span>Articles Published</span>
            </div>
        </div>

        <div class="stat-box">
            <i class="fas fa-award"></i>
            <div>
                <strong><?php echo count($expertise_list); ?></strong>
                <span>Expertise Areas</span>
            </div>
        </div>

    </div>


    <!-- MAIN GRID -->
    <div class="author-expertise-grid">

        <!-- LEFT -->
        <div class="expertise-left">

            <h3>Areas of expertise</h3>

            <div class="expertise-tags">
                <?php foreach ($expertise_list as $item): ?>
                    <span><?php echo esc_html($item); ?></span>
                <?php endforeach; ?>
            </div>

        </div>

        <!-- RIGHT -->
        <div class="expertise-right">

            <h3>Credentials</h3>

            <ul>
                <?php foreach ($credentials_list as $item): ?>
                    <li>
                        <i class="fas fa-check-circle"></i>
                        <?php echo esc_html($item); ?>
                    </li>
                <?php endforeach; ?>
            </ul>

        </div>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode('author_expertise_section', 'wedmate_author_expertise_section');


// Author Stories section

function wedmate_author_stories() {

    ob_start();

    $author_id = get_queried_object_id();

    // QUERY POSTS
    $args = array(
        'post_type'      => 'post',
        'author'         => $author_id,
        'posts_per_page' => 6,
    );

    $query = new WP_Query($args);
    $total = $query->found_posts;
    ?>

    <!-- TITLE -->
    <div class="author-stories-header">
        <h2>
            Stories by <?php echo esc_html(get_the_author_meta('display_name', $author_id)); ?>
            <span>(<?php echo esc_html($total); ?>)</span>
        </h2>
    </div>

    <!-- GRID -->
    <div class="author-stories-grid">

    <?php if ($query->have_posts()): ?>
        <?php while ($query->have_posts()): $query->the_post(); ?>

            <a href="<?php the_permalink(); ?>" class="author-story-card">

                <div class="author-story-img">
                    <?php the_post_thumbnail('large'); ?>
                </div>

                <div class="author-story-meta">

                    <?php
                    $cat = get_the_category();
                    if (!empty($cat)) {
                        echo '<span class="author-cat">'.esc_html($cat[0]->name).'</span>';
                    }
                    ?>

                </div>

                <h3><?php the_title(); ?></h3>

                <div class="author-story-bottom">
                    <span><?php echo get_the_date('M d, Y'); ?></span>
                    <span class="dot">•</span>

                    <?php
                    $read_time = wedmate_get_read_time(get_the_ID());
                    ?>

                     <span class="read-time">

        <svg class="watch-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12 6 12 12 16 14"></polyline>
        </svg>

        <?php echo $read_time; ?> min

    </span>
                </div>

            </a>

        <?php endwhile; wp_reset_postdata(); ?>
    <?php endif; ?>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode('author_stories', 'wedmate_author_stories');


// contact form code 

add_filter('wpcf7_autop_or_not', '__return_false');



// Post page code starts from here --------------->


function wedmate_post_meta_bar() {

    ob_start();

    $author_id = get_the_author_meta('ID');

    // AUTHOR
    $name  = get_the_author();
    $role  = get_user_meta($author_id, 'author_role', true);
    $image = get_user_meta($author_id, 'author_image', true);

    // DATES
    $published = get_the_date('M d, Y');
    $updated   = get_the_modified_date('M d, Y');

    // READ TIME
   $read_time = wedmate_get_read_time(get_the_ID());
    ?>

    <div class="post-meta-bar">

    <!-- LEFT: AUTHOR -->
    <a href="<?php echo esc_url(get_author_posts_url($author_id)); ?>" 
       class="post-meta-author"
       rel="author">

        <div class="post-meta-avatar">
            <?php
            if ($image) {
                echo '<img src="'.esc_url($image).'" />';
            } else {
                echo get_avatar($author_id, 40);
            }
            ?>
        </div>

        <div class="post-meta-author-info">
            <span class="post-meta-name"><?php echo esc_html($name); ?></span>
            <?php if ($role): ?>
                <span class="post-meta-role"><?php echo esc_html($role); ?></span>
            <?php endif; ?>
        </div>

    </a>

    <!-- RIGHT: DATES -->
    <div class="post-meta-dates">

        <!-- Published -->
        <div class="post-meta-row">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>

            <span>Published <?php echo esc_html($published); ?></span>
        </div>

        <!-- Updated + Read -->
        <div class="post-meta-row">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>

            <span>
                Updated <?php echo esc_html($updated); ?> · <?php echo $read_time; ?> min read
            </span>
        </div>

    </div>

</div>

    <?php

    return ob_get_clean();
}

add_shortcode('post_meta_bar', 'wedmate_post_meta_bar');

/* =========================
   ARTICLE TABLE OF CONTENTS
========================= */


/* AUTO ADD IDS TO H2 HEADINGS */
function wedmate_add_heading_ids($content) {

    if (
        is_admin() ||
        !is_singular()
    ) {
        return $content;
    }

    /* FIX: prevent fatal error on empty content */
    if (empty($content) || trim($content) === '') {
        return $content;
    }

    libxml_use_internal_errors(true);

    $dom = new DOMDocument();

    $dom->loadHTML(
        '<?xml encoding="utf-8" ?>' . $content,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );

    $headings = $dom->getElementsByTagName('h2');

    if (!$headings->length) {
        return $content;
    }

    $used_ids = array();

    foreach ($headings as $heading) {

        $text = trim($heading->textContent);

        if (empty($text)) {
            continue;
        }

        /* KEEP EXISTING ID */
        if ($heading->hasAttribute('id')) {

            $id = $heading->getAttribute('id');

        } else {

            /* CREATE CLEAN ID */
            $id = sanitize_title($text);

            /* HANDLE DUPLICATES */
            $original_id = $id;
            $counter = 2;

            while (in_array($id, $used_ids)) {

                $id = $original_id . '-' . $counter;

                $counter++;
            }

            $heading->setAttribute('id', $id);
        }

        $used_ids[] = $id;
    }

    return $dom->saveHTML();
}

add_filter('the_content', 'wedmate_add_heading_ids', 20);


/* ========================================
   TOC SHORTCODE
======================================== */

function wedmate_article_toc() {

    global $post;

    if (!$post) {
        return '';
    }

    $content = $post->post_content;

    if (empty($content)) {
        return '';
    }

    libxml_use_internal_errors(true);

    $dom = new DOMDocument();

    $dom->loadHTML(
        mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'),
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );

    $headings = $dom->getElementsByTagName('h2');

    if (!$headings->length) {
        return '';
    }

    $used_ids = array();

    ob_start();

    echo '<div class="ws-toc-box">';

    echo '<h4 class="ws-toc-title">
            In this article
          </h4>';

    foreach ($headings as $heading) {

        $text = trim($heading->textContent);

        if (empty($text)) {
            continue;
        }

        /* GENERATE SAME IDS */
        $id = sanitize_title($text);

        $original_id = $id;
        $counter = 2;

        while (in_array($id, $used_ids)) {

            $id = $original_id . '-' . $counter;

            $counter++;
        }

        $used_ids[] = $id;

        echo '<a href="#' . esc_attr($id) . '" class="ws-toc-link">'
            . esc_html($text) .
        '</a>';
    }

    echo '</div>';

    return ob_get_clean();
}

add_shortcode('article_toc', 'wedmate_article_toc');



//  Author section post page ------------------>
//  


function wedmate_author_about_block() {

    ob_start();

    $author_id = get_the_author_meta('ID');

    // DATA (you already store these)
    $name        = get_the_author_meta('display_name', $author_id);
    $bio         = get_the_author_meta('description', $author_id);
    $image       = get_user_meta($author_id, 'author_image', true);
    $role        = get_user_meta($author_id, 'author_role', true);
    $experience  = get_user_meta($author_id, 'author_experience', true);
    $expertise   = get_user_meta($author_id, 'author_expertise', true);

    // expertise array
    $expertise_list = array_filter(array_map('trim', explode(',', $expertise)));
    ?>

    <div class="post-author-about">

        <div class="post-author-about-left">
            <?php if ($image): ?>
                <img src="<?php echo esc_url($image); ?>" />
            <?php else: ?>
                <?php echo get_avatar($author_id, 120); ?>
            <?php endif; ?>
        </div>

        <div class="post-author-about-right">

            <span class="post-author-label">About the author</span>

            <h3 class="post-author-name">
    <a href="<?php echo esc_url(get_author_posts_url($author_id)); ?>" rel="author">
        <?php echo esc_html($name); ?>
    </a>
</h3>

            <div class="post-author-meta">
                <?php if ($role): ?>
                    <span><?php echo esc_html($role); ?></span>
                <?php endif; ?>

                <?php if ($experience): ?>
                    <span> · <?php echo esc_html($experience); ?> years</span>
                <?php endif; ?>
            </div>

            <p class="post-author-bio">
                <?php echo esc_html($bio); ?>
            </p>

            <?php if (!empty($expertise_list)): ?>
                <div class="post-author-tags">
                    <?php foreach ($expertise_list as $item): ?>
                        <span><?php echo esc_html($item); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode('author_about', 'wedmate_author_about_block');


// Comment section post page 

function wedmate_reader_comments() {

    ob_start();

    $post_id = get_the_ID();

    $all_comments = get_comments(array(
        'post_id' => $post_id,
        'status'  => 'approve',
    ));

    $total_comments = count($all_comments);
    ?>

    <div class="post-comments-wrap">

        <!-- TITLE -->
        <h2 class="post-comments-title">
            Reader conversations <span>(<?php echo $total_comments; ?>)</span>
        </h2>

        <!-- COMMENTS -->
        <div class="post-comments-list">

            <?php foreach ($all_comments as $index => $comment): ?>

                <div class="post-comment-card <?php echo ($index >= 2) ? 'hidden-comment' : ''; ?>">

                    <!-- AVATAR -->
                    <div class="post-comment-avatar">
                        <?php echo strtoupper(substr($comment->comment_author, 0, 1)); ?>
                    </div>

                    <!-- CONTENT -->
                    <div class="post-comment-content">

                        <div class="post-comment-meta">
                            <strong><?php echo esc_html($comment->comment_author); ?></strong>
                            <span>· <?php echo human_time_diff(strtotime($comment->comment_date), current_time('timestamp')); ?> ago</span>
                        </div>

                        <p><?php echo esc_html($comment->comment_content); ?></p>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

        <!-- BUTTON -->
        <?php if ($total_comments > 2): ?>
            <div class="post-comments-footer">
                <button class="post-comments-btn">Load all comments</button>
            </div>
        <?php endif; ?>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode('reader_comments', 'wedmate_reader_comments');


// Related section post page 

function wedmate_related_posts() {

    ob_start();

    $post_id = get_the_ID();

    // Get categories
    $categories = wp_get_post_categories($post_id);

    $args = array(
        'post_type'      => 'post',
        'posts_per_page' => 3,
        'post__not_in'   => array($post_id),
    );

    // If category exists → filter
    if (!empty($categories)) {
        $args['category__in'] = $categories;
    }

    $query = new WP_Query($args);

    //  FALLBACK (if no related found)
    if (!$query->have_posts()) {

        $args = array(
            'post_type'      => 'post',
            'posts_per_page' => 3,
            'post__not_in'   => array($post_id),
            'orderby'        => 'date',
        );

        $query = new WP_Query($args);
    }

    if ($query->have_posts()) {

        echo '<div class="post-related-wrap">';
        echo '<div class="post-related-grid">';

        while ($query->have_posts()) {
            $query->the_post();

            $cat = get_the_category();
            $cat_name = !empty($cat) ? $cat[0]->name : '';

            echo '<a href="'.get_permalink().'" class="post-related-card">';

            echo '<div class="post-related-img">';
            the_post_thumbnail('large');
            echo '</div>';

            echo '<span class="post-related-cat">'.$cat_name.'</span>';
            echo '<h3 class="post-related-title">'.get_the_title().'</h3>';

            echo '</a>';
        }

        echo '</div>';
        echo '</div>';

        wp_reset_postdata();
    }

    return ob_get_clean();
}

add_shortcode('related_posts', 'wedmate_related_posts');



// Homepage Hero Section Code Starts From Here ---------->


function wedmate_cover_story_hero() {

    ob_start();

    $args = array(
        'post_type'      => 'post',
        'posts_per_page' => 1,
        'tag'            => 'cover-story',
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {

        while ($query->have_posts()) {
            $query->the_post();

            $thumb = get_the_post_thumbnail_url(get_the_ID(), 'full');

            $category = get_the_category();
            $cat_name = (!empty($category)) ? $category[0]->name : '';

            $tags = get_the_tags();

            // Read time
            $read_time = wedmate_get_read_time(get_the_ID());

            ?>

            <div class="ws-hero-wrap">

                <a href="<?php the_permalink(); ?>" class="ws-hero-card">

                    <!-- BG IMAGE -->
                    <div class="ws-hero-bg" style="background-image:url('<?php echo esc_url($thumb); ?>')"></div>

                    <!-- OVERLAY -->
                    <div class="ws-hero-overlay"></div>

                  <div class="ws-hero-top">

    <?php
    $tags = get_the_tags();

    if (!empty($tags)) {

        // SORT → cover-story first
        usort($tags, function($a, $b) {

            if ($a->slug === 'cover-story') return -1;
            if ($b->slug === 'cover-story') return 1;

            return 0;
        });

        // PRINT TAGS
        foreach ($tags as $tag) {

            if ($tag->slug === 'cover-story') {
                echo '<span class="ws-hero-tag ws-cover">'
                    .esc_html(strtoupper($tag->name)).
                '</span>';
            } else {
                echo '<span class="ws-hero-tag">'
                    .esc_html(strtoupper($tag->name)).
                '</span>';
            }

        }
    }

    // CATEGORY LAST
    if ($cat_name) {
        echo '<span class="ws-hero-cat">'.esc_html($cat_name).'</span>';
    }
    ?>

</div>

                    <!-- CONTENT -->
                    <div class="ws-hero-content">

                        <h1 class="ws-hero-title"><?php the_title(); ?></h1>

                        <p class="ws-hero-excerpt"><?php echo wp_trim_words(get_the_excerpt(), 20); ?></p>

                        <div class="ws-hero-meta">

                            <?php
                            $author_id = get_the_author_meta('ID');
                            $custom_image = get_user_meta($author_id, 'author_image', true);

                            if ($custom_image) {
                                echo '<img src="'.esc_url($custom_image).'" class="ws-hero-author-img" />';
                            } else {
                                echo get_avatar($author_id, 28);
                            }
                            ?>

                            <span class="ws-hero-author">
                                <?php echo esc_html(get_the_author()); ?>
                            </span>

                            <span class="ws-dot">•</span>

                            <span class="ws-hero-read">

    <svg class="ws-watch-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"></circle>
        <polyline points="12 6 12 12 16 14"></polyline>
    </svg>

    <?php echo $read_time; ?> min read

</span>
                            <span class="ws-dot">•</span>

                            <span class="ws-hero-date"><?php echo get_the_date('M d, Y'); ?></span>

                        </div>

                    </div>

                </a>

            </div>

            <?php
        }

        wp_reset_postdata();
    }

    return ob_get_clean();
}

add_shortcode('cover_story_hero', 'wedmate_cover_story_hero');


// Custom code for starts from Vendor here ..............


add_action('add_meta_boxes', function() {
    add_meta_box(
        'vendor_details',
        'Vendor Details',
        'render_vendor_meta_box',
        'vendors',
        'normal',
        'high'
    );
});

function render_vendor_meta_box($post) {

    $rating = get_post_meta($post->ID, 'rating', true);
    $reviews = get_post_meta($post->ID, 'reviews', true);
    $price = get_post_meta($post->ID, 'price', true);
    $years = get_post_meta($post->ID, 'years', true);
    $weddings = get_post_meta($post->ID, 'weddings', true);
    $phone = get_post_meta($post->ID, 'phone', true);
    $email = get_post_meta($post->ID, 'email', true);
	$short_description = get_post_meta($post->ID, 'short_description', true);
	$services = get_post_meta($post->ID, 'services', true);
	$website = get_post_meta($post->ID, 'website', true);
	$booking_timeline = get_post_meta($post->ID, 'booking_timeline', true);
	$portfolio_gallery = get_post_meta($post->ID, 'portfolio_gallery', true);
	$verified_vendor = get_post_meta($post->ID, 'verified_vendor', true);

    ?>
    
    <p>Rating: <input type="text" name="rating" value="<?php echo esc_attr($rating); ?>"></p>
    <p>Reviews: <input type="text" name="reviews" value="<?php echo esc_attr($reviews); ?>"></p>
    <p>Price Range: <input type="text" name="price" value="<?php echo esc_attr($price); ?>"></p>
    <p>Years Active: <input type="text" name="years" value="<?php echo esc_attr($years); ?>"></p>
    <p>Weddings Done: <input type="text" name="weddings" value="<?php echo esc_attr($weddings); ?>"></p>
    <p>Phone: <input type="text" name="phone" value="<?php echo esc_attr($phone); ?>"></p>
    <p>Email: <input type="text" name="email" value="<?php echo esc_attr($email); ?>"></p>
	<p>
    Short Description:<br>
    <textarea name="short_description" rows="3" style="width:100%;"><?php echo esc_textarea($short_description); ?>		     </textarea>
	</p>

	<p>
    Services (One per line):<br>
    <textarea name="services" rows="5" style="width:100%;"><?php echo esc_textarea($services); ?></textarea>
	</p>

	<p>
    Website:<br>
    <input type="text" name="website" value="<?php echo esc_attr($website); ?>" style="width:100%;">
	</p>
    
    <p>
    Booking Timeline:<br>
    <input type="text" name="booking_timeline" value="<?php echo esc_attr($booking_timeline); ?>" style="width:100%;">
    </p>

	<p><strong>Portfolio Gallery</strong></p>

	<input type="hidden" 
       id="wsv_portfolio_gallery" 
       name="portfolio_gallery"
       value="<?php echo esc_attr($portfolio_gallery); ?>">

	<div id="wsv_gallery_preview" class="wsv-gallery-preview"></div>

	<p>
    <button type="button" class="button button-primary" id="wsv_upload_gallery">
        Upload Portfolio Images
    </button>

    <button type="button" class="button" id="wsv_clear_gallery">
        Clear Gallery
    </button>
	</p>
	
	<p>
    <label>
        <input type="checkbox"
               name="verified_vendor"
               value="1"
               <?php checked($verified_vendor, '1'); ?>>
        Verified Vendor
    </label>
    </p>

    <?php
}

add_action('save_post', function($post_id) {

    if (array_key_exists('rating', $_POST)) {
        update_post_meta($post_id, 'rating', $_POST['rating']);
        update_post_meta($post_id, 'reviews', $_POST['reviews']);
        update_post_meta($post_id, 'price', $_POST['price']);
        update_post_meta($post_id, 'years', $_POST['years']);
        update_post_meta($post_id, 'weddings', $_POST['weddings']);
        update_post_meta($post_id, 'phone', $_POST['phone']);
        update_post_meta($post_id, 'email', $_POST['email']);
		update_post_meta($post_id, 'short_description', $_POST['short_description']);
		update_post_meta($post_id, 'services', $_POST['services']);
		update_post_meta($post_id, 'website', $_POST['website']);
		update_post_meta($post_id, 'booking_timeline', $_POST['booking_timeline']);
		update_post_meta($post_id, 'portfolio_gallery', $_POST['portfolio_gallery']);
		update_post_meta($post_id, 'verified_vendor', isset($_POST['verified_vendor']) ? '1' : '0');
    }

});

add_action('admin_enqueue_scripts', function($hook) {

    global $post;

    if (($hook == 'post.php' || $hook == 'post-new.php') && isset($post->post_type) && $post->post_type === 'vendors') {

        wp_enqueue_media();

        wp_enqueue_script(
            'wsv-gallery-admin',
            get_stylesheet_directory_uri() . '/wsv-gallery.js',
            array('jquery'),
            null,
            true
        );
    }
});

add_action('admin_head', function() {
?>
<style>

.wsv-gallery-preview{
    display:flex;
    flex-wrap:wrap;
    gap:12px;
    margin:15px 0;
}

.wsv-thumb{
    width:90px;
    height:90px;
    border-radius:12px;
    overflow:hidden;
    border:1px solid #ddd;
}

.wsv-thumb img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}

</style>
<?php
});

// Shortcode starts from here

// ================= VENDOR CARD FUNCTION =================
function wedmate_vendor_card($post_id) {

    $id = $post_id;
    $link = get_permalink($id);

    $rating   = get_post_meta($id, 'rating', true);
    $reviews  = get_post_meta($id, 'reviews', true);
    $price    = get_post_meta($id, 'price', true);

    $category = get_the_terms($id, 'vendor_category');
    $location = get_the_terms($id, 'location');
    $tags     = get_the_terms($id, 'vendor_tag');

    ob_start();
    ?>

    <a href="<?php echo esc_url($link); ?>" class="vendor-card-link">
        <div class="vendor-card">

            <div class="vendor-img">
                <div class="vendor-img-inner">
                    <?php if (has_post_thumbnail($id)) {
                        echo get_the_post_thumbnail($id, 'large', ['loading'=>'lazy']);
                    } ?>
                </div>

                <?php
                $icon_map = [
                    'verified'     => 'fa-check-circle',
                    'editors-pick' => 'fa-star',
                    'top-rated'    => 'fa-award',
                ];

                if (!empty($tags) && !is_wp_error($tags)) {
                    $tag  = $tags[0];
                    $slug = $tag->slug;
                    $icon = $icon_map[$slug] ?? 'fa-check-circle';

                    echo '<span class="vendor-badge">';
                    echo '<i class="fas '.$icon.'"></i>';
                    echo esc_html(strtoupper($tag->name));
                    echo '</span>';
                }

                if ($rating) {
                    echo '<span class="vendor-rating">★ '.esc_html($rating).'</span>';
                }
                ?>
            </div>

            <div class="vendor-content">

                <?php if (!empty($category) && !is_wp_error($category)) {
                    echo '<span class="vendor-cat">'.esc_html(strtoupper($category[0]->name)).'</span>';
                } ?>

                <h3 class="vendor-title"><?php echo esc_html(get_the_title($id)); ?></h3>

                <p><?php echo esc_html(get_the_excerpt($id)); ?></p>

                <div class="vendor-meta">

                    <?php if (!empty($location) && !is_wp_error($location)) {
                        echo '<span class="vendor-location"><i class="fas fa-map-marker-alt"></i> '.esc_html($location[0]->name).'</span>';
                    } ?>

                    <span class="vendor-reviews">

    <?php if ($price) : ?>

        <span class="vendor-price">

            <i class="fas fa-indian-rupee-sign"></i>

            <?php echo esc_html($price); ?>

        </span>

    <?php endif; ?>


    <?php if ($reviews) : ?>

        · <?php echo esc_html($reviews); ?> reviews

    <?php endif; ?>

</span>

                </div>

            </div>

        </div>
    </a>

    <?php
    return ob_get_clean();
}


// ================= SHORTCODE =================
function wedmate_vendors_grid() {

    ob_start();

    // FILTER UI
    ?>
    <div class="vendor-filter-wrap">

        <input type="text" id="vendor-search-input" placeholder="Search vendor or specialty...">

        <select id="vendor-category-filter">
            <option value="">All</option>
            <?php
            $cats = get_terms(['taxonomy'=>'vendor_category','hide_empty'=>true]);
            foreach ($cats as $cat) {
                echo '<option value="'.$cat->slug.'">'.$cat->name.'</option>';
            }
            ?>
        </select>

        <select id="vendor-location-filter">
            <option value="">All</option>
            <?php
            $locs = get_terms(['taxonomy'=>'location','hide_empty'=>true]);
            foreach ($locs as $loc) {
                echo '<option value="'.$loc->slug.'">'.$loc->name.'</option>';
            }
            ?>
        </select>

        <span id="vendor-count"></span>

    </div>

    <div id="vendor-results" class="vendor-grid">
    <?php

    $query = new WP_Query([
        'post_type' => 'vendors',
        'posts_per_page' => -1
    ]);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            echo wedmate_vendor_card(get_the_ID());
        }
        wp_reset_postdata();
    }

    echo '</div>';
    ?>

   <script>
document.addEventListener("DOMContentLoaded", function () {

    const search   = document.getElementById('vendor-search-input');
    const category = document.getElementById('vendor-category-filter');
    const location = document.getElementById('vendor-location-filter');
    const results  = document.getElementById('vendor-results');
    const countEl  = document.getElementById('vendor-count');

    // ================= FETCH FUNCTION =================
    function fetchVendors() {

        const formData = new URLSearchParams();
        formData.append('action', 'vendor_filter');
        formData.append('search', search.value);
        formData.append('category', category.value);
        formData.append('location', location.value);

        // LOADING STATE
        results.style.opacity = "0.5";

        fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: formData.toString()
        })
        .then(res => res.text())
        .then(data => {

            results.innerHTML = data;
            results.style.opacity = "1";

            // UPDATE COUNT
            let count = results.querySelectorAll('.vendor-card').length;
            countEl.textContent = count + (count === 1 ? ' vendor' : ' vendors');
        });
    }

    // ================= DEBOUNCE SEARCH =================
    let debounceTimer;

    search.addEventListener('keyup', function () {
        clearTimeout(debounceTimer);

        debounceTimer = setTimeout(() => {
            fetchVendors();
        }, 400);
    });

    // ================= DROPDOWN FILTER =================
    category.addEventListener('change', fetchVendors);
    location.addEventListener('change', fetchVendors);

    // ================= INITIAL COUNT =================
    let initialCount = results.querySelectorAll('.vendor-card').length;
    countEl.textContent = initialCount + (initialCount === 1 ? ' vendor' : ' vendors');

});
</script>

    <?php

    return ob_get_clean();
}
add_shortcode('vendor_grid', 'wedmate_vendors_grid');


// ================= AJAX =================
add_action('wp_ajax_vendor_filter', 'vendor_filter_function');
add_action('wp_ajax_nopriv_vendor_filter', 'vendor_filter_function');

function vendor_filter_function() {

    // ================= SAFE INPUT =================
    $search   = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
    $location = isset($_POST['location']) ? sanitize_text_field($_POST['location']) : '';

    // ================= TAX QUERY =================
    $tax_query = [];

    if (!empty($category)) {
        $tax_query[] = [
            'taxonomy' => 'vendor_category',
            'field'    => 'slug',
            'terms'    => $category
        ];
    }

    if (!empty($location)) {
        $tax_query[] = [
            'taxonomy' => 'location',
            'field'    => 'slug',
            'terms'    => $location
        ];
    }

    // ================= QUERY OPTIMIZED =================
    $args = [
        'post_type'           => 'vendors',
        'posts_per_page'      => -1,
        's'                   => $search,
        'no_found_rows'       => true,   
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ];

    if (!empty($tax_query)) {
        $args['tax_query'] = $tax_query;
    }

    $query = new WP_Query($args);

    // ================= OUTPUT =================
    if ($query->have_posts()) {

        while ($query->have_posts()) {
            $query->the_post();

            // REUSE CARD FUNCTION
            echo wedmate_vendor_card(get_the_ID());
        }

    } else {
        echo '<p style="padding:20px;">No vendors found</p>';
    }

    wp_reset_postdata();
    wp_die();
}


// Single Vendor page custom code starts from here ----------->
// 
// 

function wedmate_vendor_single() {

    if (!is_singular(['vendors', 'listings'])) return '';

    ob_start();

    global $post;
    $id = $post->ID;

    $rating   = get_post_meta($id, 'rating', true);
    $reviews  = get_post_meta($id, 'reviews', true);
    $price    = get_post_meta($id, 'price', true);

    $category = get_the_terms($id, 'vendor_category');
    $location = get_the_terms($id, 'location');
    $tags     = get_the_terms($id, 'vendor_tag');
    ?>

    <div class="vendor-single">

        <!-- HERO -->
        <div class="vendor-hero">
            <?php echo get_the_post_thumbnail($id, 'full'); ?>
        </div>

        <!-- HEADER CARD -->
        <div class="vendor-header-card">

            <!-- LEFT -->
            <div class="vendor-header-left">

                <!-- BADGE + CATEGORY -->

				<div class="vendor-top-line">

    <?php
    if (!empty($tags) && !is_wp_error($tags)) {

        echo '<span class="vendor-badg">';
        echo '<i class="fas fa-check-circle"></i>';
        echo esc_html(strtoupper($tags[0]->name));
        echo '</span>';
    }
    ?>
    <?php
    if (!empty($category) && !is_wp_error($category)) {

        echo '<span class="vendor-cate">';
        echo esc_html(strtoupper($category[0]->name));
        echo '</span>';
    }
    ?>

</div>
				
				
                <!-- TITLE -->
                <h1><?php echo esc_html(get_the_title($id)); ?></h1>

                <!-- TAGLINE -->
                <p class="vendor-tagline">
                    <?php echo esc_html(get_the_excerpt($id)); ?>
                </p>

                <!-- META -->
                <div class="vendor-meta">

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

                    <?php if ($price) : ?>
                        <span>
                            <?php echo esc_html($price); ?>
                        </span>
                    <?php endif; ?>

                </div>

            </div>

            <!-- RIGHT -->
            <div class="vendor-header-right">

                <a href="#" class="btn-enquire">
                    <i class="fas fa-envelope"></i>
                    Enquire
                </a>

                <a href="#" class="btn-save">
                    <i class="far fa-heart"></i>
                    Save
                </a>

            </div>

        </div>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode('vendor_single', 'wedmate_vendor_single');


// vendor about section

function wedmate_vendor_content_layout() {

    if (!is_singular(['vendors', 'listings'])) return '';

    ob_start();

    global $post;
    $id = $post->ID;

    $rating = get_post_meta($id, 'rating', true);
    $reviews = get_post_meta($id, 'reviews', true);
    $price = get_post_meta($id, 'price', true);
    $years = get_post_meta($id, 'years', true);
    $weddings = get_post_meta($id, 'weddings', true);
    $phone = get_post_meta($id, 'phone', true);
    $email = get_post_meta($id, 'email', true);
    $website = get_post_meta($id, 'website', true);
    $booking_timeline = get_post_meta($id, 'booking_timeline', true);
  
    ?>

    <div class="wsv-layout-wrap">

        <div class="wsv-layout-grid">

            <!-- LEFT -->
            <div class="wsv-main">

                <!-- ABOUT -->
                <div class="wsv-about">

                    <div class="wsv-about-head">

    <h2 class="wsv-sec-title">
        About <?php echo esc_html(get_the_title($id)); ?>
    </h2>

</div>

<div class="wsv-about-content">
    <?php echo wpautop(get_post_meta($id, 'short_description', true)); ?>
</div>
                </div>

<?php

$services = get_post_meta($id, 'services', true);

if (!empty($services)) :

$services_array = array_filter(array_map('trim', explode("\n", $services)));

?>

<div class="wsv-services">

    <h2 class="wsv-sec-title">
        Services
    </h2>

    <div class="wsv-services-grid">

        <?php foreach ($services_array as $service) : ?>

            <div class="wsv-service-card">

                <span class="wsv-service-icon">
                    ✦
                </span>

                <span class="wsv-service-name">
                    <?php echo esc_html($service); ?>
                </span>

            </div>

        <?php endforeach; ?>

    </div>

</div>

<?php endif; ?>

<?php

$portfolio_gallery = get_post_meta($id, 'portfolio_gallery', true);

if (!empty($portfolio_gallery)) :

$gallery_ids = array_filter(explode(',', $portfolio_gallery));

?>

<div class="wsv-portfolio">

    <h2 class="wsv-sec-title">
        Portfolio
    </h2>

    <div class="wsv-portfolio-grid">

        <?php foreach ($gallery_ids as $image_id) : ?>

            <div class="wsv-portfolio-item">

                <?php echo wp_get_attachment_image($image_id, 'large'); ?>

            </div>

        <?php endforeach; ?>

    </div>

</div>

<?php endif; ?>

            </div>
            
            

            <!-- RIGHT -->
            <div class="wsv-sidebar">

                <div class="wsv-glance-card">

    <h3 class="wsv-side-title">
        At a glance
    </h3>

    <div class="wsv-glance-list">

        <?php if ($years) : ?>
        <div class="wsv-glance-row">
            <span>Years active</span>
            <strong><?php echo esc_html($years); ?></strong>
        </div>
        <?php endif; ?>

        <?php if ($weddings) : ?>
        <div class="wsv-glance-row">
            <span>Weddings done</span>
            <strong><?php echo esc_html($weddings); ?>+</strong>
        </div>
        <?php endif; ?>

        <?php if ($price) : ?>
        <div class="wsv-glance-row">
            <span>Price range</span>
            <strong><?php echo esc_html($price); ?></strong>
        </div>
        <?php endif; ?>

        <?php if ($rating) : ?>
        <div class="wsv-glance-row">
            <span>Rating</span>

            <strong class="wsv-rating">
                <i class="fas fa-star"></i>
                <?php echo esc_html($rating); ?>
            </strong>

        </div>
        <?php endif; ?>

    </div>
    
    </div>
    

    <div class="wsv-contact-card">

    <h3 class="wsv-side-title">
        Contact
    </h3>

    <div class="wsv-contact-list">

        <?php if ($email) : ?>
        <div class="wsv-contact-item">

            <div class="wsv-contact-icon">
                <i class="far fa-envelope"></i>
            </div>

            <span>
                <?php echo esc_html($email); ?>
            </span>

        </div>
        <?php endif; ?>

        <?php if ($phone) : ?>
        <div class="wsv-contact-item">

            <div class="wsv-contact-icon">
                <i class="fas fa-phone-alt"></i>
            </div>

            <span>
                <?php echo esc_html($phone); ?>
            </span>

        </div>
        <?php endif; ?>

        <?php if ($website) : ?>
        <div class="wsv-contact-item">

            <div class="wsv-contact-icon">
                <i class="fas fa-globe"></i>
            </div>

            <span>
                <?php echo esc_html($website); ?>
            </span>

        </div>
        <?php endif; ?>

        <?php if ($booking_timeline) : ?>
        <div class="wsv-contact-item">

            <div class="wsv-contact-icon">
                <i class="far fa-calendar-alt"></i>
            </div>

            <span>
                <?php echo esc_html($booking_timeline); ?>
            </span>

        </div>
        <?php endif; ?>

    </div>

</div>

<div class="wsv-quote-card">

    <h3 class="wsv-quote-title">
        Get a quote
    </h3>

    <p class="wsv-quote-text">
        Tell us your wedding date and city — we'll forward your enquiry directly.
    </p>

    <a href="#" class="wsv-quote-btn">
        Send enquiry
    </a>

</div>


            </div>

        </div>

    <?php

$verified_vendor = get_post_meta($id, 'verified_vendor', true);

if ($verified_vendor == '1') :

?>

<div class="wsv-verified">

    <div class="wsv-verified-content">

        <div class="wsv-verified-top">

            <div class="wsv-verified-icon">
                <i class="fas fa-shield-alt"></i>
            </div>

            <h3>
                WedMate verified
            </h3>

        </div>

        <p>
            We've verified business credentials, reviewed real wedding work and confirmed pricing transparency. This vendor meets our editorial standards.
        </p>

    </div>

</div>

<?php endif; ?>

    </div>
    
    

    <?php

    return ob_get_clean();
}

add_shortcode('vendor_content_layout', 'wedmate_vendor_content_layout');


// Related vendor section 

function wedmate_related_vendors() {

    if (!is_singular(['vendors', 'listings'])) return '';

    ob_start();

    global $post;

    $current_id = $post->ID;

    $related_vendors = new WP_Query(array(
        'post_type' => 'vendors',
        'posts_per_page' => 3,
        'post__not_in' => array($current_id)
    ));

    if ($related_vendors->have_posts()) :

    ?>

    <div class="wsv-related-wrap">

        <h2 class="wsv-related-title">
            Other vendors you may like
        </h2>

        <div class="wsv-related-grid">

            <?php while ($related_vendors->have_posts()) : $related_vendors->the_post();

            $vendor_id = get_the_ID();

            $rating = get_post_meta($vendor_id, 'rating', true);

            $location = get_the_terms($vendor_id, 'location');

            $category = get_the_terms($vendor_id, 'vendor_category');

            ?>

            <a href="<?php the_permalink(); ?>" class="wsv-related-card">

                <div class="wsv-related-image">

                    <?php the_post_thumbnail('large'); ?>

                </div>

                <div class="wsv-related-content">

                    <?php if (!empty($category) && !is_wp_error($category)) : ?>

                        <div class="wsv-related-cat">
                            <?php echo esc_html($category[0]->name); ?>
                        </div>

                    <?php endif; ?>

                    <h3 class="wsv-related-name">
                        <?php the_title(); ?>
                    </h3>

                    <div class="wsv-related-meta">

                        <div class="wsv-related-location">

                            <i class="fas fa-map-marker-alt"></i>

                            <?php
                            if (!empty($location) && !is_wp_error($location)) {
                                echo esc_html($location[0]->name);
                            }
                            ?>

                        </div>

                        <?php if ($rating) : ?>

                        <div class="wsv-related-rating">

                            <i class="fas fa-star"></i>

                            <?php echo esc_html($rating); ?>

                        </div>

                        <?php endif; ?>

                    </div>

                </div>

            </a>

            <?php endwhile; wp_reset_postdata(); ?>

        </div>

    </div>

    <?php endif;

    return ob_get_clean();
}

add_shortcode('related_vendors', 'wedmate_related_vendors');


// Vendor rewrite rules ------------------->

function wedmate_vendor_location_rewrite() {

    add_rewrite_rule(
        '^vendors/([^/]+)/([^/]+)/?$',
        'index.php?vendor_category=$matches[1]&location_filter=$matches[2]',
        'top'
    );

}

add_action(
    'init',
    'wedmate_vendor_location_rewrite'
);

//------------->

function wedmate_vendor_query_vars($vars) {

    $vars[] = 'location_filter';

    return $vars;

}

add_filter(
    'query_vars',
    'wedmate_vendor_query_vars'
);





// Story Page Custom Code Starts From Here ---------->

/* STORY META BOX */
function wedmate_story_meta_box() {

    add_meta_box(
        'wedmate_story_details',
        'Story Details',
        'wedmate_story_meta_callback',
        'stories',
        'normal',
        'high'
    );

}

add_action('add_meta_boxes', 'wedmate_story_meta_box');


/* STORY META CALLBACK */
function wedmate_story_meta_callback($post) {

    wp_nonce_field('wedmate_story_nonce', 'wedmate_story_nonce_field');

    $couple_names = get_post_meta($post->ID, 'couple_names', true);
    $wedding_date = get_post_meta($post->ID, 'wedding_date', true);
    $venue = get_post_meta($post->ID, 'venue', true);
    $guests = get_post_meta($post->ID, 'guests', true);
    $wedding_days = get_post_meta($post->ID, 'wedding_days', true);
    $petals_count = get_post_meta($post->ID, 'petals_count', true);
    $outfit_changes = get_post_meta($post->ID, 'outfit_changes', true);
    $photographer_name = get_post_meta($post->ID, 'photographer_name', true);
    $story_intro = get_post_meta($post->ID, 'story_intro', true);
    $story_gallery = get_post_meta($post->ID, 'story_gallery', true);
    $selected_vendors = get_post_meta($post->ID, 'selected_vendors', true);

    if (!is_array($selected_vendors)) {
        $selected_vendors = array();
    }

    $vendors = get_posts(array(
        'post_type' => 'listings',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    ));
    $featured_story = get_post_meta($post->ID, 'featured_story', true);
    $story_short_desc = get_post_meta($post->ID, 'story_short_desc', true);

    ?>

    <p>
        <label>Couple Names</label><br>
        <input type="text" name="couple_names" value="<?php echo esc_attr($couple_names); ?>" style="width:100%;">
    </p>

    <p>
        <label>Wedding Date</label><br>
        <input type="text" name="wedding_date" value="<?php echo esc_attr($wedding_date); ?>" style="width:100%;">
    </p>

    <p>
        <label>Venue</label><br>
        <input type="text" name="venue" value="<?php echo esc_attr($venue); ?>" style="width:100%;">
    </p>

    <p>
        <label>Guests</label><br>
        <input type="text" name="guests" value="<?php echo esc_attr($guests); ?>" style="width:100%;">
    </p>

    <p>
        <label>Wedding Days</label><br>
        <input type="text" name="wedding_days" value="<?php echo esc_attr($wedding_days); ?>" style="width:100%;">
    </p>

    <p>
        <label>Petals Count</label><br>
        <input type="text" name="petals_count" value="<?php echo esc_attr($petals_count); ?>" style="width:100%;">
    </p>

    <p>
        <label>Outfit Changes</label><br>
        <input type="text" name="outfit_changes" value="<?php echo esc_attr($outfit_changes); ?>" style="width:100%;">
    </p>

    <p>
        <label>Photographer Name</label><br>
        <input type="text" name="photographer_name" value="<?php echo esc_attr($photographer_name); ?>" style="width:100%;">
    </p>

    <p>
        <label>Story Intro</label><br>
        <textarea name="story_intro" rows="5" style="width:100%;"><?php echo esc_textarea($story_intro); ?></textarea>
    </p>

    <p><strong>Story Gallery</strong></p>

    <input type="hidden"
           id="wrs_story_gallery"
           name="story_gallery"
           value="<?php echo esc_attr($story_gallery); ?>">

    <div id="wrs_gallery_preview" class="wrs-gallery-preview"></div>

    <p>

        <button type="button"
                class="button button-primary"
                id="wrs_upload_gallery">

            Upload Story Images

        </button>

        <button type="button"
                class="button"
                id="wrs_clear_gallery">

            Clear Gallery

        </button>

    </p>
    
    <p>
    <label>Story Short Description</label><br>

    <textarea name="story_short_desc"
              rows="3"
              style="width:100%;"><?php echo esc_textarea($story_short_desc); ?></textarea>
    </p>
    
    <p>

    <label>

        <input type="checkbox"
               name="featured_story"
               value="1"
               <?php checked($featured_story, '1'); ?>>

        Mark as Featured Story

    </label>

    </p>

    <hr>

    <h2>Wedding Team</h2>

    <div class="wrs-vendor-list">

        <?php

        if ($vendors) :

        foreach ($vendors as $vendor) :

        $vendor_id = $vendor->ID;

        $vendor_categories = get_the_terms($vendor_id, 'vendor_category');

        $vendor_category_name = '';

        if (!empty($vendor_categories) && !is_wp_error($vendor_categories)) {
            $vendor_category_name = $vendor_categories[0]->name;
        }

        ?>

        <label class="wrs-vendor-item">

            <input type="checkbox"
                   name="selected_vendors[]"
                   value="<?php echo esc_attr($vendor_id); ?>"
                   <?php checked(in_array($vendor_id, $selected_vendors)); ?>>

            <div class="wrs-vendor-content">

                <?php if ($vendor_category_name) : ?>

                <span class="wrs-vendor-cat">
                    <?php echo esc_html(strtoupper($vendor_category_name)); ?>
                </span>

                <?php endif; ?>

                <strong class="wrs-vendor-name">
                    <?php echo esc_html(get_the_title($vendor_id)); ?>
                </strong>

            </div>

        </label>

        <?php

        endforeach;

        endif;

        ?>

    </div>

    <?php
}


/* SAVE STORY META */
function wedmate_save_story_meta($post_id) {

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (
        !isset($_POST['wedmate_story_nonce_field']) ||
        !wp_verify_nonce($_POST['wedmate_story_nonce_field'], 'wedmate_story_nonce')
    ) {
        return;
    }

    if (get_post_type($post_id) !== 'stories' || !current_user_can('edit_post', $post_id)) {
        return;
    }

    $fields = array(
        'couple_names',
        'wedding_date',
        'venue',
        'guests',
        'wedding_days',
        'petals_count',
        'outfit_changes',
        'photographer_name',
        'story_intro',
        'story_gallery',
        'featured_story',
        'story_short_desc'
    );

   foreach ($fields as $field) {

    if ($field === 'featured_story') {

        $value = isset($_POST[$field]) ? '1' : '';

    } else {

        $value = isset($_POST[$field])
            ? sanitize_text_field($_POST[$field])
            : '';

    }

    update_post_meta(
        $post_id,
        $field,
        $value
    );

}

    if (isset($_POST['selected_vendors'])) {

        $vendors = array_map('intval', $_POST['selected_vendors']);

        update_post_meta(
            $post_id,
            'selected_vendors',
            $vendors
        );

    } else {

        update_post_meta(
            $post_id,
            'selected_vendors',
            array()
        );

    }

}

add_action('save_post', 'wedmate_save_story_meta');


/* STORY GALLERY ADMIN SCRIPT */
add_action('admin_enqueue_scripts', function($hook) {

    global $post;

    if (($hook == 'post.php' || $hook == 'post-new.php') && isset($post->post_type) && $post->post_type === 'stories') {

        wp_enqueue_media();

        wp_enqueue_script(
            'wrs-gallery-admin',
            get_stylesheet_directory_uri() . '/wrs-gallery.js',
            array('jquery'),
            null,
            true
        );
    }
});


/* ADMIN CSS */
add_action('admin_head', function() {
?>
<style>

.wrs-gallery-preview{
    display:flex;
    flex-wrap:wrap;
    gap:12px;
    margin:15px 0;
}

.wrs-thumb{
    width:90px;
    height:90px;
    border-radius:12px;
    overflow:hidden;
    border:1px solid #ddd;
}

.wrs-thumb img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}

.wrs-vendor-list{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:14px;
    margin-top:20px;
}

.wrs-vendor-item{
    display:flex;
    align-items:flex-start;
    gap:12px;
    padding:16px;
    border:1px solid #ddd;
    border-radius:14px;
    background:#fff;
    cursor:pointer;
}

.wrs-vendor-item input{
    margin-top:4px;
}

.wrs-vendor-content{
    display:flex;
    flex-direction:column;
    gap:4px;
}

.wrs-vendor-cat{
    font-size:10px;
    font-weight:700;
    letter-spacing:.18em;
    text-transform:uppercase;
    color:#e61b95;
}

.wrs-vendor-name{
    font-size:15px;
    line-height:1.4;
}

</style>
<?php
});


/* STORY CARD FUNCTION */
function wedmate_story_card($post_id, $featured = false) {

    $id = $post_id;

    $link = get_permalink($id);

    $location = get_the_terms($id, 'location');

    $couple = get_post_meta($id, 'couple_names', true);

    $wedding_date = get_post_meta($id, 'wedding_date', true);

    $guests = get_post_meta($id, 'guests', true);

    $wedding_days = get_post_meta($id, 'wedding_days', true);

    $short_desc = get_post_meta($id, 'story_short_desc', true);

    $featured_story = get_post_meta($id, 'featured_story', true);

    ob_start();

    ?>

    <a href="<?php echo esc_url($link); ?>" class="wrs-story-card">

        <div class="wrs-story-image">

            <?php
            if (has_post_thumbnail($id)) {
                echo get_the_post_thumbnail($id, 'large');
            }
            ?>

            <?php if ($featured_story == '1') : ?>

                <div class="wrs-featured-badge">

                    <i class="fas fa-heart"></i>

                    Featured Story

                </div>

            <?php endif; ?>

        </div>

        <div class="wrs-story-content">

            <?php if ($couple) : ?>

                <div class="wrs-story-couple">

                    <?php echo esc_html(strtoupper($couple)); ?>

                </div>

            <?php endif; ?>

            <h3 class="wrs-story-title">

                <?php echo esc_html(get_the_title($id)); ?>

            </h3>

            <p class="wrs-story-excerpt">

                <?php echo esc_html($short_desc); ?>

            </p>

            <div class="wrs-story-meta">

                <?php if (!empty($location) && !is_wp_error($location)) : ?>

                    <span class="wrs-story-location">

                        <i class="fas fa-map-marker-alt"></i>

                        <?php echo esc_html($location[0]->name); ?>

                    </span>

                <?php endif; ?>

                <?php if ($wedding_date) : ?>

                    <span class="wrs-story-dot">•</span>

                    <span class="wrs-story-date">

                        <i class="far fa-calendar"></i>

                        <?php echo esc_html($wedding_date); ?>

                    </span>

                <?php endif; ?>


                <!-- EXTRA META ONLY FOR FEATURED STORY -->
                <?php if ($featured) : ?>

                    <?php if ($guests) : ?>

                        <span class="wrs-story-dot">•</span>

                        <span class="wrs-story-guests">

                            <?php echo esc_html($guests); ?> guests

                        </span>

                    <?php endif; ?>

                    <?php if ($wedding_days) : ?>

                        <span class="wrs-story-dot">•</span>

                        <span class="wrs-story-days">

                            <?php echo esc_html($wedding_days); ?> days

                        </span>

                    <?php endif; ?>

                    

                <?php endif; ?>

            </div>

        </div>

    </a>

    <?php

    return ob_get_clean();
}

/* STORY ARCHIVE SHORTCODE */
function wedmate_story_archive_shortcode() {

    ob_start();

    $search = isset($_GET['story_search']) ? sanitize_text_field($_GET['story_search']) : '';

    $category = isset($_GET['story_category']) ? sanitize_text_field($_GET['story_category']) : '';

    ?>

    <div class="wrs-archive-wrap">

       <?php

$total_stories = wp_count_posts('stories')->publish;

?>

<div class="wrs-archive-filter">

    <form method="GET" class="wrs-filter-form">

        <div class="wrs-filter-search">

            <i class="fas fa-search"></i>

            <input type="text"
                   name="story_search"
                   placeholder="Search couples, cities or themes..."
                   value="<?php echo esc_attr($search); ?>">

        </div>

        <div class="wrs-filter-right">

            <select name="story_category"
                    onchange="this.form.submit()">

                <option value="">
                    All
                </option>

                <?php

                $categories = get_terms(array(
                    'taxonomy' => 'story_category',
                    'hide_empty' => true
                ));

                foreach ($categories as $cat) :

                ?>

                    <option value="<?php echo esc_attr($cat->slug); ?>"
                        <?php selected($category, $cat->slug); ?>>

                        <?php echo esc_html($cat->name); ?>

                    </option>

                <?php endforeach; ?>

            </select>

            <span class="wrs-story-count">

                <?php echo esc_html($total_stories); ?> stories

            </span>

        </div>

    </form>

</div>

        <?php

        if (empty($search) && empty($category)) :

        $featured_query = new WP_Query(array(
    'post_type' => 'stories',
    'posts_per_page' => 1,
    'post_status' => 'publish',
    'meta_key' => 'featured_story',
    'meta_value' => '1',
    'orderby' => 'date',
    'order' => 'DESC'
));

        if ($featured_query->have_posts()) :

        ?>

        <div class="wrs-featured-story">

            <?php

            while ($featured_query->have_posts()) :
            $featured_query->the_post();

            echo wedmate_story_card(get_the_ID(), true);

            endwhile;

            wp_reset_postdata();

            ?>

        </div>

        <?php endif; endif; ?>

        <?php

       $exclude_featured = array();

/* EXCLUDE FEATURED STORY */
if (isset($featured_query) && $featured_query->have_posts()) {

    while ($featured_query->have_posts()) :
        $featured_query->the_post();

        $exclude_featured[] = get_the_ID();

    endwhile;

    wp_reset_postdata();
}

/* MAIN STORIES QUERY */
$args = array(
    'post_type'      => 'stories',
    'posts_per_page' => 9,
    'post_status'    => 'publish',
    's'              => $search,
    'post__not_in'   => $exclude_featured
);

if ($category) {

    $args['tax_query'] = array(
        array(
            'taxonomy' => 'story_category',
            'field'    => 'slug',
            'terms'    => $category
        )
    );
}

        $stories = new WP_Query($args);

        ?>

        <div class="wrs-story-grid">

            <?php

            if ($stories->have_posts()) :

                while ($stories->have_posts()) :
                $stories->the_post();

                echo wedmate_story_card(get_the_ID());

                endwhile;

                wp_reset_postdata();

            else :

                ?>

                <div class="wrs-no-stories">
                    No stories found.
                </div>

                <?php

            endif;

            ?>

        </div>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode('story_archive', 'wedmate_story_archive_shortcode');


// Single Story page code starts from here ------->


/* SINGLE STORY HERO SECTION */
function wedmate_story_single_hero() {

    if (!is_singular('stories')) return '';

    ob_start();

    global $post;

    $id = $post->ID;

    $couple_names    = get_post_meta($id, 'couple_names', true);
    $wedding_date    = get_post_meta($id, 'wedding_date', true);
    $guests          = get_post_meta($id, 'guests', true);
    $wedding_days    = get_post_meta($id, 'wedding_days', true);
    $petals_count    = get_post_meta($id, 'petals_count', true);
    $outfit_changes  = get_post_meta($id, 'outfit_changes', true);
    $photographer    = get_post_meta($id, 'photographer_name', true);

    $location = get_the_terms($id, 'location');

    ?>

    <div class="ws-story-hero">

        <!-- HERO IMAGE -->
        <div class="ws-story-hero-image">

            <?php echo get_the_post_thumbnail($id, 'full'); ?>

            <div class="ws-story-overlay"></div>

            <!-- CONTENT -->
            <div class="ws-story-hero-content">

                <div class="ws-story-badge">

                    <i class="far fa-heart"></i>

                    REAL WEDDING • <?php echo esc_html($wedding_date); ?>

                </div>

                <h1 class="ws-story-title">

                    <?php echo esc_html(get_the_title($id)); ?>

                </h1>

                <div class="ws-story-meta">

                    <?php if ($couple_names) : ?>

                        <span class="couple">
                            <?php echo esc_html($couple_names); ?>
                        </span>

                    <?php endif; ?>

                    <?php if (!empty($location) && !is_wp_error($location)) : ?>

                        <span>

                            <i class="fas fa-map-marker-alt"></i>

                            <?php echo esc_html($location[0]->name); ?>

                        </span>

                    <?php endif; ?>

                    <?php if ($wedding_date) : ?>

                        <span>

                            <i class="far fa-calendar"></i>

                            <?php echo esc_html($wedding_date); ?>

                        </span>

                    <?php endif; ?>

                    <?php if ($photographer) : ?>

                        <span>

                            <i class="fas fa-camera"></i>

                            Shot by <?php echo esc_html($photographer); ?>

                        </span>

                    <?php endif; ?>

                </div>

            </div>

        </div>

        <!-- BOTTOM STATS STRIP -->
        <div class="ws-story-strip">

            <div class="ws-strip-item">

                <strong><?php echo esc_html($wedding_days); ?></strong>

                <span>DAYS</span>

            </div>

            <div class="ws-strip-item">

                <strong><?php echo esc_html($guests); ?></strong>

                <span>GUESTS</span>

            </div>

            <div class="ws-strip-item">

                <strong><?php echo esc_html($petals_count); ?></strong>

                <span>PETALS</span>

            </div>

            <div class="ws-strip-item">

                <strong><?php echo esc_html($outfit_changes); ?></strong>

                <span>OUTFIT CHANGES</span>

            </div>

        </div>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode('story_single_hero', 'wedmate_story_single_hero');


/* STORY CONTENT LAYOUT */
function wedmate_story_content_layout() {

    if (!is_singular('stories')) return '';

    ob_start();

    global $post;

    $id = $post->ID;
	$couple_names   = get_post_meta($id, 'couple_names', true);
	$wedding_date   = get_post_meta($id, 'wedding_date', true);
	$guests         = get_post_meta($id, 'guests', true);
	$wedding_days   = get_post_meta($id, 'wedding_days', true);
	$photographer   = get_post_meta($id, 'photographer_name', true);
	$location = get_the_terms($id, 'location');
	$theme    = get_the_terms($id, 'story_theme');
    $story_intro = get_post_meta($id, 'story_intro', true);
    $gallery = get_post_meta($id, 'story_gallery', true);
    $gallery_ids = array_filter(explode(',', $gallery));

    ?>

    <div class="ws-story-layout-wrap">

        <div class="ws-story-layout-grid">

            <!-- LEFT CONTENT -->
            <div class="ws-story-main">

                <!-- STORY INTRO -->
                <div class="ws-story-section">

                    <span class="ws-story-label">
        THE STORY
    </span>

    <?php

    $theme = get_the_terms($id, 'story_theme');

    ?>

    <h2 class="ws-story-heading">

        A wedding in

        <?php if (!empty($theme) && !is_wp_error($theme)) : ?>

            <span>

                <?php echo esc_html(strtolower($theme[0]->name)); ?>

            </span>

        <?php endif; ?>

    </h2>

    <div class="ws-story-content">

                        <?php echo wpautop($story_intro); ?>

                    </div>

                </div>


                <!-- GALLERY -->
                <?php if (!empty($gallery_ids)) : ?>

                <div class="ws-story-gallery-wrap">

                    <h2 class="ws-story-gallery-title">
                        Inside the celebration
                    </h2>

                    <div class="ws-story-gallery-grid">

                        <?php foreach ($gallery_ids as $image_id) : ?>

                            <div class="ws-gallery-item">

                                <?php echo wp_get_attachment_image($image_id, 'large'); ?>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

                <?php endif; ?>

				<?php

$selected_vendors = get_post_meta($id, 'selected_vendors', true);

if (!empty($selected_vendors)) :

?>

<div class="ws-story-vendors">

    <h2 class="ws-vendor-title">

        <i class="fas fa-wand-magic-sparkles"></i>

        Wedding team

    </h2>

    <div class="ws-vendor-grid">

        <?php

        foreach ($selected_vendors as $vendor_id) :

        if (get_post_status($vendor_id) !== 'publish') continue;

        $vendor_category = get_the_terms($vendor_id, 'vendor_category');

        ?>

        <a href="<?php echo get_permalink($vendor_id); ?>"
           class="ws-vendor-card">

            <div class="ws-vendor-info">

                <?php if (!empty($vendor_category) && !is_wp_error($vendor_category)) : ?>

                    <span class="ws-vendor-cat">

                        <?php echo esc_html(strtoupper((get_post_meta($id, 'wml_team_roles', true)[$vendor_id] ?? '') ?: $vendor_category[0]->name)); ?>

                    </span>

                <?php endif; ?>

                <h3>

                    <?php echo esc_html(get_the_title($vendor_id)); ?>

                </h3>

            </div>

            <span class="ws-vendor-link">

                VIEW →

            </span>

        </a>

        <?php endforeach; ?>

    </div>

</div>

<?php endif; ?>
				
            </div>


            <!-- RIGHT SIDEBAR -->
            <div class="ws-story-sidebar">

               <?php

$theme = get_the_terms($id, 'story_theme');

?>

<div class="ws-details-card">

    <h3 class="ws-details-title">
        Wedding details
    </h3>

    <div class="ws-details-list">

        <?php if ($couple_names) : ?>

        <div class="ws-details-row">

            <span>Couple</span>

            <strong><?php echo esc_html($couple_names); ?></strong>

        </div>

        <?php endif; ?>


        <?php if (!empty($location) && !is_wp_error($location)) : ?>

        <div class="ws-details-row">

            <span>Location</span>

            <strong><?php echo esc_html($location[0]->name); ?></strong>

        </div>

        <?php endif; ?>


        <?php if ($wedding_date) : ?>

        <div class="ws-details-row">

            <span>Date</span>

            <strong><?php echo esc_html($wedding_date); ?></strong>

        </div>

        <?php endif; ?>


        <?php if (!empty($theme) && !is_wp_error($theme)) : ?>

        <div class="ws-details-row">

            <span>Theme</span>

            <strong><?php echo esc_html($theme[0]->name); ?></strong>

        </div>

        <?php endif; ?>


        <?php if ($wedding_days) : ?>

        <div class="ws-details-row">

            <span>Days</span>

            <strong><?php echo esc_html($wedding_days); ?></strong>

        </div>

        <?php endif; ?>


        <?php if ($guests) : ?>

        <div class="ws-details-row">

            <span>Guests</span>

            <strong><?php echo esc_html($guests); ?></strong>

        </div>

        <?php endif; ?>


        <?php if ($photographer) : ?>

        <div class="ws-details-row">

            <span>Photographer</span>

            <strong><?php echo esc_html($photographer); ?></strong>

        </div>

        <?php endif; ?>

    </div>

</div>
				<!-- SUBMIT STORY CARD -->
<div class="ws-submit-story-card">

    <h3>
        Loved this wedding?
    </h3>

    <p>
        Submit your own real wedding for a chance to be featured on WedMate.
    </p>

    <a href="#" class="ws-submit-story-btn">
        SUBMIT YOURS
    </a>

</div>


<!-- SHARE CARD -->
<div class="ws-share-story-card">

    <a href="#">

        <i class="fas fa-share-alt"></i>

        Share this story

    </a>

</div>

            </div>

        </div>

    </div>

    <?php

    return ob_get_clean();
}

add_shortcode('story_content_layout', 'wedmate_story_content_layout');


/* RELATED STORIES */
function wedmate_related_stories() {

    if (!is_singular('stories')) return '';

    ob_start();

    global $post;

    $current_id = $post->ID;

    $related_stories = new WP_Query(array(
        'post_type'      => 'stories',
        'posts_per_page' => 3,
        'post__not_in'   => array($current_id),
        'post_status'    => 'publish'
    ));

    if ($related_stories->have_posts()) :

    ?>

    <div class="ws-related-stories-wrap">

        <h2 class="ws-related-title">
            More real weddings
        </h2>

        <div class="ws-related-grid">

            <?php

            while ($related_stories->have_posts()) :
            $related_stories->the_post();

            $story_id = get_the_ID();

            $couple = get_post_meta($story_id, 'couple_names', true);

            $location = get_the_terms($story_id, 'location');

            ?>

            <a href="<?php the_permalink(); ?>"
               class="ws-related-card">

                <div class="ws-related-image">

                    <?php the_post_thumbnail('large'); ?>

                </div>

                <div class="ws-related-content">

                    <?php if ($couple) : ?>

                    <div class="ws-related-couple">

                        <?php echo esc_html(strtoupper($couple)); ?>

                    </div>

                    <?php endif; ?>

                    <h3 class="ws-related-name">

                        <?php the_title(); ?>

                    </h3>

                    <?php if (!empty($location) && !is_wp_error($location)) : ?>

                    <div class="ws-related-location">

                        <i class="fas fa-map-marker-alt"></i>

                        <?php echo esc_html($location[0]->name); ?>

                    </div>

                    <?php endif; ?>

                </div>

            </a>

            <?php endwhile; wp_reset_postdata(); ?>

        </div>

    </div>

    <?php endif;

    return ob_get_clean();
}

add_shortcode('related_stories', 'wedmate_related_stories');



// Custom url strucutre 

function wedmate_venue_location_rewrite() {

    add_rewrite_rule(
        '^venues/([^/]+)/?$',
        'index.php?location=$matches[1]',
        'top'
    );

}

add_action(
    'init',
    'wedmate_venue_location_rewrite'
);


//Venue page meta fields

/* =====================================================
   VENUE META BOX SYSTEM
===================================================== */

add_action('add_meta_boxes', function () {

    add_meta_box(
        'wm_venue_details',
        'Venue Details',
        'wm_render_venue_meta_box',
        'venues',
        'normal',
        'high'
    );

});


function wm_render_venue_meta_box($post) {

    // BASIC
    $rating              = get_post_meta($post->ID, 'rating', true);
    $reviews             = get_post_meta($post->ID, 'reviews', true);
    $price               = get_post_meta($post->ID, 'price', true);
    $guests              = get_post_meta($post->ID, 'guests', true);
    $tagline             = get_post_meta($post->ID, 'tagline', true);
    $verified_venue      = get_post_meta($post->ID, 'verified_venue', true);

    // ABOUT
    $venue_overview      = get_post_meta($post->ID, 'venue_overview', true);

    // FUNCTION SPACES
    $function_spaces     = get_post_meta($post->ID, 'function_spaces', true);

    // AMENITIES
    $amenities           = get_post_meta($post->ID, 'amenities', true);

    // CONTACT
    $address             = get_post_meta($post->ID, 'address', true);
    $phone               = get_post_meta($post->ID, 'phone', true);
    $email               = get_post_meta($post->ID, 'email', true);
    $website             = get_post_meta($post->ID, 'website', true);

    // GLANCE
    $rooms               = get_post_meta($post->ID, 'rooms', true);
    $per_plate           = get_post_meta($post->ID, 'per_plate', true);

    // VERIFIED
    $verified_text       = get_post_meta($post->ID, 'verified_text', true);

    // GALLERY
    $venue_gallery       = get_post_meta($post->ID, 'venue_gallery', true);

?>

<style>
.wm-admin-group{
    margin-bottom:35px;
    padding-bottom:25px;
    border-bottom:1px solid #eee;
}
.wm-admin-group h2{
    margin-bottom:18px;
}
.wm-admin-field{
    margin-bottom:18px;
}
.wm-admin-field label{
    display:block;
    font-weight:600;
    margin-bottom:6px;
}
.wm-admin-field input,
.wm-admin-field textarea{
    width:100%;
}
</style>

<!-- BASIC -->
<div class="wm-admin-group">

    <h2>Basic Details</h2>

    <div class="wm-admin-field">
        <label>Tagline</label>
        <textarea name="tagline" rows="3"><?php
        echo esc_textarea($tagline);
        ?></textarea>
    </div>

    <div class="wm-admin-field">
        <label>Rating</label>
        <input type="text" name="rating"
        value="<?php echo esc_attr($rating); ?>">
    </div>

    <div class="wm-admin-field">
        <label>Reviews</label>
        <input type="text" name="reviews"
        value="<?php echo esc_attr($reviews); ?>">
    </div>

    <div class="wm-admin-field">
        <label>Price</label>
        <input type="text" name="price"
        value="<?php echo esc_attr($price); ?>">
    </div>

    <div class="wm-admin-field">
        <label>Guests</label>
        <input type="text" name="guests"
        value="<?php echo esc_attr($guests); ?>">
    </div>

    <div class="wm-admin-field">
        <label>
            <input type="checkbox"
            name="verified_venue"
            value="1"
            <?php checked($verified_venue, '1'); ?>>
            Verified Venue
        </label>
    </div>

</div>


<!-- ABOUT -->
<div class="wm-admin-group">

    <h2>About Venue</h2>

    <div class="wm-admin-field">
        <label>Venue Overview</label>

        <textarea
        name="venue_overview"
        rows="8"><?php

        echo esc_textarea($venue_overview);

        ?></textarea>

    </div>

</div>


<!-- FUNCTION SPACES -->
<div class="wm-admin-group">

    <h2>Function Spaces</h2>

    <div class="wm-admin-field">

        <label>
            One per line
        </label>

        <textarea
        name="function_spaces"
        rows="6"><?php

        echo esc_textarea($function_spaces);

        ?></textarea>

    </div>

</div>


<!-- AMENITIES -->
<div class="wm-admin-group">

    <h2>Amenities</h2>

    <div class="wm-admin-field">

        <label>
            One per line
        </label>

        <textarea
        name="amenities"
        rows="6"><?php

        echo esc_textarea($amenities);

        ?></textarea>

    </div>

</div>


<!-- CONTACT -->
<div class="wm-admin-group">

    <h2>Contact Details</h2>

    <div class="wm-admin-field">
        <label>Address</label>
        <textarea name="address"
        rows="3"><?php
        echo esc_textarea($address);
        ?></textarea>
    </div>

    <div class="wm-admin-field">
        <label>Phone</label>
        <input type="text" name="phone"
        value="<?php echo esc_attr($phone); ?>">
    </div>

    <div class="wm-admin-field">
        <label>Email</label>
        <input type="text" name="email"
        value="<?php echo esc_attr($email); ?>">
    </div>

    <div class="wm-admin-field">
        <label>Website</label>
        <input type="text" name="website"
        value="<?php echo esc_attr($website); ?>">
    </div>

</div>


<!-- GLANCE -->
<div class="wm-admin-group">

    <h2>At A Glance</h2>

    <div class="wm-admin-field">
        <label>Rooms</label>
        <input type="text" name="rooms"
        value="<?php echo esc_attr($rooms); ?>">
    </div>

    <div class="wm-admin-field">
    <label>Per Plate</label>
    <input type="text" name="per_plate"
    value="<?php echo esc_attr($per_plate); ?>">
</div>

</div>


<!-- VERIFIED -->
<div class="wm-admin-group">

    <h2>Verified Section</h2>

    <div class="wm-admin-field">

        <label>Verified Text</label>

        <textarea
        name="verified_text"
        rows="5"><?php

        echo esc_textarea($verified_text);

        ?></textarea>

    </div>

</div>



<!-- GALLERY -->
<div class="wm-admin-group">

    <h2>Venue Gallery</h2>

    <div class="wm-admin-field">

        <input
            type="hidden"
            id="venue_gallery"
            name="venue_gallery"
            value="<?php echo esc_attr($venue_gallery); ?>"
        >

        <button
            type="button"
            class="button button-primary"
            id="wm-gallery-upload"
        >
            Select Gallery Images
        </button>

        <div id="wm-gallery-preview">

            <?php

            if (!empty($venue_gallery)) :

                $gallery_ids = explode(',', $venue_gallery);

                foreach ($gallery_ids as $image_id) :

                    ?>

                    <div class="wm-gallery-item"
                         data-id="<?php echo esc_attr($image_id); ?>">

                        <?php
                        echo wp_get_attachment_image(
                            $image_id,
                            'thumbnail'
                        );
                        ?>

                        <button type="button"
                                class="wm-remove-image">

                            Remove

                        </button>

                    </div>

                    <?php

                endforeach;

            endif;

            ?>

        </div>

    </div>

</div>

<?php
}


/* =====================================================
   SAVE VENUE META
===================================================== */

add_action('save_post', function ($post_id) {

    $fields = [

        'rating',
        'reviews',
        'price',
        'guests',
        'tagline',
        'verified_venue',
        'venue_overview',
        'function_spaces',
        'amenities',
        'address',
        'phone',
        'email',
        'website',
        'rooms',
        'per_plate',
        'verified_text',
        'venue_gallery'
    ];

    foreach ($fields as $field) {

        if (isset($_POST[$field])) {

            update_post_meta(
                $post_id,
                $field,
                $_POST[$field]
            );

        }

    }

});


add_action('admin_footer', function () {

global $post;

if (!$post || $post->post_type !== 'venues') {
    return;
}

?>


<script>

jQuery(document).ready(function($){

    let frame;

    $('#wm-gallery-upload').on('click', function(e){

        e.preventDefault();

        if(frame){
            frame.open();
            return;
        }

        frame = wp.media({

            title: 'Select Gallery Images',

            button: {
                text: 'Use Images'
            },

            multiple: true

        });

        frame.on('select', function(){

            let attachments =
                frame.state().get('selection').toJSON();

            let ids = [];

            let preview = '';

            attachments.forEach(function(attachment){

                ids.push(attachment.id);

                preview += `
                    <div class="wm-gallery-item"
                         data-id="${attachment.id}">

                        <img src="${attachment.sizes.thumbnail.url}">

                        <button type="button"
                                class="wm-remove-image">

                            Remove

                        </button>

                    </div>
                `;

            });

            $('#venue_gallery').val(ids.join(','));

            $('#wm-gallery-preview').html(preview);

        });

        frame.open();

    });


    // REMOVE IMAGE
    $(document).on(
        'click',
        '.wm-remove-image',
        function(){

            $(this)
            .closest('.wm-gallery-item')
            .remove();

            let ids = [];

            $('.wm-gallery-item').each(function(){

                ids.push($(this).attr('data-id'));

            });

            $('#venue_gallery').val(ids.join(','));

        }
    );

});

</script>

<?php

});

/* ------------   Ajax filter for venue      ----------------   */
function venue_filter() {

    $search = $_POST['search'] ?? '';

    $location = $_POST['location'] ?? '';

    $args = [
        'post_type' => 'venues',
        'posts_per_page' => -1
    ];

    // SEARCH
    if (!empty($search)) {

        $args['s'] = sanitize_text_field($search);

    }

    // LOCATION
    if (!empty($location)) {

        $args['tax_query'] = [
            [
                'taxonomy' => 'location',
                'field' => 'slug',
                'terms' => sanitize_text_field($location)
            ]
        ];

    }

    $query = new WP_Query($args);

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

    wp_die();

}

add_action(
    'wp_ajax_venue_filter',
    'venue_filter'
);

add_action(
    'wp_ajax_nopriv_venue_filter',
    'venue_filter'
);



/* =========================================================
   LOAD SEO DATA FROM SEO MANAGER
========================================================= */

function wedmate_dynamic_seo_data() {

    return get_option(
        'wedmate_seo_data',
        []
    );

}

/* =========================================================
   YOAST DYNAMIC SEO TITLE
========================================================= */

add_filter('wpseo_title', 'wedmate_dynamic_wpseo_title');

function wedmate_dynamic_wpseo_title($title) {

    /* =========================
       VENDOR PAGES
    ========================= */

    if (is_tax('vendor_category')) {

        $current_category = get_queried_object();

        $current_location = get_query_var('location_filter');

        $seo_key = $current_category->slug;

        if ($current_location) {

            $seo_key .= '-' . $current_location;

        }

        $seo_data = wedmate_dynamic_seo_data();

        // CUSTOM SEO TITLE
        if (!empty($seo_data[$seo_key]['title'])) {

            return $seo_data[$seo_key]['title'];

        }

        // FALLBACK TITLE
        if ($current_location) {

            return 'Best Wedding ' .
                   $current_category->name .
                   ' in ' .
                   ucfirst($current_location) .
                   ' | WedMate';

        }

        return 'Best Wedding ' .
               $current_category->name .
               ' | WedMate';

    }


    /* =========================
       VENUE PAGES
    ========================= */

    if (is_tax('location')) {

        $current_term = get_queried_object();

        $seo_key = 'venue-' . $current_term->slug;

        $seo_data = wedmate_dynamic_seo_data();

        // CUSTOM TITLE
        if (!empty($seo_data[$seo_key]['title'])) {

            return $seo_data[$seo_key]['title'];

        }

        // FALLBACK TITLE
        return 'Best Wedding Venues in ' .
               $current_term->name .
               ' | WedMate';

    }

    return $title;

}



/* =========================================================
   YOAST DYNAMIC META DESCRIPTION
========================================================= */

add_filter('wpseo_metadesc', 'wedmate_dynamic_wpseo_desc');

function wedmate_dynamic_wpseo_desc($desc) {

    if (is_tax('vendor_category')) {

        $current_category = get_queried_object();

        $current_location = get_query_var('location_filter');

        $seo_key = $current_category->slug;

        if ($current_location) {

            $seo_key .= '-' . $current_location;

        }

        $seo_data = wedmate_dynamic_seo_data();

        // CUSTOM DESCRIPTION
        if (!empty($seo_data[$seo_key]['desc'])) {

            return $seo_data[$seo_key]['desc'];

        }

        // FALLBACK DESCRIPTION
        if ($current_location) {

            return 'Find the best wedding ' .
                   strtolower($current_category->name) .
                   ' in ' .
                   ucfirst($current_location) .
                   ' with pricing, reviews and portfolios on WedMate.';

        }

        // CATEGORY ONLY
        return 'Explore top wedding ' .
               strtolower($current_category->name) .
               ' on WedMate.';

    }

    return $desc;

}

/* =========================================================
   SEO MANAGER MENU
========================================================= */

function wedmate_seo_manager_menu() {

    add_menu_page(

        'SEO Manager',

        'SEO Manager',

        'manage_options',

        'wedmate-seo-manager',

        'wedmate_seo_manager_page',

        'dashicons-chart-area',

        25

    );

}

add_action(
    'admin_menu',
    'wedmate_seo_manager_menu'
);

function wedmate_seo_manager_page() {

    $categories = get_terms([
        'taxonomy' => 'vendor_category',
        'hide_empty' => false
    ]);

    $locations = get_terms([
        'taxonomy' => 'location',
        'hide_empty' => false
    ]);


/* =========================
   TOTAL COUNTS
========================= */

$total_vendor_combinations =
    count($categories) * count($locations);

$total_venue_combinations =
    count($locations);

$total_combinations =
    $total_vendor_combinations +
    $total_venue_combinations;

    ?>

    <div class="wrap">

        <h1>SEO Manager</h1>
        
        

        <div class="seo-top-bar">

    <input
        type="text"
        id="seo-search"
        placeholder="Search category or location...">

    <div class="seo-stats">

        <span>
            <strong>Vendor Combinations:</strong>
            <?php echo esc_html($total_vendor_combinations); ?>
        </span>

        <span>
            <strong>Venue Pages:</strong>
            <?php echo esc_html($total_venue_combinations); ?>
        </span>

        <span>
            <strong>Total SEO Rows:</strong>
            <?php echo esc_html($total_combinations); ?>
        </span>

    </div>

</div>

        <h2 class="nav-tab-wrapper">

            <a href="#vendors-tab"
               class="nav-tab nav-tab-active"
               id="vendors-tab-btn">

               Vendors

            </a>

            <a href="#venues-tab"
               class="nav-tab"
               id="venues-tab-btn">

               Venues

            </a>

        </h2>

        <form method="post">

            <?php

            if (isset($_POST['wedmate_save_seo'])) {

                update_option(
                    'wedmate_seo_data',
                    $_POST['seo_data']
                );

                echo '<div class="updated"><p>SEO Data Saved</p></div>';

            }

            $saved_data = get_option(
                'wedmate_seo_data',
                []
            );

            ?>

            <!-- =========================
                 VENDORS TAB
            ========================== -->

            <div id="vendors-tab-content">

                <table class="widefat striped seo-table">

                    <thead>

                        <tr>

                            <th>Category</th>

                            <th>Location</th>

                            <th>H1</th>

                            <th>Meta Title</th>

                            <th>Meta Description</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php

                        foreach ($categories as $category) {

                            foreach ($locations as $location) {

                                $seo_key =
                                    $category->slug .
                                    '-' .
                                    $location->slug;

                                $h1 =
                                    $saved_data[$seo_key]['h1']
                                    ?? '';

                                $title =
                                    $saved_data[$seo_key]['title']
                                    ?? '';

                                $desc =
                                    $saved_data[$seo_key]['desc']
                                    ?? '';

                                ?>

                                <tr class="seo-row">

                                    <td>
                                        <?php echo esc_html($category->name); ?>
                                    </td>

                                    <td>
                                        <?php echo esc_html($location->name); ?>
                                    </td>

                                    <td>

                                        <input
                                            type="text"
                                            name="seo_data[<?php echo esc_attr($seo_key); ?>][h1]"
                                            value="<?php echo esc_attr($h1); ?>"
                                            style="width:100%;">

                                    </td>

                                    <td>

                                        <input
                                            type="text"
                                            name="seo_data[<?php echo esc_attr($seo_key); ?>][title]"
                                            value="<?php echo esc_attr($title); ?>"
                                            style="width:100%;">

                                    </td>

                                    <td>

                                        <textarea
                                            name="seo_data[<?php echo esc_attr($seo_key); ?>][desc]"
                                            rows="3"
                                            style="width:100%;"><?php

                                            echo esc_textarea($desc);

                                        ?></textarea>

                                    </td>

                                </tr>

                                <?php

                            }

                        }

                        ?>

                    </tbody>

                </table>

                <div class="seo-pagination"></div>

            </div>


            <!-- =========================
                 VENUES TAB
            ========================== -->

            <div id="venues-tab-content" style="display:none;">

                <table class="widefat striped seo-table">

                    <thead>

                        <tr>

                            <th>Location</th>

                            <th>H1</th>

                            <th>Meta Title</th>

                            <th>Meta Description</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php

                        foreach ($locations as $location) {

                            $seo_key =
                                'venue-' .
                                $location->slug;

                            $h1 =
                                $saved_data[$seo_key]['h1']
                                ?? '';

                            $title =
                                $saved_data[$seo_key]['title']
                                ?? '';

                            $desc =
                                $saved_data[$seo_key]['desc']
                                ?? '';

                            ?>

                            <tr class="seo-row">

                                <td>
                                    <?php echo esc_html($location->name); ?>
                                </td>

                                <td>

                                    <input
                                        type="text"
                                        name="seo_data[<?php echo esc_attr($seo_key); ?>][h1]"
                                        value="<?php echo esc_attr($h1); ?>"
                                        style="width:100%;">

                                </td>

                                <td>

                                    <input
                                        type="text"
                                        name="seo_data[<?php echo esc_attr($seo_key); ?>][title]"
                                        value="<?php echo esc_attr($title); ?>"
                                        style="width:100%;">

                                </td>

                                <td>

                                    <textarea
                                        name="seo_data[<?php echo esc_attr($seo_key); ?>][desc]"
                                        rows="3"
                                        style="width:100%;"><?php

                                        echo esc_textarea($desc);

                                    ?></textarea>

                                </td>

                            </tr>

                            <?php

                        }

                        ?>

                    </tbody>

                </table>

                <div class="seo-pagination"></div>

            </div>


            <p style="margin-top:20px;">

                <button
                    type="submit"
                    name="wedmate_save_seo"
                    class="button button-primary">

                    Save SEO Data

                </button>

            </p>

        </form>

    </div>

<style>

.seo-top-bar {

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:20px;

    margin:20px 0 26px;

    flex-wrap:wrap;

}

#seo-search {

    width:320px;

    max-width:100%;

    padding:10px 14px;

    border:1px solid #ccd0d4;

    border-radius:6px;

}

.seo-stats {

    display:flex;

    align-items:center;

    gap:18px;

    flex-wrap:wrap;

    font-size:13px;

    color:#50575e;

}

.seo-stats span {

    white-space:nowrap;

}

@media (max-width:782px) {

    .seo-top-bar {

        align-items:flex-start;

        flex-direction:column;

    }

    #seo-search {

        width:100%;

    }

}

</style>

    <script>

    document.addEventListener('DOMContentLoaded', function() {

        /* =========================
           TABS
        ========================== */

        const vendorBtn =
            document.getElementById('vendors-tab-btn');

        const venueBtn =
            document.getElementById('venues-tab-btn');

        const vendorTab =
            document.getElementById('vendors-tab-content');

        const venueTab =
            document.getElementById('venues-tab-content');


        vendorBtn.addEventListener('click', function(e) {

            e.preventDefault();

            vendorTab.style.display = 'block';

            venueTab.style.display = 'none';

            vendorBtn.classList.add('nav-tab-active');

            venueBtn.classList.remove('nav-tab-active');

        });


        venueBtn.addEventListener('click', function(e) {

            e.preventDefault();

            vendorTab.style.display = 'none';

            venueTab.style.display = 'block';

            venueBtn.classList.add('nav-tab-active');

            vendorBtn.classList.remove('nav-tab-active');

        });


        /* =========================
           SEARCH + PAGINATION
        ========================== */

        const rowsPerPage = 50;

        const searchInput =
            document.getElementById('seo-search');

        const tables =
            document.querySelectorAll('.seo-table');


        tables.forEach(function(table) {

            const allRows =
                Array.from(
                    table.querySelectorAll('tbody .seo-row')
                );

            const pagination =
                table.parentElement.querySelector(
                    '.seo-pagination'
                );

            let filteredRows = [...allRows];

            let currentPage = 1;


            function renderTable() {

                allRows.forEach(function(row) {

                    row.style.display = 'none';

                });

                const start =
                    (currentPage - 1) * rowsPerPage;

                const end =
                    start + rowsPerPage;

                filteredRows
                    .slice(start, end)
                    .forEach(function(row) {

                        row.style.display = '';

                    });

                renderPagination();

            }


            function renderPagination() {

                pagination.innerHTML = '';

                const totalPages =
                    Math.ceil(
                        filteredRows.length / rowsPerPage
                    );

                if (totalPages <= 1) return;

                for (let i = 1; i <= totalPages; i++) {

                    const btn =
                        document.createElement('button');

                    btn.innerText = i;

                    btn.classList.add('button');

                    btn.style.marginRight = '6px';

                    btn.style.marginTop = '14px';

                    if (i === currentPage) {

                        btn.classList.add('button-primary');

                    }

                    btn.addEventListener('click', function(e) {

                        e.preventDefault();

                        currentPage = i;

                        renderTable();

                    });

                    pagination.appendChild(btn);

                }

            }


            searchInput.addEventListener('keyup', function() {

                const keyword =
                    this.value.toLowerCase();

                filteredRows =
                    allRows.filter(function(row) {

                        return row.innerText
                            .toLowerCase()
                            .includes(keyword);

                    });

                currentPage = 1;

                renderTable();

            });

            renderTable();

        });

    });

    </script>

    <?php

}
