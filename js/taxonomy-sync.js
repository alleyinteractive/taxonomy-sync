( function( $ ) {

// Whenever a selection is made in the all categories tab, update all the individual taxonomy tabs to keep selections in sync
var taxonomy_sync_field_display = function( target ) {
	// Check the current mode and determine what fields should be displayed
	if( $('#taxonomy_sync_settings-mode').val() == 'slave' ) {
		$('#taxonomy-sync-remote-url-row').hide();
		$('#taxonomy-sync-full-sync-wrapper').hide();
		$('#taxonomy-sync-full-sync-not-available').show();
	} else {
		$('#taxonomy-sync-remote-url-row').show();
		$('#taxonomy-sync-full-sync-not-available').hide();
		$('#taxonomy-sync-full-sync-wrapper').show();
	}
};

$( document ).ready( function() {
	
	// Handle the full sync action
	$( '#taxonomy_sync_full_sync_button' ).live( 'click', function( e ) {
		e.preventDefault();
		
		// Send the post request
		// Get all current related links for the post
		var start_time = new Date().getTime();
		$.post( ajaxurl, { action: 'taxonomy_sync_full_sync', taxonomy_sync_full_sync_nonce: taxonomy_sync_data.nonce }, function ( response ) {
			// Just display the response. It will explain what happened.
			$('#taxonomy-sync-full-sync-message').html( response );
		});	
	} );
	
	// Handle only displaying fields that are relevant to the current mode
	$( '#taxonomy_sync_settings-mode' ).live( 'change', function( e ) {
		taxonomy_sync_field_display();
	} );
	
	taxonomy_sync_field_display();
} );

} )( jQuery );