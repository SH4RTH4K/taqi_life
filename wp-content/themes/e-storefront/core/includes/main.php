<?php
/**
* Get started notice
*/

add_action( 'wp_ajax_e_storefront_dismissed_notice_handler', 'e_storefront_ajax_notice_handler' );

function e_storefront_ajax_notice_handler() {
    check_ajax_referer( 'e_storefront_welcome_nonce', 'nonce' );

    if ( isset( $_POST['type'] ) ) {
        $e_storefront_type = sanitize_text_field( wp_unslash( $_POST['type'] ) );
        update_option( 'dismissed-' . $e_storefront_type, true );
    }
    wp_send_json_success();
}

function e_storefront_deprecated_hook_admin_notice() {
    if ( get_option( 'dismissed-get_started', false ) ) {
        return;
    }
    $e_storefront_current_screen = get_current_screen();
    if (
        $e_storefront_current_screen &&
        $e_storefront_current_screen->id !== 'appearance_page_e-storefront-guide-page' &&
        $e_storefront_current_screen->id !== 'appearance_page_estorefront-wizard'
    ) {
        $e_storefront_comments_theme = wp_get_theme();
        ?>
        <div class="e-storefront-notice-wrapper notice notice-success notice-get-started-class is-dismissible" data-notice="get_started">
            <div class="e-storefront-notice">
                <div class="e-storefront-notice-content">
                    <div class="e-storefront-notice-heading">
                        <h2>
                            <?php esc_html_e( 'Thanks For Installing ', 'e-storefront' ); ?>
                            <?php echo esc_html( $e_storefront_comments_theme ); ?>
                            <?php esc_html_e( ' Theme', 'e-storefront' ); ?>
                        </h2>
                        <p>
                        <?php
                        printf(
                            esc_html__( '%s is now installed and ready to use. We\'ve provided some links to get you started.', 'e-storefront' ),
                            esc_html( $e_storefront_comments_theme )
                        );
                        ?>
                        </p>
                    </div>
                    <div class="diplay-flex-btn">
                        <a class="button button-primary"
                           href="<?php echo esc_url( admin_url( 'themes.php?page=e-storefront-guide-page' ) ); ?>">
                           <?php esc_html_e( 'GET STARTED', 'e-storefront' ); ?>
                        </a>
                        <a class="button button-primary"
                           target="_blank"
                           href="<?php echo esc_url( E_STOREFRONT_BUY_NOW ); ?>">
                           <?php esc_html_e( 'GO TO PREMIUM', 'e-storefront' ); ?>
                        </a>
                        <a class="button button-primary import"
                           href="<?php echo esc_url( admin_url( 'themes.php?page=estorefront-wizard' ) ); ?>">
                           <?php esc_html_e( 'ONE CLICK DEMO IMPORTER', 'e-storefront' ); ?>
                        </a>
                    </div>
                </div>
                <div class="e-storefront-notice-img">
                    <a href="<?php echo esc_url( E_STOREFRONT_THEME_BUNDLE ); ?>" target="_blank">
                        <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/notification.png' ); ?>" alt="<?php esc_attr_e( 'logo', 'e-storefront' ); ?>">
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
}
add_action( 'admin_notices', 'e_storefront_deprecated_hook_admin_notice' );

add_action( 'admin_menu', 'e_storefront_getting_started' );
function e_storefront_getting_started() {
    add_theme_page(
        esc_html__( 'Get Started', 'e-storefront' ),
        esc_html__( 'Get Started', 'e-storefront' ),
        'edit_theme_options',
        'e-storefront-guide-page',
        'e_storefront_test_guide'
    );
}

// After switching theme, reset dismissed notice option
add_action('after_switch_theme', 'e_storefront_after_switch_theme');
function e_storefront_after_switch_theme() {
    update_option('dismissed-get_started', FALSE);
}

add_action( 'admin_enqueue_scripts', 'e_storefront_admin_enqueue_scripts' );
function e_storefront_admin_enqueue_scripts( $hook ) {
	wp_enqueue_style(
		'e-storefront-admin-style',
		get_template_directory_uri() . '/css/main.css',
		array(),
		wp_get_theme()->get( 'Version' )
	);
	wp_enqueue_script(
		'e-storefront-admin-script',
		get_template_directory_uri() . '/js/e-storefront-admin-script.js',
		array( 'jquery' ),
		wp_get_theme()->get( 'Version' ),
		true
	);
	wp_localize_script(
		'e-storefront-admin-script',
		'e_storefront_ajax_object',
		array(
			'ajax_url'      => admin_url( 'admin-ajax.php' ),
			'dismiss_nonce' => wp_create_nonce( 'e_storefront_welcome_nonce' ),
			'redirect_url'  => admin_url( 'themes.php?page=e-storefront-guide-page' ),
		)
	);
}

if ( ! defined( 'E_STOREFRONT_DOCS_FREE' ) ) {
define('E_STOREFRONT_DOCS_FREE',__('https://demo.misbahwp.com/docs/e-storefront-free-docs/','e-storefront'));
}
 if ( ! defined( 'E_STOREFRONT_DOCS_PRO' ) ) {
define('E_STOREFRONT_DOCS_PRO',__('https://demo.misbahwp.com/docs/e-storefront-pro-docs/','e-storefront'));
}
if ( ! defined( 'E_STOREFRONT_BUY_NOW' ) ) {
define('E_STOREFRONT_BUY_NOW',__('https://www.misbahwp.com/products/estore-wordpress-theme','e-storefront'));
}
if ( ! defined( 'E_STOREFRONT_SUPPORT_FREE' ) ) {
define('E_STOREFRONT_SUPPORT_FREE',__('https://wordpress.org/support/theme/e-storefront','e-storefront'));
}
if ( ! defined( 'E_STOREFRONT_REVIEW_FREE' ) ) {
define('E_STOREFRONT_REVIEW_FREE',__('https://wordpress.org/support/theme/e-storefront/reviews/#new-post','e-storefront'));
}
if ( ! defined( 'E_STOREFRONT_DEMO_PRO' ) ) {
define('E_STOREFRONT_DEMO_PRO',__('https://demo.misbahwp.com/e-storefront/','e-storefront'));
}
if( ! defined( 'E_STOREFRONT_THEME_BUNDLE' ) ) {
define('E_STOREFRONT_THEME_BUNDLE',__('https://www.misbahwp.com/products/wordpress-bundle','e-storefront'));
}

function e_storefront_test_guide() { 
	$theme = wp_get_theme();?>
	<div class="wrap" id="main-page">
		<div class="demo-import-box">
			<h4><?php echo esc_html__('Import homepage demo in just one click.','e-storefront'); ?></h4>
			<p><?php echo esc_html__('Get started with the wordpress theme installation','e-storefront'); ?></p>
			<a class="button button-primary import" href="themes.php?page=estorefront-wizard"><?php echo esc_html__('ONE CLICK DEMO IMPORTER','e-storefront'); ?></a>
		</div>
		<div id="lefty">
            <div id="lefty-up">
                <div id="description">
                    <h3><?php esc_html_e('Welcome! Thank you for choosing ','e-storefront'); ?><?php echo esc_html( $theme ); ?>  <span><?php esc_html_e('Version: ', 'e-storefront'); ?><?php echo esc_html($theme['Version']);?></span></h3>
                    <div id="description-insidee">
                        <?php
                            $theme = wp_get_theme();
                            echo wp_kses_post( apply_filters( 'misbah_theme_description', esc_html( $theme->get( 'Description' ) ) ) );
                        ?>
                    </div>
                    <div id="admin_links">
                        <h3><?php esc_html_e('Unlock More Features With Premium Version','e-storefront'); ?></h3>
                        <div id="admin_inside_links">
                            <a href="<?php echo esc_url( E_STOREFRONT_BUY_NOW ); ?>" target="_blank" class="blue-button-1"><?php esc_html_e( 'Get Premium', 'e-storefront' ) ?></a>
                            <a href="<?php echo esc_url( E_STOREFRONT_DEMO_PRO ); ?>" id="customizer" target="_blank"><?php esc_html_e( 'Live Demo', 'e-storefront' ); ?> </a>
                            <a class="blue-button-1" href="<?php echo esc_url( E_STOREFRONT_DOCS_PRO ); ?>" target="_blank" class="btn3"><?php esc_html_e( 'Pro Documentation', 'e-storefront' ) ?></a>
                            <a class="blue-button-2" href="<?php echo esc_url( E_STOREFRONT_THEME_BUNDLE ); ?>" target="_blank" class="btn4"><?php esc_html_e( 'View All Themes', 'e-storefront' ) ?></a>
                        </div>
                    </div>
                </div>
                <div id="theme-img">
					
                    <img class="img_responsive" style="width: 100%;" src="<?php echo esc_url( $theme->get_screenshot() ); ?>" />
                    <div id="img-btm-box">
                        <h3 class="bundle-box-title"><?php esc_html_e('Get This Premium Theme at Flat 20% OFF','e-storefront'); ?></h3>
                        <div class="bundle-info">
                            <div class="bundle-left">
                                <p class="coupon-text"><?php esc_html_e('Use Coupon Code:','e-storefront'); ?></p>
                                <p class="coupon-code"><?php esc_html_e('HEAT20','e-storefront'); ?></p>
                            </div>
                            <div class="bundle-right">
                                <a class="white-button" href="<?php echo esc_url( E_STOREFRONT_BUY_NOW ); ?>" target="_blank"><?php esc_html_e( 'Buy Now $40', 'e-storefront' ) ?><span><?php esc_html_e( '$60', 'e-storefront' ) ?></span></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="lefty-down">
                <div id="admin_links">
                    <h3><?php esc_html_e('Important Links','e-storefront'); ?></h3>
                    <p id="description-insidee"><?php esc_html_e('Below are some Important Link, Customize your theme, Get Support, and If you are stuck somewhere get help with the documentation','e-storefront'); ?></p>
                    <div id="admin_inside_links">
                        <a href="<?php echo esc_url( E_STOREFRONT_DOCS_FREE ); ?>" target="_blank" class="blue-button-1"><?php esc_html_e( 'E Storefront Documentation', 'e-storefront' ) ?></a>
                        <a href="<?php echo esc_url( admin_url('customize.php') ); ?>" id="customizer" target="_blank"><?php esc_html_e( 'Customize Theme', 'e-storefront' ); ?> </a>
                        <a class="blue-button-1" href="<?php echo esc_url( E_STOREFRONT_SUPPORT_FREE ); ?>" target="_blank" class="btn3"><?php esc_html_e( 'Get Support', 'e-storefront' ) ?></a>
                        <a class="blue-button-2" href="<?php echo esc_url( E_STOREFRONT_REVIEW_FREE ); ?>" target="_blank" class="btn4"><?php esc_html_e( 'Review Theme', 'e-storefront' ) ?></a>
                    </div>
                </div>
            </div>
		</div>

		<div id="righty">
			<div class="postboxx donate">
				<h3 class="hndle bundle"><?php esc_html_e( 'Get All Themes', 'e-storefront' ); ?></h3>
				<div class="insidee theme-bundle">
					<img width="100%" src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/bundle-image.png' ); ?>" alt="<?php esc_attr_e('logo', 'e-storefront'); ?>">
					<p class="offer"><?php esc_html_e('Get 110+ Perfect WordPress Theme In A Single Package at just $89.','e-storefront'); ?></p>
					<p class="coupon"><?php esc_html_e('Get Our Theme Pack of 110+ WordPress Themes At 20% Off ','e-storefront'); ?><span><?php esc_html_e('"HEAT20"','e-storefront'); ?></span></p>
				<div id="admin_pro_linkss">
					<a class="blue-button-1" href="<?php echo esc_url( E_STOREFRONT_THEME_BUNDLE ); ?>" target="_blank"><?php esc_html_e( 'Buy All Themes - $89', 'e-storefront' ) ?></a>
				</div>
				<div class="d-table">
			    <ul class="d-column">
			      <li class="feature"><?php esc_html_e('Features','e-storefront'); ?></li>
			      <li class="free"><?php esc_html_e('Pro','e-storefront'); ?></li>
			      <li class="plus"><?php esc_html_e('Free','e-storefront'); ?></li>
			    </ul>
			    <ul class="d-row">
			      <li class="points"><?php esc_html_e('24hrs Priority Support','e-storefront'); ?></li>
			      <li class="right"><span class="dashicons dashicons-yes"></span></li>
			      <li class="wrong"><span class="dashicons dashicons-yes"></span></li>
			    </ul>
			    <ul class="d-row">
			      <li class="points"><?php esc_html_e('Advance Posttype','e-storefront'); ?></li>
			      <li class="right"><span class="dashicons dashicons-yes"></span></li>
			      <li class="wrong"><span class="dashicons dashicons-no"></span></li>
			    </ul>
			    <ul class="d-row">
			      <li class="points"><?php esc_html_e('Section Reordering','e-storefront'); ?></li>
			      <li class="right"><span class="dashicons dashicons-yes"></span></li>
			      <li class="wrong"><span class="dashicons dashicons-no"></span></li>
			    </ul>
			    <ul class="d-row">
			      <li class="points"><?php esc_html_e('Enable / Disable Option','e-storefront'); ?></li>
			      <li class="right"><span class="dashicons dashicons-yes"></span></li>
			      <li class="wrong"><span class="dashicons dashicons-yes"></span></li>
			    </ul>
			    <ul class="d-row">
			      <li class="points"><?php esc_html_e('Multiple Sections','e-storefront'); ?></li>
			      <li class="right"><span class="dashicons dashicons-yes"></span></li>
			      <li class="wrong"><span class="dashicons dashicons-no"></span></li>
			    </ul>
			    <ul class="d-row">
			      <li class="points"><?php esc_html_e('Advance Color Pallete','e-storefront'); ?></li>
			      <li class="right"><span class="dashicons dashicons-yes"></span></li>
			      <li class="wrong"><span class="dashicons dashicons-no"></span></li>
			    </ul>
			    <ul class="d-row">
			      <li class="points"><?php esc_html_e('Advance Widgets','e-storefront'); ?></li>
			      <li class="right"><span class="dashicons dashicons-yes"></span></li>
			      <li class="wrong"><span class="dashicons dashicons-yes"></span></li>
			    </ul>
			    <ul class="d-row">
			      <li class="points"><?php esc_html_e('Page Templates','e-storefront'); ?></li>
			      <li class="right"><span class="dashicons dashicons-yes"></span></li>
			      <li class="wrong"><span class="dashicons dashicons-no"></span></li>
			    </ul>
			    <ul class="d-row">
			      <li class="points"><?php esc_html_e('Advance Typography','e-storefront'); ?></li>
			      <li class="right"><span class="dashicons dashicons-yes"></span></li>
			      <li class="wrong"><span class="dashicons dashicons-no"></span></li>
			    </ul>
			    <ul class="d-row">
			      <li class="points"><?php esc_html_e('Section Background Image / Color ','e-storefront'); ?></li>
			      <li class="right"><span class="dashicons dashicons-yes"></span></li>
			      <li class="wrong"><span class="dashicons dashicons-no"></span></li>
			    </ul>
	  		</div>
			</div>
		</div>
	</div>
<?php } ?>
