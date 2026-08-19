<?php

if ( class_exists("Kirki")){

if ( ! defined( 'E_STOREFRONT_BUY_NOW_1' ) ) {
define('E_STOREFRONT_BUY_NOW_1',__('https://www.misbahwp.com/products/estore-wordpress-theme','e-storefront'));
}

	// LOGO

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'slider',
		'settings'    => 'e_storefront_logo_resizer',
		'label'       => esc_html__( 'Adjust Your Logo Size ', 'e-storefront' ),
		'section'     => 'title_tagline',
		'choices'     => [
			'min'  => 10,
			'max'  => 300,
			'step' => 10,
		],
	] );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'custom',
		'settings'    => 'e_storefront_enable_logo_text',
		'section'     => 'title_tagline',
			'default'         => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Enable / Disable Site Title and Tagline', 'e-storefront' ) . '</h3>',
		'priority'    => 10,
	] );

    Kirki::add_field( 'theme_config_id', [
		'type'        => 'switch',
		'settings'    => 'e_storefront_display_header_title',
		'label'       => esc_html__( 'Site Title Enable / Disable Button', 'e-storefront' ),
		'section'     => 'title_tagline',
		'default'     => true,
		'priority'    => 10,
		'choices'     => [
			'on'  => esc_html__( 'Enable', 'e-storefront' ),
			'off' => esc_html__( 'Disable', 'e-storefront' ),
		],
	] );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'switch',
		'settings'    => 'e_storefront_display_header_text',
		'label'       => esc_html__( 'Tagline Enable / Disable Button', 'e-storefront' ),
		'section'     => 'title_tagline',
		'default'     => false,
		'priority'    => 10,
		'choices'     => [
			'on'  => esc_html__( 'Enable', 'e-storefront' ),
			'off' => esc_html__( 'Disable', 'e-storefront' ),
		],
	] );

	// FONT STYLE TYPOGRAPHY

	Kirki::add_panel( 'e_storefront_panel_id', array(
	    'priority'    => 10,
	    'title'       => esc_html__( 'Theme Options', 'e-storefront' ),
	) );

	Kirki::add_section( 'e_storefront_font_style_section', array(
		'title'      => esc_html__( 'Typography Option',  'e-storefront' ),
		'priority'   => 2,
		'capability' => 'edit_theme_options',
	) );

	Kirki::add_field( 'theme_config_id', [
	    'label'       => '<span class="custom-label-class">' . esc_html__( 'INFORMATION ABOUT PREMIUM VERSION :-', 'e-storefront' ) . '</span>',
	    'default'     => '<a class="premium_info_btn" target="_blank" href="' . esc_url( E_STOREFRONT_BUY_NOW_1 ) . '">' . __( 'GO TO PREMIUM', 'e-storefront' ) . '</a>',
	    'type'        => 'custom',
	    'section'     => 'e_storefront_font_style_section',
	    'description' => '<div class="custom-description-class">' . __( '<p>1. One Click Demo Importer </p><p>2. Color Pallete Setup </p><p>3. Section Reordering Facility</p><p>4. For More Options kindly Go For Premium Version.</p>', 'e-storefront' ) . '</div>',
	]);

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'custom',
		'settings'    => 'e_storefront_all_headings_typography',
		'section'     => 'e_storefront_font_style_section',
			'default'         => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Heading Of All Sections',  'e-storefront' ) . '</h3>',
		'priority'    => 10,
	] );

	Kirki::add_field( 'global', array(
		'type'        => 'typography',
		'settings'    => 'e_storefront_all_headings_typography',
		'label'       => esc_html__( 'Heading Typography',  'e-storefront' ),
		'description' => esc_html__( 'Select the typography options for your heading.',  'e-storefront' ),
		'section'     => 'e_storefront_font_style_section',
		'priority'    => 10,
		'default'     => array(
			'font-family'    => '',
			'variant'        => '',
		),
		'output' => array(
			array(
				'element' => array( 'h1','h2','h3','h4','h5','h6', ),
			),
		),
	) );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'custom',
		'settings'    => 'e_storefront_body_content_typography',
		'section'     => 'e_storefront_font_style_section',
			'default'         => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Body Content',  'e-storefront' ) . '</h3>',
		'priority'    => 10,
	] );

	Kirki::add_field( 'global', array(
		'type'        => 'typography',
		'settings'    => 'e_storefront_body_content_typography',
		'label'       => esc_html__( 'Content Typography',  'e-storefront' ),
		'description' => esc_html__( 'Select the typography options for your content.',  'e-storefront' ),
		'section'     => 'e_storefront_font_style_section',
		'priority'    => 10,
		'default'     => array(
			'font-family'    => '',
			'variant'        => '',
		),
		'output' => array(
			array(
				'element' => array( 'body', ),
			),
		),
	) );

	// PANEL
	Kirki::add_panel( 'e_storefront_panel_id_2', array(
	    'priority'    => 10,
	    'title'       => esc_html__( 'Theme Dark Mode', 'e-storefront' ),
	) );

	// DARK MODE SECTION
	Kirki::add_section( 'e_storefront_section_dark_mode', array(
	    'title'          => esc_html__( 'Dark Mode', 'e-storefront' ),
	    'priority'       => 3,
	) );

	Kirki::add_field( 'theme_config_id', [
	    'label'       => '<span class="custom-label-class">' . esc_html__( 'INFORMATION ABOUT PREMIUM VERSION :-', 'e-storefront' ) . '</span>',
	    'default'     => '<a class="premium_info_btn" target="_blank" href="' . esc_url( E_STOREFRONT_BUY_NOW_1 ) . '">' . __( 'GO TO PREMIUM', 'e-storefront' ) . '</a>',
	    'type'        => 'custom',
	    'section'     => 'e_storefront_section_dark_mode',
	    'description' => '<div class="custom-description-class">' . __( '<p>1. One Click Demo Importer </p><p>2. Color Pallete Setup </p><p>3. Section Reordering Facility</p><p>4. For More Options kindly Go For Premium Version.</p>', 'e-storefront' ) . '</div>',
	]);

	Kirki::add_field( 'theme_config_id', [
	    'type'        => 'custom',
	    'settings'    => 'e_storefront_dark_colors',
	    'section'     => 'e_storefront_section_dark_mode',
	    'default'     => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Dark Appearance', 'e-storefront' ) . '</h3>',
	    'priority'    => 10,
	]);

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'switch',
		'settings'    => 'e_storefront_is_dark_mode_enabled',
		'label'       => esc_html__( 'Turn To Dark Mode', 'e-storefront' ),
		'section'     => 'e_storefront_section_dark_mode',
		'default'     => false,
		'priority'    => 10,
		'choices'     => [
			'on'  => esc_html__( 'Enable', 'e-storefront' ),
			'off' => esc_html__( 'Disable', 'e-storefront' ),
		],
	] );


	// PANEL
	Kirki::add_panel( 'e_storefront_panel_id_3', array(
	    'priority'    => 10,
	    'title'       => esc_html__( '404 Settings / No Result', 'e-storefront' ),
	) );

	// 404 SECTION
	Kirki::add_section( 'e_storefront_section_404', array(
		'panel'          => 'e_storefront_panel_id_3',
	    'title'          => esc_html__( '404 Settings', 'e-storefront' ),
	    'priority'       => 3,
	) );

	Kirki::add_field( 'theme_config_id', [
	    'label'       => '<span class="custom-label-class">' . esc_html__( 'INFORMATION ABOUT PREMIUM VERSION :-', 'e-storefront' ) . '</span>',
	    'default'     => '<a class="premium_info_btn" target="_blank" href="' . esc_url( E_STOREFRONT_BUY_NOW_1 ) . '">' . __( 'GO TO PREMIUM', 'e-storefront' ) . '</a>',
	    'type'        => 'custom',
	    'section'     => 'e_storefront_section_404',
	    'description' => '<div class="custom-description-class">' . __( '<p>1. One Click Demo Importer </p><p>2. Color Pallete Setup </p><p>3. Section Reordering Facility</p><p>4. For More Options kindly Go For Premium Version.</p>', 'e-storefront' ) . '</div>',
		'priority'    => 10,
	]);

	Kirki::add_field( 'theme_config_id', [
	    'type'        => 'custom',
	    'settings'    => 'e_storefront_404_heading',
	    'section'     => 'e_storefront_section_404',
	    'default'     => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( '404 Heading', 'e-storefront' ) . '</h3>',
	    'priority'    => 10,
	]);

	Kirki::add_field( 'theme_config_id', [
		'type'     => 'text',
		'settings' => 'e_storefront_404_page_title',
		'section'  => 'e_storefront_section_404',
		'default'  => esc_html__('404 Not Found', 'e-storefront'),
		'priority' => 10,
	] );

		Kirki::add_field( 'theme_config_id', [
	    'type'        => 'custom',
	    'settings'    => 'e_storefront_404_text',
	    'section'     => 'e_storefront_section_404',
	    'default'     => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( '404 Content', 'e-storefront' ) . '</h3>',
	    'priority'    => 10,
	]);

	Kirki::add_field( 'theme_config_id', [
		'type'     => 'text',
		'settings' => 'e_storefront_404_page_content',
		'section'  => 'e_storefront_section_404',
		'default'  => esc_html__('Sorry, no posts matched your criteria.', 'e-storefront'),
		'priority' => 10,
	] );

	// NO Result
	Kirki::add_section( 'e_storefront_no_result', array(
		'panel'          => 'e_storefront_panel_id_3',
	    'title'          => esc_html__( 'No Result Page Settings', 'e-storefront' ),
	    'priority'       => 3,
	) );

	Kirki::add_field( 'theme_config_id', [
	    'label'       => '<span class="custom-label-class">' . esc_html__( 'INFORMATION ABOUT PREMIUM VERSION :-', 'e-storefront' ) . '</span>',
	    'default'     => '<a class="premium_info_btn" target="_blank" href="' . esc_url( E_STOREFRONT_BUY_NOW_1 ) . '">' . __( 'GO TO PREMIUM', 'e-storefront' ) . '</a>',
	    'type'        => 'custom',
	    'section'     => 'e_storefront_no_result',
	    'description' => '<div class="custom-description-class">' . __( '<p>1. One Click Demo Importer </p><p>2. Color Pallete Setup </p><p>3. Section Reordering Facility</p><p>4. For More Options kindly Go For Premium Version.</p>', 'e-storefront' ) . '</div>',
		'priority'    => 10,
	]);

	Kirki::add_field( 'theme_config_id', [
	    'type'        => 'custom',
	    'settings'    => 'e_storefront_not_found_heading',
	    'section'     => 'e_storefront_no_result',
	    'default'     => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'No Search Result Heading', 'e-storefront' ) . '</h3>',
	    'priority'    => 10,
	]);

	Kirki::add_field( 'theme_config_id', [
		'type'     => 'text',
		'settings' => 'e_storefront_no_results_page_title',
		'section'  => 'e_storefront_no_result',
		'default'  => esc_html__('404 Not Found', 'e-storefront'),
		'priority' => 10,
	] );

	Kirki::add_field( 'theme_config_id', [
	    'type'        => 'custom',
	    'settings'    => 'e_storefront_not_found_text',
	    'section'     => 'e_storefront_no_result',
	    'default'     => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'No Search Result Content', 'e-storefront' ) . '</h3>',
	    'priority'    => 10,
	]);

	Kirki::add_field( 'theme_config_id', [
		'type'     => 'text',
		'settings' => 'e_storefront_no_results_page_content',
		'section'  => 'e_storefront_no_result',
		'default'  => esc_html__('Sorry, but nothing matched your search terms. Please try again with some different keywords.', 'e-storefront'),
		'priority' => 10,
	] );



	//COLOR SECTION

	Kirki::add_section( 'e_storefront_section_color', array(
	    'title'          => esc_html__( 'Global Color', 'e-storefront' ),
	    'panel'          => 'e_storefront_panel_id',
	    'priority'       => 160,
	) );

	Kirki::add_field( 'theme_config_id', [
	    'label'       => '<span class="custom-label-class">' . esc_html__( 'INFORMATION ABOUT PREMIUM VERSION :-', 'e-storefront' ) . '</span>',
	    'default'     => '<a class="premium_info_btn" target="_blank" href="' . esc_url( E_STOREFRONT_BUY_NOW_1 ) . '">' . __( 'GO TO PREMIUM', 'e-storefront' ) . '</a>',
	    'type'        => 'custom',
	    'section'     => 'e_storefront_section_color',
	    'description' => '<div class="custom-description-class">' . __( '<p>1. One Click Demo Importer </p><p>2. More Font Family Options </p><p>3. Color Pallete Setup </p><p>4. Section Reordering Facility</p><p>5. For More Options kindly Go For Premium Version.</p>', 'e-storefront' ) . '</div>',
	] );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'custom',
		'settings'    => 'e_storefront_global_colors',
		'section'     => 'e_storefront_section_color',
		'default'         => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Here you can change your theme color on one click.', 'e-storefront' ) . '</h3>',
		'priority'    => 10,
	] );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'color',
		'settings'    => 'e_storefront_first_color',
		'label'       => __( 'Choose Your First Color', 'e-storefront' ),
		'section'     => 'e_storefront_section_color',
		'default'     => '#1ABA1A',
	] );

	// Additional Settings

	Kirki::add_section( 'e_storefront_additional_settings', array(
	    'title'          => esc_html__( 'Additional Settings', 'e-storefront' ),
	    'panel'          => 'e_storefront_panel_id',
	    'priority'       => 160,
	) );

	Kirki::add_field( 'theme_config_id', [
	    'label'       => '<span class="custom-label-class">' . esc_html__( 'INFORMATION ABOUT PREMIUM VERSION :-', 'e-storefront' ) . '</span>',
	    'default'     => '<a class="premium_info_btn" target="_blank" href="' . esc_url( E_STOREFRONT_BUY_NOW_1 ) . '">' . __( 'GO TO PREMIUM', 'e-storefront' ) . '</a>',
	    'type'        => 'custom',
	    'section'     => 'e_storefront_additional_settings',
	    'description' => '<div class="custom-description-class">' . __( '<p>1. One Click Demo Importer </p><p>2. Color Pallete Setup </p><p>3. Section Reordering Facility</p><p>4. For More Options kindly Go For Premium Version.</p>', 'e-storefront' ) . '</div>',
	]);

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'toggle',
		'settings'    => 'e_storefront_scroll_enable_setting',
		'label'       => esc_html__( 'Here you can enable or disable your scroller.', 'e-storefront' ),
		'section'     => 'e_storefront_additional_settings',
		'default'     => '1',
		'priority'    => 10,
	] );

	new \Kirki\Field\Radio_Buttonset(
	[
		'settings'    => 'e_storefront_scroll_top_position',
		'label'       => esc_html__( 'Alignment for Scroll To Top', 'e-storefront' ),
		'section'     => 'e_storefront_additional_settings',
		'default'     => 'Right',
		'priority'    => 10,
		'choices'     => [
			'Left'   => esc_html__( 'Left', 'e-storefront' ),
			'Center' => esc_html__( 'Center', 'e-storefront' ),
			'Right'  => esc_html__( 'Right', 'e-storefront' ),
		],
	]
	);

	Kirki::add_field( 'theme_config_id', [
		'type'     => 'dashicons',
		'settings' => 'e_storefront_scroll_top_icon',
		'label'    => esc_html__( 'Select Appropriate Scroll Top Icon', 'e-storefront' ),
		'section'  => 'e_storefront_additional_settings',
		'default'  => 'dashicons dashicons-arrow-up-alt',
		'priority' => 10,
	] );

	new \Kirki\Field\Select(
	[
		'settings'    => 'menu_text_transform_e_storefront',
		'label'       => esc_html__( 'Menus Text Transform', 'e-storefront' ),
		'section'     => 'e_storefront_additional_settings',
		'default'     => 'CAPITALISE',
		'placeholder' => esc_html__( 'Choose an option', 'e-storefront' ),
		'choices'     => [
			'CAPITALISE' => esc_html__( 'CAPITALISE', 'e-storefront' ),
			'UPPERCASE' => esc_html__( 'UPPERCASE', 'e-storefront' ),
			'LOWERCASE' => esc_html__( 'LOWERCASE', 'e-storefront' ),

		],
	] );

	new \Kirki\Field\Select(
	[
		'settings'    => 'e_storefront_menu_zoom',
		'label'       => esc_html__( 'Menu Transition', 'e-storefront' ),
		'section'     => 'e_storefront_additional_settings',
		'default' => 'Zoom Out',
		'placeholder' => esc_html__( 'Choose an option', 'e-storefront' ),
		'choices'     => [
			'Zoomout' => __('Zoom Out','e-storefront'),
            'Zoominn' => __('Zoom Inn','e-storefront'),
            
		],
	] );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'slider',
		'settings'    => 'e_storefront_container_width',
		'label'       => esc_html__( 'Theme Container Width', 'e-storefront' ),
		'section'     => 'e_storefront_additional_settings',
		'default'     => 100,
		'choices'     => [
			'min'  => 50,
			'max'  => 100,
			'step' => 1,
		],
	] );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'toggle',
		'settings'    => 'e_storefront_site_loader',
		'label'       => esc_html__( 'Here you can enable or disable your Site Loader.', 'e-storefront' ),
		'section'     => 'e_storefront_additional_settings',
		'default'     => false,
		'priority'    => 10,
	] );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'toggle',
		'settings'    => 'e_storefront_sticky_header',
		'label'       => esc_html__( 'Here you can enable or disable your Sticky Header.', 'e-storefront' ),
		'section'     => 'e_storefront_additional_settings',
		'default'     => false,
		'priority'    => 10,
	] );

	new \Kirki\Field\Select(
	[
		'settings'    => 'e_storefront_page_layout',
		'label'       => esc_html__( 'Page Layout Setting', 'e-storefront' ),
		'section'     => 'e_storefront_additional_settings',
		'default' => 'Right Sidebar',
		'placeholder' => esc_html__( 'Choose an option', 'e-storefront' ),
		'choices'     => [
			'Left Sidebar' => __('Left Sidebar','e-storefront'),
            'Right Sidebar' => __('Right Sidebar','e-storefront'),
            'One Column' => __('One Column','e-storefront')
		],
	] );

	if ( class_exists("woocommerce")){

	// Woocommerce Settings

	Kirki::add_section( 'e_storefront_woocommerce_settings', array(
		'title'          => esc_html__( 'Woocommerce Settings', 'e-storefront' ),
		'panel'          => 'e_storefront_panel_id',
		'priority'       => 160,
	) );

	Kirki::add_field( 'theme_config_id', [
	    'label'       => '<span class="custom-label-class">' . esc_html__( 'INFORMATION ABOUT PREMIUM VERSION :-', 'e-storefront' ) . '</span>',
	    'default'     => '<a class="premium_info_btn" target="_blank" href="' . esc_url( E_STOREFRONT_BUY_NOW_1 ) . '">' . __( 'GO TO PREMIUM', 'e-storefront' ) . '</a>',
	    'type'        => 'custom',
	    'section'     => 'e_storefront_woocommerce_settings',
	    'description' => '<div class="custom-description-class">' . __( '<p>1. One Click Demo Importer </p><p>2. Color Pallete Setup </p><p>3. Section Reordering Facility</p><p>4. For More Options kindly Go For Premium Version.</p>', 'e-storefront' ) . '</div>',
		'priority'    => 10,
	]);

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'toggle',
		'settings'    => 'e_storefront_shop_sidebar',
		'label'       => esc_html__( 'Here you can enable or disable shop page sidebar.', 'e-storefront' ),
		'section'     => 'e_storefront_woocommerce_settings',
		'default'     => '1',
		'priority'    => 10,
	] );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'toggle',
		'settings'    => 'e_storefront_product_sidebar',
		'label'       => esc_html__( 'Here you can enable or disable product page sidebar.', 'e-storefront' ),
		'section'     => 'e_storefront_woocommerce_settings',
		'default'     => '1',
		'priority'    => 10,
	] );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'toggle',
		'settings'    => 'e_storefront_related_product_setting',
		'label'       => esc_html__( 'Here you can enable or disable your related products.', 'e-storefront' ),
		'section'     => 'e_storefront_woocommerce_settings',
		'default'     => true,
		'priority'    => 10,
	] );

	new \Kirki\Field\Number(
		[
			'settings' => 'e_storefront_per_columns',
			'label'    => esc_html__( 'Product Per Row', 'e-storefront' ),
			'section'  => 'e_storefront_woocommerce_settings',
			'default'  => 3,
			'choices'  => [
				'min'  => 1,
				'max'  => 4,
				'step' => 1,
			],
		]
	);

	new \Kirki\Field\Number(
		[
			'settings' => 'e_storefront_product_per_page',
			'label'    => esc_html__( 'Product Per Page', 'e-storefront' ),
			'section'  => 'e_storefront_woocommerce_settings',
			'default'  => 9,
			'choices'  => [
				'min'  => 1,
				'max'  => 15,
				'step' => 1,
			],
		]
	);

	new \Kirki\Field\Number(
	[
		'settings' => 'custom_related_products_number_per_row',
		'label'    => esc_html__( 'Related Product Per Column', 'e-storefront' ),
		'section'  => 'e_storefront_woocommerce_settings',
		'default'  => 3,
		'choices'  => [
			'min'  => 1,
			'max'  => 4,
			'step' => 1,
		],
	]
	);

	new \Kirki\Field\Number(
	[
		'settings' => 'custom_related_products_number',
		'label'    => esc_html__( 'Related Product Per Page', 'e-storefront' ),
		'section'  => 'e_storefront_woocommerce_settings',
		'default'  => 3,
		'choices'  => [
			'min'  => 1,
			'max'  => 10,
			'step' => 1,
		],
	]
	);

	new \Kirki\Field\Select(
	[
		'settings'    => 'e_storefront_shop_page_layout',
		'label'       => esc_html__( 'Shop Page Layout Setting', 'e-storefront' ),
		'section'     => 'e_storefront_woocommerce_settings',
		'default' => 'Right Sidebar',
		'placeholder' => esc_html__( 'Choose an option', 'e-storefront' ),
		'choices'     => [
			'Left Sidebar' => __('Left Sidebar','e-storefront'),
            'Right Sidebar' => __('Right Sidebar','e-storefront')
		],
	] );

	new \Kirki\Field\Select(
	[
		'settings'    => 'e_storefront_product_page_layout',
		'label'       => esc_html__( 'Product Page Layout Setting', 'e-storefront' ),
		'section'     => 'e_storefront_woocommerce_settings',
		'default' => 'Right Sidebar',
		'placeholder' => esc_html__( 'Choose an option', 'e-storefront' ),
		'choices'     => [
			'Left Sidebar' => __('Left Sidebar','e-storefront'),
            'Right Sidebar' => __('Right Sidebar','e-storefront')
		],
	] );

	new \Kirki\Field\Radio_Buttonset( [
		'settings'    => 'e_storefront_woocommerce_pagination_position',
		'label'       => esc_html__( 'Woocommerce Pagination Alignment', 'e-storefront' ),
		'section'     => 'e_storefront_woocommerce_settings',
		'default'     => 'Center',
		'priority'    => 10,
		'choices'     => [
			'Left'   => esc_html__( 'Left', 'e-storefront' ),
			'Center' => esc_html__( 'Center', 'e-storefront' ),
			'Right'  => esc_html__( 'Right', 'e-storefront' ),
		],
	]	);

}

	// POST SECTION

	Kirki::add_section( 'e_storefront_section_post', array(
	    'title'          => esc_html__( 'Post Settings', 'e-storefront' ),
	    'panel'          => 'e_storefront_panel_id',
	    'priority'       => 160,
	) );

	Kirki::add_field( 'theme_config_id', [
	    'label'       => '<span class="custom-label-class">' . esc_html__( 'INFORMATION ABOUT PREMIUM VERSION :-', 'e-storefront' ) . '</span>',
	    'default'     => '<a class="premium_info_btn" target="_blank" href="' . esc_url( E_STOREFRONT_BUY_NOW_1 ) . '">' . __( 'GO TO PREMIUM', 'e-storefront' ) . '</a>',
	    'type'        => 'custom',
	    'section'     => 'e_storefront_section_post',
	    'description' => '<div class="custom-description-class">' . __( '<p>1. One Click Demo Importer </p><p>2. Color Pallete Setup </p><p>3. Section Reordering Facility</p><p>4. For More Options kindly Go For Premium Version.</p>', 'e-storefront' ) . '</div>',
		'priority'    => 10,
	]);

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'slider',
		'settings'    => 'e_storefront_post_excerpt_number',
		'label'       => esc_html__( 'Post Content Range', 'e-storefront' ),
		'section'     => 'e_storefront_section_post',
		'default'     => 15,
		'choices'     => [
			'min'  => 0,
			'max'  => 100,
			'step' => 1,
		],
	] );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'toggle',
		'settings'    => 'e_storefront_pagination_setting',
		'label'       => esc_html__( 'Here you can enable or disable your Pagination.', 'e-storefront' ),
		'section'     => 'e_storefront_section_post',
		'default'     => true,
		'priority'    => 10,
	] );

	new \Kirki\Field\Sortable(
	[
		'settings' => 'e_storefront_archive_element_sortable',
		'label'    => __( 'Archive Post Page Element Reordering', 'e-storefront' ),
		'description'    => esc_html__( 'This setting is not favorable with post format.', 'e-storefront' ),
		'section'  => 'e_storefront_section_post',
		'default'  => [ 'option1', 'option2', 'option3', 'option4', 'option5' ],
		'choices'  => [
			'option1' => esc_html__( 'Post Image', 'e-storefront' ),
			'option2' => esc_html__( 'Post Meta', 'e-storefront' ),
			'option3' => esc_html__( 'Post Title', 'e-storefront' ),
			'option4' => esc_html__( 'Post Content', 'e-storefront' ),
			'option5' => esc_html__( 'Post Button', 'e-storefront' ),
		],
	]
	);

	new \Kirki\Field\Select(
	[
		'settings'    => 'e_storefront_archive_sidebar_layout',
		'label'       => esc_html__( 'Archive Post Sidebar Layout Setting', 'e-storefront' ),
		'section'     => 'e_storefront_section_post',
		'default' => 'Right Sidebar',
		'placeholder' => esc_html__( 'Choose an option', 'e-storefront' ),
		'choices'     => [
			'Left Sidebar' => __('Left Sidebar','e-storefront'),
            'Right Sidebar' => __('Right Sidebar','e-storefront'),
            'Three Column' => __('Three Column','e-storefront'),
            'Four Column' => __('Four Column','e-storefront'),
            'Grid Layout Without Sidebar' => __('Grid Layout Without Sidebar','e-storefront'),
            'Grid Layout With Right Sidebar' => __('Grid Layout With Right Sidebar','e-storefront'),
            'Grid Layout With Left Sidebar' => __('Grid Layout With Left Sidebar','e-storefront')
		],
	] );

	new \Kirki\Field\Select(
	[
		'settings'    => 'e_storefront_single_post_sidebar_layout',
		'label'       => esc_html__( 'Single Post Sidebar Layout Setting', 'e-storefront' ),
		'section'     => 'e_storefront_section_post',
		'default' => 'Right Sidebar',
		'placeholder' => esc_html__( 'Choose an option', 'e-storefront' ),
		'choices'     => [
			'Left Sidebar' => __('Left Sidebar','e-storefront'),
            'Right Sidebar' => __('Right Sidebar','e-storefront'),
		],
	] );

	new \Kirki\Field\Select(
	[
		'settings'    => 'e_storefront_search_sidebar_layout',
		'label'       => esc_html__( 'Search Page Sidebar Layout Setting', 'e-storefront' ),
		'section'     => 'e_storefront_section_post',
		'default' => 'Right Sidebar',
		'placeholder' => esc_html__( 'Choose an option', 'e-storefront' ),
		'choices'     => [
			'Left Sidebar' => __('Left Sidebar','e-storefront'),
            'Right Sidebar' => __('Right Sidebar','e-storefront'),
            'Three Column' => __('Three Column','e-storefront'),
            'Four Column' => __('Four Column','e-storefront'),
            'Grid Layout Without Sidebar' => __('Grid Layout Without Sidebar','e-storefront'),
            'Grid Layout With Right Sidebar' => __('Grid Layout With Right Sidebar','e-storefront'),
            'Grid Layout With Left Sidebar' => __('Grid Layout With Left Sidebar','e-storefront')
		],
	] );

	Kirki::add_field( 'e_storefront_config', [
		'type'        => 'select',
		'settings'    => 'e_storefront_post_column_count',
		'label'       => esc_html__( 'Grid Column for Archive Page', 'e-storefront' ),
		'section'     => 'e_storefront_section_post',
		'default'    => '2',
		'choices' => [
				'1' => __( '1 Column', 'e-storefront' ),
				'2' => __( '2 Column', 'e-storefront' ),
			],
	] );

	// Breadcrumb
	Kirki::add_section( 'e_storefront_bradcrumb', array(
	    'title'          => esc_html__( 'Breadcrumb Settings', 'e-storefront' ),
	    'panel'          => 'e_storefront_panel_id',
	    'priority'       => 160,
	) );

	Kirki::add_field( 'theme_config_id', [
	    'label'       => '<span class="custom-label-class">' . esc_html__( 'INFORMATION ABOUT PREMIUM VERSION :-', 'e-storefront' ) . '</span>',
	    'default'     => '<a class="premium_info_btn" target="_blank" href="' . esc_url( E_STOREFRONT_BUY_NOW_1 ) . '">' . __( 'GO TO PREMIUM', 'e-storefront' ) . '</a>',
	    'type'        => 'custom',
	    'section'     => 'e_storefront_bradcrumb',
	    'description' => '<div class="custom-description-class">' . __( '<p>1. One Click Demo Importer </p><p>2. Color Pallete Setup </p><p>3. Section Reordering Facility</p><p>4. For More Options kindly Go For Premium Version.</p>', 'e-storefront' ) . '</div>',
		'priority'    => 10,
	]);

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'custom',
		'settings'    => 'e_storefront_enable_breadcrumb_heading',
		'section'     => 'e_storefront_bradcrumb',
			'default'         => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Enable / Disable Single Page Breadcrumb', 'e-storefront' ) . '</h3>',
		'priority'    => 10,
	] );

    Kirki::add_field( 'theme_config_id', [
		'type'        => 'switch',
		'settings'    => 'e_storefront_breadcrumb_enable',
		'label'       => esc_html__( 'Breadcrumb Enable / Disable', 'e-storefront' ),
		'section'     => 'e_storefront_bradcrumb',
		'default'     => true,
		'priority'    => 10,
		'choices'     => [
			'on'  => esc_html__( 'Enable', 'e-storefront' ),
			'off' => esc_html__( 'Disable', 'e-storefront' ),
		],
	] );

	Kirki::add_field( 'theme_config_id', [
        'type'     => 'text',
        'default'     => '/',
        'settings' => 'e_storefront_breadcrumb_separator' ,
        'label'    => esc_html__( 'Breadcrumb Separator',  'e-storefront' ),
        'section'  => 'e_storefront_bradcrumb',
    ] );

	// HEADER SECTION

	Kirki::add_section( 'e_storefront_header_section', array(
        'title'          => esc_html__( 'Header Settings', 'e-storefront' ),
        'panel'          => 'e_storefront_panel_id',
        'priority'       => 160,
    ) );

	Kirki::add_field( 'theme_config_id', [
	    'label'       => '<span class="custom-label-class">' . esc_html__( 'INFORMATION ABOUT PREMIUM VERSION :-', 'e-storefront' ) . '</span>',
	    'default'     => '<a class="premium_info_btn" target="_blank" href="' . esc_url( E_STOREFRONT_BUY_NOW_1 ) . '">' . __( 'GO TO PREMIUM', 'e-storefront' ) . '</a>',
	    'type'        => 'custom',
	    'section'     => 'e_storefront_header_section',
	    'description' => '<div class="custom-description-class">' . __( '<p>1. One Click Demo Importer </p><p>2. Color Pallete Setup </p><p>3. Section Reordering Facility</p><p>4. For More Options kindly Go For Premium Version.</p>', 'e-storefront' ) . '</div>',
		'priority'    => 10,
	]);

    Kirki::add_field( 'theme_config_id', [
		'type'        => 'custom',
		'settings'    => 'e_storefront_header_inner_text_heading',
		'section'     => 'e_storefront_header_section',
			'default'         => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Header Text',  'e-storefront' ) . '</h3>',
	] );

	Kirki::add_field( 'theme_config_id', [
        'type'     => 'text',
        'settings' => 'e_storefront_header_inner_text' ,
        'section'  => 'e_storefront_header_section',
    ] );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'custom',
		'settings'    => 'e_storefront_phone_number_heading',
		'section'     => 'e_storefront_header_section',
			'default'         => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Phone Number',  'e-storefront' ) . '</h3>',
	] );

	Kirki::add_field( 'theme_config_id', [
        'type'     => 'text',
        'settings' => 'e_storefront_phone_number' ,
        'section'  => 'e_storefront_header_section',
    ] );

    Kirki::add_field( 'theme_config_id', [
		'type'        => 'custom',
		'settings'    => 'e_storefront_email_address_heading',
		'section'     => 'e_storefront_header_section',
			'default'         => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Email Address',  'e-storefront' ) . '</h3>',
	] );

	Kirki::add_field( 'theme_config_id', [
        'type'     => 'text',
        'settings' => 'e_storefront_email_id' ,
        'section'  => 'e_storefront_header_section',
    ] );

   	Kirki::add_field( 'theme_config_id', [
		'type'        => 'custom',
		'settings'    => 'e_storefront_enable_socail_link',
		'section'     => 'e_storefront_header_section',
		'default'     => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Social Media Link', 'e-storefront' ) . '</h3>',
		'priority'    => 10,
	] );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'repeater',
		'section'     => 'e_storefront_header_section',
		'priority'    => 11,
		'row_label' => [
			'type'  => 'field',
			'value' => esc_html__( 'Social Icon', 'e-storefront' ),
			'field' => 'link_text',
		],
		'button_label' => esc_html__('Add New Social Icon', 'e-storefront' ),
		'settings'     => 'e_storefront_social_links_settings',
		'default'      => '',
		'fields' 	   => [
			'link_text' => [
				'type'        => 'text',
				'label'       => esc_html__( 'Icon', 'e-storefront' ),
				'description' => esc_html__( 'Add the fontawesome class ex: "fab fa-facebook-f".', 'e-storefront' ),
				'default'     => '',
			],
			'link_url' => [
				'type'        => 'url',
				'label'       => esc_html__( 'Social Link', 'e-storefront' ),
				'description' => esc_html__( 'Add the social icon url here.', 'e-storefront' ),
				'default'     => '',
			],
		],
		'choices' => [
			'limit' => 5
		],
	] );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'custom',
		'settings'    => 'e_storefront_header_button_heading',
		'section'     => 'e_storefront_header_section',
		'priority'    => 12,
			'default'         => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Button Urls',  'e-storefront' ) . '</h3>',
	] );


	Kirki::add_field( 'theme_config_id', [
		'type'     => 'url',
		'label'       => esc_html__( 'Wishlist URL', 'e-storefront' ),
		'settings' => 'e_storefront_wislist_url',
		'section'  => 'e_storefront_header_section',
		'default'  => '',
		'priority' => 12,
	] );

	Kirki::add_field( 'theme_config_id', [
		'type'     => 'url',
		'label'       => esc_html__( 'Bell URL', 'e-storefront' ),
		'settings' => 'e_storefront_bell_link',
		'section'  => 'e_storefront_header_section',
		'default'  => '',
		'priority' => 12,
	] );

	// Header Info SECTION

	Kirki::add_section( 'e_storefront_header_info_section', array(
        'title'          => esc_html__( ' Header Info Settings', 'e-storefront' ),
        'panel'          => 'e_storefront_panel_id',
        'priority'       => 160,
    ) );

	Kirki::add_field( 'theme_config_id', [
	    'label'       => '<span class="custom-label-class">' . esc_html__( 'INFORMATION ABOUT PREMIUM VERSION :-', 'e-storefront' ) . '</span>',
	    'default'     => '<a class="premium_info_btn" target="_blank" href="' . esc_url( E_STOREFRONT_BUY_NOW_1 ) . '">' . __( 'GO TO PREMIUM', 'e-storefront' ) . '</a>',
	    'type'        => 'custom',
	    'section'     => 'e_storefront_header_info_section',
	    'description' => '<div class="custom-description-class">' . __( '<p>1. One Click Demo Importer </p><p>2. Color Pallete Setup </p><p>3. Section Reordering Facility</p><p>4. For More Options kindly Go For Premium Version.</p>', 'e-storefront' ) . '</div>',
		'priority'    => 10,
	]);

    Kirki::add_field( 'theme_config_id', [
		'type'        => 'custom',
		'settings'    => 'e_storefront_enable_heading_1',
		'section'     => 'e_storefront_header_info_section',
			'default'         => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Enable / Disable Header Info', 'e-storefront' ) . '</h3>',
		'priority'    => 10,
	] );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'switch',
		'settings'    => 'e_storefront_category_box_enable',
		'label'       => esc_html__( 'Section Enable / Disable', 'e-storefront' ),
		'section'     => 'e_storefront_header_info_section',
		'default'     => true,
		'priority'    => 10,
		'choices'     => [
			'on'  => esc_html__( 'Enable', 'e-storefront' ),
			'off' => esc_html__( 'Disable', 'e-storefront' ),
		],
	] );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'custom',
		'settings'    => 'e_storefront_slider_text_heading_1',
		'section'     => 'e_storefront_header_info_section',
		'default'     => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Track Button', 'e-storefront' ) . '</h3>',
	] );

	Kirki::add_field( 'theme_config_id', [
		'label'    => esc_html__( 'ButtonText',  'e-storefront' ),
		'type'     => 'text',
		'settings' => 'e_storefront_slider_track_button_text',
		'section'  => 'e_storefront_header_info_section',
    ] );

    Kirki::add_field( 'theme_config_id', [
    	'label'    => esc_html__( 'Button Url',  'e-storefront' ),
		'type'     => 'url',
		'settings' => 'e_storefront_slider_track_button_url',
		'section'  => 'e_storefront_header_info_section',
    ] );

    Kirki::add_field( 'theme_config_id', [
		'type'        => 'custom',
		'settings'    => 'e_storefront_slider_text_heading',
		'section'     => 'e_storefront_header_info_section',
		'default'     => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Compare Button', 'e-storefront' ) . '</h3>',
	] );

	Kirki::add_field( 'theme_config_id', [
		'label'    => esc_html__( 'Button Text',  'e-storefront' ),
		'type'     => 'text',
		'settings' => 'e_storefront_slider_compare_button_text',
		'section'  => 'e_storefront_header_info_section',
    ] );

    Kirki::add_field( 'theme_config_id', [
    	'label'    => esc_html__( 'Button Url',  'e-storefront' ),
		'type'     => 'url',
		'settings' => 'e_storefront_slider_compare_button_url',
		'section'  => 'e_storefront_header_info_section',
    ] );

    Kirki::add_field( 'theme_config_id', [
		'type'        => 'custom',
		'settings'    => 'e_storefront_slider_text_heading_2',
		'section'     => 'e_storefront_header_info_section',
		'default'     => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Support Button', 'e-storefront' ) . '</h3>',
	] );

	Kirki::add_field( 'theme_config_id', [
		'label'    => esc_html__( 'Button Text',  'e-storefront' ),
		'type'     => 'text',
		'settings' => 'e_storefront_slider_support_button_text',
		'section'  => 'e_storefront_header_info_section',
    ] );

    Kirki::add_field( 'theme_config_id', [
    	'label'    => esc_html__( 'Button Url',  'e-storefront' ),
		'type'     => 'url',
		'settings' => 'e_storefront_slider_support_button_url',
		'section'  => 'e_storefront_header_info_section',
    ] );

    Kirki::add_field( 'theme_config_id', [
		'type'        => 'custom',
		'settings'    => 'e_storefront_slider_text_heading_3',
		'section'     => 'e_storefront_header_info_section',
		'default'     => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Help Button', 'e-storefront' ) . '</h3>',
	] );

	Kirki::add_field( 'theme_config_id', [
		'label'    => esc_html__( 'Button Text',  'e-storefront' ),
		'type'     => 'text',
		'settings' => 'e_storefront_slider_help_button_text',
		'section'  => 'e_storefront_header_info_section',
    ] );

    Kirki::add_field( 'theme_config_id', [
    	'label'    => esc_html__( 'Button Url',  'e-storefront' ),
		'type'     => 'url',
		'settings' => 'e_storefront_slider_help_button_url',
		'section'  => 'e_storefront_header_info_section',
    ] );

	// SLIDER SECTION

	Kirki::add_section( 'e_storefront_blog_slide_section', array(
        'title'          => esc_html__( ' Slider Settings', 'e-storefront' ),
        'panel'          => 'e_storefront_panel_id',
        'priority'       => 160,
    ) );

	Kirki::add_field( 'theme_config_id', [
	    'label'       => '<span class="custom-label-class">' . esc_html__( 'INFORMATION ABOUT PREMIUM VERSION :-', 'e-storefront' ) . '</span>',
	    'default'     => '<a class="premium_info_btn" target="_blank" href="' . esc_url( E_STOREFRONT_BUY_NOW_1 ) . '">' . __( 'GO TO PREMIUM', 'e-storefront' ) . '</a>',
	    'type'        => 'custom',
	    'section'     => 'e_storefront_blog_slide_section',
	    'description' => '<div class="custom-description-class">' . __( '<p>1. One Click Demo Importer </p><p>2. Color Pallete Setup </p><p>3. Section Reordering Facility</p><p>4. For More Options kindly Go For Premium Version.</p>', 'e-storefront' ) . '</div>',
		'priority'    => 10,
	]);

    Kirki::add_field( 'theme_config_id', [
		'type'        => 'custom',
		'settings'    => 'e_storefront_enable_heading_2',
		'section'     => 'e_storefront_blog_slide_section',
			'default'         => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Enable / Disable Slider', 'e-storefront' ) . '</h3>',
		'priority'    => 10,
	] );

    Kirki::add_field( 'theme_config_id', [
		'type'        => 'switch',
		'settings'    => 'e_storefront_blog_box_enable',
		'label'       => esc_html__( 'Section Enable / Disable', 'e-storefront' ),
		'section'     => 'e_storefront_blog_slide_section',
		'default'     => true,
		'priority'    => 10,
		'choices'     => [
			'on'  => esc_html__( 'Enable', 'e-storefront' ),
			'off' => esc_html__( 'Disable', 'e-storefront' ),
		],
	] );

    Kirki::add_field( 'theme_config_id', [
		'type'        => 'custom',
		'settings'    => 'e_storefront_slider_heading',
		'section'     => 'e_storefront_blog_slide_section',
			'default'         => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Slider', 'e-storefront' ) . '</h3>',
		'priority'    => 10,
	] );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'switch',
		'settings'    => 'e_storefront_slider_categories',
		'label'       => esc_html__( 'Categories Tab Enable / Disable', 'e-storefront' ),
		'section'     => 'e_storefront_blog_slide_section',
		'default'     => true,
		'priority'    => 10,
		'choices'     => [
			'on'  => esc_html__( 'Enable', 'e-storefront' ),
			'off' => esc_html__( 'Disable', 'e-storefront' ),
		],
	] );

	Kirki::add_field( 'theme_config_id', [
		'label'    => esc_html__( 'Left Categories Heading',  'e-storefront' ),
		'type'     => 'text',
		'settings' => 'e_storefront_slider_categories_heading',
		'section'  => 'e_storefront_blog_slide_section',
    ] );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'number',
		'settings'    => 'e_storefront_blog_slide_number',
		'label'       => esc_html__( 'Number of slides to show', 'e-storefront' ),
		'section'     => 'e_storefront_blog_slide_section',
		'default'     => 0,
		'description' => esc_html__( 'After selecting no of slides publish them. Now you need to refresh the site After refreshing you will see further settings', 'e-storefront' ),
		'choices'     => [
			'min'  => 0,
			'max'  => 3,
			'step' => 1,
		],
	] );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'select',
		'settings'    => 'e_storefront_blog_slide_category',
		'label'       => esc_html__( 'Select the category to show slider ( Image Dimension 1600 x 600 )', 'e-storefront' ),
		'section'     => 'e_storefront_blog_slide_section',
		'default'     => '',
		'placeholder' => esc_html__( 'Select an category...', 'e-storefront' ),
		'priority'    => 10,
		'choices'     => e_storefront_get_categories_select(),
	] );

    new \Kirki\Field\Image(
		[
			'settings'    => 'e_storefront_slider_right_image_1',
			'label'       => esc_html__( 'Right Box Upload Image 1', 'e-storefront' ),
			'section'     => 'e_storefront_blog_slide_section',
			'default'     => '',
		]
	);

	Kirki::add_field( 'theme_config_id', [
		'label'    => esc_html__( 'Short Text',  'e-storefront' ),
		'type'     => 'text',
		'settings' => 'e_storefront_slider_right_box_1_short_heading',
		'section'  => 'e_storefront_blog_slide_section',
    ] );

    Kirki::add_field( 'theme_config_id', [
    	'label'    => esc_html__( 'Heading',  'e-storefront' ),
		'type'     => 'text',
		'settings' => 'e_storefront_slider_right_box_1_heading',
		'section'  => 'e_storefront_blog_slide_section',
    ] );

    Kirki::add_field( 'theme_config_id', [
    	'label'    => esc_html__( 'Button Url',  'e-storefront' ),
		'type'     => 'url',
		'settings' => 'e_storefront_slider_right_box_1_button_url',
		'section'  => 'e_storefront_blog_slide_section',
    ] );

	new \Kirki\Field\Image(
		[
			'settings'    => 'e_storefront_slider_right_image_2',
			'label'       => esc_html__( 'Right Box Upload Image 2', 'e-storefront' ),
			'section'     => 'e_storefront_blog_slide_section',
			'default'     => '',
		]
	);

	Kirki::add_field( 'theme_config_id', [
		'label'    => esc_html__( 'Short Text',  'e-storefront' ),
		'type'     => 'text',
		'settings' => 'e_storefront_slider_right_box_2_short_heading',
		'section'  => 'e_storefront_blog_slide_section',
    ] );

    Kirki::add_field( 'theme_config_id', [
    	'label'    => esc_html__( 'Heading',  'e-storefront' ),
		'type'     => 'text',
		'settings' => 'e_storefront_slider_right_box_2_heading',
		'section'  => 'e_storefront_blog_slide_section',
    ] );

    Kirki::add_field( 'theme_config_id', [
    	'label'    => esc_html__( 'Price',  'e-storefront' ),
		'type'     => 'text',
		'settings' => 'e_storefront_slider_right_box_2_price',
		'section'  => 'e_storefront_blog_slide_section',
    ] );

	//Categories SECTION

	Kirki::add_section( 'e_storefront_testimonial_section', array(
	    'title'          => esc_html__( 'Categories Settings', 'e-storefront' ),
	    'panel'          => 'e_storefront_panel_id',
	    'priority'       => 160,
	) );

	Kirki::add_field( 'theme_config_id', [
	    'label'       => '<span class="custom-label-class">' . esc_html__( 'INFORMATION ABOUT PREMIUM VERSION :-', 'e-storefront' ) . '</span>',
	    'default'     => '<a class="premium_info_btn" target="_blank" href="' . esc_url( E_STOREFRONT_BUY_NOW_1 ) . '">' . __( 'GO TO PREMIUM', 'e-storefront' ) . '</a>',
	    'type'        => 'custom',
	    'section'     => 'e_storefront_testimonial_section',
	    'description' => '<div class="custom-description-class">' . __( '<p>1. One Click Demo Importer </p><p>2. Color Pallete Setup </p><p>3. Section Reordering Facility</p><p>4. For More Options kindly Go For Premium Version.</p>', 'e-storefront' ) . '</div>',
		'priority'    => 10,
	]);

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'custom',
		'settings'    => 'e_storefront_enable_heading_1',
		'section'     => 'e_storefront_testimonial_section',
			'default'         => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Enable / Disable Categories',  'e-storefront' ) . '</h3>',
		'priority'    => 1,
	] );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'switch',
		'settings'    => 'e_storefront_testimonial_section_enable',
		'section'     => 'e_storefront_testimonial_section',
		'default'     => true,
		'priority'    => 2,
		'choices'     => [
			'on'  => esc_html__( 'Enable',  'e-storefront' ),
			'off' => esc_html__( 'Disable',  'e-storefront' ),
		],
	] );

	Kirki::add_field( 'theme_config_id', [
		'label'    => esc_html__( 'Categories Heading',  'e-storefront' ),
		'type'     => 'text',
		'settings' => 'e_storefront_top_categories_heading',
		'section'  => 'e_storefront_testimonial_section',
    ] );

	//Product SECTION

	Kirki::add_section( 'e_storefront_product_section', array(
	    'title'          => esc_html__( 'Product Settings', 'e-storefront' ),
	    'panel'          => 'e_storefront_panel_id',
	    'priority'       => 160,
	) );

	Kirki::add_field( 'theme_config_id', [
	    'label'       => '<span class="custom-label-class">' . esc_html__( 'INFORMATION ABOUT PREMIUM VERSION :-', 'e-storefront' ) . '</span>',
	    'default'     => '<a class="premium_info_btn" target="_blank" href="' . esc_url( E_STOREFRONT_BUY_NOW_1 ) . '">' . __( 'GO TO PREMIUM', 'e-storefront' ) . '</a>',
	    'type'        => 'custom',
	    'section'     => 'e_storefront_product_section',
	    'description' => '<div class="custom-description-class">' . __( '<p>1. One Click Demo Importer </p><p>2. Color Pallete Setup </p><p>3. Section Reordering Facility</p><p>4. For More Options kindly Go For Premium Version.</p>', 'e-storefront' ) . '</div>',
		'priority'    => 10,
	]);

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'custom',
		'settings'    => 'e_storefront_enable_heading',
		'section'     => 'e_storefront_product_section',
			'default'         => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Enable / Disable Product',  'e-storefront' ) . '</h3>',
		'priority'    => 1,
	] );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'switch',
		'settings'    => 'e_storefront_product_section_enable',
		'section'     => 'e_storefront_product_section',
		'default'     => false,
		'priority'    => 2,
		'choices'     => [
			'on'  => esc_html__( 'Enable',  'e-storefront' ),
			'off' => esc_html__( 'Disable',  'e-storefront' ),
		],
	] );

	Kirki::add_field( 'theme_config_id', [
		'label'    => esc_html__( 'Product Heading',  'e-storefront' ),
		'type'     => 'text',
		'settings' => 'e_storefront_products_short_heading',
		'section'  => 'e_storefront_product_section',
    ] );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'custom',
		'settings'    => 'e_storefront_category_heading',
		'section'     => 'e_storefront_product_section',
			'default'         => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Select the category to show Products',  'e-storefront' ) . '</h3>',
	] );

	// FOOTER SECTION

	Kirki::add_section( 'e_storefront_footer_section', array(
        'title'          => esc_html__( 'Footer Settings', 'e-storefront' ),
        'description'    => esc_html__( 'Here you can change copyright text', 'e-storefront' ),
        'panel'          => 'e_storefront_panel_id',
        'priority'       => 160,
    ) );

	Kirki::add_field( 'theme_config_id', [
	    'label'       => '<span class="custom-label-class">' . esc_html__( 'INFORMATION ABOUT PREMIUM VERSION :-', 'e-storefront' ) . '</span>',
	    'default'     => '<a class="premium_info_btn" target="_blank" href="' . esc_url( E_STOREFRONT_BUY_NOW_1 ) . '">' . __( 'GO TO PREMIUM', 'e-storefront' ) . '</a>',
	    'type'        => 'custom',
	    'section'     => 'e_storefront_footer_section',
	    'description' => '<div class="custom-description-class">' . __( '<p>1. One Click Demo Importer </p><p>2. Color Pallete Setup </p><p>3. Section Reordering Facility</p><p>4. For More Options kindly Go For Premium Version.</p>', 'e-storefront' ) . '</div>',
		'priority'    => 10,
	]);


	Kirki::add_field( 'theme_config_id', [
		'type'        => 'custom',
		'settings'    => 'e_storefront_footer_enable_heading',
		'section'     => 'e_storefront_footer_section',
			'default'         => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Enable / Disable Footer Link', 'e-storefront' ) . '</h3>',
		'priority'    => 10,
	] );

    Kirki::add_field( 'theme_config_id', [
		'type'        => 'switch',
		'settings'    => 'e_storefront_copyright_enable',
		'label'       => esc_html__( 'Section Enable / Disable', 'e-storefront' ),
		'section'     => 'e_storefront_footer_section',
		'default'     => '1',
		'priority'    => 10,
		'choices'     => [
			'on'  => esc_html__( 'Enable', 'e-storefront' ),
			'off' => esc_html__( 'Disable', 'e-storefront' ),
		],
	] );

    Kirki::add_field( 'theme_config_id', [
		'type'        => 'custom',
		'settings'    => 'e_storefront_footer_text_heading',
		'section'     => 'e_storefront_footer_section',
			'default'         => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Footer Copyright Text', 'e-storefront' ) . '</h3>',
		'priority'    => 10,
	] );

    Kirki::add_field( 'theme_config_id', [
		'type'     => 'text',
		'settings' => 'e_storefront_footer_text',
		'section'  => 'e_storefront_footer_section',
		'default'  => '',
		'priority' => 10,
	] );

	Kirki::add_field( 'theme_config_id', [
	'type'        => 'custom',
	'settings'    => 'e_storefront_footer_text_heading_2',
	'section'     => 'e_storefront_footer_section',
		'default'         => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Footer Copyright Alignment', 'e-storefront' ) . '</h3>',
	'priority'    => 10,
	] );

	new \Kirki\Field\Select(
	[
		'settings'    => 'e_storefront_copyright_text_alignment',
		'label'       => esc_html__( 'Copyright text Alignment', 'e-storefront' ),
		'section'     => 'e_storefront_footer_section',
		'default'     => 'LEFT-ALIGN',
		'placeholder' => esc_html__( 'Choose an option', 'e-storefront' ),
		'choices'     => [
			'LEFT-ALIGN' => esc_html__( 'LEFT-ALIGN', 'e-storefront' ),
			'CENTER-ALIGN' => esc_html__( 'CENTER-ALIGN', 'e-storefront' ),
			'RIGHT-ALIGN' => esc_html__( 'RIGHT-ALIGN', 'e-storefront' ),

		],
	] );

	Kirki::add_field( 'theme_config_id', [
	'type'        => 'custom',
	'settings'    => 'e_storefront_footer_text_heading_1',
	'section'     => 'e_storefront_footer_section',
		'default'         => '<h3 style="color: #2271b1; padding:10px; background:#fff; margin:0; border-left: solid 5px #2271b1; ">' . __( 'Footer Copyright Background Color', 'e-storefront' ) . '</h3>',
	'priority'    => 10,
	] );

	Kirki::add_field( 'theme_config_id', [
		'type'        => 'color',
		'settings'    => 'e_storefront_copyright_bg',
		'label'       => __( 'Choose Your Copyright Background Color', 'e-storefront' ),
		'section'     => 'e_storefront_footer_section',
		'default'     => '#000000',
	] );
}

