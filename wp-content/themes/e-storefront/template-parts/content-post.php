<?php
  global $post;
?>

<div id="post-<?php the_ID(); ?>" <?php post_class('post-single mb-4'); ?>>
  <div class="post-content">
    <?php
      the_content();
      the_tags('<div class="post-tags"><strong>'.esc_html__('Tags:','e-storefront').'</strong> ', ', ', '</div>');
      if ( get_the_category_list() ) : ?>
          <div class="post-categories">
              <strong><?php esc_html_e( 'Categories:', 'e-storefront' ); ?></strong>
              <?php the_category( ', ' ); ?>
          </div>
      <?php endif;
    ?>
  </div>
</div>