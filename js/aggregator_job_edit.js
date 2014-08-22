jQuery(document).ready(function($){

    // Loop through the taxonomies, checking if they're checked and showing/hiding
    // the relevant meta boxes accordingly
    for ( var key in aggregator_taxonomies ) {

        // This taxonomy
        var tax = aggregator_taxonomies[key];

        // A flag to indicate whether or not the meta box should be shown for this taxonomy
        var display = false;

        // Is the checkbox for this taxonomy selected?
        if ( $('input.checkbox_check').is(':checked') ) {
            display = true;
        }

        // Find out what the ID is for the meta box
        var meta_box_selector = '';
        if ( $( '#' + taxonomy + 'div' ).length ) {

            // This is a standard category-style taxonomy
            meta_box_selector = '#' + taxonomy + 'div';

        } else if ( $('#tagsdiv-' + taxonomy).length ) {

            // This is a tag-style taxonomy
            meta_box_selector = '#tagsdiv-' + taxonomy;

        }

        if ( display ) {
            $( meta_box_selector).show();
        } else {
            $( meta_box_selector).hide();
        }

    }

});