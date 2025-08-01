<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
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

<style>
.admin-reports {
    padding: 2rem;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.reports-content {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.report-section h3 {
    margin-bottom: 1rem;
    color: #333;
}

.report-section p {
    color: #666;
    line-height: 1.6;
}
</style>
<?= $this->endSection() ?> 