let ws = null;
let reconnectInterval = null;
let typingTimer = null;
let isTyping = false;
let displayedMessages = new Set(); 
let messageQueue = []; 
let lastMessageTime = 0; 
const MESSAGE_RATE_LIMIT = 1000;
let isInitializing = false;

// Helper function to safely get DOM elements
function safeGetElement(id) {
    const element = document.getElementById(id);
    if (!element) {
        return null;
    }
    return element;
}

// Helper function to safely get session variables
function getSessionId() {
    // For admin, prioritize currentSessionId
    if (getUserType() === 'agent' && typeof currentSessionId !== 'undefined' && currentSessionId) {
        return currentSessionId;
    }
    return typeof sessionId !== 'undefined' ? sessionId : (typeof currentSessionId !== 'undefined' ? currentSessionId : null);
}

function getUserType() {
    return typeof userType !== 'undefined' ? userType : null;
}

function getUserId() {
    return typeof userId !== 'undefined' ? userId : null;
}

// Admin session refresh function
function refreshAdminSessions() {
    // Try to use JSON API first, fallback to HTML parsing
    fetch('/admin/sessions-data')
        .then(response => {
            if (response.ok) {
                return response.json();
            } else {
                // Fallback to HTML parsing
                return fetch('/admin/chat').then(r => r.text()).then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    const waitingSessions = [];
                    const activeSessions = [];
                    
                    // Parse waiting sessions
                    const waitingElements = doc.querySelectorAll('#waitingSessions .session-item');
                    waitingElements.forEach(el => {
                        const sessionId = el.getAttribute('data-session-id');
                        const name = el.querySelector('strong')?.textContent || '';
                        const time = el.querySelector('small')?.textContent || '';
                        waitingSessions.push({ session_id: sessionId, customer_name: name, created_at: time });
                    });
                    
                    // Parse active sessions
                    const activeElements = doc.querySelectorAll('#activeSessions .session-item');
                    activeElements.forEach(el => {
                        const sessionId = el.getAttribute('data-session-id');
                        const name = el.querySelector('strong')?.textContent || '';
                        const agent = el.querySelector('small')?.textContent || '';
                        activeSessions.push({ session_id: sessionId, customer_name: name, agent_name: agent });
                    });
                    
                    return { waitingSessions, activeSessions };
                });
            }
        })
        .then(data => {
            // Update waiting sessions
            const waitingContainer = document.getElementById('waitingSessions');
            const waitingCount = document.getElementById('waitingCount');
            if (waitingContainer && waitingCount) {
                waitingContainer.innerHTML = '';
                waitingCount.textContent = data.waitingSessions.length;
                
                data.waitingSessions.forEach(session => {
                    const item = document.createElement('div');
                    item.className = 'session-item';
                    item.setAttribute('data-session-id', session.session_id);
                    
                    item.innerHTML = `
                        <div class="session-info">
                            <strong>${escapeHtml(session.customer_name || 'Anonymous')}</strong>
                            <small>Topic: ${escapeHtml(session.chat_topic || 'No topic specified')}</small>
                            <small>${session.created_at}</small>
                        </div>
                        <button class="btn btn-accept" onclick="acceptChat('${session.session_id}')">Accept</button>
                    `;
                    
                    waitingContainer.appendChild(item);
                });
            }
            
            // Update active sessions
            const activeContainer = document.getElementById('activeSessions');
            const activeCount = document.getElementById('activeCount');
            if (activeContainer && activeCount) {
                activeContainer.innerHTML = '';
                activeCount.textContent = data.activeSessions.length;
                
                data.activeSessions.forEach(session => {
                    const item = document.createElement('div');
                    item.className = 'session-item active';
                    item.setAttribute('data-session-id', session.session_id);
                    item.onclick = () => openChat(session.session_id);
                    
                    item.innerHTML = `
                        <div class="session-info">
                            <strong>${escapeHtml(session.customer_name || 'Anonymous')}</strong>
                            <small>Topic: ${escapeHtml(session.chat_topic || 'No topic specified')}</small>
                            <small>Agent: ${escapeHtml(session.agent_name || 'Unassigned')}</small>
                        </div>
                        <span class="unread-badge" style="display: none;">0</span>
                    `;
                    
                    activeContainer.appendChild(item);
                });
            }
        })
        .catch(error => {
            // Error handling without console log
        });
}

