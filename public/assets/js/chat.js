let ws = null;
let reconnectInterval = null;
let typingTimer = null;
let isTyping = false;
let displayedMessages = new Set(); // Track displayed messages to prevent duplicates

// Initialize WebSocket connection
function initWebSocket() {
    ws = new WebSocket('ws://localhost:8081');
    
    ws.onopen = function() {
        console.log('Connected to chat server');
        document.getElementById('connectionStatus').textContent = 'Online';
        document.getElementById('connectionStatus').classList.add('online');
        
        // Register connection
        if (typeof userType !== 'undefined') {
            const registerData = {
                type: 'register',
                session_id: sessionId || null,
                user_type: userType,
                user_id: typeof userId !== 'undefined' ? userId : null
            };
            ws.send(JSON.stringify(registerData));
        }
        
        // Clear reconnect interval
        if (reconnectInterval) {
            clearInterval(reconnectInterval);
            reconnectInterval = null;
        }
        
        // For admin, set up periodic refresh of sessions
        if (userType === 'agent') {
            setInterval(refreshAdminSessions, 10000); // Refresh every 10 seconds
        }
    };
    
    ws.onmessage = function(event) {
        const data = JSON.parse(event.data);
        handleWebSocketMessage(data);
    };
    
    ws.onclose = function() {
        console.log('Disconnected from chat server');
        document.getElementById('connectionStatus').textContent = 'Offline';
        document.getElementById('connectionStatus').classList.remove('online');
        
        // Attempt to reconnect
        if (!reconnectInterval) {
            reconnectInterval = setInterval(function() {
                console.log('Attempting to reconnect...');
                initWebSocket();
            }, 5000);
        }
    };
    
    ws.onerror = function(error) {
        console.error('WebSocket error:', error);
    };
}

// Handle incoming WebSocket messages
function handleWebSocketMessage(data) {
    switch (data.type) {
        case 'connected':
            console.log(data.message);
            if (sessionId && userType === 'customer') {
                loadChatHistory();
            }
            break;
            
        case 'message':
            console.log('Received message:', data);
            console.log('Current session ID:', currentSessionId);
            console.log('Message session ID:', data.session_id);
            console.log('User type:', userType);
            console.log('Sender type:', data.sender_type);
            console.log('Message content:', data.message);
            
            // Check if this message belongs to the currently open chat (for admin)
            if (userType === 'agent' && currentSessionId && data.session_id === currentSessionId) {
                console.log('Displaying message for admin');
                displayMessage(data);
                playNotificationSound();
            } else if (userType === 'customer' && sessionId && data.session_id === sessionId) {
                console.log('Displaying message for customer');
                displayMessage(data);
                playNotificationSound();
            } else {
                console.log('Message not for current session');
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
    }
}

// Customer chat functions
if (document.getElementById('startChatForm')) {
    document.getElementById('startChatForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch('/chat/start-session', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                sessionId = result.session_id;
                location.reload(); // Reload to show chat interface
            } else {
                alert(result.error || 'Failed to start chat');
            }
        } catch (error) {
            console.error('Error starting chat:', error);
            alert('Failed to connect. Please try again.');
        }
    });
}

