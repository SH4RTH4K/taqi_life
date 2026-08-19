<?php get_header(); ?>

<div id="content" class="layout-img">
   <div class="feature-header">
      <div class="feature-post-thumbnail">
        <div class="slider-alternate">
          <img src="<?php echo get_stylesheet_directory_uri() . '/assets/images/header-banner.png'; ?>">
        </div>
        <h1 class="post-title feature-header-title"><?php echo(esc_html_e('Archive Post','e-storefront')); ?></h1>
        <?php if ( get_theme_mod('e_storefront_breadcrumb_enable',true) ) : ?>
          <div class="bread_crumb text-center">
            <?php e_storefront_breadcrumb();  ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <div class="container">
    <div class="row">
      <?php if(get_theme_mod('e_storefront_archive_sidebar_layout', 'Right Sidebar') == 'Right Sidebar'){ ?>
      <div class="col-lg-9 col-md-8 mt-5">
        <div class="row">
          <?php
            if ( have_posts() ) :

              while ( have_posts() ) :
                the_post();
                ?>
                <div class="col-lg-6 col-md-12 mb-4"> <!-- Each post will take half width -->
                  <?php get_template_part( 'template-parts/content', get_post_format() ); ?>
                </div>
                <?php
              endwhile;

            else:
              get_template_part( 'nothing-found' );

            endif;

            get_template_part( 'template-parts/pagination' );
          ?>
        </div>
      </div>
      <div class="col-lg-3 col-md-4">
        <?php get_sidebar(); ?>
      </div>
      <?php } elseif(get_theme_mod('e_storefront_archive_sidebar_layout', 'Right Sidebar') == 'Left Sidebar'){ ?>
      <div class="col-lg-3 col-md-4">
        <?php get_sidebar(); ?>
      </div>
      <div class="col-lg-9 col-md-8 mt-5">
        <div class="row">
          <?php
            if ( have_posts() ) :

              while ( have_posts() ) :
                the_post();
                ?>
                <div class="col-lg-6 col-md-12 mb-4"> <!-- Each post will take half width -->
                  <?php get_template_part( 'template-parts/content', get_post_format() ); ?>
                </div>
                <?php
              endwhile;

            else:
              get_template_part( 'nothing-found' );

            endif;

            get_template_part( 'template-parts/pagination' );
          ?>
        </div>
      </div>
      <?php } elseif(get_theme_mod('e_storefront_archive_sidebar_layout', 'Right Sidebar') == 'Three Column'){ ?>
      <div class="col-lg-3 col-md-4">
        <?php get_sidebar(); ?>
      </div>
      <div class="col-lg-6 col-md-6 mt-5">
        <div class="row">
          <?php
            if ( have_posts() ) :

              while ( have_posts() ) :

                the_post();
                get_template_part( 'template-parts/content', get_post_format() );

              endwhile;

            else:

              get_template_part( 'nothing-found' );

            endif;

            get_template_part( 'template-parts/pagination' );
          ?>
        </div>
      </div>
      <div class="col-lg-3 col-md-4 sidebar-area mt-5">
        <?php dynamic_sidebar('e-storefront-sidebar-2'); ?>
      </div>
      <?php } elseif(get_theme_mod('e_storefront_archive_sidebar_layout', 'Right Sidebar') == 'Four Column'){ ?>
      <div class="col-lg-3 col-md-4">
        <?php get_sidebar(); ?>
      </div>
      <div class="col-lg-3 col-md-4 sidebar-area mt-5">
        <?php dynamic_sidebar('e-storefront-sidebar-2'); ?>
      </div>
      <div class="col-lg-3 col-md-4 mt-5">
        <div class="row">
          <?php
            if ( have_posts() ) :

              while ( have_posts() ) :

                the_post();
                get_template_part( 'template-parts/content', get_post_format() );

              endwhile;

            else:

              get_template_part( 'nothing-found' );

            endif;

            get_template_part( 'template-parts/pagination' );
          ?>
        </div>
      </div>
      <div class="col-lg-3 col-md-4 sidebar-area mt-5">
        <?php dynamic_sidebar('e-storefront-sidebar-3'); ?>
      </div>
      <?php } elseif(get_theme_mod('e_storefront_archive_sidebar_layout', 'Right Sidebar') == 'Grid Layout Without Sidebar'){ ?>
        <div class="row mt-5">
              <?php
                if ( have_posts() ) :

                  while ( have_posts() ) :

                    the_post();
                    get_template_part( 'template-parts/content-grid' );

                  endwhile;

                else:

                  get_template_part( 'nothing-found' );

                endif;

                get_template_part( 'template-parts/pagination' );
              ?>
        </div>
      <?php } elseif(get_theme_mod('e_storefront_archive_sidebar_layout', 'Right Sidebar') == 'Grid Layout With Right Sidebar'){ ?>
        <div class="row">
          <div class="col-lg-9 col-md-8 mt-5">
            <div class="row">
              <?php
                if ( have_posts() ) :

                  while ( have_posts() ) :

                    the_post();
                    get_template_part( 'template-parts/content-grid' );

                  endwhile;

                else:

                  get_template_part( 'nothing-found' );

                endif;

                get_template_part( 'template-parts/pagination' );
              ?>
            </div>
          </div>
          <div class="col-lg-3 col-md-4">
            <?php get_sidebar(); ?>
          </div>
        </div>
      <?php } elseif(get_theme_mod('e_storefront_archive_sidebar_layout', 'Right Sidebar') == 'Grid Layout With Left Sidebar'){ ?>
        <div class="row">
          <div class="col-lg-3 col-md-4">
            <?php get_sidebar(); ?>
          </div>
          <div class="col-lg-9 col-md-8 mt-5">
            <div class="row">
              <?php
                if ( have_posts() ) :

                  while ( have_posts() ) :

                    the_post();
                    get_template_part( 'template-parts/content-grid' );

                  endwhile;

                else:

                  get_template_part( 'nothing-found' );

                endif;

                get_template_part( 'template-parts/pagination' );
              ?>
            </div>
          </div>
        </div>
      <?php } ?>
    </div>
  </div>
</div>

<?php get_footer(); ?>