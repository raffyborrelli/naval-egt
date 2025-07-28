<?php
/**
 * Naval EGT Admin - Main Dashboard Page - Updated
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get statistics
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
$pending_users = count(get_users(array(
    'role' => 'naval_egt_client',
    'meta_query' => array(
        array(
            'key' => 'naval_egt_status',
            'value' => 'pending',
            'compare' => '='
        )
    )
)));

$logs_table = $wpdb->prefix . 'naval_egt_ftp_logs';
$total_files = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE action = 'upload'");
$recent_uploads = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table WHERE action = 'upload' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");

// Get recent activity
$recent_activity = $wpdb->get_results("
    SELECT l.*, u.display_name, u.user_email 
    FROM $logs_table l 
    LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
    ORDER BY l.created_at DESC 
    LIMIT 10
");
?>

<div class="naval-egt-admin-wrap">
    <div class="naval-egt-admin-header">
        <h1><?php _e('Naval EGT - Area di Amministrazione', 'naval-egt'); ?></h1>
        <p><?php _e('Gestisci gli utenti dell\'area riservata, monitora l\'attività e configura le impostazioni del plugin.', 'naval-egt'); ?></p>
    </div>

    <!-- Dashboard Statistics -->
    <div class="naval-egt-admin-cards">
        <div class="naval-egt-admin-card stat-total-users" data-stat-type="total_users">
            <h3><?php _e('Utenti Totali', 'naval-egt'); ?></h3>
            <div class="naval-egt-stat-number"><?php echo intval($total_users); ?></div>
            <div class="naval-egt-stat-label"><?php _e('Registrati nell\'area riservata', 'naval-egt'); ?></div>
        </div>

        <div class="naval-egt-admin-card stat-active-users" data-stat-type="active_users">
            <h3><?php _e('Utenti Attivi', 'naval-egt'); ?></h3>
            <div class="naval-egt-stat-number"><?php echo intval($active_users); ?></div>
            <div class="naval-egt-stat-label"><?php _e('Con accesso confermato', 'naval-egt'); ?></div>
        </div>

        <div class="naval-egt-admin-card stat-pending-users" data-stat-type="pending_users">
            <h3><?php _e('In Attesa', 'naval-egt'); ?></h3>
            <div class="naval-egt-stat-number"><?php echo intval($pending_users); ?></div>
            <div class="naval-egt-stat-label"><?php _e('Registrazioni da attivare', 'naval-egt'); ?></div>
        </div>

        <div class="naval-egt-admin-card stat-total-files" data-stat-type="total_files">
            <h3><?php _e('File Caricati', 'naval-egt'); ?></h3>
            <div class="naval-egt-stat-number"><?php echo intval($total_files); ?></div>
            <div class="naval-egt-stat-label"><?php _e('Totale su tutta la piattaforma', 'naval-egt'); ?></div>
        </div>
    </div>

    <!-- Main Content Tabs -->
    <div class="naval-egt-admin-tabs">
        <div class="naval-egt-admin-tab-nav">
            <button data-tab="overview" class="active"><?php _e('Panoramica', 'naval-egt'); ?></button>
            <button data-tab="users"><?php _e('Gestione Utenti', 'naval-egt'); ?></button>
            <button data-tab="add-user"><?php _e('Aggiungi Utente', 'naval-egt'); ?></button>
            <button data-tab="files"><?php _e('Gestione File', 'naval-egt'); ?></button>
            <button data-tab="logs"><?php _e('Log Attività', 'naval-egt'); ?></button>
            <button data-tab="tools"><?php _e('Strumenti', 'naval-egt'); ?></button>
        </div>

        <div class="naval-egt-admin-tab-content">
            <!-- Overview Tab -->
            <div id="overview" class="naval-egt-admin-tab-pane active">
                <div class="naval-egt-admin-settings-grid">
                    <!-- Recent Activity -->
                    <div class="naval-egt-admin-settings-section">
                        <div class="naval-egt-admin-settings-header">
                            <h3><?php _e('Attività Recente', 'naval-egt'); ?></h3>
                        </div>
                        <div class="naval-egt-admin-settings-content">
                            <?php if (!empty($recent_activity)): ?>
                            <div class="naval-egt-recent-activity">
                                <?php foreach ($recent_activity as $activity): ?>
                                <div class="naval-egt-activity-item">
                                    <div class="naval-egt-activity-icon">
                                        <?php 
                                        switch ($activity->action) {
                                            case 'upload':
                                                echo '⬆️';
                                                break;
                                            case 'download':
                                                echo '⬇️';
                                                break;
                                            case 'delete':
                                                echo '🗑️';
                                                break;
                                            default:
                                                echo '📄';
                                        }
                                        ?>
                                    </div>
                                    <div class="naval-egt-activity-details">
                                        <div class="naval-egt-activity-user">
                                            <strong><?php echo esc_html($activity->display_name ?: 'Utente eliminato'); ?></strong>
                                            <?php if ($activity->user_email): ?>
                                            <small>(<?php echo esc_html($activity->user_email); ?>)</small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="naval-egt-activity-action">
                                            <?php 
                                            switch ($activity->action) {
                                                case 'upload':
                                                    printf(__('ha caricato %s', 'naval-egt'), '<code>' . esc_html($activity->file_name) . '</code>');
                                                    break;
                                                case 'download':
                                                    printf(__('ha scaricato %s', 'naval-egt'), '<code>' . esc_html($activity->file_name) . '</code>');
                                                    break;
                                                case 'delete':
                                                    printf(__('ha eliminato %s', 'naval-egt'), '<code>' . esc_html($activity->file_name) . '</code>');
                                                    break;
                                                case 'create_folder':
                                                    printf(__('ha creato la cartella %s', 'naval-egt'), '<code>' . esc_html($activity->file_name) . '</code>');
                                                    break;
                                            }
                                            ?>
                                        </div>
                                        <div class="naval-egt-activity-time">
                                            <?php echo date_i18n('d/m/Y H:i', strtotime($activity->created_at)); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="naval-egt-admin-empty">
                                <p><?php _e('Nessuna attività recente', 'naval-egt'); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="naval-egt-admin-settings-section">
                        <div class="naval-egt-admin-settings-header">
                            <h3><?php _e('Statistiche Veloci', 'naval-egt'); ?></h3>
                        </div>
                        <div class="naval-egt-admin-settings-content">
                            <div class="naval-egt-quick-stats">
                                <div class="naval-egt-quick-stat">
                                    <span class="stat-label"><?php _e('Upload questa settimana:', 'naval-egt'); ?></span>
                                    <span class="stat-value"><?php echo intval($recent_uploads); ?></span>
                                </div>
                                <div class="naval-egt-quick-stat">
                                    <span class="stat-label"><?php _e('Spazio utilizzato:', 'naval-egt'); ?></span>
                                    <span class="stat-value">
                                        <?php 
                                        $upload_dir = wp_upload_dir();
                                        $ftp_path = $upload_dir['basedir'] . '/naval-egt-ftp/';
                                        if (is_dir($ftp_path)) {
                                            $size = $this->get_directory_size($ftp_path);
                                            echo size_format($size);
                                        } else {
                                            echo '0 MB';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="naval-egt-quick-stat">
                                    <span class="stat-label"><?php _e('Sistema operativo:', 'naval-egt'); ?></span>
                                    <span class="stat-value"><?php echo PHP_OS; ?></span>
                                </div>
                                <div class="naval-egt-quick-stat">
                                    <span class="stat-label"><?php _e('Versione WordPress:', 'naval-egt'); ?></span>
                                    <span class="stat-value"><?php echo get_bloginfo('version'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="naval-egt-admin-form">
                    <h3><?php _e('Azioni Rapide', 'naval-egt'); ?></h3>
                    <div class="naval-egt-admin-flex naval-egt-admin-flex-gap-20">
                        <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-primary" onclick="switchToTab('users')">
                            <?php _e('Gestisci Utenti', 'naval-egt'); ?>
                        </button>
                        <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-success" onclick="switchToTab('add-user')">
                            <?php _e('➕ Aggiungi Nuovo Utente', 'naval-egt'); ?>
                        </button>
                        <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-warning" onclick="switchToTab('files')">
                            <?php _e('📁 Gestisci File', 'naval-egt'); ?>
                        </button>
                        <a href="<?php echo admin_url('admin.php?page=naval-egt-settings'); ?>" class="naval-egt-admin-btn naval-egt-admin-btn-secondary">
                            <?php _e('Impostazioni', 'naval-egt'); ?>
                        </a>
                        <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-warning btn-export-users">
                            <?php _e('Esporta Utenti', 'naval-egt'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Users Tab -->
            <div id="users" class="naval-egt-admin-tab-pane">
                <div id="users-table-container">
                    <div class="naval-egt-admin-loading">
                        <div class="naval-egt-admin-loading-spinner"></div>
                        <p><?php _e('Caricamento utenti...', 'naval-egt'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Add User Tab -->
            <div id="add-user" class="naval-egt-admin-tab-pane">
                <div class="naval-egt-admin-form">
                    <h3><?php _e('➕ Aggiungi Nuovo Utente', 'naval-egt'); ?></h3>
                    <p><?php _e('Crea un nuovo account per l\'area riservata Naval EGT.', 'naval-egt'); ?></p>
                    
                    <form id="add-user-form">
                        <?php wp_nonce_field('naval_egt_add_user', 'naval_egt_add_user_nonce'); ?>
                        
                        <div class="naval-egt-admin-form-row">
                            <div class="naval-egt-form-group">
                                <label for="add_first_name"><?php _e('Nome *', 'naval-egt'); ?></label>
                                <input type="text" id="add_first_name" name="first_name" required>
                            </div>
                            <div class="naval-egt-form-group">
                                <label for="add_last_name"><?php _e('Cognome *', 'naval-egt'); ?></label>
                                <input type="text" id="add_last_name" name="last_name" required>
                            </div>
                        </div>
                        
                        <div class="naval-egt-admin-form-row">
                            <div class="naval-egt-form-group">
                                <label for="add_email"><?php _e('Email *', 'naval-egt'); ?></label>
                                <input type="email" id="add_email" name="email" required>
                            </div>
                            <div class="naval-egt-form-group">
                                <label for="add_company"><?php _e('Azienda *', 'naval-egt'); ?></label>
                                <input type="text" id="add_company" name="company" required>
                            </div>
                        </div>
                        
                        <div class="naval-egt-admin-form-row">
                            <div class="naval-egt-form-group">
                                <label for="add_username"><?php _e('Username (opzionale)', 'naval-egt'); ?></label>
                                <input type="text" id="add_username" name="username">
                                <div class="description"><?php _e('Se vuoto, sarà generato automaticamente dall\'email', 'naval-egt'); ?></div>
                            </div>
                            <div class="naval-egt-form-group">
                                <label for="add_phone"><?php _e('Telefono', 'naval-egt'); ?></label>
                                <input type="tel" id="add_phone" name="phone">
                            </div>
                        </div>
                        
                        <div class="naval-egt-admin-form-row">
                            <div class="naval-egt-form-group">
                                <label for="add_password"><?php _e('Password (opzionale)', 'naval-egt'); ?></label>
                                <input type="password" id="add_password" name="password">
                                <div class="description"><?php _e('Se vuota, sarà generata automaticamente', 'naval-egt'); ?></div>
                            </div>
                            <div class="naval-egt-form-group">
                                <label for="add_status"><?php _e('Status', 'naval-egt'); ?></label>
                                <select id="add_status" name="status">
                                    <option value="active"><?php _e('Attivo', 'naval-egt'); ?></option>
                                    <option value="pending"><?php _e('In attesa', 'naval-egt'); ?></option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="naval-egt-form-group">
                            <label>
                                <input type="checkbox" id="send_welcome_email" name="send_welcome_email" value="1" checked>
                                <?php _e('Invia email di benvenuto con le credenziali di accesso', 'naval-egt'); ?>
                            </label>
                        </div>
                        
                        <div class="naval-egt-admin-flex naval-egt-admin-flex-gap-10">
                            <button type="submit" class="naval-egt-admin-btn naval-egt-admin-btn-primary">
                                <?php _e('✅ Crea Utente', 'naval-egt'); ?>
                            </button>
                            <button type="reset" class="naval-egt-admin-btn naval-egt-admin-btn-secondary">
                                <?php _e('🔄 Reset Form', 'naval-egt'); ?>
                            </button>
                        </div>
                        
                        <div id="add-user-result" style="margin-top: 20px;"></div>
                    </form>
                </div>
            </div>

            <!-- Files Tab -->
            <div id="files" class="naval-egt-admin-tab-pane">
                <div class="naval-egt-admin-form">
                    <h3><?php _e('📁 Gestione File Globale', 'naval-egt'); ?></h3>
                    <p><?php _e('Carica file per specifici utenti o gestisci i file esistenti.', 'naval-egt'); ?></p>
                    
                    <div class="naval-egt-admin-form-row">
                        <div class="naval-egt-form-group">
                            <label for="select-user-for-upload"><?php _e('Seleziona Utente', 'naval-egt'); ?></label>
                            <select id="select-user-for-upload" class="widefat">
                                <option value=""><?php _e('Seleziona un utente...', 'naval-egt'); ?></option>
                                <?php
                                $all_users = get_users(array('role' => 'naval_egt_client'));
                                foreach ($all_users as $user) {
                                    $company = get_user_meta($user->ID, 'naval_egt_company', true);
                                    echo '<option value="' . $user->ID . '">' . esc_html($user->display_name) . 
                                         ($company ? ' (' . esc_html($company) . ')' : '') . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="naval-egt-form-group">
                            <label for="select-folder-for-upload"><?php _e('Cartella di Destinazione', 'naval-egt'); ?></label>
                            <select id="select-folder-for-upload" class="widefat">
                                <option value="downloads/"><?php _e('Downloads (visibile all\'utente)', 'naval-egt'); ?></option>
                                <option value="shared/"><?php _e('Shared (cartella condivisa)', 'naval-egt'); ?></option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="naval-egt-form-group">
                        <label for="admin-bulk-file-upload"><?php _e('Carica File', 'naval-egt'); ?></label>
                        <div class="naval-egt-admin-file-upload" id="admin-file-upload-area">
                            <div class="naval-egt-admin-file-upload-icon">📁</div>
                            <div class="naval-egt-admin-file-upload-text"><?php _e('Clicca per selezionare i file o trascinali qui', 'naval-egt'); ?></div>
                            <div class="naval-egt-admin-file-upload-subtext"><?php _e('Supporta PDF, DOC, XLS, immagini, file CAD (Max 50MB)', 'naval-egt'); ?></div>
                            <input type="file" id="admin-bulk-file-upload" multiple style="display: none;" 
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.jpg,.jpeg,.png,.gif,.dwg,.dxf">
                        </div>
                        <div id="selected-files-list" style="margin-top: 10px;"></div>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div id="upload-progress" style="display: none; margin: 20px 0;">
                        <div class="naval-egt-progress-bar" style="width: 100%; height: 8px; background: #f0f0f1; border-radius: 4px; overflow: hidden;">
                            <div id="upload-progress-fill" style="height: 100%; background: linear-gradient(90deg, #2a5298, #1e3c72); width: 0%; transition: width 0.3s ease;"></div>
                        </div>
                        <div id="upload-status" style="text-align: center; margin-top: 10px; font-size: 14px; color: #666;"></div>
                    </div>
                    
                    <div class="naval-egt-admin-flex naval-egt-admin-flex-gap-10">
                        <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-primary" id="btn-admin-upload">
                            <?php _e('📤 Carica File per Utente', 'naval-egt'); ?>
                        </button>
                        <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-secondary" onclick="resetFileUpload()">
                            <?php _e('🔄 Reset Selezione', 'naval-egt'); ?>
                        </button>
                    </div>
                </div>

                <!-- File Statistics -->
                <div class="naval-egt-admin-form">
                    <h3><?php _e('📊 Statistiche File', 'naval-egt'); ?></h3>
                    <div class="naval-egt-admin-settings-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                        <?php
                        $file_stats = $wpdb->get_results("
                            SELECT 
                                action,
                                COUNT(*) as count,
                                SUM(file_size) as total_size
                            FROM $logs_table 
                            WHERE action IN ('upload', 'download') 
                            GROUP BY action
                        ");
                        
                        foreach ($file_stats as $stat):
                        ?>
                        <div class="naval-egt-admin-card">
                            <h3><?php echo $stat->action === 'upload' ? '⬆️ Upload' : '⬇️ Download'; ?></h3>
                            <div class="naval-egt-stat-number"><?php echo intval($stat->count); ?></div>
                            <div class="naval-egt-stat-label"><?php echo size_format($stat->total_size ?: 0); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Logs Tab -->
            <div id="logs" class="naval-egt-admin-tab-pane">
                <div id="logs-table-container">
                    <div class="naval-egt-admin-loading">
                        <div class="naval-egt-admin-loading-spinner"></div>
                        <p><?php _e('Caricamento log...', 'naval-egt'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Tools Tab -->
            <div id="tools" class="naval-egt-admin-tab-pane">
                <div class="naval-egt-admin-settings-grid">
                    <!-- Database Tools -->
                    <div class="naval-egt-admin-form">
                        <h3><?php _e('🔧 Strumenti Database', 'naval-egt'); ?></h3>
                        <p><?php _e('Strumenti per la manutenzione del database e pulizia dei dati.', 'naval-egt'); ?></p>
                        
                        <div class="naval-egt-admin-flex naval-egt-admin-flex-gap-10" style="flex-direction: column; align-items: flex-start;">
                            <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-warning" onclick="cleanupOldLogs()">
                                <?php _e('🗑️ Pulisci Log Vecchi (>90 giorni)', 'naval-egt'); ?>
                            </button>
                            <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-secondary" onclick="validateFtpFolders()">
                                <?php _e('✅ Valida Cartelle FTP', 'naval-egt'); ?>
                            </button>
                            <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-success" onclick="refreshStats()">
                                <?php _e('📈 Aggiorna Statistiche', 'naval-egt'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Export Tools -->
                    <div class="naval-egt-admin-form">
                        <h3><?php _e('📊 Strumenti di Esportazione', 'naval-egt'); ?></h3>
                        <p><?php _e('Esporta dati per backup o analisi esterne.', 'naval-egt'); ?></p>
                        
                        <div class="naval-egt-admin-flex naval-egt-admin-flex-gap-10" style="flex-direction: column; align-items: flex-start;">
                            <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-primary btn-export-users">
                                <?php _e('👥 Esporta Tutti gli Utenti (CSV)', 'naval-egt'); ?>
                            </button>
                            <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-primary btn-export-logs">
                                <?php _e('📋 Esporta Log Attività (CSV)', 'naval-egt'); ?>
                            </button>
                            <button type="button" class="naval-egt-admin-btn naval-egt-admin-btn-secondary" onclick="exportSystemInfo()">
                                <?php _e('💻 Esporta Info Sistema', 'naval-egt'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- System Info -->
                    <div class="naval-egt-admin-form">
                        <h3><?php _e('💻 Informazioni Sistema', 'naval-egt'); ?></h3>
                        <div class="naval-egt-system-info">
                            <p><strong><?php _e('Versione Plugin:', 'naval-egt'); ?></strong> <?php echo NAVAL_EGT_VERSION; ?></p>
                            <p><strong><?php _e('WordPress:', 'naval-egt'); ?></strong> <?php echo get_bloginfo('version'); ?></p>
                            <p><strong><?php _e('PHP:', 'naval-egt'); ?></strong> <?php echo PHP_VERSION; ?></p>
                            <p><strong><?php _e('Server:', 'naval-egt'); ?></strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></p>
                            <p><strong><?php _e('Max Upload Size:', 'naval-egt'); ?></strong> <?php echo size_format(wp_max_upload_size()); ?></p>
                            <p><strong><?php _e('Memory Limit:', 'naval-egt'); ?></strong> <?php echo ini_get('memory_limit'); ?></p>
                            <p><strong><?php _e('Directory FTP:', 'naval-egt'); ?></strong> 
                               <?php 
                               $upload_dir = wp_upload_dir();
                               echo $upload_dir['basedir'] . '/naval-egt-ftp/';
                               ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Inizializza la gestione dei tab
    initializeTabs();
    
    // Form aggiunta utente
    $('#add-user-form').on('submit', function(e) {
        e.preventDefault();
        handleAddUser();
    });
    
    // Gestione file upload
    initializeFileUpload();
    
    // Carica utenti nel primo tab
    loadUsersTable();
});

// Funzione per inizializzare i tab
function initializeTabs() {
    jQuery('.naval-egt-admin-tab-nav button').on('click', function() {
        const tab = jQuery(this).data('tab');
        switchToTab(tab);
    });
}

// Funzione per cambiare tab
function switchToTab(tabName) {
    // Aggiorna navigazione
    jQuery('.naval-egt-admin-tab-nav button').removeClass('active');
    jQuery('[data-tab="' + tabName + '"]').addClass('active');
    
    // Mostra il contenuto del tab
    jQuery('.naval-egt-admin-tab-pane').removeClass('active');
    jQuery('#' + tabName).addClass('active');
    
    // Carica dati specifici del tab se necessario
    if (tabName === 'users') {
        loadUsersTable();
    } else if (tabName === 'logs') {
        loadLogs();
    }
}

// Gestione aggiunta utente
function handleAddUser() {
    const form = jQuery('#add-user-form');
    const resultDiv = jQuery('#add-user-result');
    const submitBtn = form.find('button[type="submit"]');
    
    // Mostra loading
    submitBtn.prop('disabled', true).html('⏳ Creazione in corso...');
    resultDiv.html('');
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: form.serialize() + '&action=naval_egt_add_user&nonce=' + naval_egt_admin.nonce,
        success: function(response) {
            console.log('Add user response:', response);
            
            if (response.success) {
                resultDiv.html(`
                    <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745;">
                        <strong>✅ Utente creato con successo!</strong><br>
                        <strong>ID:</strong> ${response.data.user_id}<br>
                        <strong>Username:</strong> ${response.data.username}<br>
                        <small>📧 Email di benvenuto inviata all'utente</small>
                    </div>
                `);
                
                // Reset del form
                form[0].reset();
                
                // Aggiorna statistiche
                updateDashboardStats();
                
                // Se siamo nel tab users, ricarica la tabella
                if (jQuery('#users').hasClass('active')) {
                    setTimeout(loadUsersTable, 1000);
                }
                
            } else {
                resultDiv.html(`
                    <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; border-left: 4px solid #dc3545;">
                        <strong>❌ Errore:</strong> ${response.data?.message || 'Errore sconosciuto'}
                    </div>
                `);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            resultDiv.html(`
                <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; border-left: 4px solid #dc3545;">
                    <strong>❌ Errore di connessione:</strong> ${error}
                </div>
            `);
        },
        complete: function() {
            submitBtn.prop('disabled', false).html('✅ Crea Utente');
        }
    });
}

// Gestione file upload
function initializeFileUpload() {
    const $ = jQuery;
    
    // Click handler per l'area di upload
    $('#admin-file-upload-area').on('click', function() {
        $('#admin-bulk-file-upload').click();
    });
    
    // Gestione selezione file
    $('#admin-bulk-file-upload').on('change', function() {
        displaySelectedFiles(this.files);
    });
    
    // Drag & Drop
    $('#admin-file-upload-area')
        .on('dragenter dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
        })
        .on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
        })
        .on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
            
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                $('#admin-bulk-file-upload')[0].files = files;
                displaySelectedFiles(files);
            }
        });
    
    // Pulsante upload
    $('#btn-admin-upload').on('click', function() {
        handleFileUpload();
    });
}

// Mostra file selezionati
function displaySelectedFiles(files) {
    const filesList = jQuery('#selected-files-list');
    let html = '';
    
    if (files.length > 0) {
        html = '<div style="background: #f9f9f9; padding: 10px; border-radius: 5px; margin-top: 10px;">';
        html += '<strong>📎 File selezionati:</strong><br>';
        
        for (let i = 0; i < files.length; i++) {
            const size = (files[i].size / 1024 / 1024).toFixed(2);
            html += `<div style="margin: 5px 0; padding: 5px; background: white; border-radius: 3px;">
                📄 ${files[i].name} <small>(${size} MB)</small>
            </div>`;
        }
        html += '</div>';
        
        // Aggiorna il testo dell'area upload
        jQuery('.naval-egt-admin-file-upload-text').text(`${files.length} file selezionati`);
    }
    
    filesList.html(html);
}

// Gestione upload file
function handleFileUpload() {
    const userId = jQuery('#select-user-for-upload').val();
    const folder = jQuery('#select-folder-for-upload').val();
    const files = jQuery('#admin-bulk-file-upload')[0].files;
    
    if (!userId) {
        showNotice('⚠️ Seleziona un utente', 'warning');
        return;
    }
    
    if (files.length === 0) {
        showNotice('⚠️ Seleziona almeno un file', 'warning');
        return;
    }
    
    // Mostra progress bar
    jQuery('#upload-progress').show();
    jQuery('#upload-status').text('Preparazione upload...');
    
    // Upload di ogni file
    uploadFilesSequentially(files, userId, folder, 0);
}

// Upload sequenziale dei file
function uploadFilesSequentially(files, userId, folder, index) {
    if (index >= files.length) {
        // Tutti i file sono stati caricati
        jQuery('#upload-progress').hide();
        showNotice('✅ Tutti i file sono stati caricati con successo!', 'success');
        resetFileUpload();
        return;
    }
    
    const file = files[index];
    const formData = new FormData();
    formData.append('action', 'naval_egt_admin_upload_file_for_user');
    formData.append('nonce', naval_egt_admin.nonce);
    formData.append('user_id', userId);
    formData.append('subfolder', folder);
    formData.append('file', file);
    
    // Aggiorna status
    jQuery('#upload-status').text(`Caricamento ${index + 1} di ${files.length}: ${file.name}`);
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        xhr: function() {
            const xhr = new window.XMLHttpRequest();
            xhr.upload.addEventListener('progress', function(evt) {
                if (evt.lengthComputable) {
                    const fileProgress = (evt.loaded / evt.total) * 100;
                    const totalProgress = ((index + (fileProgress / 100)) / files.length) * 100;
                    jQuery('#upload-progress-fill').css('width', totalProgress + '%');
                }
            }, false);
            return xhr;
        },
        success: function(response) {
            if (response.success) {
                // Passa al file successivo
                uploadFilesSequentially(files, userId, folder, index + 1);
            } else {
                jQuery('#upload-progress').hide();
                showNotice('❌ Errore durante il caricamento: ' + response.data.message, 'error');
            }
        },
        error: function() {
            jQuery('#upload-progress').hide();
            showNotice('❌ Errore durante il caricamento del file: ' + file.name, 'error');
        }
    });
}

// Reset upload
function resetFileUpload() {
    jQuery('#admin-bulk-file-upload').val('');
    jQuery('#selected-files-list').html('');
    jQuery('.naval-egt-admin-file-upload-text').text('Clicca per selezionare i file o trascinali qui');
    jQuery('#upload-progress').hide();
    jQuery('#upload-progress-fill').css('width', '0%');
}

// Carica tabella utenti
function loadUsersTable() {
    jQuery('#users-table-container').html(`
        <div class="naval-egt-admin-loading">
            <div class="naval-egt-admin-loading-spinner"></div>
            <p>Caricamento utenti...</p>
        </div>
    `);
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'naval_egt_admin_list_users',
            nonce: naval_egt_admin.nonce,
            per_page: 20
        },
        success: function(response) {
            if (response.success && response.data.users) {
                renderUsersTable(response.data.users);
            } else {
                jQuery('#users-table-container').html('<div class="naval-egt-admin-empty">Errore durante il caricamento</div>');
            }
        },
        error: function() {
            jQuery('#users-table-container').html('<div class="naval-egt-admin-empty">Errore di connessione</div>');
        }
    });
}

// Render tabella utenti
function renderUsersTable(users) {
    let html = `
        <div class="naval-egt-admin-table-container">
            <div class="naval-egt-admin-table-header">
                <h3>👥 Lista Utenti (${users.length})</h3>
                <div class="naval-egt-admin-table-filters">
                    <button class="naval-egt-admin-btn naval-egt-admin-btn-primary btn-export-users">📊 Esporta CSV</button>
                </div>
            </div>
            <table class="naval-egt-admin-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Azienda</th>
                        <th>Status</th>
                        <th>Registrato</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    if (users.length === 0) {
        html += '<tr><td colspan="6" class="naval-egt-admin-empty">Nessun utente trovato</td></tr>';
    } else {
        users.forEach(user => {
            const statusClass = `naval-egt-status-${user.status}`;
            const statusText = getStatusText(user.status);
            const formattedDate = new Date(user.registered).toLocaleDateString('it-IT');
            
            html += `
                <tr>
                    <td><strong>${user.display_name}</strong></td>
                    <td>${user.email}</td>
                    <td>${user.company || '-'}</td>
                    <td><span class="naval-egt-status-badge ${statusClass}">${statusText}</span></td>
                    <td>${formattedDate}</td>
                    <td class="naval-egt-actions">
                        <button class="naval-egt-btn-sm naval-egt-btn-edit" onclick="editUser(${user.id})">✏️</button>
                        <button class="naval-egt-btn-sm ${user.status === 'active' ? 'naval-egt-btn-suspend' : 'naval-egt-btn-activate'}" 
                                onclick="toggleUserStatus(${user.id}, '${user.status}')">
                            ${user.status === 'active' ? '⏸️' : '▶️'}
                        </button>
                        <button class="naval-egt-btn-sm naval-egt-btn-delete" onclick="deleteUser(${user.id})">🗑️</button>
                    </td>
                </tr>
            `;
        });
    }
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    jQuery('#users-table-container').html(html);
}

// Utility functions
function getStatusText(status) {
    const statusTexts = {
        'active': 'Attivo',
        'pending': 'In attesa',
        'suspended': 'Sospeso'
    };
    return statusTexts[status] || status;
}

function showNotice(message, type = 'info') {
    const noticeClass = 'naval-egt-admin-notice-' + type;
    const notice = jQuery(`
        <div class="naval-egt-admin-notice ${noticeClass}" style="margin: 15px 0; padding: 12px 15px; border-radius: 4px;">
            <p style="margin: 0;">${message}</p>
        </div>
    `);
    
    // Rimuovi notice esistenti
    jQuery('.naval-egt-admin-notice').remove();
    
    // Aggiungi nuova notice
    jQuery('.naval-egt-admin-wrap').prepend(notice);
    
    // Auto-hide dopo 5 secondi
    setTimeout(function() {
        notice.fadeOut(500, function() {
            jQuery(this).remove();
        });
    }, 5000);
}

function updateDashboardStats() {
    // Ricarica le statistiche del dashboard
    location.reload();
}

// Export functions
jQuery(document).on('click', '.btn-export-users', function() {
    window.location.href = ajaxurl + '?action=naval_egt_export_users&nonce=' + naval_egt_admin.nonce;
});

jQuery(document).on('click', '.btn-export-logs', function() {
    window.location.href = ajaxurl + '?action=naval_egt_export_logs&nonce=' + naval_egt_admin.nonce;
});

// User management functions
function editUser(userId) {
    showNotice('🚧 Funzionalità di modifica utente in sviluppo', 'info');
}

function toggleUserStatus(userId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'suspended' : 'active';
    const action = newStatus === 'active' ? 'attivare' : 'sospendere';
    
    if (!confirm(`Sei sicuro di voler ${action} questo utente?`)) {
        return;
    }
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'naval_egt_admin_update_user_status',
            nonce: naval_egt_admin.nonce,
            user_id: userId,
            status: newStatus
        },
        success: function(response) {
            if (response.success) {
                showNotice('✅ Status utente aggiornato', 'success');
                loadUsersTable();
            } else {
                showNotice('❌ Errore durante l\'aggiornamento', 'error');
            }
        }
    });
}

function deleteUser(userId) {
    if (!confirm('⚠️ Sei sicuro di voler eliminare questo utente?\n\nQuesta azione eliminerà:\n- L\'account utente\n- Tutti i suoi file\n- I log delle attività\n\nL\'azione NON può essere annullata!')) {
        return;
    }
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'naval_egt_admin_delete_user',
            nonce: naval_egt_admin.nonce,
            user_id: userId
        },
        success: function(response) {
            if (response.success) {
                showNotice('✅ Utente eliminato con successo', 'success');
                loadUsersTable();
                updateDashboardStats();
            } else {
                showNotice('❌ Errore durante l\'eliminazione: ' + (response.data?.message || 'Errore sconosciuto'), 'error');
            }
        }
    });
}

// Tools functions
function cleanupOldLogs() {
    if (confirm('Sei sicuro di voler eliminare i log più vecchi di 90 giorni?')) {
        showNotice('🧹 Pulizia dei log in corso...', 'info');
        // Implementa la funzionalità
    }
}

function validateFtpFolders() {
    showNotice('🔍 Validazione cartelle FTP in corso...', 'info');
    // Implementa la funzionalità
}

function refreshStats() {
    showNotice('📈 Aggiornamento statistiche...', 'info');
    setTimeout(() => {
        location.reload();
    }, 1000);
}

function exportSystemInfo() {
    showNotice('💻 Export info sistema in sviluppo', 'info');
}

function loadLogs() {
    jQuery('#logs-table-container').html(`
        <div class="naval-egt-admin-loading">
            <div class="naval-egt-admin-loading-spinner"></div>
            <p>Caricamento log...</p>
        </div>
    `);
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'naval_egt_admin_get_logs',
            nonce: naval_egt_admin.nonce,
            per_page: 50
        },
        success: function(response) {
            if (response.success && response.data.logs) {
                renderLogsTable(response.data.logs);
            } else {
                jQuery('#logs-table-container').html('<div class="naval-egt-admin-empty">Errore durante il caricamento dei log</div>');
            }
        }
    });
}

function renderLogsTable(logs) {
    let html = `
        <div class="naval-egt-admin-table-container">
            <div class="naval-egt-admin-table-header">
                <h3>📋 Log Attività (${logs.length})</h3>
            </div>
            <table class="naval-egt-admin-table">
                <thead>
                    <tr>
                        <th>Data/Ora</th>
                        <th>Utente</th>
                        <th>Azione</th>
                        <th>File</th>
                        <th>Dimensione</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    if (logs.length === 0) {
        html += '<tr><td colspan="6" class="naval-egt-admin-empty">Nessun log trovato</td></tr>';
    } else {
        logs.forEach(log => {
            const date = new Date(log.created_at).toLocaleString('it-IT');
            const size = log.file_size ? (log.file_size / 1024 / 1024).toFixed(2) + ' MB' : '-';
            
            html += `
                <tr>
                    <td>${date}</td>
                    <td>
                        <strong>${log.display_name || 'Utente eliminato'}</strong>
                        ${log.user_email ? '<br><small>' + log.user_email + '</small>' : ''}
                    </td>
                    <td><span class="action-badge action-${log.action}">${getActionText(log.action)}</span></td>
                    <td>
                        ${log.file_name || '-'}
                        ${log.file_path ? '<br><small>' + log.file_path + '</small>' : ''}
                    </td>
                    <td>${size}</td>
                    <td>${log.ip_address || '-'}</td>
                </tr>
            `;
        });
    }
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    jQuery('#logs-table-container').html(html);
}

function getActionText(action) {
    const actionTexts = {
        'upload': '⬆️ Upload',
        'download': '⬇️ Download',
        'delete': '🗑️ Eliminazione',
        'admin_upload': '📤 Upload Admin',
        'create_folder': '📁 Nuova Cartella'
    };
    return actionTexts[action] || action;
}
</script>

<style>
/* Additional CSS for improved UI */
.naval-egt-admin-file-upload {
    border: 2px dashed #ccd0d4;
    border-radius: 8px;
    padding: 30px;
    text-align: center;
    background: #f9f9f9;
    transition: all 0.3s ease;
    cursor: pointer;
}

