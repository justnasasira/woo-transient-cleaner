<?php

class Woo_Transient_Cleaner_Admin {
    /**
     * The main plugin instance
     *
     * @var Woo_Transient_Cleaner
     */
    private $plugin;

    /**
     * Initialize the admin interface
     *
     * @param Woo_Transient_Cleaner $plugin The main plugin instance
     */
    public function __construct($plugin) {
        $this->plugin = $plugin;
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add AJAX handlers
        add_action('wp_ajax_wtc_manual_cleanup', array($this, 'handle_manual_cleanup'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_management_page(
            __('WooCommerce Transient Cleaner', 'woo-transient-cleaner'),
            __('WC Transient Cleaner', 'woo-transient-cleaner'),
            'manage_options',
            'woo-transient-cleaner',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('wtc_options', 'wtc_options', array($this, 'sanitize_options'));
    }

    /**
     * Sanitize options
     */
    public function sanitize_options($input) {
        try {
            $sanitized = array();
            
            // Sanitize interval
            $sanitized['interval'] = absint($input['interval']);
            if ($sanitized['interval'] < 1) {
                $sanitized['interval'] = 3;
            }
            
            // Preserve existing values for last_run and next_run
            $existing_options = get_option('wtc_options', array());
            $sanitized['last_run'] = isset($existing_options['last_run']) ? absint($existing_options['last_run']) : 0;
            $sanitized['next_run'] = isset($existing_options['next_run']) ? absint($existing_options['next_run']) : 0;
            
            // Sanitize logging enabled
            $sanitized['logging_enabled'] = isset($input['logging_enabled']) ? true : false;

            // Update the options
            $update_result = update_option('wtc_options', $sanitized);
            
            if ($update_result === false) {
                // If update failed, try to add the option
                if (get_option('wtc_options') === false) {
                    $update_result = add_option('wtc_options', $sanitized);
                }
            }

            if ($update_result === false) {
                add_settings_error(
                    'wtc_options',
                    'wtc_options_update_failed',
                    __('Failed to save settings. Please try again.', 'woo-transient-cleaner'),
                    'error'
                );
            } else {
                add_settings_error(
                    'wtc_options',
                    'wtc_options_updated',
                    __('Settings saved successfully.', 'woo-transient-cleaner'),
                    'success'
                );
            }
            
            return $sanitized;
        } catch (Exception $e) {
            error_log('WooCommerce Transient Cleaner Error (sanitize_options): ' . $e->getMessage());
            add_settings_error(
                'wtc_options',
                'wtc_options_error',
                __('An error occurred while saving settings.', 'woo-transient-cleaner'),
                'error'
            );
            return $input;
        }
    }

    /**
     * Handle manual cleanup via AJAX
     */
    public function handle_manual_cleanup() {
        try {
            // Check nonce and capabilities
            if (!check_ajax_referer('wtc_manual_cleanup', 'nonce', false)) {
                throw new Exception('Security check failed. Please refresh the page and try again.');
            }
            
            if (!current_user_can('manage_options')) {
                throw new Exception('You do not have permission to perform this action.');
            }

            // Verify WooCommerce is active
            if (!class_exists('WooCommerce')) {
                throw new Exception('WooCommerce is not active. Please activate WooCommerce first.');
            }

            // Perform cleanup
            $result = $this->plugin->cleanup_transients();
            
            if ($result !== true) {
                throw new Exception('Cleanup process did not complete successfully.');
            }
            
            // Get updated options
            $options = get_option('wtc_options');
            if ($options === false) {
                throw new Exception('Failed to retrieve updated options.');
            }
            
            wp_send_json_success(array(
                'message' => __('Transients cleaned successfully!', 'woo-transient-cleaner'),
                'last_run' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $options['last_run']),
                'next_run' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $options['next_run'])
            ));
        } catch (Exception $e) {
            // Log the error
            error_log('WooCommerce Transient Cleaner Error: ' . $e->getMessage());
            
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'woo-transient-cleaner'));
        }

        // Verify WooCommerce is active
        if (!class_exists('WooCommerce')) {
            echo '<div class="notice notice-error"><p>' . 
                 __('WooCommerce is not active. This plugin requires WooCommerce to be installed and activated.', 'woo-transient-cleaner') . 
                 '</p></div>';
            return;
        }

        // Get current options
        $options = get_option('wtc_options');
        $last_run = $options['last_run'] ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $options['last_run']) : __('Never', 'woo-transient-cleaner');
        $next_run = $options['next_run'] ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $options['next_run']) : __('Not scheduled', 'woo-transient-cleaner');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="card">
                <h2><?php _e('Status', 'woo-transient-cleaner'); ?></h2>
                <p>
                    <strong><?php _e('Last Run:', 'woo-transient-cleaner'); ?></strong>
                    <span id="wtc-last-run"><?php echo esc_html($last_run); ?></span>
                </p>
                <p>
                    <strong><?php _e('Next Scheduled Run:', 'woo-transient-cleaner'); ?></strong>
                    <span id="wtc-next-run"><?php echo esc_html($next_run); ?></span>
                </p>
                <p>
                    <button type="button" class="button button-primary" id="wtc-manual-cleanup">
                        <?php _e('Clean Transients Now', 'woo-transient-cleaner'); ?>
                    </button>
                    <span class="spinner" style="float: none; margin-top: 4px;"></span>
                </p>
                <div id="wtc-message" style="margin-top: 10px;"></div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('wtc_options'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wtc-interval"><?php _e('Cleanup Interval (days)', 'woo-transient-cleaner'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="wtc-interval" name="wtc_options[interval]" 
                                   value="<?php echo esc_attr($options['interval']); ?>" 
                                   min="1" max="30" step="1" />
                            <p class="description">
                                <?php _e('How often should the plugin clean transients? (1-30 days)', 'woo-transient-cleaner'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('Enable Logging', 'woo-transient-cleaner'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="wtc_options[logging_enabled]" 
                                       value="1" <?php checked($options['logging_enabled']); ?> />
                                <?php _e('Log cleanup activities to the WordPress debug log', 'woo-transient-cleaner'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#wtc-manual-cleanup').on('click', function() {
                var $button = $(this);
                var $spinner = $button.next('.spinner');
                var $message = $('#wtc-message');
                
                $button.prop('disabled', true);
                $spinner.addClass('is-active');
                $message.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wtc_manual_cleanup',
                        nonce: '<?php echo wp_create_nonce('wtc_manual_cleanup'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#wtc-last-run').text(response.data.last_run);
                            $('#wtc-next-run').text(response.data.next_run);
                            $message.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                        } else {
                            $message.html('<div class="notice notice-error"><p>Error: ' + (response.data ? response.data.message : 'Could not clean transients.') + '</p></div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        var errorMessage = 'An error occurred while processing your request.';
                        if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                            errorMessage = xhr.responseJSON.data.message;
                        } else if (error) {
                            errorMessage = error;
                        }
                        $message.html('<div class="notice notice-error"><p>Error: ' + errorMessage + '</p></div>');
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                        $spinner.removeClass('is-active');
                    }
                });
            });
        });
        </script>
        <?php
    }
} 