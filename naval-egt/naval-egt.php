<?php
/**
 * Plugin Name: Naval EGT - Area Riservata
 * Plugin URI: https://www.navalegt.it/
 * Description: Plugin per la gestione dell'area riservata clienti Naval EGT con accesso, registrazione e cartella FTP integrata.
 * Version: 1.0.0
 * Author: Scherpmind di Raffaele Borrelli
 * Author URI: https://scherpmind.it
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
define('NAVAL_EGT_VERSION', '1.0.0');
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
        
        // AJAX Actions
        add_action('wp_ajax_naval_egt_add_user', array($this, 'ajax_add_user'));
        add_action('wp_ajax_naval_egt_admin_list_users', array($this, 'ajax_list_users'));
        add_action('wp_ajax_naval_egt_upload_file_for_user', array($this, 'ajax_upload_file_for_user'));
        add_action('wp_ajax_naval_egt_list_files', array($this, 'ajax_list_files'));
        add_action('wp_ajax_naval_egt_download_file', array($this, 'ajax_download_file'));
        add_action('wp_ajax_naval_egt_refresh_user_files', array($this, 'ajax_refresh_user_files'));
        
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
        
        // Ensure FTP base directory exists
        $this->ensure_ftp_directory();
    }
    
    private function ensure_ftp_directory() {
        $upload_dir = wp_upload_dir();
        $ftp_base = $upload_dir['basedir'] . '/naval-egt-ftp/';
        
        if (!file_exists($ftp_base)) {
            wp_mkdir_p($ftp_base);
            
            // Add security .htaccess
            $htaccess_content = "Options -Indexes\nDeny from all\n";
            file_put_contents($ftp_base . '.htaccess', $htaccess_content);
        }
    }
    
    public function enqueue_scripts() {
        wp_enqueue_style('naval-egt-style', NAVAL_EGT_PLUGIN_URL . 'assets/css/style.css', array(), NAVAL_EGT_VERSION);
        wp_enqueue_script('naval-egt-script', NAVAL_EGT_PLUGIN_URL . 'assets/js/script.js', array('jquery'), NAVAL_EGT_VERSION, true);
        
        // Inline CSS per migliorare l'aspetto
        wp_add_inline_style('naval-egt-style', '
            .naval-egt-file-upload-area {
                border: 3px dashed #2a5298;
                border-radius: 15px;
                padding: 40px;
                text-align: center;
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                cursor: pointer;
                transition: all 0.3s ease;
                position: relative;
                overflow: hidden;
            }
            
            .naval-egt-file-upload-area:hover {
                border-color: #1e3c72;
                background: linear-gradient(135deg, #e8f2ff 0%, #d1ecf1 100%);
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(42, 82, 152, 0.15);
            }
            
            .naval-egt-file-upload-area.dragover {
                border-color: #28a745;
                background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
                transform: scale(1.02);
            }
            
            .naval-egt-upload-icon {
                font-size: 4em;
                color: #2a5298;
                margin-bottom: 20px;
                animation: float 3s ease-in-out infinite;
            }
            
            @keyframes float {
                0%, 100% { transform: translateY(0px); }
                50% { transform: translateY(-10px); }
            }
            
            .naval-egt-upload-text {
                font-size: 1.2em;
                font-weight: 600;
                color: #2a5298;
                margin-bottom: 10px;
            }
            
            .naval-egt-upload-subtext {
                color: #6c757d;
                font-size: 0.9em;
            }
            
            .naval-egt-progress-container {
                margin-top: 20px;
                background: white;
                border-radius: 10px;
                padding: 20px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            
            .naval-egt-progress-bar {
                width: 100%;
                height: 8px;
                background: #e9ecef;
                border-radius: 4px;
                overflow: hidden;
                margin-bottom: 10px;
            }
            
            .naval-egt-progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #28a745, #20c997);
                border-radius: 4px;
                transition: width 0.3s ease;
                width: 0%;
            }
            
            .naval-egt-file-preview {
                display: flex;
                align-items: center;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 10px;
                margin-top: 15px;
            }
            
            .naval-egt-file-icon {
                font-size: 2em;
                margin-right: 15px;
            }
            
            .naval-egt-success-animation {
                animation: successPulse 0.6s ease-out;
            }
            
            @keyframes successPulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
        ');
        
        wp_localize_script('naval-egt-script', 'naval_egt_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('naval_egt_nonce'),
        ));
    }
    
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'naval-egt') !== false) {
            wp_enqueue_media();
            wp_localize_script('jquery', 'naval_egt_admin', array(
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
    }
    
    public function admin_page() {
        $users = get_users(array('role' => 'naval_egt_client'));
        ?>
        <div class="wrap">
            <h1>Naval EGT - Gestione Area Riservata</h1>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px;">
                <!-- Caricamento File -->
                <div style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <h2>📤 Carica File per Utente</h2>
                    
                    <div style="margin-bottom: 20px;">
                        <label for="target-user"><strong>Seleziona Utente:</strong></label>
                        <select id="target-user" style="width: 100%; padding: 10px; margin-top: 5px; border-radius: 5px; border: 1px solid #ddd;">
                            <option value="">-- Seleziona Utente --</option>
                            <?php foreach ($users as $user): 
                                $company = get_user_meta($user->ID, 'naval_egt_company', true);
                            ?>
                                <option value="<?php echo $user->ID; ?>">
                                    <?php echo esc_html($user->display_name); ?>
                                    <?php if ($company): ?>(<?php echo esc_html($company); ?>)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="naval-egt-file-upload-area" id="file-upload-area">
                        <div class="naval-egt-upload-icon">📁</div>
                        <div class="naval-egt-upload-text">Trascina qui i file PDF o clicca per selezionare</div>
                        <div class="naval-egt-upload-subtext">Formati supportati: PDF (max 50MB)</div>
                        <input type="file" id="file-input" multiple accept=".pdf" style="display: none;">
                    </div>
                    
                    <div id="file-preview" style="margin-top: 20px;"></div>
                    <div id="upload-progress" style="display: none;"></div>
                    
                    <button id="upload-btn" style="width: 100%; padding: 15px; background: linear-gradient(135deg, #28a745, #20c997); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; margin-top: 20px; cursor: pointer; display: none;">
                        🚀 Carica File
                    </button>
                </div>
                
                <!-- Lista Utenti -->
                <div style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <h2>👥 Utenti Registrati (<?php echo count($users); ?>)</h2>
                    <div id="users-list">
                        <div style="text-align: center; padding: 20px; color: #666;">
                            Caricamento utenti...
                        </div>
                    </div>
                    
                    <!-- Debug Info -->
                    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; font-size: 12px; color: #666;">
                        <strong>Debug Info:</strong><br>
                        Upload Dir: <?php echo wp_upload_dir()['basedir']; ?>/naval-egt-ftp/<br>
                        Current User: <?php echo wp_get_current_user()->display_name; ?> (ID: <?php echo get_current_user_id(); ?>)
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Carica lista utenti
            loadUsersList();
            
            // Upload area events
            const uploadArea = $('#file-upload-area');
            const fileInput = $('#file-input');
            const uploadBtn = $('#upload-btn');
            const filePreview = $('#file-preview');
            let selectedFiles = [];
            
            uploadArea.on('click', function() {
                fileInput.click();
            });
            
            uploadArea.on('dragover dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragover');
            });
            
            uploadArea.on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
            });
            
            uploadArea.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
                
                const files = e.originalEvent.dataTransfer.files;
                handleFiles(files);
            });
            
            fileInput.on('change', function() {
                const files = this.files;
                handleFiles(files);
            });
            
            function handleFiles(files) {
                selectedFiles = Array.from(files);
                displayFilePreview();
                uploadBtn.show();
            }
            
            function displayFilePreview() {
                let html = '';
                selectedFiles.forEach((file, index) => {
                    const size = formatFileSize(file.size);
                    html += `
                        <div class="naval-egt-file-preview" data-index="${index}">
                            <div class="naval-egt-file-icon">📄</div>
                            <div style="flex: 1;">
                                <div style="font-weight: 600;">${file.name}</div>
                                <div style="color: #666; font-size: 0.9em;">${size}</div>
                            </div>
                            <button onclick="removeFile(${index})" style="background: #dc3545; color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer;">×</button>
                        </div>
                    `;
                });
                filePreview.html(html);
            }
            
            window.removeFile = function(index) {
                selectedFiles.splice(index, 1);
                displayFilePreview();
                if (selectedFiles.length === 0) {
                    uploadBtn.hide();
                }
            };
            
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 B';
                const k = 1024;
                const sizes = ['B', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
            
            uploadBtn.on('click', function() {
                const userId = $('#target-user').val();
                if (!userId) {
                    alert('Seleziona un utente!');
                    return;
                }
                
                if (selectedFiles.length === 0) {
                    alert('Seleziona almeno un file!');
                    return;
                }
                
                uploadFiles(userId);
            });
            
            function uploadFiles(userId) {
                const progressContainer = $('#upload-progress');
                progressContainer.show().html(`
                    <div class="naval-egt-progress-container">
                        <div style="font-weight: 600; margin-bottom: 10px;">Caricamento in corso...</div>
                        <div class="naval-egt-progress-bar">
                            <div class="naval-egt-progress-fill" id="progress-fill"></div>
                        </div>
                        <div id="progress-text">0%</div>
                    </div>
                `);
                
                uploadBtn.prop('disabled', true).text('Caricamento...');
                
                const formData = new FormData();
                formData.append('action', 'naval_egt_upload_file_for_user');
                formData.append('nonce', naval_egt_admin.nonce);
                formData.append('user_id', userId);
                
                selectedFiles.forEach((file, index) => {
                    formData.append(`files[${index}]`, file);
                });
                
                console.log('Uploading files for user:', userId, 'Files:', selectedFiles.length);
                
                $.ajax({
                    url: naval_egt_admin.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    xhr: function() {
                        const xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener('progress', function(e) {
                            if (e.lengthComputable) {
                                const percent = Math.round((e.loaded / e.total) * 100);
                                $('#progress-fill').css('width', percent + '%');
                                $('#progress-text').text(percent + '%');
                            }
                        });
                        return xhr;
                    },
                    success: function(response) {
                        console.log('Upload response:', response);
                        if (response.success) {
                            progressContainer.html(`
                                <div class="naval-egt-progress-container naval-egt-success-animation" style="background: #d4edda; border: 2px solid #28a745;">
                                    <div style="color: #155724; font-weight: 600; text-align: center;">
                                        ✅ ${response.data.message}
                                    </div>
                                </div>
                            `);
                            
                            // Reset form
                            selectedFiles = [];
                            filePreview.html('');
                            uploadBtn.hide().prop('disabled', false).text('🚀 Carica File');
                            $('#target-user').val('');
                            fileInput.val('');
                            
                            setTimeout(() => {
                                progressContainer.fadeOut();
                            }, 3000);
                        } else {
                            progressContainer.html(`
                                <div class="naval-egt-progress-container" style="background: #f8d7da; border: 2px solid #dc3545;">
                                    <div style="color: #721c24; font-weight: 600; text-align: center;">
                                        ❌ ${response.data.message}
                                    </div>
                                </div>
                            `);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Upload error:', error, xhr.responseText);
                        progressContainer.html(`
                            <div class="naval-egt-progress-container" style="background: #f8d7da; border: 2px solid #dc3545;">
                                <div style="color: #721c24; font-weight: 600; text-align: center;">
                                    ❌ Errore durante il caricamento
                                </div>
                            </div>
                        `);
                    },
                    complete: function() {
                        uploadBtn.prop('disabled', false).text('🚀 Carica File');
                    }
                });
            }
            
            function loadUsersList() {
                $.ajax({
                    url: naval_egt_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'naval_egt_admin_list_users',
                        nonce: naval_egt_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            displayUsers(response.data.users);
                        }
                    }
                });
            }
            
            function displayUsers(users) {
                let html = '';
                users.forEach(user => {
                    const status = user.status || 'active';
                    const statusColor = status === 'active' ? '#28a745' : (status === 'pending' ? '#ffc107' : '#dc3545');
                    
                    html += `
                        <div style="padding: 15px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 10px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong>${user.display_name}</strong><br>
                                    <small style="color: #666;">${user.email}</small>
                                    ${user.company ? '<br><small>🏢 ' + user.company + '</small>' : ''}
                                </div>
                                <div style="text-align: right;">
                                    <span style="background: ${statusColor}; color: white; padding: 4px 8px; border-radius: 12px; font-size: 12px;">
                                        ${status.toUpperCase()}
                                    </span>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                $('#users-list').html(html || '<div style="text-align: center; color: #666;">Nessun utente trovato</div>');
            }
        });
        </script>
        <?php
    }
    
    public function handle_custom_actions() {
        if (!isset($_GET['naval_action'])) {
            return;
        }
        
        $action = sanitize_text_field($_GET['naval_action']);
        
        switch ($action) {
            case 'register':
                $this->handle_registration();
                break;
            case 'login':
                $this->handle_login();
                break;
            case 'logout':
                $this->handle_logout();
                break;
        }
    }
    
    private function handle_registration() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        if (!wp_verify_nonce($_POST['naval_egt_nonce'], 'naval_egt_register')) {
            wp_die('Errore di sicurezza');
        }
        
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $email = sanitize_email($_POST['email']);
        $company = sanitize_text_field($_POST['company']);
        $phone = sanitize_text_field($_POST['phone']);
        $password = $_POST['password']; // Password scelta dall'utente
        
        // Validation
        if (empty($first_name) || empty($last_name) || empty($email) || empty($company) || empty($password)) {
            $this->add_notice('Tutti i campi obbligatori devono essere compilati.', 'error');
            return;
        }
        
        if (!is_email($email)) {
            $this->add_notice('Inserisci un indirizzo email valido.', 'error');
            return;
        }
        
        if (email_exists($email)) {
            $this->add_notice('Un account con questa email esiste già.', 'error');
            return;
        }
        
        if (strlen($password) < 6) {
            $this->add_notice('La password deve essere di almeno 6 caratteri.', 'error');
            return;
        }
        
        // Crea username univoco
        $username = $this->generate_username($email);
        
        // Crea utente
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            $this->add_notice('Errore durante la registrazione: ' . $user_id->get_error_message(), 'error');
            return;
        }
        
        // Imposta ruolo
        $user = new WP_User($user_id);
        $user->set_role('naval_egt_client');
        
        // Salva dati aggiuntivi
        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        update_user_meta($user_id, 'naval_egt_company', $company);
        update_user_meta($user_id, 'naval_egt_phone', $phone);
        update_user_meta($user_id, 'naval_egt_status', 'active'); // Attivo subito
        
        // Crea cartella FTP
        $folder_created = $this->create_user_ftp_folder($user_id);
        
        // INVIA EMAIL DI BENVENUTO
        $email_sent = $this->send_welcome_email($user_id, $password);
        
        // Debug info
        error_log("Naval EGT Registration - User ID: $user_id, Folder: $folder_created, Email sent: " . ($email_sent ? 'YES' : 'NO'));
        
        if ($email_sent) {
            $this->add_notice('Registrazione completata! Ti è stata inviata una email di conferma all\'indirizzo: ' . $email . '. Ora puoi effettuare il login.', 'success');
        } else {
            $this->add_notice('Registrazione completata! ATTENZIONE: Non è stato possibile inviare l\'email di conferma. Contatta l\'assistenza per ricevere le credenziali.', 'warning');
        }
    }
    
    private function send_welcome_email($user_id, $password) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            error_log("Naval EGT: User not found for email sending: $user_id");
            return false;
        }
        
        $to = $user->user_email;
        $subject = 'Benvenuto nell\'Area Riservata Naval EGT';
        
        $message = "Ciao " . $user->display_name . ",\n\n";
        $message .= "Benvenuto nell'Area Riservata Naval EGT!\n\n";
        $message .= "La tua registrazione è stata completata con successo. Ecco i tuoi dati di accesso:\n\n";
        $message .= "🔐 DATI DI ACCESSO:\n";
        $message .= "- Email: " . $user->user_email . "\n";
        $message .= "- Username: " . $user->user_login . "\n";
        $message .= "- Password: " . $password . "\n\n";
        $message .= "🌐 LINK DI ACCESSO:\n";
        $message .= home_url('/area-riservata/') . "\n\n";
        $message .= "📁 COSA PUOI FARE:\n";
        $message .= "- Accedere alla tua area riservata personale\n";
        $message .= "- Visualizzare e scaricare i file che Naval EGT condivide con te\n";
        $message .= "- Caricare documenti nella tua cartella dedicata\n";
        $message .= "- Gestire il tuo profilo\n\n";
        $message .= "Per qualsiasi assistenza, contatta:\n";
        $message .= "📧 technical@navalegt.it\n\n";
        $message .= "Cordiali saluti,\n";
        $message .= "Il Team Naval EGT";
        
        // Headers per migliorare la deliverability
        $headers = array();
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'From: Naval EGT <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>';
        
        $result = wp_mail($to, $subject, $message, $headers);
        
        // Log per debug
        error_log("Naval EGT: Email attempt to $to - Result: " . ($result ? 'SUCCESS' : 'FAILED'));
        
        // Se wp_mail fallisce, prova con mail() PHP nativo
        if (!$result) {
            error_log("Naval EGT: wp_mail failed, trying native mail()");
            $native_headers = "From: Naval EGT <noreply@" . parse_url(home_url(), PHP_URL_HOST) . ">\r\n";
            $native_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            
            $result = mail($to, $subject, $message, $native_headers);
            error_log("Naval EGT: Native mail() result: " . ($result ? 'SUCCESS' : 'FAILED'));
        }
        
        return $result;
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
        $folder_path = $base_path . $folder_name . '/';
        
        // Crea le cartelle
        $downloads_path = $folder_path . 'downloads/';
        $uploads_path = $folder_path . 'uploads/';
        
        wp_mkdir_p($downloads_path);
        wp_mkdir_p($uploads_path);
        
        // Security files
        file_put_contents($folder_path . '.htaccess', "Options -Indexes\nDeny from all\n");
        file_put_contents($downloads_path . '.htaccess', "Options -Indexes\n");
        file_put_contents($uploads_path . '.htaccess', "Options -Indexes\n");
        
        // README file
        $readme = "Cartella FTP per " . $user->display_name . "\n";
        $readme .= "Creata il: " . date('d/m/Y H:i:s') . "\n\n";
        $readme .= "downloads/ = File condivisi da Naval EGT\n";
        $readme .= "uploads/ = I tuoi file caricati\n";
        file_put_contents($folder_path . 'README.txt', $readme);
        
        update_user_meta($user_id, 'naval_egt_ftp_folder', $folder_name);
        
        error_log("Naval EGT: Created FTP folder for user $user_id: $folder_path");
        
        return $folder_name;
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
                <p>Accedi o registrati per utilizzare l'area riservata dedicata ai clienti Naval EGT</p>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; max-width: 1000px; margin: 0 auto;">
                <!-- LOGIN -->
                <div style="background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
                    <h3>🚪 Accedi al tuo account</h3>
                    
                    <form method="post" action="<?php echo esc_url(add_query_arg('naval_action', 'login')); ?>">
                        <?php wp_nonce_field('naval_egt_login', 'naval_egt_nonce'); ?>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px;">Email o Username</label>
                            <input type="text" name="username" required 
                                   style="width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px;" />
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px;">Password</label>
                            <input type="password" name="password" required 
                                   style="width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 16px;" />
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" name="remember" style="margin-right: 10px;" />
                                Ricordami
                            </label>
                        </div>
                        
                        <button type="submit" style="width: 100%; padding: 15px; background: linear-gradient(135deg, #2a5298, #1e3c72); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer;">
                            🔑 Accedi
                        </button>
                    </form>
                </div>
                
                <!-- REGISTRAZIONE -->
                <div style="background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
                    <h3>📝 Nuovo utente? Registrati</h3>
                    
                    <form method="post" action="<?php echo esc_url(add_query_arg('naval_action', 'register')); ?>">
                        <?php wp_nonce_field('naval_egt_register', 'naval_egt_nonce'); ?>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label style="display: block; font-weight: 600; margin-bottom: 5px;">Nome *</label>
                                <input type="text" name="first_name" required 
                                       style="width: 100%; padding: 10px; border: 2px solid #e1e5e9; border-radius: 6px;" />
                            </div>
                            <div>
                                <label style="display: block; font-weight: 600; margin-bottom: 5px;">Cognome *</label>
                                <input type="text" name="last_name" required 
                                       style="width: 100%; padding: 10px; border: 2px solid #e1e5e9; border-radius: 6px;" />
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Email *</label>
                            <input type="email" name="email" required 
                                   style="width: 100%; padding: 10px; border: 2px solid #e1e5e9; border-radius: 6px;" />
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Azienda *</label>
                            <input type="text" name="company" required 
                                   style="width: 100%; padding: 10px; border: 2px solid #e1e5e9; border-radius: 6px;" />
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Telefono</label>
                            <input type="tel" name="phone" 
                                   style="width: 100%; padding: 10px; border: 2px solid #e1e5e9; border-radius: 6px;" />
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Scegli Password *</label>
                            <input type="password" name="password" required minlength="6"
                                   style="width: 100%; padding: 10px; border: 2px solid #e1e5e9; border-radius: 6px;" />
                            <small style="color: #666;">Minimo 6 caratteri</small>
                        </div>
                        
                        <button type="submit" style="width: 100%; padding: 12px; background: linear-gradient(135deg, #28a745, #20c997); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer;">
                            ✨ Registrati Ora
                        </button>
                    </form>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                <p><strong>🆘 Assistenza Tecnica:</strong> <a href="mailto:technical@navalegt.it">technical@navalegt.it</a></p>
                <p><small>Se non ricevi l'email di registrazione, controlla la cartella spam o contatta l'assistenza</small></p>
            </div>
        </div>
        
        <style>
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
        
        .naval-egt-notice {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            border-left: 4px solid;
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
        
        @media (max-width: 768px) {
            .naval-egt-area-riservata > div {
                grid-template-columns: 1fr !important;
            }
        }
        </style>
        
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
                <div style="display: flex; justify-content: space-between; align-items: center;">
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
                        
                        <!-- Debug Info -->
                        <div style="margin-top: 20px; padding: 10px; background: #f8f9fa; border-radius: 5px; font-size: 11px; color: #666;">
                            <strong>Debug:</strong><br>
                            Cartella FTP: <?php echo esc_html($user_data['ftp_folder']); ?><br>
                            File trovati: <?php echo count($user_files); ?>
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
                    console.log('Refresh response:', response);
                    if (response.success) {
                        location.reload(); // Ricarica la pagina per mostrare i nuovi file
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Refresh error:', error);
                }
            });
        }
        </script>
        
        <style>
        .naval-egt-dashboard {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        @media (max-width: 768px) {
            .naval-egt-dashboard > div > div {
                grid-template-columns: 1fr !important;
            }
        }
        </style>
        
        <?php
        return ob_get_clean();
    }
    
    private function get_user_files($user_id) {
        $folder_name = get_user_meta($user_id, 'naval_egt_ftp_folder', true);
        if (!$folder_name) {
            error_log("Naval EGT: No FTP folder found for user $user_id");
            return array();
        }
        
        $upload_dir = wp_upload_dir();
        $downloads_path = $upload_dir['basedir'] . '/naval-egt-ftp/' . $folder_name . '/downloads/';
        
        error_log("Naval EGT: Looking for files in: $downloads_path");
        
        if (!is_dir($downloads_path)) {
            error_log("Naval EGT: Downloads directory does not exist: $downloads_path");
            return array();
        }
        
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
        
        error_log("Naval EGT: Found " . count($files) . " files for user $user_id");
        
        return $files;
    }
    
    private function get_download_url($file_path) {
        return add_query_arg(array(
            'action' => 'naval_egt_download_file',
            'nonce' => wp_create_nonce('naval_egt_nonce'),
            'file' => urlencode($file_path)
        ), admin_url('admin-ajax.php'));
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
    
    // AJAX METHODS
    public function ajax_add_user() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Accesso negato'));
        }
        
        wp_send_json_success(array('message' => 'Funzione da implementare'));
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
                'status' => get_user_meta($user->ID, 'naval_egt_status', true) ?: 'active',
                'registered' => $user->user_registered
            );
        }
        
        wp_send_json_success(array('users' => $users_data));
    }
    
    public function ajax_upload_file_for_user() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Accesso negato'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_admin')) {
            wp_send_json_error(array('message' => 'Errore di sicurezza'));
        }
        
        $user_id = intval($_POST['user_id']);
        if (!$user_id) {
            wp_send_json_error(array('message' => 'ID utente mancante'));
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            wp_send_json_error(array('message' => 'Utente non trovato'));
        }
        
        $folder_name = get_user_meta($user_id, 'naval_egt_ftp_folder', true);
        if (!$folder_name) {
            // Se non esiste, creala
            $folder_name = $this->create_user_ftp_folder($user_id);
            if (!$folder_name) {
                wp_send_json_error(array('message' => 'Impossibile creare cartella utente'));
            }
        }
        
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/naval-egt-ftp/' . $folder_name . '/downloads/';
        
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        
        error_log("Naval EGT: Upload target directory: $target_dir");
        
        $uploaded_files = array();
        
        // Handle multiple files
        if (isset($_FILES['files'])) {
            $files = $_FILES['files'];
            $file_count = count($files['name']);
            
            error_log("Naval EGT: Processing $file_count files");
            
            for ($i = 0; $i < $file_count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file_name = sanitize_file_name($files['name'][$i]);
                    $file_tmp = $files['tmp_name'][$i];
                    
                    // Check file type
                    $file_type = wp_check_filetype($file_name);
                    if ($file_type['ext'] !== 'pdf') {
                        wp_send_json_error(array('message' => 'Solo file PDF sono permessi: ' . $file_name));
                    }
                    
                    $target_file = $target_dir . $file_name;
                    
                    // Make filename unique if exists
                    $counter = 1;
                    $original_name = pathinfo($file_name, PATHINFO_FILENAME);
                    $extension = pathinfo($file_name, PATHINFO_EXTENSION);
                    
                    while (file_exists($target_file)) {
                        $file_name = $original_name . '_' . $counter . '.' . $extension;
                        $target_file = $target_dir . $file_name;
                        $counter++;
                    }
                    
                    if (move_uploaded_file($file_tmp, $target_file)) {
                        $uploaded_files[] = $file_name;
                        
                        // Log the upload
                        $this->log_file_action($user_id, 'admin_upload', $file_name, 'downloads/' . $file_name, filesize($target_file));
                        
                        error_log("Naval EGT: Successfully uploaded file: $target_file");
                    } else {
                        error_log("Naval EGT: Failed to move uploaded file: $file_tmp to $target_file");
                    }
                } else {
                    error_log("Naval EGT: Upload error for file $i: " . $files['error'][$i]);
                }
            }
        }
        
        if (!empty($uploaded_files)) {
            // Send notification email to user
            $this->notify_user_new_files($user, $uploaded_files);
            
            wp_send_json_success(array(
                'message' => 'File caricati con successo: ' . implode(', ', $uploaded_files)
            ));
        } else {
            wp_send_json_error(array('message' => 'Nessun file è stato caricato'));
        }
    }
    
    public function ajax_refresh_user_files() {
        if (!wp_verify_nonce($_POST['nonce'], 'naval_egt_nonce')) {
            wp_send_json_error(array('message' => 'Errore di sicurezza'));
        }
        
        if (!is_user_logged_in() || !naval_egt_user_can_access()) {
            wp_send_json_error(array('message' => 'Accesso negato'));
        }
        
        $user_id = get_current_user_id();
        $files = $this->get_user_files($user_id);
        
        wp_send_json_success(array(
            'files' => $files,
            'count' => count($files)
        ));
    }
    
    private function notify_user_new_files($user, $files) {
        $subject = 'Nuovi file disponibili - Naval EGT';
        $files_list = implode("\n- ", $files);
        
        $message = "Ciao " . $user->display_name . ",\n\n";
        $message .= "Sono stati caricati nuovi file nella tua area riservata Naval EGT:\n\n";
        $message .= "- " . $files_list . "\n\n";
        $message .= "Puoi accedere alla tua area riservata per visualizzarli e scaricarli:\n";
        $message .= home_url('/dashboard-clienti/') . "\n\n";
        $message .= "Cordiali saluti,\n";
        $message .= "Il Team Naval EGT";
        
        $headers = array();
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'From: Naval EGT <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>';
        
        $result = wp_mail($user->user_email, $subject, $message, $headers);
        
        error_log("Naval EGT: Notification email sent to " . $user->user_email . " - Result: " . ($result ? 'SUCCESS' : 'FAILED'));
        
        return $result;
    }
    
    private function log_file_action($user_id, $action, $file_name, $file_path, $file_size) {
        global $wpdb;
        
        $result = $wpdb->insert(
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
        
        if ($result === false) {
            error_log("Naval EGT: Failed to log file action: " . $wpdb->last_error);
        }
    }
    
    public function ajax_download_file() {
        if (!wp_verify_nonce($_GET['nonce'], 'naval_egt_nonce')) {
            wp_die('Errore di sicurezza');
        }
        
        if (!is_user_logged_in() || !naval_egt_user_can_access()) {
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
            wp_die('File non trovato: ' . $full_path);
        }
        
        // Log download
        $this->log_file_action($user_id, 'download', basename($file_path), $file_path, filesize($full_path));
        
        // Force download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($full_path) . '"');
        header('Content-Length: ' . filesize($full_path));
        readfile($full_path);
        exit;
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
            PRIMARY KEY (id)
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
        // Area Riservata page
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
        
        // Dashboard page
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
    
    return in_array('naval_egt_client', $user->roles) || in_array('administrator', $user->roles);
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
?>