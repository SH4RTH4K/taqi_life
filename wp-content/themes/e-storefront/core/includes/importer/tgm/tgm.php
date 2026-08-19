<?php require get_template_directory() . '/core/includes/importer/tgm/class-tgm-plugin-activation.php';

function e_storefront_register_recommended_plugins() {
	$plugins = array(
		array(
			'name'             => __( 'Kirki Customizer Framework', 'e-storefront' ),
			'slug'             => 'kirki',
			'required'         => false,
			'force_activation' => false,
		),
		array(
			'name'             => __( 'Woocommerce', 'e-storefront' ),
			'slug'             => 'woocommerce',
			'required'         => false,
			'force_activation' => false,
		),
		array(
			'name'             => __( 'Currency Switcher Professional for WooCommerce', 'e-storefront' ),
			'slug'             => 'woocommerce-currency-switcher',
			'required'         => false,
			'force_activation' => false,
		),
		array(
			'name'             => __( 'Google Language Translator', 'e-storefront' ),
			'slug'             => 'google-language-translator',
			'required'         => false,
			'force_activation' => false,
		),
		array(
			'name'             => __( 'Mosaic Gallery Advanced Gallery', 'e-storefront' ),
			'slug'             => 'mosaic-gallery-advanced-gallery',
			'required'         => false,
			'force_activation' => false,
		),
	);
	$config = array();
	tgmpa( $plugins, $config );
}
add_action( 'tgmpa_register', 'e_storefront_register_recommended_plugins' );