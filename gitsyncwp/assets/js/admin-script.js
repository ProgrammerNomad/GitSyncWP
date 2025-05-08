// Wrap everything in jQuery function to ensure $ is available
jQuery(function($) {
    // Initialize Select2
    function initSelect2() {
        $('.select2-repo').select2({
            width: '100%',
            placeholder: 'Search repositories (org/repo-name)',
            allowClear: true,
            templateResult: formatRepo,
            templateSelection: formatRepoSelection,
            matcher: customMatcher
        });
    }

    // Custom matcher for better search
    function customMatcher(params, data) {
        // If there are no search terms, return all of the data
        if ($.trim(params.term) === '') {
            return data;
        }

        // Do not display the item if there is no 'text' property
        if (typeof data.text === 'undefined') {
            return null;
        }

        // Search in owner/repo format
        const searchStr = params.term.toLowerCase();
        const fullName = data.text.toLowerCase();

        // Match against full repo name or parts (owner or repo name)
        if (fullName.indexOf(searchStr) > -1) {
            return data;
        }

        // Split owner and repo
        const [owner, repo] = fullName.split('/');
        
        // Check if search matches either owner or repo
        if ((owner && owner.indexOf(searchStr) > -1) || 
            (repo && repo.indexOf(searchStr) > -1)) {
            return data;
        }

        // If it doesn't contain the search term, don't return anything
        return null;
    }

    // Format dropdown options
    function formatRepo(repo) {
        if (repo.loading) {
            return repo.text;
        }

        if (!repo.element) {
            return repo.text;
        }

        // Get repository details from data attributes
        const isPrivate = repo.element.dataset.private === '1';
        const description = repo.element.dataset.description;
        const updatedAt = repo.element.dataset.updated;
        
        // Split the repository name into owner and repo parts
        const [owner, repoName] = repo.text.split('/');

        const $container = $(
            `<div class="select2-repo-result">
                <div class="select2-repo-title">
                    <span class="repo-icon">${isPrivate ? '🔒' : '🌐'}</span>
                    <span class="repo-owner">${owner}</span>
                    <span class="repo-separator">/</span>
                    <span class="repo-name">${repoName}</span>
                </div>
                ${description ? 
                    `<div class="select2-repo-description">${description}</div>` : ''}
                ${updatedAt ? 
                    `<div class="select2-repo-meta">Last updated: ${updatedAt}</div>` : ''}
            </div>`
        );

        return $container;
    }

    // Format selected option
    function formatRepoSelection(repo) {
        if (!repo.element) {
            return repo.text;
        }

        const isPrivate = repo.element.dataset.private === '1';
        const [owner, repoName] = repo.text.split('/');

        return `<span class="repo-icon">${isPrivate ? '🔒' : '🌐'}</span> ${owner}/<strong>${repoName}</strong>`;
    }

    // Refresh repositories function
    function refreshRepositories() {
        const token = $('#gitsyncwp_github_token').val();
        const $repoSelect = $('#gitsyncwp_github_repo');
        const $loading = $('#repo-loading');
        const $tokenFeedback = $('#token-feedback');

        if (!token) {
            showTokenError('Please enter a GitHub token');
            return;
        }

        $loading.show();
        $repoSelect.prop('disabled', true);
        $tokenFeedback.html('').removeClass('notice-error notice-success').hide();

        // Clear Select2 cache
        if ($repoSelect.data('select2')) {
            $repoSelect.select2('destroy');
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            cache: false, // Prevent jQuery caching
            data: {
                action: 'gitsyncwp_fetch_repositories',
                token: token,
                nonce: gitsyncwpAjax.nonce,
                _nocache: new Date().getTime() // Prevent browser caching
            },
            success: function(response) {
                if (response.success && Array.isArray(response.data)) {
                    // Show success message with count
                    const repoCount = response.data.length;
                    showTokenSuccess(`Token validated successfully! Found ${repoCount} accessible ${repoCount === 1 ? 'repository' : 'repositories'}.`);
                    
                    $repoSelect.empty().append('<option value="">Select a repository</option>');
                    
                    response.data.forEach(function(repo) {
                        const icon = repo.private ? '🔒' : '🌐';
                        const description = repo.description || '';
                        const updated_at = repo.updated_at ? new Date(repo.updated_at).toISOString().split('T')[0] : '';
                        
                        $repoSelect.append(
                            `<option value="${repo.full_name}"
                                data-description="${description}"
                                data-updated="${updated_at}"
                                data-private="${repo.private ? '1' : '0'}">
                                ${repo.full_name}
                            </option>`
                        );
                    });
                    
                    // Reinitialize Select2
                    initSelect2();
                    $repoSelect.trigger('change');
                } else {
                    showTokenError(response.data?.message || 'Invalid token or no repositories found with write access');
                }
            },
            error: function(xhr) {
                let errorMessage = 'Failed to validate token';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data.message;
                }
                showTokenError(errorMessage);
                
                // Clear select options on error
                $repoSelect.empty().append('<option value="">Select a repository</option>');
                initSelect2();
            },
            complete: function() {
                $loading.hide();
                $repoSelect.prop('disabled', false);
            }
        });
    }

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
                    <p class="description">Please select your backup repository from the options below.</p>
                </div>
            `)
            .removeClass('notice-error')
            .addClass('notice-success')
            .show();

        // Scroll to repository section
        $('html, body').animate({
            scrollTop: $('#gitsyncwp_github_repo').offset().top - 100
        }, 500);
    }

    // Document ready
    $(document).ready(function() {
        // Initialize Select2
        initSelect2();

        $('.gitsyncwp-accordion-header').on('click', function() {
            const $content = $(this).next('.gitsyncwp-accordion-content');
            const $icon = $(this).find('.dashicons');
            
            // Toggle active class
            $content.toggleClass('active');
            
            // Toggle icon
            if ($content.hasClass('active')) {
                $icon.removeClass('dashicons-arrow-down-alt2')
                     .addClass('dashicons-arrow-up-alt2');
            } else {
                $icon.removeClass('dashicons-arrow-up-alt2')
                     .addClass('dashicons-arrow-down-alt2');
            }
        });

        // Handle GitHub token input changes
        $('#gitsyncwp_github_token').on('change', function() {
            refreshRepositories();
        });

        // Handle refresh button click
        $('#refresh-repos').on('click', function(e) {
            e.preventDefault();
            refreshRepositories();
        });
    });
});