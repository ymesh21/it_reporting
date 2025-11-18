<?php
// trainee_management.php
require_once 'auth_check.php';
require_once 'config.php';

// Ensure user is logged in and has appropriate role
if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

// Check if user has permission to manage trainees (Zone or Woreda only)
$allowed_roles = ['Zone', 'Woreda'];
if (!in_array($_SESSION['user_role'], $allowed_roles)) {
    header("Location: unauthorized.php");
    exit;
}

$trainees = [];
$sessions = [];
$error = null;
$where_clause = "";
$bind_params = [];
$role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

try {
    // --- 1. Role-Based Filtering Setup ---
    if ($role == 'Woreda') {
        // Find the user's district_id if they are a Woreda user
        $stmt_woreda = $pdo->prepare("SELECT district_id FROM users WHERE id = ?");
        $stmt_woreda->execute([$user_id]);
        $user_district_id = $stmt_woreda->fetchColumn();

        // Filter trainees based on sessions belonging to the user's woreda
        if ($user_district_id) {
            $where_clause = "WHERE ts.district_id = :district_id";
            $bind_params['district_id'] = $user_district_id;
        }
    }
    // Zone users can see all districts (no filter needed)

    // --- 2. Fetch all Sessions (for Add/Edit Form dropdown) ---
    // Filter sessions dropdown based on role
    $sql_sessions = "SELECT id, title FROM training_sessions ts ";
    if (!empty($where_clause)) {
        $sql_sessions .= $where_clause;
    }
    $sql_sessions .= " ORDER BY title";
    $stmt_sessions = $pdo->prepare($sql_sessions);
    $stmt_sessions->execute($bind_params);
    $sessions = $stmt_sessions->fetchAll();

    // --- 3. Fetch Trainees (for DataTables list) ---
    $sql_trainees = "
        SELECT 
            t.id, t.fullname, t.gender, t.phone, t.organization,
            ts.title AS session_title, ts.start_date, w.name AS woreda_name, t.session_id
        FROM trainees t
        JOIN training_sessions ts ON t.session_id = ts.id
        JOIN districts w ON ts.district_id = w.id
        " . $where_clause . "
        ORDER BY t.id DESC
    ";
    $stmt_trainees = $pdo->prepare($sql_trainees);
    $stmt_trainees->execute($bind_params);
    $trainees = $stmt_trainees->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>
<?php include_once 'inc/header.php' ?>
<?php include_once 'inc/sidebar.php'; ?>
<div id="main">
    <?php include_once 'inc/nav.php'; ?>
    <div class="container-fluid pt-2">
        <h1 class="fw-light mb-4"><i class="fas fa-user-graduate me-2"></i> Trainee Management</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addTraineeModal">
            <i class="fas fa-plus me-2"></i> Add New Trainee
        </button>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <table id="traineeTable" class="table table-striped table-hover w-100">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Training Session</th>
                            <th>Woreda</th>
                            <th>Actions</th>
                            <th class="d-none">Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trainees as $trainee):
                            $trainee_json = json_encode($trainee);
                            ?>
                            <tr data-trainee-id="<?php echo $trainee['id']; ?>">
                                <td><?php echo htmlspecialchars($trainee['id']); ?></td>
                                <td><?php echo htmlspecialchars($trainee['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($trainee['gender']); ?></td>
                                <td><?php echo htmlspecialchars($trainee['session_title']); ?></td>
                                <td><?php echo htmlspecialchars($trainee['woreda_name']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info view-btn" data-id="<?php echo $trainee['id']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#viewTraineeModal" title="View"><i
                                            class="fas fa-eye"></i></button>
                                    <button class="btn btn-sm btn-warning edit-btn" data-id="<?php echo $trainee['id']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#editTraineeModal" title="Edit"><i
                                            class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-danger delete-btn" data-id="<?php echo $trainee['id']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#deleteTraineeModal" title="Delete"><i
                                            class="fas fa-trash-alt"></i></button>
                                </td>
                                <td class="d-none trainee-data-json"><?php echo htmlspecialchars($trainee_json); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Trainee Modal -->
    <div class="modal fade" id="addTraineeModal" tabindex="-1" aria-labelledby="addTraineeModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addTraineeModalLabel">Add New Trainee</h5>
                </div>
                <form id="addTraineeForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="fullname" class="form-control" placeholder="Enter full name" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Training Session <span class="text-danger">*</span></label>
                            <select name="session_id" class="form-select" required>
                                <option value="">Select Session</option>
                                <?php foreach ($sessions as $session): ?>
                                    <option value="<?php echo $session['id']; ?>">
                                        <?php echo htmlspecialchars($session['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gender <span class="text-danger">*</span></label>
                                <select name="gender" class="form-select" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact (Phone/Email)</label>
                                <input type="text" name="phone" class="form-control" placeholder="Phone or email">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Organization</label>
                            <input type="text" name="organization" class="form-control" placeholder="Organization name">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Trainee
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Trainee Modal -->
    <div class="modal fade" id="viewTraineeModal" tabindex="-1" aria-labelledby="viewTraineeModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="viewTraineeModalLabel">Trainee Details</h5>
                </div>
                <div class="modal-body">
                    <dl class="row">
                        <dt class="col-sm-4">ID:</dt>
                        <dd class="col-sm-8" id="view_id"></dd>
                        <dt class="col-sm-4">Name:</dt>
                        <dd class="col-sm-8" id="view_fullname"></dd>
                        <dt class="col-sm-4">Gender:</dt>
                        <dd class="col-sm-8" id="view_gender"></dd>
                        <hr>
                        <dt class="col-sm-4">Training Session:</dt>
                        <dd class="col-sm-8" id="view_session_title"></dd>
                        <dt class="col-sm-4">Woreda:</dt>
                        <dd class="col-sm-8" id="view_woreda_name"></dd>
                        <dt class="col-sm-4">Session Date:</dt>
                        <dd class="col-sm-8" id="view_start_date"></dd>
                        <hr>
                        <dt class="col-sm-4">Contact:</dt>
                        <dd class="col-sm-8" id="view_phone"></dd>
                        <dt class="col-sm-4">Institution:</dt>
                        <dd class="col-sm-8" id="view_organization"></dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Trainee Modal -->
    <div class="modal fade" id="editTraineeModal" tabindex="-1" aria-labelledby="editTraineeModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title" id="editTraineeModalLabel">Edit Trainee</h5>
                </div>
                <form id="editTraineeForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_trainee_id">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="fullname" id="edit_fullname" class="form-control" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Training Session <span class="text-danger">*</span></label>
                            <select name="session_id" id="edit_session_id" class="form-select" required>
                                <option value="">Select Session</option>
                                <?php foreach ($sessions as $session): ?>
                                    <option value="<?php echo $session['id']; ?>">
                                        <?php echo htmlspecialchars($session['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gender <span class="text-danger">*</span></label>
                                <select name="gender" id="edit_gender" class="form-select" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact (Phone/Email)</label>
                                <input type="text" name="phone" id="edit_phone" class="form-control">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Organization</label>
                            <input type="text" name="organization" id="edit_organization" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-1"></i> Update Trainee
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Trainee Modal -->
    <div class="modal fade" id="deleteTraineeModal" tabindex="-1" aria-labelledby="deleteTraineeModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteTraineeModalLabel">Delete Trainee</h5>
                </div>
                <form id="deleteTraineeForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteTraineeId">
                        <p>Are you sure you want to delete the following trainee?</p>
                        <div class="alert alert-warning">
                            <strong>Name:</strong> <span id="deleteTraineeName"></span>
                        </div>
                        <p class="text-danger">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i> Delete Trainee
                        </button>
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
            const traineeTable = $('#traineeTable').DataTable({
                responsive: true,
                "order": [[0, "desc"]],
                "pageLength": 10,
                "columnDefs": [
                    { "visible": false, "targets": 6 } // Hide the JSON data column
                ]
            });

            // Client-side validation for Add Trainee Form
            $('#addTraineeForm').on('submit', function (e) {
                e.preventDefault();
                
                const fullName = $('input[name="fullname"]').val().trim();
                const sessionId = $('select[name="session_id"]').val();
                const gender = $('select[name="gender"]').val();

                console.log('Client-side validation - Name:', fullName, 'Session:', sessionId, 'Gender:', gender);

                // Validate full name
                if (!fullName) {
                    showAlert('Please enter trainee full name.', 'error', 4000);
                    $('input[name="fullname"]').focus();
                    return;
                }

                if (fullName.length < 2) {
                    showAlert('Full name must be at least 2 characters long.', 'error', 4000);
                    $('input[name="fullname"]').focus();
                    return;
                }

                // Validate training session
                if (!sessionId) {
                    showAlert('Please select a training session.', 'error', 4000);
                    $('select[name="session_id"]').focus();
                    return;
                }

                // Validate gender
                if (!gender) {
                    showAlert('Please select gender.', 'error', 4000);
                    $('select[name="gender"]').focus();
                    return;
                }

                // If all validations pass, proceed with AJAX submission
                submitAddForm();
            });

            // Client-side validation for Edit Trainee Form
            $('#editTraineeForm').on('submit', function (e) {
                e.preventDefault();
                
                const fullName = $('#edit_fullname').val().trim();
                const sessionId = $('#edit_session_id').val();
                const gender = $('#edit_gender').val();

                console.log('Client-side validation (Edit) - Name:', fullName, 'Session:', sessionId, 'Gender:', gender);

                // Validate full name
                if (!fullName) {
                    showAlert('Please enter trainee full name.', 'error', 4000);
                    $('#edit_fullname').focus();
                    return;
                }

                if (fullName.length < 2) {
                    showAlert('Full name must be at least 2 characters long.', 'error', 4000);
                    $('#edit_fullname').focus();
                    return;
                }

                // Validate training session
                if (!sessionId) {
                    showAlert('Please select a training session.', 'error', 4000);
                    $('#edit_session_id').focus();
                    return;
                }

                // Validate gender
                if (!gender) {
                    showAlert('Please select gender.', 'error', 4000);
                    $('#edit_gender').focus();
                    return;
                }

                // If all validations pass, proceed with AJAX submission
                submitEditForm();
            });

            // AJAX submission functions
            function submitAddForm() {
                const form = $('#addTraineeForm');
                const formData = form.serialize();

                const submitBtn = form.find('button[type="submit"]');
                const originalHtml = submitBtn.html();
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

                console.log('Submitting Add Trainee Form:', formData);

                $.ajax({
                    url: 'ajax/trainee_crud.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function (response) {
                        console.log('Add Trainee Response:', response);
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
                        console.error("Add Trainee AJAX Error:", status, error);
                        
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
                        submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Trainee');
                    }
                });
            }

            function submitEditForm() {
                const form = $('#editTraineeForm');
                const formData = form.serialize();

                const submitBtn = form.find('button[type="submit"]');
                const originalHtml = submitBtn.html();
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Updating...');

                console.log('Submitting Edit Trainee Form:', formData);

                $.ajax({
                    url: 'ajax/trainee_crud.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function (response) {
                        console.log('Edit Trainee Response:', response);
                        if (response.success) {
                            form.closest('.modal').modal('hide');
                            showAlert(response.message, 'success');
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            showAlert(response.message, 'error', 7000);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("Edit Trainee AJAX Error:", status, error);
                        
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
                        submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Update Trainee');
                    }
                });
            }

            function submitDeleteForm() {
                const form = $('#deleteTraineeForm');
                const formData = form.serialize();

                const submitBtn = form.find('button[type="submit"]');
                const originalHtml = submitBtn.html();
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Deleting...');

                console.log('Submitting Delete Trainee Form:', formData);

                $.ajax({
                    url: 'ajax/trainee_crud.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function (response) {
                        console.log('Delete Trainee Response:', response);
                        if (response.success) {
                            form.closest('.modal').modal('hide');
                            showAlert(response.message, 'success');
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            showAlert(response.message, 'error', 7000);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("Delete Trainee AJAX Error:", status, error);
                        
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
                        submitBtn.prop('disabled', false).html('<i class="fas fa-trash me-1"></i> Delete Trainee');
                    }
                });
            }

            // Delete Form Handler
            $('#deleteTraineeForm').on('submit', function (e) {
                e.preventDefault();
                submitDeleteForm();
            });

            // ----------------------------------------------------
            // VIEW Button Handler (Extracts data from the row)
            // ----------------------------------------------------
            $('#traineeTable tbody').on('click', '.view-btn', function () {
                const row = $(this).closest('tr');
                const rowData = traineeTable.row(row).data();
                const jsonString = rowData[6]; // JSON is in index 6

                try {
                    const trainee = JSON.parse(jsonString);

                    // Populate the modal fields
                    $('#view_id').text(trainee.id);
                    $('#view_fullname').text(trainee.fullname);
                    $('#view_gender').text(trainee.gender);
                    $('#view_session_title').text(trainee.session_title);
                    $('#view_woreda_name').text(trainee.woreda_name);
                    $('#view_start_date').text(trainee.start_date);
                    $('#view_phone').text(trainee.phone || 'N/A');
                    $('#view_organization').text(trainee.organization || 'N/A');

                } catch (e) {
                    console.error("Failed to parse trainee data JSON:", e);
                    showAlert("Error displaying trainee details.", 'error');
                }
            });

            // ----------------------------------------------------
            // EDIT Button Handler (AJAX pre-filling)
            // ----------------------------------------------------
            $('#traineeTable tbody').on('click', '.edit-btn', function () {
                const traineeId = $(this).data('id');

                // Fetch trainee data via AJAX
                $.ajax({
                    url: 'ajax/trainee_crud.php',
                    method: 'POST',
                    data: {
                        action: 'get',
                        id: traineeId
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            const trainee = response.data;

                            // Populate the edit form
                            $('#edit_trainee_id').val(trainee.id);
                            $('#edit_fullname').val(trainee.fullname);
                            $('#edit_gender').val(trainee.gender);
                            $('#edit_phone').val(trainee.phone || '');
                            $('#edit_organization').val(trainee.organization || '');
                            $('#edit_session_id').val(trainee.session_id);

                            // Show the modal
                            $('#editTraineeModal').modal('show');
                        } else {
                            showAlert('Error loading trainee data: ' + response.message, 'error');
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("AJAX Error:", status, error);
                        showAlert('An unexpected network error occurred.', 'error');
                    }
                });
            });

            // ----------------------------------------------------
            // DELETE Button Handler
            // ----------------------------------------------------
            $('#traineeTable tbody').on('click', '.delete-btn', function () {
                const row = $(this).closest('tr');
                const rowData = traineeTable.row(row).data();
                const jsonString = rowData[6];

                try {
                    const trainee = JSON.parse(jsonString);
                    $('#deleteTraineeId').val(trainee.id);
                    $('#deleteTraineeName').text(trainee.fullname);
                    $('#deleteTraineeModal').modal('show');
                } catch (e) {
                    console.error("Failed to parse trainee data JSON:", e);
                    showAlert("Error loading trainee data for deletion.", 'error');
                }
            });

            // Clear validation when modals are closed
            $('#addTraineeModal').on('hidden.bs.modal', function() {
                $('#addTraineeForm')[0].reset();
                $('.is-invalid').removeClass('is-invalid');
                $('.is-valid').removeClass('is-valid');
                $('.invalid-feedback').remove();
            });

            $('#editTraineeModal').on('hidden.bs.modal', function() {
                $('.is-invalid').removeClass('is-invalid');
                $('.is-valid').removeClass('is-valid');
                $('.invalid-feedback').remove();
            });
        });
    </script>

    </body>

    </html>