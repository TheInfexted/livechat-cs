<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="admin-dashboard">
    <div class="dashboard-header">
        <h2>Chat Dashboard</h2>
        <div class="user-info">
            <span>Welcome, <?= esc($user['username']) ?></span>
            <span class="status-indicator" id="connectionStatus">Offline</span>
            <a href="<?= base_url('admin/dashboard') ?>" class="btn btn-home">Home</a>
            <a href="<?= base_url('logout') ?>" class="btn btn-logout">Logout</a>
        </div>
    </div>
    
    <div class="dashboard-content">
        <div class="sessions-panel">
            <div class="panel-section">
                <h3>Waiting Sessions <span class="count" id="waitingCount"><?= count($waitingSessions) ?></span></h3>
                <div class="sessions-list" id="waitingSessions">
                    <?php foreach ($waitingSessions as $session): ?>
                    <div class="session-item" data-session-id="<?= $session['session_id'] ?>">
                        <div class="session-info">
                            <strong><?= esc($session['customer_name'] ?? 'Anonymous') ?></strong>
                            <small>Topic: <?= esc($session['chat_topic'] ?? 'No topic specified') ?></small>
                            <small><?= date('H:i', strtotime($session['created_at'])) ?></small>
                        </div>
                        <button class="btn btn-accept" onclick="acceptChat('<?= $session['session_id'] ?>')">Accept</button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="panel-section">
                <h3>Active Chats <span class="count" id="activeCount"><?= count($activeSessions) ?></span></h3>
                <div class="sessions-list" id="activeSessions">
                    <?php foreach ($activeSessions as $session): ?>
                    <div class="session-item active" data-session-id="<?= $session['session_id'] ?>" onclick="openChat('<?= $session['session_id'] ?>')">
                        <div class="session-info">
                            <strong><?= esc($session['customer_name'] ?? 'Anonymous') ?></strong>
                            <small>Topic: <?= esc($session['chat_topic'] ?? 'No topic specified') ?></small>
                            <small>Agent: <?= esc($session['agent_name'] ?? 'Unassigned') ?></small>
                        </div>
                        <span class="unread-badge" style="display: none;">0</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="chat-panel" id="chatPanel" style="display: none;">
            <div class="chat-header">
                <h3 id="chatCustomerName">Select a chat</h3>
                <button class="btn btn-close-chat" onclick="closeCurrentChat()">Close Chat</button>
            </div>
            
            <div class="chat-window">
                <div class="messages-container" id="messagesContainer">
                    <!-- Messages will be loaded here -->
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
        </div>
    </div>
</div>

<script>
    let userType = 'agent';
    let userId = <?= $user['id'] ?>;
    let currentSessionId = null;
    let sessionId = null; // For admin, sessionId is not needed initially
</script>
<?= $this->endSection() ?>