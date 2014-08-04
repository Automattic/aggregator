jQuery( document ).ready( function( $ ) {
	
	var $meta_box = $( '#aggregator-promotion' );
	
	$( '.edit-aggregator-promotion, .cancel-post-aggregator-promotion, .save-post-aggregator-promotion', $meta_box )
		.click( function ( e ) {
			e.preventDefault();
			$( '.aggregator-promotion-select', $meta_box ).slideToggle();
			if ( $( this ).hasClass( 'cancel-post-aggregator-promotion' ) ) {
				// Restore the previous state
				if ( $( 'input.aggregator-promotion-sequestered', $meta_box ).val() )
					$( '#aggregator-promotion-radio-promoted' ).prop( 'checked', true );
				else
					$( '#aggregator-promotion-radio-not-promoted' ).prop( 'checked', true );
			}
		} );
	
	$( '.save-post-aggregator-promotion', $meta_box )
		.click( function( e ) {
			e.preventDefault();
			var $selector = $( '.aggregator-promotion-select', $meta_box );
			if ( $( 'input:radio:checked', $selector ).val() ) {
				$( '.post-aggregator-promotion-display', $meta_box ).text( aggregator.this_site_plus );
				$( 'input.aggregator-promotion-sequestered', $meta_box ).val( '1' );
			} else {
				$( '.post-aggregator-promotion-display', $meta_box ).text( aggregator.this_site_only );
				$( 'input.aggregator-promotion-sequestered', $meta_box ).val( '' );
			}
		} );
	
} );