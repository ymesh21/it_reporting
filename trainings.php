<?php
// session_management.php
require_once 'auth_check.php';
require_once 'config.php';

// Authorization Guard: Only Admin, Zone, and district users can access this page
if (!has_role('Admin') && !has_role('Zone') && !has_role('district')) {
    header("Location: dashboard.php");
    exit;
}

$sessions = [];
$districts = [];
$categories = [];
$error = null;
$where_clause = "";
$bind_params = [];
$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

$user_district_id = null;
if (isset($user_id)) {
    $stmt_user_data = $pdo->prepare("SELECT district_id FROM users WHERE id = ?");
    $stmt_user_data->execute([$user_id]);
    $user_district_id = $stmt_user_data->fetchColumn();
}

try {
    // --- 1. Role-Based Filtering Setup ---
    if ($user_role == 'district') {
        // district users only see sessions in their district
        $stmt_district = $pdo->prepare("SELECT district_id FROM users WHERE id = ?");
        $stmt_district->execute([$user_id]);
        $user_district_id = $stmt_district->fetchColumn();

        if ($user_district_id) {
            $where_clause = "WHERE ts.district_id = :district_id";
            $bind_params['district_id'] = $user_district_id;
        } else {
            // User exists but has no district assigned; show no sessions.
            $where_clause = "WHERE 1 = 0";
        }
    }
    // Zone and Admin users see all sessions (no filter needed)

    // --- 2. Fetch districts (for form dropdown) ---
    $districts = $pdo->query("SELECT id, name FROM districts ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. Fetch Categories (for form dropdown) ---
    $categories = $pdo->query("SELECT id, name FROM training_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

    // --- 4. Fetch Sessions (for DataTables list) ---
    $sql_sessions = "
        SELECT 
            ts.id, ts.title, ts.start_date, ts.end_date, ts.budget, u.firstname, u.lastname, ts.created_by as user_id,
            w.name AS district_name, c.name AS category_name, 
            ts.district_id, ts.category_id 
        FROM training_sessions ts
        JOIN users u ON ts.created_by = u.id
        JOIN districts w ON ts.district_id = w.id
        JOIN training_categories c ON ts.category_id = c.id
        " . $where_clause . "
        ORDER BY ts.start_date DESC
    ";
    $stmt_sessions = $pdo->prepare($sql_sessions);
    $stmt_sessions->execute($bind_params);
    $sessions = $stmt_sessions->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>
<?php include_once 'inc/header.php' ?>
<?php include_once 'inc/sidebar.php'; ?>
<div id="main">
    <?php include_once 'inc/nav.php'; ?>
    <div class="container-fluid content pt-2">
        <h1 class="fw-light mb-4"><i class="fas fa-chalkboard-teacher me-2"></i> Training Sessions Management</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addSessionModal">
            <i class="fas fa-plus me-2"></i> Create New Session
        </button>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <table id="sessionTable" class="table table-striped table-hover w-100">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>district</th>
                            <th>Start Date</th>
                            <th>Actions</th>
                            <th class="d-none">Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $session):
                            $session_json = json_encode($session);
                            ?>
                            <tr data-session-id="<?php echo $session['id']; ?>">
                                <td><?php echo htmlspecialchars($session['id']); ?></td>
                                <td><?php echo htmlspecialchars($session['title']); ?></td>
                                <td><?php echo htmlspecialchars($session['category_name']); ?></td>
                                <td><?php echo htmlspecialchars($session['district_name']); ?></td>
                                <td><?php echo htmlspecialchars(date('M j, Y', strtotime($session['start_date']))); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-info view-btn" data-bs-toggle="modal"
                                        data-bs-target="#viewSessionModal" title="View"><i class="fas fa-eye"></i></button>
                                        <button class="btn btn-sm btn-warning edit-btn" data-bs-toggle="modal"
                                        data-bs-target="#editSessionModal" title="Edit"><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-sm btn-danger delete-btn" data-bs-toggle="modal"
                                        data-bs-target="#deleteSessionModal" title="Delete"><i
                                            class="fas fa-trash-alt"></i></button>
                                    </div>
                                </td>
                                <td class="d-none session-data-json"><?php echo htmlspecialchars($session_json); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ADD Training Modal -->
<div class="modal fade" id="addSessionModal" tabindex="-1" aria-labelledby="addSessionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addSessionModalLabel"><i class="fas fa-plus me-2"></i> Create New Session</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addSessionForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="created_by" value="<?php echo htmlspecialchars($user_id); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="Enter session title" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">District <span class="text-danger">*</span></label>
                            <?php if ($user_role == 'district' && $user_district_id): ?>
                                <input type="hidden" name="district_id" value="<?php echo htmlspecialchars($user_district_id); ?>">
                                <?php 
                                    // Display the district name as read-only text for user confirmation
                                    $district_name = array_column($districts, 'name', 'id')[$user_district_id] ?? 'Unknown district';
                                ?>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($district_name); ?>" readonly>
                                <small class="form-text text-muted">Sessions must be created in your assigned district.</small>
                            <?php else: ?>
                                <select name="district_id" class="form-select" required>
                                    <option value="">Select district</option>
                                    <?php foreach ($districts as $district): ?>
                                        <option value="<?php echo $district['id']; ?>"><?php echo htmlspecialchars($district['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Budget (ETB)</label>
                            <input type="number" name="budget" class="form-control" placeholder="0.00" step="0.01" min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Session</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT Training Modal -->
<div class="modal fade" id="editSessionModal" tabindex="-1" aria-labelledby="editSessionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="editSessionModalLabel"><i class="fas fa-edit me-2"></i> Edit Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editSessionForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_session_id">

                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="edit_title" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3" placeholder="Optional session description"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category_id" id="edit_category_id" class="form-select" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">District <span class="text-danger">*</span></label>
                            <select name="district_id" id="edit_district_id" class="form-select" required>
                                <?php foreach ($districts as $district): ?>
                                    <option value="<?php echo $district['id']; ?>">
                                        <?php echo htmlspecialchars($district['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="edit_start_date" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" id="edit_end_date" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Budget (ETB)</label>
                            <input type="number" name="budget" id="edit_budget" class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i> Update Session</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- VIEW Session Modal -->
<div class="modal fade" id="viewSessionModal" tabindex="-1" aria-labelledby="viewSessionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="viewSessionModalLabel">Session Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row">
                    <dt class="col-sm-3">Title:</dt>
                    <dd class="col-sm-9" id="view_title"></dd>
                    
                    <dt class="col-sm-3">Category:</dt>
                    <dd class="col-sm-9" id="view_category"></dd>
                    
                    <dt class="col-sm-3">District:</dt>
                    <dd class="col-sm-9" id="view_district"></dd>
                    
                    <dt class="col-sm-3">Start Date:</dt>
                    <dd class="col-sm-9" id="view_start_date"></dd>
                    
                    <dt class="col-sm-3">End Date:</dt>
                    <dd class="col-sm-9" id="view_end_date"></dd>
                    
                    <dt class="col-sm-3">Budget:</dt>
                    <dd class="col-sm-9" id="view_budget"></dd>
                    
                    <dt class="col-sm-3">Description:</dt>
                    <dd class="col-sm-9" id="view_description"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- DELETE Session Modal -->
<div class="modal fade" id="deleteSessionModal" tabindex="-1" aria-labelledby="deleteSessionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteSessionModalLabel"><i class="fas fa-trash-alt me-2"></i> Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="deleteSessionForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_session_id">

                    <p class="mb-3">Are you sure you want to delete the session: **<span id="delete_session_title" class="fw-bold text-danger"></span>**?</p>
                    <div class="alert alert-warning" role="alert">
                        This will also delete all <strong>trainees</strong> linked to this session. This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="confirmDeleteBtn">Delete Session</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once 'inc/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Enhanced Alert Function with Beautiful Styling
        function showAlert(message, type, duration = 5000) {
            const alertConfig = {
                success: {
                    icon: 'fas fa-check-circle',
                    title: 'Success!',
                    bgClass: 'alert-success',
                    iconColor: 'text-success'
                },
                error: {
                    icon: 'fas fa-exclamation-triangle',
                    title: 'Error!',
                    bgClass: 'alert-danger',
                    iconColor: 'text-danger'
                },
                warning: {
                    icon: 'fas fa-exclamation-circle',
                    title: 'Warning!',
                    bgClass: 'alert-warning',
                    iconColor: 'text-warning'
                },
                info: {
                    icon: 'fas fa-info-circle',
                    title: 'Information',
                    bgClass: 'alert-info',
                    iconColor: 'text-info'
                }
            };

            const config = alertConfig[type] || alertConfig.info;
            
            const alertHtml = `
                <div class="alert ${config.bgClass} alert-dismissible fade show custom-alert" role="alert">
                    <div class="alert-icon me-3">
                        <i class="${config.icon} ${config.iconColor}"></i>
                    </div>
                    <div class="alert-content flex-grow-1">
                        <h6 class="alert-title mb-1">${config.title}</h6>
                        <p class="alert-message mb-0">${message}</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="
                        background: none;
                        opacity: 0.7;
                        font-size: 0.8rem;
                    "></button>
                </div>
            `;

            // Create alert container if it doesn't exist
            let alertContainer = $('#alert-container');
            if (alertContainer.length === 0) {
                $('body').prepend('<div id="alert-container" style="position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 350px; max-width: 500px;"></div>');
                alertContainer = $('#alert-container');
            }

            // Add the alert
            alertContainer.append(alertHtml);

            // Auto remove after duration
            setTimeout(() => {
                $(`.alert`).alert('close');
            }, duration);
        }

        // Initialize DataTables
        const sessionTable = $('#sessionTable').DataTable({
            responsive: true,
            "order": [[4, "desc"]],
            "pageLength": 10,
            dom:
                "<'row'<'col-md-6'l><'col-md-6 text-right'f>>" +
                "<'row'<'col-sm-12'tr>>" +
                "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            buttons: [
                {
                    extend: 'copyHtml5',
                    exportOptions: { columns: ':visible:not(:last-child)' }
                },
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Excel',
                    className: 'btn-success btn-sm',
                    exportOptions: { columns: [0, 1, 2, 3, 4] }
                },
                {
                    extend: 'csvHtml5',
                    text: '<i class="fas fa-file-csv"></i> CSV',
                    className: 'btn-info btn-sm',
                    exportOptions: { columns: [0, 1, 2, 3, 4] }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> PDF',
                    className: 'btn-danger btn-sm',
                    exportOptions: { columns: [0, 1, 2, 3, 4] }
                }
            ],
            "columnDefs": [{
                "visible": false,
                "targets": 6
            }]
        });

        // Client-side validation for Add Session Form
        $('#addSessionForm').on('submit', function (e) {
            e.preventDefault();
            
            const title = $('input[name="title"]').val().trim();
            const categoryId = $('select[name="category_id"]').val();
            const districtId = $('select[name="district_id"]').val() || $('input[name="district_id"]').val();
            const startDate = $('input[name="start_date"]').val();
            const endDate = $('input[name="end_date"]').val();
            const budget = $('input[name="budget"]').val();

            // Validate title
            if (!title) {
                showAlert('Please enter session title.', 'error', 4000);
                $('input[name="title"]').focus();
                return;
            }

            if (title.length < 3) {
                showAlert('Session title must be at least 3 characters long.', 'error', 4000);
                $('input[name="title"]').focus();
                return;
            }

            // Validate category
            if (!categoryId) {
                showAlert('Please select a category.', 'error', 4000);
                $('select[name="category_id"]').focus();
                return;
            }

            // Validate district
            if (!districtId) {
                showAlert('Please select a district.', 'error', 4000);
                $('select[name="district_id"]').focus();
                return;
            }

            // Validate dates
            if (!startDate) {
                showAlert('Please select start date.', 'error', 4000);
                $('input[name="start_date"]').focus();
                return;
            }

            if (!endDate) {
                showAlert('Please select end date.', 'error', 4000);
                $('input[name="end_date"]').focus();
                return;
            }

            const start = new Date(startDate);
            const end = new Date(endDate);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (start < today) {
                showAlert('Start date cannot be in the past.', 'error', 4000);
                $('input[name="start_date"]').focus();
                return;
            }

            if (end < start) {
                showAlert('End date cannot be before start date.', 'error', 4000);
                $('input[name="end_date"]').focus();
                return;
            }

            // Validate budget if provided
            if (budget && parseFloat(budget) < 0) {
                showAlert('Budget cannot be negative.', 'error', 4000);
                $('input[name="budget"]').focus();
                return;
            }

            // If all validations pass, proceed with AJAX submission
            submitAddForm();
        });

        // Client-side validation for Edit Session Form
        $('#editSessionForm').on('submit', function (e) {
            e.preventDefault();
            
            const title = $('#edit_title').val().trim();
            const categoryId = $('#edit_category_id').val();
            const districtId = $('#edit_district_id').val();
            const startDate = $('#edit_start_date').val();
            const endDate = $('#edit_end_date').val();
            const budget = $('#edit_budget').val();

            // Validate title
            if (!title) {
                showAlert('Please enter session title.', 'error', 4000);
                $('#edit_title').focus();
                return;
            }

            if (title.length < 3) {
                showAlert('Session title must be at least 3 characters long.', 'error', 4000);
                $('#edit_title').focus();
                return;
            }

            // Validate category
            if (!categoryId) {
                showAlert('Please select a category.', 'error', 4000);
                $('#edit_category_id').focus();
                return;
            }

            // Validate district
            if (!districtId) {
                showAlert('Please select a district.', 'error', 4000);
                $('#edit_district_id').focus();
                return;
            }

            // Validate dates
            if (!startDate) {
                showAlert('Please select start date.', 'error', 4000);
                $('#edit_start_date').focus();
                return;
            }

            if (!endDate) {
                showAlert('Please select end date.', 'error', 4000);
                $('#edit_end_date').focus();
                return;
            }

            const start = new Date(startDate);
            const end = new Date(endDate);

            if (end < start) {
                showAlert('End date cannot be before start date.', 'error', 4000);
                $('#edit_end_date').focus();
                return;
            }

            // Validate budget if provided
            if (budget && parseFloat(budget) < 0) {
                showAlert('Budget cannot be negative.', 'error', 4000);
                $('#edit_budget').focus();
                return;
            }

            // If all validations pass, proceed with AJAX submission
            submitEditForm();
        });

        // AJAX submission functions
        function submitAddForm() {
            const form = $('#addSessionForm');
            const formData = form.serialize();

            const submitBtn = form.find('button[type="submit"]');
            const originalHtml = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

            console.log('Submitting Add Session Form:', formData);

            $.ajax({
                url: 'ajax/session_crud.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    console.log('Add Session Response:', response);
                    if (response.success) {
                        form.closest('.modal').modal('hide');
                        form[0].reset();
                        showAlert(response.message, 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showAlert(response.message, 'error', 7000);
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Add Session AJAX Error:", status, error);
                    
                    let errorMessage = 'An unexpected network error occurred.';
                    
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response && response.message) {
                            errorMessage = response.message;
                        }
                    } catch (e) {
                        if (xhr.responseText) {
                            errorMessage = 'Server Error: ' + xhr.responseText.substring(0, 100);
                        }
                    }
                    
                    showAlert(errorMessage, 'error', 7000);
                },
                complete: function () {
                    submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Session');
                }
            });
        }

        function submitEditForm() {
            const form = $('#editSessionForm');
            const formData = form.serialize();

            const submitBtn = form.find('button[type="submit"]');
            const originalHtml = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Updating...');

            console.log('Submitting Edit Session Form:', formData);

            $.ajax({
                url: 'ajax/session_crud.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    console.log('Edit Session Response:', response);
                    if (response.success) {
                        form.closest('.modal').modal('hide');
                        showAlert(response.message, 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showAlert(response.message, 'error', 7000);
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Edit Session AJAX Error:", status, error);
                    
                    let errorMessage = 'An unexpected network error occurred.';
                    
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response && response.message) {
                            errorMessage = response.message;
                        }
                    } catch (e) {
                        if (xhr.responseText) {
                            errorMessage = 'Server Error: ' + xhr.responseText.substring(0, 100);
                        }
                    }
                    
                    showAlert(errorMessage, 'error', 7000);
                },
                complete: function () {
                    submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Update Session');
                }
            });
        }

        function submitDeleteForm() {
            const form = $('#deleteSessionForm');
            const formData = form.serialize();

            const submitBtn = form.find('#confirmDeleteBtn');
            const originalHtml = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Deleting...');

            console.log('Submitting Delete Session Form:', formData);

            $.ajax({
                url: 'ajax/session_crud.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    console.log('Delete Session Response:', response);
                    if (response.success) {
                        form.closest('.modal').modal('hide');
                        showAlert(response.message, 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showAlert(response.message, 'error', 7000);
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Delete Session AJAX Error:", status, error);
                    
                    let errorMessage = 'An unexpected network error occurred.';
                    
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response && response.message) {
                            errorMessage = response.message;
                        }
                    } catch (e) {
                        if (xhr.responseText) {
                            errorMessage = 'Server Error: ' + xhr.responseText.substring(0, 100);
                        }
                    }
                    
                    showAlert(errorMessage, 'error', 7000);
                },
                complete: function () {
                    submitBtn.prop('disabled', false).html('Delete Session');
                }
            });
        }

        // Delete Form Handler
        $('#deleteSessionForm').on('submit', function (e) {
            e.preventDefault();
            submitDeleteForm();
        });

        // ----------------------------------------------------
        // VIEW Button Handler
        // ----------------------------------------------------
        $('#sessionTable tbody').on('click', '.view-btn', function () {
            const row = $(this).closest('tr');
            const rowData = sessionTable.row(row).data();
            const jsonString = rowData[6];

            try {
                const session = JSON.parse(jsonString);

                // Populate the modal fields
                $('#view_title').text(session.title);
                $('#view_category').text(session.category_name);
                $('#view_district').text(session.district_name);
                $('#view_start_date').text(new Date(session.start_date).toLocaleDateString());
                $('#view_end_date').text(new Date(session.end_date).toLocaleDateString());
                $('#view_budget').text(session.budget ? 'ETB ' + parseFloat(session.budget).toFixed(2) : 'N/A');
                $('#view_description').text(session.description || 'No description provided.');

            } catch (e) {
                console.error("Failed to parse session data JSON:", e);
                showAlert("Error displaying session details.", 'error');
            }
        });

        // ----------------------------------------------------
        // EDIT Button Handler
        // ----------------------------------------------------
        $('#sessionTable tbody').on('click', '.edit-btn', function () {
            const row = $(this).closest('tr');
            const rowData = sessionTable.row(row).data();
            const jsonString = rowData[6];

            try {
                const session = JSON.parse(jsonString);

                // Populate the edit form
                $('#edit_session_id').val(session.id);
                $('#edit_title').val(session.title);
                $('#edit_description').val(session.description || '');

                // Set the correct option in dropdowns
                $('#edit_category_id').val(session.category_id);
                $('#edit_district_id').val(session.district_id);

                // Set date inputs (must be YYYY-MM-DD format)
                $('#edit_start_date').val(session.start_date);
                $('#edit_end_date').val(session.end_date);
                $('#edit_budget').val(session.budget);

            } catch (e) {
                console.error("Failed to parse session data JSON:", e);
                showAlert("Error loading session data for editing.", 'error');
            }
        });

        // ----------------------------------------------------
        // DELETE Button Handler
        // ----------------------------------------------------
        $('#sessionTable tbody').on('click', '.delete-btn', function () {
            const row = $(this).closest('tr');
            const rowData = sessionTable.row(row).data();
            const jsonString = rowData[6];

            try {
                const session = JSON.parse(jsonString);

                // Set the ID in the hidden input field of the delete form
                $('#delete_session_id').val(session.id);

                // Display the session title for confirmation
                $('#delete_session_title').text(session.title);

            } catch (e) {
                console.error("Failed to parse session data for deletion:", e);
                showAlert("Error preparing deletion details.", 'error');
            }
        });

        // Clear validation when modals are closed
        $('#addSessionModal').on('hidden.bs.modal', function() {
            $('#addSessionForm')[0].reset();
            $('.is-invalid').removeClass('is-invalid');
            $('.is-valid').removeClass('is-valid');
            $('.invalid-feedback').remove();
        });

        $('#editSessionModal').on('hidden.bs.modal', function() {
            $('.is-invalid').removeClass('is-invalid');
            $('.is-valid').removeClass('is-valid');
            $('.invalid-feedback').remove();
        });

        // Make Bootstrap modals draggable
        $('.modal').on('shown.bs.modal', function() {
            $(this).find('.modal-dialog').draggable({
                handle: '.modal-header'
            });
        });
    });
</script>
</body>
</html>