<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/chat-history.css') ?>">

<div class="chat-view-container">
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger">
            <?= session()->getFlashdata('error') ?>
        </div>
    <?php endif; ?>
    
    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success">
            <?= session()->getFlashdata('success') ?>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="page-header">
        <div class="header-content">
            <h2><?= esc($title) ?></h2>
            <div class="header-actions">
                <a href="<?= base_url('chat-history') ?>" class="btn btn-secondary">Back to History</a>
            </div>
        </div>
        <div class="user-info">
            <span>Welcome, <?= esc($user['username']) ?> (<?= ucfirst($user['role']) ?>)</span>
            <a href="<?= base_url('logout') ?>" class="btn btn-logout">Logout</a>
        </div>
    </div>

    <!-- Chat Session Details -->
    <div class="chat-session-details">
        <div class="details-grid">
            <div class="detail-card">
                <h3>Session Information</h3>
                <div class="detail-row">
                    <strong>Session ID:</strong>
                    <span class="session-id"><?= esc($chatSession['session_id']) ?></span>
                </div>
                <div class="detail-row">
                    <strong>Status:</strong>
                    <span class="status-badge status-<?= esc($chatSession['status']) ?>">
                        <?= ucfirst(esc($chatSession['status'])) ?>
                    </span>
                </div>
                <div class="detail-row">
                    <strong>Created:</strong>
                    <span><?= date('M d, Y H:i:s', strtotime($chatSession['created_at'])) ?></span>
                </div>
                <?php if ($chatSession['closed_at']): ?>
                <div class="detail-row">
                    <strong>Closed:</strong>
                    <span><?= date('M d, Y H:i:s', strtotime($chatSession['closed_at'])) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($duration): ?>
                <div class="detail-row">
                    <strong>Duration:</strong>
                    <span>
                        <?php
                        $durationText = '';
                        if ($duration->days > 0) $durationText .= $duration->days . ' days ';
                        if ($duration->h > 0) $durationText .= $duration->h . ' hours ';
                        if ($duration->i > 0) $durationText .= $duration->i . ' minutes ';
                        if ($duration->s > 0) $durationText .= $duration->s . ' seconds';
                        echo trim($durationText);
                        ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <div class="detail-card">
                <h3>Customer Information</h3>
                <div class="detail-row">
                    <strong>Username:</strong>
                    <span><?= esc($chatSession['customer_name']) ?></span>
                </div>
                <div class="detail-row">
                    <strong>Full Name:</strong>
                    <span><?= $chatSession['customer_fullname'] ? esc($chatSession['customer_fullname']) : 'Anonymous' ?></span>
                </div>
                <div class="detail-row">
                    <strong>Email:</strong>
                    <span><?= $chatSession['customer_email'] ? esc($chatSession['customer_email']) : 'Not provided' ?></span>
                </div>
            </div>

            <div class="detail-card">
                <h3>Agent Information</h3>
                <div class="detail-row">
                    <strong>Agent:</strong>
                    <span><?= $chatSession['agent_name'] ? esc($chatSession['agent_name']) : 'Unassigned' ?></span>
                </div>
                <?php if ($chatSession['agent_email']): ?>
                <div class="detail-row">
                    <strong>Agent Email:</strong>
                    <span><?= esc($chatSession['agent_email']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="detail-card">
                <h3>Message Statistics</h3>
                <div class="detail-row total-row">
                    <strong>Total Messages:</strong>
                    <span class="message-count"><?= $messageStats['total_messages'] ?></span>
                </div>
                <div class="detail-row customer-row">
                    <strong>Customer Messages:</strong>
                    <span class="message-count"><?= $messageStats['customer_messages'] ?></span>
                </div>
                <div class="detail-row agent-row">
                    <strong>Agent Messages:</strong>
                    <span class="message-count"><?= $messageStats['agent_messages'] ?></span>
                </div>
                <div class="detail-row system-row">
                    <strong>System Messages:</strong>
                    <span class="message-count"><?= $messageStats['system_messages'] ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Chat Messages -->
    <div class="chat-messages-section">
        <h3>Chat Conversation</h3>
        <div class="messages-container">
            <?php if (empty($messages)): ?>
                <div class="no-messages">
                    <p>No messages found in this chat session.</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <div class="message message-<?= esc($message['sender_type']) ?> <?= $message['message_type'] === 'system' ? 'message-system' : '' ?>">
                        <div class="message-header">
                            <span class="message-sender">
                                <?php if ($message['sender_type'] === 'customer'): ?>
                                    <strong><?= esc($chatSession['customer_name']) ?></strong>
                                <?php elseif ($message['sender_type'] === 'agent'): ?>
                                    <strong><?= $message['sender_name'] ? esc($message['sender_name']) : 'Agent' ?></strong>
                                <?php else: ?>
                                    <strong>System</strong>
                                <?php endif; ?>
                            </span>
                            <span class="message-time" title="<?= esc($message['created_at']) ?>">
                                <?= date('M d, Y H:i:s', strtotime($message['created_at'])) ?>
                            </span>
                        </div>
                        <div class="message-content">
                            <?php if ($message['message_type'] === 'text'): ?>
                                <p><?= nl2br(esc($message['message'])) ?></p>
                            <?php elseif ($message['message_type'] === 'file'): ?>
                                <div class="file-message">
                                    <i class="icon-file"></i>
                                    <span>File: <?= esc($message['file_name']) ?></span>
                                    <?php if ($message['file_path']): ?>
                                        <a href="<?= base_url($message['file_path']) ?>" target="_blank" class="btn btn-sm btn-secondary">Download</a>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($message['message_type'] === 'image'): ?>
                                <div class="image-message">
                                    <p><?= nl2br(esc($message['message'])) ?></p>
                                    <?php if ($message['file_path']): ?>
                                        <img src="<?= base_url($message['file_path']) ?>" alt="<?= esc($message['file_name']) ?>" style="max-width: 300px; height: auto;">
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($message['message_type'] === 'system'): ?>
                                <p class="system-message"><?= nl2br(esc($message['message'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if (!$message['is_read']): ?>
                            <div class="message-status">
                                <span class="unread-indicator" title="Unread">●</span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-scroll to bottom of messages
    const messagesContainer = document.querySelector('.messages-container');
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Print functionality
    const printStyles = `
        <style media="print">
            .page-header, .btn, .header-actions { display: none !important; }
            .chat-view-container { margin: 0; padding: 20px; }
            .message { page-break-inside: avoid; }
            .detail-card { page-break-inside: avoid; }
            @page { margin: 1cm; }
        </style>
    `;
    document.head.insertAdjacentHTML('beforeend', printStyles);
});
</script>
<?= $this->endSection() ?>
