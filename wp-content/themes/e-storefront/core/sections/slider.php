<?php if ( get_theme_mod('e_storefront_category_box_enable',true) ) : ?>
<?php $e_storefront_args = array(
  'post_type' => 'post',
  'post_status' => 'publish',
  'category_name' =>  get_theme_mod('e_storefront_blog_slide_category'),
  'posts_per_page' => get_theme_mod('e_storefront_blog_slide_number'),
); ?>
<div class="search-box py-4">
  <div class="container">
    <div class="row box-search">
      <div class="col-lg-5 col-md-12 col-sm-12 col-12 align-self-center">
        <?php if (class_exists('woocommerce')) : ?>
          <div class="search-box-main">
            <div class="box-categories">
                <button class="categories-button">
                  <?php esc_html_e('All Categories', 'e-storefront'); ?>
                  <i class="fas fa-caret-down"></i>
                </button>
                <div class="header-product-categories">
                  <?php
                  $product_categories = get_terms(array(
                      'taxonomy'   => 'product_cat',
                      'orderby'    => 'title',
                      'order'      => 'ASC',
                      'hide_empty' => false,
                  ));

                  if (!empty($product_categories)) :
                      foreach ($product_categories as $category) :
                          ?>
                          <h4><a href="<?php echo esc_url(get_term_link($category)); ?>"><?php echo esc_html($category->name); ?></a></h4>
                          <?php
                          $products = new WP_Query(array(
                              'posts_per_page' => -1,
                              'post_type'      => 'product',
                              'orderby'        => 'title',
                              'order'          => 'ASC',
                              'tax_query'      => array(
                                  array(
                                      'taxonomy' => 'product_cat',
                                      'field'    => 'slug',
                                      'terms'    => $category->slug,
                                  ),
                              ),
                          ));
                      endforeach;
                  endif;
                  ?>
                </div>
            </div>
            <div class="main-search-box">
              <?php get_product_search_form(); ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
      <div class="col-lg-7 col-md-12 col-sm-12 col-12 align-self-center text-md-end text-center">
        <?php if ( get_theme_mod('e_storefront_slider_track_button_url') || get_theme_mod('e_storefront_slider_track_button_text')) : ?>       
          <span class="track-btn">
            <a href="<?php echo esc_url(get_theme_mod('e_storefront_slider_track_button_url'));?>"><i class="fas fa-map-marker-alt me-2"></i><?php echo esc_html(get_theme_mod('e_storefront_slider_track_button_text'));?></a>
          </span>
        <?php endif; ?>
        <?php if ( get_theme_mod('e_storefront_slider_compare_button_url') || get_theme_mod('e_storefront_slider_compare_button_text')) : ?>       
          <span class="track-btn">
            <a href="<?php echo esc_url(get_theme_mod('e_storefront_slider_compare_button_url'));?>"><i class="fas fa-sync-alt me-2"></i><?php echo esc_html(get_theme_mod('e_storefront_slider_compare_button_text'));?></a>
          </span>
        <?php endif; ?>
        <?php if ( get_theme_mod('e_storefront_slider_support_button_url') || get_theme_mod('e_storefront_slider_support_button_text')) : ?>       
          <span class="track-btn">
            <a href="<?php echo esc_url(get_theme_mod('e_storefront_slider_support_button_url'));?>"><i class="fas fa-headphones me-2"></i><?php echo esc_html(get_theme_mod('e_storefront_slider_support_button_text'));?></a>
          </span>
        <?php endif; ?>
        <?php if ( get_theme_mod('e_storefront_slider_help_button_url') || get_theme_mod('e_storefront_slider_help_button_text')) : ?>       
          <span class="track-btn">
            <a href="<?php echo esc_url(get_theme_mod('e_storefront_slider_help_button_url'));?>"><i class="fas fa-info-circle me-2"></i><?php echo esc_html(get_theme_mod('e_storefront_slider_help_button_text'));?></a>
          </span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php if ( get_theme_mod('e_storefront_blog_box_enable',true) ) :
  $e_storefront_slider_categories_enabled = get_theme_mod('e_storefront_slider_categories', true); 
  $e_storefront_col_class = $e_storefront_slider_categories_enabled ? 'col-lg-6 col-md-4' : 'col-lg-9 col-md-8'; ?>
  <div class="slider">
    <div class="container">
      <div class="slider-box">
        <div class="row">
          <?php if ( $e_storefront_slider_categories_enabled ) : ?>
            <div class="col-lg-3 col-md-4 ">
              <div class="slider-left-box">
                <?php if ( get_theme_mod('e_storefront_slider_categories_heading')) : ?>
                  <h3 class="mb-3"> <?php echo esc_html(get_theme_mod('e_storefront_slider_categories_heading'));?></h3>
                <?php endif; ?>
                <div class="slider-product-categories">
                  <?php
                  $product_categories = get_terms(array(
                      'taxonomy'   => 'product_cat',
                      'orderby'    => 'title',
                      'order'      => 'ASC',
                      'hide_empty' => false,
                  ));

                  if (!empty($product_categories)) :
                      foreach ($product_categories as $category) :
                          // Retrieve the Font Awesome icon class for the category
                          $icon_class = get_term_meta($category->term_id, 'product_cat_icon', true);
                          ?>
                          <h4 class="mb-3">
                              <a href="<?php echo esc_url(get_term_link($category)); ?>">
                                  <?php if ($icon_class): ?>
                                      <i class="<?php echo esc_attr($icon_class); ?>"></i>
                                  <?php endif; ?>
                                  <?php echo esc_html($category->name); ?>
                              </a>
                          </h4>
                          <?php
                          $products = new WP_Query(array(
                              'posts_per_page' => -1,
                              'post_type'      => 'product',
                              'orderby'        => 'title',
                              'order'          => 'ASC',
                              'tax_query'      => array(
                                  array(
                                      'taxonomy' => 'product_cat',
                                      'field'    => 'slug',
                                      'terms'    => $category->slug,
                                  ),
                              ),
                          ));
                      endforeach;
                  endif;
                  ?>
                </div>
              </div>
            </div>
          <?php endif; ?>
          <div class="<?php echo esc_attr($e_storefront_col_class); ?>">
            <div class="slider-inner-banner">
              <div class="owl-carousel">
                <?php $e_storefront_arr_posts = new WP_Query( $e_storefront_args );
                $i = 1;
                if ( $e_storefront_arr_posts->have_posts() ) :
                  while ( $e_storefront_arr_posts->have_posts() ) :
                    $e_storefront_arr_posts->the_post();
                    ?>
                    <div class="sldier-box">
                      <?php
                        if ( has_post_thumbnail() ) :
                          the_post_thumbnail();
                        else:
                          ?>
                          <div class="slider-alternate">
                            <img src="<?php echo get_stylesheet_directory_uri() . '/assets/images/right-2.png'; ?>">
                          </div>
                          <?php
                        endif;
                      ?>
                      <div class="slider-owl-position">
                        <div class="blog_box_inner">
                          <h3 class="mb-4"><?php the_title(); ?></h3>
                          <p class="mb-5 content"><?php echo wp_trim_words(get_the_content(), 20); ?></p>
                          <p class="slider-button mb-0">
                            <a href="<?php echo esc_url(get_permalink($post->ID)); ?>"><?php esc_html_e('BUY NOW','e-storefront'); ?></a>
                          </p>
                        </div>
                      </div>
                    </div>
                  <?php
                  $i++;
                endwhile;
                wp_reset_postdata();
                endif; ?>
              </div>
            </div>
          </div>
          <div class="col-lg-3 col-md-4">
            <div class="slider-right-box">
              <div class="right-box-1">
                <div class="right-alternate">
                  <?php if ( get_theme_mod('e_storefront_slider_right_image_1') ) : ?>
                    <img src="<?php echo esc_url(get_theme_mod('e_storefront_slider_right_image_1'));?>">
                  <?php else: ?>
                    <div class="right-alternate">
                      <img src="<?php echo get_stylesheet_directory_uri() . '/assets/images/right-1.png'; ?>">
                    </div>
                  <?php endif; ?>
                  <div class="right_alternate_content">
                    <?php if ( get_theme_mod('e_storefront_slider_right_box_1_short_heading') ) : ?>
                      <h6 class="mb-3"><?php echo esc_html(get_theme_mod('e_storefront_slider_right_box_1_short_heading'));?></h6>
                    <?php endif; ?>
                    <?php if ( get_theme_mod('e_storefront_slider_right_box_1_heading') ) : ?>
                      <h3 class="mb-5 content"><?php echo esc_html(get_theme_mod('e_storefront_slider_right_box_1_heading'));?></h3>
                    <?php endif; ?>
                    <?php if ( get_theme_mod('e_storefront_slider_right_box_1_button_url') ) : ?>
                      <p class="alternate-button mb-0">
                        <a href="<?php echo esc_url(get_theme_mod('e_storefront_slider_right_box_1_button_url'));?>"><?php esc_html_e('Shop Now','e-storefront'); ?></a>
                      </p>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <div class="right-box-1">
                <div class="right-alternate">
                  <?php if ( get_theme_mod('e_storefront_slider_right_image_2') ) : ?>
                    <img src="<?php echo esc_url(get_theme_mod('e_storefront_slider_right_image_2'));?>">
                  <?php else: ?>
                    <div class="right-alternate">
                      <img src="<?php echo get_stylesheet_directory_uri() . '/assets/images/right-2.png'; ?>">
                    </div>
                  <?php endif; ?>
                  <div class="right_alternate_content_2">
                    <?php if ( get_theme_mod('e_storefront_slider_right_box_2_short_heading') ) : ?>
                      <h5 class="mb-1"><?php echo esc_html(get_theme_mod('e_storefront_slider_right_box_2_short_heading'));?></h5>
                    <?php endif; ?>
                    <?php if ( get_theme_mod('e_storefront_slider_right_box_2_short_heading') ) : ?>
                      <h4 class="mb-5 content"><?php echo esc_html(get_theme_mod('e_storefront_slider_right_box_2_heading'));?></h4>
                    <?php endif; ?>
                    <?php if ( get_theme_mod('e_storefront_slider_right_box_2_price') ) : ?>
                      <div class="alternate-price mb-0">
                        <?php esc_html_e('from','e-storefront'); ?>
                        <p><?php echo esc_html(get_theme_mod('e_storefront_slider_right_box_2_price'));?></p>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>