add_action( 'customize_register', 'e_storefront_customizer_settings' );
	function e_storefront_customizer_settings( $wp_customize ) {
		$e_storefront_args = array(
	       'type'                     => 'product',
	        'child_of'                 => 0,
	        'parent'                   => '',
	        'orderby'                  => 'term_group',
	        'order'                    => 'ASC',
	        'hide_empty'               => false,
	        'hierarchical'             => 1,
	        'number'                   => '',
	        'taxonomy'                 => 'product_cat',
	        'pad_counts'               => false
	    );
		$categories = get_categories($e_storefront_args);
		$e_storefront_cat_posts = array();
		$e_storefront_m = 0;
		$e_storefront_cat_posts[]='Select';
		foreach($categories as $category){
			if($e_storefront_m==0){
				$default = $category->slug;
				$e_storefront_m++;
			}
			$e_storefront_cat_posts[$category->slug] = $category->name;
		}

		$wp_customize->add_setting('e_storefront_products_category',array(
			'default'	=> 'select',
			'sanitize_callback' => 'e_storefront_sanitize_select',
			'description'    => esc_html__( 'You have to select product category to show product section.', 'e-storefront' ),
		));

		$wp_customize->add_control('e_storefront_products_category',array(
			'type'    => 'select',
			'choices' => $e_storefront_cat_posts,
			'label' => __('Select category to display Products ','e-storefront'),
			'section' => 'e_storefront_product_section',
		));
	}