jQuery(function($){

	// Disable the portal on the 'source' dropdown
	$( '#portal' ).on( 'change', function () {

		var blog_id = $( '#portal' ).val();

		// Enable all options
		$( '#source option' ).prop( 'disabled', false );

		// Disable the selected portal
		$( '#source option[value=' + blog_id + ']' ).prop( 'disabled', true );

	} );

	// Detect changes to the drop down
	$( '.new_aggregator' ).on( 'submit', function (e) {

		// Stop submission
		e.preventDefault();

		// Grab our blog IDs
		var portal = $( '#portal' ).val();
		var source = $( '#source' ).val();

		// Grab the URL
		var ajax_data = {
			'action': 'get_new_job_url',
			'security': ajax_object.nonce,
			'portal': portal,
			'source': source
		};

		$.post( ajax_object.ajax_url, ajax_data, function ( response ) {
			window.location.href = response;
		} );

	} );

});
