jQuery(function($){

    // Disable the portal on the 'source' dropdown
    $('#portal').on( 'change', function () {

        var blog_id = $('#portal').val();

        // Enable all options
        $('#source option').prop( 'disabled', false );

        // Disable the selected portal
        $('#source option[value=' + blog_id + ']').prop( 'disabled', true );

    } );

});