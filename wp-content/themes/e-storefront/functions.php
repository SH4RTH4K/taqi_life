<?php

/*-----------------------------------------------------------------------------------*/
/* Enqueue script and styles */
/*-----------------------------------------------------------------------------------*/

function e_storefront_enqueue_google_fonts() {

	require_once get_theme_file_path( 'core/includes/wptt-webfont-loader.php' );

	wp_enqueue_style(
		'google-fonts-inter',
		wptt_get_webfont_url( 'https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap' ),
		array(),
		'1.0'
	);

	wp_enqueue_style(
		'google-fonts-public-sans',
		wptt_get_webfont_url( 'https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,100..900;1,100..900&display=swap' ),
		array(),
		'1.0'
	);
	
}
add_action( 'wp_enqueue_scripts', 'e_storefront_enqueue_google_fonts' );

if (!function_exists('e_storefront_enqueue_scripts')) {

	function e_storefront_enqueue_scripts() {

		wp_enqueue_style(
			'bootstrap-css',
			get_template_directory_uri() . '/css/bootstrap.css',
			array(),'4.5.0'
		);

		wp_enqueue_style(
			'fontawesome-css',
			get_template_directory_uri() . '/css/fontawesome-all.css',
			array(),'4.5.0'
		);

		wp_enqueue_style(
			'owl.carousel-css',
			get_template_directory_uri() . '/css/owl.carousel.css',
			array(),'2.3.4'
		);

		wp_enqueue_style('e-storefront-style', get_stylesheet_uri(), array() );

		wp_enqueue_style('dashicons');

		wp_style_add_data('e-storefront-style', 'rtl', 'replace');

		wp_enqueue_style(
			'e-storefront-media-css',
			get_template_directory_uri() . '/css/media.css',
			array(),'2.3.4'
		);

		wp_enqueue_style(
			'e-storefront-woocommerce-css',
			get_template_directory_uri() . '/css/woocommerce.css',
			array(),'2.3.4'
		);

		wp_enqueue_script(
			'e-storefront-navigation',
			get_template_directory_uri() . '/js/navigation.js',
			FALSE,
			'1.0',
			TRUE
		);

		wp_enqueue_script(
			'owl.carousel-js',
			get_template_directory_uri() . '/js/owl.carousel.js',
			array('jquery'),
			'2.3.4',
			TRUE
		);

		wp_enqueue_script(
			'e-storefront-script',
			get_template_directory_uri() . '/js/script.js',
			array('jquery'),
			'1.0',
			TRUE
		);

		if ( get_theme_mod( 'e_storefront_animation_enabled', true ) ) {
		        wp_enqueue_script(
		            'e-storefront-wow-script',
		            get_template_directory_uri() . '/js/wow.js',
		            array( 'jquery' ),
		            '1.0',
		            true
		        );

		        wp_enqueue_style(
		            'e-storefront-animate',
		            get_template_directory_uri() . '/css/animate.css',
		            array(),
		            '4.1.1'
		        );
		    }


		if ( is_singular() ) wp_enqueue_script( 'comment-reply' );

		$e_storefront_css = '';

		if ( get_header_image() ) :

			$e_storefront_css .=  '
				.header-outter, .page-template-frontpage .header-outter{
					background-image: url('.esc_url(get_header_image()).');
					-webkit-background-size: cover !important;
					-moz-background-size: cover !important;
					-o-background-size: cover !important;
					background-size: cover !important;
				}';

		endif;

		wp_add_inline_style( 'e-storefront-style', $e_storefront_css );

		// Theme Customize CSS.
		require get_template_directory(). '/core/includes/inline.php';
		wp_add_inline_style( 'e-storefront-style',$e_storefront_custom_css );

	}

	add_action( 'wp_enqueue_scripts', 'e_storefront_enqueue_scripts' );
}

/*-----------------------------------------------------------------------------------*/
/* Setup theme */
/*-----------------------------------------------------------------------------------*/

