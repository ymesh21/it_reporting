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
if ($role != 'Admin') {
    $stmt = $pdo->prepare("SELECT district_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_district_id = $stmt->fetchColumn();

    // Zone users should see all districts in their zone (requires a 'zones' table, 
    // but assuming for simplicity, Zone user's district_id represents their control area)
    // For now, let's simplify: Zone sees all, Woreda sees only their own.
    if ($role == 'Woreda') {
        $where_clause = "WHERE w.id = :district_id";
        $bind_params['district_id'] = $user_district_id;
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


// --- 3. Fetch Sessions by Category (for Bar Chart) ---
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
// Encode data for JavaScript consumption
$sessions_by_category_json = json_encode($sessions_by_category);


// --- 4. Fetch Trainees by Gender (for Doughnut Chart) ---
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


// --- 5. Fetch Recent Sessions (for DataTables) ---
$sql_recent = "
    SELECT ts.title, w.name AS woreda, tc.name AS category, ts.start_date
    FROM training_sessions ts
    JOIN districts w ON ts.district_id = w.id
    JOIN training_categories tc ON ts.category_id = tc.id
    " . $where_clause . "
    ORDER BY ts.start_date DESC
    LIMIT 10"; // Limit for quick loading

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
// Note: Categories are typically universal, but we count categories used in the viewable sessions.
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
// Counts how many districts have had at least one session in the viewable scope.
$sql_woreda_count = "
    SELECT COUNT(DISTINCT ts.district_id)
    FROM training_sessions ts
    JOIN districts w ON ts.district_id = w.id 
    " . $where_clause;

$stmt_recent = $pdo->prepare($sql_recent);
$stmt_recent->execute($bind_params);
$recent_sessions = $stmt_recent->fetchAll();

$stmt_woreda_count = $pdo->prepare($sql_woreda_count);
$stmt_woreda_count->execute($bind_params);
$total_districts = $stmt_woreda_count->fetchColumn();
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
                <div class="row mb-4">

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
                                <small class="opacity-75">Viewable sessions for your role.</small>
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
                                <small class="opacity-75">Total individuals trained.</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card border-0 custom-shadow h-100 bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-0 opacity-75">Unique Categories</h6>
                                        <h2 class="display-4 fw-bold mb-0">
                                            <?php echo number_format($total_categories); ?>
                                        </h2>
                                    </div>
                                    <i class="fas fa-tags fa-3x opacity-50"></i>
                                </div>
                                <small class="opacity-75">Categories with training activity.</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card border-0 custom-shadow h-100 bg-warning text-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-0 opacity-75">Active districts</h6>
                                        <h2 class="display-4 fw-bold mb-0"><?php echo number_format($total_districts); ?>
                                        </h2>
                                    </div>
                                    <i class="fas fa-map-marked-alt fa-3x opacity-50"></i>
                                </div>
                                <small class="opacity-75">districts with reported sessions.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="row mb-5">
                    <div class="col-lg-7 mb-4">
                        <div class="card border-0 custom-shadow h-100">
                            <div class="card-header bg-white fw-bold"><i class="fas fa-chart-bar me-2"></i> Training
                                Sessions by Category</div>
                            <div class="card-body">
                                <canvas id="categoryChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5 mb-4">
                        <div class="card border-0 custom-shadow h-100">
                            <div class="card-header bg-white fw-bold"><i class="fas fa-venus-mars me-2"></i> Trainee
                                Gender Distribution</div>
                            <div class="card-body d-flex justify-content-center align-items-center">
                                <canvas id="genderChart" style="max-height: 350px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-0 custom-shadow">
                            <div class="card-header bg-white fw-bold"><i class="fas fa-table me-2"></i> Recent Training
                                Sessions (Details)</div>
                            <div class="card-body">
                                <table id="recentSessionsTable" class="table table-striped table-hover w-100">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Woreda</th>
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
                                                <td><?= htmlspecialchars($session['start_date']); ?></td>
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
                // These options control how the table looks and behaves:
                "responsive": true,    // Makes the table adapt to different screen sizes
                "pageLength": 5,       // Sets the default number of rows per page
                "order": [[3, "desc"]], // Sorts by the 4th column (Date) descending
                "searching": true,     // Explicitly ensures the search box is visible (default: true)
                "paging": true,        // Explicitly ensures pagination controls are visible (default: true)
            });
        });

        // --- Chart.js Data Parsing ---
        const categoryData = <?php echo $sessions_by_category_json; ?>;
        const genderData = <?php echo $trainees_by_gender_json; ?>;

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
                    backgroundColor: ['#004c8c', '#ff7f00', '#38a700', '#17a2b8'], // Use your custom primary/secondary colors
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { display: false } }
            }
        });

        // --- Chart 2: Trainees by Gender (Doughnut Chart) ---
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
    </script>
</body>

</html>