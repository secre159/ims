<?php
$page_title = 'System Logs';
require_once('includes/load.php');
page_require_level(2); // IT level access

// Get filter parameters
$log_type = isset($_GET['log_type']) ? $_GET['log_type'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Build WHERE clause based on filters
$where_clauses = ["DATE(l.date) BETWEEN '{$start_date}' AND '{$end_date}'"];

if ($log_type !== 'all') {
    $where_clauses[] = "l.action = '{$log_type}'";
}

$where_sql = implode(' AND ', $where_clauses);

// Fetch system logs
$system_logs = find_by_sql("
    SELECT 
        l.*,
        u.name as user_name,
        u.username as user_username,
        u.user_level
    FROM user_activity_logs l
    LEFT JOIN users u ON l.user_id = u.id
    WHERE {$where_sql}
    ORDER BY l.date DESC
    LIMIT 500
");

// Get log type statistics
$log_stats = find_by_sql("
    SELECT 
        action,
        COUNT(*) as count
    FROM user_activity_logs
    WHERE DATE(date) BETWEEN '{$start_date}' AND '{$end_date}'
    GROUP BY action
");

$stats_by_type = [];
foreach($log_stats as $stat) {
    $stats_by_type[$stat['action']] = $stat['count'];
}

$user_levels = [
    1 => 'Admin',
    2 => 'IT',
    3 => 'User'
];
?>

<?php include_once('layouts/header.php'); ?>

<style>
:root {
    --primary-green: #1e7e34;
    --dark-green: #155724;
    --light-green: #28a745;
    --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.logs-header {
    background: linear-gradient(135deg, var(--dark-green) 0%, #000 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 15px;
    margin-bottom: 2rem;
    box-shadow: var(--card-shadow);
}

.stats-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
    margin-bottom: 1rem;
    border-left: 4px solid var(--light-green);
    text-align: center;
}

.stats-card h3 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary-green);
    margin: 0;
}

.stats-card p {
    margin: 0;
    color: #6c757d;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.85rem;
}

.filter-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
    margin-bottom: 2rem;
}

.log-entry {
    border-left: 4px solid #dee2e6;
    padding: 1rem;
    margin-bottom: 0.5rem;
    background: white;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.log-entry:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-left-color: var(--light-green);
}

.log-entry.log-login {
    border-left-color: #28a745;
}

.log-entry.log-logout {
    border-left-color: #6c757d;
}

.log-entry.log-create,
.log-entry.log-add {
    border-left-color: #17a2b8;
}

.log-entry.log-update,
.log-entry.log-edit {
    border-left-color: #ffc107;
}

.log-entry.log-delete {
    border-left-color: #dc3545;
}

.table th {
    background: var(--dark-green);
    color: white;
    font-weight: 600;
    border: none;
}

.table-hover tbody tr:hover {
    background-color: rgba(40, 167, 69, 0.05);
}

.badge-action {
    padding: 0.5rem 0.75rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.8rem;
}

.log-time {
    font-size: 0.85rem;
    color: #6c757d;
}

.log-user {
    font-weight: 600;
    color: var(--primary-green);
}

.log-description {
    margin: 0.5rem 0 0 0;
    color: #495057;
}
</style>

<div class="logs-header">
    <h5><i class="fas fa-server me-2"></i> System Activity Logs</h5>
    <p class="mb-0">Monitor and track all system activities</p>
</div>

<!-- Filter Card -->
<div class="filter-card">
    <form method="GET" action="" class="row g-3">
        <div class="col-md-3">
            <label class="form-label">Log Type</label>
            <select name="log_type" class="form-select">
                <option value="all" <?php echo $log_type == 'all' ? 'selected' : ''; ?>>All Types</option>
                <option value="login" <?php echo $log_type == 'login' ? 'selected' : ''; ?>>Login</option>
                <option value="logout" <?php echo $log_type == 'logout' ? 'selected' : ''; ?>>Logout</option>
                <option value="add" <?php echo $log_type == 'add' ? 'selected' : ''; ?>>Add/Create</option>
                <option value="update" <?php echo $log_type == 'update' ? 'selected' : ''; ?>>Update/Edit</option>
                <option value="delete" <?php echo $log_type == 'delete' ? 'selected' : ''; ?>>Delete</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>" required>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-success me-2">
                <i class="fas fa-filter"></i> Filter
            </button>
            <a href="system_logs.php" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Reset
            </a>
        </div>
    </form>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="stats-card">
            <h3><?php echo array_sum($stats_by_type); ?></h3>
            <p>Total Logs</p>
        </div>
    </div>
    <div class="col-md-2">
        <div class="stats-card" style="border-left-color: #28a745;">
            <h3><?php echo isset($stats_by_type['login']) ? $stats_by_type['login'] : 0; ?></h3>
            <p>Logins</p>
        </div>
    </div>
    <div class="col-md-2">
        <div class="stats-card" style="border-left-color: #6c757d;">
            <h3><?php echo isset($stats_by_type['logout']) ? $stats_by_type['logout'] : 0; ?></h3>
            <p>Logouts</p>
        </div>
    </div>
    <div class="col-md-2">
        <div class="stats-card" style="border-left-color: #17a2b8;">
            <h3><?php echo isset($stats_by_type['add']) ? $stats_by_type['add'] : 0; ?></h3>
            <p>Created</p>
        </div>
    </div>
    <div class="col-md-2">
        <div class="stats-card" style="border-left-color: #ffc107;">
            <h3><?php echo isset($stats_by_type['update']) ? $stats_by_type['update'] : 0; ?></h3>
            <p>Updated</p>
        </div>
    </div>
    <div class="col-md-2">
        <div class="stats-card" style="border-left-color: #dc3545;">
            <h3><?php echo isset($stats_by_type['delete']) ? $stats_by_type['delete'] : 0; ?></h3>
            <p>Deleted</p>
        </div>
    </div>
</div>

<!-- System Logs Table -->
<div class="card">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i> Activity Log Details</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="systemLogsTable">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($system_logs)): ?>
                        <?php foreach($system_logs as $log): ?>
                        <tr>
                            <td>
                                <div class="log-time">
                                    <?php echo date('M d, Y', strtotime($log['date'])); ?><br>
                                    <?php echo date('h:i:s A', strtotime($log['date'])); ?>
                                </div>
                            </td>
                            <td>
                                <div class="log-user"><?php echo $log['user_name']; ?></div>
                                <small class="text-muted"><?php echo $log['user_username']; ?></small>
                            </td>
                            <td>
                                <span class="badge badge-action bg-info">
                                    <?php echo $user_levels[$log['user_level']]; ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $action_colors = [
                                    'login' => 'success',
                                    'logout' => 'secondary',
                                    'add' => 'info',
                                    'update' => 'warning',
                                    'delete' => 'danger'
                                ];
                                $color = isset($action_colors[$log['action']]) ? $action_colors[$log['action']] : 'primary';
                                ?>
                                <span class="badge badge-action bg-<?php echo $color; ?>">
                                    <?php echo strtoupper($log['action']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="log-description"><?php echo $log['info']; ?></div>
                            </td>
                            <td>
                                <small class="text-muted"><?php echo $log['ip'] ?? 'N/A'; ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No logs found</h5>
                                <p class="text-muted">No activity logs match your filter criteria</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#systemLogsTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 25,
        language: {
            search: "Search logs:",
            lengthMenu: "Show _MENU_ entries"
        }
    });
});
</script>

<?php include_once('layouts/footer.php'); ?>
