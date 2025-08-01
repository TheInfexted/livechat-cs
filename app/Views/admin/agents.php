<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>">
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
                        <?php if ($user['role'] === 'admin'): ?>
                            <button class="btn btn-sm btn-primary" onclick="editAgent(<?= $agent['id'] ?>, '<?= esc($agent['username']) ?>', '<?= esc($agent['email']) ?>', '<?= esc($agent['role']) ?>')">Edit</button>
                            <button class="btn btn-sm btn-danger" onclick="deleteAgent(<?= $agent['id'] ?>, '<?= esc($agent['username']) ?>')">Delete</button>
                        <?php else: ?>
                            <span class="text-muted">View Only</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function editAgent(agentId, username, email, role) {
    const newUsername = prompt('Enter new username:', username);
    if (!newUsername) return;
    
    const newEmail = prompt('Enter new email:', email);
    if (!newEmail) return;
    
    const newRole = prompt('Enter new role (admin/support):', role);
    if (!newRole || !['admin', 'support'].includes(newRole)) {
        alert('Invalid role. Must be "admin" or "support".');
        return;
    }
    
    const formData = new FormData();
    formData.append('agent_id', agentId);
    formData.append('username', newUsername);
    formData.append('email', newEmail);
    formData.append('role', newRole);
    
    fetch('/admin/agents/edit', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Agent updated successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the agent.');
    });
}

function deleteAgent(agentId, username) {
    if (!confirm(`Are you sure you want to delete agent "${username}"?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('agent_id', agentId);
    
    fetch('/admin/agents/delete', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Agent deleted successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the agent.');
    });
}
</script>
<?= $this->endSection() ?> 