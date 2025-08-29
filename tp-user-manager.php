<?php
/**
 * Plugin Name: TP User Manager
 * Plugin URI: trafficportal.com
 * Description: A plugin that provides shortcodes for user creation and signin with WordPress Users API integration.
 * Version: 1.0.0
 * Author: Cameron Fung
 * License: GPL v2 or later
 * Text Domain: tp-user-manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ShortcodeUserPlugin
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init();
    }

    private function init()
    {
        add_action('init', array($this, 'register_shortcodes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_login', array($this, 'send_user_data_on_login'), 10, 2);
        add_filter('http_request_args', array($this, 'add_api_token_to_requests'), 10, 2);
        
        // Load additional modules
        $this->load_includes();
    }

    private function load_includes()
    {
        $includes_path = plugin_dir_path(__FILE__) . 'includes/';
        
        if (file_exists($includes_path . 'intro-key-handler.php')) {
            require_once $includes_path . 'intro-key-handler.php';
        }
    }

    public function register_shortcodes()
    {
        add_shortcode('user_profile', array($this, 'user_profile_shortcode'));
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'shortcode-user-plugin-js',
            plugin_dir_url(__FILE__) . 'assets/shortcode-user-plugin.js',
            array('jquery'),
            '1.0.0',
            true
        );
        wp_localize_script('shortcode-user-plugin-js', 'ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('user_plugin_nonce')
        ));

        wp_enqueue_style(
            'shortcode-user-plugin-css',
            plugin_dir_url(__FILE__) . 'assets/shortcode-user-plugin.css',
            array(),
            '1.0.0'
        );
    }


    public function user_profile_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'show_avatar' => 'true',
            'avatar_size' => '96'
        ), $atts);

        if (!is_user_logged_in()) {
            return '<p>Please log in to view your profile.</p>';
        }

        $current_user = wp_get_current_user();

        ob_start();
        ?>
        <div class="user-profile-container">
            <?php if ($atts['show_avatar'] === 'true'): ?>
                <div class="user-avatar">
                    <?php echo get_avatar($current_user->ID, $atts['avatar_size']); ?>
                </div>
            <?php endif; ?>

            <div class="user-info">
                <h3>Welcome, <?php echo esc_html($current_user->display_name); ?>!</h3>
                <p><strong>Username:</strong> <?php echo esc_html($current_user->user_login); ?></p>
                <p><strong>Email:</strong> <?php echo esc_html($current_user->user_email); ?></p>
                <?php if ($current_user->first_name): ?>
                    <p><strong>First Name:</strong> <?php echo esc_html($current_user->first_name); ?></p>
                <?php endif; ?>
                <?php if ($current_user->last_name): ?>
                    <p><strong>Last Name:</strong> <?php echo esc_html($current_user->last_name); ?></p>
                <?php endif; ?>
                <?php if ($current_user->id): ?>
                    <p><strong>id:</strong> <?php echo esc_html($current_user->id); ?></p>
                <?php endif; ?>
                <p><strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($current_user->user_registered)); ?></p>
            </div>

            <div class="user-actions">
                <a href="<?php echo wp_logout_url(); ?>" class="logout-btn">Logout</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* 
     * @param string $user_login The user's login username
     * @param WP_User $user The WP_User object
     */
    public function send_user_data_on_login($user_login, $user)
    {
        // Get the user ID
        $user_id = $user->ID;

        // Prepare the data to send
        $data = array(
            'uid' => $user_id,
            'wpUserId' => $user_id
        );

        // API endpoint
        $api_url = 'https://dev.trfc.link/users';

        // Prepare the request arguments
        $args = array(
            'method' => 'PUT',
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($data),
            'timeout' => 30,
        );

        // Make the API request
        $response = wp_remote_request($api_url, $args);

        // Optional: Log the response for debugging
        if (is_wp_error($response)) {
            error_log('API request failed: ' . $response->get_error_message());
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            error_log("API request completed. Status: {$response_code}, Response: {$response_body}");
        }
    }

    public function add_api_token_to_requests($args, $url)
    {
        if (strpos($url, 'dev.trfc.link') !== false) {
            if (!isset($args['headers'])) {
                $args['headers'] = array();
            }
            $args['headers']['X-API-Key'] = $_ENV['API_KEY'] ?? '';
        }
        
        return $args;
    }
}

register_activation_hook(__FILE__, 'shortcode_user_plugin_activate');
register_deactivation_hook(__FILE__, 'shortcode_user_plugin_deactivate');

function shortcode_user_plugin_activate()
{
    do_action('shortcode_user_plugin_activated');
}

function shortcode_user_plugin_deactivate()
{
    do_action('shortcode_user_plugin_deactivated');
}

function shortcode_user_plugin_init()
{
    return ShortcodeUserPlugin::get_instance();
}

add_action('plugins_loaded', 'shortcode_user_plugin_init');
