<?php
	the_posts_pagination( array(
		'prev_text' => esc_html__( 'Previous page', 'e-storefront' ),
		'next_text' => esc_html__( 'Next page', 'e-storefront' ),
	) );