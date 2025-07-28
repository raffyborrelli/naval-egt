<?php
/**
 * Plugin Name: Naval EGT - Area Riservata
 * Plugin URI: https://www.navalegt.it/
 * Description: Plugin per la gestione dell'area riservata clienti Naval EGT con accesso, registrazione e cartella FTP integrata.
 * Version: 1.0.2
 * Author: Scherpmind di Raffaele Borrelli
 * Author URI: https://scherpmind.com
 * Text Domain: naval-egt
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('NAVAL_EGT_VERSION', '1.0.2');
define('NAVAL_EGT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NAVAL_EGT_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Main Naval EGT Plugin Class
 */
class NavalEGTPlugin {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('template_redirect', array($this, 'handle_custom_actions'));
        
        // AJAX Actions - Admin
        add_action('wp_ajax_naval_egt_add_user', array($this, 'ajax_add_user'));
        add_action('wp_ajax_naval_egt_admin_list_users', array($this, 'ajax_list_users'));
        add_action('wp_ajax_naval_egt_admin_update_user_status', array($this, 'ajax_update_user_status'));
        add_action('wp_ajax_naval_egt_admin_delete_user', array($this, 'ajax_delete_user'));
        add_action('wp_ajax_naval_egt_admin_upload_file_for_user', array($this, 'ajax_upload_file_for_user'));
        add_action('wp_ajax_naval_egt_admin_get_logs', array($this, 'ajax_get_logs'));
        add_action('wp_ajax_naval_egt_get_user_data', array($this, 'ajax_get_user_data'));
        add_action('wp_ajax_naval_egt_update_user', array($this, 'ajax_update_user'));
        
        // AJAX Actions - Frontend
        add_action('wp_ajax_naval_egt_download_file', array($this, 'ajax_download_file'));
        add_action('wp_ajax_naval_egt_refresh_user_files', array($this, 'ajax_refresh_user_files'));
        
        // Export actions
        add_action('wp_ajax_naval_egt_export_users', array($this, 'export_users'));
        add_action('wp_ajax_naval_egt_export_logs', array($this, 'export_logs'));
        
        // Shortcodes
        add_shortcode('naval_egt_login_form', array($this, 'login_form_shortcode'));
        add_shortcode('naval_egt_dashboard', array($this, 'dashboard_shortcode'));
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        load_plugin_textdomain('naval-egt', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        if (!session_id()) {
            session_start();
        }
        
        $this->ensure_ftp_directory();
    }
    
