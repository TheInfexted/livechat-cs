<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="chat-container customer-chat">
    <div class="chat-header">
        <h3>Customer Support</h3>
        <span class="status-indicator" id="connectionStatus">Offline</span>
    </div>
    
    <div id="chatInterface">
        <?php if (!$session_id): ?>
        <div class="chat-start-form">
            <h4>Start a Conversation</h4>
            <form id="startChatForm">
                <div class="form-group">
                    <label for="customerProblem">What do you need help with? *</label>
                    <input type="text" id="customerProblem" name="name" required placeholder="Describe your issue or question...">
                </div>
                <div class="form-group">
                    <label for="customerEmail">Email (Optional)</label>
                    <input type="email" id="customerEmail" name="email">
                </div>
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
    
    // Load quick actions when page loads
    document.addEventListener('DOMContentLoaded', function() {
        if (sessionId) {
            fetchQuickActions();
        }
    });

    function fetchQuickActions() {
        fetch('/chat/quick-actions')
            .then(response => response.json())
            .then(data => {
                const quickActionsButtons = document.getElementById('quickActionsButtons');
                quickActionsButtons.innerHTML = '';

                data.forEach(action => {
                    const btn = document.createElement('button');
                    btn.classList.add('quick-action-btn');
                    btn.textContent = action.display_name;
                    btn.onclick = () => sendQuickMessage(action.keyword);
                    quickActionsButtons.appendChild(btn);
                });
            })
            .catch(error => console.error('Error fetching quick actions:', error));
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

</script>
<?= $this->endSection() ?>