if (!function_exists('e_storefront_after_setup_theme')) {

	function e_storefront_after_setup_theme() {

		load_theme_textdomain( 'e-storefront', get_stylesheet_directory() . '/languages' );
		if ( ! isset( $e_storefront_content_width ) ) $e_storefront_content_width = 900;

		register_nav_menus( array(
			'main-menu' => esc_html__( 'Main Menu', 'e-storefront' ),
		));

		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'woocommerce' );
		add_theme_support( 'align-wide' );
		add_theme_support('title-tag');
		add_theme_support('automatic-feed-links');
		add_theme_support( 'wp-block-styles' );
		add_theme_support('post-thumbnails');
		add_theme_support( 'custom-background', array(
		  'default-color' => 'ffffff'
		));

		add_theme_support( 'custom-logo', array(
			'height'      => 70,
			'width'       => 70,
			'flex-height' => true,
			'flex-width'  => true,
		) );

		add_theme_support( 'custom-header', array(
			'header-text' => false,
			'width' => 1920,
			'height' => 100,
			'flex-height' => true,
			'flex-width' => true,
		));

		add_theme_support( 'html5', array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
		) );

		add_theme_support( 'post-formats', array('image','video','gallery','audio',) );

		add_editor_style( array( '/css/editor-style.css' ) );

	}

	add_action( 'after_setup_theme', 'e_storefront_after_setup_theme', 999 );

}

function e_storefront_domain_setting() {

require get_template_directory() .'/core/includes/customizer-notice/e-storefront-customizer-notify.php';
require get_template_directory() .'/core/includes/main.php';
require get_template_directory() .'/core/includes/theme-breadcrumb.php';
require get_template_directory() . '/core/includes/customizer.php';
require get_template_directory() . '/core/includes/importer/config.php';
load_template( trailingslashit( get_template_directory() ) . '/core/includes/class-upgrade-pro.php' );

}

add_action('after_setup_theme', 'e_storefront_domain_setting');

/*-----------------------------------------------------------------------------------*/
/* Enqueue theme logo style */
/*-----------------------------------------------------------------------------------*/
function e_storefront_logo_resizer() {

    $e_storefront_theme_logo_size_css = '';
    $e_storefront_logo_resizer = get_theme_mod('e_storefront_logo_resizer');

	$e_storefront_theme_logo_size_css = '
		.custom-logo{
			height: '.esc_attr($e_storefront_logo_resizer).'px !important;
			width: '.esc_attr($e_storefront_logo_resizer).'px !important;
		}
	';
    wp_add_inline_style( 'e-storefront-style',$e_storefront_theme_logo_size_css );

}
add_action( 'wp_enqueue_scripts', 'e_storefront_logo_resizer' );

/*-----------------------------------------------------------------------------------*/
/* Enqueue Global color style */
/*-----------------------------------------------------------------------------------*/
function e_storefront_global_color() {

    $e_storefront_theme_color_css = '';
    $e_storefront_copyright_bg = get_theme_mod('e_storefront_copyright_bg');

	$e_storefront_theme_color_css = '
    	.copyright {
			background: '.esc_attr($e_storefront_copyright_bg).';
		}
	';
    wp_add_inline_style( 'e-storefront-style',$e_storefront_theme_color_css );
    wp_add_inline_style( 'e-storefront-woocommerce-css',$e_storefront_theme_color_css );

}
add_action( 'wp_enqueue_scripts', 'e_storefront_global_color' );

/*-----------------------------------------------------------------------------------*/
/* Get post comments */
/*-----------------------------------------------------------------------------------*/

