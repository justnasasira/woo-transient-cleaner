<?php

class Woo_Transient_Cleaner {
    /**
     * The single instance of the class.
     *
     * @var Woo_Transient_Cleaner
     */
    protected static $instance = null;

    /**
     * Plugin options
     *
     * @var array
     */
    protected $options;

    /**
     * Initialize the plugin
     */
    public function __construct() {
        $this->init_options();
        $this->init_hooks();
    }

    /**
     * Initialize plugin options
     */
    private function init_options() {
        $default_options = array(
            'interval' => 3, // Default interval in days
            'last_run' => 0,
            'next_run' => 0,
            'logging_enabled' => false
        );

        // Get existing options
        $existing_options = get_option('wtc_options');
        
        // If options don't exist, create them
        if ($existing_options === false) {
            $this->options = $default_options;
            add_option('wtc_options', $default_options);
        } else {
            // Merge existing options with defaults
            $this->options = wp_parse_args($existing_options, $default_options);
        }
    }

    /**
     * Update plugin options
     * 
     * @param array $new_options New options to update
     * @return bool True on success, false on failure
     */
    private function update_options($new_options) {
        try {
            // Ensure we have all required options
            $default_options = array(
                'interval' => 3,
                'last_run' => 0,
                'next_run' => 0,
                'logging_enabled' => false
            );

            // Merge with defaults to ensure all options exist
            $options = wp_parse_args($new_options, $default_options);

            // Validate options
            $options['interval'] = absint($options['interval']);
            if ($options['interval'] < 1) {
                $options['interval'] = 3;
            }

            $options['last_run'] = absint($options['last_run']);
            $options['next_run'] = absint($options['next_run']);
            $options['logging_enabled'] = (bool) $options['logging_enabled'];

            // Update options
            $update_result = update_option('wtc_options', $options);
            
            if ($update_result === false) {
                // If update failed, try to add the option
                if (get_option('wtc_options') === false) {
                    $update_result = add_option('wtc_options', $options);
                }
            }

            if ($update_result) {
                $this->options = $options;
                return true;
            }

            throw new Exception('Failed to update plugin options in the database');
        } catch (Exception $e) {
            error_log('WooCommerce Transient Cleaner Error (update_options): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register activation hook
        register_activation_hook(WTC_PLUGIN_DIR . 'woo-transient-cleaner.php', array($this, 'activate'));
        
        // Register deactivation hook
        register_deactivation_hook(WTC_PLUGIN_DIR . 'woo-transient-cleaner.php', array($this, 'deactivate'));

        // Schedule the cleanup event
        add_action('init', array($this, 'schedule_cleanup'));

        // Hook into the scheduled event
        add_action('wtc_cleanup_transients', array($this, 'cleanup_transients'));

        // Add settings link to plugins page
        add_filter('plugin_action_links_woo-transient-cleaner/woo-transient-cleaner.php', array($this, 'add_settings_link'));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Schedule the first cleanup
        if (!wp_next_scheduled('wtc_cleanup_transients')) {
            wp_schedule_event(time(), 'daily', 'wtc_cleanup_transients');
        }

        // Set initial next run time
        $this->update_next_run_time();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear the scheduled event
        wp_clear_scheduled_hook('wtc_cleanup_transients');
    }

    /**
     * Schedule the cleanup event
     */
    public function schedule_cleanup() {
        if (!wp_next_scheduled('wtc_cleanup_transients')) {
            wp_schedule_event(time(), 'daily', 'wtc_cleanup_transients');
        }
    }

    /**
     * Clean up transients
     */
    public function cleanup_transients() {
        try {
            // Verify WooCommerce is active
            if (!class_exists('WooCommerce')) {
                throw new Exception('WooCommerce is not active');
            }

            // Check if it's time to run based on the interval
            $last_run = $this->options['last_run'];
            $interval = $this->options['interval'] * DAY_IN_SECONDS;
            
            if (time() - $last_run < $interval) {
                return true;
            }

            // Verify database connection
            global $wpdb;
            if (!$wpdb->check_connection(false)) {
                throw new Exception('Database connection failed');
            }

            // Clear expired transients
            $expired_count = $this->clear_expired_transients();
            
            // Clear WooCommerce transients
            $wc_count = $this->clear_woocommerce_transients();

            // Update last run time
            $new_options = array(
                'last_run' => time(),
                'next_run' => time() + ($this->options['interval'] * DAY_IN_SECONDS),
                'interval' => $this->options['interval'],
                'logging_enabled' => $this->options['logging_enabled']
            );

            if (!$this->update_options($new_options)) {
                throw new Exception('Failed to update plugin options after cleanup');
            }

            // Log the cleanup if logging is enabled
            if ($this->options['logging_enabled']) {
                $this->log_cleanup($expired_count, $wc_count);
            }

            return true;
        } catch (Exception $e) {
            error_log('WooCommerce Transient Cleaner Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Clear expired transients
     * 
     * @return int Number of transients cleared
     */
    private function clear_expired_transients() {
        global $wpdb;
        
        try {
            // Verify database connection
            if (!$wpdb->check_connection(false)) {
                throw new Exception('Database connection failed');
            }

            // Delete expired transients
            $expired = $wpdb->query($wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s AND option_value < %d",
                '%_transient_timeout_%',
                time()
            ));
            
            if ($expired === false) {
                throw new Exception('Failed to delete expired transients: ' . $wpdb->last_error);
            }
            
            // Delete the actual transients
            $transients = $wpdb->query($wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s AND option_name NOT LIKE %s",
                '%_transient_%',
                '%_transient_timeout_%'
            ));
            
            if ($transients === false) {
                throw new Exception('Failed to delete transients: ' . $wpdb->last_error);
            }
            
            return $expired + $transients;
        } catch (Exception $e) {
            error_log('WooCommerce Transient Cleaner Error (clear_expired_transients): ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Clear WooCommerce transients
     * 
     * @return int Number of transients cleared
     */
    private function clear_woocommerce_transients() {
        global $wpdb;
        
        try {
            // Verify database connection
            if (!$wpdb->check_connection(false)) {
                throw new Exception('Database connection failed');
            }

            // Clear WooCommerce transients
            $count = $wpdb->query($wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
                '%_wc_transient_%'
            ));
            
            if ($count === false) {
                throw new Exception('Failed to delete WooCommerce transients: ' . $wpdb->last_error);
            }
            
            // Clear WooCommerce cache
            if (function_exists('wc_cache_flush')) {
                wc_cache_flush();
            }
            
            return $count;
        } catch (Exception $e) {
            error_log('WooCommerce Transient Cleaner Error (clear_woocommerce_transients): ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update next run time
     */
    private function update_next_run_time() {
        $new_options = array(
            'next_run' => $this->options['last_run'] + ($this->options['interval'] * DAY_IN_SECONDS),
            'interval' => $this->options['interval'],
            'last_run' => $this->options['last_run'],
            'logging_enabled' => $this->options['logging_enabled']
        );
        
        return $this->update_options($new_options);
    }

    /**
     * Log cleanup activity
     */
    private function log_cleanup($expired_count = 0, $wc_count = 0) {
        $log_message = sprintf(
            '[%s] WooCommerce Transient Cleaner: Cleaned %d expired transients and %d WooCommerce transients. Next run scheduled for %s.',
            current_time('mysql'),
            $expired_count,
            $wc_count,
            date('Y-m-d H:i:s', $this->options['next_run'])
        );
        
        error_log($log_message);
    }

    /**
     * Add settings link to plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('tools.php?page=woo-transient-cleaner') . '">' . __('Settings', 'woo-transient-cleaner') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Run the plugin
     */
    public function run() {
        // Initialize admin interface
        if (is_admin()) {
            new Woo_Transient_Cleaner_Admin($this);
        }
    }
} 