<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="admin-dashboard">
    <div class="dashboard-header">
        <h2>Admin Dashboard</h2>
        <div class="user-info">
            <span>Welcome, <?= esc($user['username']) ?></span>
            <a href="<?= base_url('logout') ?>" class="btn btn-logout">Logout</a>
        </div>
    </div>
    
    <div class="dashboard-stats">
        <div class="stat-card">
            <h3>Total Sessions</h3>
            <p class="stat-number"><?= $totalSessions ?></p>
        </div>
        <div class="stat-card">
            <h3>Active Chats</h3>
            <p class="stat-number"><?= $activeSessions ?></p>
        </div>
        <div class="stat-card">
            <h3>Waiting</h3>
            <p class="stat-number"><?= $waitingSessions ?></p>
        </div>
        <div class="stat-card">
            <h3>Closed</h3>
            <p class="stat-number"><?= $closedSessions ?></p>
        </div>
    </div>
    
    <div class="dashboard-actions">
        <a href="<?= base_url('admin/chat') ?>" class="btn btn-primary">Manage Chats</a>
        <a href="<?= base_url('admin/agents') ?>" class="btn btn-secondary">Manage Agents</a>
        <a href="<?= base_url('admin/reports') ?>" class="btn btn-info">View Reports</a>
    </div>
</div>

<style>
.admin-dashboard {
    padding: 2rem;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #eee;
}

.dashboard-header h2 {
    margin: 0;
    color: #333;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 5px;
    text-decoration: none;
    font-weight: 500;
    cursor: pointer;
}

.btn-primary {
    background: #667eea;
    color: white;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-info {
    background: #17a2b8;
    color: white;
}

.btn-logout {
    background: #dc3545;
    color: white;
}

.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-card h3 {
    margin: 0 0 0.5rem 0;
    color: #666;
    font-size: 0.9rem;
    text-transform: uppercase;
}

.stat-number {
    margin: 0;
    font-size: 2rem;
    font-weight: bold;
    color: #333;
}

.dashboard-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.dashboard-actions .btn {
    flex: 1;
    min-width: 150px;
    text-align: center;
}
</style>
<?= $this->endSection() ?> 