if (!function_exists('e_storefront_comment')) :
    /**
     * Template for comments and pingbacks.
     *
     * Used as a callback by wp_list_comments() for displaying the comments.
     */
    function e_storefront_comment($comment, $args, $depth){

        if ('pingback' == $comment->comment_type || 'trackback' == $comment->comment_type) : ?>

            <li id="comment-<?php comment_ID(); ?>" <?php comment_class('media'); ?>>
            <div class="comment-body">
                <?php esc_html_e('Pingback:', 'e-storefront');
                comment_author_link(); ?><?php edit_comment_link(__('Edit', 'e-storefront'), '<span class="edit-link">', '</span>'); ?>
            </div>

        <?php else : ?>

        <li id="comment-<?php comment_ID(); ?>" <?php comment_class(empty($args['has_children']) ? '' : 'parent'); ?>>
            <article id="div-comment-<?php comment_ID(); ?>" class="comment-body media mb-4">
                <a class="pull-left" href="#">
                    <?php if (0 != $args['avatar_size']) echo get_avatar($comment, $args['avatar_size']); ?>
                </a>
                <div class="media-body">
                    <div class="media-body-wrap card">
                        <div class="card-header">
                            <h5 class="mt-0"><?php /* translators: %s: author */ printf('<cite class="fn">%s</cite>', get_comment_author_link() ); ?></h5>
                            <div class="comment-meta">
                                <a href="<?php echo esc_url(get_comment_link($comment->comment_ID)); ?>">
                                    <time datetime="<?php comment_time('c'); ?>">
                                        <?php /* translators: %s: Date */ printf( esc_attr('%1$s at %2$s', '1: date, 2: time', 'e-storefront'), esc_attr( get_comment_date() ), esc_attr( get_comment_time() ) ); ?>
                                    </time>
                                </a>
                                <?php edit_comment_link( __( 'Edit', 'e-storefront' ), '<span class="edit-link">', '</span>' ); ?>
                            </div>
                        </div>

                        <?php if ('0' == $comment->comment_approved) : ?>
                            <p class="comment-awaiting-moderation"><?php esc_html_e('Your comment is awaiting moderation.', 'e-storefront'); ?></p>
                        <?php endif; ?>

                        <div class="comment-content card-block">
                            <?php comment_text(); ?>
                        </div>

                        <?php comment_reply_link(
                            array_merge(
                                $args, array(
                                    'add_below' => 'div-comment',
                                    'depth' => $depth,
                                    'max_depth' => $args['max_depth'],
                                    'before' => '<footer class="reply comment-reply card-footer">',
                                    'after' => '</footer><!-- .reply -->'
                                )
                            )
                        ); ?>
                    </div>
                </div>
            </article>

            <?php
        endif;
    }
endif; // ends check for e_storefront_comment()

if (!function_exists('e_storefront_widgets_init')) {

	function e_storefront_widgets_init() {

		register_sidebar(array(

			'name' => esc_html__('Sidebar','e-storefront'),
			'id'   => 'e-storefront-sidebar',
			'description'   => esc_html__('This sidebar will be shown next to the content.', 'e-storefront'),
			'before_widget' => '<div id="%1$s" class="sidebar-widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h4 class="title">',
			'after_title'   => '</h4>'

		));

		register_sidebar(array(

			'name' => esc_html__('Sidebar 2','e-storefront'),
			'id'   => 'e-storefront-sidebar-2',
			'description'   => esc_html__('This sidebar will be shown next to the content.', 'e-storefront'),
			'before_widget' => '<div id="%1$s" class="sidebar-widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h4 class="title">',
			'after_title'   => '</h4>'

		));

		register_sidebar(array(

			'name' => esc_html__('Sidebar 3','e-storefront'),
			'id'   => 'e-storefront-sidebar-3',
			'description'   => esc_html__('This sidebar will be shown next to the content.', 'e-storefront'),
			'before_widget' => '<div id="%1$s" class="sidebar-widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h4 class="title">',
			'after_title'   => '</h4>'

		));

		register_sidebar(array(

			'name' => esc_html__('Footer Sidebar','e-storefront'),
			'id'   => 'e-storefront-footer-sidebar',
			'description'   => esc_html__('This sidebar will be shown next at the bottom of your content.', 'e-storefront'),
			'before_widget' => '<div id="%1$s" class="col-lg-3 col-md-3 %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h4 class="title">',
			'after_title'   => '</h4>'

		));

	}

	add_action( 'widgets_init', 'e_storefront_widgets_init' );

}

function e_storefront_get_categories_select() {
	$teh_cats = get_categories();
	$results = array();
	$count = count($teh_cats);
	for ($i=0; $i < $count; $i++) {
	if (isset($teh_cats[$i]))
  		$results[$teh_cats[$i]->slug] = $teh_cats[$i]->name;
	else
  		$count++;
	}
	return $results;
}

function e_storefront_sanitize_select( $input, $setting ) {
	// Ensure input is a slug
	$input = sanitize_key( $input );

	// Get list of choices from the control
	// associated with the setting
	$choices = $setting->manager->get_control( $setting->id )->choices;

	// If the input is a valid key, return it;
	// otherwise, return the default
	return ( array_key_exists( $input, $choices ) ? $input : $setting->default );
}

// Change number or products per row to 3
add_filter('loop_shop_columns', 'e_storefront_loop_columns');
if (!function_exists('e_storefront_loop_columns')) {
	function e_storefront_loop_columns() {
		$e_storefront_columns = get_theme_mod( 'e_storefront_per_columns', 3 );
		return $e_storefront_columns;
	}
}

