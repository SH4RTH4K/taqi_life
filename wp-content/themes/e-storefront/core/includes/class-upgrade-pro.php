<?php
/**
 * Singleton class for handling the theme's customizer integration.
 *
 * @since  1.0.0
 * @access public
 */
final class E_Storefront_Customize {

	/**
	 * Returns the instance.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return object
	 */
	public static function get_instance() {

		static $instance = null;

		if ( is_null( $instance ) ) {
			$instance = new self;
			$instance->setup_actions();
		}

		return $instance;
	}

	/**
	 * Constructor method.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private function __construct() {}

	/**
	 * Sets up initial actions.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return void
	 */
	private function setup_actions() {

		// Register panels, sections, settings, controls, and partials.
		add_action( 'customize_register', array( $this, 'sections' ) );

		// Register scripts and styles for the controls.
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_control_scripts' ), 0 );
	}

	/**
	 * Sets up the customizer sections.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  object  $manager
	 * @return void
	*/
	public function sections( $manager ) {

		// Load custom sections.
		load_template( trailingslashit( get_template_directory() ) . '/core/includes/upgrade-pro.php' );

		// Register custom section types.
		$manager->register_section_type( 'E_Storefront_Customize_Section_Pro' );

		// Add the PRO Upgrade section.
		$manager->add_section(
		    new E_Storefront_Customize_Section_Pro(
		        $manager,
		        'e_storefront_upgrade_pro',
		        array(
		            'title'         => esc_html__( 'E Storefront PRO', 'e-storefront' ),
		            'pro_text'      => esc_html__( 'E Storefront PRO', 'e-storefront' ),
		            'pro_url'       => esc_url( E_STOREFRONT_BUY_NOW ),
		            'demo_text'     => esc_html__( 'Demo', 'e-storefront' ),
		            'demo_url'      => esc_url( E_STOREFRONT_DEMO_PRO ),
		            'support_text'  => esc_html__( 'Support', 'e-storefront' ),
		            'support_url'   => esc_url( E_STOREFRONT_SUPPORT_FREE ),
		            'bundle_text'   => esc_html__( 'Get All Themes', 'e-storefront' ),
		            'bundle_url'    => esc_url( E_STOREFRONT_THEME_BUNDLE ),
		            'lite_doc_text' => esc_html__( 'Lite Doc', 'e-storefront' ),
		            'lite_doc_url'  => esc_url( E_STOREFRONT_DOCS_FREE ),
		            'review_text'   => esc_html__( 'Review', 'e-storefront' ),
		            'review_url'    => esc_url( E_STOREFRONT_REVIEW_FREE ),
		            'priority'      => 1,
		        )
		    )
		);

	}

	/**
	 * Loads theme customizer CSS.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function enqueue_control_scripts() {

		wp_enqueue_script(
			'e-storefront-customize-controls',
			trailingslashit( get_template_directory_uri() ) . '/js/customize-controls.js',
			array( 'customize-controls' )
		);

		wp_enqueue_style(
			'e-storefront-customize-controls',
			trailingslashit( get_template_directory_uri() ) . '/css/customize-controls.css'
		);
	}
}

// Doing this customizer thang!
E_Storefront_Customize::get_instance();
