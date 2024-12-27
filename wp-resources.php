<?php
/**
 * Plugin Name: WP Resources
 * Plugin URI: https://ncdlabs.com/wp-resources
 * Description: Monitors system resources like CPU, memory, and disk space with real-time updates and customizable warning thresholds.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Author: Lou Grossi
 * Author URI: https://ncdlabs.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-resources
 * Domain Path: /languages
 *
 * @package WP_Resourc es
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WPResources {
    /**
     * Default warning thresholds
     * @var array<string, float>
     */
    private const DEFAULT_THRESHOLDS = [
        'memory' => 80.0, // Warning at 80% usage
        'disk' => 85.0,   // Warning at 85% usage
        'cpu' => 5.0      // Warning at load average > 5.0
    ];

    /**
     * Warning level percentages of threshold
     * @var array<string, float>
     */
    private const WARNING_LEVELS = [
        'warning' => 80.0,  // Yellow warning at 80% of threshold
        'critical' => 90.0  // Red warning at 90% of threshold
    ];

    /**
     * Plugin version
     * @var string
     */
    private const VERSION = '1.0.0';

    /**
     * Default cron interval
     * @var string
     */
    private const DEFAULT_CRON_INTERVAL = 'hourly';

    /**
     * Number of alerts per page in history
     * @var int
     */
    private const ALERTS_PER_PAGE = 20;

    /**
     * Valid resource types
     * @var array<string>
     */
    private const VALID_RESOURCE_TYPES = ['memory', 'disk', 'cpu'];

    /**
     * Valid email frequencies
     * @var array<string>
     */
    private const VALID_EMAIL_FREQUENCIES = ['immediate', 'hourly', 'daily'];

    /**
     * Cache duration in seconds
     * @var int
     */
    private const CACHE_DURATION = 60; // 1 minute

    /**
     * Cache key prefix
     * @var string
     */
    private const CACHE_KEY_PREFIX = 'wp_resources_cache_';

    /**
     * Current thresholds
     * @var array<string, float>
     */
    private $thresholds;

    /**
     * Plugin options
     * @var array|null
     */
    private $options = null;

    /**
     * Maximum number of alerts to keep
     * @var int
     */
    private const MAX_ALERTS = 10000;

    private const DEFAULT_WARNING_LEVELS = [
        'memory' => [
            'warning' => 80.0,
            'critical' => 90.0
        ],
        'disk' => [
            'warning' => 80.0,
            'critical' => 90.0
        ],
        'cpu' => [
            'warning' => 5.0,
            'critical' => 8.0
        ]
    ];

    private const DEFAULT_EMAIL_SETTINGS = [
        'enabled' => true,
        'recipients' => '',
        'frequency' => 'immediate',
        'last_sent' => 0,
        'notify_warning' => true,
        'notify_critical' => true
    ];

    private function get_options() {
        if ($this->options === null) {
            $defaults = array(
                'version' => self::VERSION,
                'thresholds' => self::DEFAULT_THRESHOLDS,
                'cron_interval' => self::DEFAULT_CRON_INTERVAL,
                'email' => self::DEFAULT_EMAIL_SETTINGS
            );
            
            $saved_options = get_option('wp_resources_options', array());
            
            // Ensure thresholds are floats
            if (isset($saved_options['thresholds'])) {
                foreach (self::VALID_RESOURCE_TYPES as $type) {
                    if (isset($saved_options['thresholds'][$type])) {
                        $saved_options['thresholds'][$type] = (float) $saved_options['thresholds'][$type];
                    }
                }
            }

            // Ensure email settings have all required fields
            if (isset($saved_options['email'])) {
                $saved_options['email'] = wp_parse_args($saved_options['email'], self::DEFAULT_EMAIL_SETTINGS);
            }
            
            $this->options = wp_parse_args($saved_options, $defaults);
        }
        return $this->options;
    }

    private function update_options($new_options) {
        $this->options = wp_parse_args($new_options, $this->get_options());
        update_option('wp_resources_options', $this->options);
        $this->thresholds = $this->options['thresholds'];
    }

    public function __construct() {
        $this->thresholds = self::DEFAULT_THRESHOLDS;
        // Initialize options
        $this->get_options();
        
        // Load text domain for translations
        load_plugin_textdomain('wp-resources', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_get_system_resources', array($this, 'ajax_get_system_resources'));
        add_action('wp_ajax_save_wp_resources_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_get_alert_history', array($this, 'ajax_get_alert_history'));
        add_action('wp_ajax_clear_alert_history', array($this, 'ajax_clear_history'));
        add_action('admin_notices', array($this, 'display_resource_warnings'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_resources_cron_check', array($this, 'cron_check_resources'));
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
    }

    public function add_admin_menu() {
        // Main menu page only
        $hook = add_menu_page(
            __('WP Resources', 'wp-resources'),
            __('WP Resources', 'wp-resources'),
            'manage_options',
            'wp-resources',
            array($this, 'display_admin_page'),
            'dashicons-chart-area',
            100
        );

        // Ensure scripts only load on our page
        add_action("load-$hook", array($this, 'enqueue_admin_scripts'));
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_wp-resources') {
            return;
        }

        wp_enqueue_style(
            'wp-resources-admin',
            plugins_url('css/admin.css', __FILE__),
            array(),
            self::VERSION
        );

        wp_enqueue_script(
            'wp-resources-admin',
            plugins_url('js/admin.js', __FILE__),
            array('jquery'),
            self::VERSION,
            true
        );

        $options = $this->get_options();
        
        // Get warning levels from options
        $warning_levels = isset($options['warning_levels']) ? 
            $options['warning_levels'] : self::DEFAULT_WARNING_LEVELS;

        wp_localize_script('wp-resources-admin', 'wpResourcesL10n', array(
            'ajaxurl' => admin_url('ajax-php'),
            'nonce' => wp_create_nonce('wp_resources_nonce'),
            'warningLevels' => $warning_levels,
            'strings' => array(
                'noAlerts' => __('No alerts found.', 'wp-resources'),
                'pageInfo' => __('Showing %1$s to %2$s of %3$s alerts', 'wp-resources'),
                'confirmClear' => __('Are you sure you want to clear all alert history?', 'wp-resources'),
                'error' => __('Error:', 'wp-resources')
            )
        ));
    }

    public function display_admin_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
        ?>
        <div class="wrap">
            <h1><?php _e('WP Resources', 'wp-resources'); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=wp-resources&tab=dashboard" class="nav-tab <?php echo $active_tab == 'dashboard' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Dashboard', 'wp-resources'); ?>
                </a>
                <a href="?page=wp-resources&tab=history" class="nav-tab <?php echo $active_tab == 'history' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Alert History', 'wp-resources'); ?>
                </a>
                <a href="?page=wp-resources&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Settings', 'wp-resources'); ?>
                </a>
            </h2>

            <div id="wp-resources-dashboard" class="tab-content" <?php echo $active_tab != 'dashboard' ? 'style="display: none;"' : ''; ?>>
                <?php include plugin_dir_path(__FILE__) . 'templates/admin-page.php'; ?>
            </div>

            <div id="wp-resources-history" class="tab-content" <?php echo $active_tab != 'history' ? 'style="display: none;"' : ''; ?>>
                <?php include plugin_dir_path(__FILE__) . 'templates/history-page.php'; ?>
            </div>
            
            <div id="wp-resources-settings" class="tab-content" <?php echo $active_tab != 'settings' ? 'style="display: none;"' : ''; ?>>
                <?php $this->display_settings_page(); ?>
            </div>
        </div>
        <?php
    }

    public function ajax_get_system_resources() {
        // Force refresh if explicitly requested
        if (!empty($_GET['refresh'])) {
            delete_transient(self::CACHE_KEY_PREFIX . 'resources');
        }
        wp_send_json_success($this->get_system_resources());
    }

    public function get_system_resources() {
        try {
            // Try to get cached data first
            $cached_data = get_transient(self::CACHE_KEY_PREFIX . 'resources');
            if ($cached_data !== false) {
                return $cached_data;
            }

            $support = $this->check_system_support();
            
            // Initialize response with defaults
            $response = $this->get_default_resource_response($support);
            
            // Get metrics for supported resources
            foreach (self::VALID_RESOURCE_TYPES as $type) {
                if ($support[$type]) {
                    $this->update_resource_metrics($type, $response);
                }
            }

            // Cache the response
            set_transient(self::CACHE_KEY_PREFIX . 'resources', $response, self::CACHE_DURATION);
            
            return $response;
            
        } catch (Exception $e) {
            error_log(sprintf(
                'WP Resources - Critical Error: %s',
                $e->getMessage()
            ));
            return $this->get_default_resource_response([
                'memory' => false,
                'disk' => false,
                'cpu' => false
            ]);
        }
    }

    private function get_default_resource_response($support) {
        return [
            'memory_usage' => 'N/A',
            'memory_limit' => 'N/A',
            'memory_percentage' => 0,
            'disk_free' => 'N/A',
            'disk_total' => 'N/A',
            'disk_usage_percentage' => 0,
            'cpu_usage' => 'N/A',
            'warnings' => array_fill_keys(self::VALID_RESOURCE_TYPES, false),
            'support' => $support
        ];
    }

    private function update_resource_metrics($type, &$response) {
        try {
            switch ($type) {
                case 'memory':
                    $this->update_memory_metrics($response);
                    break;
                case 'disk':
                    $this->update_disk_metrics($response);
                    break;
                case 'cpu':
                    $this->update_cpu_metrics($response);
                    break;
            }
        } catch (Exception $e) {
            error_log(sprintf(
                'WP Resources - %s Error: %s',
                ucfirst($type),
                $e->getMessage()
            ));
        }
    }

    private function update_memory_metrics(&$response) {
        $memory_usage = memory_get_usage(true);
        $memory_limit = $this->parse_memory_limit(ini_get('memory_limit'));
        $memory_percentage = ($memory_usage / $memory_limit) * 100;
        
        $response['memory_usage'] = $this->format_bytes($memory_usage);
        $response['memory_limit'] = $this->format_bytes($memory_limit);
        $response['memory_percentage'] = round($memory_percentage, 2);
        
        // Use warning levels from settings
        $warning_levels = $this->get_options()['warning_levels']['memory'];
        $response['warnings']['memory'] = $memory_percentage >= $warning_levels['warning'];
    }

    private function update_disk_metrics(&$response) {
        $disk_free_space = @disk_free_space("/");
        $disk_total_space = @disk_total_space("/");
        
        if ($disk_free_space === false || $disk_total_space === false) {
            throw new Exception('Unable to read disk space');
        }
        
        $disk_used_space = $disk_total_space - $disk_free_space;
        $disk_usage_percentage = round(($disk_used_space / $disk_total_space) * 100, 2);
        
        $response['disk_free'] = $this->format_bytes($disk_free_space);
        $response['disk_total'] = $this->format_bytes($disk_total_space);
        $response['disk_usage'] = $this->format_bytes($disk_used_space);
        $response['disk_usage_percentage'] = $disk_usage_percentage;
        
        // Use warning levels from settings
        $warning_levels = $this->get_options()['warning_levels']['disk'];
        $response['warnings']['disk'] = $disk_usage_percentage >= $warning_levels['warning'];
    }

    private function update_cpu_metrics(&$response) {
        $load = sys_getloadavg();
        if ($load === false) {
            throw new Exception('Unable to get CPU load average');
        }
        
        $cpu_usage = $load[0];
        $response['cpu_usage'] = $this->format_cpu_load($cpu_usage);
        
        // Use warning levels from settings
        $warning_levels = $this->get_options()['warning_levels']['cpu'];
        $response['warnings']['cpu'] = $cpu_usage >= $warning_levels['warning'];
    }

    /**
     * Format bytes to human readable format
     * @param int $bytes Number of bytes
     * @param int $precision Decimal precision
     * @return string Formatted size
     */
    private function format_bytes($bytes, $precision = 2) {
        $bytes = max(0, (int) $bytes);
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        if ($bytes === 0) {
            return '0 B';
        }
        
        $pow = floor(log($bytes) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return sprintf(
            '%.' . $precision . 'f %s',
            $bytes,
            $units[$pow]
        );
    }

    /**
     * Parse memory limit value to bytes
     * @param string $val Memory limit value
     * @return int Number of bytes
     */
    private function parse_memory_limit($val) {
        try {
            $val = trim($val);
            if (empty($val)) {
                throw new Exception(__('Empty memory limit value', 'wp-resources'));
            }
            
            $last = strtolower($val[strlen($val)-1]);
            $val = (int) $val;
            
            if ($val <= 0) {
                throw new Exception(__('Invalid memory limit value', 'wp-resources'));
            }
            
            switch($last) {
                case 'g': $val *= 1024;
                case 'm': $val *= 1024;
                case 'k': $val *= 1024;
            }
            
            return $val;
        } catch (Exception $e) {
            error_log(sprintf(
                'WP Resources: Memory limit parsing error - %s',
                $e->getMessage()
            ));
            return PHP_INT_MAX; // Return a safe default
        }
    }

    /**
     * Format a percentage value
     * @param float $value The value to format
     * @param int $precision Decimal precision
     * @return string Formatted percentage
     */
    private function format_percentage($value, $precision = 2) {
        return sprintf(
            '%.' . $precision . 'f%%',
            max(0, min(100, (float) $value))
        );
    }

    /**
     * Format CPU load value
     * @param float $value CPU load value
     * @param int $precision Decimal precision
     * @return string Formatted CPU load
     */
    private function format_cpu_load($value, $precision = 2) {
        return sprintf(
            '%.' . $precision . 'f',
            max(0, (float) $value)
        );
    }

    /**
     * Get resource status (normal, warning, critical)
     * @param string $type Resource type
     * @param float $value Current value
     * @return string Status
     */
    private function get_resource_status($type, $value) {
        $value = (float) $value;
        $threshold = $this->thresholds[$type];
        
        // Calculate percentage of threshold
        $thresholdPercentage = ($value / $threshold) * 100;
        
        if ($thresholdPercentage >= self::WARNING_LEVELS['critical']) {
            return 'critical';
        }
        
        if ($thresholdPercentage >= self::WARNING_LEVELS['warning']) {
            return 'warning';
        }
        
        return 'normal';
    }

    public function display_resource_warnings() {
        $resources = $this->get_system_resources();
        $warnings = $resources['warnings'];
        
        if ($warnings['memory'] || $warnings['disk'] || $warnings['cpu']) {
            // Send email notification
            $this->send_email_notification($warnings, $resources);
            
            // Log alerts to database
            if ($warnings['memory']) {
                $this->log_alert(
                    'memory',
                    sprintf(__('Memory usage exceeded threshold', 'wp-resources')),
                    $resources['memory_percentage'] . '%',
                    $this->thresholds['memory'] . '%'
                );
            }
            if ($warnings['disk']) {
                $this->log_alert(
                    'disk',
                    sprintf(__('Disk usage exceeded threshold', 'wp-resources')),
                    $resources['disk_usage_percentage'] . '%',
                    $this->thresholds['disk'] . '%'
                );
            }
            if ($warnings['cpu']) {
                $this->log_alert(
                    'cpu',
                    sprintf(__('CPU load exceeded threshold', 'wp-resources')),
                    $resources['cpu_usage'],
                    $this->thresholds['cpu']
                );
            }
            
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>' . __('WP Resources Warning:', 'wp-resources') . '</strong></p><ul>';
            
            if ($warnings['memory']) {
                printf(
                    '<li>' . __('Memory usage is at %1$s%% (%2$s of %3$s)', 'wp-resources') . '</li>',
                    $resources['memory_percentage'],
                    $resources['memory_usage'],
                    $resources['memory_limit']
                );
            }
            if ($warnings['disk']) {
                printf(
                    '<li>' . __('Disk usage is at %s%%', 'wp-resources') . '</li>',
                    $resources['disk_usage_percentage']
                );
            }
            if ($warnings['cpu']) {
                printf(
                    '<li>' . __('CPU load is high: %s', 'wp-resources') . '</li>',
                    $resources['cpu_usage']
                );
            }
            
            echo '</ul></div>';
        }
    }

    public function register_settings() {
        register_setting('wp_resources_options', 'wp_resources_options');
    }

    public function ajax_save_settings() {
        // Verify nonce and capabilities
        if (!check_ajax_referer('wp_resources_settings', 'nonce', false) || 
            !current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'wp-resources'));
            return;
        }

        try {
            $new_options = $this->get_options();
            $new_options['thresholds'] = array(
                'memory' => min(100, max(0, intval($_POST['memory_threshold']))),
                'disk' => min(100, max(0, intval($_POST['disk_threshold']))),
                'cpu' => max(0, floatval($_POST['cpu_threshold']))
            );
            
            $this->update_options($new_options);
            
            wp_send_json_success(array(
                'message' => __('Settings saved successfully.', 'wp-resources'),
                'thresholds' => $new_options['thresholds']
            ));
        } catch (Exception $e) {
            wp_send_json_error(sprintf(
                __('Error saving settings: %s', 'wp-resources'), 
                $e->getMessage()
            ));
        }
    }

    public function display_settings_page() {
        if (isset($_POST['submit'])) {
            try {
                check_admin_referer('wp_resources_settings');
                
                $new_options = $this->get_options();
                
                // Validate and save warning levels
                foreach (self::VALID_RESOURCE_TYPES as $type) {
                    $warning = $type === 'cpu' ? 
                        max(0, floatval($_POST["{$type}_warning"])) :
                        min(100, max(0, intval($_POST["{$type}_warning"])));
                        
                    $critical = $type === 'cpu' ? 
                        max(0, floatval($_POST["{$type}_critical"])) :
                        min(100, max(0, intval($_POST["{$type}_critical"])));
                    
                    // Ensure critical is higher than warning
                    if ($critical <= $warning) {
                        throw new Exception(
                            sprintf(
                                __('Critical threshold must be higher than warning threshold for %s', 'wp-resources'),
                                $type
                            )
                        );
                    }
                    
                    $new_options['warning_levels'][$type] = [
                        'warning' => $warning,
                        'critical' => $critical
                    ];
                }
                
                // Save email settings
                $new_options['email'] = array(
                    'enabled' => isset($_POST['email_enabled']),
                    'recipients' => sanitize_textarea_field($_POST['email_recipients']),
                    'frequency' => sanitize_key($_POST['email_frequency']),
                    'last_sent' => 0
                );

                // Save cron interval
                $new_interval = sanitize_key($_POST['cron_interval']);
                if ($new_interval !== $new_options['cron_interval']) {
                    $new_options['cron_interval'] = $new_interval;
                    $this->update_cron_schedule($new_interval);
                }
                
                $this->update_options($new_options);
                echo '<div class="notice notice-success is-dismissible"><p>' . 
                    __('Settings saved successfully.', 'wp-resources') . '</p></div>';
            } catch (Exception $e) {
                echo '<div class="notice notice-error is-dismissible"><p>' . 
                    sprintf(__('Error saving settings: %s', 'wp-resources'), esc_html($e->getMessage())) . 
                    '</p></div>';
            }
        }

        $options = $this->get_options();
        $warning_levels = isset($options['warning_levels']) ? 
            $options['warning_levels'] : self::DEFAULT_WARNING_LEVELS;
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
                            <th scope="row"><?php _e('Memory Usage', 'wp-resources'); ?></th>
                            <td>
                                <label>
                                    <?php _e('Warning at:', 'wp-resources'); ?>
                                    <input type="number" name="memory_warning" 
                                        value="<?php echo esc_attr($warning_levels['memory']['warning']); ?>" 
                                        min="0" max="100" step="1" class="small-text" required> %
                                </label>
                                <br>
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
                                <br>
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
                                <br>
                                <label>
                                    <?php _e('Critical at:', 'wp-resources'); ?>
                                    <input type="number" name="cpu_critical" 
                                        value="<?php echo esc_attr($warning_levels['cpu']['critical']); ?>" 
                                        min="0" step="0.1" class="small-text" required>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="settings-section">
                    <h2><?php _e('Notification Settings', 'wp-resources'); ?></h2>
                    <p class="description"><?php _e('Configure how you want to be notified when thresholds are exceeded.', 'wp-resources'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Sound Notifications', 'wp-resources'); ?></th>
                            <td>
                                <label class="sound-toggle">
                                    <input type="checkbox" id="toggle-sound" checked>
                                    <?php _e('Enable warning sounds', 'wp-resources'); ?>
                                </label>
                                <p class="description"><?php _e('Play a sound when resource usage exceeds warning thresholds.', 'wp-resources'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Email Notifications', 'wp-resources'); ?></th>
                            <td>
                                <label class="email-toggle">
                                    <input type="checkbox" name="email_enabled" 
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
                                        <textarea name="email_recipients" id="email_recipients" 
                                            class="large-text code" rows="3" 
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
                                        <span class="description"><?php _e('How often to send email notifications.', 'wp-resources'); ?></span>
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
                                        <?php _e('Hourly', 'wp-resources'); ?>
                                    </option>
                                    <option value="twicedaily" <?php selected($cron_interval, 'twicedaily'); ?>>
                                        <?php _e('Twice Daily', 'wp-resources'); ?>
                                    </option>
                                    <option value="daily" <?php selected($cron_interval, 'daily'); ?>>
                                        <?php _e('Daily', 'wp-resources'); ?>
                                    </option>
                                </select>
                                <p class="description">
                                    <?php _e('Select how frequently you want to check system resources. More frequent checks provide better monitoring but may impact performance.', 'wp-resources'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <p class="submit">
                    <?php submit_button(null, 'primary', 'submit', false); ?>
                    <input type="submit" name="reset" class="button button-secondary button-reset" 
                        value="<?php esc_attr_e('Reset to Defaults', 'wp-resources'); ?>"
                        onclick="return confirm('<?php esc_attr_e('Are you sure you want to reset all settings to their default values?', 'wp-resources'); ?>')">
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('input[name="email_enabled"]').on('change', function() {
                $('.email-settings').toggleClass('hidden', !this.checked);
            });
        });
        </script>
        <?php
    }

    private function maybe_upgrade() {
        $saved_version = get_option('wp_resources_version');
        if ($saved_version !== $this->version) {
            // Add upgrade routines here for future versions
            update_option('wp_resources_version', $this->version);
        }
    }

    private function check_system_support() {
        // Try to get cached support data
        $cached_support = get_transient(self::CACHE_KEY_PREFIX . 'support');
        if ($cached_support !== false) {
            return $cached_support;
        }

        $support = [];
        
        foreach (self::VALID_RESOURCE_TYPES as $type) {
            try {
                switch ($type) {
                    case 'memory':
                        $support[$type] = function_exists('memory_get_usage');
                        break;
                    case 'disk':
                        $support[$type] = function_exists('disk_free_space') && 
                                        function_exists('disk_total_space') &&
                                        @disk_free_space("/") !== false;
                        break;
                    case 'cpu':
                        $support[$type] = function_exists('sys_getloadavg') &&
                                        sys_getloadavg() !== false;
                        break;
                    default:
                        $support[$type] = false;
                }

                if (!$support[$type]) {
                    error_log(sprintf(
                        'WP Resources: %s monitoring is not supported on this server',
                        ucfirst($type)
                    ));
                }
            } catch (Exception $e) {
                error_log(sprintf(
                    'WP Resources: Error checking %s support - %s',
                    $type,
                    $e->getMessage()
                ));
                $support[$type] = false;
            }
        }

        // Cache support data for 1 hour as it rarely changes
        set_transient(self::CACHE_KEY_PREFIX . 'support', $support, HOUR_IN_SECONDS);

        return $support;
    }

    private function validate_email_settings($settings) {
        $validated = [
            'enabled' => (bool) $settings['enabled'],
            'frequency' => in_array($settings['frequency'], self::VALID_EMAIL_FREQUENCIES, true) 
                ? $settings['frequency'] 
                : 'immediate',
            'last_sent' => (int) $settings['last_sent'],
            'notify_warning' => isset($settings['notify_warning']) ? (bool) $settings['notify_warning'] : true,
            'notify_critical' => isset($settings['notify_critical']) ? (bool) $settings['notify_critical'] : true,
            'recipients' => []
        ];

        // Validate recipients
        $recipients = array_map('trim', explode("\n", $settings['recipients']));
        foreach ($recipients as $recipient) {
            if (is_email($recipient)) {
                $validated['recipients'][] = $recipient;
            }
        }

        // If no valid recipients but enabled, use admin email
        if ($validated['enabled'] && empty($validated['recipients'])) {
            $validated['recipients'][] = get_option('admin_email');
        }

        return $validated;
    }

    private function send_email_notification($warnings, $resources) {
        $options = $this->get_options();
        $email_settings = $this->validate_email_settings($options['email']);
        
        if (!$email_settings['enabled'] || empty($email_settings['recipients'])) {
            return;
        }

        // Check if any resources are in warning or critical state
        $has_warnings = false;
        $has_critical = false;
        
        foreach (self::VALID_RESOURCE_TYPES as $type) {
            if ($warnings[$type]) {
                $value = (float) ($type === 'cpu' ? $resources['cpu_usage'] : $resources[$type . '_usage_percentage']);
                $threshold = $this->thresholds[$type];
                $threshold_percentage = ($value / $threshold) * 100;
                
                if ($threshold_percentage >= self::WARNING_LEVELS['critical']) {
                    $has_critical = true;
                } else if ($threshold_percentage >= self::WARNING_LEVELS['warning']) {
                    $has_warnings = true;
                }
            }
        }

        // Return if no notifications are needed based on settings
        if ((!$has_warnings || !$email_settings['notify_warning']) && 
            (!$has_critical || !$email_settings['notify_critical'])) {
            return;
        }

        // Check frequency
        $now = time();
        $last_sent = $email_settings['last_sent'];
        
        switch ($email_settings['frequency']) {
            case 'hourly':
                if ($now - $last_sent < HOUR_IN_SECONDS) {
                    return;
                }
                break;
            case 'daily':
                if ($now - $last_sent < DAY_IN_SECONDS) {
                    return;
                }
                break;
            case 'immediate':
                // Send immediately
                break;
        }

        // Build email content
        $subject = sprintf(
            __('[%s] Resource %s', 'wp-resources'),
            get_bloginfo('name'),
            $has_critical ? __('Critical Alert', 'wp-resources') : __('Warning', 'wp-resources')
        );

        $message = $this->build_email_message($warnings, $resources);

        // Send email
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            sprintf('From: %s <%s>', get_bloginfo('name'), get_option('admin_email'))
        );
        
        $sent = false;
        foreach ($email_settings['recipients'] as $recipient) {
            if (wp_mail($recipient, $subject, $message, $headers)) {
                $sent = true;
            }
        }

        if ($sent) {
            // Update last sent time only if at least one email was sent
            $options['email']['last_sent'] = $now;
            $this->update_options($options);
        }
    }

    private function build_email_message($warnings, $resources) {
        $message = sprintf(
            __('Resource usage warnings have been triggered on %s:', 'wp-resources'),
            get_bloginfo('name')
        ) . "\n\n";

        foreach (self::VALID_RESOURCE_TYPES as $type) {
            if ($warnings[$type]) {
                $message .= $this->format_resource_message($type, $resources) . "\n";
            }
        }

        $message .= "\n" . sprintf(
            __('View details: %s', 'wp-resources'),
            admin_url('admin.php?page=wp-resources')
        );

        return $message;
    }

    private function format_resource_message($type, $resources) {
        switch ($type) {
            case 'memory':
                return sprintf(
                    __("Memory Usage: %s%% (%s of %s)", 'wp-resources'),
                    $resources['memory_percentage'],
                    $resources['memory_usage'],
                    $resources['memory_limit']
                );
            case 'disk':
                return sprintf(
                    __("Disk Usage: %s%%", 'wp-resources'),
                    $resources['disk_usage_percentage']
                );
            case 'cpu':
                return sprintf(
                    __("CPU Load: %s", 'wp-resources'),
                    $resources['cpu_usage']
                );
            default:
                return '';
        }
    }

    /**
     * Get database table name
     * @return string
     */
    private static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'wp_resources_history';
    }

    /**
     * Handle database errors
     * @param string $operation Operation being performed
     * @throws Exception
     */
    private function handle_db_error($operation) {
        global $wpdb;
        if ($wpdb->last_error) {
            $error_msg = sprintf(
                'Database error during %s: %s',
                $operation,
                $wpdb->last_error
            );
            error_log('WP Resources: ' . $error_msg);
            throw new Exception($error_msg);
        }
    }

    /**
     * Cleanup old alerts to prevent database bloat
     */
    private function cleanup_old_alerts() {
        try {
            global $wpdb;
            $table_name = self::get_table_name();
            
            // Get total count
            $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $this->handle_db_error('counting total alerts');
            
            if ($total > self::MAX_ALERTS) {
                // Calculate how many to delete
                $to_delete = $total - self::MAX_ALERTS;
                
                // Delete oldest alerts
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM $table_name WHERE id IN (
                        SELECT id FROM (
                            SELECT id FROM $table_name ORDER BY created_at ASC LIMIT %d
                        ) tmp
                    )",
                    $to_delete
                ));
                $this->handle_db_error('cleaning up old alerts');
            }
        } catch (Exception $e) {
            error_log(sprintf(
                'WP Resources: Failed to cleanup old alerts - %s',
                $e->getMessage()
            ));
        }
    }

    private function log_alert($type, $message, $value, $threshold) {
        global $wpdb;
        $table_name = self::get_table_name();
        
        try {
            // Start transaction
            $wpdb->query('START TRANSACTION');
            
            $result = $wpdb->insert(
                $table_name,
                array(
                    'alert_type' => $type,
                    'alert_message' => $message,
                    'resource_value' => $value,
                    'threshold_value' => $threshold,
                ),
                array('%s', '%s', '%s', '%s')
            );

            if ($result === false) {
                throw new Exception($wpdb->last_error);
            }

            // Cleanup old alerts if needed
            $this->cleanup_old_alerts();

            // Commit transaction
            $wpdb->query('COMMIT');

        } catch (Exception $e) {
            // Rollback on error
            $wpdb->query('ROLLBACK');
            error_log(sprintf(
                'WP Resources: Failed to log alert - %s',
                $e->getMessage()
            ));
        }
    }

    public function ajax_get_alert_history() {
        check_ajax_referer('wp_resources_nonce', 'nonce');
        
        try {
            global $wpdb;
            $table_name = self::get_table_name();
            
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $offset = ($page - 1) * self::ALERTS_PER_PAGE;
            
            $where = '';
            $where_args = array();
            
            // Add type filter if specified
            if (!empty($_GET['filter'])) {
                $type = sanitize_key($_GET['filter']);
                if (in_array($type, self::VALID_RESOURCE_TYPES, true)) {
                    $where = 'WHERE alert_type = %s';
                    $where_args[] = $type;
                }
            }
            
            // Use SQL_CALC_FOUND_ROWS for better performance
            $query = $wpdb->prepare(
                "SELECT SQL_CALC_FOUND_ROWS * FROM $table_name $where 
                ORDER BY created_at DESC LIMIT %d OFFSET %d",
                array_merge($where_args, array(self::ALERTS_PER_PAGE, $offset))
            );
            
            $alerts = $wpdb->get_results($query);
            $this->handle_db_error('fetching alerts');
            
            $total = (int) $wpdb->get_var('SELECT FOUND_ROWS()');
            $this->handle_db_error('counting alerts');
            
            wp_send_json_success(array(
                'alerts' => $alerts,
                'total' => $total,
                'pages' => ceil($total / self::ALERTS_PER_PAGE)
            ));
        } catch (Exception $e) {
            wp_send_json_error(sprintf(
                __('Error fetching alert history: %s', 'wp-resources'),
                $e->getMessage()
            ));
        }
    }

    public function ajax_clear_history() {
        check_ajax_referer('wp_resources_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'wp-resources'));
            return;
        }
        
        try {
            global $wpdb;
            $table_name = self::get_table_name();
            
            $result = $wpdb->query("TRUNCATE TABLE $table_name");
            
            if ($result === false) {
                $this->handle_db_error('clearing history');
            }
            
            wp_send_json_success(array(
                'message' => __('Alert history cleared successfully.', 'wp-resources')
            ));
        } catch (Exception $e) {
            wp_send_json_error(sprintf(
                __('Failed to clear history: %s', 'wp-resources'),
                $e->getMessage()
            ));
        }
    }

    public function add_cron_intervals($schedules) {
        $schedules['wp_resources_5min'] = array(
            'interval' => 300,
            'display' => __('Every 5 minutes', 'wp-resources')
        );
        $schedules['wp_resources_15min'] = array(
            'interval' => 900,
            'display' => __('Every 15 minutes', 'wp-resources')
        );
        $schedules['wp_resources_30min'] = array(
            'interval' => 1800,
            'display' => __('Every 30 minutes', 'wp-resources')
        );
        return $schedules;
    }

    public function cron_check_resources() {
        $resources = $this->get_system_resources();
        $warnings = $resources['warnings'];
        
        if ($warnings['memory'] || $warnings['disk'] || $warnings['cpu']) {
            // Log alerts to database
            foreach (self::VALID_RESOURCE_TYPES as $type) {
                if ($warnings[$type]) {
                    $value = $this->format_resource_value($type, $resources);
                    $threshold = $this->format_threshold_value($type);
                    
                    $this->log_alert(
                        $type,
                        sprintf(__('%s usage exceeded threshold', 'wp-resources'), ucfirst($type)),
                        $value,
                        $threshold
                    );
                }
            }

            // Send email notification if enabled
            $this->send_email_notification($warnings, $resources);
        }
    }

    /**
     * Format resource value for logging
     * @param string $type Resource type
     * @param array $resources Resource data
     * @return string Formatted value
     */
    private function format_resource_value($type, $resources) {
        if ($type === 'cpu') {
            return (string) $resources['cpu_usage'];
        }
        return $resources[$type . '_usage_percentage'] . '%';
    }

    /**
     * Format threshold value for logging
     * @param string $type Resource type
     * @return string Formatted threshold
     */
    private function format_threshold_value($type) {
        $threshold = $this->thresholds[$type];
        return $type === 'cpu' ? (string) $threshold : $threshold . '%';
    }

    public function update_cron_schedule($new_interval) {
        $timestamp = wp_next_scheduled('wp_resources_cron_check');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wp_resources_cron_check');
        }
        wp_schedule_event(time(), $new_interval, 'wp_resources_cron_check');
    }

    /**
     * Clean up plugin data and settings
     */
    public static function deactivate() {
        global $wpdb;

        // Clear scheduled cron event
        $timestamp = wp_next_scheduled('wp_resources_cron_check');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wp_resources_cron_check');
        }

        // Remove all plugin options
        delete_option('wp_resources_options');

        // Clear all transients
        delete_transient(self::CACHE_KEY_PREFIX . 'resources');
        delete_transient(self::CACHE_KEY_PREFIX . 'support');

        // Optionally, drop the history table (commented out by default)
        /*
        $table_name = $wpdb->prefix . 'wp_resources_history';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        */
    }

    /**
     * Plugin activation hook
     */
    public static function activate() {
        try {
            global $wpdb;
            $table_name = self::get_table_name();
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                alert_type varchar(20) NOT NULL,
                alert_message text NOT NULL,
                resource_value varchar(50) NOT NULL,
                threshold_value varchar(50) NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY alert_type (alert_type),
                KEY created_at (created_at)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            // Set default options if they don't exist
            if (!get_option('wp_resources_options')) {
                $default_options = array(
                    'version' => self::VERSION,
                    'warning_levels' => self::DEFAULT_WARNING_LEVELS,
                    'cron_interval' => self::DEFAULT_CRON_INTERVAL,
                    'email' => self::DEFAULT_EMAIL_SETTINGS
                );
                update_option('wp_resources_options', $default_options);
            }

            // Schedule cron event with default interval
            if (!wp_next_scheduled('wp_resources_cron_check')) {
                wp_schedule_event(time(), self::DEFAULT_CRON_INTERVAL, 'wp_resources_cron_check');
            }

        } catch (Exception $e) {
            error_log(sprintf(
                'WP Resources: Activation error - %s',
                $e->getMessage()
            ));
            throw $e; // Re-throw to prevent activation
        }
    }
}

// Initialize the plugin
$wp_resources = new WPResources();

// Register activation hook
register_activation_hook(__FILE__, array('WPResources', 'activate'));

// Register deactivation hook
register_deactivation_hook(__FILE__, array('WPResources', 'deactivate')); 