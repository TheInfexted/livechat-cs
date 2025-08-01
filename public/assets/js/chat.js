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


            

            
        case 'system_message':
            displaySystemMessage(data.message);
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
        // First get the response content
        const response = await fetch(`/admin/canned-responses/get/${responseId}`);
        const responseData = await response.json();
        
        if (responseData.content) {
            // Send the message through WebSocket
            if (ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({
                    type: 'message',
                    session_id: currentSessionId || sessionId,
                    message: responseData.content,
                    sender_type: userType,
                    sender_id: typeof userId !== 'undefined' ? userId : null
                }));
            }
        }
    } catch (error) {
        console.error('Error sending canned response:', error);
    }
}

// Customer satisfaction rating
function showRatingModal(sessionId) {
    const modal = document.createElement('div');
    modal.className = 'rating-modal';
    modal.innerHTML = `
        <div class="modal-content">
            <h3>Rate Your Experience</h3>
            <div class="rating-stars">
                ${[1,2,3,4,5].map(i => `
                    <span class="star" data-rating="${i}" onclick="selectRating(${i})">★</span>
                `).join('')}
            </div>
            <textarea placeholder="Additional feedback (optional)" id="ratingFeedback"></textarea>
            <div class="modal-actions">
                <button onclick="submitRating('${sessionId}')">Submit</button>
                <button onclick="this.parentElement.parentElement.parentElement.remove()">Skip</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

function selectRating(rating) {
    document.querySelectorAll('.star').forEach((star, index) => {
        star.classList.toggle('selected', index < rating);
    });
    window.selectedRating = rating;
}

async function submitRating(sessionId) {
    const rating = window.selectedRating;
    const feedback = document.getElementById('ratingFeedback').value;
    
    if (!rating) {
        alert('Please select a rating');
        return;
    }
    
    try {
        const response = await fetch('/chat/rate-session', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `session_id=${sessionId}&rating=${rating}&feedback=${encodeURIComponent(feedback)}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.querySelector('.rating-modal').remove();
            displaySystemMessage('Thank you for your feedback!');
        }
    } catch (error) {
        console.error('Error submitting rating:', error);
    }
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

// Enhanced WebSocket with reconnection and heartbeat
class EnhancedWebSocket {
    constructor(url) {
        this.url = url;
        this.ws = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectInterval = 1000;
        this.heartbeatInterval = null;
        this.messageQueue = [];
        this.connect();
    }
    
    connect() {
        try {
            this.ws = new WebSocket(this.url);
            this.setupEventHandlers();
            this.updateConnectionStatus('connecting');
        } catch (error) {
            console.error('WebSocket connection failed:', error);
            this.scheduleReconnect();
        }
    }
    
    setupEventHandlers() {
        this.ws.onopen = () => {
            console.log('WebSocket connected');
            this.updateConnectionStatus('connected');
            this.reconnectAttempts = 0;
            this.startHeartbeat();
            this.flushMessageQueue();
            
            if (this.onopen) this.onopen();
        };
        
        this.ws.onmessage = (event) => {
            const data = JSON.parse(event.data);
            if (data.type === 'pong') {
                return; // Heartbeat response
            }
            if (this.onmessage) this.onmessage(event);
        };
        
        this.ws.onclose = () => {
            console.log('WebSocket disconnected');
            this.updateConnectionStatus('disconnected');
            this.stopHeartbeat();
            this.scheduleReconnect();
            
            if (this.onclose) this.onclose();
        };
        
        this.ws.onerror = (error) => {
            console.error('WebSocket error:', error);
            if (this.onerror) this.onerror(error);
        };
    }
    
    send(data) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(data);
        } else {
            // Queue message for when connection is restored
            this.messageQueue.push(data);
        }
    }
    
    scheduleReconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.updateConnectionStatus('reconnecting');
            setTimeout(() => {
                this.reconnectAttempts++;
                this.connect();
            }, this.reconnectInterval * Math.pow(2, this.reconnectAttempts));
        }
    }
    
    startHeartbeat() {
        this.heartbeatInterval = setInterval(() => {
            if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                this.ws.send(JSON.stringify({ type: 'ping' }));
            }
        }, 30000); // 30 seconds
    }
    
    stopHeartbeat() {
        if (this.heartbeatInterval) {
            clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = null;
        }
    }
    
    flushMessageQueue() {
        while (this.messageQueue.length > 0) {
            this.send(this.messageQueue.shift());
        }
    }
    
    updateConnectionStatus(status) {
        const statusElement = document.getElementById('connectionStatus');
        if (statusElement) {
            statusElement.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            statusElement.className = `status-indicator ${status}`;
        }
        
        // Show connection notification
        showConnectionNotification(status);
    }
    
    close() {
        this.stopHeartbeat();
        if (this.ws) {
            this.ws.close();
        }
    }
}



