/**
 * Naval EGT Plugin - Admin JavaScript - Updated
 */

(function($) {
    'use strict';

    // Admin object
    const NavalEGTAdmin = {
        
        // Current data
        currentPage: 1,
        currentFilter: '',
        currentSearch: '',
        
        // Initialize admin functionality
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.initFileUpload();
            this.initUserManagement();
            console.log('🚀 Naval EGT Admin initialized');
        },

        // Bind all admin events
        bindEvents: function() {
            // Tab navigation
            $(document).on('click', '.naval-egt-admin-tab-nav button', this.switchTab);
            
            // User management
            $(document).on('submit', '#add-user-form', this.handleAddUser);
            $(document).on('click', '.btn-edit-user', this.editUser);
            $(document).on('click', '.btn-delete-user', this.deleteUser);
            $(document).on('click', '.btn-change-status', this.changeUserStatus);
            
            // File upload
            $(document).on('click', '#admin-file-upload-area', this.triggerFileSelect);
            $(document).on('change', '#admin-bulk-file-upload', this.handleFileSelect);
            $(document).on('click', '#btn-admin-upload', this.handleFileUpload);
            
            // Export functions
            $(document).on('click', '.btn-export-users', this.exportUsers);
            $(document).on('click', '.btn-export-logs', this.exportLogs);
            
            // Drag and drop for file upload
            this.initDragAndDrop();
        },

        // Initialize tabs
        initTabs: function() {
            const $firstTab = $('.naval-egt-admin-tab-nav button:first');
            if ($firstTab.length) {
                $firstTab.addClass('active');
                const targetPane = $firstTab.data('tab');
                $('.naval-egt-admin-tab-pane').removeClass('active');
                $(`#${targetPane}`).addClass('active');
            }
        },

        // Initialize file upload functionality
        initFileUpload: function() {
            console.log('🗂️ Initializing file upload system');
        },

        // Initialize user management
        initUserManagement: function() {
            // Load users table if we're on the users tab
            if ($('#users').hasClass('active')) {
                this.loadUsers();
            }
        },

        // Initialize drag and drop
        initDragAndDrop: function() {
            const uploadArea = $('#admin-file-upload-area');
            
            uploadArea.on('dragenter dragover', function(e) {
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
                if (files.length > 0) {
                    $('#admin-bulk-file-upload')[0].files = files;
                    NavalEGTAdmin.displaySelectedFiles(files);
                }
            });
        },

        // Switch tabs
        switchTab: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const targetTab = $btn.data('tab');
            
            // Update nav
            $('.naval-egt-admin-tab-nav button').removeClass('active');
            $btn.addClass('active');
            
            // Update content
            $('.naval-egt-admin-tab-pane').removeClass('active');
            $(`#${targetTab}`).addClass('active');
            
            // Load tab-specific data
            if (targetTab === 'users') {
                NavalEGTAdmin.loadUsers();
            } else if (targetTab === 'logs') {
                NavalEGTAdmin.loadLogs();
            }
            
            console.log(`📂 Switched to tab: ${targetTab}`);
        },

        // Handle add user form submission
        handleAddUser: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            const $resultDiv = $('#add-user-result');
            
            // Show loading state
            $submitBtn.prop('disabled', true).html('⏳ Creazione in corso...');
            $resultDiv.html('');
            
            console.log('👤 Creating new user...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: $form.serialize() + '&action=naval_egt_add_user',
                success: function(response) {
                    console.log('✅ Add user response:', response);
                    
                    if (response.success) {
                        $resultDiv.html(`
                            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745; margin-top: 15px;">
                                <strong>✅ Utente creato con successo!</strong><br>
                                <strong>ID:</strong> ${response.data.user_id}<br>
                                <strong>Username:</strong> ${response.data.username}<br>
                                <small>📧 Email di benvenuto inviata all'utente</small>
                            </div>
                        `);
                        
                        // Reset form
                        $form[0].reset();
                        
                        // Update dashboard stats
                        NavalEGTAdmin.updateDashboardStats();
                        
                        // If users tab is active, reload table
                        if ($('#users').hasClass('active')) {
                            setTimeout(() => NavalEGTAdmin.loadUsers(), 1000);
                        }
                        
                    } else {
                        $resultDiv.html(`
                            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; border-left: 4px solid #dc3545; margin-top: 15px;">
                                <strong>❌ Errore:</strong> ${response.data?.message || 'Errore sconosciuto durante la creazione dell\'utente'}
                            </div>
                        `);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ AJAX Error:', error, xhr.responseText);
                    $resultDiv.html(`
                        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; border-left: 4px solid #dc3545; margin-top: 15px;">
                            <strong>❌ Errore di connessione:</strong> ${error}<br>
                            <small>Controlla la console del browser per maggiori dettagli</small>
                        </div>
                    `);
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).html('✅ Crea Utente');
                }
            });
        },

        // Trigger file selection
        triggerFileSelect: function(e) {
            e.preventDefault();
            $('#admin-bulk-file-upload').click();
        },

        // Handle file selection
        handleFileSelect: function() {
            const files = this.files;
            NavalEGTAdmin.displaySelectedFiles(files);
        },

        // Display selected files
        displaySelectedFiles: function(files) {
            const $filesList = $('#selected-files-list');
            const $uploadText = $('.naval-egt-admin-file-upload-text');
            
            if (files.length === 0) {
                $filesList.html('');
                $uploadText.text('Clicca per selezionare i file o trascinali qui');
                return;
            }
            
            let html = '<div style="background: #f9f9f9; padding: 15px; border-radius: 8px; margin-top: 15px; border: 1px solid #e1e5e9;">';
            html += `<strong>📎 ${files.length} file selezionati:</strong><br><br>`;
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const size = (file.size / 1024 / 1024).toFixed(2);
                const sizeColor = file.size > 50 * 1024 * 1024 ? 'color: red;' : 'color: green;';
                
                html += `
                    <div style="display: flex; align-items: center; margin: 8px 0; padding: 10px; background: white; border-radius: 6px; border: 1px solid #e1e5e9;">
                        <span style="font-size: 1.5em; margin-right: 10px;">${NavalEGTAdmin.getFileIcon(file.name)}</span>
                        <div style="flex: 1;">
                            <strong>${file.name}</strong><br>
                            <small style="${sizeColor}">${size} MB</small>
                        </div>
                        ${file.size > 50 * 1024 * 1024 ? '<span style="color: red; font-weight: bold;">⚠️ Troppo grande</span>' : '<span style="color: green;">✅ OK</span>'}
                    </div>
                `;
            }
            html += '</div>';
            
            $filesList.html(html);
            $uploadText.text(`${files.length} file selezionati - Pronto per l'upload`);
            
            console.log(`📎 Selected ${files.length} files for upload`);
        },

        // Get file icon based on extension
        getFileIcon: function(filename) {
            const ext = filename.split('.').pop().toLowerCase();
            const icons = {
                'pdf': '📄',
                'doc': '📝', 'docx': '📝',
                'xls': '📊', 'xlsx': '📊',
                'ppt': '📊', 'pptx': '📊',
                'zip': '🗜️', 'rar': '🗜️', '7z': '🗜️',
                'jpg': '🖼️', 'jpeg': '🖼️', 'png': '🖼️', 'gif': '🖼️', 'bmp': '🖼️',
                'dwg': '📐', 'dxf': '📐',
                'txt': '📝', 'rtf': '📝'
            };
            return icons[ext] || '📄';
        },

        // Handle file upload
        handleFileUpload: function(e) {
            e.preventDefault();
            
            const userId = $('#select-user-for-upload').val();
            const folder = $('#select-folder-for-upload').val();
            const files = $('#admin-bulk-file-upload')[0].files;
            
            // Validation
            if (!userId) {
                NavalEGTAdmin.showNotice('⚠️ Seleziona un utente per l\'upload', 'warning');
                return;
            }
            
            if (files.length === 0) {
                NavalEGTAdmin.showNotice('⚠️ Seleziona almeno un file da caricare', 'warning');
                return;
            }
            
            // Check file sizes
            for (let i = 0; i < files.length; i++) {
                if (files[i].size > 50 * 1024 * 1024) {
                    NavalEGTAdmin.showNotice(`❌ Il file "${files[i].name}" è troppo grande (max 50MB)`, 'error');
                    return;
                }
            }
            
            console.log(`📤 Starting upload of ${files.length} files for user ${userId}`);
            
            // Show progress
            $('#upload-progress').show();
            $('#upload-status').text('Preparazione upload...');
            $('#upload-progress-fill').css('width', '0%');
            
            // Upload files sequentially
            NavalEGTAdmin.uploadFilesSequentially(files, userId, folder, 0);
        },

        // Upload files one by one
        uploadFilesSequentially: function(files, userId, folder, index) {
            if (index >= files.length) {
                // All files uploaded
                $('#upload-progress').hide();
                NavalEGTAdmin.showNotice(`✅ Tutti i ${files.length} file sono stati caricati con successo!`, 'success');
                NavalEGTAdmin.resetFileUpload();
                return;
            }
            
            const file = files[index];
            const formData = new FormData();
            formData.append('action', 'naval_egt_admin_upload_file_for_user');
            formData.append('nonce', naval_egt_admin.nonce);
            formData.append('user_id', userId);
            formData.append('subfolder', folder);
            formData.append('file', file);
            
            // Update status
            $('#upload-status').text(`Caricamento ${index + 1} di ${files.length}: ${file.name}`);
            
            console.log(`📤 Uploading file ${index + 1}/${files.length}: ${file.name}`);
            
            $.ajax({
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
                            $('#upload-progress-fill').css('width', totalProgress + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    console.log(`✅ File ${index + 1} uploaded:`, response);
                    
                    if (response.success) {
                        // Continue with next file
                        NavalEGTAdmin.uploadFilesSequentially(files, userId, folder, index + 1);
                    } else {
                        $('#upload-progress').hide();
                        NavalEGTAdmin.showNotice(`❌ Errore durante il caricamento di "${file.name}": ${response.data?.message || 'Errore sconosciuto'}`, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error(`❌ Upload error for file ${file.name}:`, error);
                    $('#upload-progress').hide();
                    NavalEGTAdmin.showNotice(`❌ Errore di connessione durante il caricamento di "${file.name}": ${error}`, 'error');
                }
            });
        },

        // Reset file upload
        resetFileUpload: function() {
            $('#admin-bulk-file-upload').val('');
            $('#selected-files-list').html('');
            $('.naval-egt-admin-file-upload-text').text('Clicca per selezionare i file o trascinali qui');
            $('#upload-progress').hide();
            $('#upload-progress-fill').css('width', '0%');
            $('#select-user-for-upload').val('');
            
            console.log('🔄 File upload reset');
        },

        // Load users table
        loadUsers: function(page = 1, search = '', status = '') {
            this.currentPage = page;
            this.currentSearch = search;
            this.currentFilter = status;
            
            const $container = $('#users-table-container');
            $container.html(`
                <div class="naval-egt-admin-loading">
                    <div class="naval-egt-admin-loading-spinner"></div>
                    <p>Caricamento utenti...</p>
                </div>
            `);
            
            console.log('👥 Loading users table...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'naval_egt_admin_list_users',
                    nonce: naval_egt_admin.nonce,
                    page: page,
                    per_page: 20,
                    search: search,
                    status: status
                },
                success: function(response) {
                    console.log('✅ Users loaded:', response);
                    
                    if (response.success && response.data.users) {
                        NavalEGTAdmin.renderUsersTable(response.data.users);
                    } else {
                        $container.html('<div class="naval-egt-admin-empty">❌ Errore durante il caricamento degli utenti</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Error loading users:', error);
                    $container.html('<div class="naval-egt-admin-empty">❌ Errore di connessione durante il caricamento</div>');
                }
            });
        },

        // Render users table
        renderUsersTable: function(users) {
            let html = `
                <div class="naval-egt-admin-table-container">
                    <div class="naval-egt-admin-table-header">
                        <h3>👥 Lista Utenti Naval EGT (${users.length})</h3>
                        <div class="naval-egt-admin-table-filters">
                            <input type="text" class="admin-search-input" placeholder="🔍 Cerca utenti..." value="${this.currentSearch}">
                            <select class="admin-filter-select" id="status-filter-select">
                                <option value="">Tutti gli status</option>
                                <option value="active" ${this.currentFilter === 'active' ? 'selected' : ''}>✅ Attivi</option>
                                <option value="pending" ${this.currentFilter === 'pending' ? 'selected' : ''}>⏳ In attesa</option>
                                <option value="suspended" ${this.currentFilter === 'suspended' ? 'selected' : ''}>⏸️ Sospesi</option>
                            </select>
                            <button class="naval-egt-admin-btn naval-egt-admin-btn-primary btn-export-users">📊 Esporta CSV</button>
                        </div>
                    </div>
                    <table class="naval-egt-admin-table">
                        <thead>
                            <tr>
                                <th>👤 Nome</th>
                                <th>📧 Email</th>
                                <th>🏢 Azienda</th>
                                <th>📊 Status</th>
                                <th>📅 Registrato</th>
                                <th>⚙️ Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            if (users.length === 0) {
                html += '<tr><td colspan="6" class="naval-egt-admin-empty">🔍 Nessun utente trovato con i criteri di ricerca</td></tr>';
            } else {
                users.forEach(user => {
                    const statusClass = `naval-egt-status-${user.status}`;
                    const statusText = NavalEGTAdmin.getStatusText(user.status);
                    const statusIcon = NavalEGTAdmin.getStatusIcon(user.status);
                    const formattedDate = new Date(user.registered).toLocaleDateString('it-IT', {
                        day: '2-digit',
                        month: '2-digit', 
                        year: 'numeric'
                    });
                    
                    html += `
                        <tr data-user-id="${user.id}">
                            <td>
                                <strong>${user.display_name}</strong><br>
                                <small style="color: #666;">@${user.username}</small>
                            </td>
                            <td>
                                <a href="mailto:${user.email}" style="color: #2a5298; text-decoration: none;">
                                    ${user.email}
                                </a>
                            </td>
                            <td>${user.company || '—'}</td>
                            <td>
                                <span class="naval-egt-status-badge ${statusClass}">
                                    ${statusIcon} ${statusText}
                                </span>
                            </td>
                            <td>${formattedDate}</td>
                            <td class="naval-egt-actions">
                                <button class="naval-egt-btn-sm naval-egt-btn-edit btn-edit-user" 
                                        data-user-id="${user.id}" title="Modifica utente">
                                    ✏️
                                </button>
                                <button class="naval-egt-btn-sm ${user.status === 'active' ? 'naval-egt-btn-suspend' : 'naval-egt-btn-activate'} btn-toggle-status" 
                                        data-user-id="${user.id}" data-current-status="${user.status}"
                                        title="${user.status === 'active' ? 'Sospendi utente' : 'Attiva utente'}">
                                    ${user.status === 'active' ? '⏸️' : '▶️'}
                                </button>
                                <button class="naval-egt-btn-sm naval-egt-btn-delete btn-delete-user" 
                                        data-user-id="${user.id}" title="Elimina utente">
                                    🗑️
                                </button>
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
            
            $('#users-table-container').html(html);
            
            // Bind search and filter events
            $('.admin-search-input').on('input', NavalEGTAdmin.debounce(function() {
                const search = $(this).val();
                NavalEGTAdmin.loadUsers(1, search, NavalEGTAdmin.currentFilter);
            }, 500));
            
            $('#status-filter-select').on('change', function() {
                const status = $(this).val();
                NavalEGTAdmin.loadUsers(1, NavalEGTAdmin.currentSearch, status);
            });
            
            // Bind user action events
            $('.btn-edit-user').on('click', NavalEGTAdmin.editUser);
            $('.btn-toggle-status').on('click', NavalEGTAdmin.toggleUserStatus);
            $('.btn-delete-user').on('click', NavalEGTAdmin.deleteUser);
            
            console.log(`✅ Users table rendered with ${users.length} users`);
        },

        // Get status text
        getStatusText: function(status) {
            const statusTexts = {
                'active': 'Attivo',
                'pending': 'In attesa',
                'suspended': 'Sospeso'
            };
            return statusTexts[status] || status;
        },

        // Get status icon
        getStatusIcon: function(status) {
            const statusIcons = {
                'active': '✅',
                'pending': '⏳',
                'suspended': '⏸️'
            };
            return statusIcons[status] || '❓';
        },

        // Edit user
        editUser: function(e) {
            e.preventDefault();
            const userId = $(this).data('user-id');
            
            NavalEGTAdmin.showNotice('🚧 Funzionalità di modifica utente in sviluppo', 'info');
            console.log(`✏️ Edit user requested for ID: ${userId}`);
        },

        // Toggle user status
        toggleUserStatus: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const userId = $btn.data('user-id');
            const currentStatus = $btn.data('current-status');
            const newStatus = currentStatus === 'active' ? 'suspended' : 'active';
            const actionText = newStatus === 'active' ? 'attivare' : 'sospendere';
            
            if (!confirm(`⚠️ Sei sicuro di voler ${actionText} questo utente?`)) {
                return;
            }
            
            console.log(`🔄 Toggling user ${userId} status from ${currentStatus} to ${newStatus}`);
            
            // Show loading on button
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('⏳');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'naval_egt_admin_update_user_status',
                    nonce: naval_egt_admin.nonce,
                    user_id: userId,
                    status: newStatus
                },
                success: function(response) {
                    console.log('✅ Status toggle response:', response);
                    
                    if (response.success) {
                        NavalEGTAdmin.showNotice(`✅ Status utente aggiornato a "${NavalEGTAdmin.getStatusText(newStatus)}"`, 'success');
                        // Reload users table
                        NavalEGTAdmin.loadUsers(NavalEGTAdmin.currentPage, NavalEGTAdmin.currentSearch, NavalEGTAdmin.currentFilter);
                        NavalEGTAdmin.updateDashboardStats();
                    } else {
                        NavalEGTAdmin.showNotice(`❌ Errore durante l'aggiornamento: ${response.data?.message || 'Errore sconosciuto'}`, 'error');
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Status toggle error:', error);
                    NavalEGTAdmin.showNotice(`❌ Errore di connessione: ${error}`, 'error');
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Delete user
        deleteUser: function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const userId = $btn.data('user-id');
            const $row = $btn.closest('tr');
            const userName = $row.find('td:first strong').text();
            
            const confirmMessage = `⚠️ ATTENZIONE: Stai per eliminare l'utente "${userName}"
            
Questa azione eliminerà PERMANENTEMENTE:
• L'account utente
• Tutti i suoi file FTP
• I log delle attività
• Tutti i dati associati

⚠️ L'azione NON può essere annullata!

Sei sicuro di voler procedere?`;
            
            if (!confirm(confirmMessage)) {
                return;
            }
            
            console.log(`🗑️ Deleting user ${userId} (${userName})`);
            
            // Show loading
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('⏳');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'naval_egt_admin_delete_user',
                    nonce: naval_egt_admin.nonce,
                    user_id: userId
                },
                success: function(response) {
                    console.log('✅ Delete user response:', response);
                    
                    if (response.success) {
                        NavalEGTAdmin.showNotice(`✅ Utente "${userName}" eliminato con successo`, 'success');
                        // Remove row with animation
                        $row.fadeOut(500, function() {
                            $(this).remove();
                        });
                        NavalEGTAdmin.updateDashboardStats();
                    } else {
                        NavalEGTAdmin.showNotice(`❌ Errore durante l'eliminazione: ${response.data?.message || 'Errore sconosciuto'}`, 'error');
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Delete user error:', error);
                    NavalEGTAdmin.showNotice(`❌ Errore di connessione: ${error}`, 'error');
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Load logs
        loadLogs: function() {
            const $container = $('#logs-table-container');
            $container.html(`
                <div class="naval-egt-admin-loading">
                    <div class="naval-egt-admin-loading-spinner"></div>
                    <p>Caricamento log attività...</p>
                </div>
            `);
            
            console.log('📋 Loading activity logs...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'naval_egt_admin_get_logs',
                    nonce: naval_egt_admin.nonce,
                    per_page: 50
                },
                success: function(response) {
                    console.log('✅ Logs loaded:', response);
                    
                    if (response.success && response.data.logs) {
                        NavalEGTAdmin.renderLogsTable(response.data.logs);
                    } else {
                        $container.html('<div class="naval-egt-admin-empty">❌ Errore durante il caricamento dei log</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Error loading logs:', error);
                    $container.html('<div class="naval-egt-admin-empty">❌ Errore di connessione</div>');
                }
            });
        },

        // Render logs table
        renderLogsTable: function(logs) {
            let html = `
                <div class="naval-egt-admin-table-container">
                    <div class="naval-egt-admin-table-header">
                        <h3>📋 Log Attività (${logs.length})</h3>
                        <div class="naval-egt-admin-table-filters">
                            <button class="naval-egt-admin-btn naval-egt-admin-btn-primary btn-export-logs">📊 Esporta Log CSV</button>
                        </div>
                    </div>
                    <table class="naval-egt-admin-table">
                        <thead>
                            <tr>
                                <th>📅 Data/Ora</th>
                                <th>👤 Utente</th>
                                <th>⚡ Azione</th>
                                <th>📄 File</th>
                                <th>📏 Dimensione</th>
                                <th>🌐 IP</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            if (logs.length === 0) {
                html += '<tr><td colspan="6" class="naval-egt-admin-empty">📭 Nessun log di attività trovato</td></tr>';
            } else {
                logs.forEach(log => {
                    const date = new Date(log.created_at).toLocaleString('it-IT', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    const size = log.file_size ? (log.file_size / 1024 / 1024).toFixed(2) + ' MB' : '—';
                    const actionIcon = NavalEGTAdmin.getActionIcon(log.action);
                    const actionText = NavalEGTAdmin.getActionText(log.action);
                    
                    html += `
                        <tr>
                            <td>${date}</td>
                            <td>
                                <strong>${log.display_name || 'Utente eliminato'}</strong>
                                ${log.user_email ? '<br><small style="color: #666;">' + log.user_email + '</small>' : ''}
                            </td>
                            <td>
                                <span class="action-badge action-${log.action}">
                                    ${actionIcon} ${actionText}
                                </span>
                            </td>
                            <td>
                                ${log.file_name ? '<strong>' + log.file_name + '</strong>' : '—'}
                                ${log.file_path ? '<br><small style="color: #666;">' + log.file_path + '</small>' : ''}
                            </td>
                            <td>${size}</td>
                            <td>${log.ip_address || '—'}</td>
                        </tr>
                    `;
                });
            }
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
            
            $('#logs-table-container').html(html);
            console.log(`✅ Logs table rendered with ${logs.length} entries`);
        },

        // Get action icon
        getActionIcon: function(action) {
            const actionIcons = {
                'upload': '⬆️',
                'download': '⬇️',
                'delete': '🗑️',
                'admin_upload': '📤',
                'create_folder': '📁'
            };
            return actionIcons[action] || '❓';
        },

        // Get action text
        getActionText: function(action) {
            const actionTexts = {
                'upload': 'Upload Utente',
                'download': 'Download',
                'delete': 'Eliminazione',
                'admin_upload': 'Upload Admin',
                'create_folder': 'Nuova Cartella'
            };
            return actionTexts[action] || action;
        },

        // Export functions
        exportUsers: function(e) {
            e.preventDefault();
            console.log('📊 Exporting users to CSV...');
            
            NavalEGTAdmin.showNotice('📊 Preparazione export utenti...', 'info');
            
            const exportUrl = ajaxurl + '?action=naval_egt_export_users&nonce=' + naval_egt_admin.nonce;
            window.location.href = exportUrl;
        },

        exportLogs: function(e) {
            e.preventDefault();
            console.log('📊 Exporting logs to CSV...');
            
            NavalEGTAdmin.showNotice('📊 Preparazione export log...', 'info');
            
            const exportUrl = ajaxurl + '?action=naval_egt_export_logs&nonce=' + naval_egt_admin.nonce;
            window.location.href = exportUrl;
        },

        // Update dashboard stats
        updateDashboardStats: function() {
            // This would typically reload specific stat elements
            // For now, we'll just reload the page after a short delay
            setTimeout(() => {
                location.reload();
            }, 2000);
            
            console.log('📈 Dashboard stats will be updated...');
        },

        // Show admin notice
        showNotice: function(message, type = 'info') {
            const noticeClass = 'naval-egt-admin-notice-' + type;
            const notice = $(`
                <div class="naval-egt-admin-notice ${noticeClass}" style="margin: 15px 0; padding: 12px 15px; border-radius: 4px; border-left: 4px solid;">
                    <p style="margin: 0; font-weight: 500;">${message}</p>
                </div>
            `);
            
            // Remove existing notices
            $('.naval-egt-admin-notice').remove();
            
            // Add new notice at the top
            $('.naval-egt-admin-wrap').prepend(notice);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                notice.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 5000);
            
            console.log(`📢 Notice: ${message} (${type})`);
        },

        // Debounce function for search
        debounce: function(func, wait, immediate) {
            let timeout;
            return function executedFunction() {
                const context = this;
                const args = arguments;
                const later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        }
    };

    // Global helper functions for backward compatibility
    window.switchToTab = function(tabName) {
        $('.naval-egt-admin-tab-nav button').removeClass('active');
        $(`[data-tab="${tabName}"]`).addClass('active');
        $('.naval-egt-admin-tab-pane').removeClass('active');
        $(`#${tabName}`).addClass('active');
        
        if (tabName === 'users') {
            NavalEGTAdmin.loadUsers();
        } else if (tabName === 'logs') {
            NavalEGTAdmin.loadLogs();
        }
    };

    window.resetFileUpload = function() {
        NavalEGTAdmin.resetFileUpload();
    };

    // Document ready
    $(document).ready(function() {
        NavalEGTAdmin.init();
        console.log('🎯 Naval EGT Admin System Ready!');
    });

    // Make NavalEGTAdmin globally available
    window.NavalEGTAdmin = NavalEGTAdmin;

})(jQuery);

// Additional helper functions
function loadUsersTable() {
    if (window.NavalEGTAdmin) {
        window.NavalEGTAdmin.loadUsersTable();
    }
}

function saveUserChanges() {
    if (window.NavalEGTAdmin) {
        const formData = $('#edit-user-form').serialize();
        
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData + '&action=naval_egt_update_user&nonce=' + naval_egt_admin.nonce,
            success: function(response) {
                if (response.success) {
                    window.NavalEGTAdmin.showNotice('Utente aggiornato con successo', 'success');
                    $('#edit-user-modal').hide();
                    window.NavalEGTAdmin.loadUsersTable();
                } else {
                    window.NavalEGTAdmin.showNotice(response.data?.message || 'Errore durante l\'aggiornamento', 'error');
                }
            }
        });
    }
}