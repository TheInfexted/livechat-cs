<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="login-container">
    <div class="login-box">
        <h2>Admin Login</h2>
        
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger">
                <?= session()->getFlashdata('error') ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="/login">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
    </div>
</div>

<style>
.login-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.login-box {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    width: 100%;
    max-width: 400px;
}

.login-box h2 {
    text-align: center;
    margin-bottom: 2rem;
    color: #333;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: #555;
    font-weight: 500;
}

.form-group input {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 1rem;
}

.btn {
    width: 100%;
    padding: 0.75rem;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 5px;
    font-size: 1rem;
    cursor: pointer;
    margin-top: 1rem;
}

.btn:hover {
    background: #5a6fd8;
}

.alert {
    padding: 0.75rem;
    margin-bottom: 1rem;
    border-radius: 5px;
    color: #721c24;
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
}

.login-info {
    margin-top: 2rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 5px;
    border-left: 4px solid #667eea;
}

.login-info p {
    margin: 0.25rem 0;
    font-size: 0.9rem;
}

.login-info code {
    background: #e9ecef;
    padding: 0.2rem 0.4rem;
    border-radius: 3px;
    font-family: monospace;
}
</style>
<?= $this->endSection() ?> 