// Enhanced message display with read receipts
function displayMessageEnhanced(data) {
    const container = document.getElementById('messagesContainer');
    if (!container) return;
    
    const messageId = `msg_${data.id || Date.now()}`;
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${data.sender_type}`;
    messageDiv.setAttribute('data-message-id', messageId);
    
    const bubble = document.createElement('div');
    bubble.className = 'message-bubble';
    
    bubble.textContent = data.message;
    
    const time = document.createElement('div');
    time.className = 'message-time';
    time.textContent = formatTime(data.timestamp);
    
    // Add read receipt for sent messages
    if (data.sender_type === userType && userType !== 'system') {
        const status = document.createElement('div');
        status.className = 'message-status sent';
        time.appendChild(status);
    }
    
    bubble.appendChild(time);
    messageDiv.appendChild(bubble);
    container.appendChild(messageDiv);
    
    container.scrollTop = container.scrollHeight;
    
    // Mark message as delivered after a short delay
    if (data.sender_type !== userType) {
        setTimeout(() => markMessageAsRead(messageId), 1000);
    }
}



// Enhanced typing indicator with user names
function handleTypingIndicatorEnhanced(data) {
    const container = document.getElementById('messagesContainer');
    const indicator = document.getElementById('typingIndicator');
    
    if (!container || !indicator) return;
    
    if (data.is_typing && data.user_type !== userType) {
        const userName = data.user_name || (data.user_type === 'agent' ? 'Agent' : 'Customer');
        indicator.innerHTML = `
            <div class="typing-indicator-enhanced">
                <span>${userName} is typing</span>
                <div class="typing-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        `;
        indicator.style.display = 'block';
        container.scrollTop = container.scrollHeight;
    } else {
        indicator.style.display = 'none';
    }
}

// Customer information panel


// Notification system
function showNotification(message, type = 'info', duration = 3000) {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    // Position notification
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 24px;
        border-radius: 6px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
    `;
    
    // Set background color based on type
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
    };
    
    notification.style.backgroundColor = colors[type] || colors.info;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, duration);
}

function showConnectionNotification(status) {
    const messages = {
        connecting: 'Connecting to chat server...',
        connected: 'Connected to chat server',
        disconnected: 'Disconnected from chat server',
        reconnecting: 'Reconnecting...'
    };
    
    const types = {
        connecting: 'info',
        connected: 'success',
        disconnected: 'error',
        reconnecting: 'warning'
    };
    
    if (messages[status]) {
        showNotification(messages[status], types[status], 2000);
    }
}

// Utility functions
function getCurrentSessionId() {
    return currentSessionId || sessionId;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function markMessageAsRead(messageId) {
    // Send read receipt via WebSocket
    if (ws && ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify({
            type: 'message_read',
            message_id: messageId,
            session_id: getCurrentSessionId()
        }));
    }
}

function updateMessageStatus(messageId, status) {
    const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
    if (messageElement) {
        const statusElement = messageElement.querySelector('.message-status');
        if (statusElement) {
            statusElement.className = `message-status ${status}`;
        }
    }
}

// Quick actions for common responses
function initQuickActions() {
    const chatInputArea = document.querySelector('.chat-input-area');
    if (!chatInputArea || userType !== 'agent') return;
    
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
            console.error('Error loading canned responses:', error);
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
    if (message && getCurrentSessionId()) {
        // Send via WebSocket
        if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({
                type: 'message',
                session_id: getCurrentSessionId(),
                message: message,
                sender_type: userType,
                sender_id: userId
            }));
        }
    }
}

// Initialize all enhanced features
document.addEventListener('DOMContentLoaded', function() {
    // Initialize enhanced WebSocket if original fails
    if (typeof initWebSocket === 'function') {
        const originalInit = initWebSocket;
        initWebSocket = function() {
            try {
                originalInit();
            } catch (error) {
                console.log('Falling back to enhanced WebSocket');
                ws = new EnhancedWebSocket('ws://localhost:8081');
                ws.onopen = function() {
                    console.log('Enhanced WebSocket connected');
                    // Register connection as before
                };
                ws.onmessage = function(event) {
                    const data = JSON.parse(event.data);
                    handleWebSocketMessage(data);
                };
            }
        };
    }
    
    // Initialize all enhanced features
    setTimeout(() => {

        initQuickActions();
    }, 1000);
});

// Handle page visibility change for connection management
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        // Page is hidden, reduce connection activity
        if (ws && typeof ws.stopHeartbeat === 'function') {
            ws.stopHeartbeat();
        }
    } else {
        // Page is visible, resume full activity
        if (ws && typeof ws.startHeartbeat === 'function') {
            ws.startHeartbeat();
        }
    }
});

// Handle browser back/forward buttons
window.addEventListener('popstate', function(event) {
    // Handle navigation state if needed
    if (currentSessionId && event.state && event.state.sessionId !== currentSessionId) {
        // Session changed, update UI accordingly
        currentSessionId = event.state.sessionId;
        if (currentSessionId) {
            loadChatHistoryForSession(currentSessionId);
        }
    }
});