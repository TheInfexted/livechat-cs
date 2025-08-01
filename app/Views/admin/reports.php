<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>">
<div class="admin-reports">
    <div class="page-header">
        <h2>Chat Reports</h2>
        <a href="<?= base_url('admin') ?>" class="btn btn-secondary">Back to Dashboard</a>
    </div>
    
    <div class="reports-content">
        <div class="report-section">
            <h3>Chat Statistics</h3>
            <p>Detailed reports and analytics will be displayed here.</p>
            <p>This feature is under development.</p>
        </div>
    </div>
</div>
<?= $this->endSection() ?> 