.naval-egt-admin-file-upload:hover, 
.naval-egt-admin-file-upload.dragover {
    border-color: #2a5298;
    background: #f0f4f8;
}

.naval-egt-admin-file-upload-icon {
    font-size: 3em;
    margin-bottom: 15px;
    color: #2a5298;
}

.naval-egt-admin-file-upload-text {
    color: #333;
    font-size: 16px;
    margin-bottom: 10px;
    font-weight: 500;
}

.naval-egt-admin-file-upload-subtext {
    color: #666;
    font-size: 14px;
}

.naval-egt-status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.naval-egt-status-active {
    background: #d4edda;
    color: #155724;
}

.naval-egt-status-pending {
    background: #fff3cd;
    color: #856404;
}

.naval-egt-status-suspended {
    background: #f8d7da;
    color: #721c24;
}

.action-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.action-upload, .action-admin_upload {
    background: #d4edda;
    color: #155724;
}

.action-download {
    background: #d1ecf1;
    color: #0c5460;
}

.action-delete {
    background: #f8d7da;
    color: #721c24;
}

.action-create_folder {
    background: #fff3cd;
    color: #856404;
}

.naval-egt-admin-notice {
    padding: 12px 15px;
    margin: 15px 0;
    border-left: 4px solid;
    border-radius: 4px;
    font-weight: 500;
}

.naval-egt-admin-notice-success {
    background: #d4edda;
    border-color: #28a745;
    color: #155724;
}

.naval-egt-admin-notice-error {
    background: #f8d7da;
    border-color: #dc3545;
    color: #721c24;
}

.naval-egt-admin-notice-warning {
    background: #fff3cd;
    border-color: #ffc107;
    color: #856404;
}

.naval-egt-admin-notice-info {
    background: #d1ecf1;
    border-color: #17a2b8;
    color: #0c5460;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.naval-egt-admin-loading-spinner {
    display: inline-block;
    width: 40px;
    height: 40px;
    border: 4px solid rgba(42, 82, 152, 0.3);
    border-top: 4px solid #2a5298;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 15px;
}
</style>