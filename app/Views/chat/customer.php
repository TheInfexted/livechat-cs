<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/date.css?v=' . time()) ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/file-upload.css?v=' . time()) ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/image-display.css?v=' . time()) ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/voice-recording.css?v=' . time()) ?>">

<?php if (isset($is_fullscreen) && $is_fullscreen): ?>
<!-- Fullscreen Mode CSS -->
<link rel="stylesheet" href="<?= base_url('assets/css/chat-fullscreen.css?v=' . time()) ?>">
<?php endif; ?>

<div class="chat-container customer-chat">
    <div class="chat-header">
        <h3>Customer Support</h3>
        <div class="header-actions">
            <span class="status-indicator" id="connectionStatus">Offline</span>
            <button class="btn btn-notification-toggle" id="notificationToggle" onclick="toggleNotificationSound()" title="Toggle notification sound">
                <i class="bi bi-bell-fill" id="notificationIcon"></i>
            </button>
            <button class="btn btn-close-chat" id="customerCloseBtn" onclick="closeCustomerChat()" style="display: none;">Leave Chat</button>
            <?php if (isset($is_fullscreen) && $is_fullscreen): ?>
            <button class="btn btn-fullscreen-close" id="fullscreenCloseBtn" onclick="closeFullscreen()" title="Close Chat">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <div id="chatInterface">
        <?php if (isset($auto_session_error) && $auto_session_error): ?>
        <!-- Show error for failed auto-session creation -->
        <div class="chat-error">
            <h4>Chat Unavailable</h4>
            <div class="error-message">
                <p style="color: #dc3545; margin-bottom: 15px;">
                    <strong>Error:</strong> <?= htmlspecialchars($auto_session_error) ?>
                </p>
                <p>We're sorry for the inconvenience. Please try refreshing the page or contact support directly.</p>
                <button onclick="location.reload()" class="btn btn-primary">Try Again</button>
            </div>
        </div>
        <?php elseif (!$session_id): ?>
        <div class="chat-start-form">
            <h4>Start a Conversation</h4>
                    <form id="startChatForm">
                        <!-- Hidden fields for role-based information -->
                        <input type="hidden" name="user_role" value="<?= $user_role ?? 'anonymous' ?>">
                        <input type="hidden" name="external_username" value="<?= $external_username ?? '' ?>">
                        <input type="hidden" name="external_fullname" value="<?= $external_fullname ?? '' ?>">
                        <input type="hidden" name="external_system_id" value="<?= $external_system_id ?? '' ?>">
                        <input type="hidden" name="api_key" value="<?= $api_key ?? '' ?>">
                        <input type="hidden" name="customer_phone" value="<?= $customer_phone ?? '' ?>">
                        
                        <?php if ($user_role === 'loggedUser' && ($external_fullname || $external_username)): ?>
                            <!-- For logged users, show the name as read-only -->
                            <div class="form-group">
                                <label for="customerName">Your Name</label>
                                <input type="text" id="customerName" name="customer_name" value="<?= $external_fullname ?: $external_username ?>" readonly style="background-color: #f0f0f0;">
                                <small style="color: #666;">This information was provided by your system login.</small>
                            </div>
                        <?php else: ?>
                            <!-- For anonymous users, allow name input -->
                            <div class="form-group">
                                <label for="customerName">Your Name (Optional)</label>
                                <input type="text" id="customerName" name="customer_name" placeholder="Enter your name (or leave blank for Anonymous)">
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="customerProblem">What do you need help with? *</label>
                            <input type="text" id="customerProblem" name="chat_topic" required placeholder="Describe your issue or question...">
                        </div>
                        <div class="form-group">
                            <label for="customerEmail">Email (Optional)</label>
                            <?php if ($user_role === 'loggedUser' && !empty($external_email)): ?>
                            <input type="email" id="customerEmail" name="email" value="<?= esc($external_email) ?>" readonly style="background-color: #f0f0f0;">
                            <small style="color: #666;">This email was provided by your system login.</small>
                            <?php else: ?>
                            <input type="email" id="customerEmail" name="email" value="<?= esc($external_email ?? '') ?>">
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($user_role === 'loggedUser'): ?>
                            <p style="color: #28a745; font-size: 14px; margin-bottom: 15px;">
                                ✓ You are logged in as a verified user
                            </p>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn btn-primary">Start Chat</button>
                    </form>
        </div>
        <?php else: ?>
        <div class="chat-window customer-chat" data-session-id="<?= $session_id ?>">
            <div class="messages-container" id="messagesContainer">
                <div class="message system">
                    <p>Connecting to support...</p>
                </div>
            </div>
            
            <div class="typing-indicator" id="typingIndicator" style="display: none;">
                <span></span>
                <span></span>
                <span></span>
            </div>
            
            <!-- Quick Action Toolbar -->
            <div class="quick-actions-toolbar" id="quickActionsToolbar">
                <div class="quick-actions-buttons" id="quickActionsButtons">
                    <!-- Quick action buttons will be loaded here -->
                </div>
            </div>
            
            <div class="chat-input-area">
                <form id="messageForm">
                    <div class="input-group">
                        <input type="file" id="fileInput" class="file-input-hidden" onchange="handleFileSelect(event)" accept="*/*">
                        <button type="button" class="file-upload-btn" onclick="triggerFileUpload()" title="Attach file">
                            <i class="bi bi-paperclip"></i>
                        </button>
                        <button type="button" class="voice-record-btn" id="voiceRecordBtn" onclick="toggleVoiceRecording()" title="Record voice message">
                            <i class="bi bi-mic-fill"></i>
                        </button>
                        <input type="text" id="messageInput" class="form-control" placeholder="Type your message..." autocomplete="off">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-send">Send</button>
                        </div>
                    </div>
                </form>
                
                <!-- File Upload Progress -->
                <div id="fileUploadProgress" class="file-upload-progress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <span class="progress-text">Uploading file...</span>
                </div>
                
                <!-- File Preview -->
                <div id="filePreview" class="file-preview" style="display: none;">
                    <div class="preview-content">
                        <span class="file-info">
                            <i class="file-icon"></i>
                            <span class="file-name"></span>
                            <span class="file-size"></span>
                        </span>
                        <button type="button" class="btn-remove-file" onclick="removeFilePreview()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Voice Recording UI -->
                <div id="voiceRecordingUI" class="voice-recording-ui" style="display: none;">
                    <div class="recording-content">
                        <div class="recording-indicator">
                            <i class="bi bi-mic-fill recording-icon"></i>
                            <span class="recording-text">Recording...</span>
                            <span class="recording-timer" id="recordingTimer">00:00</span>
                        </div>
                        <button type="button" class="btn-cancel-recording" onclick="cancelVoiceRecording()" title="Cancel recording">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Voice Message Preview (before sending) -->
                <div id="voicePreview" class="voice-preview" style="display: none;">
                    <div class="preview-content">
                        <div class="voice-info">
                            <i class="bi bi-mic-fill" style="color: #667eea;"></i>
                            <span class="voice-duration" id="voiceDuration">00:00</span>
                            <span class="voice-label">Voice Message</span>
                        </div>
                        <button type="button" class="btn-remove-voice" onclick="removeVoicePreview()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

    <script>
    let userType = 'customer';
    let sessionId = '<?= $session_id ?? '' ?>';
    let currentSessionId = null;
    let baseUrl = '<?= base_url() ?>';
    
    // Role information for iframe integration
    let userRole = '<?= $user_role ?? 'anonymous' ?>';
    let externalUsername = '<?= $external_username ?? '' ?>';
    let externalFullname = '<?= $external_fullname ?? '' ?>';
    let externalSystemId = '<?= $external_system_id ?? '' ?>';
    let apiKey = '<?= $api_key ?? '' ?>';
    let customerPhone = '<?= $customer_phone ?? '' ?>';
    let externalEmail = '<?= $external_email ?? '' ?>';
    let isIframe = <?= $is_iframe ? 'true' : 'false' ?>;
    
    
    // Load quick actions when page loads
    document.addEventListener('DOMContentLoaded', function() {
        if (sessionId) {
            fetchQuickActions();
            // Initialize typing functionality for existing sessions
            initializeTypingForCustomer();
        }
    });

    function fetchQuickActions() {
        // Build URL with API key parameter if available
        let url = baseUrl + 'chat/quick-actions';
        if (apiKey) {
            url += '?api_key=' + encodeURIComponent(apiKey);
        }
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                const quickActionsButtons = document.getElementById('quickActionsButtons');
                if (!quickActionsButtons) return;
                
                quickActionsButtons.innerHTML = '';

                data.forEach(action => {
                    const btn = document.createElement('button');
                    btn.classList.add('quick-action-btn');
                    btn.textContent = action.display_name;
                    btn.onclick = () => sendQuickMessage(action.keyword);
                    quickActionsButtons.appendChild(btn);
                });
            })
            .catch(error => {
                // Fallback to hide the toolbar if quick actions fail to load
                const toolbar = document.getElementById('quickActionsToolbar');
                if (toolbar) {
                    toolbar.style.display = 'none';
                }
            });
    }

    function sendQuickMessage(keyword) {
        const messageInput = document.getElementById('messageInput');
        messageInput.value = keyword;
        
        // Trigger the form submission to send the message
        const messageForm = document.getElementById('messageForm');
        if (messageForm) {
            messageForm.dispatchEvent(new Event('submit'));
        }
    }
    
    // File upload functionality
    let selectedFile = null;
    
    // Drag and drop support
    document.addEventListener('DOMContentLoaded', function() {
        const chatInputArea = document.querySelector('.chat-input-area');
        if (chatInputArea) {
            chatInputArea.addEventListener('dragover', handleDragOver);
            chatInputArea.addEventListener('drop', handleFileDrop);
            chatInputArea.addEventListener('dragleave', handleDragLeave);
        }
    });
    
    function handleFileSelect(event) {
        const file = event.target.files[0];
        if (file) {
            selectedFile = file;
            // Ensure DOM is ready before showing preview
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => showFilePreview(file));
            } else {
                showFilePreview(file);
            }
        }
    }
    
    function handleDragOver(event) {
        event.preventDefault();
        event.stopPropagation();
        event.currentTarget.classList.add('drag-over');
    }
    
    function handleDragLeave(event) {
        event.preventDefault();
        event.stopPropagation();
        event.currentTarget.classList.remove('drag-over');
    }
    
    function handleFileDrop(event) {
        event.preventDefault();
        event.stopPropagation();
        event.currentTarget.classList.remove('drag-over');
        
        const files = event.dataTransfer.files;
        if (files.length > 0) {
            selectedFile = files[0];
            // Ensure DOM is ready before showing preview
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => showFilePreview(files[0]));
            } else {
                showFilePreview(files[0]);
            }
        }
    }
    
    function showFilePreview(file) {
        const preview = document.getElementById('filePreview');
        if (!preview) return;
        
        const fileName = preview.querySelector('.file-name');
        const fileSize = preview.querySelector('.file-size');
        const fileIcon = preview.querySelector('.file-icon');
        
        if (!fileName || !fileSize || !fileIcon) {
            console.error('File preview elements not found');
            return;
        }
        
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        
        // Set appropriate icon based on file type
        const extension = file.name.split('.').pop().toLowerCase();
        fileIcon.className = getFileIcon(extension);
        
        preview.style.display = 'block';
    }
    
    function removeFilePreview() {
        selectedFile = null;
        document.getElementById('filePreview').style.display = 'none';
        document.getElementById('fileInput').value = '';
    }
    
    // Send regular text message via WebSocket
    function sendTextMessage(message) {
        if (ws && ws.readyState === WebSocket.OPEN && sessionId) {
            const messageData = {
                type: 'message',
                session_id: sessionId,
                message: message,
                sender_type: 'customer',
                sender_id: null
            };
            
            ws.send(JSON.stringify(messageData));
            
            // Clear the message input
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.value = '';
            }
        }
    }
    
    // Trigger file upload dialog (similar to bo-livechat)
    function triggerFileUpload() {
        document.getElementById('fileInput').click();
    }
    
    // Handle form submission with file or text message
    function submitMessageOrFile(event) {
        if (event) event.preventDefault();
        
        if (selectedFile) {
            // Upload file instead of sending text message
            return uploadFile();
        } else {
            // Send regular text message
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            if (message) {
                sendTextMessage(message);
            }
        }
        
        return false;
    }
    
    function uploadFile() {
        if (!selectedFile || !sessionId) {
            return;
        }
        
        const formData = new FormData();
        formData.append('file', selectedFile);
        formData.append('session_id', sessionId);
        
        // Show progress
        showUploadProgress();
        
        fetch(baseUrl + 'chat/upload-file', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideUploadProgress();
            
            if (data.success) {
                // File uploaded successfully, remove preview
                removeFilePreview();
                
                // Send WebSocket notification for real-time updates
                if (ws && ws.readyState === WebSocket.OPEN && data.file_data) {
                    const fileMessage = {
                        type: 'file_message',
                        id: data.message_id,
                        session_id: sessionId,
                        sender_type: 'customer',
                        sender_id: null,
                        sender_name: data.file_data.customer_name || 'Customer',
                        message: '', // No text message, just show the file
                        message_type: data.file_data.file_type || 'file',
                        file_data: data.file_data,
                        timestamp: new Date().toISOString().slice(0, 19).replace('T', ' ')
                    };
                    
                    ws.send(JSON.stringify(fileMessage));
                }
                
                console.log('File uploaded successfully:', data.file_data);
            } else {
                alert('File upload failed: ' + data.error);
            }
        })
        .catch(error => {
            hideUploadProgress();
            console.error('File upload error:', error);
            alert('File upload failed. Please try again.');
        });
    }
    
    function showUploadProgress() {
        document.getElementById('fileUploadProgress').style.display = 'block';
        // Animate progress bar
        const progressFill = document.querySelector('.progress-fill');
        progressFill.style.width = '0%';
        
        // Simulate progress (in a real implementation, you'd track actual upload progress)
        let progress = 0;
        const interval = setInterval(() => {
            progress += Math.random() * 30;
            if (progress > 90) progress = 90;
            progressFill.style.width = progress + '%';
            
            if (progress >= 90) {
                clearInterval(interval);
            }
        }, 200);
    }
    
    function hideUploadProgress() {
        const progressElement = document.getElementById('fileUploadProgress');
        const progressFill = document.querySelector('.progress-fill');
        
        // Complete the progress
        progressFill.style.width = '100%';
        
        setTimeout(() => {
            progressElement.style.display = 'none';
            progressFill.style.width = '0%';
        }, 500);
    }
    
    function formatFileSize(bytes) {
        if (bytes >= 1073741824) {
            return (bytes / 1073741824).toFixed(2) + ' GB';
        } else if (bytes >= 1048576) {
            return (bytes / 1048576).toFixed(2) + ' MB';
        } else if (bytes >= 1024) {
            return (bytes / 1024).toFixed(2) + ' KB';
        } else {
            return bytes + ' B';
        }
    }
    
    function getFileIcon(extension) {
        const iconMap = {
            // Images
            'jpg': 'fas fa-image text-primary',
            'jpeg': 'fas fa-image text-primary',
            'png': 'fas fa-image text-primary',
            'gif': 'fas fa-image text-primary',
            'webp': 'fas fa-image text-primary',
            'bmp': 'fas fa-image text-primary',
            
            // Videos
            'mp4': 'fas fa-video text-danger',
            'avi': 'fas fa-video text-danger',
            'mov': 'fas fa-video text-danger',
            'wmv': 'fas fa-video text-danger',
            'flv': 'fas fa-video text-danger',
            'webm': 'fas fa-video text-danger',
            
            // Documents
            'pdf': 'fas fa-file-pdf text-danger',
            'doc': 'fas fa-file-word text-info',
            'docx': 'fas fa-file-word text-info',
            'txt': 'fas fa-file-alt text-info',
            'rtf': 'fas fa-file-alt text-info',
            
            // Archives
            'zip': 'fas fa-file-archive text-warning',
            'rar': 'fas fa-file-archive text-warning',
            '7z': 'fas fa-file-archive text-warning',
            'tar': 'fas fa-file-archive text-warning',
            'gz': 'fas fa-file-archive text-warning',
            
            // Spreadsheets
            'xls': 'fas fa-file-excel text-success',
            'xlsx': 'fas fa-file-excel text-success',
            'csv': 'fas fa-file-csv text-success',
            
            // Presentations
            'ppt': 'fas fa-file-powerpoint text-warning',
            'pptx': 'fas fa-file-powerpoint text-warning'
        };
        
        return iconMap[extension] || 'fas fa-file text-secondary';
    }
    
    // Function to handle customer leaving the chat (session closes for both customer and admin)
    function closeCustomerChat() {
        if (sessionId && confirm('Are you sure you want to leave this chat? This will close the chat session for both you and the agent.')) {
            // Get references to UI elements
            const closeBtn = document.getElementById('customerCloseBtn');
            const messageInput = document.getElementById('messageInput');
            const sendBtn = document.querySelector('.btn-send');
            
            // Function to reset UI state in case of error
            const resetUIState = () => {
                if (closeBtn) {
                    closeBtn.disabled = false;
                    closeBtn.textContent = 'Leave Chat';
                }
                if (messageInput) messageInput.disabled = false;
                if (sendBtn) sendBtn.disabled = false;
            };
            
            // Function to complete successful leave process
            const completeLeaveProcess = (message) => {
                // Show message that customer has left
                displaySystemMessage(message || 'You have left the chat. Thank you for contacting us!');
                
                // Clear the session ID so it can't be reused
                sessionId = null;
                currentSessionId = null;
                
                // Hide the close button
                if (closeBtn) {
                    closeBtn.style.display = 'none';
                }
                
                // Show start new chat interface after a delay
                setTimeout(() => {
                    showStartNewChatInterface();
                }, 2000);
            };
            
            // Disable the close button to prevent multiple clicks
            if (closeBtn) {
                closeBtn.disabled = true;
                closeBtn.textContent = 'Ending...';
            }
            
            // Disable message input
            if (messageInput) messageInput.disabled = true;
            if (sendBtn) sendBtn.disabled = true;
            
            // Set a timeout as a fallback in case the request hangs
            const timeoutId = setTimeout(() => {
                resetUIState();
                alert('The request timed out. Please try again.');
            }, 10000); // 10 second timeout
            
            // Additional timeout to force UI reset if everything else fails
            const forceResetTimeoutId = setTimeout(() => {
                resetUIState();
            }, 12000); // 12 second force reset
            
            // End the session completely (the HTTP request will handle the system message)
            fetch('/chat/end-customer-session', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `session_id=${sessionId}`
            })
            .then(response => {
                // Clear the timeout since we got a response
                clearTimeout(timeoutId);
                clearTimeout(forceResetTimeoutId);
                
                // Handle both success and error status codes
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                return response.json();
            })
            .then(result => {
                
                if (result && result.success) {
                    completeLeaveProcess(result.message);
                } else {
                    // Handle server-side errors
                    resetUIState();
                    alert(result?.error || 'Failed to end chat session. Please try again.');
                }
            })
            .catch(error => {
                // Clear the timeout since we caught an error
                clearTimeout(timeoutId);
                clearTimeout(forceResetTimeoutId);
                
                // Always reset UI state on any error
                resetUIState();
                
                // Show appropriate error message
                let errorMessage = 'Failed to end chat session. Please try again.';
                if (error.message && error.message.includes('Failed to fetch')) {
                    errorMessage = 'Network error. Please check your connection and try again.';
                } else if (error.message) {
                    errorMessage = `Error: ${error.message}`;
                }
                
                alert(errorMessage);
            });
        }
    }
    
    function displaySystemMessage(message) {
        const container = document.getElementById('messagesContainer');
        if (!container) return;
        
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message system';
        
        const p = document.createElement('p');
        p.textContent = message;
        
        messageDiv.appendChild(p);
        container.appendChild(messageDiv);
        
        container.scrollTop = container.scrollHeight;
    }
    
    // Function to show start new chat interface
    function showStartNewChatInterface() {
        const chatInterface = document.getElementById('chatInterface');
        if (chatInterface) {
            // For logged users, show simplified interface
            if (userRole === 'loggedUser' && (externalFullname || externalUsername)) {
                // Seamless experience for logged users - no form needed
                chatInterface.innerHTML = `
                    <div class="chat-start-form" style="text-align: center; padding: 30px;">
                        <h4>Chat Session Ended</h4>
                        <p style="color: #666; margin-bottom: 25px;">Thank you for contacting us. Your conversation has ended.</p>
                        <p style="color: #28a745; font-size: 14px; margin-bottom: 20px;">
                            ✓ Logged in as ${externalFullname || externalUsername}
                        </p>
                        <button type="button" class="btn btn-primary start-new-chat-btn-local" style="padding: 12px 24px; font-size: 16px;">
                            Start New Chat
                        </button>
                    </div>
                `;
            } else {
                // For anonymous users, show the full form
                const nameFieldHtml = `
                    <div class="form-group">
                        <label for="customerName">Your Name (Optional)</label>
                        <input type="text" id="customerName" name="customer_name" placeholder="Enter your name (or leave blank for Anonymous)">
                    </div>
                `;
                
                const roleFieldsHtml = `
                    <input type="hidden" name="user_role" value="${userRole}">
                    <input type="hidden" name="external_username" value="${externalUsername}">
                    <input type="hidden" name="external_fullname" value="${externalFullname}">
                    <input type="hidden" name="external_system_id" value="${externalSystemId}">
                    <input type="hidden" name="api_key" value="${apiKey}">
                    <input type="hidden" name="customer_phone" value="${customerPhone}">
                `;
                
                chatInterface.innerHTML = `
                    <div class="chat-start-form">
                        <h4>Start a New Conversation</h4>
                        <p style="color: #666; margin-bottom: 20px;">Your previous chat has ended. You can start a new conversation below:</p>
                        <form id="startChatForm">
                            ${roleFieldsHtml}
                            ${nameFieldHtml}
                            <div class="form-group">
                                <label for="customerProblem">What do you need help with? *</label>
                                <input type="text" id="customerProblem" name="chat_topic" required placeholder="Describe your issue or question...">
                            </div>
                            <div class="form-group">
                                <label for="customerEmail">Email (Optional)</label>
                                <input type="email" id="customerEmail" name="email" value="${externalEmail}">
                            </div>
                            <button type="submit" class="btn btn-primary">Start New Chat</button>
                        </form>
                    </div>
                `;
            }
        }
    }
    
    // Function to start new chat for logged users without form (local version)
    function startNewChatForLoggedUserLocal() {
        // Show loading state
        const chatInterface = document.getElementById('chatInterface');
        if (chatInterface) {
            chatInterface.innerHTML = `
                <div class="chat-loading" style="text-align: center; padding: 40px;">
                    <div class="loading-spinner"></div>
                    <h4>Starting new chat session...</h4>
                    <p class="loading-message">Please wait while we prepare your chat.</p>
                </div>
            `;
        }
        
        // Create form data with existing user information
        const formData = new FormData();
        formData.append('user_role', userRole);
        formData.append('external_username', externalUsername);
        formData.append('external_fullname', externalFullname);
        formData.append('external_system_id', externalSystemId);
        formData.append('api_key', apiKey);
        formData.append('customer_phone', customerPhone);
        formData.append('customer_name', externalFullname || externalUsername);
        formData.append('chat_topic', 'General Support'); // Default topic for logged users
        formData.append('email', externalEmail);
        
        
        // Start new session
        fetch('/chat/start-session', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(result => {
            if (result.success) {
                sessionId = result.session_id;
                currentSessionId = result.session_id;
                
                if (chatInterface) {
                    chatInterface.innerHTML = `
                        <div class="chat-window customer-chat" data-session-id="${result.session_id}">
                            <div class="messages-container" id="messagesContainer">
                                <div class="message system">
                                    <p>Connecting to support...</p>
                                </div>
                            </div>
                            
                            <div class="typing-indicator" id="typingIndicator" style="display: none;">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                            
                            <!-- Quick Action Toolbar -->
                            <div class="quick-actions-toolbar" id="quickActionsToolbar">
                                <div class="quick-actions-buttons" id="quickActionsButtons">
                                    <!-- Quick action buttons will be loaded here -->
                                </div>
                            </div>
                            
                            <div class="chat-input-area">
                                <form id="messageForm">
                                    <input type="text" id="messageInput" placeholder="Type your message..." autocomplete="off">
                                    <button type="submit" class="btn btn-send">Send</button>
                                </form>
                            </div>
                        </div>
                    `;
                    
                    // Initialize WebSocket and chat functionality
                    initWebSocket();
                    initializeMessageForm();
                    
                    // Load quick actions for the customer
                    setTimeout(() => {
                        fetchQuickActions();
                        // Initialize typing functionality
                        initializeTypingForCustomer();
                        
                        // Explicitly load chat history for the new session
                        // This ensures history loads even if WebSocket 'connected' event doesn't fire
                        if (typeof loadChatHistory === 'function') {
                            loadChatHistory();
                        }
                    }, 1000);
                }
            } else {
                // Show error and revert to previous state
                alert(result.error || 'Failed to start new chat session');
                showStartNewChatInterface();
            }
        })
        .catch(error => {
            alert('Failed to connect. Please try again.');
            showStartNewChatInterface();
        });
    }
    
    // Add event listener for Start New Chat button (local version)
    document.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('start-new-chat-btn-local')) {
            e.preventDefault();
            startNewChatForLoggedUserLocal();
        }
    });
    
    // Override the handleWebSocketMessage function to include our typing indicator logic
    if (typeof handleWebSocketMessage !== 'undefined') {
        const originalHandleWebSocketMessage = handleWebSocketMessage;
        
        window.handleWebSocketMessage = function(data) {
            // Call the original handler
            originalHandleWebSocketMessage(data);
            
            // Handle typing indicator specifically for customer interface
            if (data.type === 'typing') {
                const indicator = document.getElementById('typingIndicator');
                if (indicator && data.session_id === sessionId) {
                    // Show typing indicator only if agent is typing and it's not the customer
                    if (data.is_typing && data.user_type !== 'customer') {
                        indicator.style.display = 'flex';
                    } else {
                        indicator.style.display = 'none';
                    }
                }
            }
        };
    }
    
    // Add typing event listeners to message input (integrate with chat.js)
    function initializeTypingForCustomer() {
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            // Use the chat.js typing functionality if available
            if (typeof sendTypingIndicator === 'function') {
                messageInput.addEventListener('input', function() {
                    if (typeof isTyping === 'undefined' || !isTyping) {
                        sendTypingIndicator(true);
                    }
                    
                    if (typeof typingTimer !== 'undefined') {
                        clearTimeout(typingTimer);
                    }
                    
                    typingTimer = setTimeout(() => {
                        sendTypingIndicator(false);
                    }, 1000);
                });
                
                messageInput.addEventListener('blur', function() {
                    if (typeof isTyping !== 'undefined' && isTyping) {
                        sendTypingIndicator(false);
                    }
                });
            } else {
                // Fallback if chat.js typing functions are not available
                let localIsTyping = false;
                let localTypingTimer = null;
                
                messageInput.addEventListener('input', function() {
                    if (ws && ws.readyState === WebSocket.OPEN && sessionId) {
                        if (!localIsTyping) {
                            localIsTyping = true;
                            ws.send(JSON.stringify({
                                type: 'typing',
                                session_id: sessionId,
                                user_type: 'customer',
                                is_typing: true
                            }));
                        }
                        
                        clearTimeout(localTypingTimer);
                        localTypingTimer = setTimeout(() => {
                            localIsTyping = false;
                            ws.send(JSON.stringify({
                                type: 'typing',
                                session_id: sessionId,
                                user_type: 'customer',
                                is_typing: false
                            }));
                        }, 1000);
                    }
                });
                
                messageInput.addEventListener('blur', function() {
                    if (ws && ws.readyState === WebSocket.OPEN && sessionId && localIsTyping) {
                        localIsTyping = false;
                        ws.send(JSON.stringify({
                            type: 'typing',
                            session_id: sessionId,
                            user_type: 'customer',
                            is_typing: false
                        }));
                    }
                });
            }
        }
    }
    
    // Function to close the fullscreen chat iframe only
    function closeFullscreen() {
        // If we're in an iframe (which we should be for fullscreen chat),
        // send a message to the parent window to close the iframe
        if (window.parent !== window) {
            try {
                window.parent.postMessage({
                    type: 'close_fullscreen_chat',
                    source: 'livechat_iframe',
                    sessionId: sessionId
                }, '*');
            } catch (e) {
                console.error('Failed to send close message to parent:', e);
            }
        } else {
            // If we're not in an iframe, we might be in a popup or standalone window
            // In this case, we can try to close the window
            try {
                window.close();
            } catch (e) {
                // If we can't close, try to go back in history
                try {
                    window.history.back();
                } catch (historyError) {
                    // Last resort - show message
                    alert('Unable to close the chat. Please use your browser\'s back button or close this tab.');
                }
            }
        }
    }

</script>
<script src="<?= base_url('assets/js/voice-recording.js?v=' . time()) ?>"></script>
<?= $this->endSection() ?>