<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="chat-container customer-chat">
    <div class="chat-header">
        <h3>Customer Support</h3>
        <div class="header-actions">
            <span class="status-indicator" id="connectionStatus">Offline</span>
            <button class="btn btn-close-chat" id="customerCloseBtn" onclick="closeCustomerChat()" style="display: none;">Leave Chat</button>
        </div>
    </div>
    
    <div id="chatInterface">
        <?php if (!$session_id): ?>
        <div class="chat-start-form">
            <h4>Start a Conversation</h4>
                    <form id="startChatForm">
                        <!-- Hidden fields for role-based information -->
                        <input type="hidden" name="user_role" value="<?= $user_role ?? 'anonymous' ?>">
                        <input type="hidden" name="external_username" value="<?= $external_username ?? '' ?>">
                        <input type="hidden" name="external_fullname" value="<?= $external_fullname ?? '' ?>">
                        <input type="hidden" name="external_system_id" value="<?= $external_system_id ?? '' ?>">
                        
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
                            <input type="email" id="customerEmail" name="email">
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
        <div class="chat-window" data-session-id="<?= $session_id ?>">
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
    let isIframe = <?= $is_iframe ? 'true' : 'false' ?>;
    
    // Load quick actions when page loads
    document.addEventListener('DOMContentLoaded', function() {
        if (sessionId) {
            fetchQuickActions();
        }
        
        // Form submission handler is in chat.js - no need for duplicate here
    });

    function fetchQuickActions() {
        fetch(baseUrl + 'chat/quick-actions')
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
                console.error('Error fetching quick actions:', error);
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
    
    // Function to handle customer leaving the chat (session remains open for admin)
    function closeCustomerChat() {
        if (sessionId && confirm('Are you sure you want to leave this chat? The agent can still see the conversation and may respond.')) {
            // Disable the close button to prevent multiple clicks
            const closeBtn = document.getElementById('customerCloseBtn');
            if (closeBtn) {
                closeBtn.disabled = true;
                closeBtn.textContent = 'Ending...';
            }
            
            // Disable message input
            const messageInput = document.getElementById('messageInput');
            const sendBtn = document.querySelector('.btn-send');
            if (messageInput) messageInput.disabled = true;
            if (sendBtn) sendBtn.disabled = true;
            
            // End the session completely (the HTTP request will handle the system message)
            console.log('Ending chat session with ID:', sessionId);
            fetch('/chat/end-customer-session', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `session_id=${sessionId}`
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(result => {
                console.log('Response result:', result);
                if (result.success) {
                    // Show message that customer has left
                    displaySystemMessage(result.message || 'You have left the chat. Thank you for contacting us!');
                    
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
                } else {
                    // Re-enable buttons if there was an error
                    if (closeBtn) {
                        closeBtn.disabled = false;
                        closeBtn.textContent = 'End Chat';
                    }
                    if (messageInput) messageInput.disabled = false;
                    if (sendBtn) sendBtn.disabled = false;
                    
                    alert(result.error || 'Failed to end chat session. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error ending chat session:', error);
                
                // Re-enable buttons on error
                if (closeBtn) {
                    closeBtn.disabled = false;
                    closeBtn.textContent = 'End Chat';
                }
                if (messageInput) messageInput.disabled = false;
                if (sendBtn) sendBtn.disabled = false;
                
                alert('Failed to end chat session. Please try again.');
            });
        }
    }
    
    // Add missing displaySystemMessage function for customer view
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
            // Generate form HTML based on user role
            let nameFieldHtml = '';
            let roleFieldsHtml = '';
            let statusMessageHtml = '';
            
            if (userRole === 'loggedUser' && (externalFullname || externalUsername)) {
                // For logged users, show read-only name field
                const displayName = externalFullname || externalUsername;
                nameFieldHtml = `
                    <div class="form-group">
                        <label for="customerName">Your Name</label>
                        <input type="text" id="customerName" name="customer_name" value="${displayName}" readonly style="background-color: #f0f0f0;">
                        <small style="color: #666;">This information was provided by your system login.</small>
                    </div>
                `;
                statusMessageHtml = `
                    <p style="color: #28a745; font-size: 14px; margin-bottom: 15px;">
                        ✓ You are logged in as a verified user
                    </p>
                `;
            } else {
                // For anonymous users, show editable name field
                nameFieldHtml = `
                    <div class="form-group">
                        <label for="customerName">Your Name (Optional)</label>
                        <input type="text" id="customerName" name="customer_name" placeholder="Enter your name (or leave blank for Anonymous)">
                    </div>
                `;
            }
            
            // Add hidden fields for role information
            roleFieldsHtml = `
                <input type="hidden" name="user_role" value="${userRole}">
                <input type="hidden" name="external_username" value="${externalUsername}">
                <input type="hidden" name="external_fullname" value="${externalFullname}">
                <input type="hidden" name="external_system_id" value="${externalSystemId}">
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
                            <input type="email" id="customerEmail" name="email">
                        </div>
                        ${statusMessageHtml}
                        <button type="submit" class="btn btn-primary">Start New Chat</button>
                    </form>
                </div>
            `;
            
            // Form submission will be handled by the global chat.js event listener
        }
    }

</script>
<?= $this->endSection() ?>