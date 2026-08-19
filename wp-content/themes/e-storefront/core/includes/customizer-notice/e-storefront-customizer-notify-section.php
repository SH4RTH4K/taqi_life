<?php

class E_Storefront_Customizer_Notify_Section extends WP_Customize_Section {
	
	public $type = 'e-storefront-customizer-notify-section';
	
	public $e_storefront_recommended_actions = '';
	
	public $recommended_plugins = '';
	
	public $total_actions = '';
	
	public $plugin_text = '';
	
	public $dismiss_button = '';

	
	public function check_active( $slug ) {
		if ( file_exists( ABSPATH . 'wp-content/plugins/' . $slug . '/' . $slug . '.php' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' ); 
			$needs = is_plugin_active( $slug . '/' . $slug . '.php' ) ? 'deactivate' : 'activate';

			return array(
				'status' => is_plugin_active( $slug . '/' . $slug . '.php' ),
				'needs'  => $needs,
			);
		}

		return array(
			'status' => false,
			'needs'  => 'install',
		);
	}

	
	public function create_action_link( $state, $slug ) {
		switch ( $state ) {
			case 'install':
				return wp_nonce_url(
					add_query_arg(
						array(
							'action' => 'install-plugin',
							'plugin' => $slug,
						),
						network_admin_url( 'update.php' )
					),
					'install-plugin_' . $slug
				);
				break;
			case 'deactivate':
				return add_query_arg(
					array(
						'action'        => 'deactivate',
						'plugin'        => rawurlencode( $slug . '/' . $slug . '.php' ),
						'plugin_status' => 'all',
						'paged'         => '1',
						'_wpnonce'      => wp_create_nonce( 'deactivate-plugin_' . $slug . '/' . $slug . '.php' ),
					), network_admin_url( 'plugins.php' )
				);
				break;
			case 'activate':
				return add_query_arg(
					array(
						'action'        => 'activate',
						'plugin'        => rawurlencode( $slug . '/' . $slug . '.php' ),
						'plugin_status' => 'all',
						'paged'         => '1',
						'_wpnonce'      => wp_create_nonce( 'activate-plugin_' . $slug . '/' . $slug . '.php' ),
					), network_admin_url( 'plugins.php' )
				);
				break;
		}// End switch().
	}

	
	public function call_plugin_api( $slug ) {
		require_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );
		$call_api = get_transient( 'e_storefront_cust_notify_plugin_info_' . $slug );
		if ( false === $call_api ) {
			$call_api = plugins_api(
				'plugin_information', array(
					'slug'   => $slug,
					'fields' => array(
						'downloaded'        => false,
						'rating'            => false,
						'description'       => false,
						'short_description' => true,
						'donate_link'       => false,
						'tags'              => false,
						'sections'          => false,
						'homepage'          => false,
						'added'             => false,
						'last_updated'      => false,
						'compatibility'     => false,
						'tested'            => false,
						'requires'          => false,
						'downloadlink'      => false,
						'icons'             => false,
					),
				)
			);
			set_transient( 'e_storefront_cust_notify_plugin_info_' . $slug, $call_api, 30 * MINUTE_IN_SECONDS );
		}

		return $call_api;
	}

	
	public function json() {
		$json = parent::json();
		global $e_storefront_customizer_notify_e_storefront_recommended_actions;
		global $e_storefront_customizer_notify_recommended_plugins;

		global $e_storefront_install_button_label;
		global $e_storefront_activate_button_label;
		global $e_storefront_deactivate_button_label;

		$formatted_array                               = array();
		$e_storefront_customizer_notify_show_e_storefront_recommended_actions = get_option( 'e_storefront_customizer_notify_show' );
		foreach ( $e_storefront_customizer_notify_e_storefront_recommended_actions as $key => $e_storefront_lite_customizer_notify_recommended_action ) {
			if ( $e_storefront_customizer_notify_show_e_storefront_recommended_actions[ $e_storefront_lite_customizer_notify_recommended_action['id'] ] === false ) {
				continue;
			}
			if ( $e_storefront_lite_customizer_notify_recommended_action['check'] ) {
				continue;
			}

			$e_storefront_lite_customizer_notify_recommended_action['index'] = $key + 1;

			if ( isset( $e_storefront_lite_customizer_notify_recommended_action['plugin_slug'] ) ) {
				$active = $this->check_active( $e_storefront_customizer_notify_recommended_action['plugin_slug'] );
				$e_storefront_lite_customizer_notify_recommended_action['url'] = $this->create_action_link( $active['needs'], $e_storefront_lite_customizer_notify_recommended_action['plugin_slug'] );
				if ( $active['needs'] !== 'install' && $active['status'] ) {
					$e_storefront_lite_customizer_notify_recommended_action['class'] = 'active';
				} else {
					$e_storefront_lite_customizer_notify_recommended_action['class'] = '';
				}

				switch ( $active['needs'] ) {
					case 'install':
						$e_storefront_lite_customizer_notify_recommended_action['button_class'] = 'install-now button';
						$e_storefront_lite_customizer_notify_recommended_action['button_label'] = $e_storefront_install_button_label;
						break;
					case 'activate':
						$e_storefront_lite_customizer_notify_recommended_action['button_class'] = 'activate-now button button-primary';
						$e_storefront_lite_customizer_notify_recommended_action['button_label'] = $e_storefront_activate_button_label;
						break;
					case 'deactivate':
						$e_storefront_lite_customizer_notify_recommended_action['button_class'] = 'deactivate-now button';
						$e_storefront_lite_customizer_notify_recommended_action['button_label'] = $e_storefront_deactivate_button_label;
						break;
				}
			}
			$formatted_array[] = $e_storefront_lite_customizer_notify_recommended_action;
		}// End foreach().

		$customize_plugins = array();

		$e_storefront_lite_customizer_notify_show_recommended_plugins = get_option( 'e_storefront_customizer_notify_show_recommended_plugins' );

		foreach ( $e_storefront_customizer_notify_recommended_plugins as $slug => $plugin_opt ) {

			if ( ! $plugin_opt['recommended'] ) {
				continue;
			}

			if ( isset( $e_storefront_lite_customizer_notify_show_recommended_plugins[ $slug ] ) && $e_storefront_lite_customizer_notify_show_recommended_plugins[ $slug ] ) {
				continue;
			}

			$active = $this->check_active( $slug );

			if ( ! empty( $active['needs'] ) && ( $active['needs'] == 'deactivate' ) ) {
				continue;
			}

			$ti_customizer_notify_recommended_plugin['url'] = $this->create_action_link( $active['needs'], $slug );
			if ( $active['needs'] !== 'install' && $active['status'] ) {
				$ti_customizer_notify_recommended_plugin['class'] = 'active';
			} else {
				$ti_customizer_notify_recommended_plugin['class'] = '';
			}
			
			switch ( $active['needs'] ) {
				case 'install':
					$ti_customizer_notify_recommended_plugin['button_class'] = 'install-now button';
					$ti_customizer_notify_recommended_plugin['button_label'] = $e_storefront_install_button_label;
					break;
				case 'activate':
					$ti_customizer_notify_recommended_plugin['button_class'] = 'activate-now button button-primary';
					$ti_customizer_notify_recommended_plugin['button_label'] = $e_storefront_activate_button_label;
					break;
				case 'deactivate':
					$ti_customizer_notify_recommended_plugin['button_class'] = 'deactivate-now button';
					$ti_customizer_notify_recommended_plugin['button_label'] = $e_storefront_deactivate_button_label;
					break;
			}
			$info = $this->call_plugin_api( $slug );
			$ti_customizer_notify_recommended_plugin['id']          = $slug;
			$ti_customizer_notify_recommended_plugin['plugin_slug'] = $slug;

			if ( ! empty( $plugin_opt['description'] ) ) {
				$ti_customizer_notify_recommended_plugin['description'] = $plugin_opt['description'];
			} else {
				$ti_customizer_notify_recommended_plugin['description'] = $info->short_description;
			}

			$ti_customizer_notify_recommended_plugin['title'] = $info->name;

			$customize_plugins[] = $ti_customizer_notify_recommended_plugin;

		}// End foreach().

		$json['e_storefront_recommended_actions'] = $formatted_array;
		$json['recommended_plugins'] = $customize_plugins;
		$json['total_actions']       = count( $e_storefront_customizer_notify_e_storefront_recommended_actions );
		$json['plugin_text']         = $this->plugin_text;
		$json['dismiss_button']      = $this->dismiss_button;
		return $json;

	}
	
