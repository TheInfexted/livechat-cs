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
        <div class="header-actions">
            <button class="btn btn-primary" onclick="showAddModal()">Add New Response</button>
            <a href="<?= base_url('admin') ?>" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>
    
    <div class="keyword-responses-container">
        <div class="responses-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>Keyword</th>
                        <th>Response</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($responses)): ?>
                        <tr>
                            <td colspan="5" class="text-center">No automated responses found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($responses as $response): ?>
                            <tr>
                                <td><strong><?= esc($response['keyword']) ?></strong></td>
                                <td class="response-preview"><?= esc(substr($response['response'], 0, 100)) ?><?= strlen($response['response']) > 100 ? '...' : '' ?></td>
                                <td>
                                    <span class="status-badge <?= $response['is_active'] ? 'active' : 'inactive' ?>">
                                        <?= $response['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($response['created_at'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="editResponse(<?= $response['id'] ?>)">Edit</button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteResponse(<?= $response['id'] ?>)">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="responseModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add New Response</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form id="responseForm" method="post" action="<?= base_url('admin/save-keyword-response') ?>">
            <input type="hidden" id="responseId" name="id" value="">
            
            <div class="form-group">
                <label for="keyword">Keyword/Phrase:</label>
                <input type="text" id="keyword" name="keyword" required 
                       placeholder="e.g., hello, refund, help" class="form-control">
                <small class="form-text">Keywords are case-insensitive and will trigger when found in customer messages</small>
            </div>
            
            <div class="form-group">
                <label for="response">Auto Response:</label>
                <textarea id="response" name="response" required rows="4" 
                          placeholder="Enter the automated response message..." class="form-control"></textarea>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="is_active" name="is_active" value="1" checked>
                    Active (response will be sent automatically)
                </label>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Response</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
.keyword-responses-container {
    margin-top: 20px;
}

.responses-table {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th,
.table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

.response-preview {
    max-width: 300px;
    word-wrap: break-word;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.active {
    background-color: #d4edda;
    color: #155724;
}

.status-badge.inactive {
    background-color: #f8d7da;
    color: #721c24;
}

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
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.modal-header {
    padding: 20px;
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 8px 8px 0 0;
}

.modal-header h3 {
    margin: 0;
    color: #495057;
}

.close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: #000;
}

.modal form {
    padding: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #495057;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

.form-text {
    font-size: 12px;
    color: #6c757d;
    margin-top: 5px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    font-weight: normal !important;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    margin-right: 8px;
    width: auto;
}

.form-actions {
    border-top: 1px solid #dee2e6;
    padding-top: 15px;
    text-align: right;
}

.form-actions .btn {
    margin-left: 10px;
}

.header-actions {
    display: flex;
    gap: 10px;
}

.text-center {
    text-align: center;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 12px;
    margin-right: 5px;
}
</style>

<script>
function showAddModal() {
    document.getElementById('responseModal').style.display = 'block';
    document.getElementById('modalTitle').textContent = 'Add New Response';
    document.getElementById('responseForm').reset();
    document.getElementById('responseId').value = '';
    document.getElementById('is_active').checked = true;
}

function editResponse(id) {
    // Fetch response data and populate modal
    fetch(`<?= base_url('admin/get-keyword-response') ?>/${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('responseModal').style.display = 'block';
            document.getElementById('modalTitle').textContent = 'Edit Response';
            document.getElementById('responseId').value = data.id;
            document.getElementById('keyword').value = data.keyword;
            document.getElementById('response').value = data.response;
            document.getElementById('is_active').checked = data.is_active == 1;
        })
        .catch(error => {
            alert('Error loading response data');
            console.error('Error:', error);
        });
}

function deleteResponse(id) {
    if (confirm('Are you sure you want to delete this automated response?')) {
        fetch('<?= base_url('admin/delete-keyword-response') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting response');
            }
        })
        .catch(error => {
            alert('Error deleting response');
            console.error('Error:', error);
        });
    }
}

function closeModal() {
    document.getElementById('responseModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('responseModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>

<?= $this->endSection() ?>
