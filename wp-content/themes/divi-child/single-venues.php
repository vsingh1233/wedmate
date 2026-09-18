<?php get_header(); ?>

<div class="venue-single">

    <?php
    get_template_part(
        'template-parts/venue/hero'
    );

    get_template_part(
        'template-parts/venue/content'
    );
    
    get_template_part(
    'template-parts/venue/related'
);
    
    ?>
    

</div>

<?php get_footer(); ?>