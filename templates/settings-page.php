<?php
$options = $this->get_options();
$warning_levels = isset($options['warning_levels']) ? $options['warning_levels'] : self::DEFAULT_WARNING_LEVELS;
$email_settings = $options['email'];
$cron_interval = $options['cron_interval'];
?>

<div class="wrap">
    <form method="post" action="" id="wp-resources-settings">
        <?php wp_nonce_field('wp_resources_settings'); ?>
        
        <div class="settings-section">
            <h2><?php _e('Resource Warning Thresholds', 'wp-resources'); ?></h2>
            <p class="description">
                <?php _e('Configure when to show warnings for resource usage. Warning (yellow) indicates attention needed, Critical (red) indicates immediate action required.', 'wp-resources'); ?>
            </p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Memory Usage</th>
                    <td>
                        <label>
                            <?php _e('Warning at:', 'wp-resources'); ?>
                            <input type="number" name="memory_warning" 
                                value="<?php echo esc_attr($warning_levels['memory']['warning']); ?>" 
                                min="0" max="100" step="1" class="small-text" required> %
                        </label>
                        <label>
                            <?php _e('Critical at:', 'wp-resources'); ?>
                            <input type="number" name="memory_critical" 
                                value="<?php echo esc_attr($warning_levels['memory']['critical']); ?>" 
                                min="0" max="100" step="1" class="small-text" required> %
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Disk Usage', 'wp-resources'); ?></th>
                    <td>
                        <label>
                            <?php _e('Warning at:', 'wp-resources'); ?>
                            <input type="number" name="disk_warning" 
                                value="<?php echo esc_attr($warning_levels['disk']['warning']); ?>" 
                                min="0" max="100" step="1" class="small-text" required> %
                        </label>
                        <label>
                            <?php _e('Critical at:', 'wp-resources'); ?>
                            <input type="number" name="disk_critical" 
                                value="<?php echo esc_attr($warning_levels['disk']['critical']); ?>" 
                                min="0" max="100" step="1" class="small-text" required> %
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('CPU Load', 'wp-resources'); ?></th>
                    <td>
                        <label>
                            <?php _e('Warning at:', 'wp-resources'); ?>
                            <input type="number" name="cpu_warning" 
                                value="<?php echo esc_attr($warning_levels['cpu']['warning']); ?>" 
                                min="0" step="0.1" class="small-text" required>
                        </label>
                        <label>
                            <?php _e('Critical at:', 'wp-resources'); ?>
                            <input type="number" name="cpu_critical" 
                                value="<?php echo esc_attr($warning_levels['cpu']['critical']); ?>" 
                                min="0" step="0.1" class="small-text" required>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('PHP Processes', 'wp-resources'); ?></th>
                    <td>
                        <label>
                            <?php _e('Warning at:', 'wp-resources'); ?>
                            <input type="number" name="php_processes_warning" 
                                value="<?php echo esc_attr($warning_levels['php_processes']['warning']); ?>" 
                                min="0" max="100" step="1" class="small-text" required> %
                        </label>
                        <label>
                            <?php _e('Critical at:', 'wp-resources'); ?>
                            <input type="number" name="php_processes_critical" 
                                value="<?php echo esc_attr($warning_levels['php_processes']['critical']); ?>" 
                                min="0" max="100" step="1" class="small-text" required> %
                        </label>
                        <p class="description"><?php _e('Percentage of maximum allowed PHP processes in use', 'wp-resources'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('WordPress Cron', 'wp-resources'); ?></th>
                    <td>
                        <label>
                            <?php _e('Warning at:', 'wp-resources'); ?>
                            <input type="number" name="cron_jobs_warning" 
                                value="<?php echo esc_attr($warning_levels['cron_jobs']['warning']); ?>" 
                                min="0" step="1" class="small-text" required>
                        </label>
                        <label>
                            <?php _e('Critical at:', 'wp-resources'); ?>
                            <input type="number" name="cron_jobs_critical" 
                                value="<?php echo esc_attr($warning_levels['cron_jobs']['critical']); ?>" 
                                min="0" step="1" class="small-text" required>
                        </label>
                        <p class="description"><?php _e('Number of overdue cron jobs', 'wp-resources'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Transients', 'wp-resources'); ?></th>
                    <td>
                        <label>
                            <?php _e('Warning at:', 'wp-resources'); ?>
                            <input type="number" name="transients_warning" 
                                value="<?php echo esc_attr($warning_levels['transients']['warning']); ?>" 
                                min="0" step="1" class="small-text" required>
                        </label>
                        <label>
                            <?php _e('Critical at:', 'wp-resources'); ?>
                            <input type="number" name="transients_critical" 
                                value="<?php echo esc_attr($warning_levels['transients']['critical']); ?>" 
                                min="0" step="1" class="small-text" required>
                        </label>
                        <p class="description"><?php _e('Number of expired transients', 'wp-resources'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Database Size', 'wp-resources'); ?></th>
                    <td>
                        <label>
                            <?php _e('Warning at:', 'wp-resources'); ?>
                            <input type="number" name="db_size_warning" 
                                value="<?php echo esc_attr($warning_levels['db_size']['warning']); ?>" 
                                min="0" step="1" class="small-text" required> MB
                        </label>
                        <label>
                            <?php _e('Critical at:', 'wp-resources'); ?>
                            <input type="number" name="db_size_critical" 
                                value="<?php echo esc_attr($warning_levels['db_size']['critical']); ?>" 
                                min="0" step="1" class="small-text" required> MB
                        </label>
                        <p class="description"><?php _e('Total database size thresholds', 'wp-resources'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Database Connections', 'wp-resources'); ?></th>
                    <td>
                        <label>
                            <?php _e('Warning at:', 'wp-resources'); ?>
                            <input type="number" name="db_connections_warning" 
                                value="<?php echo esc_attr($warning_levels['db_connections']['warning']); ?>" 
                                min="0" max="100" step="1" class="small-text" required> %
                        </label>
                        <label>
                            <?php _e('Critical at:', 'wp-resources'); ?>
                            <input type="number" name="db_connections_critical" 
                                value="<?php echo esc_attr($warning_levels['db_connections']['critical']); ?>" 
                                min="0" max="100" step="1" class="small-text" required> %
                        </label>
                        <p class="description"><?php _e('Percentage of maximum allowed connections', 'wp-resources'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Query Performance', 'wp-resources'); ?></th>
                    <td>
                        <label>
                            <?php _e('Warning at:', 'wp-resources'); ?>
                            <input type="number" name="db_performance_warning" 
                                value="<?php echo esc_attr($warning_levels['db_performance']['warning']); ?>" 
                                min="0" step="1" class="small-text" required> slow queries
                        </label>
                        <label>
                            <?php _e('Critical at:', 'wp-resources'); ?>
                            <input type="number" name="db_performance_critical" 
                                value="<?php echo esc_attr($warning_levels['db_performance']['critical']); ?>" 
                                min="0" step="1" class="small-text" required> slow queries
                        </label>
                        <p class="description"><?php _e('Number of slow queries before warning', 'wp-resources'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Security Status', 'wp-resources'); ?></th>
                    <td>
                        <label>
                            <?php _e('Warning at:', 'wp-resources'); ?>
                            <input type="number" name="security_warning" 
                                value="<?php echo esc_attr($warning_levels['security']['warning']); ?>" 
                                min="0" step="1" class="small-text" required> events
                        </label>
                        <label>
                            <?php _e('Critical at:', 'wp-resources'); ?>
                            <input type="number" name="security_critical" 
                                value="<?php echo esc_attr($warning_levels['security']['critical']); ?>" 
                                min="0" step="1" class="small-text" required> events
                        </label>
                        <p class="description"><?php _e('Number of security events (failed logins, file changes, PHP errors) before warning', 'wp-resources'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Network Status', 'wp-resources'); ?></th>
                    <td>
                        <label>
                            <?php _e('Warning at:', 'wp-resources'); ?>
                            <input type="number" name="network_warning" 
                                value="<?php echo esc_attr($warning_levels['network']['warning']); ?>" 
                                min="0" step="1" class="small-text" required> ms
                        </label>
                        <label>
                            <?php _e('Critical at:', 'wp-resources'); ?>
                            <input type="number" name="network_critical" 
                                value="<?php echo esc_attr($warning_levels['network']['critical']); ?>" 
                                min="0" step="1" class="small-text" required> ms
                        </label>
                        <p class="description"><?php _e('Response time thresholds in milliseconds', 'wp-resources'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('SSL Certificate', 'wp-resources'); ?></th>
                    <td>
                        <label>
                            <?php _e('Warning at:', 'wp-resources'); ?>
                            <input type="number" name="ssl_warning" 
                                value="<?php echo esc_attr($warning_levels['ssl']['warning']); ?>" 
                                min="0" step="1" class="small-text" required> days
                        </label>
                        <label>
                            <?php _e('Critical at:', 'wp-resources'); ?>
                            <input type="number" name="ssl_critical" 
                                value="<?php echo esc_attr($warning_levels['ssl']['critical']); ?>" 
                                min="0" step="1" class="small-text" required> days
                        </label>
                        <p class="description"><?php _e('Days before certificate expiry to show warning', 'wp-resources'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="settings-section">
            <h2><?php _e('Notification Settings', 'wp-resources'); ?></h2>
            <p class="description"><?php _e('Configure how you want to be notified when thresholds are exceeded.', 'wp-resources'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Email Notifications', 'wp-resources'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="email_enabled" id="email-enabled" 
                                <?php checked($email_settings['enabled']); ?>>
                            <?php _e('Enable email notifications', 'wp-resources'); ?>
                        </label>
                        
                        <div class="email-settings <?php echo $email_settings['enabled'] ? '' : 'hidden'; ?>">
                            <p>
                                <label>
                                    <input type="checkbox" name="notify_warning" 
                                        <?php checked($email_settings['notify_warning']); ?>>
                                    <?php _e('Send notifications for warnings (yellow)', 'wp-resources'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="notify_critical" 
                                        <?php checked($email_settings['notify_critical']); ?>>
                                    <?php _e('Send notifications for critical alerts (red)', 'wp-resources'); ?>
                                </label>
                            </p>
                            
                            <p>
                                <label for="email_recipients"><?php _e('Recipients:', 'wp-resources'); ?></label><br>
                                <textarea name="email_recipients" id="email_recipients" class="large-text code" rows="3" 
                                    placeholder="<?php esc_attr_e('Enter email addresses, one per line', 'wp-resources'); ?>"
                                ><?php echo esc_textarea($email_settings['recipients']); ?></textarea>
                                <span class="description"><?php _e('Enter email addresses, one per line. If left empty, the admin email will be used.', 'wp-resources'); ?></span>
                            </p>
                            
                            <p>
                                <label for="email_frequency"><?php _e('Frequency:', 'wp-resources'); ?></label><br>
                                <select name="email_frequency" id="email_frequency">
                                    <option value="immediate" <?php selected($email_settings['frequency'], 'immediate'); ?>>
                                        <?php _e('Immediate', 'wp-resources'); ?>
                                    </option>
                                    <option value="hourly" <?php selected($email_settings['frequency'], 'hourly'); ?>>
                                        <?php _e('Hourly Summary', 'wp-resources'); ?>
                                    </option>
                                    <option value="daily" <?php selected($email_settings['frequency'], 'daily'); ?>>
                                        <?php _e('Daily Summary', 'wp-resources'); ?>
                                    </option>
                                </select>
                            </p>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="settings-section">
            <h2><?php _e('Resource Check Schedule', 'wp-resources'); ?></h2>
            <p class="description"><?php _e('Configure how often the system should check resource usage.', 'wp-resources'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Check Interval', 'wp-resources'); ?></th>
                    <td>
                        <select name="cron_interval" id="cron_interval">
                            <option value="wp_resources_5min" <?php selected($cron_interval, 'wp_resources_5min'); ?>>
                                <?php _e('Every 5 minutes', 'wp-resources'); ?>
                            </option>
                            <option value="wp_resources_15min" <?php selected($cron_interval, 'wp_resources_15min'); ?>>
                                <?php _e('Every 15 minutes', 'wp-resources'); ?>
                            </option>
                            <option value="wp_resources_30min" <?php selected($cron_interval, 'wp_resources_30min'); ?>>
                                <?php _e('Every 30 minutes', 'wp-resources'); ?>
                            </option>
                            <option value="hourly" <?php selected($cron_interval, 'hourly'); ?>>
                                <?php _e('Every hour', 'wp-resources'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('More frequent checks provide better monitoring but may impact performance.', 'wp-resources'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="settings-section">
            <h2><?php _e('Dashboard Settings', 'wp-resources'); ?></h2>
            <p class="description"><?php _e('Configure how the dashboard updates and displays information.', 'wp-resources'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Update Frequency', 'wp-resources'); ?></th>
                    <td>
                        <input type="number" name="update_frequency" id="update_frequency"
                            value="<?php echo esc_attr($options['update_frequency'] ?? 60); ?>"
                            min="10" max="300" step="1">
                        <p class="description"><?php _e('How often to update the dashboard (in seconds). Min: 10, Max: 300', 'wp-resources'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="settings-section">
            <h2><?php _e('Resource Thresholds', 'wp-resources'); ?></h2>
        </div>

        <p class="submit">
            <?php submit_button(null, 'primary', 'submit', false); ?>
            <input type="submit" name="reset" class="button button-secondary" 
                value="<?php esc_attr_e('Reset to Defaults', 'wp-resources'); ?>"
                onclick="return confirm('<?php esc_attr_e('Are you sure you want to reset all settings to their default values?', 'wp-resources'); ?>')">
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Initialize sound toggle from localStorage
    const soundEnabled = localStorage.getItem('wp_resources_sound') !== 'disabled';
    $('#toggle-sound').prop('checked', soundEnabled);

    // Toggle email settings visibility
    $('#email-enabled').on('change', function() {
        $('.email-settings').toggleClass('hidden', !this.checked);
    });

    // Save sound preference to localStorage
    $('#toggle-sound').on('change', function() {
        localStorage.setItem('wp_resources_sound', this.checked ? 'enabled' : 'disabled');
    });

    // Handle reset button
    $('input[name="reset"]').on('click', function(e) {
        if (!confirm($(this).attr('data-confirm'))) {
            e.preventDefault();
        }
    });
});
</script> 