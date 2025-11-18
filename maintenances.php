<?php
// maintenance_management.php
require_once 'auth_check.php';
require_once 'config.php';

// Authorization Guard: Only Admin, Zone, and Woreda users can access this page
if (!has_role('Admin') && !has_role('Zone') && !has_role('Woreda')) {
    header("Location: dashboard.php");
    exit;
}

$maintenances = [];
$devices = [];
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
    if ($user_role == 'Woreda') {
        // Woreda users only see maintenances for devices in their district
        if ($user_district_id) {
            $where_clause = "WHERE d.district_id = :district_id";
            $bind_params['district_id'] = $user_district_id;
        } else {
            $where_clause = "WHERE 1 = 0";
        }
    } elseif ($user_role == 'Zone') {
        // Zone users see maintenances for devices in all Woredas under their zone
        $zone_district_id = $user_district_id;
        $where_clause = "WHERE w.parent_id = :zone_id";
        $bind_params['zone_id'] = $zone_district_id;
    }
    // Admin users see all maintenances (no filter needed)

    // --- 2. Fetch devices (for form dropdown) ---
    if ($user_role == 'Admin') {
        $devices = $pdo->query("SELECT id, device_code, name FROM devices ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($user_role == 'Zone') {
        $stmt = $pdo->prepare("SELECT d.id, d.device_code, d.name FROM devices d JOIN districts w ON d.district_id = w.id WHERE w.parent_id = ? ORDER BY d.name");
        $stmt->execute([$user_district_id]);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($user_role == 'Woreda') {
        $stmt = $pdo->prepare("SELECT id, device_code, name FROM devices WHERE district_id = ? ORDER BY name");
        $stmt->execute([$user_district_id]);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- 3. Fetch Maintenances (for DataTables list) ---
    $sql_maintenances = "
        SELECT 
            m.id, m.maintenance_date, m.issue_description, m.action_taken, m.status, 
            m.remarks, d.device_code, d.name AS device_name, w.name AS district_name,
            u.firstname, u.lastname, m.device_id, d.district_id
        FROM maintenances m
        JOIN devices d ON m.device_id = d.id
        JOIN districts w ON d.district_id = w.id
        JOIN users u ON m.user_id = u.id
        " . $where_clause . "
        ORDER BY m.maintenance_date DESC
    ";

    $stmt_maintenances = $pdo->prepare($sql_maintenances);
    if (!empty($bind_params)) {
        foreach ($bind_params as $key => $value) {
            $stmt_maintenances->bindValue(':' . $key, $value);
        }
    }
    $stmt_maintenances->execute();
    $maintenances = $stmt_maintenances->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>
<?php include_once 'inc/header.php' ?>
<?php include_once 'inc/sidebar.php'; ?>
<div id="main">
    <?php include_once 'inc/nav.php'; ?>
    <div class="container-fluid content pt-2">
        <h1 class="fw-light mb-4"><i class="fas fa-tools me-2"></i> Maintenance Management</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($user_role != 'Zone'): ?>
            <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addMaintenanceModal">
                <i class="fas fa-plus me-2"></i> Add New Maintenance
            </button>
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <table id="maintenanceTable" class="table table-striped table-hover w-100">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Device</th>
                            <th>Issue Description</th>
                            <th>Status</th>
                            <th>Maintenance Date</th>
                            <th>District</th>
                            <th>Technician</th>
                            <th>Actions</th>
                            <th class="d-none">Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($maintenances as $maintenance):
                            $maintenance_json = json_encode($maintenance);
                            ?>
                            <tr data-maintenance-id="<?php echo $maintenance['id']; ?>">
                                <td><?php echo htmlspecialchars($maintenance['id']); ?></td>
                                <td><?php echo htmlspecialchars($maintenance['device_name'] . ' (' . $maintenance['device_code'] . ')'); ?>
                                </td>
                                <td><?php echo htmlspecialchars(substr($maintenance['issue_description'], 0, 50) . '...'); ?>
                                </td>
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
                                        class="badge bg-<?php echo $badge_class; ?>"><?php echo htmlspecialchars($maintenance['status']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars(date('M j, Y', strtotime($maintenance['maintenance_date']))); ?>
                                </td>
                                <td><?php echo htmlspecialchars($maintenance['district_name']); ?></td>
                                <td><?php echo htmlspecialchars($maintenance['firstname'] . ' ' . $maintenance['lastname']); ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-info view-btn" data-bs-toggle="modal"
                                            data-bs-target="#viewMaintenanceModal" title="View"><i
                                                class="fas fa-eye"></i></button>
                                        <?php if ($user_role != 'Zone'): ?>
                                            <button class="btn btn-sm btn-warning edit-btn" data-bs-toggle="modal"
                                                data-bs-target="#editMaintenanceModal" title="Edit"><i
                                                    class="fas fa-edit"></i></button>
                                            <button class="btn btn-sm btn-danger delete-btn" data-bs-toggle="modal"
                                                data-bs-target="#deleteMaintenanceModal" title="Delete"><i
                                                    class="fas fa-trash-alt"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="d-none maintenance-data-json"><?php echo htmlspecialchars($maintenance_json); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($user_role != 'Zone'): ?>
    <!-- ADD Maintenance Modal -->
    <div class="modal fade" id="addMaintenanceModal" tabindex="-1" aria-labelledby="addMaintenanceModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addMaintenanceModalLabel"><i class="fas fa-plus me-2"></i> Add New
                        Maintenance</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <form id="addMaintenanceForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Device <span class="text-danger">*</span></label>
                                <select name="device_id" class="form-select" required>
                                    <option value="">Select Device</option>
                                    <?php foreach ($devices as $device): ?>
                                        <option value="<?php echo $device['id']; ?>">
                                            <?php echo htmlspecialchars($device['name'] . ' (' . $device['device_code'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Maintenance Date <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="maintenance_date" class="form-control" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="">Select Status</option>
                                <option value="Pending">Pending</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                                <option value="Not Fixable">Not Fixable</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Issue Description <span class="text-danger">*</span></label>
                            <textarea name="issue_description" class="form-control" rows="3"
                                placeholder="Describe the issue..." required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Action Taken</label>
                            <textarea name="action_taken" class="form-control" rows="3"
                                placeholder="Describe the action taken..."></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2"
                                placeholder="Additional remarks..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save
                            Maintenance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT Maintenance Modal -->
    <div class="modal fade" id="editMaintenanceModal" tabindex="-1" aria-labelledby="editMaintenanceModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="editMaintenanceModalLabel"><i class="fas fa-edit me-2"></i> Edit Maintenance
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editMaintenanceForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_maintenance_id">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Device <span class="text-danger">*</span></label>
                                <select name="device_id" id="edit_device_id" class="form-select" required>
                                    <?php foreach ($devices as $device): ?>
                                        <option value="<?php echo $device['id']; ?>">
                                            <?php echo htmlspecialchars($device['name'] . ' (' . $device['device_code'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Maintenance Date <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="maintenance_date" id="edit_maintenance_date"
                                    class="form-control" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" id="edit_status" class="form-select" required>
                                <option value="Pending">Pending</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                                <option value="Not Fixable">Not Fixable</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Issue Description <span class="text-danger">*</span></label>
                            <textarea name="issue_description" id="edit_issue_description" class="form-control" rows="3"
                                required></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Action Taken</label>
                            <textarea name="action_taken" id="edit_action_taken" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" id="edit_remarks" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i> Update
                            Maintenance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- DELETE Maintenance Modal -->
    <div class="modal fade" id="deleteMaintenanceModal" tabindex="-1" aria-labelledby="deleteMaintenanceModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteMaintenanceModalLabel"><i class="fas fa-trash-alt me-2"></i> Confirm
                        Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <form id="deleteMaintenanceForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete_maintenance_id">

                        <p class="mb-3">Are you sure you want to delete this maintenance record?</p>
                        <div class="alert alert-warning" role="alert">
                            This action cannot be undone.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" id="confirmDeleteBtn">Delete Maintenance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- VIEW Maintenance Modal -->
<div class="modal fade" id="viewMaintenanceModal" tabindex="-1" aria-labelledby="viewMaintenanceModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="viewMaintenanceModalLabel">Maintenance Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row">
                    <dt class="col-sm-3">Device:</dt>
                    <dd class="col-sm-9" id="view_device"></dd>

                    <dt class="col-sm-3">Maintenance Date:</dt>
                    <dd class="col-sm-9" id="view_maintenance_date"></dd>

                    <dt class="col-sm-3">Status:</dt>
                    <dd class="col-sm-9" id="view_status"></dd>

                    <dt class="col-sm-3">District:</dt>
                    <dd class="col-sm-9" id="view_district"></dd>

                    <dt class="col-sm-3">Technician:</dt>
                    <dd class="col-sm-9" id="view_technician"></dd>

                    <dt class="col-sm-3">Issue Description:</dt>
                    <dd class="col-sm-9" id="view_issue_description"></dd>

                    <dt class="col-sm-3">Action Taken:</dt>
                    <dd class="col-sm-9" id="view_action_taken"></dd>

                    <dt class="col-sm-3">Remarks:</dt>
                    <dd class="col-sm-9" id="view_remarks"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include_once 'inc/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Enhanced Alert Function
        function showAlert(message, type, duration = 5000) {
            const alertConfig = {
                success: { icon: 'fas fa-check-circle', title: 'Success!', bgClass: 'alert-success', iconColor: 'text-success' },
                error: { icon: 'fas fa-exclamation-triangle', title: 'Error!', bgClass: 'alert-danger', iconColor: 'text-danger' },
                warning: { icon: 'fas fa-exclamation-circle', title: 'Warning!', bgClass: 'alert-warning', iconColor: 'text-warning' },
                info: { icon: 'fas fa-info-circle', title: 'Information', bgClass: 'alert-info', iconColor: 'text-info' }
            };

            const config = alertConfig[type] || alertConfig.info;

            const alertHtml = `
                <div class="alert ${config.bgClass} alert-dismissible fade show custom-alert" role="alert">
                    <div class="alert-icon me-3"><i class="${config.icon} ${config.iconColor}"></i></div>
                    <div class="alert-content flex-grow-1">
                        <h6 class="alert-title mb-1">${config.title}</h6>
                        <p class="alert-message mb-0">${message}</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;

            let alertContainer = $('#alert-container');
            if (alertContainer.length === 0) {
                $('body').prepend('<div id="alert-container" style="position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 350px; max-width: 500px;"></div>');
                alertContainer = $('#alert-container');
            }

            alertContainer.append(alertHtml);
            setTimeout(() => { $(`.alert`).alert('close'); }, duration);
        }

        // Initialize DataTables
        const maintenanceTable = $('#maintenanceTable').DataTable({
            responsive: true,
            "order": [[0, "desc"]],
            "pageLength": 10,
            dom: "<'row'<'col-md-6'l><'col-md-6 text-right'f>><'row'<'col-sm-12'tr>><'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            buttons: [
                { extend: 'copyHtml5', exportOptions: { columns: ':visible:not(:last-child)' } },
                { extend: 'excelHtml5', text: '<i class="fas fa-file-excel"></i> Excel', className: 'btn-success btn-sm', exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] } },
                { extend: 'csvHtml5', text: '<i class="fas fa-file-csv"></i> CSV', className: 'btn-info btn-sm', exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] } },
                { extend: 'pdfHtml5', text: '<i class="fas fa-file-pdf"></i> PDF', className: 'btn-danger btn-sm', exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6] } }
            ],
            "columnDefs": [{ "visible": false, "targets": 8 }]
        });

        <?php if ($user_role != 'Zone'): ?>
            // Client-side validation for Add Maintenance Form
            $('#addMaintenanceForm').on('submit', function (e) {
                e.preventDefault();

                const deviceId = $('select[name="device_id"]').val();
                const maintenanceDate = $('input[name="maintenance_date"]').val();
                const status = $('select[name="status"]').val();
                const issueDescription = $('textarea[name="issue_description"]').val().trim();

                if (!deviceId) { showAlert('Please select a device.', 'error', 4000); $('select[name="device_id"]').focus(); return; }
                if (!maintenanceDate) { showAlert('Please select maintenance date.', 'error', 4000); $('input[name="maintenance_date"]').focus(); return; }
                if (!status) { showAlert('Please select status.', 'error', 4000); $('select[name="status"]').focus(); return; }
                if (!issueDescription) { showAlert('Please enter issue description.', 'error', 4000); $('textarea[name="issue_description"]').focus(); return; }

                submitAddForm();
            });

            // Client-side validation for Edit Maintenance Form
            $('#editMaintenanceForm').on('submit', function (e) {
                e.preventDefault();

                const deviceId = $('#edit_device_id').val();
                const maintenanceDate = $('#edit_maintenance_date').val();
                const status = $('#edit_status').val();
                const issueDescription = $('#edit_issue_description').val().trim();

                if (!deviceId) { showAlert('Please select a device.', 'error', 4000); $('#edit_device_id').focus(); return; }
                if (!maintenanceDate) { showAlert('Please select maintenance date.', 'error', 4000); $('#edit_maintenance_date').focus(); return; }
                if (!status) { showAlert('Please select status.', 'error', 4000); $('#edit_status').focus(); return; }
                if (!issueDescription) { showAlert('Please enter issue description.', 'error', 4000); $('#edit_issue_description').focus(); return; }

                submitEditForm();
            });

            // AJAX submission functions
            function submitAddForm() {
                const form = $('#addMaintenanceForm');
                const formData = form.serialize();
                const submitBtn = form.find('button[type="submit"]');
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

                $.ajax({
                    url: 'ajax/maintenance_crud.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function (response) {
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
                        let errorMessage = 'An unexpected network error occurred.';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response && response.message) errorMessage = response.message;
                        } catch (e) {
                            if (xhr.responseText) errorMessage = 'Server Error: ' + xhr.responseText.substring(0, 100);
                        }
                        showAlert(errorMessage, 'error', 7000);
                    },
                    complete: function () {
                        submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Maintenance');
                    }
                });
            }

            function submitEditForm() {
                const form = $('#editMaintenanceForm');
                const formData = form.serialize();
                const submitBtn = form.find('button[type="submit"]');
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Updating...');

                $.ajax({
                    url: 'ajax/maintenance_crud.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            form.closest('.modal').modal('hide');
                            showAlert(response.message, 'success');
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            showAlert(response.message, 'error', 7000);
                        }
                    },
                    error: function (xhr, status, error) {
                        let errorMessage = 'An unexpected network error occurred.';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response && response.message) errorMessage = response.message;
                        } catch (e) {
                            if (xhr.responseText) errorMessage = 'Server Error: ' + xhr.responseText.substring(0, 100);
                        }
                        showAlert(errorMessage, 'error', 7000);
                    },
                    complete: function () {
                        submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Update Maintenance');
                    }
                });
            }

            function submitDeleteForm() {
                const form = $('#deleteMaintenanceForm');
                const formData = form.serialize();
                const submitBtn = form.find('#confirmDeleteBtn');
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Deleting...');

                $.ajax({
                    url: 'ajax/maintenance_crud.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            form.closest('.modal').modal('hide');
                            showAlert(response.message, 'success');
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            showAlert(response.message, 'error', 7000);
                        }
                    },
                    error: function (xhr, status, error) {
                        let errorMessage = 'An unexpected network error occurred.';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response && response.message) errorMessage = response.message;
                        } catch (e) {
                            if (xhr.responseText) errorMessage = 'Server Error: ' + xhr.responseText.substring(0, 100);
                        }
                        showAlert(errorMessage, 'error', 7000);
                    },
                    complete: function () {
                        submitBtn.prop('disabled', false).html('Delete Maintenance');
                    }
                });
            }

            // Delete Form Handler
            $('#deleteMaintenanceForm').on('submit', function (e) {
                e.preventDefault();
                submitDeleteForm();
            });
        <?php endif; ?>

        // VIEW Button Handler
        $('#maintenanceTable tbody').on('click', '.view-btn', function () {
            const row = $(this).closest('tr');
            const rowData = maintenanceTable.row(row).data();
            const jsonString = rowData[8];

            try {
                const maintenance = JSON.parse(jsonString);

                const status_badge = {
                    'Pending': 'warning',
                    'In Progress': 'info',
                    'Completed': 'success',
                    'Not Fixable': 'danger'
                };
                const badge_class = status_badge[maintenance.status] || 'secondary';

                $('#view_device').text(maintenance.device_name + ' (' + maintenance.device_code + ')');
                $('#view_maintenance_date').text(new Date(maintenance.maintenance_date).toLocaleString());
                $('#view_status').html('<span class="badge bg-' + badge_class + '">' + maintenance.status + '</span>');
                $('#view_district').text(maintenance.district_name);
                $('#view_technician').text(maintenance.firstname + ' ' + maintenance.lastname);
                $('#view_issue_description').text(maintenance.issue_description);
                $('#view_action_taken').text(maintenance.action_taken || 'No action taken recorded.');
                $('#view_remarks').text(maintenance.remarks || 'No remarks.');
            } catch (e) {
                console.error("Failed to parse maintenance data JSON:", e);
                showAlert("Error displaying maintenance details.", 'error');
            }
        });

        <?php if ($user_role != 'Zone'): ?>
            // EDIT Button Handler
            $('#maintenanceTable tbody').on('click', '.edit-btn', function () {
                const row = $(this).closest('tr');
                const rowData = maintenanceTable.row(row).data();
                const jsonString = rowData[8];

                try {
                    const maintenance = JSON.parse(jsonString);

                    $('#edit_maintenance_id').val(maintenance.id);
                    $('#edit_device_id').val(maintenance.device_id);

                    // Format datetime for datetime-local input
                    const maintenanceDate = new Date(maintenance.maintenance_date);
                    const formattedDate = maintenanceDate.toISOString().slice(0, 16);
                    $('#edit_maintenance_date').val(formattedDate);

                    $('#edit_status').val(maintenance.status);
                    $('#edit_issue_description').val(maintenance.issue_description);
                    $('#edit_action_taken').val(maintenance.action_taken || '');
                    $('#edit_remarks').val(maintenance.remarks || '');
                } catch (e) {
                    console.error("Failed to parse maintenance data JSON:", e);
                    showAlert("Error loading maintenance data for editing.", 'error');
                }
            });

            // DELETE Button Handler
            $('#maintenanceTable tbody').on('click', '.delete-btn', function () {
                const row = $(this).closest('tr');
                const rowData = maintenanceTable.row(row).data();
                const jsonString = rowData[8];

                try {
                    const maintenance = JSON.parse(jsonString);
                    $('#delete_maintenance_id').val(maintenance.id);
                } catch (e) {
                    console.error("Failed to parse maintenance data for deletion:", e);
                    showAlert("Error preparing deletion details.", 'error');
                }
            });

            // Clear validation when modals are closed
            $('#addMaintenanceModal').on('hidden.bs.modal', function () {
                $('#addMaintenanceForm')[0].reset();
            });

            $('#editMaintenanceModal').on('hidden.bs.modal', function () {
                // Clear any validation states if needed
            });
        <?php endif; ?>

        // Make Bootstrap modals draggable
        $('.modal').on('shown.bs.modal', function () {
            $(this).find('.modal-dialog').draggable({
                handle: '.modal-header'
            });
        });
    });
</script>
</body>

</html>