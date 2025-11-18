<?php
// devices_management.php
require_once 'auth_check.php';
require_once 'config.php';

// Authorization Guard: Only Admin, Zone, and Woreda users can access this page
if (!has_role('Admin') && !has_role('Zone') && !has_role('Woreda')) {
    header("Location: dashboard.php");
    exit;
}

$devices = [];
$districts = [];
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

    // Store in session for later use if needed
    $_SESSION['user_district_id'] = $user_district_id;
}

try {
    // --- 1. Role-Based Filtering Setup ---
    if ($user_role == 'Woreda') {
        // Woreda users only see devices in their district
        if ($user_district_id) {
            $where_clause = "WHERE d.district_id = :district_id";
            $bind_params['district_id'] = $user_district_id;
        } else {
            $where_clause = "WHERE 1 = 0";
        }
    } elseif ($user_role == 'Zone') {
        // Zone users see devices in all Woredas under their zone
        $zone_district_id = $user_district_id;
        $where_clause = "WHERE w.parent_id = :zone_id";
        $bind_params['zone_id'] = $zone_district_id;
    }
    // Admin users see all devices (no filter needed)

    // --- 2. Fetch districts (for form dropdown - Admin only) ---
    if ($user_role == 'Admin') {
        $districts = $pdo->query("SELECT id, name FROM districts ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($user_role == 'Zone') {
        $stmt = $pdo->prepare("SELECT id, name FROM districts WHERE parent_id = ? ORDER BY name");
        $stmt->execute([$user_district_id]);
        $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($user_role == 'Woreda') {
        $stmt = $pdo->prepare("SELECT id, name FROM districts WHERE id = ?");
        $stmt->execute([$user_district_id]);
        $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- 3. Fetch Devices (for DataTables list) ---
    $sql_devices = "
        SELECT 
            d.id, d.device_code, d.name, d.brand, d.model, d.serial_number, 
            d.device_type, d.description, w.name AS district_name, d.district_id
        FROM devices d
        JOIN districts w ON d.district_id = w.id
        " . $where_clause . "
        ORDER BY d.name ASC
    ";

    $stmt_devices = $pdo->prepare($sql_devices);
    if (!empty($bind_params)) {
        foreach ($bind_params as $key => $value) {
            $stmt_devices->bindValue(':' . $key, $value);
        }
    }
    $stmt_devices->execute();
    $devices = $stmt_devices->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>
<?php include_once 'inc/header.php' ?>
<?php include_once 'inc/sidebar.php'; ?>
<div id="main">
    <?php include_once 'inc/nav.php'; ?>
    <div class="container-fluid content pt-2">
        <h1 class="fw-light mb-4"><i class="fas fa-laptop me-2"></i> Devices Management</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($user_role != 'Zone'): ?>
            <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                <i class="fas fa-plus me-2"></i> Add New Device
            </button>
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <table id="deviceTable" class="table table-striped table-hover w-100">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Device Code</th>
                            <th>Name</th>
                            <th>Brand</th>
                            <th>Model</th>
                            <th>Type</th>
                            <th>District</th>
                            <th>Actions</th>
                            <th class="d-none">Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($devices as $device):
                            $device_json = json_encode($device);
                            ?>
                            <tr data-device-id="<?php echo $device['id']; ?>">
                                <td><?php echo htmlspecialchars($device['id']); ?></td>
                                <td><?php echo htmlspecialchars($device['device_code']); ?></td>
                                <td><?php echo htmlspecialchars($device['name']); ?></td>
                                <td><?php echo htmlspecialchars($device['brand']); ?></td>
                                <td><?php echo htmlspecialchars($device['model']); ?></td>
                                <td><?php echo htmlspecialchars($device['device_type']); ?></td>
                                <td><?php echo htmlspecialchars($device['district_name']); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-info view-btn" data-bs-toggle="modal"
                                            data-bs-target="#viewDeviceModal" title="View"><i
                                                class="fas fa-eye"></i></button>
                                        <?php if ($user_role != 'Zone'): ?>
                                            <button class="btn btn-sm btn-warning edit-btn" data-bs-toggle="modal"
                                                data-bs-target="#editDeviceModal" title="Edit"><i
                                                    class="fas fa-edit"></i></button>
                                            <button class="btn btn-sm btn-danger delete-btn" data-bs-toggle="modal"
                                                data-bs-target="#deleteDeviceModal" title="Delete"><i
                                                    class="fas fa-trash-alt"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="d-none device-data-json"><?php echo htmlspecialchars($device_json); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($user_role != 'Zone'): ?>
    <!-- ADD Device Modal -->
    <div class="modal fade" id="addDeviceModal" tabindex="-1" aria-labelledby="addDeviceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addDeviceModalLabel"><i class="fas fa-plus me-2"></i> Add New Device</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <form id="addDeviceForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Device Code <span class="text-danger">*</span></label>
                                <input type="text" name="device_code" class="form-control" placeholder="Enter device code"
                                    required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Device Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="Enter device name"
                                    required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Brand <span class="text-danger">*</span></label>
                                <input type="text" name="brand" class="form-control" placeholder="Enter brand" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Model <span class="text-danger">*</span></label>
                                <input type="text" name="model" class="form-control" placeholder="Enter model" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Serial Number</label>
                                <input type="text" name="serial_number" class="form-control"
                                    placeholder="Enter serial number">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Device Type <span class="text-danger">*</span></label>
                                <select name="device_type" class="form-select" required>
                                    <option value="">Select Device Type</option>
                                    <option value="Laptop">Laptop</option>
                                    <option value="Desktop">Desktop</option>
                                    <option value="Printer">Printer</option>
                                    <option value="Scanner">Scanner</option>
                                    <option value="Tablet">Tablet</option>
                                    <option value="Smartphone">Smartphone</option>
                                    <option value="Server">Server</option>
                                    <option value="Network Switch">Network Switch</option>
                                    <option value="Router">Router</option>
                                    <option value="Access Point">Access Point</option>
                                    <option value="Monitor">Monitor</option>
                                    <option value="Projector">Projector</option>
                                    <option value="UPS">UPS</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">District <span class="text-danger">*</span></label>
                                <?php if ($user_role == 'Woreda' && $user_district_id): ?>
                                    <!-- Woreda users can only see their district -->
                                    <select name="district_id" class="form-select" required>
                                        <?php foreach ($districts as $district): ?>
                                            <option value="<?php echo $district['id']; ?>" <?php echo $district['id'] == $user_district_id ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($district['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">You can only assign devices to your district.</small>
                                <?php else: ?>
                                    <!-- Admin and Zone users can select from available districts -->
                                    <select name="district_id" class="form-select" required>
                                        <option value="">Select District</option>
                                        <?php foreach ($districts as $district): ?>
                                            <option value="<?php echo $district['id']; ?>">
                                                <?php echo htmlspecialchars($district['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"
                                placeholder="Optional device description"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Device</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT Device Modal -->
    <div class="modal fade" id="editDeviceModal" tabindex="-1" aria-labelledby="editDeviceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="editDeviceModalLabel"><i class="fas fa-edit me-2"></i> Edit Device</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editDeviceForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_device_id">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Device Code <span class="text-danger">*</span></label>
                                <input type="text" name="device_code" id="edit_device_code" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Device Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="edit_name" class="form-control" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Brand <span class="text-danger">*</span></label>
                                <input type="text" name="brand" id="edit_brand" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Model <span class="text-danger">*</span></label>
                                <input type="text" name="model" id="edit_model" class="form-control" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Serial Number</label>
                                <input type="text" name="serial_number" id="edit_serial_number" class="form-control">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Device Type <span class="text-danger">*</span></label>
                                <select name="device_type" id="edit_device_type" class="form-select" required>
                                    <option value="">Select Device Type</option>
                                    <option value="Laptop">Laptop</option>
                                    <option value="Desktop">Desktop</option>
                                    <option value="Printer">Printer</option>
                                    <option value="Scanner">Scanner</option>
                                    <option value="Tablet">Tablet</option>
                                    <option value="Smartphone">Smartphone</option>
                                    <option value="Server">Server</option>
                                    <option value="Network Switch">Network Switch</option>
                                    <option value="Router">Router</option>
                                    <option value="Access Point">Access Point</option>
                                    <option value="Monitor">Monitor</option>
                                    <option value="Projector">Projector</option>
                                    <option value="UPS">UPS</option>
                                    <option value="Other">Other</option>
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

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i> Update
                            Device</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- DELETE Device Modal -->
    <div class="modal fade" id="deleteDeviceModal" tabindex="-1" aria-labelledby="deleteDeviceModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteDeviceModalLabel"><i class="fas fa-trash-alt me-2"></i> Confirm
                        Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <form id="deleteDeviceForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete_device_id">

                        <p class="mb-3">Are you sure you want to delete the device: **<span id="delete_device_name"
                                class="fw-bold text-danger"></span>**?</p>
                        <div class="alert alert-warning" role="alert">
                            This will also delete all maintenance records linked to this device. This action cannot be
                            undone.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" id="confirmDeleteBtn">Delete Device</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- VIEW Device Modal -->
<div class="modal fade" id="viewDeviceModal" tabindex="-1" aria-labelledby="viewDeviceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="viewDeviceModalLabel">Device Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row">
                    <dt class="col-sm-3">Device Code:</dt>
                    <dd class="col-sm-9" id="view_device_code"></dd>

                    <dt class="col-sm-3">Name:</dt>
                    <dd class="col-sm-9" id="view_name"></dd>

                    <dt class="col-sm-3">Brand:</dt>
                    <dd class="col-sm-9" id="view_brand"></dd>

                    <dt class="col-sm-3">Model:</dt>
                    <dd class="col-sm-9" id="view_model"></dd>

                    <dt class="col-sm-3">Serial Number:</dt>
                    <dd class="col-sm-9" id="view_serial_number"></dd>

                    <dt class="col-sm-3">Device Type:</dt>
                    <dd class="col-sm-9" id="view_device_type"></dd>

                    <dt class="col-sm-3">District:</dt>
                    <dd class="col-sm-9" id="view_district"></dd>

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
        const deviceTable = $('#deviceTable').DataTable({
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
            // Client-side validation for Add Device Form
            $('#addDeviceForm').on('submit', function (e) {
                e.preventDefault();

                const deviceCode = $('input[name="device_code"]').val().trim();
                const name = $('input[name="name"]').val().trim();
                const brand = $('input[name="brand"]').val().trim();
                const model = $('input[name="model"]').val().trim();
                const deviceType = $('select[name="device_type"]').val();
                const districtId = $('select[name="district_id"]').val(); // Now checking select field

                if (!deviceCode) { showAlert('Please enter device code.', 'error', 4000); $('input[name="device_code"]').focus(); return; }
                if (!name) { showAlert('Please enter device name.', 'error', 4000); $('input[name="name"]').focus(); return; }
                if (!brand) { showAlert('Please enter brand.', 'error', 4000); $('input[name="brand"]').focus(); return; }
                if (!model) { showAlert('Please enter model.', 'error', 4000); $('input[name="model"]').focus(); return; }
                if (!deviceType) { showAlert('Please select device type.', 'error', 4000); $('select[name="device_type"]').focus(); return; }
                if (!districtId) { showAlert('Please select district.', 'error', 4000); $('select[name="district_id"]').focus(); return; } // Updated message

                submitAddForm();
            });

            // Client-side validation for Edit Device Form
            $('#editDeviceForm').on('submit', function (e) {
                e.preventDefault();

                const deviceCode = $('#edit_device_code').val().trim();
                const name = $('#edit_name').val().trim();
                const brand = $('#edit_brand').val().trim();
                const model = $('#edit_model').val().trim();
                const deviceType = $('#edit_device_type').val();
                const districtId = $('#edit_district_id').val(); // Now checking select field

                if (!deviceCode) { showAlert('Please enter device code.', 'error', 4000); $('#edit_device_code').focus(); return; }
                if (!name) { showAlert('Please enter device name.', 'error', 4000); $('#edit_name').focus(); return; }
                if (!brand) { showAlert('Please enter brand.', 'error', 4000); $('#edit_brand').focus(); return; }
                if (!model) { showAlert('Please enter model.', 'error', 4000); $('#edit_model').focus(); return; }
                if (!deviceType) { showAlert('Please select device type.', 'error', 4000); $('#edit_device_type').focus(); return; }
                if (!districtId) { showAlert('Please select district.', 'error', 4000); $('#edit_district_id').focus(); return; } // Updated message

                submitEditForm();
            });

            // AJAX submission functions
            function submitAddForm() {
                const form = $('#addDeviceForm');
                const formData = form.serialize();
                const submitBtn = form.find('button[type="submit"]');
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

                $.ajax({
                    url: 'ajax/device_crud.php',
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
                        submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Device');
                    }
                });
            }

            function submitEditForm() {
                const form = $('#editDeviceForm');
                const formData = form.serialize();
                const submitBtn = form.find('button[type="submit"]');
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Updating...');

                $.ajax({
                    url: 'ajax/device_crud.php',
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
                        submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Update Device');
                    }
                });
            }

            function submitDeleteForm() {
                const form = $('#deleteDeviceForm');
                const formData = form.serialize();
                const submitBtn = form.find('#confirmDeleteBtn');
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Deleting...');

                $.ajax({
                    url: 'ajax/device_crud.php',
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
                        submitBtn.prop('disabled', false).html('Delete Device');
                    }
                });
            }

            // Delete Form Handler
            $('#deleteDeviceForm').on('submit', function (e) {
                e.preventDefault();
                submitDeleteForm();
            });
        <?php endif; ?>

        // VIEW Button Handler
        $('#deviceTable tbody').on('click', '.view-btn', function () {
            const row = $(this).closest('tr');
            const rowData = deviceTable.row(row).data();
            const jsonString = rowData[8];

            try {
                const device = JSON.parse(jsonString);
                $('#view_device_code').text(device.device_code);
                $('#view_name').text(device.name);
                $('#view_brand').text(device.brand);
                $('#view_model').text(device.model);
                $('#view_serial_number').text(device.serial_number || 'N/A');
                $('#view_device_type').text(device.device_type);
                $('#view_district').text(device.district_name);
                $('#view_description').text(device.description || 'No description provided.');
            } catch (e) {
                console.error("Failed to parse device data JSON:", e);
                showAlert("Error displaying device details.", 'error');
            }
        });

        <?php if ($user_role != 'Zone'): ?>
            // EDIT Button Handler
            $('#deviceTable tbody').on('click', '.edit-btn', function () {
                const row = $(this).closest('tr');
                const rowData = deviceTable.row(row).data();
                const jsonString = rowData[8];

                try {
                    const device = JSON.parse(jsonString);
                    $('#edit_device_id').val(device.id);
                    $('#edit_device_code').val(device.device_code);
                    $('#edit_name').val(device.name);
                    $('#edit_brand').val(device.brand);
                    $('#edit_model').val(device.model);
                    $('#edit_serial_number').val(device.serial_number || '');
                    $('#edit_device_type').val(device.device_type); // This will now work with select
                    $('#edit_district_id').val(device.district_id);
                    $('#edit_district_display').val(device.district_name);
                    $('#edit_description').val(device.description || '');
                } catch (e) {
                    console.error("Failed to parse device data JSON:", e);
                    showAlert("Error loading device data for editing.", 'error');
                }
            });

            // DELETE Button Handler
            $('#deviceTable tbody').on('click', '.delete-btn', function () {
                const row = $(this).closest('tr');
                const rowData = deviceTable.row(row).data();
                const jsonString = rowData[8];

                try {
                    const device = JSON.parse(jsonString);
                    $('#delete_device_id').val(device.id);
                    $('#delete_device_name').text(device.name);
                } catch (e) {
                    console.error("Failed to parse device data for deletion:", e);
                    showAlert("Error preparing deletion details.", 'error');
                }
            });

            // Clear validation when modals are closed
            $('#addDeviceModal').on('hidden.bs.modal', function () {
                $('#addDeviceForm')[0].reset();
            });

            $('#editDeviceModal').on('hidden.bs.modal', function () {
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