;(function( $, window, document ) {
    $(document).ready(function() {
        // Hide and show password click events
        $(document).on('click', '.toggle-log', function(event) {
            event.preventDefault(); // Prevent the default action of the link
            $(document.body).toggleClass('with-log');
            return false;
        });
    });
}) (jQuery, window, document);