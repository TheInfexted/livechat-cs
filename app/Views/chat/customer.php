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
                    <label for="customerName">Your Name *</label>
                    <input type="text" id="customerName" name="name" required>
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
</script>
<?= $this->endSection() ?>