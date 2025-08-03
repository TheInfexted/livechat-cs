<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>">
<div class="admin-dashboard">
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
    <div class="dashboard-header">
        <h2><?= esc($title) ?></h2>
        <div class="user-info">
            <span>Welcome, <?= esc($user['username']) ?> (<?= ucfirst($user['role']) ?>)</span>
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
        <?php if ($user['role'] === 'admin'): ?>
            <a href="<?= base_url('admin/agents') ?>" class="btn btn-secondary">Manage Agents</a>
        <?php endif; ?>

        <a href="<?= base_url('admin/canned-responses') ?>" class="btn btn-info">Canned Responses</a>
        <a href="<?= base_url('admin/keyword-responses') ?>" class="btn btn-primary">Automated Responses</a>
    </div>
</div>
<?= $this->endSection() ?> 