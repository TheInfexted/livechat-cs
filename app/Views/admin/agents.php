<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="admin-agents">
    <div class="page-header">
        <h2>Manage Agents</h2>
        <a href="<?= base_url('admin') ?>" class="btn btn-secondary">Back to Dashboard</a>
    </div>
    
    <div class="agents-list">
        <table class="agents-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($agents as $agent): ?>
                <tr>
                    <td><?= esc($agent['username']) ?></td>
                    <td><?= esc($agent['email']) ?></td>
                    <td>
                        <span class="role-badge role-<?= $agent['role'] ?>">
                            <?= ucfirst($agent['role']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-badge status-<?= $agent['status'] ?? 'active' ?>">
                            <?= ucfirst($agent['status'] ?? 'active') ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary">Edit</button>
                        <button class="btn btn-sm btn-danger">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.admin-agents {
    padding: 2rem;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.agents-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.agents-table th,
.agents-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.agents-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #333;
}

.role-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 3px;
    font-size: 0.8rem;
    font-weight: 500;
}

.role-admin {
    background: #dc3545;
    color: white;
}

.role-support {
    background: #17a2b8;
    color: white;
}

.status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 3px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-active {
    background: #28a745;
    color: white;
}

.status-inactive {
    background: #6c757d;
    color: white;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
    margin-right: 0.25rem;
}

.btn-danger {
    background: #dc3545;
    color: white;
}
</style>
<?= $this->endSection() ?> 