//Change number of products that are displayed per page (shop page)
add_filter( 'loop_shop_per_page', 'e_storefront_per_page', 20 );
function e_storefront_per_page( $e_storefront_cols ) {
  	$e_storefront_cols = get_theme_mod( 'e_storefront_product_per_page', 9 );
	return $e_storefront_cols;
}

// Add filter to modify the number of related products
add_filter( 'woocommerce_output_related_products_args', 'e_storefront_products_args' );
function e_storefront_products_args( $args ) {
    $args['posts_per_page'] = get_theme_mod( 'custom_related_products_number', 6 );
    $args['columns'] = get_theme_mod( 'custom_related_products_number_per_row', 3 );
    return $args;
}

// Add Font Awesome icon field to WooCommerce category add form
function e_storefront_add_product_cat_fontawesome_icon_field() {
    ?>
    <div class="form-field term-group">
        <label for="product-cat-icon"><?php _e('Category Icon (Font Awesome)', 'e-storefront'); ?></label>
        <input type="text" name="product_cat_icon" id="product-cat-icon" value="" placeholder="e.g., fa fa-star" />
        <p class="description"><?php _e('Enter the Font Awesome icon class for this category (e.g., fa fa-star)', 'e-storefront'); ?></p>
    </div>
    <?php
}
add_action('product_cat_add_form_fields', 'e_storefront_add_product_cat_fontawesome_icon_field', 10, 2);

// Add Font Awesome icon field to WooCommerce category edit form
function e_storefront_edit_product_cat_fontawesome_icon_field($term) {
    $icon = get_term_meta($term->term_id, 'product_cat_icon', true);
    ?>
    <tr class="form-field term-group-wrap">
        <th scope="row"><label for="product-cat-icon"><?php _e('Category Icon (Font Awesome)', 'e-storefront'); ?></label></th>
        <td>
            <input type="text" name="product_cat_icon" id="product-cat-icon" value="<?php echo esc_attr($icon); ?>" placeholder="e.g., fa fa-star" />
            <p class="description"><?php _e('Enter the Font Awesome icon class for this category (e.g., fa fa-star)', 'e-storefront'); ?></p>
        </td>
    </tr>
    <?php
}
add_action('product_cat_edit_form_fields', 'e_storefront_edit_product_cat_fontawesome_icon_field', 10, 2);

// Save the Font Awesome icon field value for WooCommerce categories
function e_storefront_edit_save_product_cat_fontawesome_icon_field($term_id) {
    if (isset($_POST['product_cat_icon']) && '' !== $_POST['product_cat_icon']) {
        update_term_meta($term_id, 'product_cat_icon', sanitize_text_field($_POST['product_cat_icon']));
    } else {
        delete_term_meta($term_id, 'product_cat_icon');
    }
}
add_action('created_product_cat', 'e_storefront_edit_save_product_cat_fontawesome_icon_field', 10, 2);
add_action('edited_product_cat', 'e_storefront_edit_save_product_cat_fontawesome_icon_field', 10, 2);


// edit link option
if (!function_exists('e_storefront_edit_link')) :

    function e_storefront_edit_link($view = 'default'){
        global $post;
            edit_post_link(
                sprintf(
                    wp_kses(
                    /* translators: %s: Name of current post. Only visible to screen readers */
                        __('Edit <span class="screen-reader-text">%s</span>', 'e-storefront'),
                        array(
                            'span' => array(
                                'class' => array(),
                            ),
                        )
                    ),
                    get_the_title()
                ),
                '<span class="edit-link"><i class="fas fa-edit"></i>',
                '</span>'
            );
    }
endif;

function get_page_id_by_title($pagename){

    $args = array(
        'post_type' => 'page',
        'posts_per_page' => 1,
        'post_status' => 'publish',
        'title' => $pagename
    );
    $query = new WP_Query( $args );

    $page_id = '1';
    if (isset($query->post->ID)) {
        $page_id = $query->post->ID;
    }

    return $page_id;
}

add_filter( 'woocommerce_enable_setup_wizard', '__return_false' );

/*-----------------------------------------------------------------------------------*/
/* Dark Mode */
/*-----------------------------------------------------------------------------------*/

function e_storefront_body_class( $e_storefront_classes ) {
    $e_storefront_dark_mode_enabled = get_theme_mod( 'e_storefront_is_dark_mode_enabled', false );

    if ( $e_storefront_dark_mode_enabled ) {
        $e_storefront_classes[] = 'dark-mode';
    }

    return $e_storefront_classes;
}
add_filter( 'body_class', 'e_storefront_body_class' );