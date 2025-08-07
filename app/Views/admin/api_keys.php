<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/chat-history.css') ?>">

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
    
    <!-- Header -->
    <div class="page-header">
        <div class="header-content">
            <h2>🔑 API Key Management</h2>
            <div class="user-info">
                <span>Logged in as <?= esc($user['username']) ?> (<?= ucfirst($user['role']) ?>)</span>
                <a href="<?= base_url('logout') ?>" class="btn btn-logout">Logout</a>
            </div>
        </div>
        <div class="header-actions">
            <a href="<?= base_url('admin/dashboard') ?>" class="btn btn-secondary">← Back to Dashboard</a>
            <button class="btn btn-success" onclick="showCreateApiKeyModal()">+ Create New API Key</button>
        </div>
    </div>

    <!-- API Keys Table -->
    <div class="table-container">
        <table class="chat-history-table">
            <thead>
                <tr>
                    <th>Client Name</th>
                    <th>API Key</th>
                    <th>Status</th>
                    <th>Domain</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($api_keys)): ?>
                    <tr>
                        <td colspan="6" class="no-data">
                            No API keys found. Create your first API key to get started!
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($api_keys as $key): ?>
                        <tr>
                            <td>
                                <strong><?= esc($key['client_name']) ?></strong>
                                <br><small style="color: #666;"><?= esc($key['client_email']) ?></small>
                            </td>
                            <td>
                                <div class="api-key-display">
                                    <code class="api-key-text" id="key-<?= $key['id'] ?>" 
                                          data-key="<?= esc($key['api_key']) ?>"
                                          style="cursor: pointer;" 
                                          title="Click to reveal"
                                          onclick="toggleApiKey(<?= $key['id'] ?>)">
                                        <?= substr($key['api_key'], 0, 8) ?>...
                                    </code>
                                    <button class="btn btn-sm" onclick="copyApiKey('<?= esc($key['api_key']) ?>')" 
                                            title="Copy API Key">📋</button>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $key['status'] ?>">
                                    <?= ucfirst($key['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($key['client_domain']): ?>
                                    <span><?= esc($key['client_domain']) ?></span>
                                <?php else: ?>
                                    <span class="no-data">All domains</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="date-time"><?= date('M j, Y', strtotime($key['created_at'])) ?></span>
                            </td>
                            <td class="actions">
                                <button class="btn btn-sm btn-secondary" onclick="editApiKey(<?= $key['id'] ?>)" title="Edit">✏️</button>
                                <?php if ($key['status'] === 'active'): ?>
                                    <button class="btn btn-sm" style="background: orange; color: white;" 
                                            onclick="suspendApiKey(<?= $key['id'] ?>)" title="Suspend">⏸️</button>
                                    <button class="btn btn-sm" style="background: red; color: white;" 
                                            onclick="revokeApiKey(<?= $key['id'] ?>)" title="Revoke">❌</button>
                                <?php elseif ($key['status'] === 'suspended'): ?>
                                    <button class="btn btn-sm btn-success" onclick="activateApiKey(<?= $key['id'] ?>)" title="Activate">✅</button>
                                    <button class="btn btn-sm" style="background: red; color: white;" 
                                            onclick="revokeApiKey(<?= $key['id'] ?>)" title="Revoke">❌</button>
                                <?php else: ?>
                                    <span class="no-data">Revoked</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create API Key Modal -->
<div id="createApiKeyModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Create New API Key</h3>
            <button class="close-modal" onclick="hideCreateApiKeyModal()">×</button>
        </div>
        <form id="createApiKeyForm">
            <div class="form-group">
                <label for="client_name">Client Name *</label>
                <input type="text" id="client_name" name="client_name" required>
            </div>
            <div class="form-group">
                <label for="client_email">Client Email *</label>
                <input type="email" id="client_email" name="client_email" required>
            </div>
            <div class="form-group">
                <label for="client_domain">Allowed Domains (Optional)</label>
                <input type="text" id="client_domain" name="client_domain" 
                       placeholder="example.com, *.example.com, localhost">
                <small>Leave blank to allow all domains. Use comma to separate multiple domains.</small>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="hideCreateApiKeyModal()">Cancel</button>
                <button type="submit" class="btn btn-success">Create API Key</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit API Key Modal -->
<div id="editApiKeyModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit API Key</h3>
            <button class="close-modal" onclick="hideEditApiKeyModal()">×</button>
        </div>
        <form id="editApiKeyForm">
            <input type="hidden" id="edit_key_id" name="key_id">
            <div class="form-group">
                <label for="edit_client_name">Client Name *</label>
                <input type="text" id="edit_client_name" name="client_name" required>
            </div>
            <div class="form-group">
                <label for="edit_client_email">Client Email *</label>
                <input type="email" id="edit_client_email" name="client_email" required>
            </div>
            <div class="form-group">
                <label for="edit_client_domain">Allowed Domains (Optional)</label>
                <input type="text" id="edit_client_domain" name="client_domain" 
                       placeholder="example.com, *.example.com, localhost">
                <small>Leave blank to allow all domains. Use comma to separate multiple domains.</small>
            </div>
            <div class="form-group">
                <label for="edit_status">Status</label>
                <select id="edit_status" name="status">
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                    <option value="revoked">Revoked</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="hideEditApiKeyModal()">Cancel</button>
                <button type="submit" class="btn btn-success">Update API Key</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Additional styles for API Keys page */
.api-key-display {
    display: flex;
    align-items: center;
    gap: 5px;
}

.api-key-text {
    background: #f8f9fa;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
    font-size: 12px;
}

.usage-bar {
    min-width: 120px;
}

.usage-text {
    font-size: 11px;
    margin-bottom: 2px;
}

.usage-progress {
    width: 100%;
    height: 6px;
    background: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
}

.usage-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #ffc107, #dc3545);
    transition: width 0.3s ease;
}

.badge {
    display: inline-block;
    padding: 0.25em 0.5em;
    font-size: 75%;
    font-weight: 700;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 0.25rem;
    color: #fff;
}

.badge-free { background-color: #6c757d; }
.badge-basic { background-color: #007bff; }
.badge-pro { background-color: #28a745; }
.badge-enterprise { background-color: #6f42c1; }

.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 0;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.close-modal {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal form {
    padding: 20px;
}

.form-row {
    display: flex;
    gap: 15px;
}

.form-row .form-group {
    flex: 1;
}

.modal-actions {
    padding: 20px;
    border-top: 1px solid #dee2e6;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}
</style>

<script>
// Modal functions
function showCreateApiKeyModal() {
    document.getElementById('createApiKeyModal').style.display = 'block';
}

function hideCreateApiKeyModal() {
    document.getElementById('createApiKeyModal').style.display = 'none';
    document.getElementById('createApiKeyForm').reset();
}


// API Key functions
function toggleApiKey(keyId) {
    const keyElement = document.getElementById('key-' + keyId);
    const fullKey = keyElement.getAttribute('data-key');
    
    if (keyElement.textContent.includes('...')) {
        keyElement.textContent = fullKey;
        keyElement.title = 'Click to hide';
    } else {
        keyElement.textContent = fullKey.substr(0, 8) + '...';
        keyElement.title = 'Click to reveal';
    }
}

function copyApiKey(apiKey) {
    navigator.clipboard.writeText(apiKey).then(function() {
        alert('API Key copied to clipboard!');
    }).catch(function() {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = apiKey;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        alert('API Key copied to clipboard!');
    });
}


async function editApiKey(keyId) {
    try {
        const response = await fetch(`<?= base_url('admin/api-keys/edit') ?>/${keyId}`);
        const apiKey = await response.json();
        
        if (apiKey.error) {
            alert('Error: ' + apiKey.error);
            return;
        }
        
        // Populate edit form
        document.getElementById('edit_key_id').value = apiKey.id;
        document.getElementById('edit_client_name').value = apiKey.client_name;
        document.getElementById('edit_client_email').value = apiKey.client_email;
        document.getElementById('edit_client_domain').value = apiKey.client_domain || '';
        document.getElementById('edit_status').value = apiKey.status;
        
        // Show edit modal
        document.getElementById('editApiKeyModal').style.display = 'block';
    } catch (error) {
        alert('Error loading API key data');
    }
}

function hideEditApiKeyModal() {
    document.getElementById('editApiKeyModal').style.display = 'none';
    document.getElementById('editApiKeyForm').reset();
}


function suspendApiKey(keyId) {
    if (confirm('Are you sure you want to suspend this API key?')) {
        fetch(`<?= base_url('admin/api-keys/suspend') ?>/${keyId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('API key suspended successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error suspending API key');
        });
    }
}

function activateApiKey(keyId) {
    if (confirm('Are you sure you want to activate this API key?')) {
        fetch(`<?= base_url('admin/api-keys/activate') ?>/${keyId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('API key activated successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error activating API key');
        });
    }
}

function revokeApiKey(keyId) {
    if (confirm('Are you sure you want to revoke this API key? This action cannot be undone!')) {
        fetch(`<?= base_url('admin/api-keys/revoke') ?>/${keyId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('API key revoked successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error revoking API key');
        });
    }
}

// Form submission
document.getElementById('createApiKeyForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    try {
        const response = await fetch('<?= base_url('admin/api-keys/create') ?>', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(`API Key created successfully!\n\nAPI Key: ${result.api_key}\n\nMake sure to save this key - you won't be able to see it again!`);
            hideCreateApiKeyModal();
            location.reload();
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        alert('Error creating API key');
    }
});

// Edit form submission
document.getElementById('editApiKeyForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    try {
        const response = await fetch('<?= base_url('admin/api-keys/update') ?>', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('API Key updated successfully!');
            hideEditApiKeyModal();
            location.reload();
        } else {
            alert('Error: ' + result.error);
        }
    } catch (error) {
        alert('Error updating API key');
    }
});


// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('createApiKeyModal');
    if (event.target == modal) {
        hideCreateApiKeyModal();
    }
}
</script>

<?= $this->endSection() ?>
