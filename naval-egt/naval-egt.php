<?php
/**
 * Plugin Name: Naval EGT - Area Riservata
 * Plugin URI: https://www.navalegt.it/
 * Description: Plugin per la gestione dell'area riservata clienti Naval EGT con accesso, registrazione e cartella FTP integrata.
 * Version: 1.0.0
 * Author: Scherpmind di Raffaele Borrelli
 * Author URI: https://scherpmind.it
 * Text Domain: naval-egt
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('NAVAL_EGT_VERSION', '1.0.0');
define('NAVAL_EGT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NAVAL_EGT_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Main Naval EGT Plugin Class
 */
class NavalEGTPlugin {

    public function ajax_add_user() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Accesso negato'));
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['naval_egt_add_user_nonce'], 'naval_egt_add_user')) {
        wp_send_json_error(array('message' => 'Errore di sicurezza'));
    }
    
    // Get data
    $first_name = sanitize_text_field($_POST['first_name'] ?? '');
    $last_name = sanitize_text_field($_POST['last_name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $company = sanitize_text_field($_POST['company'] ?? '');
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    $username = sanitize_text_field($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $status = sanitize_text_field($_POST['status'] ?? 'active');
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($company)) {
        wp_send_json_error(array('message' => 'Tutti i campi obbligatori sono richiesti'));
    }
    
    if (!is_email($email)) {
        wp_send_json_error(array('message' => 'Email non valida'));
    }
    
    if (email_exists($email)) {
        wp_send_json_error(array('message' => 'Email già esistente'));
    }
    
    // Generate username if empty
    if (empty($username)) {
        $username = sanitize_user(substr($email, 0, strpos($email, '@')));
        $counter = 1;
        $original = $username;
        while (username_exists($username)) {
            $username = $original . $counter;
            $counter++;
        }
    }
    
    // Generate password if empty
    if (empty($password)) {
        $password = wp_generate_password(12, false);
    }
    
    // Create user
    $user_id = wp_create_user($username, $password, $email);
    
    if (is_wp_error($user_id)) {
        wp_send_json_error(array('message' => 'Errore creazione utente: ' . $user_id->get_error_message()));
    }
    
    // Set role
    $user = new WP_User($user_id);
    $user->set_role('naval_egt_client');
    
    // Save meta
    update_user_meta($user_id, 'first_name', $first_name);
    update_user_meta($user_id, 'last_name', $last_name);
    update_user_meta($user_id, 'naval_egt_company', $company);
    update_user_meta($user_id, 'naval_egt_phone', $phone);
    update_user_meta($user_id, 'naval_egt_status', $status);
    
    // Create FTP folder
    $folder_name = 'client_' . $user_id . '_' . sanitize_title($username);
    update_user_meta($user_id, 'naval_egt_ftp_folder', $folder_name);
    
    wp_send_json_success(array(
        'message' => 'Utente creato con successo!',
        'user_id' => $user_id,
        'username' => $username
    ));
}

public function ajax_list_users() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Accesso negato'));
    }
    
    $users = get_users(array('role' => 'naval_egt_client'));
    $users_data = array();
    
    foreach ($users as $user) {
        $users_data[] = array(
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'company' => get_user_meta($user->ID, 'naval_egt_company', true),
            'status' => get_user_meta($user->ID, 'naval_egt_status', true),
            'registered' => $user->user_registered
        );
    }
    
    wp_send_json_success(array('users' => $users_data));
}
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('naval-egt', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize components
        $this->load_dependencies();
        $this->setup_hooks();
    }
    
    private function load_dependencies() {
        require_once NAVAL_EGT_PLUGIN_PATH . 'includes/class-user-management.php';
        require_once NAVAL_EGT_PLUGIN_PATH . 'includes/class-ftp-manager.php';
        require_once NAVAL_EGT_PLUGIN_PATH . 'includes/class-shortcodes.php';
        require_once NAVAL_EGT_PLUGIN_PATH . 'includes/class-ajax-handlers.php';
    }
    
    private function setup_hooks() {
    // Enqueue scripts and styles
    add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    
    // Add admin menu
    add_action('admin_menu', array($this, 'add_admin_menu'));
    
    // Initialize components
    new NavalEGT_UserManagement();
    new NavalEGT_FTPManager();
    new NavalEGT_Shortcodes(); 
    new NavalEGT_AjaxHandlers();
    
    // FIX: Aggiungi handlers AJAX diretti
    add_action('wp_ajax_naval_egt_add_user', array($this, 'ajax_add_user'));
    add_action('wp_ajax_naval_egt_admin_list_users', array($this, 'ajax_list_users'));
}
    
    public function enqueue_scripts() {
        wp_enqueue_style('naval-egt-style', NAVAL_EGT_PLUGIN_URL . 'assets/css/style.css', array(), NAVAL_EGT_VERSION);
        wp_enqueue_script('naval-egt-script', NAVAL_EGT_PLUGIN_URL . 'assets/js/script.js', array('jquery'), NAVAL_EGT_VERSION, true);
        
        // Localize script for AJAX
        wp_localize_script('naval-egt-script', 'naval_egt_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('naval_egt_nonce'),
            'messages' => array(
                'loading' => __('Caricamento in corso...', 'naval-egt'),
                'error' => __('Si è verificato un errore. Riprova.', 'naval-egt'),
                'success' => __('Operazione completata con successo!', 'naval-egt')
            )
        ));
    }
    
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'naval-egt') !== false || strpos($hook, 'naval_egt') !== false) {
        wp_enqueue_style('naval-egt-admin-style', NAVAL_EGT_PLUGIN_URL . 'assets/css/admin-style.css', array(), NAVAL_EGT_VERSION);
        wp_enqueue_script('naval-egt-admin-script', NAVAL_EGT_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery'), NAVAL_EGT_VERSION, true);
        
        // Localize script with proper nonce
        wp_localize_script('naval-egt-admin-script', 'naval_egt_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('naval_egt_admin'),
            'messages' => array(
                'loading' => __('Caricamento...', 'naval-egt'),
                'error' => __('Errore', 'naval-egt'),
                'success' => __('Successo', 'naval-egt')
            )
        ));
    }
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Naval EGT', 'naval-egt'),
            __('Naval EGT', 'naval-egt'),
            'manage_options',
            'naval-egt',
            array($this, 'admin_page'),
            'dashicons-lock',
            30
        );
        
        add_submenu_page(
            'naval-egt',
            __('Impostazioni', 'naval-egt'),
            __('Impostazioni', 'naval-egt'),
            'manage_options',
            'naval-egt-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'naval-egt',
            __('Utenti Area Riservata', 'naval-egt'),
            __('Utenti', 'naval-egt'),
            'manage_options',
            'naval-egt-users',
            array($this, 'users_page')
        );
    }
    
    public function admin_page() {
        include NAVAL_EGT_PLUGIN_PATH . 'admin/admin-page.php';
    }
    
    public function settings_page() {
        include NAVAL_EGT_PLUGIN_PATH . 'admin/settings-page.php';
    }
    
    public function users_page() {
        include NAVAL_EGT_PLUGIN_PATH . 'admin/users-page.php';
    }
    
    public function activate() {
        $this->create_tables();
        $this->create_roles();
        $this->create_pages();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Clean up if needed
        flush_rewrite_rules();
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table for user sessions
        $table_name = $wpdb->prefix . 'naval_egt_sessions';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            session_token varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            ip_address varchar(45),
            user_agent text,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY session_token (session_token)
        ) $charset_collate;";
        
        // Table for FTP activity logs
        $table_name2 = $wpdb->prefix . 'naval_egt_ftp_logs';
        $sql2 = "CREATE TABLE $table_name2 (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action varchar(50) NOT NULL,
            file_name varchar(255),
            file_path text,
            file_size bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45),
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql2);
    }
    
    private function create_roles() {
        // Create custom role for Naval EGT clients
        add_role('naval_egt_client', __('Cliente Naval EGT', 'naval-egt'), array(
            'read' => true,
            'naval_egt_access' => true,
        ));
    }
    
    private function create_pages() {
        // Create area riservata page if it doesn't exist
        $page_slug = 'area-riservata';
        $page = get_page_by_path($page_slug);
        
        if (!$page) {
            $page_data = array(
                'post_title' => 'Area Riservata',
                'post_content' => '[naval_egt_login_form]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => $page_slug,
                'comment_status' => 'closed',
                'ping_status' => 'closed'
            );
            
            wp_insert_post($page_data);
        }
        
        // Create dashboard page
        $dashboard_slug = 'dashboard-clienti';
        $dashboard_page = get_page_by_path($dashboard_slug);
        
        if (!$dashboard_page) {
            $dashboard_data = array(
                'post_title' => 'Dashboard Clienti',
                'post_content' => '[naval_egt_dashboard]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => $dashboard_slug,
                'comment_status' => 'closed',
                'ping_status' => 'closed'
            );
            
            wp_insert_post($dashboard_data);
        }
    }
}

// Initialize the plugin
new NavalEGTPlugin();

/**
 * Helper function to check if user has access to Naval EGT area
 */
function naval_egt_user_can_access($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return false;
    }
    
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return false;
    }
    
    return in_array('naval_egt_client', $user->roles) || in_array('administrator', $user->roles);
}

/**
 * Helper function to get Naval EGT user data
 */
function naval_egt_get_user_data($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!naval_egt_user_can_access($user_id)) {
        return false;
    }
    
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return false;
    }
    
    return array(
        'id' => $user->ID,
        'username' => $user->user_login,
        'email' => $user->user_email,
        'display_name' => $user->display_name,
        'registered' => $user->user_registered,
        'company' => get_user_meta($user->ID, 'naval_egt_company', true),
        'phone' => get_user_meta($user->ID, 'naval_egt_phone', true),
        'ftp_folder' => get_user_meta($user->ID, 'naval_egt_ftp_folder', true)
    );
}
?>