<?php
// user_management.php
require_once 'auth_check.php';
require_once 'config.php';

// Authorization Guard: Only Admins can access this page
if (!has_role('Admin')) {
    header("Location: dashboard.php");
    exit;
}

// Helper function to get badge class
function get_role_badge_class($role)
{
    $role = strtolower($role);
    if ($role == 'admin')
        return 'danger';
    if ($role == 'zone')
        return 'warning';
    return 'success';
}

$users = [];
$districts = [];
$error = null;

try {
    // 1. Fetch districts for the Add/Edit forms
    $districts = $pdo->query("SELECT id, name FROM districts ORDER BY name")->fetchAll();

    // 2. Fetch Users for the DataTables list (including Woreda name)
    $stmt_users = $pdo->query("
        SELECT 
            u.id, u.firstname, u.lastname, u.email, u.phone, 
            u.role, u.sex, u.position, w.name AS woreda_name, u.district_id
        FROM users u
        JOIN districts w ON u.district_id = w.id
        ORDER BY u.id DESC
    ");
    $users = $stmt_users->fetchAll();

} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>
<?php include_once 'inc/header.php' ?>
<?php include_once 'inc/sidebar.php'; ?>
<div id="main">
    <?php include_once 'inc/nav.php'; ?>
    <div class="container-fluid content pt-2">
        <h1 class="fw-light mb-4"><i class="fas fa-users-cog me-2"></i> User Management</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-plus me-2"></i> Add New User
        </button>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <table id="userTable" class="table table-striped table-hover w-100">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Woreda</th>
                            <th>Actions</th>
                            <th class="d-none">Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user):
                            $user_json = json_encode($user);
                            ?>
                            <tr data-user-id="<?php echo $user['id']; ?>">
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>
                                </td>
                                <td><a
                                        href="mailto:<?php echo htmlspecialchars($user['email']); ?>"><?php echo htmlspecialchars($user['email']); ?></a>
                                </td>
                                <td><span
                                        class="badge bg-<?php echo get_role_badge_class($user['role']); ?>"><?php echo htmlspecialchars($user['role']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($user['woreda_name']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info view-btn" data-id="<?php echo $user['id']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#viewUserModal" title="View"><i
                                            class="fas fa-eye"></i></button>
                                    <button class="btn btn-sm btn-warning edit-btn" data-id="<?php echo $user['id']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#editUserModal" title="Edit"><i
                                            class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-danger delete-btn" data-id="<?php echo $user['id']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#deleteUserModal" title="Delete"><i
                                            class="fas fa-trash-alt"></i></button>
                                </td>
                                <td class="d-none user-data-json"><?php echo htmlspecialchars($user_json); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- User ADD modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addUserModalLabel"><i class="fas fa-user-plus me-2"></i> Add New User
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form id="addUserForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="firstname" class="form-control" placeholder="First Name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="lastname" class="form-control" placeholder="Last Name" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="Email" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Password" required>
                        <small class="form-text text-muted">Minimum 6 characters recommended.</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="">Select Role</option>
                                <option value="Admin">Admin</option>
                                <option value="Zone">Zone</option>
                                <option value="Woreda">Woreda</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gender <span class="text-danger">*</span></label>
                            <select name="sex" class="form-select" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Woreda <span class="text-danger">*</span></label>
                        <select name="district_id" class="form-select" required>
                            <option value="">Select Woreda</option>
                            <?php foreach ($districts as $woreda): ?>
                                <option value="<?php echo $woreda['id']; ?>">
                                    <?php echo htmlspecialchars($woreda['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="Phone Number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Position</label>
                        <input type="text" name="position" class="form-control" placeholder="Position">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="editUserModalLabel"><i class="fas fa-edit me-2"></i> Edit User Information
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editUserForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_user_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="firstname" id="edit_firstname" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="lastname" id="edit_lastname" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-control">
                        <small class="form-text text-muted">Leave blank to keep current password.</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="role" id="edit_role" class="form-select" required>
                                <option value="Admin">Admin</option>
                                <option value="Zone">Zone</option>
                                <option value="Woreda">Woreda</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Gender <span class="text-danger">*</span></label>
                            <select name="sex" id="edit_sex" class="form-select" required>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Woreda <span class="text-danger">*</span></label>
                        <select name="district_id" id="edit_district_id" class="form-select" required>
                            <?php foreach ($districts as $woreda): ?>
                                <option value="<?php echo $woreda['id']; ?>">
                                    <?php echo htmlspecialchars($woreda['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="edit_phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Position</label>
                        <input type="text" name="position" id="edit_position" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i> Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- User VIEW modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="viewUserModalLabel">User Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row">
                    <dt class="col-sm-3">ID:</dt>
                    <dd class="col-sm-9" id="view_id"></dd>
                    <dt class="col-sm-3">Name:</dt>
                    <dd class="col-sm-9" id="view_name"></dd>
                    <dt class="col-sm-3">Email:</dt>
                    <dd class="col-sm-9" id="view_email"></dd>
                    <dt class="col-sm-3">Role:</dt>
                    <dd class="col-sm-9" id="view_role"></dd>
                    <dt class="col-sm-3">Woreda:</dt>
                    <dd class="col-sm-9" id="view_woreda"></dd>
                    <dt class="col-sm-3">Gender:</dt>
                    <dd class="col-sm-9" id="view_sex"></dd>
                    <dt class="col-sm-3">Phone:</dt>
                    <dd class="col-sm-9" id="view_phone"></dd>
                    <dt class="col-sm-3">Position:</dt>
                    <dd class="col-sm-9" id="view_position"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- User DELETE Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteUserModalLabel"><i class="fas fa-trash-alt me-2"></i> Confirm
                    Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <form id="deleteUserForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_user_id">

                    <p class="mb-3">Are you sure you want to delete the user: **<span id="delete_username"
                            class="fw-bold text-danger"></span>**?</p>
                    <div class="alert alert-warning" role="alert">
                        This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="confirmDeleteBtn">Delete User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once 'inc/footer.php'; ?>

<style>
/* Custom Alert Styles */
.custom-alert {
    backdrop-filter: blur(10px);
    background-color: rgba(255, 255, 255, 0.95) !important;
    border: none;
    border-left: 4px solid;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    padding: 1rem 1.5rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    animation: slideInDown 0.3s ease-out;
}

.alert-success {
    border-left-color: #198754 !important;
    background: linear-gradient(135deg, #d1f2eb, #e8f6f3) !important;
}

.alert-danger {
    border-left-color: #dc3545 !important;
    background: linear-gradient(135deg, #fadbd8, #fdebd0) !important;
}

.alert-warning {
    border-left-color: #ffc107 !important;
    background: linear-gradient(135deg, #fdebd0, #fcf3cf) !important;
}

.alert-info {
    border-left-color: #0dcaf0 !important;
    background: linear-gradient(135deg, #d1f2eb, #d6eaf8) !important;
}

.alert-icon {
    font-size: 1.5rem;
}

.alert-title {
    font-size: 0.9rem;
    font-weight: bold;
}

.alert-message {
    font-size: 0.85rem;
    margin-bottom: 0;
}

/* Animation */
@keyframes slideInDown {
    from {
        transform: translateY(-100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}
</style>

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
        const userTable = $('#userTable').DataTable({
            responsive: true,
            "order": [[0, "desc"]],
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
                    exportOptions: { columns: [0, 1, 2, 3, 4, 5] }
                },
                {
                    extend: 'csvHtml5',
                    text: '<i class="fas fa-file-csv"></i> CSV',
                    className: 'btn-info btn-sm',
                    exportOptions: { columns: [0, 1, 2, 3, 4, 5] }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> PDF',
                    className: 'btn-danger btn-sm',
                    exportOptions: { columns: [0, 1, 2, 3, 4, 5] }
                }
            ],
            "columnDefs": [{
                "visible": false,
                "targets": 6
            }]
        });

        // Client-side validation for Add User Form
        $('#addUserForm').on('submit', function (e) {
            e.preventDefault();
            
            const firstName = $('input[name="firstname"]').val().trim();
            const lastName = $('input[name="lastname"]').val().trim();
            const email = $('input[name="email"]').val().trim();
            const password = $('input[name="password"]').val();
            const role = $('select[name="role"]').val();
            const sex = $('select[name="sex"]').val();
            const districtId = $('select[name="district_id"]').val();

            console.log('Client-side validation - First:', firstName, 'Last:', lastName, 'Email:', email);

            // Validate first name
            if (!firstName) {
                showAlert('Please enter first name.', 'error', 4000);
                $('input[name="firstname"]').focus();
                return;
            }

            if (firstName.length < 2) {
                showAlert('First name must be at least 2 characters long.', 'error', 4000);
                $('input[name="firstname"]').focus();
                return;
            }

            // Validate last name
            if (!lastName) {
                showAlert('Please enter last name.', 'error', 4000);
                $('input[name="lastname"]').focus();
                return;
            }

            if (lastName.length < 2) {
                showAlert('Last name must be at least 2 characters long.', 'error', 4000);
                $('input[name="lastname"]').focus();
                return;
            }

            // Validate email
            if (!email) {
                showAlert('Please enter email address.', 'error', 4000);
                $('input[name="email"]').focus();
                return;
            }

            if (!isValidEmail(email)) {
                showAlert('Please enter a valid email address.', 'error', 4000);
                $('input[name="email"]').focus();
                return;
            }

            // Validate password
            if (!password) {
                showAlert('Please enter a password.', 'error', 4000);
                $('input[name="password"]').focus();
                return;
            }

            if (password.length < 6) {
                showAlert('Password must be at least 6 characters long.', 'error', 4000);
                $('input[name="password"]').focus();
                return;
            }

            // Validate role
            if (!role) {
                showAlert('Please select a user role.', 'error', 4000);
                $('select[name="role"]').focus();
                return;
            }

            // Validate gender
            if (!sex) {
                showAlert('Please select gender.', 'error', 4000);
                $('select[name="sex"]').focus();
                return;
            }

            // Validate woreda
            if (!districtId) {
                showAlert('Please select a woreda.', 'error', 4000);
                $('select[name="district_id"]').focus();
                return;
            }

            // If all validations pass, proceed with AJAX submission
            submitAddForm();
        });

        // Client-side validation for Edit User Form
        $('#editUserForm').on('submit', function (e) {
            e.preventDefault();
            
            const firstName = $('#edit_firstname').val().trim();
            const lastName = $('#edit_lastname').val().trim();
            const email = $('#edit_email').val().trim();
            const password = $('input[name="password"]').val();
            const role = $('#edit_role').val();
            const sex = $('#edit_sex').val();
            const districtId = $('#edit_district_id').val();

            console.log('Client-side validation (Edit) - First:', firstName, 'Last:', lastName, 'Email:', email);

            // Validate first name
            if (!firstName) {
                showAlert('Please enter first name.', 'error', 4000);
                $('#edit_firstname').focus();
                return;
            }

            if (firstName.length < 2) {
                showAlert('First name must be at least 2 characters long.', 'error', 4000);
                $('#edit_firstname').focus();
                return;
            }

            // Validate last name
            if (!lastName) {
                showAlert('Please enter last name.', 'error', 4000);
                $('#edit_lastname').focus();
                return;
            }

            if (lastName.length < 2) {
                showAlert('Last name must be at least 2 characters long.', 'error', 4000);
                $('#edit_lastname').focus();
                return;
            }

            // Validate email
            if (!email) {
                showAlert('Please enter email address.', 'error', 4000);
                $('#edit_email').focus();
                return;
            }

            if (!isValidEmail(email)) {
                showAlert('Please enter a valid email address.', 'error', 4000);
                $('#edit_email').focus();
                return;
            }

            // Validate password (if provided)
            if (password && password.length < 6) {
                showAlert('Password must be at least 6 characters long.', 'error', 4000);
                $('input[name="password"]').focus();
                return;
            }

            // Validate role
            if (!role) {
                showAlert('Please select a user role.', 'error', 4000);
                $('#edit_role').focus();
                return;
            }

            // Validate gender
            if (!sex) {
                showAlert('Please select gender.', 'error', 4000);
                $('#edit_sex').focus();
                return;
            }

            // Validate woreda
            if (!districtId) {
                showAlert('Please select a woreda.', 'error', 4000);
                $('#edit_district_id').focus();
                return;
            }

            // If all validations pass, proceed with AJAX submission
            submitEditForm();
        });

        // Email validation function
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // AJAX submission functions
        function submitAddForm() {
            const form = $('#addUserForm');
            const formData = form.serialize();

            const submitBtn = form.find('button[type="submit"]');
            const originalHtml = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

            console.log('Submitting Add User Form:', formData);

            $.ajax({
                url: 'ajax/user_crud.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    console.log('Add User Response:', response);
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
                    console.error("Add User AJAX Error:", status, error);
                    
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
                    submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save User');
                }
            });
        }

        function submitEditForm() {
            const form = $('#editUserForm');
            const formData = form.serialize();

            const submitBtn = form.find('button[type="submit"]');
            const originalHtml = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Updating...');

            console.log('Submitting Edit User Form:', formData);

            $.ajax({
                url: 'ajax/user_crud.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    console.log('Edit User Response:', response);
                    if (response.success) {
                        form.closest('.modal').modal('hide');
                        showAlert(response.message, 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showAlert(response.message, 'error', 7000);
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Edit User AJAX Error:", status, error);
                    
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
                    submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Update User');
                }
            });
        }

        function submitDeleteForm() {
            const form = $('#deleteUserForm');
            const formData = form.serialize();

            const submitBtn = form.find('#confirmDeleteBtn');
            const originalHtml = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Deleting...');

            console.log('Submitting Delete User Form:', formData);

            $.ajax({
                url: 'ajax/user_crud.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    console.log('Delete User Response:', response);
                    if (response.success) {
                        form.closest('.modal').modal('hide');
                        showAlert(response.message, 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showAlert(response.message, 'error', 7000);
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Delete User AJAX Error:", status, error);
                    
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
                    submitBtn.prop('disabled', false).html('Delete User');
                }
            });
        }

        // Delete Form Handler
        $('#deleteUserForm').on('submit', function (e) {
            e.preventDefault();
            submitDeleteForm();
        });

        // ----------------------------------------------------
        // User VIEW Handler
        // ----------------------------------------------------
        $('#userTable tbody').on('click', '.view-btn', function () {
            const row = $(this).closest('tr');
            const rowData = userTable.row(row).data();
            const jsonString = rowData[6];

            try {
                const user = JSON.parse(jsonString);

                // Populate the modal fields
                $('#view_id').text(user.id);
                $('#view_name').text(user.firstname + ' ' + user.lastname);
                $('#view_email').text(user.email);

                // Create badge HTML dynamically
                const badgeClass = user.role.toLowerCase() === 'admin' ? 'bg-danger' : (user.role
                    .toLowerCase() === 'zone' ? 'bg-warning' : 'bg-success');
                $('#view_role').html('<span class="badge ' + badgeClass + '">' + user.role + '</span>');

                $('#view_woreda').text(user.woreda_name);
                $('#view_sex').text(user.sex);
                $('#view_phone').text(user.phone || 'N/A');
                $('#view_position').text(user.position || 'N/A');

            } catch (e) {
                console.error("Failed to parse user data JSON:", e);
                showAlert("Error displaying user details.", 'error');
            }
        });

        // ----------------------------------------------------
        // EDIT Button Handler
        // ----------------------------------------------------
        $('#userTable tbody').on('click', '.edit-btn', function () {
            const userId = $(this).data('id');
            console.log('Edit button clicked for user ID:', userId);

            // Fetch user data via AJAX using POST method
            $.ajax({
                url: 'ajax/user_crud.php',
                method: 'POST',
                data: {
                    action: 'get',
                    id: userId
                },
                dataType: 'json',
                success: function (response) {
                    console.log('Edit AJAX Response:', response);
                    if (response.success) {
                        const user = response.data;
                        console.log('User data received:', user);

                        // Populate the edit form
                        $('#edit_user_id').val(user.id);
                        $('#edit_firstname').val(user.firstname);
                        $('#edit_lastname').val(user.lastname);
                        $('#edit_email').val(user.email);
                        $('#edit_phone').val(user.phone || '');
                        $('#edit_position').val(user.position || '');
                        $('#edit_role').val(user.role);
                        $('#edit_sex').val(user.sex);
                        $('#edit_district_id').val(user.district_id);

                        // Show the modal
                        $('#editUserModal').modal('show');
                    } else {
                        showAlert('Error: Could not retrieve user data for editing. ' + (response.message || ''), 'error');
                    }
                },
                error: function (xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    console.log("XHR Response:", xhr.responseText);
                    showAlert('An error occurred during AJAX request. Check console for details.', 'error');
                }
            });
        });

        // ----------------------------------------------------
        // DELETE Button Handler
        // ----------------------------------------------------
        $('#userTable tbody').on('click', '.delete-btn', function () {
            const row = $(this).closest('tr');
            const rowData = userTable.row(row).data();
            const jsonString = rowData[6];

            try {
                const user = JSON.parse(jsonString);

                // Set the ID in the hidden input field of the delete form
                $('#delete_user_id').val(user.id);

                // Display the user's name for confirmation
                $('#delete_username').text(user.firstname + ' ' + user.lastname);

            } catch (e) {
                console.error("Failed to parse user data for deletion:", e);
                showAlert("Error preparing deletion details.", 'error');
            }
        });

        // Clear validation when modals are closed
        $('#addUserModal').on('hidden.bs.modal', function() {
            $('#addUserForm')[0].reset();
            $('.is-invalid').removeClass('is-invalid');
            $('.is-valid').removeClass('is-valid');
            $('.invalid-feedback').remove();
        });

        $('#editUserModal').on('hidden.bs.modal', function() {
            $('.is-invalid').removeClass('is-invalid');
            $('.is-valid').removeClass('is-valid');
            $('.invalid-feedback').remove();
        });
    });
</script>
</body>
</html>