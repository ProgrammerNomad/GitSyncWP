jQuery(function($) {
    // Helper function to show token error
    function showTokenError(message) {
        const $tokenFeedback = $('#token-feedback');
        $tokenFeedback
            .html(`<p><span class="dashicons dashicons-warning"></span> ${message}</p>`)
            .removeClass('notice-success')
            .addClass('notice-error')
            .show();
    }

    // Helper function to show token success
    function showTokenSuccess(message) {
        const $tokenFeedback = $('#token-feedback');
        $tokenFeedback
            .html(`
                <div>
                    <p><span class="dashicons dashicons-yes"></span> ${message}</p>
                    <p class="description">Please validate your repository and save the settings.</p>
                </div>
            `)
            .removeClass('notice-error')
            .addClass('notice-success')
            .show();
    }

    // Document ready
    $(document).ready(function() {
        // Handle validate repository button click
        $('#validate-repo').on('click', function() {
            const token = $('#gitsyncwp_github_token').val();
            const repo = $('#gitsyncwp_github_repo').val();
            const $feedback = $('#validation-feedback');

            if (!token || !repo) {
                $feedback
                    .html('<p><span class="dashicons dashicons-warning"></span> Please enter both token and repository URL.</p>')
                    .removeClass('notice-success')
                    .addClass('notice-error')
                    .show();
                return;
            }

            $feedback.html('<p><span class="dashicons dashicons-update"></span> Validating...</p>')
                     .removeClass('notice-error notice-success')
                     .addClass('notice-info')
                     .show();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'gitsyncwp_fetch_repositories',
                    token: token,
                    repo: repo,
                    nonce: gitsyncwpAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $feedback
                            .html(`<p><span class="dashicons dashicons-yes"></span> ${response.data.message}</p>`)
                            .removeClass('notice-error notice-info')
                            .addClass('notice-success')
                            .show();
                    } else {
                        $feedback
                            .html(`<p><span class="dashicons dashicons-warning"></span> ${response.data.message}</p>`)
                            .removeClass('notice-success notice-info')
                            .addClass('notice-error')
                            .show();
                    }
                },
                error: function() {
                    $feedback
                        .html('<p><span class="dashicons dashicons-warning"></span> Failed to validate token and repository.</p>')
                        .removeClass('notice-success notice-info')
                        .addClass('notice-error')
                        .show();
                }
            });
        });

        // Accordion functionality
        $('.gitsyncwp-accordion-header').on('click', function() {
            const $accordionItem = $(this).closest('.gitsyncwp-accordion-item');
            const $content = $accordionItem.find('.gitsyncwp-accordion-content');

            // Toggle active class
            $accordionItem.toggleClass('active');
            $content.slideToggle();

            // Close other accordion items
            $accordionItem.siblings('.gitsyncwp-accordion-item').removeClass('active')
                .find('.gitsyncwp-accordion-content').slideUp();
        });
    });
});