// Auto-refresh admin sessions every 5 seconds
function startAdminAutoRefresh() {
    if (getUserType() === 'agent') {
        setInterval(() => {
            refreshAdminSessions();
        }, 5000); // Refresh every 5 seconds
    }
}

// Critical functions that need to be available early
async function checkSessionStatus() {
    const sessionToCheck = getSessionId();
    const userTypeToCheck = getUserType();
    
    if (sessionToCheck && userTypeToCheck === 'customer') {
        try {
            const response = await fetch(`/chat/check-session-status/${sessionToCheck}`);
            const result = await response.json();
            
            if (result.status === 'closed') {
                disableChatInput();
                showChatClosedMessage();
                displaySystemMessage('This chat session has been closed by the support team.');
                return false;
            }
            return true;
        } catch (error) {
            return true;
        }
    }
    return true;
}

async function loadChatHistory() {
    const currentSession = getSessionId();
    if (!currentSession) return;
    
    try {
        const response = await fetch(`/chat/messages/${currentSession}`);
        const messages = await response.json();
        
        const container = document.getElementById('messagesContainer');
        if (container) {
            container.innerHTML = '';
            displayedMessages.clear(); // Clear displayed messages for fresh load
            messages.forEach(message => {
                // Ensure each message has proper timestamp
                message = ensureMessageTimestamp(message);
                displayMessage(message);
            });
        }
    } catch (error) {
        // Error handling without console log
    }
}

