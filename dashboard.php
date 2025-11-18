<?php
// dashboard.php
require_once 'auth_check.php';
check_auth(); // Redirects to login.php if not logged in

// Get user data for display and logic
$role = $_SESSION['user_role'];
$firstname = 'User'; // You would fetch this from the database using $_SESSION['user_id']
$woreda_name = 'N/A'; // Similarly, fetch this based on the user's district_id

// Include the database connection
require_once 'config.php';

// --- 1. Filter Logic based on Role ---
$where_clause = "";
$bind_params = [];
$role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id']; // Needed to potentially fetch the user's woreda

// Fetch the user's district_id if not an Admin
$user_district_id = null;
if ($role != 'Admin') {
    $stmt = $pdo->prepare("SELECT district_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_district_id = $stmt->fetchColumn();

    // Zone users should see all districts in their zone
    if ($role == 'Woreda') {
        $where_clause = "WHERE w.id = :district_id";
        $bind_params['district_id'] = $user_district_id;
    } elseif ($role == 'Zone') {
        $where_clause = "WHERE w.parent_id = :zone_id";
        $bind_params['zone_id'] = $user_district_id;
    }
}

// --- 2. Fetch Total Sessions Count ---
$sql_count = "
    SELECT COUNT(ts.id) 
    FROM training_sessions ts
    JOIN districts w ON ts.district_id = w.id 
    " . $where_clause;
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($bind_params);
$total_sessions = $stmt_count->fetchColumn();

// --- 3. Fetch Total Trainees Count ---
$sql_trainees_count = "
    SELECT COUNT(t.id) 
    FROM trainees t
    JOIN training_sessions ts ON t.session_id = ts.id
    JOIN districts w ON ts.district_id = w.id 
    " . $where_clause;
$stmt_trainees = $pdo->prepare($sql_trainees_count);
$stmt_trainees->execute($bind_params);
$total_trainees = $stmt_trainees->fetchColumn();

// --- 4. Fetch Total Categories Count ---
$sql_category_count = "
    SELECT COUNT(DISTINCT tc.id)
    FROM training_sessions ts
    JOIN training_categories tc ON ts.category_id = tc.id
    JOIN districts w ON ts.district_id = w.id 
    " . $where_clause;
$stmt_category_count = $pdo->prepare($sql_category_count);
$stmt_category_count->execute($bind_params);
$total_categories = $stmt_category_count->fetchColumn();

// --- 5. Fetch Total Active districts Count ---
$sql_woreda_count = "
    SELECT COUNT(DISTINCT ts.district_id)
    FROM training_sessions ts
    JOIN districts w ON ts.district_id = w.id 
    " . $where_clause;
$stmt_woreda_count = $pdo->prepare($sql_woreda_count);
$stmt_woreda_count->execute($bind_params);
$total_districts = $stmt_woreda_count->fetchColumn();

// --- 6. NEW: Fetch Total Devices Count ---
$sql_devices_count = "
    SELECT COUNT(d.id) 
    FROM devices d
    JOIN districts w ON d.district_id = w.id 
    " . $where_clause;
$stmt_devices = $pdo->prepare($sql_devices_count);
$stmt_devices->execute($bind_params);
$total_devices = $stmt_devices->fetchColumn();

// --- 7. NEW: Fetch Total Maintenances Count ---
$sql_maintenances_count = "
    SELECT COUNT(m.id) 
    FROM maintenances m
    JOIN devices d ON m.device_id = d.id
    JOIN districts w ON d.district_id = w.id 
    " . $where_clause;
$stmt_maintenances = $pdo->prepare($sql_maintenances_count);
$stmt_maintenances->execute($bind_params);
$total_maintenances = $stmt_maintenances->fetchColumn();

// --- 8. NEW: Fetch Maintenances by Status ---
$sql_maintenance_status = "
    SELECT m.status, COUNT(m.id) AS count
    FROM maintenances m
    JOIN devices d ON m.device_id = d.id
    JOIN districts w ON d.district_id = w.id 
    " . $where_clause . "
    GROUP BY m.status
    ORDER BY count DESC";
$stmt_maintenance_status = $pdo->prepare($sql_maintenance_status);
$stmt_maintenance_status->execute($bind_params);
$maintenances_by_status = $stmt_maintenance_status->fetchAll();
$maintenances_by_status_json = json_encode($maintenances_by_status);

// --- 9. NEW: Fetch Devices by Type ---
$sql_device_type = "
    SELECT d.device_type, COUNT(d.id) AS count
    FROM devices d
    JOIN districts w ON d.district_id = w.id 
    " . $where_clause . "
    GROUP BY d.device_type
    ORDER BY count DESC
    LIMIT 10";
$stmt_device_type = $pdo->prepare($sql_device_type);
$stmt_device_type->execute($bind_params);
$devices_by_type = $stmt_device_type->fetchAll();
$devices_by_type_json = json_encode($devices_by_type);

// --- 10. Fetch Sessions by Category (for Bar Chart) ---
$sql_category = "
    SELECT tc.name AS category, COUNT(ts.id) AS count
    FROM training_sessions ts
    JOIN training_categories tc ON ts.category_id = tc.id
    JOIN districts w ON ts.district_id = w.id 
    " . $where_clause . "
    GROUP BY tc.name
    ORDER BY count DESC";
$stmt_category = $pdo->prepare($sql_category);
$stmt_category->execute($bind_params);
$sessions_by_category = $stmt_category->fetchAll();
$sessions_by_category_json = json_encode($sessions_by_category);

// --- 11. Fetch Trainees by Gender (for Doughnut Chart) ---
$sql_gender = "
    SELECT t.gender, COUNT(t.id) AS count
    FROM trainees t
    JOIN training_sessions ts ON t.session_id = ts.id
    JOIN districts w ON ts.district_id = w.id
    " . $where_clause . "
    GROUP BY t.gender";
$stmt_gender = $pdo->prepare($sql_gender);
$stmt_gender->execute($bind_params);
$trainees_by_gender = $stmt_gender->fetchAll();
$trainees_by_gender_json = json_encode($trainees_by_gender);

// --- 12. Fetch Recent Sessions (for DataTables) ---
$sql_recent = "
    SELECT ts.title, w.name AS woreda, tc.name AS category, ts.start_date
    FROM training_sessions ts
    JOIN districts w ON ts.district_id = w.id
    JOIN training_categories tc ON ts.category_id = tc.id
    " . $where_clause . "
    ORDER BY ts.start_date DESC
    LIMIT 10";

$stmt_recent = $pdo->prepare($sql_recent);
$stmt_recent->execute($bind_params);
$recent_sessions = $stmt_recent->fetchAll();

// --- 13. NEW: Fetch Recent Maintenances (for DataTables) ---
$sql_recent_maintenances = "
    SELECT m.id, d.name AS device_name, d.device_code, m.status, m.maintenance_date, w.name AS district_name
    FROM maintenances m
    JOIN devices d ON m.device_id = d.id
    JOIN districts w ON d.district_id = w.id
    " . $where_clause . "
    ORDER BY m.maintenance_date DESC
    LIMIT 10";

$stmt_recent_maintenances = $pdo->prepare($sql_recent_maintenances);
$stmt_recent_maintenances->execute($bind_params);
$recent_maintenances = $stmt_recent_maintenances->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | IT Reporting System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@7.1.0/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.3.5/css/dataTables.bootstrap5.css" rel="stylesheet">
</head>

<body>
    <div id="wrapper">
        <?php include_once 'inc/sidebar.php'; ?>

        <div id="main">
            <?php include_once 'inc/nav.php'; ?>

            <div class="content">
                <!-- Welcome Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-0 custom-shadow bg-light">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h3 class="mb-1">Welcome back,
                                            <?php echo htmlspecialchars($_SESSION['user_firstname'] ?? 'User'); ?>!</h3>
                                        <p class="text-muted mb-0">
                                            Role: <span
                                                class="badge bg-primary"><?php echo htmlspecialchars($role); ?></span>
                                            <?php if ($user_district_id): ?>
                                                | District: <span class="badge bg-secondary">
                                                    <?php
                                                    $stmt_district = $pdo->prepare("SELECT name FROM districts WHERE id = ?");
                                                    $stmt_district->execute([$user_district_id]);
                                                    $district_name = $stmt_district->fetchColumn();
                                                    echo htmlspecialchars($district_name);
                                                    ?>
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="text-muted">
                                            <i class="fas fa-calendar-day me-2"></i>
                                            <?php echo date('l, F j, Y'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <!-- Training Statistics -->
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card border-0 custom-shadow h-100 bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-0 opacity-75">Total Sessions</h6>
                                        <h2 class="display-4 fw-bold mb-0"><?php echo number_format($total_sessions); ?>
                                        </h2>
                                    </div>
                                    <i class="fas fa-calendar-alt fa-3x opacity-50"></i>
                                </div>
                                <small class="opacity-75">Training sessions conducted</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card border-0 custom-shadow h-100 bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-0 opacity-75">Total Trainees</h6>
                                        <h2 class="display-4 fw-bold mb-0"><?php echo number_format($total_trainees); ?>
                                        </h2>
                                    </div>
                                    <i class="fas fa-users fa-3x opacity-50"></i>
                                </div>
                                <small class="opacity-75">Individuals trained</small>
                            </div>
                        </div>
                    </div>

                    <!-- IT Assets Statistics -->
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card border-0 custom-shadow h-100 bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-0 opacity-75">Total Devices</h6>
                                        <h2 class="display-4 fw-bold mb-0"><?php echo number_format($total_devices); ?>
                                        </h2>
                                    </div>
                                    <i class="fas fa-laptop fa-3x opacity-50"></i>
                                </div>
                                <small class="opacity-75">IT equipment registered</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card border-0 custom-shadow h-100 bg-warning text-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-0 opacity-75">Maintenances</h6>
                                        <h2 class="display-4 fw-bold mb-0">
                                            <?php echo number_format($total_maintenances); ?></h2>
                                    </div>
                                    <i class="fas fa-tools fa-3x opacity-50"></i>
                                </div>
                                <small class="opacity-75">Maintenance records</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Stats Row -->
                <div class="row mb-4">
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card border-0 custom-shadow h-100 bg-secondary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-0 opacity-75">Categories</h6>
                                        <h2 class="display-4 fw-bold mb-0">
                                            <?php echo number_format($total_categories); ?></h2>
                                    </div>
                                    <i class="fas fa-tags fa-3x opacity-50"></i>
                                </div>
                                <small class="opacity-75">Training categories used</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card border-0 custom-shadow h-100 bg-dark text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-0 opacity-75">Active Districts</h6>
                                        <h2 class="display-4 fw-bold mb-0">
                                            <?php echo number_format($total_districts); ?></h2>
                                    </div>
                                    <i class="fas fa-map-marked-alt fa-3x opacity-50"></i>
                                </div>
                                <small class="opacity-75">Districts with activities</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card border-0 custom-shadow h-100 bg-danger text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-0 opacity-75">Pending Maintenance</h6>
                                        <h2 class="display-4 fw-bold mb-0">
                                            <?php
                                            $pending_count = 0;
                                            foreach ($maintenances_by_status as $status) {
                                                if ($status['status'] == 'Pending') {
                                                    $pending_count = $status['count'];
                                                    break;
                                                }
                                            }
                                            echo number_format($pending_count);
                                            ?>
                                        </h2>
                                    </div>
                                    <i class="fas fa-exclamation-triangle fa-3x opacity-50"></i>
                                </div>
                                <small class="opacity-75">Requires attention</small>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                <!-- Charts Section -->
                <div class="row mb-5">
                    <div class="col-lg-6 mb-4">
                        <div class="card border-0 custom-shadow h-100">
                            <div class="card-header bg-white fw-bold"><i class="fas fa-chart-bar me-2"></i> Training
                                Sessions by Category</div>
                            <div class="card-body">
                                <canvas id="categoryChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-4">
                        <div class="card border-0 custom-shadow h-100">
                            <div class="card-header bg-white fw-bold"><i class="fas fa-tools me-2"></i> Maintenance
                                Status Distribution</div>
                            <div class="card-body w-50">
                                <canvas id="maintenanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-5">
                    <div class="col-lg-5 mb-4">
                        <div class="card border-0 custom-shadow h-100">
                            <div class="card-header bg-white fw-bold"><i class="fas fa-venus-mars me-2"></i> Trainee
                                Gender Distribution</div>
                            <div class="card-body d-flex justify-content-center align-items-center">
                                <canvas id="genderChart" style="max-height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7 mb-4">
                        <div class="card border-0 custom-shadow h-100">
                            <div class="card-header bg-white fw-bold"><i class="fas fa-laptop me-2"></i> Devices by Type
                            </div>
                            <div class="card-body">
                                <canvas id="deviceTypeChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities Tables -->
                <div class="row mb-4">
                    <div class="col-lg-6 mb-4">
                        <div class="card border-0 custom-shadow">
                            <div class="card-header bg-white fw-bold"><i class="fas fa-table me-2"></i> Recent Training
                                Sessions</div>
                            <div class="card-body">
                                <table id="recentSessionsTable" class="table table-striped table-hover w-100">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>District</th>
                                            <th>Category</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_sessions as $session): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($session['title']); ?></td>
                                                <td><?= htmlspecialchars($session['woreda']); ?></td>
                                                <td><?= htmlspecialchars($session['category']); ?></td>
                                                <td><?= date('M j, Y', strtotime($session['start_date'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="card border-0 custom-shadow">
                            <div class="card-header bg-white fw-bold"><i class="fas fa-tools me-2"></i> Recent
                                Maintenance Activities</div>
                            <div class="card-body">
                                <table id="recentMaintenancesTable" class="table table-striped table-hover w-100">
                                    <thead>
                                        <tr>
                                            <th>Device</th>
                                            <th>Code</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>District</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_maintenances as $maintenance): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($maintenance['device_name']); ?></td>
                                                <td><?= htmlspecialchars($maintenance['device_code']); ?></td>
                                                <td>
                                                    <?php
                                                    $status_badge = [
                                                        'Pending' => 'warning',
                                                        'In Progress' => 'info',
                                                        'Completed' => 'success',
                                                        'Not Fixable' => 'danger'
                                                    ];
                                                    $badge_class = $status_badge[$maintenance['status']] ?? 'secondary';
                                                    ?>
                                                    <span
                                                        class="badge bg-<?= $badge_class; ?>"><?= htmlspecialchars($maintenance['status']); ?></span>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($maintenance['maintenance_date'])); ?></td>
                                                <td><?= htmlspecialchars($maintenance['district_name']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include_once 'inc/footer.php'; ?>

    <script>
        // --- DataTables Initialization ---
        $(document).ready(function () {
            $('#recentSessionsTable').DataTable({
                responsive: true,
                pageLength: 5,
                order: [[3, "desc"]],
                searching: true,
                paging: true,
            });

            $('#recentMaintenancesTable').DataTable({
                responsive: true,
                pageLength: 5,
                order: [[3, "desc"]],
                searching: true,
                paging: true,
            });
        });

        // --- Chart.js Data Parsing ---
        const categoryData = <?php echo $sessions_by_category_json; ?>;
        const genderData = <?php echo $trainees_by_gender_json; ?>;
        const maintenanceStatusData = <?php echo $maintenances_by_status_json; ?>;
        const deviceTypeData = <?php echo $devices_by_type_json; ?>;

        // --- Chart 1: Sessions by Category (Bar Chart) ---
        const categoryLabels = categoryData.map(d => d.category);
        const categoryCounts = categoryData.map(d => d.count);

        new Chart(document.getElementById('categoryChart'), {
            type: 'bar',
            data: {
                labels: categoryLabels,
                datasets: [{
                    label: 'Number of Sessions',
                    data: categoryCounts,
                    backgroundColor: '#004c8c',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { display: false } }
            }
        });

        // --- Chart 2: Maintenance Status (Doughnut Chart) ---
        const maintenanceLabels = maintenanceStatusData.map(d => d.status);
        const maintenanceCounts = maintenanceStatusData.map(d => d.count);

        new Chart(document.getElementById('maintenanceChart'), {
            type: 'doughnut',
            data: {
                labels: maintenanceLabels,
                datasets: [{
                    label: 'Maintenance Count',
                    data: maintenanceCounts,
                    backgroundColor: ['#ffc107', '#17a2b8', '#28a745', '#dc3545'],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // --- Chart 3: Trainees by Gender (Doughnut Chart) ---
        const genderLabels = genderData.map(d => d.gender);
        const genderCounts = genderData.map(d => d.count);

        new Chart(document.getElementById('genderChart'), {
            type: 'doughnut',
            data: {
                labels: genderLabels,
                datasets: [{
                    label: 'Trainee Count',
                    data: genderCounts,
                    backgroundColor: ['#004c8c', '#ff7f00'],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // --- Chart 4: Devices by Type (Bar Chart) ---
        const deviceTypeLabels = deviceTypeData.map(d => d.device_type);
        const deviceTypeCounts = deviceTypeData.map(d => d.count);

        new Chart(document.getElementById('deviceTypeChart'), {
            type: 'bar',
            data: {
                labels: deviceTypeLabels,
                datasets: [{
                    label: 'Number of Devices',
                    data: deviceTypeCounts,
                    backgroundColor: '#17a2b8',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y', // Horizontal bar chart
                scales: { x: { beginAtZero: true } },
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>

</html>