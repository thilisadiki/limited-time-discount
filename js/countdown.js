jQuery(document).ready(function($) {
    var expiryTime = ltd_params.expiry_time * 1000; // Convert to milliseconds
    var timerInterval = setInterval(function() {
        var now = new Date().getTime();
        var distance = expiryTime - now;

        if (distance < 0) {
            clearInterval(timerInterval);
            $('#ltd-countdown-timer').html('<p>The special discount has expired.</p>');

            // Remove the discount when timer expires
            $.ajax({
                url: ltd_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'ltd_remove_discount',
                },
                success: function() {
                    // Reload the cart to update prices
                    location.reload();
                }
            });
        } else {
            var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((distance % (1000 * 60)) / 1000);
            $('#ltd-timer').text(minutes + 'm ' + seconds + 's');
        }
    }, 1000);
});
