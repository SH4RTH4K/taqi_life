<?php

class E_Storefront_Customizer_Notify {

	private $config = array(); // Declare $config property
	
	private $e_storefront_recommended_actions;
	
	private $recommended_plugins;
	
	private static $instance;
	
	private $e_storefront_recommended_actions_title;
	
	private $e_storefront_recommended_plugins_title;
	
	private $dismiss_button;
	
	private $e_storefront_install_button_label;
	
	private $e_storefront_activate_button_label;
	
	private $e_storefront_deactivate_button_label;

	
	public static function init( $config ) {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof E_Storefront_Customizer_Notify ) ) {
			self::$instance = new E_Storefront_Customizer_Notify;
			if ( ! empty( $config ) && is_array( $config ) ) {
				self::$instance->config = $config;
				self::$instance->setup_config();
				self::$instance->setup_actions();
			}
		}

	}

	
	public function setup_config() {

		global $e_storefront_customizer_notify_recommended_plugins;
		global $e_storefront_customizer_notify_e_storefront_recommended_actions;

		global $e_storefront_install_button_label;
		global $e_storefront_activate_button_label;
		global $e_storefront_deactivate_button_label;

		$this->e_storefront_recommended_actions = isset( $this->config['e_storefront_recommended_actions'] ) ? $this->config['e_storefront_recommended_actions'] : array();
		$this->recommended_plugins = isset( $this->config['recommended_plugins'] ) ? $this->config['recommended_plugins'] : array();

		$this->e_storefront_recommended_actions_title = isset( $this->config['e_storefront_recommended_actions_title'] ) ? $this->config['e_storefront_recommended_actions_title'] : '';
		$this->e_storefront_recommended_plugins_title = isset( $this->config['e_storefront_recommended_plugins_title'] ) ? $this->config['e_storefront_recommended_plugins_title'] : '';
		$this->dismiss_button            = isset( $this->config['dismiss_button'] ) ? $this->config['dismiss_button'] : '';

		$e_storefront_customizer_notify_recommended_plugins = array();
		$e_storefront_customizer_notify_e_storefront_recommended_actions = array();

		if ( isset( $this->recommended_plugins ) ) {
			$e_storefront_customizer_notify_recommended_plugins = $this->recommended_plugins;
		}

		if ( isset( $this->e_storefront_recommended_actions ) ) {
			$e_storefront_customizer_notify_e_storefront_recommended_actions = $this->e_storefront_recommended_actions;
		}

		$e_storefront_install_button_label    = isset( $this->config['e_storefront_install_button_label'] ) ? $this->config['e_storefront_install_button_label'] : '';
		$e_storefront_activate_button_label   = isset( $this->config['e_storefront_activate_button_label'] ) ? $this->config['e_storefront_activate_button_label'] : '';
		$e_storefront_deactivate_button_label = isset( $this->config['e_storefront_deactivate_button_label'] ) ? $this->config['e_storefront_deactivate_button_label'] : '';

	}

	
	public function setup_actions() {

		// Register the section
		add_action( 'customize_register', array( $this, 'e_storefront_plugin_notification_customize_register' ) );

		// Enqueue scripts and styles
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'e_storefront_customizer_notify_scripts_for_customizer' ), 0 );

		/* ajax callback for dismissable recommended actions */
		add_action( 'wp_ajax_quality_customizer_notify_dismiss_action', array( $this, 'e_storefront_customizer_notify_dismiss_recommended_action_callback' ) );

		add_action( 'wp_ajax_ti_customizer_notify_dismiss_recommended_plugins', array( $this, 'e_storefront_customizer_notify_dismiss_recommended_plugins_callback' ) );

	}

	
	public function e_storefront_customizer_notify_scripts_for_customizer() {

		wp_enqueue_style( 'e-storefront-customizer-notify-css', get_template_directory_uri() . '/core/includes/customizer-notice/css/e-storefront-customizer-notify.css', array());

		wp_enqueue_style( 'plugin-install' );
		wp_enqueue_script( 'plugin-install' );
		wp_add_inline_script( 'plugin-install', 'var pagenow = "customizer";' );

		wp_enqueue_script( 'updates' );

		wp_enqueue_script( 'e-storefront-customizer-notify-js', get_template_directory_uri() . '/core/includes/customizer-notice/js/e-storefront-customizer-notify.js', array( 'customize-controls' ));
		wp_localize_script(
			'e-storefront-customizer-notify-js', 'estorefrontCustomizercompanionObject', array(
				'ajaxurl'            => admin_url( 'admin-ajax.php' ),
				'template_directory' => get_template_directory_uri(),
				'base_path'          => admin_url(),
				'activating_string'  => __( 'Activating', 'e-storefront' ),
			)
		);

	}

	
	public function e_storefront_plugin_notification_customize_register( $wp_customize ) {

		
		require_once get_template_directory() . '/core/includes/customizer-notice/e-storefront-customizer-notify-section.php';

		$wp_customize->register_section_type( 'E_Storefront_Customizer_Notify_Section' );

		$wp_customize->add_section(
			new E_Storefront_Customizer_Notify_Section(
				$wp_customize,
				'e-storefront-customizer-notify-section',
				array(
					'title'          => $this->e_storefront_recommended_actions_title,
					'plugin_text'    => $this->e_storefront_recommended_plugins_title,
					'dismiss_button' => $this->dismiss_button,
					'priority'       => 0,
				)
			)
		);

	}

	
	public function e_storefront_customizer_notify_dismiss_recommended_action_callback() {

		global $e_storefront_customizer_notify_e_storefront_recommended_actions;

		$action_id = ( isset( $_GET['id'] ) ) ? $_GET['id'] : 0;

		echo esc_html( $action_id ); /* this is needed and it's the id of the dismissable required action */ 

		if ( ! empty( $action_id ) ) {
			
			if ( get_option( 'e_storefront_customizer_notify_show' ) ) {

				$e_storefront_customizer_notify_show_e_storefront_recommended_actions = get_option( 'e_storefront_customizer_notify_show' );
				switch ( $_GET['todo'] ) {
					case 'add':
						$e_storefront_customizer_notify_show_e_storefront_recommended_actions[ $action_id ] = true;
						break;
					case 'dismiss':
						$e_storefront_customizer_notify_show_e_storefront_recommended_actions[ $action_id ] = false;
						break;
				}
				update_option( 'e_storefront_customizer_notify_show', $e_storefront_customizer_notify_show_e_storefront_recommended_actions );

				
			} else {
				$e_storefront_customizer_notify_show_e_storefront_recommended_actions = array();
				if ( ! empty( $e_storefront_customizer_notify_e_storefront_recommended_actions ) ) {
					foreach ( $e_storefront_customizer_notify_e_storefront_recommended_actions as $e_storefront_lite_customizer_notify_recommended_action ) {
						if ( $e_storefront_lite_customizer_notify_recommended_action['id'] == $action_id ) {
							$e_storefront_customizer_notify_show_e_storefront_recommended_actions[ $e_storefront_lite_customizer_notify_recommended_action['id'] ] = false;
						} else {
							$e_storefront_customizer_notify_show_e_storefront_recommended_actions[ $e_storefront_lite_customizer_notify_recommended_action['id'] ] = true;
						}
					}
					update_option( 'e_storefront_customizer_notify_show', $e_storefront_customizer_notify_show_e_storefront_recommended_actions );
				}
			}
		}
		die(); 
	}

	
	public function e_storefront_customizer_notify_dismiss_recommended_plugins_callback() {

		$action_id = ( isset( $_GET['id'] ) ) ? $_GET['id'] : 0;

		echo esc_html( $action_id ); /* this is needed and it's the id of the dismissable required action */

		if ( ! empty( $action_id ) ) {

			$e_storefront_lite_customizer_notify_show_recommended_plugins = get_option( 'e_storefront_customizer_notify_show_recommended_plugins' );

			switch ( $_GET['todo'] ) {
				case 'add':
					$e_storefront_lite_customizer_notify_show_recommended_plugins[ $action_id ] = false;
					break;
				case 'dismiss':
					$e_storefront_lite_customizer_notify_show_recommended_plugins[ $action_id ] = true;
					break;
			}
			update_option( 'e_storefront_customizer_notify_show_recommended_plugins', $e_storefront_lite_customizer_notify_show_recommended_plugins );
		}
		die(); 
	}

}
