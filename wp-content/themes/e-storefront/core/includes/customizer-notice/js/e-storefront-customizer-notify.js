/**
 * Customizer notification system
 */


(function (api) {

	api.sectionConstructor['e-storefront-customizer-notify-section'] = api.Section.extend(
		{

			// No events for this type of section.
			attachEvents: function () {
			},

			// Always make the section active.
			isContextuallyActive: function () {
				return true;
			}
		}
	);

})( wp.customize );

					jQuery( document ).ready(
						function () {

							jQuery( '.e-storefront-customizer-notify-dismiss-recommended-action' ).click(
								function () {

									var id = jQuery( this ).attr( 'id' ),
									action = jQuery( this ).attr( 'data-action' );
									jQuery.ajax(
										{
											type: 'GET',
											data: {action: 'e_storefront_customizer_notify_dismiss_action', id: id, todo: action},
											dataType: 'html',
											url: estorefrontCustomizercompanionObject.ajaxurl,
											beforeSend: function () {
												jQuery( '#' + id ).parent().append( '<div id="temp_load" style="text-align:center"><img src="' + estorefrontCustomizercompanionObject.base_path + '/images/spinner-2x.gif" /></div>' );
											},
											success: function (data) {
												var container          = jQuery( '#' + data ).parent().parent();
												var index              = container.next().data( 'index' );
												var recommended_sction = jQuery( '#accordion-section-ti_customizer_notify_recomended_actions' );
												var actions_count      = recommended_sction.find( '.e-storefront-customizer-plugin-notify-actions-count' );
												var section_title      = recommended_sction.find( '.section-title' );
												jQuery( '.e-storefront-customizer-plugin-notify-actions-count .current-index' ).text( index );
												container.slideToggle().remove();
												if (jQuery( '.e-storefront-theme-recomended-actions_container > .epsilon-recommended-actions' ).length === 0) {

													actions_count.remove();

													if (jQuery( '.e-storefront-theme-recomended-actions_container > .epsilon-recommended-plugins' ).length === 0) {
														jQuery( '.control-section-ti-customizer-notify-recomended-actions' ).remove();
													} else {
														section_title.text( section_title.data( 'plugin_text' ) );
													}

												}
											},
											error: function (jqXHR, textStatus, errorThrown) {
												console.log( jqXHR + ' :: ' + textStatus + ' :: ' + errorThrown );
											}
										}
									);
								}
							);

										jQuery( '.e-storefront-customizer-notify-dismiss-button-recommended-plugin' ).click(
											function () {
												var id = jQuery( this ).attr( 'id' ),
												action = jQuery( this ).attr( 'data-action' );
												jQuery.ajax(
													{
														type: 'GET',
														data: {action: 'ti_customizer_notify_dismiss_recommended_plugins', id: id, todo: action},
														dataType: 'html',
														url: estorefrontCustomizercompanionObject.ajaxurl,
														beforeSend: function () {
															jQuery( '#' + id ).parent().append( '<div id="temp_load" style="text-align:center"><img src="' + estorefrontCustomizercompanionObject.base_path + '/images/spinner-2x.gif" /></div>' );
														},
														success: function (data) {
															var container = jQuery( '#' + data ).parent().parent();
															var index     = container.next().data( 'index' );
															jQuery( '.e-storefront-customizer-plugin-notify-actions-count .current-index' ).text( index );
															container.slideToggle().remove();

															if (jQuery( '.e-storefront-theme-recomended-actions_container > .epsilon-recommended-plugins' ).length === 0) {
																jQuery( '.control-section-ti-customizer-notify-recomended-section' ).remove();
															}
														},
														error: function (jqXHR, textStatus, errorThrown) {
															console.log( jqXHR + ' :: ' + textStatus + ' :: ' + errorThrown );
														}
													}
												);
											}
										);


										// Function to handle the activation process
										function activatePlugin(url) {
										    // Request plugin activation.
										    jQuery.ajax({
										        beforeSend: function() {
										            jQuery('.activate-now').replaceWith('<a class="button updating-message">' + estorefrontCustomizercompanionObject.activating_string + '...</a>');
										        },
										        async: true,
										        type: 'GET',
										        url: url,
										        success: function() {
										            // Reload the page.
										            location.reload();
										        }
										    });
										}

										// Define the MutationObserver callback function
										var mutationCallback = function(mutationsList, observer) {
										    for (var mutation of mutationsList) {
										        if (mutation.type === 'childList') {
										            // Check if the inserted node is of class '.activate-now'
										            if (mutation.addedNodes && mutation.addedNodes.length) {
										                var addedNode = mutation.addedNodes[0];
										                // Ensure the added node has classList property before accessing it
										                if (addedNode.classList && addedNode.classList.contains('activate-now')) {
										                    var url = addedNode.getAttribute('href');
										                    if (url) {
										                        // Call function to activate the plugin
										                        activatePlugin(url);
										                    }
										                }
										            }
										        }
										    }
										};

										// Create a new MutationObserver
										var observer = new MutationObserver(mutationCallback);

										// Start observing the document for mutations
										observer.observe(document, { childList: true, subtree: true });
					
						}
					);
					
					
					
/**
 * Remove activate button and replace with activation in progress button.
 *
 * @package e_storefront
 */


jQuery( document ).ready(
	function ($) {
		if(document.getElementsByClassName("action_button").length > 0) {

			const targetNode = document.getElementsByClassName("action_button")[0];

		// Options for the observer (which mutations to observe)
		const config = { attributes: true, childList: true, subtree: true };

		// Callback function to execute when mutations are observed
		const callback = (mutationList, observer) => {
		for (const mutation of mutationList) {
			if (mutation.type === "childList") {
			$( '.activate-now' ).on(
				'click', function (e) {
					var activateButton = $( this );
					e.preventDefault();
					if ($( activateButton ).length) {
						var url = $( activateButton ).attr( 'href' );
	
						if (typeof url !== 'undefined') {
							// Request plugin activation.
							$.ajax(
								{
									beforeSend: function () {
										$( activateButton ).replaceWith( '<a class="button updating-message">'+"activating"+'...</a>' );
									},
									async: true,
									type: 'GET',
									url: url,
									success: function () {
										// Reload the page.
										location.reload();
									}
								}
							);
						}
					}
				}
			);
			} else if (mutation.type === "attributes") {
			console.log(`The ${mutation.attributeName} attribute was modified.`);
			}
		}
		};

		// Create an observer instance linked to the callback function
		const observer = new MutationObserver(callback);

		// Start observing the target node for configured mutations
		observer.observe(targetNode, config);

		}
		$( 'body' ).on(
			'click', ' .e-storefront-install-plugin ', function () {
				var slug = $( this ).attr( 'data-slug' );

				wp.updates.installPlugin(
					{
						slug: slug
					}
				);
				return false;
			}
		);

		$( '.activate-now' ).on(
			'click', function (e) {
				var activateButton = $( this );
				e.preventDefault();
				if ($( activateButton ).length) {
					var url = $( activateButton ).attr( 'href' );

					if (typeof url !== 'undefined') {
						// Request plugin activation.
						$.ajax(
							{
								beforeSend: function () {
									$( activateButton ).replaceWith( '<a class="button updating-message">'+"activating"+'...</a>' );
								},
								async: true,
								type: 'GET',
								url: url,
								success: function () {
									// Reload the page.
									location.reload();
								}
							}
						);
					}
				}
			}
		);
	}
);