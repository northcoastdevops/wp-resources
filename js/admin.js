jQuery(document).ready(function($) {
    let previousWarnings = { memory: false, disk: false, cpu: false };
    let retryCount = 0;
    const MAX_RETRIES = 3;
    let soundEnabled = localStorage.getItem('wp_resources_sound') !== 'disabled';

    function sprintf(format, ...args) {
        return format.replace(/%(\d+\$)?s/g, function(match, num) {
            if (num) {
                return args[parseInt(num) - 1];
            }
            return args.shift();
        });
    }

    // Handle tab navigation without page reload
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        const tab = $(this).attr('href').split('tab=')[1];
        
        // Update URL without reload
        window.history.pushState(null, '', $(this).attr('href'));
        
        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Show/hide content
        $('.tab-content').hide();
        $(`#wp-resources-${tab}`).show();

        // Load history data if history tab
        if (tab === 'history') {
            loadAlertHistory();
        }
    });

    // Handle settings form submission via AJAX
    $('#wp-resources-settings form').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'save_wp_resources_settings',
                nonce: $('#_wpnonce').val(),
                memory_warning: $('input[name="memory_warning"]').val(),
                memory_critical: $('input[name="memory_critical"]').val(),
                disk_warning: $('input[name="disk_warning"]').val(),
                disk_critical: $('input[name="disk_critical"]').val(),
                cpu_warning: $('input[name="cpu_warning"]').val(),
                cpu_critical: $('input[name="cpu_critical"]').val()
            },
            success: function(response) {
                if (response.success) {
                    // Update thresholds in JS
                    wpResourcesL10n.warningLevels = response.data.warning_levels;
                    
                    // Show success message
                    const notice = $('<div class="notice notice-success is-dismissible"><p>' + 
                        response.data.message + '</p></div>');
                    $('.wrap > h1').after(notice);
                    
                    // Update dashboard if visible
                    if ($('#wp-resources-dashboard').is(':visible')) {
                        updateResources();
                    }
                } else {
                    // Show error message
                    const notice = $('<div class="notice notice-error is-dismissible"><p>' + 
                        response.data.message + '</p></div>');
                    $('.wrap > h1').after(notice);
                }
            }
        });
    });

    // Add sound toggle
    $('#refresh-resources').after(`
        <label class="sound-toggle">
            <input type="checkbox" id="toggle-sound" ${soundEnabled ? 'checked' : ''}>
            ${wpResourcesL10n.enableSound || 'Enable Warning Sound'}
        </label>
    `);

    $('#toggle-sound').on('change', function() {
        soundEnabled = this.checked;
        localStorage.setItem('wp_resources_sound', soundEnabled ? 'enabled' : 'disabled');
    });

    // Handle threshold input changes
    function updateIndicator(input) {
        const value = parseFloat(input.value);
        const wrapper = input.closest('.threshold-input-wrapper');
        const indicator = wrapper.querySelector('.threshold-indicator');
        const valueDisplay = wrapper.querySelector('.threshold-value');
        
        if (input.name === 'cpu_threshold') {
            // For CPU, we use a scale of 0-10 for the visual
            const percentage = Math.min((value / 10) * 100, 100);
            indicator.style.setProperty('--value', percentage + '%');
            valueDisplay.textContent = value.toFixed(1);
        } else {
            indicator.style.setProperty('--value', value + '%');
            valueDisplay.textContent = value + '%';
        }
    }

    $('.threshold-input').on('input', function() {
        updateIndicator(this);
    });

    // Initialize indicators
    $('.threshold-input').each(function() {
        updateIndicator(this);
    });

    function updateResources() {
        const $refreshButton = $('#refresh-resources');
        const $dashicon = $refreshButton.find('.dashicons');
        
        $dashicon.addClass('spin');
        $('.resource-panel').addClass('loading');
        
        $.ajax({
            url: ajaxurl,
            data: {
                action: 'get_system_resources'
            },
            success: function(response) {
                if (response.success) {
                    updateUI(response.data);
                    retryCount = 0;
                } else {
                    handleError(wpResourcesL10n.errorLoading);
                }
            },
            error: function() {
                handleError(wpResourcesL10n.errorLoading);
            },
            complete: function() {
                $dashicon.removeClass('spin');
                $('.resource-panel').removeClass('loading');
            }
        });
    }

    function updateUI(data) {
        // Update memory panel
        if (data.support.memory) {
            $('#memory-usage').text(data.memory_usage);
            $('#memory-limit').text(data.memory_limit);
            $('#memory-percentage').text(data.memory_percentage + '%');
            updateProgressBar('#memory-progress', data.memory_percentage);
            checkWarning('memory', data.warnings.memory);
        }
        
        // Update disk panel
        if (data.support.disk) {
            const usedSpace = data.disk_total - data.disk_free;
            $('#disk-usage').text(data.disk_usage || (data.disk_total - data.disk_free));
            $('#disk-free').text(data.disk_free);
            $('#disk-total').text(data.disk_total);
            updateProgressBar('#disk-progress', data.disk_usage_percentage);
            checkWarning('disk', data.warnings.disk);
        }
        
        // Update CPU panel
        if (data.support.cpu) {
            $('#cpu-usage').text(data.cpu_usage + ' avg');
            // Calculate CPU percentage relative to critical threshold
            const cpuCritical = wpResourcesL10n.warningLevels.cpu.critical;
            const cpuPercentage = (data.cpu_usage / cpuCritical) * 100;
            updateProgressBar('#cpu-progress', cpuPercentage);
            checkWarning('cpu', data.warnings.cpu);
        }

        // Update support status
        Object.entries(data.support).forEach(([key, supported]) => {
            $(`#${key}-panel`).toggleClass('unsupported', !supported);
        });
    }

    function updateProgressBar(selector, percentage) {
        if (percentage === null || isNaN(percentage)) {
            $(selector).css('width', '0%')
                .removeClass('warning critical normal')
                .addClass('error');
            return;
        }
        
        const $bar = $(selector);
        const resourceType = selector.replace('#', '').replace('-progress', '');
        const warningLevels = wpResourcesL10n.warningLevels[resourceType];
        
        // Ensure percentage doesn't exceed 100%
        const displayPercentage = Math.min(percentage, 100);
        
        $bar.css('width', displayPercentage + '%')
            .removeClass('error warning critical normal');

        // Add appropriate class based on percentage relative to thresholds
        if (percentage >= 100) {
            $bar.addClass('critical');
        } else if (percentage >= (warningLevels.warning / warningLevels.critical * 100)) {
            $bar.addClass('warning');
        } else {
            $bar.addClass('normal');
        }

        // Update text color for the percentage display
        const $panel = $bar.closest('.resource-panel');
        $panel.find('div').not('.progress-bar, .progress').removeClass('warning critical');
        
        if (percentage >= 100) {
            $panel.find('div').not('.progress-bar, .progress').addClass('critical');
        } else if (percentage >= (warningLevels.warning / warningLevels.critical * 100)) {
            $panel.find('div').not('.progress-bar, .progress').addClass('warning');
        }
    }

    function checkWarning(type, isWarning) {
        if (isWarning && !previousWarnings[type] && soundEnabled) {
            playWarningSound();
        }
        previousWarnings[type] = isWarning;
    }

    function handleError(message) {
        retryCount++;
        if (retryCount < MAX_RETRIES) {
            console.log(sprintf(wpResourcesL10n.retrying, retryCount, MAX_RETRIES));
            setTimeout(updateResources, 5000);
        } else {
            console.error(sprintf(wpResourcesL10n.failedToLoad, MAX_RETRIES));
        }
    }

    function playWarningSound() {
        const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBTGH0fPTgjMGHm7A7+OZRQ0PVqzn77BdGAg+ltryxnMpBSl+zPLaizsIGGS57OihUBELTKXh8bllHgU2jdXzzn0vBSF1xe/glEILElyx6OyrWBUIQ5zd8sFuJAUuhM/z1YU2Bhxqvu7mnEgODlOq5O+zYBoGPJPY88p2KwUme8rx3I4+CRZiturqpVITC0mi4PK8aB8GM4nU8tGAMQYfcsLu45ZFDBFYr+ftrVoXCECY3PLEcSYELIHO8diJOQcZaLvt559NEAxPqOPwtmMcBjiP1/PMeS0GI3fH8N2RQAoUXrTp66hVFApGnt/yvmwhBTCG0fPTgjQGHm/A7eSaRQ0PVqzl77BeGQc9ltvyxnUoBSh+zPDaizsIGGS56+mjTxELTKXh8bllHgU1jdT0z3wvBSJ0xe/glEILElyx6OyrWRUIRJve8sFuJAUug8/z1YU2Bhxqvu3mnEgODlOq5O+zYRsGPJPY88p3KgUme8rx3I4+CRVht+rqpVITC0mi4PK8aB8GM4nU8tGAMQYfccPu45ZFDBFYr+ftrVwWCECY3PLEcSYGK4DN8tiIOQcZZ7zs56BODwxPpuPxtmQcBjiP1/PMeS0GI3fH8N+RQAoUXrTp66hWEwlGnt/yv2wiBDCG0fPTgzQGHm3A7eSaSg0PVqzl77BeGQc9ltrzxnUoBSh9y/HajDsIF2W56+mjUREKTKPi8blnHgU1jdTy0HwvBSF0xPDglEQKElux6eyrWRUJQ5vd88FwJAQug8/z1YY2BRxqvu3mnEgODlKq5e+zYRsGOpPX88p3KgUmfMrx3I4+CRVht+rqpVITC0mi4PK8aB8GM4nU8tGAMQYfccLu45ZGCxFYr+ftrVwXB0CY3PLEcSYGK4DN8tiIOQcZZ7zs56BODwxPpuPxtmQcBjiP1/PMeS0GI3bH8d+RQAoUXrTp66hWEwlGnt/yv2wiBDCG0fPTgzQGHm3A7eSaSg0PVqzl77BeGQc9ltrzxnUoBSh9y/HajDsIF2W56+mjUREKTKPi8blnHgU1jdTy0HwvBSF0xPDglEQKElux6eyrWRUJQ5vd88FwJAQug8/z1YY2BRxqvu3mnEgODlKq5e+zYRsGOpPX88p3Kg==');
        audio.play().catch(() => {
            console.log('Warning sound blocked by browser');
        });
    }

    // Start automatic updates
    updateResources();
    setInterval(updateResources, 60000); // Update every minute

    // Manual refresh button
    $('#refresh-resources').on('click', function(e) {
        e.preventDefault();
        updateResources();
    });

    // Alert History functionality
    let currentPage = 1;
    let totalPages = 1;
    let currentFilter = '';

    function loadAlertHistory() {
        const $list = $('#alert-history-list');
        const $prevBtn = $('.prev-page');
        const $nextBtn = $('.next-page');
        const $pageInfo = $('.page-info');

        $list.html('<tr><td colspan="5" class="loading-placeholder">' + wpResourcesL10n.loading + '</td></tr>');

        $.ajax({
            url: ajaxurl,
            data: {
                action: 'get_alert_history',
                nonce: wpResourcesL10n.nonce,
                page: currentPage,
                filter: currentFilter
            },
            success: function(response) {
                if (response.success) {
                    const { alerts, total, pages } = response.data;
                    totalPages = pages;

                    // Update pagination
                    $prevBtn.prop('disabled', currentPage === 1);
                    $nextBtn.prop('disabled', currentPage === totalPages);
                    $pageInfo.text(sprintf(wpResourcesL10n.pageInfo, currentPage, totalPages, total));

                    // Update table
                    if (alerts.length === 0) {
                        $list.html('<tr><td colspan="5" class="no-alerts">' + wpResourcesL10n.noAlerts + '</td></tr>');
                        return;
                    }

                    const rows = alerts.map(alert => `
                        <tr>
                            <td>${formatDate(alert.created_at)}</td>
                            <td><span class="alert-type ${alert.alert_type}">${alert.alert_type}</span></td>
                            <td>${alert.alert_message}</td>
                            <td>${alert.resource_value}</td>
                            <td>${alert.threshold_value}</td>
                        </tr>
                    `).join('');

                    $list.html(rows);
                }
            },
            error: function() {
                $list.html('<tr><td colspan="5" class="error">' + wpResourcesL10n.errorLoading + '</td></tr>');
            }
        });
    }

    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }

    // Pagination handlers
    $('.prev-page').on('click', function() {
        if (currentPage > 1) {
            currentPage--;
            loadAlertHistory();
        }
    });

    $('.next-page').on('click', function() {
        if (currentPage < totalPages) {
            currentPage++;
            loadAlertHistory();
        }
    });

    // Filter handler
    $('#alert-type-filter').on('change', function() {
        currentFilter = $(this).val();
        currentPage = 1;
        loadAlertHistory();
    });

    // Clear history handler
    $('#clear-history').on('click', function() {
        if (confirm(wpResourcesL10n.confirmClear)) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'clear_alert_history',
                    nonce: wpResourcesL10n.nonce
                },
                success: function(response) {
                    if (response.success) {
                        currentPage = 1;
                        loadAlertHistory();
                    }
                }
            });
        }
    });

    // Load history if we're on the history tab
    if (window.location.href.includes('tab=history')) {
        loadAlertHistory();
    }
}); 