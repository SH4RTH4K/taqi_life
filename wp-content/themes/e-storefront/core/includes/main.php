<?php
/**
* Get started notice
*/
add_action( 'wp_ajax_e_storefront_dismissed_notice_handler', 'e_storefront_ajax_notice_handler' );

function e_storefront_ajax_notice_handler() {
    if ( isset( $_POST['type'] ) ) {
        $type = sanitize_text_field( wp_unslash( $_POST['type'] ) );
        update_option( 'dismissed-' . $type, TRUE );
    }
}

function e_storefront_deprecated_hook_admin_notice() {
    // Check if it's been dismissed...
    if ( ! get_option( 'dismissed-get_started', false ) ) {
        $current_screen = get_current_screen();

        // Check screen ID correctly
        if ( 
            $current_screen && 
            $current_screen->id !== 'appearance_page_e-storefront-guide-page' &&
            $current_screen->id !== 'appearance_page_estorefront-wizard'
        ) {
            $e_storefront_comments_theme = wp_get_theme();
            ?>
            <div class="e-storefront-notice-wrapper updated notice notice-get-started-class is-dismissible" data-notice="get_started">
                <div class="e-storefront-notice">
                    <div class="e-storefront-notice-content">
                        <div class="e-storefront-notice-heading">
                            <h2>
                                <?php esc_html_e('Thanks For Installing ', 'e-storefront'); ?>
                                <?php echo esc_html( $e_storefront_comments_theme ); ?>
                                <?php esc_html_e('Theme', 'e-storefront'); ?>
                            </h2>
                            <p>
                                <?php
                                /* translators: %s: theme name */
                                printf(
                                    esc_html__("%s is now installed and ready to use. We've provided some links to get you started.", 'e-storefront'),
                                    $e_storefront_comments_theme
                                );
                                ?>
                            </p>
                        </div>
                        <div class="diplay-flex-btn">
                            <a class="button button-primary" href="<?php echo esc_url( admin_url( 'themes.php?page=e-storefront-guide-page' ) ); ?>">
                                <?php echo esc_html__('GET STARTED', 'e-storefront'); ?>
                            </a>
                            <a class="button button-primary" target="_blank" href="<?php echo esc_url( E_STOREFRONT_BUY_NOW ); ?>">
                                <?php echo esc_html__('GO TO PREMIUM', 'e-storefront'); ?>
                            </a>
                            <a class="button button-primary import" href="<?php echo esc_url( admin_url( 'themes.php?page=estorefront-wizard' ) ); ?>">
                                <?php echo esc_html__('ONE CLICK DEMO IMPORTER', 'e-storefront'); ?>
                            </a>
                        </div>
                    </div>
                    <div class="e-storefront-notice-img">
                        <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/notification.png' ); ?>" alt="<?php esc_attr_e('logo', 'e-storefront'); ?>">
                    </div>
                </div>
            </div>
            <?php
        }
    }
}
add_action( 'admin_notices', 'e_storefront_deprecated_hook_admin_notice' );

add_action( 'admin_menu', 'e_storefront_getting_started' );
function e_storefront_getting_started() {
	add_theme_page( esc_html__('Get Started', 'e-storefront'), esc_html__('Get Started', 'e-storefront'), 'edit_theme_options', 'e-storefront-guide-page', 'e_storefront_test_guide');	
}

