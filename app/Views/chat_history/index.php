<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/chat-history.css') ?>">

<div class="chat-history-container">
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
                <a href="<?= base_url('admin/dashboard') ?>" class="btn btn-secondary">Back to Dashboard</a>
                <button type="button" id="exportBtn" class="btn btn-success">Export CSV</button>
            </div>
        </div>
        <div class="user-info">
            <span>Welcome, <?= esc($user['username']) ?> (<?= ucfirst($user['role']) ?>)</span>
            <a href="<?= base_url('logout') ?>" class="btn btn-logout">Logout</a>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
        <form method="GET" action="<?= base_url('chat-history') ?>" class="filters-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="search">Search:</label>
                    <input type="text" id="search" name="search" placeholder="Username, full name, or agent..." value="<?= esc($filters['search'] ?? '') ?>">
                </div>
                
                <div class="filter-group">
                    <label for="status">Status:</label>
                    <select id="status" name="status">
                        <option value="">All Status</option>
                        <option value="waiting" <?= ($filters['status'] ?? '') === 'waiting' ? 'selected' : '' ?>>Waiting</option>
                        <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="closed" <?= ($filters['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="date_from">Date From:</label>
                    <input type="date" id="date_from" name="date_from" value="<?= esc($filters['date_from'] ?? '') ?>">
                </div>
                
                <div class="filter-group">
                    <label for="date_to">Date To:</label>
                    <input type="date" id="date_to" name="date_to" value="<?= esc($filters['date_to'] ?? '') ?>">
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary filter-button">Filter</button>
                    <a href="<?= base_url('chat-history') ?>" class="btn btn-secondary filter-button">Clear</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Chat History Table -->
    <div class="table-container">
        <table class="chat-history-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Agent</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Closed</th>
                    <th>Client Last Reply</th>
                    <th>Agent Last Reply</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($chats)): ?>
                    <tr>
                        <td colspan="10" class="no-data">No chat sessions found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($chats as $chat): ?>
                        <tr class="chat-row status-<?= esc($chat['status']) ?>">
                            <td class="chat-id">#<?= esc($chat['id']) ?></td>
                            <td class="username">
                                <span class="username-text"><?= esc($chat['username']) ?></span>
                            </td>
                            <td class="fullname">
                                <?= $chat['fullname'] ? esc($chat['fullname']) : '<span class="anonymous">Anonymous</span>' ?>
                            </td>
                            <td class="agent">
                                <?= $chat['agent_name'] ? esc($chat['agent_name']) : '<span class="unassigned">Unassigned</span>' ?>
                            </td>
                            <td class="status">
                                <span class="status-badge status-<?= esc($chat['status']) ?>">
                                    <?= ucfirst(esc($chat['status'])) ?>
                                </span>
                            </td>
                            <td class="date-time" title="<?= esc($chat['created_at']) ?>">
                                <?= date('M d, Y H:i', strtotime($chat['created_at'])) ?>
                            </td>
                            <td class="date-time" title="<?= esc($chat['closed_at']) ?>">
                                <?= $chat['closed_at'] ? date('M d, Y H:i', strtotime($chat['closed_at'])) : '<span class="not-closed">-</span>' ?>
                            </td>
                            <td class="date-time" title="<?= esc($chat['client_last_reply']) ?>">
                                <?= $chat['client_last_reply'] ? date('M d, Y H:i', strtotime($chat['client_last_reply'])) : '<span class="no-reply">-</span>' ?>
                            </td>
                            <td class="date-time" title="<?= esc($chat['agent_last_reply']) ?>">
                                <?= $chat['agent_last_reply'] ? date('M d, Y H:i', strtotime($chat['agent_last_reply'])) : '<span class="no-reply">-</span>' ?>
                            </td>
                            <td class="actions">
                                <a href="<?= base_url('chat-history/view/' . $chat['id']) ?>" class="btn btn-sm btn-primary" title="View Chat">
                                    <i class="icon-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if (isset($pager) && !empty($pager)): ?>
        <div class="pagination-container">
            <?= $pager ?>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Export functionality
    document.getElementById('exportBtn').addEventListener('click', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const exportUrl = '<?= base_url("chat-history/export") ?>?' + urlParams.toString();
        window.open(exportUrl, '_blank');
    });

    // Auto-refresh every 30 seconds for active chats
    setInterval(function() {
        // Only refresh if viewing active chats
        const statusFilter = document.getElementById('status').value;
        if (statusFilter === 'active' || statusFilter === '') {
            // Check if there are any changes before reloading
            fetch('<?= base_url("chat-history/get-stats") ?>')
                .then(response => response.json())
                .then(data => {
                    // You could update indicators here without full reload
                });
        }
    }, 30000);
});
</script>
<?= $this->endSection() ?>
