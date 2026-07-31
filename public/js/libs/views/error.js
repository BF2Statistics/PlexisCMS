;(function( $, window, document, undefined ) {
    // Ensure the DOM and jQuery are ready
    $(document).ready(function () {
        // Event handler for 'go back'
        $('#go-back').on('click', function (e) {
            e.preventDefault(); // Prevent the default link behavior
            history.back();     // Navigate back in history
        });

        // Event handler for 'reload page'
        $('#reload-page').on('click', function (e) {
            e.preventDefault(); // Prevent the default link behavior
            window.location.reload(); // Reload the current page
        });

        // Event handler for 'toggle log'
        $('#toggle-log').on('click', function (e) {
            e.preventDefault(); // Prevent the default link behavior
            $(document.body).toggleClass('with-log'); // Add/remove "with-log" class to the body
        });
    });
}) (jQuery, window, document);