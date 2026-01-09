(function($) {
    'use strict';
    
    let postsQueue = [];
    let currentIndex = 0;
    let isProcessing = false;
    let shouldStop = false;
    let currentAjaxRequest = null;
    
    /**
     * Initialize
     */
    $(document).ready(function() {
        initializeQueue();
        bindEvents();
    });
    
    /**
     * Initialize queue from table rows
     */
    function initializeQueue() {
        postsQueue = [];
        $('#wpafg-posts-list tr').each(function() {
            const postId = $(this).data('post-id');
            if (postId) {
                postsQueue.push({
                    id: postId,
                    element: $(this)
                });
            }
        });
    }
    
    /**
     * Bind events
     */
    function bindEvents() {
        // Filter without full page reload (avoids 500s caused by other plugins printing notices)
        $('#wpafg-filter-form').on('submit', function(e) {
            e.preventDefault();
            if (isProcessing) {
                stopProcessing();
            }
            fetchFilteredPosts();
        });

        // Some environments/plugins interfere with form submit handlers; also bind the button explicitly.
        $('#wpafg-apply-filter').on('click', function() {
            if (isProcessing) {
                stopProcessing();
            }
            fetchFilteredPosts();
        });

        $('#wpafg-generate-all').on('click', function() {
            if (isProcessing) {
                return;
            }
            
            if (postsQueue.length === 0) {
                alert('No posts to process.');
                return;
            }
            
            startProcessing();
        });
        
        $('#wpafg-stop-all').on('click', function() {
            stopProcessing();
        });
    }

    /**
     * Fetch filtered posts via AJAX and update the table.
     */
    function fetchFilteredPosts() {
        const postType = $('#wpafg-post-type').val() || 'post';
        const postStatus = $('#wpafg-post-status').val() || 'draft';

        const $tbody = $('#wpafg-posts-list');
        const $controls = $('.wpafg-controls');

        // Basic UI reset
        $tbody.empty();
        $controls.hide();
        $tbody.append('<tr class="wpafg-empty"><td colspan="5">Loading…</td></tr>');

        $.ajax({
            url: wpafg.ajax_url,
            type: 'POST',
            data: {
                action: 'wpafg_get_filtered_posts',
                nonce: wpafg.nonce,
                post_type: postType,
                post_status: postStatus
            },
            timeout: 60000,
            success: function(response) {
                $tbody.empty();
                if (!response || !response.success || !response.data || !Array.isArray(response.data.posts)) {
                    $tbody.append('<tr class="wpafg-empty"><td colspan="5">Filter failed: Invalid response from server.</td></tr>');
                    return;
                }

                const posts = response.data.posts;
                const dbg = response.data.debug || null;

                if (posts.length === 0) {
                    let msg = 'No posts/pages without featured images found for this filter.';
                    if (dbg) {
                        msg += ` (debug: total_matching=${dbg.total_matching}, with_thumb=${dbg.with_thumb}, expected_without_thumb=${dbg.without_thumb_expected}, where_type="${dbg.post_type_where}", where_status="${dbg.post_status_where}", raw_post_type="${dbg.raw_post_type}", raw_post_status="${dbg.raw_post_status}")`;
                    }
                    $tbody.append(`<tr class="wpafg-empty"><td colspan="5">${escapeHtml(msg)}</td></tr>`);
                    initializeQueue();
                    return;
                }

                posts.forEach(function(p) {
                    const typeLabel = (p.post_type || '').charAt(0).toUpperCase() + (p.post_type || '').slice(1);
                    const rowHtml = `
                        <tr data-post-id="${p.ID}" data-post-type="${p.post_type}" data-post-status="${p.post_status}">
                            <td>${p.ID}</td>
                            <td>${typeLabel}</td>
                            <td><strong>${escapeHtml(p.title || '')}</strong></td>
                            <td>${escapeHtml(p.date || '')}</td>
                            <td class="wpafg-status" data-status="pending"><span class="wpafg-status-text">Pending</span></td>
                        </tr>
                    `;
                    $tbody.append(rowHtml);
                });

                // Rebuild queue and show controls again
                initializeQueue();
                $controls.show();
            },
            error: function(xhr, statusText) {
                $tbody.empty();
                let msg = 'Filter failed.';
                if (statusText) msg += ' ' + statusText;
                $tbody.append(`<tr class="wpafg-empty"><td colspan="5">${escapeHtml(msg)}</td></tr>`);
            }
        });
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    
    /**
     * Start processing queue
     */
    function startProcessing() {
        isProcessing = true;
        shouldStop = false;
        currentIndex = 0;
        
        // Show progress bar and stop button
        $('.wpafg-progress-container').show();
        $('#wpafg-stop-all').removeClass('wpafg-visible').addClass('wpafg-visible').show().css('display', 'inline-block');
        $('#wpafg-generate-all').prop('disabled', true).text('Processing...');
        
        // Process first post
        processNextPost();
    }
    
    /**
     * Stop processing
     */
    function stopProcessing() {
        shouldStop = true;
        
        // Cancel current AJAX request if active
        if (currentAjaxRequest) {
            currentAjaxRequest.abort();
            currentAjaxRequest = null;
        }
        
        // Send stop request to server
        $.ajax({
            url: wpafg.ajax_url,
            type: 'POST',
            data: {
                action: 'wpafg_stop_generation',
                nonce: wpafg.nonce
            },
            success: function() {
                finishProcessing('Stopped by user');
            }
        });
    }
    
    /**
     * Process next post in queue
     */
    function processNextPost() {
        // Check if stopped
        if (shouldStop) {
            finishProcessing('Stopped by user');
            return;
        }
        
        if (currentIndex >= postsQueue.length) {
            // All done
            finishProcessing();
            return;
        }
        
        const post = postsQueue[currentIndex];
        const $row = post.element;
        const $statusCell = $row.find('.wpafg-status');
        const $statusText = $statusCell.find('.wpafg-status-text');
        
        // Update status to analyzing
        $statusCell.attr('data-status', 'analyzing');
        $statusText.text(wpafg.strings.analyzing);
        
        // Update progress
        updateProgress((currentIndex / postsQueue.length) * 100);
        
        // Make AJAX request
        currentAjaxRequest = $.ajax({
            url: wpafg.ajax_url,
            type: 'POST',
            data: {
                action: 'wpafg_generate_featured_image',
                nonce: wpafg.nonce,
                post_id: post.id
            },
            timeout: 120000, // 120 seconds timeout
            beforeSend: function() {
                $statusCell.attr('data-status', 'rendering');
                $statusText.text(wpafg.strings.rendering);
            },
            success: function(response) {
                currentAjaxRequest = null;
                
                if (shouldStop) {
                    $statusCell.attr('data-status', 'pending');
                    $statusText.text('Stopped').css('color', '#646970');
                    return;
                }
                
                if (response.success) {
                    if (response.data.status === 'pending') {
                        // Callback method - wait for callback
                        $statusCell.attr('data-status', 'rendering');
                        $statusText.text('Waiting for image...').css('color', '#2271b1');
                        
                        // Poll for completion
                        pollForCompletion(post.id, $statusCell, $statusText);
                    } else {
                        $statusCell.attr('data-status', 'done');
                        $statusText.text(wpafg.strings.done).css('color', '#46b450');
                        
                        // Move to next post
                        currentIndex++;
                        setTimeout(function() {
                            processNextPost();
                        }, 500);
                    }
                } else {
                    $statusCell.attr('data-status', 'error');
                    $statusText.text(wpafg.strings.error + ': ' + (response.data.message || 'Unknown error')).css('color', '#dc3232');
                    
                    // Continue with next post even on error
                    currentIndex++;
                    setTimeout(function() {
                        processNextPost();
                    }, 1000);
                }
            },
            error: function(xhr, status, error) {
                currentAjaxRequest = null;
                
                if (status === 'abort') {
                    $statusCell.attr('data-status', 'pending');
                    $statusText.text('Stopped').css('color', '#646970');
                    return;
                }
                
                let errorMessage = wpafg.strings.error + ': ';
                
                if (status === 'timeout') {
                    errorMessage += 'Request timeout';
                } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage += xhr.responseJSON.data.message;
                } else {
                    errorMessage += error || 'Unknown error';
                }
                
                $statusCell.attr('data-status', 'error');
                $statusText.text(errorMessage).css('color', '#dc3232');
                
                // Continue with next post even on error
                currentIndex++;
                setTimeout(function() {
                    processNextPost();
                }, 1000);
            }
        });
    }
    
    /**
     * Poll for completion (for callback method)
     */
    function pollForCompletion(postId, $statusCell, $statusText) {
        if (shouldStop) {
            return;
        }
        
        // Check status via AJAX
        setTimeout(function() {
            $.ajax({
                url: wpafg.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpafg_check_status',
                    nonce: wpafg.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success && response.data.status === 'completed') {
                        $statusCell.attr('data-status', 'done');
                        $statusText.text(wpafg.strings.done).css('color', '#46b450');
                        
                        currentIndex++;
                        setTimeout(function() {
                            processNextPost();
                        }, 500);
                    } else if (response.success && response.data.status === 'error') {
                        $statusCell.attr('data-status', 'error');
                        $statusText.text(wpafg.strings.error + ': ' + (response.data.error || 'Unknown error')).css('color', '#dc3232');
                        
                        currentIndex++;
                        setTimeout(function() {
                            processNextPost();
                        }, 1000);
                    } else {
                        // Still pending, poll again
                        pollForCompletion(postId, $statusCell, $statusText);
                    }
                }
            });
        }, 2000); // Poll every 2 seconds
    }
    
    /**
     * Update progress bar
     */
    function updateProgress(percentage) {
        percentage = Math.min(100, Math.max(0, percentage));
        $('.wpafg-progress-fill').css('width', percentage + '%');
        $('.wpafg-progress-text').text(Math.round(percentage) + '%');
    }
    
    /**
     * Finish processing
     */
    function finishProcessing(message) {
        isProcessing = false;
        shouldStop = false;
        currentAjaxRequest = null;
        updateProgress(100);
        $('#wpafg-generate-all').prop('disabled', false).text('Generate All');
        $('#wpafg-stop-all').hide();
        
        // Show completion message
        setTimeout(function() {
            alert(message || 'All posts processed!');
        }, 500);
    }
    
})(jQuery);

