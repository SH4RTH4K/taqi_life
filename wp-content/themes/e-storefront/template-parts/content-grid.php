<?php
$e_storefront_archive_element_sortable = get_theme_mod('e_storefront_archive_element_sortable', array('option1', 'option2', 'option3', 'option4', 'option5'));
?>

<div class="col-lg-3 col-md-4">
<div class="blog-grid-layout">
    <div id="post-<?php the_ID(); ?>" <?php post_class('post-box mb-4 wow zoomIn'); ?>>
        <?php foreach ($e_storefront_archive_element_sortable as $value) : ?>
            
            <?php if ($value === 'option1') : ?>
                <div class="post-thumbnail mb-2">
                    <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
                        <?php if (has_post_thumbnail()) : ?>
                            <?php the_post_thumbnail(); ?>
                        <?php else : ?>
                            <div class="slider-alternate">
                                <img src="<?php echo get_stylesheet_directory_uri() . '/assets/images/banner.png'; ?>">
                            </div>
                        <?php endif; ?>
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($value === 'option2') : ?>
                <div class="post-meta my-3">
                    <a href="<?php echo esc_url(get_author_posts_url(get_the_author_meta('ID'))); ?>">
                    <i class="far fa-user me-2"></i><?php the_author(); ?>
                    </a>
                    <span>
                        <i class="far fa-comments me-2"></i>
                        <?php comments_number(esc_html__('0', 'e-storefront'), esc_html__('1', 'e-storefront'), esc_html__('%', 'e-storefront')); ?>
                        <?php esc_html_e('comments', 'e-storefront'); ?>
                    </span>
                    <span><?php echo esc_html(e_storefront_edit_link()); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($value === 'option3') : ?>
                <h3 class="post-title mb-3 mt-0">
                    <a href="<?php the_permalink(); ?>"> <?php the_title(); ?> </a>
                </h3>
            <?php endif; ?>

            <?php if ($value === 'option4') : ?>
                <div class="post-content mb-2">
                    <?php echo wp_trim_words(get_the_content(), get_theme_mod('e_storefront_post_excerpt_number', 10)); ?>
                </div>
            <?php endif; ?>

            <?php if ($value === 'option5') : ?>
              <div class="more-btn mt-4 mb-4">
                <a class="p-1" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read More', 'e-storefront' ); ?><span><i class="fas fa-long-arrow-alt-right"></i></span></a>
              </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
</div>