( function( api ) {

	// Extends our custom "e-storefront" section.
	api.sectionConstructor['e-storefront'] = api.Section.extend( {

		// No events for this type of section.
		attachEvents: function () {},

		// Always make the section active.
		isContextuallyActive: function () {
			return true;
		}
	} );

} )( wp.customize );

// Fix for Kirki querySelector error with invalid selectors containing brackets
(function() {
	if (typeof document !== 'undefined') {
		var originalQuerySelector = document.querySelector;
		var originalQuerySelectorAll = document.querySelectorAll;
		
		document.querySelector = function(selector) {
			try {
				return originalQuerySelector.call(this, selector);
			} catch (e) {
				if (e instanceof DOMException && e.name === 'SyntaxError') {
					return null;
				}
				throw e;
			}
		};
		
		document.querySelectorAll = function(selector) {
			try {
				return originalQuerySelectorAll.call(this, selector);
			} catch (e) {
				if (e instanceof DOMException && e.name === 'SyntaxError') {
					return [];
				}
				throw e;
			}
		};
	}
})();