<?php
/**
 * Pro customizer section.
 *
 * @since  1.0.0
 * @access public
 */
class E_Storefront_Customize_Section_Pro extends WP_Customize_Section {

	/**
	 * The type of customize section being rendered.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	public $type = 'e-storefront';

	/**
	 * Custom button texts and URLs.
	 */
	public $pro_text = '';
	public $bundle_text = '';
	public $pro_url = '';
	public $bundle_url = '';

	// Added new fields
	public $demo_text = '';
	public $demo_url = '';
	public $support_text = '';
	public $support_url = '';
	public $lite_doc_text = '';
	public $lite_doc_url = '';
	public $review_text = '';
	public $review_url = '';

	/**
	 * Add custom parameters to pass to the JS via JSON.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function json() {
		$json = parent::json();

		$json['pro_text']      = $this->pro_text;
		$json['pro_url']       = esc_url( $this->pro_url );
		$json['bundle_text']   = $this->bundle_text;
		$json['bundle_url']    = esc_url( $this->bundle_url );

		// New JSON fields
		$json['demo_text']     = $this->demo_text;
		$json['demo_url']      = esc_url( $this->demo_url );
		$json['support_text']  = $this->support_text;
		$json['support_url']   = esc_url( $this->support_url );
		$json['lite_doc_text'] = $this->lite_doc_text;
		$json['lite_doc_url']  = esc_url( $this->lite_doc_url );
		$json['review_text']   = $this->review_text;
		$json['review_url']    = esc_url( $this->review_url );

		return $json;
	}

	/**
	 * Outputs the Underscore.js template.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	protected function render_template() { ?>
		<li id="accordion-section-{{ data.id }}" class="accordion-section control-section control-section-{{ data.type }} cannot-expand">
			<h3 class="accordion-section-title">
				{{ data.title }}
				<span class="customize-action">{{ data.description }}</span>
				<div class="custom-action-buttonss">
					<# if ( data.demo_text && data.demo_url ) { #>
						<a href="{{ data.demo_url }}" class="button button-primary custom-demo" target="_blank">{{ data.demo_text }}</a>
					<# } #>

					<# if ( data.pro_text && data.pro_url ) { #>
						<a href="{{ data.pro_url }}" class="button button-primary custom-pro" target="_blank">{{ data.pro_text }}</a>
					<# } #>

					<# if ( data.lite_doc_text && data.lite_doc_url ) { #>
						<a href="{{ data.lite_doc_url }}" class="button button-primary custom-doc" target="_blank">{{ data.lite_doc_text }}</a>
					<# } #>

					<# if ( data.support_text && data.support_url ) { #>
						<a href="{{ data.support_url }}" class="button button-primary custom-support" target="_blank">{{ data.support_text }}</a>
					<# } #>

					<# if ( data.bundle_text && data.bundle_url ) { #>
						<a href="{{ data.bundle_url }}" class="button button-primary custom-bundle" target="_blank">{{ data.bundle_text }}</a>
					<# } #>

					<# if ( data.review_text && data.review_url ) { #>
						<a href="{{ data.review_url }}" class="button button-primary custom-review" target="_blank">{{ data.review_text }}</a>
					<# } #>
				</div>
			</h3>
		</li>
	<?php }
}
