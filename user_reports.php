<?php
$page_title = 'User Reports';
require_once('includes/load.php');
page_require_level(2); // IT level access

// Get date range from request or default to last 30 days
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get user statistics
$user_stats = find_by_sql("
    SELECT 
        u.id,
        u.name,
        u.username,
        u.user_level,
        u.status,
        u.last_login,
        COUNT(DISTINCT r.id) as total_requests,
        COUNT(DISTINCT CASE WHEN r.status = 'Completed' THEN r.id END) as completed_requests,
        COUNT(DISTINCT CASE WHEN r.status = 'Pending' THEN r.id END) as pending_requests,
        COUNT(DISTINCT CASE WHEN r.status IN ('Cancelled', 'Canceled', 'Declined') THEN r.id END) as cancelled_requests
    FROM users u
    LEFT JOIN requests r ON u.id = r.requested_by 
        AND r.date BETWEEN '{$start_date}' AND '{$end_date}'
    GROUP BY u.id, u.name, u.username, u.user_level, u.status, u.last_login
    ORDER BY total_requests DESC
");

// Get overall statistics
$overall_stats = find_by_sql("
    SELECT 
        COUNT(DISTINCT u.id) as total_users,
        COUNT(DISTINCT CASE WHEN u.status = 1 THEN u.id END) as active_users,
        COUNT(DISTINCT r.id) as total_requests,
        COUNT(DISTINCT CASE WHEN r.status = 'Completed' THEN r.id END) as completed_requests
    FROM users u
    LEFT JOIN requests r ON u.id = r.requested_by 
        AND r.date BETWEEN '{$start_date}' AND '{$end_date}'
")[0];

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

.report-header {
    background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
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
}

.stats-card h3 {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--primary-green);
    margin: 0;
}

.stats-card p {
    margin: 0;
    color: #6c757d;
    font-weight: 600;
}

.filter-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
    margin-bottom: 2rem;
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

.badge-custom {
    padding: 0.5rem 0.75rem;
    border-radius: 20px;
    font-weight: 600;
}
</style>

<div class="report-header">
    <h5><i class="fas fa-chart-bar me-2"></i> User Activity Reports</h5>
    <p class="mb-0">Analyze user activity and request statistics</p>
</div>

<!-- Date Filter -->
<div class="filter-card">
    <form method="GET" action="" class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>" required>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <button type="submit" class="btn btn-success me-2">
                <i class="fas fa-filter"></i> Filter
            </button>
            <a href="user_reports.php" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Reset
            </a>
        </div>
    </form>
</div>

<!-- Overall Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stats-card">
            <h3><?php echo $overall_stats['total_users']; ?></h3>
            <p>Total Users</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <h3><?php echo $overall_stats['active_users']; ?></h3>
            <p>Active Users</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <h3><?php echo $overall_stats['total_requests']; ?></h3>
            <p>Total Requests</p>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <h3><?php echo $overall_stats['completed_requests']; ?></h3>
            <p>Completed</p>
        </div>
    </div>
</div>

<!-- User Activity Table -->
<div class="card">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0"><i class="fas fa-users me-2"></i> User Activity Details</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="userReportsTable">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Total Requests</th>
                        <th>Completed</th>
                        <th>Pending</th>
                        <th>Cancelled</th>
                        <th>Last Login</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($user_stats as $user): ?>
                    <tr>
                        <td>
                            <div><strong><?php echo $user['name']; ?></strong></div>
                            <small class="text-muted"><?php echo $user['username']; ?></small>
                        </td>
                        <td>
                            <span class="badge badge-custom bg-info">
                                <?php echo $user_levels[$user['user_level']]; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-custom <?php echo $user['status'] == 1 ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo $user['status'] == 1 ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td><strong><?php echo $user['total_requests']; ?></strong></td>
                        <td><span class="text-success"><?php echo $user['completed_requests']; ?></span></td>
                        <td><span class="text-warning"><?php echo $user['pending_requests']; ?></span></td>
                        <td><span class="text-danger"><?php echo $user['cancelled_requests']; ?></span></td>
                        <td>
                            <small><?php echo $user['last_login'] ? date('M d, Y h:i A', strtotime($user['last_login'])) : 'Never'; ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#userReportsTable').DataTable({
        order: [[3, 'desc']],
        pageLength: 25,
        language: {
            search: "Search users:",
            lengthMenu: "Show _MENU_ users"
        }
    });
});
</script>

<?php include_once('layouts/footer.php'); ?>
