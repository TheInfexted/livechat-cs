<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>">
<div class="admin-agents">
    <div class="dashboard-header">
        <h2>Manage Agents</h2>
        <div class="header-actions">
            <?php if ($user['role'] === 'admin'): ?>
                <button class="btn btn-primary" onclick="openAddModal()">Add New Agent</button>
            <?php endif; ?>
            <a href="<?= base_url('admin') ?>" class="btn btn-secondary">Back to Dashboard</a>
        </div>
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
                            <button class="btn btn-sm btn-primary" onclick="openEditModal(<?= $agent['id'] ?>, '<?= esc($agent['username']) ?>', '<?= esc($agent['email']) ?>', '<?= esc($agent['role']) ?>')">Edit</button>
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

<!-- Edit Agent Modal -->
<div id="editAgentModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Agent</h3>
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="editAgentForm">
                <input type="hidden" id="editAgentId" name="agent_id">
                <div class="form-group">
                    <label for="editUsername">Username</label>
                    <input type="text" id="editUsername" name="username" required>
                </div>
                <div class="form-group">
                    <label for="editEmail">Email</label>
                    <input type="email" id="editEmail" name="email" required>
                </div>
                <div class="form-group">
                    <label for="editRole">Role</label>
                    <select id="editRole" name="role" required>
                        <option value="admin">Admin</option>
                        <option value="support">Support</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editPassword">New Password (leave blank to keep current)</label>
                    <input type="password" id="editPassword" name="password" placeholder="Enter new password">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Agent</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Agent Modal -->
<div id="addAgentModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Agent</h3>
            <span class="close-modal" onclick="closeAddModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="addAgentForm">
                <div class="form-group">
                    <label for="addUsername">Username</label>
                    <input type="text" id="addUsername" name="username" required>
                </div>
                <div class="form-group">
                    <label for="addEmail">Email</label>
                    <input type="email" id="addEmail" name="email" required>
                </div>
                <div class="form-group">
                    <label for="addRole">Role</label>
                    <select id="addRole" name="role" required>
                        <option value="admin">Admin</option>
                        <option value="support">Support</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="addPassword">Password</label>
                    <input type="password" id="addPassword" name="password" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Agent</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal functions
function openEditModal(agentId, username, email, role) {
    document.getElementById('editAgentId').value = agentId;
    document.getElementById('editUsername').value = username;
    document.getElementById('editEmail').value = email;
    document.getElementById('editRole').value = role;
    document.getElementById('editPassword').value = '';
    document.getElementById('editAgentModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editAgentModal').style.display = 'none';
    document.getElementById('editAgentForm').reset();
}

function openAddModal() {
    document.getElementById('addAgentForm').reset();
    document.getElementById('addAgentModal').style.display = 'block';
}

function closeAddModal() {
    document.getElementById('addAgentModal').style.display = 'none';
    document.getElementById('addAgentForm').reset();
}

// Close modals when clicking outside
window.onclick = function(event) {
    const editModal = document.getElementById('editAgentModal');
    const addModal = document.getElementById('addAgentModal');
    
    if (event.target === editModal) {
        closeEditModal();
    }
    if (event.target === addModal) {
        closeAddModal();
    }
}

// Form submission handlers
document.addEventListener('DOMContentLoaded', function() {
    // Edit agent form submission
    document.getElementById('editAgentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('/admin/agents/edit', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Agent updated successfully!');
                closeEditModal();
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating the agent.');
        });
    });
    
    // Add agent form submission
    document.getElementById('addAgentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('/admin/agents/add', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Agent added successfully!');
                closeAddModal();
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while adding the agent.');
        });
    });
});

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