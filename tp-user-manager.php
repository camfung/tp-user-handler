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

class ShortcodeUserPlugin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        add_action('init', array($this, 'register_shortcodes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_user_signup', array($this, 'handle_user_signup'));
        add_action('wp_ajax_nopriv_user_signup', array($this, 'handle_user_signup'));
        add_action('wp_ajax_user_signin', array($this, 'handle_user_signin'));
        add_action('wp_ajax_nopriv_user_signin', array($this, 'handle_user_signin'));
    }
    
    public function register_shortcodes() {
        add_shortcode('user_signup_form', array($this, 'user_signup_form_shortcode'));
        add_shortcode('user_signin_form', array($this, 'user_signin_form_shortcode'));
        add_shortcode('user_profile', array($this, 'user_profile_shortcode'));
    }
    
    public function enqueue_scripts() {
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
    
    public function user_signup_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'redirect_url' => home_url(),
            'show_labels' => 'true',
            'button_text' => 'Sign Up'
        ), $atts);
        
        ob_start();
        ?>
        <div class="user-signup-form-container">
            <form id="user-signup-form" class="user-form">
                <?php wp_nonce_field('user_plugin_nonce', 'user_plugin_nonce'); ?>
                <input type="hidden" name="redirect_url" value="<?php echo esc_url($atts['redirect_url']); ?>">
                
                <div class="form-group">
                    <?php if ($atts['show_labels'] === 'true') : ?>
                        <label for="signup_username">Username:</label>
                    <?php endif; ?>
                    <input type="text" id="signup_username" name="username" placeholder="Username" required>
                </div>
                
                <div class="form-group">
                    <?php if ($atts['show_labels'] === 'true') : ?>
                        <label for="signup_email">Email:</label>
                    <?php endif; ?>
                    <input type="email" id="signup_email" name="email" placeholder="Email" required>
                </div>
                
                <div class="form-group">
                    <?php if ($atts['show_labels'] === 'true') : ?>
                        <label for="signup_password">Password:</label>
                    <?php endif; ?>
                    <input type="password" id="signup_password" name="password" placeholder="Password" required>
                </div>
                
                <div class="form-group">
                    <?php if ($atts['show_labels'] === 'true') : ?>
                        <label for="signup_first_name">First Name:</label>
                    <?php endif; ?>
                    <input type="text" id="signup_first_name" name="first_name" placeholder="First Name">
                </div>
                
                <div class="form-group">
                    <?php if ($atts['show_labels'] === 'true') : ?>
                        <label for="signup_last_name">Last Name:</label>
                    <?php endif; ?>
                    <input type="text" id="signup_last_name" name="last_name" placeholder="Last Name">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="submit-btn"><?php echo esc_html($atts['button_text']); ?></button>
                </div>
                
                <div id="signup-messages" class="form-messages"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function user_signin_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'redirect_url' => home_url(),
            'show_labels' => 'true',
            'button_text' => 'Sign In'
        ), $atts);
        
        ob_start();
        ?>
        <div class="user-signin-form-container">
            <form id="user-signin-form" class="user-form">
                <?php wp_nonce_field('user_plugin_nonce', 'user_plugin_nonce'); ?>
                <input type="hidden" name="redirect_url" value="<?php echo esc_url($atts['redirect_url']); ?>">
                
                <div class="form-group">
                    <?php if ($atts['show_labels'] === 'true') : ?>
                        <label for="signin_username">Username or Email:</label>
                    <?php endif; ?>
                    <input type="text" id="signin_username" name="username" placeholder="Username or Email" required>
                </div>
                
                <div class="form-group">
                    <?php if ($atts['show_labels'] === 'true') : ?>
                        <label for="signin_password">Password:</label>
                    <?php endif; ?>
                    <input type="password" id="signin_password" name="password" placeholder="Password" required>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="signin_remember" name="remember" value="1">
                        Remember Me
                    </label>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="submit-btn"><?php echo esc_html($atts['button_text']); ?></button>
                </div>
                
                <div id="signin-messages" class="form-messages"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function user_profile_shortcode($atts) {
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
            <?php if ($atts['show_avatar'] === 'true') : ?>
                <div class="user-avatar">
                    <?php echo get_avatar($current_user->ID, $atts['avatar_size']); ?>
                </div>
            <?php endif; ?>
            
            <div class="user-info">
                <h3>Welcome, <?php echo esc_html($current_user->display_name); ?>!</h3>
                <p><strong>Username:</strong> <?php echo esc_html($current_user->user_login); ?></p>
                <p><strong>Email:</strong> <?php echo esc_html($current_user->user_email); ?></p>
                <?php if ($current_user->first_name) : ?>
                    <p><strong>First Name:</strong> <?php echo esc_html($current_user->first_name); ?></p>
                <?php endif; ?>
                <?php if ($current_user->last_name) : ?>
                    <p><strong>Last Name:</strong> <?php echo esc_html($current_user->last_name); ?></p>
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
    
    public function handle_user_signup() {
        if (!wp_verify_nonce($_POST['user_plugin_nonce'], 'user_plugin_nonce')) {
            wp_die('Security check failed');
        }
        
        $username = sanitize_user($_POST['username']);
        $email = sanitize_email($_POST['email']);
        $password = $_POST['password'];
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $redirect_url = esc_url($_POST['redirect_url']);
        
        if (empty($username) || empty($email) || empty($password)) {
            wp_send_json_error('All required fields must be filled.');
        }
        
        if (username_exists($username)) {
            wp_send_json_error('Username already exists.');
        }
        
        if (email_exists($email)) {
            wp_send_json_error('Email already exists.');
        }
        
        $user_data = array(
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => $password,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $first_name ? $first_name . ' ' . $last_name : $username
        );
        
        $user_id = wp_insert_user($user_data);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error($user_id->get_error_message());
        }
        
        do_action('shortcode_user_plugin_user_created', $user_id);
        
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        
        wp_send_json_success(array(
            'message' => 'User created successfully!',
            'redirect_url' => $redirect_url,
            'user_id' => $user_id
        ));
    }
    
    public function handle_user_signin() {
        if (!wp_verify_nonce($_POST['user_plugin_nonce'], 'user_plugin_nonce')) {
            wp_die('Security check failed');
        }
        
        $username = sanitize_user($_POST['username']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']) ? true : false;
        $redirect_url = esc_url($_POST['redirect_url']);
        
        if (empty($username) || empty($password)) {
            wp_send_json_error('Username and password are required.');
        }
        
        $creds = array(
            'user_login' => $username,
            'user_password' => $password,
            'remember' => $remember
        );
        
        $user = wp_signon($creds, false);
        
        if (is_wp_error($user)) {
            wp_send_json_error($user->get_error_message());
        }
        
        do_action('shortcode_user_plugin_user_signed_in', $user->ID);
        
        wp_send_json_success(array(
            'message' => 'Signed in successfully!',
            'redirect_url' => $redirect_url,
            'user_id' => $user->ID
        ));
    }
}

register_activation_hook(__FILE__, 'shortcode_user_plugin_activate');
register_deactivation_hook(__FILE__, 'shortcode_user_plugin_deactivate');

function shortcode_user_plugin_activate() {
    do_action('shortcode_user_plugin_activated');
}

function shortcode_user_plugin_deactivate() {
    do_action('shortcode_user_plugin_deactivated');
}

function shortcode_user_plugin_init() {
    return ShortcodeUserPlugin::get_instance();
}

add_action('plugins_loaded', 'shortcode_user_plugin_init');