// Message form handler
if (document.getElementById('messageForm')) {
    document.getElementById('messageForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const messageInput = document.getElementById('messageInput');
        const message = messageInput.value.trim();
        
        // Check if chat is closed
        if (messageInput.disabled) {
            return; // Don't send if chat is closed
        }
        
        if (message && ws && ws.readyState === WebSocket.OPEN) {
            const messageData = {
                type: 'message',
                session_id: currentSessionId || sessionId,
                message: message,
                sender_type: userType,
                sender_id: typeof userId !== 'undefined' ? userId : null
            };
            
            ws.send(JSON.stringify(messageData));
            messageInput.value = '';
            
            // Clear typing indicator
            if (isTyping) {
                sendTypingIndicator(false);
            }
        }
    });
    
    // Typing indicator
    document.getElementById('messageInput').addEventListener('input', function() {
        // Don't send typing indicator if chat is closed
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

// Send typing indicator
function sendTypingIndicator(typing) {
    if (ws && ws.readyState === WebSocket.OPEN) {
        isTyping = typing;
        ws.send(JSON.stringify({
            type: 'typing',
            session_id: currentSessionId || sessionId,
            user_type: userType,
            is_typing: typing
        }));
    }
}

// Handle typing indicator display
function handleTypingIndicator(data) {
    const indicator = document.getElementById('typingIndicator');
    if (indicator) {
        if (data.is_typing && data.user_type !== userType) {
            indicator.style.display = 'flex';
        } else {
            indicator.style.display = 'none';
        }
    }
}

// Display message
function displayMessage(data) {
    const container = document.getElementById('messagesContainer');
    if (!container) return;
    
    // Create a unique identifier for this message to prevent duplicates
    const messageId = `${data.sender_type}_${data.message}_${data.timestamp}`;
    
    // Check if this message has already been displayed
    if (displayedMessages.has(messageId)) {
        console.log('Duplicate message detected, skipping:', messageId);
        return;
    }
    
    // Add to displayed messages set
    displayedMessages.add(messageId);
    
    const messageDiv = document.createElement('div');
    
    // For admin interface, position messages differently
    if (userType === 'agent') {
        if (data.sender_type === 'customer') {
            messageDiv.className = 'message customer'; // Customer messages on left
        } else {
            messageDiv.className = 'message agent'; // Agent messages on right
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
    container.appendChild(messageDiv);
    
    // Scroll to bottom
    container.scrollTop = container.scrollHeight;
}

// Display system message
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

// Load chat history
async function loadChatHistory() {
    if (!sessionId) return;
    
    try {
        const response = await fetch(`/chat/messages/${sessionId}`);
        const messages = await response.json();
        
        const container = document.getElementById('messagesContainer');
        if (!container) return;
        
        container.innerHTML = '';
        
        messages.forEach(msg => {
            displayMessage({
                sender_type: msg.sender_type,
                message: msg.message,
                timestamp: msg.created_at
            });
        });
    } catch (error) {
        console.error('Error loading chat history:', error);
    }
}

// Admin functions
function acceptChat(sessionId) {
    if (ws && ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify({
            type: 'assign_agent',
            session_id: sessionId,
            agent_id: userId
        }));
        
        // Also update via HTTP for database
        fetch('/chat/assign-agent', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `session_id=${sessionId}`
        });
        
        openChat(sessionId);
    }
}

function openChat(sessionId) {
    currentSessionId = sessionId;
    document.getElementById('chatPanel').style.display = 'flex';
    
    // Clear displayed messages when switching sessions
    displayedMessages.clear();
    
    // Update header
    const sessionItem = document.querySelector(`[data-session-id="${sessionId}"]`);
    if (sessionItem) {
        const customerName = sessionItem.querySelector('strong').textContent;
        document.getElementById('chatCustomerName').textContent = customerName;
    }
    
    // Mark as active
    document.querySelectorAll('.session-item').forEach(item => {
        item.classList.remove('active');
    });
    sessionItem.classList.add('active');
    
    // Register for this session with WebSocket
    if (ws && ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify({
            type: 'register',
            session_id: sessionId,
            user_type: 'agent',
            user_id: userId
        }));
    }
    
    // Load messages for this session
    loadChatHistoryForSession(sessionId);
}

function closeCurrentChat() {
    if (currentSessionId && confirm('Are you sure you want to close this chat?')) {
        if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({
                type: 'close_session',
                session_id: currentSessionId
            }));
        }
        
        // Also update via HTTP
        fetch('/chat/close-session', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `session_id=${currentSessionId}`
        });
        
        document.getElementById('chatPanel').style.display = 'none';
        currentSessionId = null;
    }
}