	protected function render_template() {
	?>
		<# if( data.e_storefront_recommended_actions.length > 0 || data.recommended_plugins.length > 0 ){ #>
			<li id="accordion-section-{{ data.id }}" class="accordion-section control-section control-section-{{ data.type }} cannot-expand">

				<h3 class="accordion-section-title">
					<span class="section-title" data-plugin_text="{{ data.plugin_text }}">
						<# if( data.e_storefront_recommended_actions.length > 0 ){ #>
							{{ data.title }}
						<# }else{ #>
							<# if( data.recommended_plugins.length > 0 ){ #>
								{{ data.plugin_text }}
							<# }#>
						<# } #>
					</span>
					<# if( data.e_storefront_recommended_actions.length > 0 ){ #>
						<span class="e-storefront-customizer-plugin-notify-actions-count">
							<span class="current-index">{{ data.e_storefront_recommended_actions[0].index }}</span>
							{{ data.total_actions }}
						</span>
					<# } #>
				</h3>
				<div class="e-storefront-theme-recomended-actions_container" id="plugin-filter">
					<# if( data.e_storefront_recommended_actions.length > 0 ){ #>
						<# for (action in data.e_storefront_recommended_actions) { #>
							<div class="e-storefront-recommeded-actions-container epsilon-required-actions" data-index="{{ data.e_storefront_recommended_actions[action].index }}">
								<# if( !data.e_storefront_recommended_actions[action].check ){ #>
									<div class="e-storefront-epsilon-recommeded-actions">
										<p class="title">{{ data.e_storefront_recommended_actions[action].title }}</p>
										<span data-action="dismiss" class="dashicons dashicons-no e-storefront-customizer-notify-dismiss-recommended-action" id="{{ data.e_storefront_recommended_actions[action].id }}"></span>
										<div class="description">{{{ data.e_storefront_recommended_actions[action].description }}}</div>
										<# if( data.e_storefront_recommended_actions[action].plugin_slug ){ #>
											<div class="custom-action">
												<p class="plugin-card-{{ data.e_storefront_recommended_actions[action].plugin_slug }} action_button {{ data.e_storefront_recommended_actions[action].class }}">
													<a data-slug="{{ data.e_storefront_recommended_actions[action].plugin_slug }}" class="{{ data.e_storefront_recommended_actions[action].button_class }}" href="{{ data.e_storefront_recommended_actions[action].url }}">{{ data.e_storefront_recommended_actions[action].button_label }}</a>
												</p>
											</div>
										<# } #>
										<# if( data.e_storefront_recommended_actions[action].help ){ #>
											<div class="custom-action">{{{ data.e_storefront_recommended_actions[action].help }}}</div>
										<# } #>
									</div>
								<# } #>
							</div>
						<# } #>
					<# } #>

					<# if( data.recommended_plugins.length > 0 ){ #>
						<# for (action in data.recommended_plugins) { #>
							<div class="e-storefront-recommeded-actions-container epsilon-recommended-plugins" data-index="{{ data.recommended_plugins[action].index }}">
								<# if( !data.recommended_plugins[action].check ){ #>
									<div class="e-storefront-epsilon-recommeded-actions">
										<p class="title">{{ data.recommended_plugins[action].title }}</p>
										<span data-action="dismiss" class="dashicons dashicons-no e-storefront-customizer-notify-dismiss-button-recommended-plugin" id="{{ data.recommended_plugins[action].id }}"></span>
										<div class="description">{{{ data.recommended_plugins[action].description }}}</div>
										<# if( data.recommended_plugins[action].plugin_slug ){ #>
											<div class="custom-action">
												<p class="plugin-card-{{ data.recommended_plugins[action].plugin_slug }} action_button {{ data.recommended_plugins[action].class }}">
													<a data-slug="{{ data.recommended_plugins[action].plugin_slug }}" class="{{ data.recommended_plugins[action].button_class }}" href="{{ data.recommended_plugins[action].url }}">{{ data.recommended_plugins[action].button_label }}</a>
												</p>
											</div>
										<# } #>
										<# if( data.recommended_plugins[action].help ){ #>
											<div class="custom-action">{{{ data.recommended_plugins[action].help }}}</div>
										<# } #>
									</div>
								<# } #>
							</div>
						<# } #>
					<# } #>
				</div>
			</li>
		<# } #>
	<?php
	}
}