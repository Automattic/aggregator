jQuery( document ).ready( function( $ ) {
	
	var $meta_box = $( '#fporb-promotion' );
	
	$( '.edit-fporb-promotion, .cancel-post-fporb-promotion, .save-post-fporb-promotion', $meta_box )
		.click( function ( e ) {
			e.preventDefault();
			$( '.fporb-promotion-select', $meta_box ).slideToggle();
			if ( $( this ).hasClass( 'cancel-post-fporb-promotion' ) ) {
				// Restore the previous state
				if ( $( 'input.fporb-promotion-sequestered', $meta_box ).val() )
					$( '#fporb-promotion-radio-promoted' ).prop( 'checked', true );
				else
					$( '#fporb-promotion-radio-not-promoted' ).prop( 'checked', true );
			}
		} );
	
	$( '.save-post-fporb-promotion', $meta_box )
		.click( function( e ) {
			e.preventDefault();
			var $selector = $( '.fporb-promotion-select', $meta_box );
			if ( $( 'input:radio:checked', $selector ).val() ) {
				$( '.post-fporb-promotion-display', $meta_box ).text( fporb.this_site_plus );
				$( 'input.fporb-promotion-sequestered', $meta_box ).val( '1' );
			} else {
				$( '.post-fporb-promotion-display', $meta_box ).text( fporb.this_site_only );
				$( 'input.fporb-promotion-sequestered', $meta_box ).val( '' );
			}
		} );
	
} );