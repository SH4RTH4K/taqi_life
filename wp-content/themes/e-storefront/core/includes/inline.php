<?php


$e_storefront_custom_css = '';

	/*---------------------------text-transform-------------------*/

	$e_storefront_text_transform = get_theme_mod( 'menu_text_transform_e_storefront','CAPITALISE');
    if($e_storefront_text_transform == 'CAPITALISE'){

		$e_storefront_custom_css .='.main-navigation ul li a{';

			$e_storefront_custom_css .='text-transform: capitalize;';

		$e_storefront_custom_css .='}';

	}else if($e_storefront_text_transform == 'UPPERCASE'){

		$e_storefront_custom_css .='.main-navigation ul li a{';

			$e_storefront_custom_css .='text-transform: uppercase;';

		$e_storefront_custom_css .='}';

	}else if($e_storefront_text_transform == 'LOWERCASE'){

		$e_storefront_custom_css .='.main-navigation ul li a{';

			$e_storefront_custom_css .='text-transform: lowercase;';

		$e_storefront_custom_css .='}';
	}

		/*---------------------------menu-zoom-------------------*/

		$e_storefront_menu_zoom = get_theme_mod( 'e_storefront_menu_zoom','None');

    if($e_storefront_menu_zoom == 'Zoomout'){

		$e_storefront_custom_css .='.main-navigation ul li a{';

			$e_storefront_custom_css .='';

		$e_storefront_custom_css .='}';

	}else if($e_storefront_menu_zoom == 'Zoominn'){

		$e_storefront_custom_css .='.main-navigation ul li a:hover{';

			$e_storefront_custom_css .='transition: all 0.3s ease-in-out !important; transform: scale(1.2) !important; color: var(--first-color);';

		$e_storefront_custom_css .='}';
	}

	/*---------------------------Container Width-------------------*/

$e_storefront_container_width = get_theme_mod('e_storefront_container_width');

		$e_storefront_custom_css .='body{';

			$e_storefront_custom_css .='width: '.esc_attr($e_storefront_container_width).'%; margin: auto';

		$e_storefront_custom_css .='}';

		/*---------------------------Copyright Text alignment-------------------*/

	$e_storefront_copyright_text_alignment = get_theme_mod( 'e_storefront_copyright_text_alignment','LEFT-ALIGN');

	if($e_storefront_copyright_text_alignment == 'LEFT-ALIGN'){

		$e_storefront_custom_css .='.copy-text p{';

			$e_storefront_custom_css .='text-align:left;';

		$e_storefront_custom_css .='}';


	}else if($e_storefront_copyright_text_alignment == 'CENTER-ALIGN'){

		$e_storefront_custom_css .='.copy-text p{';

			$e_storefront_custom_css .='text-align:center;';

		$e_storefront_custom_css .='}';


	}else if($e_storefront_copyright_text_alignment == 'RIGHT-ALIGN'){

		$e_storefront_custom_css .='.copy-text p{';

			$e_storefront_custom_css .='text-align:right;';

		$e_storefront_custom_css .='}';

	}

	/*---------------------------related Product Settings-------------------*/

$e_storefront_related_product_setting = get_theme_mod('e_storefront_related_product_setting',true);

	if($e_storefront_related_product_setting == false){

		$e_storefront_custom_css .='.related.products, .related h2{';

			$e_storefront_custom_css .='display: none;';

		$e_storefront_custom_css .='}';
	}

		/*---------------------------Scroll to Top Alignment Settings-------------------*/

		$e_storefront_scroll_top_position = get_theme_mod( 'e_storefront_scroll_top_position','Right');

		if($e_storefront_scroll_top_position == 'Right'){
	
			$e_storefront_custom_css .='.scroll-up{';
	
				$e_storefront_custom_css .='right: 20px;';
	
			$e_storefront_custom_css .='}';
	
		}else if($e_storefront_scroll_top_position == 'Left'){
	
			$e_storefront_custom_css .='.scroll-up{';
	
				$e_storefront_custom_css .='left: 20px;';
	
			$e_storefront_custom_css .='}';
	
		}else if($e_storefront_scroll_top_position == 'Center'){
	
			$e_storefront_custom_css .='.scroll-up{';
	
				$e_storefront_custom_css .='right: 50%;left: 50%;';
	
			$e_storefront_custom_css .='}';
		}
	
			/*---------------------------Pagination Settings-------------------*/
	
	
	$e_storefront_pagination_setting = get_theme_mod('e_storefront_pagination_setting',true);
	
		if($e_storefront_pagination_setting == false){
	
			$e_storefront_custom_css .='.nav-links{';
	
				$e_storefront_custom_css .='display: none;';
	
			$e_storefront_custom_css .='}';
		}

	/*---------------------------woocommerce pagination alignment settings-------------------*/

	$e_storefront_woocommerce_pagination_position = get_theme_mod( 'e_storefront_woocommerce_pagination_position','Center');

	if($e_storefront_woocommerce_pagination_position == 'Left'){

		$e_storefront_custom_css .='.woocommerce nav.woocommerce-pagination{';

			$e_storefront_custom_css .='text-align: left;';

		$e_storefront_custom_css .='}';

	}else if($e_storefront_woocommerce_pagination_position == 'Center'){

		$e_storefront_custom_css .='.woocommerce nav.woocommerce-pagination{';

			$e_storefront_custom_css .='text-align: center;';

		$e_storefront_custom_css .='}';

	}else if($e_storefront_woocommerce_pagination_position == 'Right'){

		$e_storefront_custom_css .='.woocommerce nav.woocommerce-pagination{';

			$e_storefront_custom_css .='text-align: right;';

		$e_storefront_custom_css .='}';
	}

	/*---------------------------Global Color-------------------*/


	$e_storefront_first_color = get_theme_mod('e_storefront_first_color');

	/*--- First Global Color ---*/

	if ($e_storefront_first_color) {
	  $e_storefront_custom_css .= ':root {';
	  $e_storefront_custom_css .= '--first-color: ' . esc_attr($e_storefront_first_color) . ' !important;';
	  $e_storefront_custom_css .= '} ';
	}