async function loadChatHistoryForSession(sessionId) {
    if (!sessionId) return;
    
    try {
        const response = await fetch(`/chat/messages/${sessionId}`);
        const messages = await response.json();
        
        const container = document.getElementById('messagesContainer');
        if (!container) return;
        
        // Clear container and reset displayed messages
        container.innerHTML = '';
        displayedMessages.clear();
        
        messages.forEach(msg => {
            displayMessage({
                sender_type: msg.sender_type,
                message: msg.message,
                timestamp: msg.created_at
            });
        });
        
        // Scroll to bottom after loading messages
        container.scrollTop = container.scrollHeight;
    } catch (error) {
        console.error('Error loading chat history:', error);
    }
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

function refreshAdminSessions() {
    // Refresh the sessions list without reloading the page
    fetch('/admin/chat')
        .then(response => response.text())
        .then(html => {
            // Update the sessions panel with new data
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            // Update waiting sessions
            const newWaitingSessions = doc.getElementById('waitingSessions');
            const currentWaitingSessions = document.getElementById('waitingSessions');
            if (newWaitingSessions && currentWaitingSessions) {
                currentWaitingSessions.innerHTML = newWaitingSessions.innerHTML;
            }
            
            // Update active sessions
            const newActiveSessions = doc.getElementById('activeSessions');
            const currentActiveSessions = document.getElementById('activeSessions');
            if (newActiveSessions && currentActiveSessions) {
                currentActiveSessions.innerHTML = newActiveSessions.innerHTML;
            }
            
            // Update counts
            const newWaitingCount = doc.getElementById('waitingCount');
            const currentWaitingCount = document.getElementById('waitingCount');
            if (newWaitingCount && currentWaitingCount) {
                currentWaitingCount.textContent = newWaitingCount.textContent;
            }
            
            const newActiveCount = doc.getElementById('activeCount');
            const currentActiveCount = document.getElementById('activeCount');
            if (newActiveCount && currentActiveCount) {
                currentActiveCount.textContent = newActiveCount.textContent;
            }
        })
        .catch(error => {
            console.error('Error refreshing sessions:', error);
        });
}

// Utility functions
function formatTime(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true,
        timeZone: 'Asia/Kuala_Lumpur'
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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
    // Add a visual overlay or message to indicate chat is closed
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
    // Clear the current session
    sessionId = null;
    currentSessionId = null;
    
    // Remove the closed message overlay
    const closedMessage = document.querySelector('.chat-closed-message');
    if (closedMessage) {
        closedMessage.remove();
    }
    
    // Show the start chat form
    const chatInterface = document.getElementById('chatInterface');
    if (chatInterface) {
        chatInterface.innerHTML = `
            <div class="chat-start-form">
                <h4>Start a New Chat Session</h4>
                <form id="startChatForm">
                    <div class="form-group">
                        <label for="name">Your Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email (Optional)</label>
                        <input type="email" id="email" name="email">
                    </div>
                    <button type="submit" class="btn btn-primary">Start Chat</button>
                </form>
            </div>
        `;
        
        // Re-attach the form event listener
        document.getElementById('startChatForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('/chat/start-session', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    sessionId = result.session_id;
                    location.reload(); // Reload to show chat interface
                } else {
                    alert(result.error || 'Failed to start chat');
                }
            } catch (error) {
                console.error('Error starting chat:', error);
                alert('Failed to connect. Please try again.');
            }
        });
    }
}

function playNotificationSound() {
    // Optional: Add notification sound
    // const audio = new Audio('/assets/sounds/notification.mp3');
    // audio.play();
}

// Check if session is closed on page load
async function checkSessionStatus() {
    if (sessionId && userType === 'customer') {
        try {
            const response = await fetch(`/chat/check-session-status/${sessionId}`);
            const result = await response.json();
            
            if (result.status === 'closed') {
                console.log('Session is closed, disabling chat');
                disableChatInput();
                showChatClosedMessage();
                displaySystemMessage('This chat session has been closed by the support team.');
                return false; // Session is closed
            }
            return true; // Session is active
        } catch (error) {
            console.error('Error checking session status:', error);
            return true; // Assume active on error
        }
    }
    return true; // No session ID or not customer
}

// Initialize WebSocket on page load
document.addEventListener('DOMContentLoaded', async function() {
    // Clear displayed messages on page load
    displayedMessages.clear();
    
    const sessionActive = await checkSessionStatus();
    if (sessionActive) {
        initWebSocket();
    }
});