async function acceptChat(sessionId) {
    try {
        // First, assign the agent via HTTP
        const response = await fetch('/chat/assign-agent', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `session_id=${sessionId}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Then notify WebSocket server
            if (ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({
                    type: 'assign_agent',
                    session_id: sessionId,
                    agent_id: userId
                }));
            }
            
            // Open the chat after successful assignment
            openChat(sessionId);
        } else {
            alert('Failed to accept chat. Please try again.');
        }
    } catch (error) {
        alert('Failed to accept chat. Please try again.');
    }
}

function openChat(sessionId) {
    currentSessionId = sessionId;
    const chatPanel = document.getElementById('chatPanel');
    if (chatPanel) {
        chatPanel.style.display = 'flex';
    }
    
    displayedMessages.clear();
    
    const sessionItem = document.querySelector(`[data-session-id="${sessionId}"]`);
    if (sessionItem) {
        const customerName = sessionItem.querySelector('strong').textContent;
        const customerNameElement = document.getElementById('chatCustomerName');
        if (customerNameElement) {
            customerNameElement.textContent = customerName;
        }
    }
    
    document.querySelectorAll('.session-item').forEach(item => {
        item.classList.remove('active');
    });
    sessionItem.classList.add('active');
    
    if (ws && ws.readyState === WebSocket.OPEN) {
        const registerData = {
            type: 'register',
            session_id: sessionId,
            user_type: 'agent',
            user_id: userId
        };
        ws.send(JSON.stringify(registerData));
    }
    
    loadChatHistoryForSession(sessionId);
    
    // Start periodic refresh for admin to catch system messages
    if (getUserType() === 'agent') {
        startMessageRefresh(sessionId);
    }
    
    // Re-initialize message form for admin after opening chat
    setTimeout(() => {
        initializeMessageForm();
        if (getUserType() === 'agent') {
            initQuickActions();
        }
    }, 500);
    
    // Ensure chat input is enabled for admin
    const input = document.getElementById('messageInput');
    const button = document.querySelector('.btn-send');
    if (input) {
        input.disabled = false;
        input.placeholder = 'Type your message...';
    }
    if (button) {
        button.disabled = false;
        button.textContent = 'Send';
    }
    
    // Remove any "chat ended" messages for admin
    const closedMessage = document.querySelector('.chat-closed-message');
    if (closedMessage) {
        closedMessage.remove();
    }
    
    // Clear any system messages about chat ending
    const systemMessages = document.querySelectorAll('.message.system');
    systemMessages.forEach(msg => {
        if (msg.textContent.includes('ended') || msg.textContent.includes('closed')) {
            msg.remove();
        }
    });
}

function closeCurrentChat() {
    if (currentSessionId && confirm('Are you sure you want to close this chat?')) {
        if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({
                type: 'close_session',
                session_id: currentSessionId
            }));
        }
        
        fetch('/chat/close-session', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `session_id=${currentSessionId}`
        });
        
        // Stop message refresh when closing chat
        stopMessageRefresh();
        
        const chatPanel = document.getElementById('chatPanel');
        if (chatPanel) {
            chatPanel.style.display = 'none';
        }
        currentSessionId = null;
    }
}

async function loadChatHistoryForSession(sessionId) {
    if (!sessionId) return;
    
    try {
        const response = await fetch(`/chat/messages/${sessionId}`);
        const messages = await response.json();
        
        const container = document.getElementById('messagesContainer');
        if (container) {
            container.innerHTML = '';
            displayedMessages.clear();
            messages.forEach(message => {
                // Ensure each message has proper timestamp
                message = ensureMessageTimestamp(message);
                displayMessage(message);
            });
        }
    } catch (error) {
        // Error handling without console log
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

function updateConnectingMessage(newMessage) {
    const container = document.getElementById('messagesContainer');
    if (!container) return;
    
    // Find the initial "Connecting to support..." message
    const systemMessages = container.querySelectorAll('.message.system p');
    systemMessages.forEach(p => {
        if (p.textContent.includes('Connecting to support')) {
            p.textContent = newMessage;
        }
    });
}

function disableChatInput() {
    const input = document.getElementById('messageInput');
    const button = document.querySelector('.btn-send');
    
    if (input) {
        input.disabled = true;
        input.placeholder = 'Chat session has ended';
    }
    if (button) {
        button.disabled = true;
        button.textContent = 'Chat Ended';
    }
}

function showChatClosedMessage() {
    const chatInterface = document.getElementById('chatInterface');
    if (chatInterface) {
        const closedMessage = document.createElement('div');
        closedMessage.className = 'chat-closed-message';
        closedMessage.innerHTML = `
            <div class="closed-overlay">
                <div class="closed-content">
                    <h3>Chat Session Ended</h3>
                    <p>This chat session has been closed by the support team.</p>
                    <p>Thank you for contacting us!</p>
                    <button class="btn btn-primary start-new-chat-btn" onclick="startNewChat()">
                        Start New Chat
                    </button>
                </div>
            </div>
        `;
        chatInterface.appendChild(closedMessage);
    }
}

function startNewChat() {
    sessionId = null;
    currentSessionId = null;
    
    const closedMessage = document.querySelector('.chat-closed-message');
    if (closedMessage) {
        closedMessage.remove();
    }
    
    const chatInterface = document.getElementById('chatInterface');
    if (chatInterface) {
        // Check if role information is available (from customer view)
        const currentUserRole = typeof userRole !== 'undefined' ? userRole : 'anonymous';
        const currentExternalUsername = typeof externalUsername !== 'undefined' ? externalUsername : '';
        const currentExternalFullname = typeof externalFullname !== 'undefined' ? externalFullname : '';
        const currentExternalSystemId = typeof externalSystemId !== 'undefined' ? externalSystemId : '';
        
        // Generate form HTML based on user role
        let nameFieldHtml = '';
        let roleFieldsHtml = '';
        let statusMessageHtml = '';
        
        if (currentUserRole === 'loggedUser' && (currentExternalFullname || currentExternalUsername)) {
            // For logged users, show read-only name field
            const displayName = currentExternalFullname || currentExternalUsername;
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
            <input type="hidden" name="user_role" value="${currentUserRole}">
            <input type="hidden" name="external_username" value="${currentExternalUsername}">
            <input type="hidden" name="external_fullname" value="${currentExternalFullname}">
            <input type="hidden" name="external_system_id" value="${currentExternalSystemId}">
        `;
        
        chatInterface.innerHTML = `
            <div class="chat-start-form">
                <h4>Start a New Chat Session</h4>
                <form id="startChatForm">
                    ${roleFieldsHtml}
                    ${nameFieldHtml}
                    <div class="form-group">
                        <label for="chatTopic">What do you need help with?</label>
                        <input type="text" id="chatTopic" name="chat_topic" required placeholder="Describe your issue or question...">
                    </div>
                    <div class="form-group">
                        <label for="email">Email (Optional)</label>
                        <input type="email" id="email" name="email">
                    </div>
                    ${statusMessageHtml}
                    <button type="submit" class="btn btn-primary">Start Chat</button>
                </form>
            </div>
        `;
        
        // Form submission will be handled by the global document event listener
    }
}

// Initialize WebSocket connection
let wsUrls = [
    'wss://ws.kopisugar.cc:39147'
];
let currentUrlIndex = 0;

function initWebSocket() {
    if (ws && ws.readyState !== WebSocket.CLOSED) {
        ws.close();
    }
    
    const wsUrl = wsUrls[currentUrlIndex];
    
    ws = new WebSocket(wsUrl);
    
    ws.onopen = function() {
        const connectionStatus = safeGetElement('connectionStatus');
        if (connectionStatus) {
            connectionStatus.textContent = 'Online';
            connectionStatus.classList.add('online');
        }
        
        setTimeout(() => {
            const currentUserType = getUserType();
            if (currentUserType) {
                const currentSession = getSessionId();
                const currentUserId = getUserId();
                const registerData = {
                    type: 'register',
                    session_id: currentSession || null,
                    user_type: currentUserType,
                    user_id: currentUserId
                };
                ws.send(JSON.stringify(registerData));
            }
        }, 100);
        
        if (reconnectInterval) {
            clearInterval(reconnectInterval);
            reconnectInterval = null;
        }
        
        if (messageQueue.length > 0) {
            messageQueue.forEach(msg => {
                ws.send(JSON.stringify(msg));
            });
            messageQueue = [];
        }
        
        if (userType === 'agent') {
            setInterval(refreshAdminSessions, 10000);
        } else if (userType === 'customer') {
            // Only show the close button if there's an active session
            const currentSession = getSessionId();
            if (currentSession) {
                const closeBtn = document.getElementById('customerCloseBtn');
                if (closeBtn) {
                    closeBtn.style.display = 'inline-block';
                }
            }
        }
    };
    
    ws.onmessage = function(event) {
        const data = JSON.parse(event.data);
        handleWebSocketMessage(data);
    };
    
    ws.onclose = function() {
        const connectionStatus = safeGetElement('connectionStatus');
        if (connectionStatus) {
            connectionStatus.textContent = 'Offline';
            connectionStatus.classList.remove('online');
        }
        
        if (!reconnectInterval) {
            reconnectInterval = setInterval(function() {
                initWebSocket();
            }, 5000);
        }
    };
    
    ws.onerror = function(error) {
        const connectionStatus = safeGetElement('connectionStatus');
        if (connectionStatus) {
            connectionStatus.textContent = 'Connection Error';
            connectionStatus.classList.remove('online');
        }
        
        // Try next URL if available
        if (currentUrlIndex < wsUrls.length - 1) {
            currentUrlIndex++;
            setTimeout(() => {
                initWebSocket();
            }, 2000); // Wait 2 seconds before trying next URL
        } else {
            // Reset to first URL for reconnect attempts
            currentUrlIndex = 0;
            displaySystemMessage('Connection error. All connection methods failed. Retrying...');
        }
    };
}

// Handle incoming WebSocket messages
function handleWebSocketMessage(data) {
    switch (data.type) {
        case 'connected':
            const connectedSession = getSessionId();
            const connectedUserType = getUserType();
            if (connectedSession) {
                if (connectedUserType === 'customer') {
                    // Update the initial "Connecting to support..." message
updateConnectingMessage('Connected to Chat');
                    loadChatHistory();
                } else if (connectedUserType === 'agent' && currentSessionId) {
                    loadChatHistoryForSession(currentSessionId);
                }
            }
            break;
            
        case 'message':
            // Check for duplicate of our own message BEFORE clearing tracking variables
            if (window.lastSentMessageContent && data.message && 
                data.message.toLowerCase().trim() === window.lastSentMessageContent && 
                data.sender_type === getUserType()) {
                // Clear tracking variables for our own message
                window.lastSentMessageContent = null;
                window.lastMessageTime = null;
                window.justSentMessage = false;
                return; // Exit early, don't display the message
            }
            
            // Clear the last sent message ID since we received it from server
            if (window.lastSentMessageId) {
                window.lastSentMessageId = null;
            }
            
            // Clear the last sent message content as well
            if (window.lastSentMessageContent) {
                window.lastSentMessageContent = null;
            }
            
            // Clear the last message time as well
            if (window.lastMessageTime) {
                window.lastMessageTime = null;
            }
            
            // Clear tracking variables when we receive our own message
            if (data.sender_type === getUserType()) {
                window.lastSentMessageContent = null;
                window.lastMessageTime = null;
                window.justSentMessage = false;
            }
            
            const messageUserType = getUserType();
            const messageSession = getSessionId();
            
            // Always display system messages that match the current session
            if (data.message_type === 'system') {
                if ((messageUserType === 'agent' && currentSessionId && data.session_id === currentSessionId) ||
                    (messageUserType === 'customer' && messageSession && data.session_id === messageSession)) {
                    displayMessage(data);
                    playNotificationSound();
                }
            }
            // Handle regular messages
            else if (messageUserType === 'agent' && currentSessionId && data.session_id === currentSessionId) {
                displayMessage(data);
                playNotificationSound();
            } else if (messageUserType === 'customer' && messageSession && data.session_id === messageSession) {
                displayMessage(data);
                playNotificationSound();
            } else {
                // Try to display message anyway if session IDs match
                if (data.session_id === (messageSession || currentSessionId)) {
                    displayMessage(data);
                }
            }
            break;
            
        case 'typing':
            handleTypingIndicator(data);
            break;
            
        case 'agent_assigned':
            displaySystemMessage(data.message);
            break;
            
        case 'session_closed':
            displaySystemMessage(data.message);
            disableChatInput();
            showChatClosedMessage();
            break;
            
        case 'waiting_sessions':
            updateWaitingSessions(data.sessions);
            break;
            
        case 'update_sessions':
            refreshAdminSessions();
            break;
            
            
        case 'system_message':
            displaySystemMessage(data.message);
            break;
    }
}

// Customer chat functions - Use event delegation to handle dynamically created forms
document.addEventListener('submit', async function(e) {
    if (e.target && e.target.id === 'startChatForm') {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const submitBtn = e.target.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.textContent : 'Start Chat';
        
        // Disable submit button to prevent double submission
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Starting Chat...';
        }
        
        try {
            const response = await fetch('/chat/start-session', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                sessionId = result.session_id;
                currentSessionId = result.session_id;
                
                const chatInterface = document.getElementById('chatInterface');
                if (chatInterface) {
                    chatInterface.innerHTML = `
                        <div class="chat-window" data-session-id="${result.session_id}">
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
                    
                    initWebSocket();
                    initializeMessageForm();
                    
                    // Show the Leave Chat button now that we have an active session
                    const closeBtn = document.getElementById('customerCloseBtn');
                    if (closeBtn) {
                        closeBtn.style.display = 'inline-block';
                    }
                    
                    // Load quick actions for the customer
                    setTimeout(() => {
                        if (typeof fetchQuickActions === 'function') {
                            fetchQuickActions();
                        }
                    }, 1000);
                }
            } else {
                alert(result.error || 'Failed to start chat');
                // Re-enable submit button on error
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            }
        } catch (error) {
            alert('Failed to connect. Please try again.');
            // Re-enable submit button on error
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    }
});

// Initialize message form handler
function initializeMessageForm() {
    const messageForm = document.getElementById('messageForm');
    
    if (messageForm) {
        const newForm = messageForm.cloneNode(true);
        messageForm.parentNode.replaceChild(newForm, messageForm);
        
        const freshMessageForm = document.getElementById('messageForm');
        
        freshMessageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            
            if (messageInput.disabled) {
                return;
            }
            
            const now = Date.now();
            if (now - lastMessageTime < MESSAGE_RATE_LIMIT) {
                return;
            }
            
            // For admin, use currentSessionId if getSessionId() is null
            const sessionToUse = getSessionId() || currentSessionId;
            
            if (message && ws && ws.readyState === WebSocket.OPEN && sessionToUse) {
                const messageData = {
                    type: 'message',
                    session_id: sessionToUse,
                    message: message,
                    sender_type: getUserType(),
                    sender_id: getUserId()
                };
                
                ws.send(JSON.stringify(messageData));
                messageInput.value = '';
                lastMessageTime = now;
                
                // Display message immediately for sender
                const immediateMessage = {
                    type: 'message',
                    session_id: sessionToUse,
                    sender_type: getUserType(),
                    message: message,
                    timestamp: new Date().toISOString(),
                    id: 'temp_' + Date.now() // Temporary ID for immediate display
                };
                displayMessage(immediateMessage);
                
                // Store this message ID and content to prevent duplicate display when received from WebSocket
                window.lastSentMessageId = immediateMessage.id;
                window.lastSentMessageContent = message.toLowerCase().trim();
                window.lastMessageTime = Date.now();
                window.justSentMessage = true; // Flag to indicate we just sent a message
                
                // Clear tracking variables after 3 seconds to prevent permanent blocking
                setTimeout(() => {
                    if (window.lastSentMessageContent === message.toLowerCase().trim()) {
                        window.lastSentMessageContent = null;
                        window.lastMessageTime = null;
                        window.justSentMessage = false;
                    }
                }, 3000);
                
                if (isTyping) {
                    sendTypingIndicator(false);
                }
            } else if (message) {
                messageQueue.push({
                    type: 'message',
                    session_id: sessionToUse,
                    message: message,
                    sender_type: getUserType(),
                    sender_id: getUserId()
                });
                messageInput.value = '';
            }
        });
        
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('input', function() {
                if (this.disabled) {
                    return;
                }
                
                if (!isTyping) {
                    sendTypingIndicator(true);
                }
                
                clearTimeout(typingTimer);
                typingTimer = setTimeout(function() {
                    sendTypingIndicator(false);
                }, 1000);
            });
        }
    }
}

// Send typing indicator
function sendTypingIndicator(typing) {
    if (ws && ws.readyState === WebSocket.OPEN) {
        isTyping = typing;
        const currentSession = getSessionId();
        ws.send(JSON.stringify({
            type: 'typing',
            session_id: currentSession,
            user_type: getUserType(),
            is_typing: typing
        }));
    }
}

// Handle typing indicator display
function handleTypingIndicator(data) {
    const indicator = document.getElementById('typingIndicator');
    if (indicator) {
        if (data.is_typing && data.user_type !== getUserType()) {
            indicator.style.display = 'flex';
        } else {
            indicator.style.display = 'none';
        }
    }
}

// Display message
function displayMessage(data) {
    const container = document.getElementById('messagesContainer');
    if (!container) {
        return;
    }
    
    // Ensure message has proper timestamp
    data = ensureMessageTimestamp(data);
    
    // Use the same ID generation logic as refreshMessagesForSession
    const messageContent = data.message ? data.message.toLowerCase().trim() : '';
    const messageId = data.id ? `db_${data.id}` : `${data.sender_type}_${messageContent}_${data.timestamp}`;
    
    if (displayedMessages.has(messageId)) {
        return;
    }
    
    displayedMessages.add(messageId);
    
    const messageDiv = document.createElement('div');
    
    // Handle system messages specially - check for message_type = 'system'
    if (data.message_type === 'system') {
        messageDiv.className = 'message system';
        const p = document.createElement('p');
        p.textContent = data.message;
        messageDiv.appendChild(p);
    } else {
        // Regular message handling
        if (userType === 'agent') {
            if (data.sender_type === 'customer') {
                messageDiv.className = 'message customer';
            } else {
                messageDiv.className = 'message agent';
            }
        } else {
            messageDiv.className = `message ${data.sender_type}`;
        }
        
        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        bubble.textContent = data.message;
        
        const time = document.createElement('div');
        time.className = 'message-time';
        time.textContent = formatTime(data.timestamp);
        
        bubble.appendChild(time);
        messageDiv.appendChild(bubble);
    }
    
    container.appendChild(messageDiv);
    container.scrollTop = container.scrollHeight;
}

function updateWaitingSessions(sessions) {
    const container = document.getElementById('waitingSessions');
    const count = document.getElementById('waitingCount');
    
    if (container && count) {
        container.innerHTML = '';
        count.textContent = sessions.length;
        
        sessions.forEach(session => {
            const item = document.createElement('div');
            item.className = 'session-item';
            item.setAttribute('data-session-id', session.session_id);
            
            item.innerHTML = `
                <div class="session-info">
                    <strong>${escapeHtml(session.customer_name)}</strong>
                    <small>${formatTime(session.created_at)}</small>
                </div>
                <button class="btn btn-accept" onclick="acceptChat('${session.session_id}')">Accept</button>
            `;
            
            container.appendChild(item);
        });
    }
}

// Utility functions
function formatTime(timestamp) {
    if (!timestamp) {
        return 'Invalid Date';
    }
    
    try {
        const date = new Date(timestamp);
        
        // Check if date is valid
        if (isNaN(date.getTime())) {
            return 'Invalid Date';
        }
        
        return date.toLocaleTimeString('en-US', { 
            hour: 'numeric', 
            minute: '2-digit',
            hour12: true,
            timeZone: 'Asia/Kuala_Lumpur'
        });
    } catch (error) {
        return 'Invalid Date';
    }
}

// Helper function to ensure message has proper timestamp
function ensureMessageTimestamp(message) {
    if (!message.timestamp || message.timestamp === 'Invalid Date') {
        // If no timestamp, use current time
        message.timestamp = new Date().toISOString();
    }
    return message;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function playNotificationSound() {
    // Optional: Add notification sound
    // const audio = new Audio('/assets/sounds/notification.mp3');
    // audio.play();
}

// Message refresh for admin to catch system messages in real-time
let messageRefreshInterval = null;

function startMessageRefresh(sessionId) {
    // Clear any existing refresh interval
    if (messageRefreshInterval) {
        clearInterval(messageRefreshInterval);
    }
    
    // Refresh messages every 1 second for admin to catch system messages
    if (getUserType() === 'agent') {
        messageRefreshInterval = setInterval(() => {
            refreshMessagesForSession(sessionId);
        }, 1000);
    }
}

function stopMessageRefresh() {
    if (messageRefreshInterval) {
        clearInterval(messageRefreshInterval);
        messageRefreshInterval = null;
    }
}

async function refreshMessagesForSession(sessionId) {
    if (!sessionId || sessionId !== currentSessionId) {
        return;
    }
    
    try {
        const response = await fetch(`/chat/messages/${sessionId}`);
        const messages = await response.json();
        
        const container = document.getElementById('messagesContainer');
        if (container) {
            // Store current scroll position
            const isScrolledToBottom = container.scrollTop === container.scrollHeight - container.clientHeight;
            
            // Create a more robust tracking system using actual message IDs from database
            let newMessagesAdded = false;
            
            messages.forEach(message => {
                message = ensureMessageTimestamp(message);
                
                // Use the actual database message ID if available, otherwise fall back to content-based ID
                const messageId = message.id ? `db_${message.id}` : `${message.sender_type}_${message.message ? message.message.toLowerCase().trim() : ''}_${message.timestamp}`;
                
                if (!displayedMessages.has(messageId)) {
                    displayMessage(message);
                    newMessagesAdded = true;
                }
            });
            
            // Only adjust scroll if new messages were added and user was at bottom
            if (newMessagesAdded && isScrolledToBottom) {
                container.scrollTop = container.scrollHeight;
            }
        }
    } catch (error) {
        // Error handling without console log
    }
}

// Canned responses
function showCannedResponses() {
    if (!currentSessionId) return;
    
    fetch('/api/canned-responses')
        .then(response => response.json())
        .then(responses => {
            displayCannedResponsesModal(responses);
        });
}

function displayCannedResponsesModal(responses) {
    const modal = document.createElement('div');
    modal.className = 'canned-responses-modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>Quick Responses</h3>
                <button class="close-modal" onclick="this.parentElement.parentElement.parentElement.remove()">×</button>
            </div>
            <div class="modal-body">
                ${responses.map(response => `
                    <div class="canned-response-item" onclick="sendCannedResponse('${response.id}')">
                        <strong>${response.title}</strong>
                        <p>${response.content}</p>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

async function sendCannedResponse(responseId) {
    try {
        const response = await fetch(`/admin/canned-responses/get/${responseId}`);
        const responseData = await response.json();
        
        if (responseData.content) {
            if (ws && ws.readyState === WebSocket.OPEN) {
                const currentSession = getSessionId();
                
                ws.send(JSON.stringify({
                    type: 'message',
                    session_id: currentSession,
                    message: responseData.content,
                    sender_type: getUserType(),
                    sender_id: getUserId()
                }));
            }
        }
    } catch (error) {
        // Error handling without console log
    }
}

// Quick actions for common responses
function initQuickActions() {
    const chatInputArea = document.querySelector('.chat-input-area');
    if (!chatInputArea || getUserType() !== 'agent') return;
    
    // Remove existing quick actions to prevent duplicates
    const existingQuickActions = chatInputArea.querySelector('.quick-actions');
    if (existingQuickActions) {
        existingQuickActions.remove();
    }
    
    const quickActions = document.createElement('div');
    quickActions.className = 'quick-actions';
    
    // Load canned responses from database
    fetch('/admin/canned-responses/get-all')
        .then(response => response.json())
        .then(responses => {
            quickActions.innerHTML = responses.map(response => 
                `<button class="quick-action-btn" onclick="sendCannedResponse(${response.id})">${response.title}</button>`
            ).join('');
        })
        .catch(error => {
            // Fallback to default responses
            quickActions.innerHTML = `
                <button class="quick-action-btn" onclick="sendQuickResponse('greeting')">👋 Greeting</button>
                <button class="quick-action-btn" onclick="sendQuickResponse('please_wait')">⏳ Please Wait</button>
                <button class="quick-action-btn" onclick="sendQuickResponse('thank_you')">🙏 Thank You</button>
            `;
        });
    
    chatInputArea.insertBefore(quickActions, chatInputArea.firstChild);
}

async function sendQuickResponse(type) {
    const responses = {
        greeting: "Hello! How can I help you today?",
        please_wait: "Thank you for your patience. Let me look into this for you.",
        thank_you: "Thank you for contacting us. Have a great day!"
    };
    
    const message = responses[type];
    if (message && getSessionId()) {
        if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({
                type: 'message',
                session_id: getSessionId(),
                message: message,
                sender_type: getUserType(),
                sender_id: getUserId()
            }));
        }
    }
}

// Initialize WebSocket on page load
document.addEventListener('DOMContentLoaded', async function() {
    displayedMessages.clear();
    
    const chatInterface = safeGetElement('chatInterface');
    const messagesContainer = safeGetElement('messagesContainer');
    const adminDashboard = safeGetElement('admin-dashboard');
    
    // Check if we're on a chat page (customer or admin)
    if (chatInterface || messagesContainer || adminDashboard) {
        // For admin interface, always initialize WebSocket
        if (getUserType() === 'agent') {
            initWebSocket();
            
            // Start auto-refresh for admin sessions
            startAdminAutoRefresh();
            
            // Ensure admin interface doesn't show "chat ended" messages
            const closedMessage = document.querySelector('.chat-closed-message');
            if (closedMessage) {
                closedMessage.remove();
            }
            
            setTimeout(() => {
                // Initialize quick actions for agents only
                initQuickActions();
            }, 500);
        } else {
            // For customer interface, check session status
            const sessionActive = await checkSessionStatus();
            if (sessionActive) {
                initWebSocket();
                
                // Wait a moment for WebSocket to connect before initializing form
                setTimeout(() => {
                    initializeMessageForm();
                }, 500);
            }
        }
    }
});