function e_storefront_admin_enqueue_scripts() {
	wp_enqueue_style( 'e-storefront-admin-style', esc_url( get_template_directory_uri() ).'/css/main.css' );
	wp_enqueue_script( 'e-storefront-admin-script', get_template_directory_uri() . '/js/e-storefront-admin-script.js', array( 'jquery' ), '', true );
    wp_localize_script( 'e-storefront-admin-script', 'e_storefront_ajax_object',
        array( 'ajax_url' => admin_url( 'admin-ajax.php' ) )
    );
}
add_action( 'admin_enqueue_scripts', 'e_storefront_admin_enqueue_scripts' );


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
	$e_storefront_theme = wp_get_theme();?>
	<div class="wrap" id="main-page">
		<div id="lefty">
			<div id="admin_links">
				<a href="<?php echo esc_url( E_STOREFRONT_DOCS_FREE ); ?>" target="_blank" class="blue-button-1"><?php esc_html_e( 'Documentation', 'e-storefront' ) ?></a>
				<a href="<?php echo esc_url( admin_url('customize.php') ); ?>" id="customizer" target="_blank"><?php esc_html_e( 'Customize', 'e-storefront' ); ?> </a>
				<a class="blue-button-1" href="<?php echo esc_url( E_STOREFRONT_SUPPORT_FREE ); ?>" target="_blank" class="btn3"><?php esc_html_e( 'Support', 'e-storefront' ) ?></a>
				<a class="blue-button-2" href="<?php echo esc_url( E_STOREFRONT_REVIEW_FREE ); ?>" target="_blank" class="btn4"><?php esc_html_e( 'Review', 'e-storefront' ) ?></a>
			</div>
			<div id="description">
				<h3><?php esc_html_e('Welcome! Thank you for choosing ','e-storefront'); ?><?php echo esc_html( $e_storefront_theme ); ?>  <span><?php esc_html_e('Version: ', 'e-storefront'); ?><?php echo esc_html($e_storefront_theme['Version']);?></span></h3>
				<div class="demo-import-box">
					<h4><?php echo esc_html__('Import homepage demo in just one click.','e-storefront'); ?></h4>
					<p><?php echo esc_html__('Get started with the wordpress theme installation','e-storefront'); ?></p>
					<a class="button button-primary import" href="themes.php?page=estorefront-wizard"><?php echo esc_html__('ONE CLICK DEMO IMPORTER','e-storefront'); ?></a>
				</div>
				<img class="img_responsive" style="width: 100%;" src="<?php echo esc_url( $e_storefront_theme->get_screenshot() ); ?>" />
				<div id="description-insidee">
					<?php
						$e_storefront_theme = wp_get_theme();
						echo wp_kses_post( apply_filters( 'misbah_theme_description', esc_html( $e_storefront_theme->get( 'Description' ) ) ) );
					?>
				</div>
			</div>
		</div>

		<div id="righty">
			<div class="volleyball-postboxx">
				<h3 class="hndle"><?php esc_html_e( 'Upgrade to Premium', 'e-storefront' ); ?></h3>
				<div class="volleyball-insidee">
					<p><?php esc_html_e('Discover upgraded pro features with premium version click to upgrade.','e-storefront'); ?></p>
					<div id="admin_pro_links">
						<a class="blue-button-2" href="<?php echo esc_url( E_STOREFRONT_BUY_NOW ); ?>" target="_blank"><?php esc_html_e( 'Go Pro', 'e-storefront' ); ?></a>
						<a class="blue-button-1" href="<?php echo esc_url( E_STOREFRONT_DEMO_PRO ); ?>" target="_blank"><?php esc_html_e( 'Live Demo', 'e-storefront' ) ?></a>
						<a class="blue-button-2" href="<?php echo esc_url( E_STOREFRONT_DOCS_PRO ); ?>" target="_blank"><?php esc_html_e( 'Pro Docs', 'e-storefront' ) ?></a>
					</div>
				</div>

				<h3 class="hndle bundle"><?php esc_html_e( 'Get All Themes', 'e-storefront' ); ?></h3>
				<div class="insidee theme-bundle">
					<img width="100%" src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/bundle-image.png' ); ?>" alt="<?php esc_attr_e('logo', 'e-storefront'); ?>">
					<p class="offer"><?php esc_html_e('Get 100+ Perfect WordPress Theme In A Single Package at just $89."','e-storefront'); ?></p>
					<p class="coupon"><?php esc_html_e('Get Our Theme Pack of 100+ WordPress Themes At 15% Off','e-storefront'); ?><span class="coupon-code"><?php esc_html_e('"Bundleup15"','e-storefront'); ?></span></p>
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
			      <li class="points"><?php esc_html_e('LearnPress Campatiblity','e-storefront'); ?></li>
			      <li class="right"><span class="dashicons dashicons-yes"></span></li>
			      <li class="wrong"><span class="dashicons dashicons-yes"></span></li>
			    </ul>
			    <ul class="d-row">
			      <li class="points"><?php esc_html_e('Kirki Framework','e-storefront'); ?></li>
			      <li class="right"><span class="dashicons dashicons-yes"></span></li>
			      <li class="wrong"><span class="dashicons dashicons-yes"></span></li>
			    </ul>
			    <ul class="d-row">
			      <li class="points"><?php esc_html_e('Advance Posttype','e-storefront'); ?></li>
			      <li class="right"><span class="dashicons dashicons-yes"></span></li>
			      <li class="wrong"><span class="dashicons dashicons-no"></span></li>
			    </ul>
			    <ul class="d-row">
			      <li class="points"><?php esc_html_e('One Click Demo Import','e-storefront'); ?></li>
			      <li class="right"><span class="dashicons dashicons-yes"></span></li>
			      <li class="wrong"><span class="dashicons dashicons-yes"></span></li>
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
