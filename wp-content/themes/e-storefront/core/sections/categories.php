<?php if ( get_theme_mod('e_storefront_testimonial_section_enable',true) ) : ?>
	<div id="trending" class="pt-5">
		<div class="container">
			<div class="categories-box">
				<div class="categories-heading">
					<?php if ( get_theme_mod('e_storefront_top_categories_heading') ) : ?>
						<h4 class="mb-0"><?php echo esc_html(get_theme_mod('e_storefront_top_categories_heading'));?></h4>
					<?php endif; ?>
				</div>
				<div class="cat-box pt-4">
					<div class="owl-carousel m-0 ser-box">
				        <?php if (class_exists('woocommerce')) { ?>
				          <?php
				            $e_storefront_prod_categories = get_terms('product_cat', array(
				              'orderby'    => 'name',
				              'order'      => 'ASC',
				              'hide_empty' => 0
				            ));
				            foreach ($e_storefront_prod_categories as $e_storefront_prod_cat) :
				            $e_storefront_cat_thumb_id = get_term_meta($e_storefront_prod_cat->term_id, 'thumbnail_id', true);
				            $e_storefront_cat_thumb_url = $e_storefront_cat_thumb_id ? wp_get_attachment_thumb_url($e_storefront_cat_thumb_id) : ''; 
				            $e_storefront_term_link = get_term_link($e_storefront_prod_cat, 'product_cat');
				          ?>
				          <div class="service-box">
				              <div class="feature-box m-0">
				                <div class="ser-content">
				                  <div class="service-icon">
				                    <?php if ($e_storefront_cat_thumb_url) : ?>
				                      <img src="<?php echo esc_url($e_storefront_cat_thumb_url); ?>" alt="<?php echo esc_html($e_storefront_prod_cat->name); ?>" />
				                    <?php else : ?>
				                      <img src="<?php echo esc_url(get_template_directory_uri()); ?>/assets/images/right-2.png" alt="" />
				                    <?php endif; ?>
				                  </div>
				                  <h4 class="mb-0 mt-2 text-center"><a href="<?php echo esc_url($e_storefront_term_link); ?>"><?php echo esc_html($e_storefront_prod_cat->name); ?></a></h4>
				                </div>
				              </div>
				            </div>
				          <?php endforeach; wp_reset_query(); ?>
				        <?php } ?>  
				    </div>
			    </div>
			</div>
		</div>
	</div>
<?php endif; ?>
<?php if ( get_theme_mod('e_storefront_product_section_enable',false) ) : ?>
	<div id="hot_products" class="py-5">
		<div class="container">
	    <?php if ( get_theme_mod('e_storefront_products_short_heading') ) : ?>
	      <h4 class="text-center short-heading mb-4"><?php echo esc_html(get_theme_mod('e_storefront_products_short_heading'));?></h4>
	    <?php endif; ?>
	    <div class="row">
	      <?php
	        $e_storefront_product_data = get_theme_mod('e_storefront_products_category');
	        if ( class_exists( 'WooCommerce' ) ) {
	          $e_storefront_args = array(
	          'post_type' => 'product',
	          'posts_per_page' => 8,
	          'product_cat' => $e_storefront_product_data,
	          'order' => 'ASC'
	        );
	        $loop = new WP_Query( $e_storefront_args );
	        while ( $loop->have_posts() ) : $loop->the_post(); global $product; ?>
	      	  <div class="col-lg-3 col-md-6 col-sm-6 ">
	            <div class="product-details mb-4">
	              	<div class="product-image">
	                	<figure>
	                  		<?php if (has_post_thumbnail( $loop->post->ID )) echo get_the_post_thumbnail($loop->post->ID, 'shop_catalog'); else echo '<img src="'.esc_url(wc_placeholder_img_src()).'" />'; ?>
	                	</figure>
	              	</div>
	              	<h4 class="product-text"><a href="<?php echo esc_url(get_permalink( $loop->post->ID )); ?>"><?php the_title(); ?></a></h4>
	              	<div class="price-review">
	                	<p class="mb-2 <?php echo esc_attr( apply_filters( 'woocommerce_product_price_class', 'price' ) ); ?>"><?php echo $product->get_price_html(); ?></p>
	                	<?php if( $product->is_type( 'simple' ) ){ woocommerce_template_loop_rating( $loop->post, $product ); } ?>
	              	</div>
	              	<div class="cart-button mt-2">
                  		<span class="icon" href="<?php if(function_exists('wc_get_cart_url')){ echo esc_url(wc_get_cart_url()); } ?>"><span class="button1"><?php if( $product->is_type( 'simple' ) ) { woocommerce_template_loop_add_to_cart(  $loop->post, $product );} ?></span></span>
            		</div>
            	</div>
	          </div>
	        <?php endwhile; wp_reset_postdata(); ?>
	      <?php } ?>
		  </div>
		</div>
	</div>
<?php endif; ?>