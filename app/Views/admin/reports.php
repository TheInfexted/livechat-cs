<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="admin-reports">
    <div class="page-header">
        <h2>Chat Reports & Analytics</h2>
        <div class="header-actions">
            <form method="GET" class="date-filter-form">
                <input type="date" name="date_from" value="<?= $dateFrom ?>" class="form-control">
                <span>to</span>
                <input type="date" name="date_to" value="<?= $dateTo ?>" class="form-control">
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
            <a href="<?= base_url('admin') ?>" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>
    
    <!-- Key Metrics -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-icon">📊</div>
            <div class="metric-content">
                <h3><?= $stats['total_sessions'] ?? 0 ?></h3>
                <p>Total Sessions</p>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon">⏱️</div>
            <div class="metric-content">
                <h3><?= $stats['avg_session_duration'] ?? 0 ?> min</h3>
                <p>Avg Session Duration</p>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon">⚡</div>
            <div class="metric-content">
                <h3><?= $stats['avg_first_response'] ?? 0 ?> min</h3>
                <p>Avg First Response</p>
            </div>
        </div>
        
        <div class="metric-card">
            <div class="metric-icon">👥</div>
            <div class="metric-content">
                <h3><?= $totalAgents ?></h3>
                <p>Total Agents</p>
            </div>
        </div>
    </div>
    
    <!-- Charts Section -->
    <div class="charts-section">
        <div class="chart-container">
            <h3>Hourly Chat Distribution</h3>
            <canvas id="hourlyChart"></canvas>
        </div>
    </div>
    
    <!-- Response Time Analysis -->
    <div class="reports-section">
        <h3>Response Time Analysis</h3>
        <div class="analysis-grid">
            <?php if (!empty($responseTimeAnalysis)): ?>
                <?php $analysis = $responseTimeAnalysis[0]; ?>
                <div class="analysis-card">
                    <h4>Average First Response</h4>
                    <p class="analysis-value"><?= number_format($analysis['avg_first_response'] / 60, 1) ?> minutes</p>
                </div>
                <div class="analysis-card">
                    <h4>Fastest Response</h4>
                    <p class="analysis-value"><?= number_format($analysis['min_response_time'] / 60, 1) ?> minutes</p>
                </div>
                <div class="analysis-card">
                    <h4>Slowest Response</h4>
                    <p class="analysis-value"><?= number_format($analysis['max_response_time'] / 60, 1) ?> minutes</p>
                </div>
            <?php else: ?>
                <p class="text-muted">No response time data available</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Busiest Hours -->
    <div class="reports-section">
        <h3>Busiest Hours (Last 7 Days)</h3>
        <div class="busy-hours-grid">
            <?php foreach (array_slice($busiestHours, 0, 5) as $hour): ?>
                <div class="busy-hour-card">
                    <div class="hour-label"><?= $hour['hour'] ?>:00</div>
                    <div class="hour-stats">
                        <span class="session-count"><?= $hour['session_count'] ?> sessions</span>
                        <span class="avg-duration"><?= number_format($hour['avg_duration'], 1) ?> min avg</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Recent Sessions -->
    <div class="reports-section">
        <h3>Recent Sessions</h3>
        <div class="table-container">
            <table class="sessions-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Agent</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Duration</th>
                        <th>Rating</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentSessions as $session): ?>
                    <tr>
                        <td><?= htmlspecialchars($session['customer_name']) ?></td>
                        <td><?= htmlspecialchars($session['agent_name'] ?? 'Unassigned') ?></td>
                        <td>
                            <span class="status-badge status-<?= $session['status'] ?>">
                                <?= ucfirst($session['status']) ?>
                            </span>
                        </td>
                        <td><?= date('M j, Y g:i A', strtotime($session['created_at'])) ?></td>
                        <td>
                            <?php 
                            if ($session['closed_at']) {
                                $duration = strtotime($session['closed_at']) - strtotime($session['created_at']);
                                echo round($duration / 60, 1) . ' min';
                            } else {
                                echo 'Active';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($session['rating']): ?>
                                <span class="rating-display">
                                    <?= str_repeat('⭐', $session['rating']) ?>
                                    (<?= $session['rating'] ?>/5)
                                </span>
                            <?php else: ?>
                                <span class="text-muted">No rating</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Hourly Distribution Chart
const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
const hourlyChart = new Chart(hourlyCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($hourlyData, 'hour')) ?>,
        datasets: [{
            label: 'Chat Sessions',
            data: <?= json_encode(array_column($hourlyData, 'chat_count')) ?>,
            borderColor: '#4CAF50',
            backgroundColor: 'rgba(76, 175, 80, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Chat Volume by Hour'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Number of Chats'
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'Hour of Day'
                }
            }
        }
    }
});

// Top Agents Chart
const agentsCtx = document.getElementById('agentsChart').getContext('2d');
const agentsChart = new Chart(agentsCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($topAgents, 'username')) ?>,
        datasets: [{
            label: 'Average Rating',
            data: <?= json_encode(array_column($topAgents, 'avg_rating')) ?>,
            backgroundColor: '#2196F3',
            borderColor: '#1976D2',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Agent Performance by Rating'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 5,
                title: {
                    display: true,
                    text: 'Average Rating'
                }
            }
        }
    }
});
</script>
<?= $this->endSection() ?> 