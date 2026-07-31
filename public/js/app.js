$(document).ready(function() {
// --- CSRF Token Handling --- //
    // 1. OUTGOING: Automatically add the CSRF header to every request
    $(document).ajaxSend(function(e, xhr, options) {
        // Read the *current* value from the meta tag
        var token = $('meta[name="csrf-token"]').attr('content');

        // Don't send token for cross-domain requests (Security best practice)
        if (!/^http:.*/.test(options.url) && !/^https:.*/.test(options.url)) {
            xhr.setRequestHeader('X-CSRF-Token', token);
        }
    });

    // 2. INCOMING: Check every response for a rotation header
    $(document).ajaxComplete(function(event, xhr, settings) {
        // Try to get the header from the response
        var newToken = xhr.getResponseHeader('X-CSRF-Token');

        if (newToken) {
            console.log("CSRF Token rotated by server:", newToken);

            // A. Update the Master Source (Meta Tag)
            $('meta[name="csrf-token"]').attr('content', newToken);

            // B. Update any hidden inputs in forms on the page
            // This ensures if the user submits a standard HTML form next, it works.
            $('input[name="csrf_token"]').val(newToken);
        }
    });

});