    private function ensure_ftp_directory() {
        $upload_dir = wp_upload_dir();
        $ftp_base = $upload_dir['basedir'] . '/naval-egt-ftp/';
        
        if (!file_exists($ftp_base)) {
            wp_mkdir_p($ftp_base);
            file_put_contents($ftp_base . '.htaccess', "Options -Indexes\nDeny from all\n");
        }
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('naval-egt-script', NAVAL_EGT_PLUGIN_URL . 'assets/js/script.js', array('jquery'), NAVAL_EGT_VERSION, true);
        wp_enqueue_style('naval-egt-style', NAVAL_EGT_PLUGIN_URL . 'assets/css/style.css', array(), NAVAL_EGT_VERSION);
        
        wp_localize_script('naval-egt-script', 'naval_egt_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('naval_egt_nonce'),
        ));
    }
    
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'naval-egt') !== false) {
            wp_enqueue_script('naval-egt-admin-script', NAVAL_EGT_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery'), NAVAL_EGT_VERSION, true);
            wp_enqueue_style('naval-egt-admin-style', NAVAL_EGT_PLUGIN_URL . 'assets/css/admin-style.css', array(), NAVAL_EGT_VERSION);
            
            wp_localize_script('naval-egt-admin-script', 'naval_egt_admin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('naval_egt_admin'),
            ));
        }
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Naval EGT',
            'Naval EGT',
            'manage_options',
            'naval-egt',
            array($this, 'admin_page'),
            'dashicons-lock',
            30
        );
        
        add_submenu_page(
            'naval-egt',
            'Impostazioni',
            'Impostazioni',
            'manage_options',
            'naval-egt-settings',
            array($this, 'settings_page')
        );
    }
    
    public function admin_page() {
        global $wpdb;
        
        $total_users = count(get_users(array('role' => 'naval_egt_client')));
        $active_users = count(get_users(array(
            'role' => 'naval_egt_client',
            'meta_query' => array(
                array(
                    'key' => 'naval_egt_status',
                    'value' => 'active',
                    'compare' => '='
                )
            )
        )));
        
        $logs_table = $wpdb->prefix . 'naval_egt_ftp_logs';
        $total_files = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE action = 'upload'") ?: 0;
        
        $recent_activity = $wpdb->get_results("
            SELECT l.*, u.display_name, u.user_email 
            FROM $logs_table l 
            LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
            ORDER BY l.created_at DESC 
            LIMIT 10
        ");
        
        include NAVAL_EGT_PLUGIN_PATH . 'admin/admin-page.php';
    }
    
    public function settings_page() {
        echo '<div class="wrap"><h1>Impostazioni Naval EGT</h1><p>Pagina delle impostazioni in sviluppo.</p></div>';
    }
    
    // AJAX METHODS
    
    public function ajax_list_users() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'naval_egt_admin')) {
            wp_send_json_error(array('message' => 'Accesso negato'));
        }
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        $status = sanitize_text_field($_POST['status'] ?? '');
        $per_page = intval($_POST['per_page'] ?? 20);
        
        $args = array(
            'role' => 'naval_egt_client',
            'number' => $per_page,
            'orderby' => 'registered',
            'order' => 'DESC'
        );
        
        if (!empty($search)) {
            $args['search'] = '*' . $search . '*';
        }
        
        $users = get_users($args);
        $users_data = array();
        
        foreach ($users as $user) {
            $user_status = get_user_meta($user->ID, 'naval_egt_status', true) ?: 'active';
            
            if (!empty($status) && $user_status !== $status) {
                continue;
            }
            
            $users_data[] = array(
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name,
                'company' => get_user_meta($user->ID, 'naval_egt_company', true),
                'phone' => get_user_meta($user->ID, 'naval_egt_phone', true),
                'status' => $user_status,
                'registered' => $user->user_registered,
                'ftp_folder' => get_user_meta($user->ID, 'naval_egt_ftp_folder', true)
            );
        }
        
        wp_send_json_success(array('users' => $users_data));
    }
    
    public function ajax_add_user() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'naval_egt_admin')) {
            wp_send_json_error(array('message' => 'Accesso negato'));
        }
        
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $email = sanitize_email($_POST['email']);
        $company = sanitize_text_field($_POST['company']);
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $username = sanitize_text_field($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $status = sanitize_text_field($_POST['status'] ?? 'active');
        
        // Validation
        if (empty($first_name) || empty($last_name) || empty($email) || empty($company)) {
            wp_send_json_error(array('message' => 'Tutti i campi obbligatori devono essere compilati'));
        }
        
        if (!is_email($email) || email_exists($email)) {
            wp_send_json_error(array('message' => 'Email non valida o già esistente'));
        }
        
        // Generate username if not provided
        if (empty($username)) {
            $username = $this->generate_username($email);
        } elseif (username_exists($username)) {
            wp_send_json_error(array('message' => 'Username già in uso'));
        }
        
        // Generate password if not provided
        if (empty($password)) {
            $password = wp_generate_password(12, false);
        }
        
        // Create user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => 'Errore durante la creazione utente: ' . $user_id->get_error_message()));
        }
        
        // Set role and data
        $user = new WP_User($user_id);
        $user->set_role('naval_egt_client');
        
        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        update_user_meta($user_id, 'naval_egt_company', $company);
        update_user_meta($user_id, 'naval_egt_phone', $phone);
        update_user_meta($user_id, 'naval_egt_status', $status);
        
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $first_name . ' ' . $last_name
        ));
        
        // Create FTP folder
        $this->create_user_ftp_folder($user_id);
        
        // Send welcome email
        $this->send_welcome_email($user_id, $password);
        
        wp_send_json_success(array(
            'message' => 'Utente creato con successo',
            'user_id' => $user_id,
            'username' => $username
        ));
    }
    
    public function ajax_get_user_data() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'naval_egt_admin')) {
            wp_send_json_error(array('message' => 'Accesso negato'));
        }
        
        $user_id = intval($_POST['user_id']);
        $user = get_user_by('id', $user_id);
        
        if (!$user || !in_array('naval_egt_client', $user->roles)) {
            wp_send_json_error(array('message' => 'Utente non trovato'));
        }
        
        wp_send_json_success(array(
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'first_name' => get_user_meta($user->ID, 'first_name', true),
            'last_name' => get_user_meta($user->ID, 'last_name', true),
            'company' => get_user_meta($user->ID, 'naval_egt_company', true),
            'phone' => get_user_meta($user->ID, 'naval_egt_phone', true),
            'status' => get_user_meta($user->ID, 'naval_egt_status', true) ?: 'active',
            'ftp_folder' => get_user_meta($user->ID, 'naval_egt_ftp_folder', true)
        ));
    }
    
    public function ajax_update_user() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'naval_egt_admin')) {
            wp_send_json_error(array('message' => 'Accesso negato'));
        }
        
        $user_id = intval($_POST['user_id']);
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            wp_send_json_error(array('message' => 'Utente non trovato'));
        }
        
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $email = sanitize_email($_POST['email']);
        $company = sanitize_text_field($_POST['company']);
        $phone = sanitize_text_field($_POST['phone']);
        $status = sanitize_text_field($_POST['status']);
        
        // Check email uniqueness
        if ($email !== $user->user_email) {
            $existing_user = get_user_by('email', $email);
            if ($existing_user && $existing_user->ID !== $user_id) {
                wp_send_json_error(array('message' => 'Email già in uso'));
            }
        }
        
        // Update user
        wp_update_user(array(
            'ID' => $user_id,
            'user_email' => $email,
            'display_name' => $first_name . ' ' . $last_name
        ));
        
        // Update meta
        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        update_user_meta($user_id, 'naval_egt_company', $company);
        update_user_meta($user_id, 'naval_egt_phone', $phone);
        update_user_meta($user_id, 'naval_egt_status', $status);
        
        wp_send_json_success(array('message' => 'Utente aggiornato con successo'));
    }
    
    public function ajax_update_user_status() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'naval_egt_admin')) {
            wp_send_json_error(array('message' => 'Accesso negato'));
        }
        
        $user_id = intval($_POST['user_id']);
        $status = sanitize_text_field($_POST['status']);
        
        if (!in_array($status, array('active', 'pending', 'suspended'))) {
            wp_send_json_error(array('message' => 'Status non valido'));
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user || !in_array('naval_egt_client', $user->roles)) {
            wp_send_json_error(array('message' => 'Utente non trovato'));
        }
        
        update_user_meta($user_id, 'naval_egt_status', $status);
        wp_send_json_success(array('message' => 'Status aggiornato'));
    }
    
    public function ajax_delete_user() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'naval_egt_admin')) {
            wp_send_json_error(array('message' => 'Accesso negato'));
        }
        
        $user_id = intval($_POST['user_id']);
        $user = get_user_by('id', $user_id);
        
        if (!$user || !in_array('naval_egt_client', $user->roles)) {
            wp_send_json_error(array('message' => 'Utente non trovato'));
        }
        
        // Delete FTP folder
        $ftp_folder = get_user_meta($user_id, 'naval_egt_ftp_folder', true);
        if ($ftp_folder) {
            $upload_dir = wp_upload_dir();
            $folder_path = $upload_dir['basedir'] . '/naval-egt-ftp/' . $ftp_folder;
            if (is_dir($folder_path)) {
                $this->delete_directory_recursive($folder_path);
            }
        }
        
        // Delete logs
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'naval_egt_ftp_logs', array('user_id' => $user_id));
        
        // Delete user
        if (wp_delete_user($user_id)) {
            wp_send_json_success(array('message' => 'Utente eliminato'));
        } else {
            wp_send_json_error(array('message' => 'Errore eliminazione'));
        }
    }
    
    public function ajax_upload_file_for_user() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'naval_egt_admin')) {
            wp_send_json_error(array('message' => 'Accesso negato'));
        }
        
        $user_id = intval($_POST['user_id']);
        $subfolder = sanitize_text_field($_POST['subfolder'] ?? 'downloads/');
        
        if (empty($user_id) || empty($_FILES['file'])) {
            wp_send_json_error(array('message' => 'Parametri mancanti'));
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user || !in_array('naval_egt_client', $user->roles)) {
            wp_send_json_error(array('message' => 'Utente non trovato'));
        }
        
        $folder_name = get_user_meta($user_id, 'naval_egt_ftp_folder', true);
        if (!$folder_name) {
            $folder_name = $this->create_user_ftp_folder($user_id);
        }
        
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/naval-egt-ftp/' . $folder_name . '/' . $subfolder;
        
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        
        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => 'Errore upload file'));
        }
        
        $file_name = sanitize_file_name($file['name']);
        $target_file = $target_dir . $file_name;
        
        // Make unique
        $counter = 1;
        $original_name = pathinfo($file_name, PATHINFO_FILENAME);
        $extension = pathinfo($file_name, PATHINFO_EXTENSION);
        
        while (file_exists($target_file)) {
            $file_name = $original_name . '_' . $counter . '.' . $extension;
            $target_file = $target_dir . $file_name;
            $counter++;
        }
        
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            $this->log_file_action($user_id, 'admin_upload', $file_name, $subfolder . $file_name, $file['size']);
            $this->notify_user_new_files($user, array($file_name));
            wp_send_json_success(array('message' => 'File caricato: ' . $file_name));
        } else {
            wp_send_json_error(array('message' => 'Errore durante il caricamento'));
        }
    }
    
    public function ajax_get_logs() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'], 'naval_egt_admin')) {
            wp_send_json_error(array('message' => 'Accesso negato'));
        }
        
        global $wpdb;
        $per_page = intval($_POST['per_page'] ?? 50);
        $table_name = $wpdb->prefix . 'naval_egt_ftp_logs';
        
        $logs = $wpdb->get_results($wpdb->prepare("
            SELECT l.*, u.display_name, u.user_email 
            FROM $table_name l 
            LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
            ORDER BY l.created_at DESC 
            LIMIT %d
        ", $per_page));
        
        wp_send_json_success(array('logs' => $logs));
    }
    
    // Frontend AJAX
    public function ajax_download_file() {
        if (!wp_verify_nonce($_GET['nonce'], 'naval_egt_nonce') || !is_user_logged_in() || !naval_egt_user_can_access()) {
            wp_die('Accesso negato');
        }
        
        $file_path = sanitize_text_field($_GET['file']);
        $user_id = get_current_user_id();
        
        $folder_name = get_user_meta($user_id, 'naval_egt_ftp_folder', true);
        if (!$folder_name) {
            wp_die('Cartella non trovata');
        }
        
        $upload_dir = wp_upload_dir();
        $full_path = $upload_dir['basedir'] . '/naval-egt-ftp/' . $folder_name . '/' . $file_path;
        
        if (!file_exists($full_path) || !is_file($full_path)) {
            wp_die('File non trovato');
        }
        
        $this->log_file_action($user_id, 'download', basename($file_path), $file_path, filesize($full_path));
        
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($full_path) . '"');
        header('Content-Length: ' . filesize($full_path));
        readfile($full_path);
        exit;
    }
    
    public function ajax_refresh_user_files() {
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_nonce') || !is_user_logged_in() || !naval_egt_user_can_access()) {
            wp_send_json_error(array('message' => 'Accesso negato'));
        }
        
        $user_id = get_current_user_id();
        $files = $this->get_user_files($user_id);
        
        wp_send_json_success(array('files' => $files));
    }
    
    // Export Methods
    public function export_users() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_GET['nonce'], 'naval_egt_admin')) {
            wp_die('Accesso negato');
        }
        
        $users = get_users(array('role' => 'naval_egt_client'));
        $filename = 'naval-egt-users-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        fputcsv($output, array('ID', 'Nome', 'Email', 'Azienda', 'Telefono', 'Status', 'Registrato'));
        
        foreach ($users as $user) {
            fputcsv($output, array(
                $user->ID,
                $user->display_name,
                $user->user_email,
                get_user_meta($user->ID, 'naval_egt_company', true),
                get_user_meta($user->ID, 'naval_egt_phone', true),
                get_user_meta($user->ID, 'naval_egt_status', true) ?: 'active',
                $user->user_registered
            ));
        }
        
        fclose($output);
        exit;
    }
    
    public function export_logs() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_GET['nonce'], 'naval_egt_admin')) {
            wp_die('Accesso negato');
        }
        
        global $wpdb;
        $logs = $wpdb->get_results("
            SELECT l.*, u.display_name, u.user_email 
            FROM {$wpdb->prefix}naval_egt_ftp_logs l 
            LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
            ORDER BY l.created_at DESC 
            LIMIT 1000
        ");
        
        $filename = 'naval-egt-logs-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        fputcsv($output, array('Data', 'Utente', 'Email', 'Azione', 'File', 'Dimensione', 'IP'));
        
        foreach ($logs as $log) {
            fputcsv($output, array(
                $log->created_at,
                $log->display_name ?: 'Utente eliminato',
                $log->user_email ?: '',
                $log->action,
                $log->file_name ?: '',
                $log->file_size ?: '',
                $log->ip_address ?: ''
            ));
        }
        
        fclose($output);
        exit;
    }
    
    // SHORTCODES
    
    public function login_form_shortcode($atts) {
        if (is_user_logged_in() && naval_egt_user_can_access()) {
            wp_redirect(home_url('/dashboard-clienti/'));
            exit;
        }
        
        ob_start();
        $this->display_notices();
        ?>
        
        <div class="naval-egt-area-riservata">
            <div class="naval-egt-header">
                <h2>🔐 AREA RISERVATA NAVAL EGT</h2>
                <p>Accedi con le tue credenziali per utilizzare l'area riservata dedicata ai clienti Naval EGT</p>
            </div>
            
            <div style="max-width: 500px; margin: 0 auto;">
                <div style="background: white; padding: 40px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
                    <h3 style="text-align: center; color: #2a5298; margin-bottom: 30px;">🚪 Accedi al tuo account</h3>
                    
                    <form method="post" action="<?php echo esc_url(add_query_arg('naval_action', 'login')); ?>">
                        <?php wp_nonce_field('naval_egt_login', 'naval_egt_nonce'); ?>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px;">Email o Username</label>
                            <input type="text" name="username" required 
                                   style="width: 100%; padding: 15px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px; box-sizing: border-box;" />
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px;">Password</label>
                            <input type="password" name="password" required 
                                   style="width: 100%; padding: 15px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px; box-sizing: border-box;" />
                        </div>
                        
                        <div style="margin-bottom: 25px;">
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" name="remember" style="margin-right: 10px;" />
                                Ricordami per i prossimi accessi
                            </label>
                        </div>
                        
                        <button type="submit" style="width: 100%; padding: 18px; background: linear-gradient(135deg, #2a5298, #1e3c72); color: white; border: none; border-radius: 10px; font-size: 18px; font-weight: 600; cursor: pointer; transition: transform 0.2s;">
                            🔑 Accedi all'Area Riservata
                        </button>
                    </form>
                    
                    <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                        <p style="margin: 0; color: #666; font-size: 14px;">
                            <strong>🔒 Account sicuro:</strong> Non hai un account? Contatta il nostro staff per la registrazione
                        </p>
                    </div>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 40px; padding: 25px; background: #f8f9fa; border-radius: 10px; border-left: 4px solid #2a5298;">
                <h4 style="margin: 0 0 10px 0; color: #2a5298;">🆘 Assistenza Tecnica</h4>
                <p style="margin: 5px 0;">
                    <strong>Email:</strong> <a href="mailto:technical@navalegt.it" style="color: #2a5298;">technical@navalegt.it</a>
                </p>
                <p style="margin: 5px 0; font-size: 14px; color: #666;">
                    Per problemi di accesso, richieste di registrazione o supporto tecnico
                </p>
            </div>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    public function dashboard_shortcode($atts) {
        if (!is_user_logged_in() || !naval_egt_user_can_access()) {
            return '<p>Devi effettuare il login. <a href="' . home_url('/area-riservata/') . '">Clicca qui</a></p>';
        }
        
        $user_data = naval_egt_get_user_data();
        $user_files = $this->get_user_files(get_current_user_id());
        
        ob_start();
        ?>
        
        <div class="naval-egt-dashboard">
            <div style="background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; padding: 30px; border-radius: 15px; margin-bottom: 30px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                    <div>
                        <h2 style="margin: 0; font-size: 2em;">👋 Benvenuto, <?php echo esc_html($user_data['display_name']); ?></h2>
                        <p style="margin: 10px 0 0 0; opacity: 0.9;">La tua area riservata Naval EGT</p>
                    </div>
                    <div>
                        <button onclick="refreshUserFiles()" style="background: rgba(255,255,255,0.2); color: white; padding: 8px 16px; border: none; border-radius: 6px; margin-right: 10px; cursor: pointer;">
                            🔄 Aggiorna
                        </button>
                        <a href="<?php echo esc_url(add_query_arg('naval_action', 'logout', home_url('/area-riservata/'))); ?>" 
                           style="background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none;">
                            🚪 Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
                <!-- File Area -->
                <div>
                    <div style="background: white; padding: 25px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); margin-bottom: 20px;">
                        <h3 style="margin: 0 0 20px 0; color: #2a5298;">📁 I Tuoi File</h3>
                        
                        <div id="user-files-list">
                            <?php if (!empty($user_files)): ?>
                                <?php foreach ($user_files as $file): ?>
                                    <div style="display: flex; align-items: center; padding: 15px; border: 1px solid #e1e5e9; border-radius: 10px; margin-bottom: 10px;">
                                        <div style="font-size: 2em; margin-right: 15px;">📄</div>
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600;"><?php echo esc_html($file['name']); ?></div>
                                            <div style="color: #666; font-size: 0.9em;">
                                                <?php echo size_format($file['size']); ?> - 
                                                <?php echo date('d/m/Y H:i', $file['date']); ?>
                                            </div>
                                        </div>
                                        <a href="<?php echo esc_url($this->get_download_url($file['path'])); ?>" 
                                           style="background: #28a745; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none;">
                                            ⬇️ Scarica
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 40px; color: #666;">
                                    📭 Nessun file disponibile
                                    <p><small>I file condivisi da Naval EGT appariranno qui</small></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Sidebar -->
                <div>
                    <div style="background: white; padding: 25px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
                        <h3 style="margin: 0 0 20px 0; color: #2a5298;">👤 Il Tuo Profilo</h3>
                        
                        <div style="space-y: 10px;">
                            <p><strong>Nome:</strong> <?php echo esc_html($user_data['display_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo esc_html($user_data['email']); ?></p>
                            <?php if ($user_data['company']): ?>
                                <p><strong>Azienda:</strong> <?php echo esc_html($user_data['company']); ?></p>
                            <?php endif; ?>
                            <?php if ($user_data['phone']): ?>
                                <p><strong>Telefono:</strong> <?php echo esc_html($user_data['phone']); ?></p>
                            <?php endif; ?>
                            <p><strong>Registrato:</strong> <?php echo date_i18n('d/m/Y', strtotime($user_data['registered'])); ?></p>
                        </div>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 15px; margin-top: 20px; text-align: center;">
                        <h4 style="margin: 0 0 10px 0;">🆘 Assistenza</h4>
                        <p style="margin: 0;"><a href="mailto:technical@navalegt.it">technical@navalegt.it</a></p>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        function refreshUserFiles() {
            jQuery.ajax({
                url: naval_egt_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'naval_egt_refresh_user_files',
                    nonce: naval_egt_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    }
                }
            });
        }
        </script>
        
        <?php
        return ob_get_clean();
    }
    
    // CUSTOM ACTIONS HANDLING
    
    public function handle_custom_actions() {
        if (!isset($_GET['naval_action'])) {
            return;
        }
        
        $action = sanitize_text_field($_GET['naval_action']);
        
        switch ($action) {
            case 'login':
                $this->handle_login();
                break;
            case 'logout':
                $this->handle_logout();
                break;
        }
    }
    
    private function handle_login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        if (!wp_verify_nonce($_POST['naval_egt_nonce'], 'naval_egt_login')) {
            wp_die('Errore di sicurezza');
        }
        
        $username = sanitize_text_field($_POST['username']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);
        
        $credentials = array(
            'user_login' => $username,
            'user_password' => $password,
            'remember' => $remember
        );
        
        $user = wp_signon($credentials);
        
        if (is_wp_error($user)) {
            $this->add_notice('Credenziali non valide.', 'error');
            return;
        }
        
        if (!naval_egt_user_can_access($user->ID)) {
            wp_logout();
            $this->add_notice('Non hai i permessi per accedere.', 'error');
            return;
        }
        
        wp_redirect(home_url('/dashboard-clienti/'));
        exit;
    }
    
    private function handle_logout() {
        wp_logout();
        wp_redirect(home_url('/area-riservata/'));
        exit;
    }
    
    // HELPER METHODS
    
    private function generate_username($email) {
        $username = sanitize_user(substr($email, 0, strpos($email, '@')));
        $counter = 1;
        $original = $username;
        
        while (username_exists($username)) {
            $username = $original . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    private function create_user_ftp_folder($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) return false;
        
        $upload_dir = wp_upload_dir();
        $base_path = $upload_dir['basedir'] . '/naval-egt-ftp/';
        
        if (!file_exists($base_path)) {
            wp_mkdir_p($base_path);
        }
        
        $folder_name = 'client_' . $user_id . '_' . sanitize_title($user->user_login);
        $folder_paths = array(
            $base_path . $folder_name . '/',
            $base_path . $folder_name . '/downloads/',
            $base_path . $folder_name . '/uploads/',
            $base_path . $folder_name . '/shared/'
        );
        
        foreach ($folder_paths as $path) {
            if (!file_exists($path)) {
                wp_mkdir_p($path);
                file_put_contents($path . '.htaccess', "Options -Indexes\n");
            }
        }
        
        // README file
        $readme = "Cartella FTP per " . $user->display_name . "\n";
        $readme .= "Creata il: " . date('d/m/Y H:i:s') . "\n\n";
        $readme .= "downloads/ = File condivisi da Naval EGT\n";
        $readme .= "uploads/ = I tuoi file caricati\n";
        $readme .= "shared/ = File condivisi bidirezionali\n";
        file_put_contents($base_path . $folder_name . '/README.txt', $readme);
        
        update_user_meta($user_id, 'naval_egt_ftp_folder', $folder_name);
        
        return $folder_name;
    }
    
    private function send_welcome_email($user_id, $password) {
        $user = get_user_by('id', $user_id);
        if (!$user) return false;
        
        $site_name = get_bloginfo('name');
        $domain = parse_url(home_url(), PHP_URL_HOST);
        
        $to = $user->user_email;
        $subject = 'Benvenuto nell\'Area Riservata Naval EGT - Credenziali di Accesso';
        
        $message = "Ciao " . $user->display_name . ",\n\n";
        $message .= "Benvenuto nell'Area Riservata Naval EGT!\n\n";
        $message .= "Il tuo account è stato creato con successo. Ecco i tuoi dati di accesso:\n\n";
        $message .= "🔐 DATI DI ACCESSO:\n";
        $message .= "- Email: " . $user->user_email . "\n";
        $message .= "- Username: " . $user->user_login . "\n";
        $message .= "- Password: " . $password . "\n\n";
        $message .= "🌐 LINK DI ACCESSO:\n";
        $message .= home_url('/area-riservata/') . "\n\n";
        $message .= "📁 COSA PUOI FARE:\n";
        $message .= "- Accedere alla tua area riservata personale\n";
        $message .= "- Visualizzare e scaricare i file che Naval EGT condivide con te\n";
        $message .= "- Gestire il tuo profilo\n\n";
        $message .= "Per qualsiasi assistenza, contatta:\n";
        $message .= "📧 technical@navalegt.it\n\n";
        $message .= "Cordiali saluti,\n";
        $message .= "Il Team Naval EGT";
        
        // Anti-spam headers
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: Naval EGT <noreply@' . $domain . '>',
            'Reply-To: Naval EGT <technical@navalegt.it>',
            'Return-Path: noreply@' . $domain,
            'X-Mailer: ' . $site_name,
            'X-Priority: 3',
            'MIME-Version: 1.0'
        );
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    private function notify_user_new_files($user, $files) {
        $domain = parse_url(home_url(), PHP_URL_HOST);
        $site_name = get_bloginfo('name');
        
        $subject = 'Nuovi file disponibili - Naval EGT';
        $files_list = implode("\n- ", $files);
        
        $message = "Ciao " . $user->display_name . ",\n\n";
        $message .= "Sono stati caricati nuovi file nella tua area riservata Naval EGT:\n\n";
        $message .= "- " . $files_list . "\n\n";
        $message .= "Puoi accedere alla tua area riservata per visualizzarli e scaricarli:\n";
        $message .= home_url('/dashboard-clienti/') . "\n\n";
        $message .= "Cordiali saluti,\n";
        $message .= "Il Team Naval EGT";
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: Naval EGT <noreply@' . $domain . '>',
            'Reply-To: Naval EGT <technical@navalegt.it>',
            'Return-Path: noreply@' . $domain,
            'X-Mailer: ' . $site_name,
            'X-Priority: 3',
            'MIME-Version: 1.0'
        );
        
        return wp_mail($user->user_email, $subject, $message, $headers);
    }
    
    private function get_user_files($user_id) {
        $folder_name = get_user_meta($user_id, 'naval_egt_ftp_folder', true);
        if (!$folder_name) return array();
        
        $upload_dir = wp_upload_dir();
        $downloads_path = $upload_dir['basedir'] . '/naval-egt-ftp/' . $folder_name . '/downloads/';
        
        if (!is_dir($downloads_path)) return array();
        
        $files = array();
        $items = scandir($downloads_path);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '.htaccess') continue;
            
            $file_path = $downloads_path . $item;
            if (is_file($file_path)) {
                $files[] = array(
                    'name' => $item,
                    'path' => 'downloads/' . $item,
                    'size' => filesize($file_path),
                    'date' => filemtime($file_path)
                );
            }
        }
        
        return $files;
    }
    
    private function get_download_url($file_path) {
        return add_query_arg(array(
            'action' => 'naval_egt_download_file',
            'nonce' => wp_create_nonce('naval_egt_nonce'),
            'file' => urlencode($file_path)
        ), admin_url('admin-ajax.php'));
    }
    
    private function log_file_action($user_id, $action, $file_name, $file_path, $file_size) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'naval_egt_ftp_logs',
            array(
                'user_id' => $user_id,
                'action' => $action,
                'file_name' => $file_name,
                'file_path' => $file_path,
                'file_size' => $file_size,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s')
        );
    }
    
    private function delete_directory_recursive($dir) {
        if (!is_dir($dir)) return false;
        
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->delete_directory_recursive($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
    
    private function add_notice($message, $type = 'info') {
        if (!session_id()) session_start();
        
        if (!isset($_SESSION['naval_egt_notices'])) {
            $_SESSION['naval_egt_notices'] = array();
        }
        
        $_SESSION['naval_egt_notices'][] = array(
            'message' => $message,
            'type' => $type
        );
    }
    
    private function display_notices() {
        if (!session_id()) session_start();
        
        $notices = $_SESSION['naval_egt_notices'] ?? array();
        unset($_SESSION['naval_egt_notices']);
        
        foreach ($notices as $notice) {
            echo '<div class="naval-egt-notice naval-egt-notice-' . esc_attr($notice['type']) . '">';
            echo '<p>' . esc_html($notice['message']) . '</p>';
            echo '</div>';
        }
    }
    
    // ACTIVATION/DEACTIVATION
    
    public function activate() {
        $this->create_tables();
        $this->create_roles();
        $this->create_pages();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}naval_egt_ftp_logs (
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
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function create_roles() {
        add_role('naval_egt_client', 'Cliente Naval EGT', array(
            'read' => true,
            'naval_egt_access' => true,
        ));
    }
    
    private function create_pages() {
        if (!get_page_by_path('area-riservata')) {
            wp_insert_post(array(
                'post_title' => 'Area Riservata',
                'post_content' => '[naval_egt_login_form]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => 'area-riservata',
                'comment_status' => 'closed'
            ));
        }
        
        if (!get_page_by_path('dashboard-clienti')) {
            wp_insert_post(array(
                'post_title' => 'Dashboard Clienti',
                'post_content' => '[naval_egt_dashboard]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => 'dashboard-clienti',
                'comment_status' => 'closed'
            ));
        }
    }
}

// Initialize plugin
new NavalEGTPlugin();

// Helper functions
function naval_egt_user_can_access($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) return false;
    
    $user = get_user_by('id', $user_id);
    if (!$user) return false;
    
    if (in_array('naval_egt_client', $user->roles)) {
        $status = get_user_meta($user_id, 'naval_egt_status', true) ?: 'active';
        return $status === 'active';
    }
    
    return in_array('administrator', $user->roles);
}

function naval_egt_get_user_data($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!naval_egt_user_can_access($user_id)) return false;
    
    $user = get_user_by('id', $user_id);
    if (!$user) return false;
    
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

// Add CSS styles
add_action('wp_head', function() {
    ?>
    <style>
    .naval-egt-notice {
        padding: 15px 20px;
        margin-bottom: 20px;
        border-radius: 8px;
        border-left: 4px solid;
        font-weight: 500;
    }
    .naval-egt-notice-success {
        background: #d4edda;
        border-color: #28a745;
        color: #155724;
    }
    .naval-egt-notice-error {
        background: #f8d7da;
        border-color: #dc3545;
        color: #721c24;
    }
    .naval-egt-notice-warning {
        background: #fff3cd;
        border-color: #ffc107;
        color: #856404;
    }
    .naval-egt-area-riservata {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    .naval-egt-header {
        text-align: center;
        margin-bottom: 40px;
        padding: 40px;
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        color: white;
        border-radius: 15px;
    }
    .naval-egt-header h2 {
        margin: 0 0 15px 0;
        font-size: 2.2em;
        font-weight: 700;
    }
    .naval-egt-dashboard {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    @media (max-width: 768px) {
        .naval-egt-area-riservata > div,
        .naval-egt-dashboard > div > div {
            grid-template-columns: 1fr !important;
        }
    }
    </style>
    <?php
});
?>