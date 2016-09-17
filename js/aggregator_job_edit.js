jQuery( document ).ready(function($){

	// On load, trigger toggling
	window.setInterval( toggle_taxonomy_meta_boxes, 500 );

	function toggle_taxonomy_meta_boxes() {

		// Loop through the taxonomies, checking if they're checked and showing/hiding
		// the relevant meta boxes accordingly
		for ( var key in aggregator_taxonomies ) {

			// This taxonomy
			var tax = aggregator_taxonomies[key];

			// A flag to indicate whether or not the meta box should be shown for this taxonomy
			var display = false;

			// Is the checkbox for this taxonomy selected?
			if ( $( '#taxo_' + tax ).is( ':checked' ) ) {
				display = true;
			}

			// Find out what the ID is for the meta box
			var meta_box_selector = '';
			if ( $( '#' + tax + 'div' ).length ) {

				// This is a standard category-style taxonomy
				meta_box_selector = '#' + tax + 'div';

			} else if ( $( '#tagsdiv-' + tax ).length ) {

				// This is a tag-style taxonomy
				meta_box_selector = '#tagsdiv-' + tax;

			}

			// Now show/hide the meta box
			if ( display ) {
				$( meta_box_selector ).show();
			} else {
				$( meta_box_selector ).hide();
			}

		}

	}

});
