<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>

<meta http-equiv="Content-Type" content="<?php echo esc_attr(get_bloginfo('html_type')); ?>; charset=<?php echo esc_attr(get_bloginfo('charset')); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.2, user-scalable=yes" />

<?php wp_head(); ?>

</head>

<body <?php body_class(); ?>>

<?php
	if ( function_exists( 'wp_body_open' ) )
	{
		wp_body_open();
	}else{
		do_action('wp_body_open');
	}
?>

<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Skip to content', 'e-storefront' ); ?></a>

<?php if ( get_theme_mod('e_storefront_site_loader', false) == true ) : ?>
	<div class="cssloader">
    	<div class="sh1"></div>
    	<div class="sh2"></div>
    	<h1 class="lt"><?php esc_html_e( 'loading',  'e-storefront' ); ?></h1>
    </div>
<?php endif; ?>

<div class="<?php if( get_theme_mod( 'e_storefront_sticky_header', false) != '') { ?>sticky-header<?php } else { ?>close-sticky main-menus<?php } ?>">
	<header id="site-navigation">
		<div class="header-inner py-2">
			<div class="container">
				<div class="row">
					<div class="col-xl-3 col-lg-5 col-md-4 align-self-center">
						<?php if ( get_theme_mod('e_storefront_header_inner_text')) : ?>
							<p class="mb-0 text-inner"><?php echo esc_html(get_theme_mod('e_storefront_header_inner_text'));?></p>
						<?php endif; ?>
					</div>
					<div class="col-xl-4 col-lg-7 col-md-8 align-self-center contact-box text-start">
						<div class="row">
							<div class="col-lg-5 col-md-6 col-sm-6 align-self-center">
								<?php if ( get_theme_mod('e_storefront_phone_number') ) : ?>
									<p class="mb-0 info-p"><i class="fas fa-phone me-2"></i><a href="tell:<?php echo esc_html(get_theme_mod('e_storefront_phone_number'));?>"><?php echo esc_html(get_theme_mod('e_storefront_phone_number'));?></a></p>
								<?php endif; ?>
							</div>
							<div class="col-lg-7 col-md-6 col-sm-6 align-self-center">
								<?php if ( get_theme_mod('e_storefront_email_id') ) : ?>
									<p class="mb-0 info-p"><i class="fas fa-envelope me-2"></i><a href="mailto:<?php echo esc_html(get_theme_mod('e_storefront_email_id'));?>"><?php echo esc_html(get_theme_mod('e_storefront_email_id'));?></a></p>
								<?php endif; ?>
							</div>
						</div>
					</div>
					<div class="col-xl-3 col-lg-5 col-md-6 col-sm-6 align-self-center ">
					    <?php $e_storefront_settings = get_theme_mod( 'e_storefront_social_links_settings' ); ?>
						<div class="social-links text-center text-md-end">
							<?php if ( is_array($e_storefront_settings) || is_object($e_storefront_settings) ){ ?>
								<?php echo esc_html('Follow us: ','e-storefront'); ?>
							    	<?php foreach( $e_storefront_settings as $e_storefront_setting ) { ?>
								        <a href="<?php echo esc_url( $e_storefront_setting['link_url'] ); ?>">
								            <i class="<?php echo esc_attr( $e_storefront_setting['link_text'] ); ?> me-2"></i>
								        </a>
							    	<?php } ?>
							<?php } ?>
						</div>
					</div>
					<div class="col-xl-2 col-lg-4 col-md-6 col-sm-6 align-self-center translation-box text-md-end">
						<?php if ( shortcode_exists('google-translator') ) : ?>
						    <span>
						        <?php echo do_shortcode('[google-translator]'); ?>
						    </span>
						<?php endif; ?>
						<?php if ( shortcode_exists('woocs') ) : ?>
						    <span>
						        <?php echo do_shortcode('[woocs]'); ?>
						    </span>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<div class="header-outter py-2">
			<div class="container">
				<div class="row">
					<div class="col-lg-2 col-md-8 align-self-center">
						<div class="logo text-start">
				    		<div class="logo-image">
				    			<?php the_custom_logo(); ?>
					    	</div>
					    	<div class="logo-content">
						    	<?php
						    		if ( get_theme_mod('e_storefront_display_header_title', true) == true ) :
							      		echo '<a href="' . esc_url(home_url('/')) . '" title="' . esc_attr(get_bloginfo('name')) . '">';
							      			echo esc_html(get_bloginfo('name'));
							      		echo '</a>';
							      	endif;

							      	if ( get_theme_mod('e_storefront_display_header_text', false) == true ) :
						      			echo '<span>'. esc_html(get_bloginfo('description')) . '</span>';
						      		endif;
					    		?>
							</div>
						</div>
					</div>
					<div class="col-lg-5 col-md-4 align-self-center">
							<div class="top-menu-wrapper">
							    <div class="navigation_header">
							        <div class="toggle-nav mobile-menu">
							            <button onclick="e_storefront_openNav()">
							                <span class="dashicons dashicons-menu"></span>
							            </button>
							        </div>
							        <div id="mySidenav" class="nav sidenav">
							            <nav id="site-navigation" class="main-navigation navbar navbar-expand-xl" aria-label="<?php esc_attr_e( 'Top Menu', 'e-storefront' ); ?>">
							                <?php {
							                    wp_nav_menu(
							                        array(
							                            'theme_location' => 'main-menu',
							                            'container_class' => 'navi clearfix navbar-nav',
							                            'menu_class'     => 'menu clearfix',
							                            'items_wrap'     => '<ul id="%1$s" class="%2$s">%3$s</ul>',
							                            'fallback_cb'    => 'wp_page_menu',
							                        )
							                    );
							                } ?>
							            </nav>
							            <a href="javascript:void(0)" class="closebtn mobile-menu" onclick="e_storefront_closeNav()">
							                <span class="dashicons dashicons-no"></span>
							            </a>
							        </div>
							    </div>
							</div>
					</div>
					<div class="col-lg-3 col-md-6 col-sm-6 text-center text-md-end align-self-center nav-box">
						<?php if ( get_theme_mod('e_storefront_wislist_url')) : ?>
							<a href="<?php echo esc_url( get_theme_mod('e_storefront_wislist_url' ) ); ?>" class="myacunt-url me-3"><i class="fas fa-heart"></i></a>
						<?php endif; ?>
						<?php if ( class_exists( 'woocommerce' ) ) {?>
							<a class="cart-customlocation me-3" href="<?php if(function_exists('wc_get_cart_url')){ echo esc_url(wc_get_cart_url()); } ?>" title="<?php esc_attr_e( 'View Shopping Cart','e-storefront' ); ?>"><i class="fas fa-shopping-cart"></i><span class="cart-item-box"><?php echo esc_html(wp_kses_data( WC()->cart->get_cart_contents_count() ));?></span></a>
						<?php }?>
						<?php if ( get_theme_mod('e_storefront_bell_link') ) : ?>
							<a href="<?php echo esc_url( get_theme_mod('e_storefront_bell_link' ) ); ?>" class="me-3"><i class="fas fa-bell"></i></a>
						<?php endif; ?>
						
					</div>
					<div class="col-lg-2 col-md-6 col-sm-6 text-center text-md-end align-self-center admin-box ">
						<div class="row">
							<div class="col-lg-3 col-md-4 col-sm-4 col-4 admin-icon align-self-center">
								<?php if(class_exists('woocommerce')){ ?>
						          	<?php if ( is_user_logged_in() ) { ?>
						            	<a href="<?php echo esc_url( get_permalink( get_option('woocommerce_myaccount_page_id') ) ); ?>"><i class="fas fa-sign-in-alt"></i></a>
						          	<?php }
						          	else { ?>
						            	<a href="<?php echo esc_url( get_permalink( get_option('woocommerce_myaccount_page_id') ) ); ?>" title="<?php esc_attr_e('Login / Register','e-storefront'); ?>"><i class="fas fa-user"></i></a>
						          	<?php } ?>
		        				<?php }?>
							</div>
							<div class="col-lg-9 col-md-8 col-sm-8 col-8 align-self-center text-start">
								<?php if(class_exists('woocommerce')){ ?>
									<p class="short-text m-0"><?php esc_html_e('welcome','e-storefront'); ?></p>
									<p class="admin-title m-0">
							          	<?php if ( is_user_logged_in() ) { ?>
							            	<a href="<?php echo esc_url( get_permalink( get_option('woocommerce_myaccount_page_id') ) ); ?>"><?php esc_html_e('Login / Register','e-storefront'); ?></a>
							          	<?php }
							          	else { ?>
							            	<a href="<?php echo esc_url( get_permalink( get_option('woocommerce_myaccount_page_id') ) ); ?>" title="<?php esc_attr_e('Login / Register','e-storefront'); ?>"><?php esc_html_e('Login / Register','e-storefront'); ?></a>
							          	<?php } ?>
									</p>
								<?php }?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</header>
</div>