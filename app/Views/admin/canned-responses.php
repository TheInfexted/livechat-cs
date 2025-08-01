<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>">
<div class="admin-canned-responses">
    <div class="page-header">
        <h2>Manage Canned Responses</h2>
        <div class="header-actions">
            <button class="btn btn-primary" onclick="openAddModal()">Add New Response</button>
            <a href="<?= base_url('admin/dashboard') ?>" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success"><?= session()->getFlashdata('success') ?></div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><?= session()->getFlashdata('error') ?></div>
    <?php endif; ?>

    <div class="responses-grid">
        <?php foreach ($responses as $response): ?>
            <div class="response-card">
                <div class="response-header">
                    <h3><?= esc($response['title']) ?></h3>
                    <div class="response-actions">
                        <button class="btn btn-sm btn-primary" onclick="editResponse(<?= $response['id'] ?>)">Edit</button>
                        <button class="btn btn-sm btn-danger" onclick="deleteResponse(<?= $response['id'] ?>)">Delete</button>
                    </div>
                </div>
                <div class="response-content">
                    <p><?= esc($response['content']) ?></p>
                </div>
                <div class="response-meta">
                    <span class="category"><?= esc($response['category']) ?></span>
                    <?php if ($response['is_global']): ?>
                        <span class="badge global">Global</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

    <!-- Add/Edit Modal -->
    <div id="responseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Response</h3>
                <button class="close-modal" onclick="closeModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="responseForm" method="POST">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="content">Content</label>
                        <textarea id="content" name="content" rows="4" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category">
                            <option value="greeting">Greeting</option>
                            <option value="general">General</option>
                            <option value="technical">Technical</option>
                            <option value="closing">Closing</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="is_global" name="is_global" value="1">
                            Make this response available to all agents
                        </label>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn btn-primary">Save Response</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        
        let editingId = null;

        function openAddModal() {
            editingId = null;
            document.getElementById('modalTitle').textContent = 'Add New Response';
            document.getElementById('responseForm').reset();
            document.getElementById('responseForm').action = '<?= base_url('admin/canned-responses/save') ?>';
            document.getElementById('responseModal').style.display = 'flex';
        }

        function editResponse(id) {
            editingId = id;
            document.getElementById('modalTitle').textContent = 'Edit Response';
            document.getElementById('responseForm').action = '<?= base_url('admin/canned-responses/save') ?>';
            
            // Fetch response data and populate form
            fetch(`<?= base_url('admin/canned-responses/get/') ?>${id}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('title').value = data.title;
                    document.getElementById('content').value = data.content;
                    document.getElementById('category').value = data.category;
                    document.getElementById('is_global').checked = data.is_global == 1;
                });
            
            document.getElementById('responseModal').style.display = 'flex';
        }

        function deleteResponse(id) {
            if (confirm('Are you sure you want to delete this response?')) {
                fetch('<?= base_url('admin/canned-responses/delete') ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${id}`
                }).then(() => {
                    location.reload();
                });
            }
        }

        function closeModal() {
            document.getElementById('responseModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('responseModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
<?= $this->endSection() ?> 