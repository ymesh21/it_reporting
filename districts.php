<?php
require_once 'auth_check.php';
require_once 'config.php';

// Authorization Guard: Only Zone users can manage Districts
if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['user_role'] !== 'Zone') {
    header("Location: unauthorized.php");
    exit;
}

$districts = [];
$zones = [];
$error = null;

try {
    // 1. Fetch the list of all Zones (used for the Parent dropdown in modals)
    $stmt_zones = $pdo->query("SELECT id, name FROM districts WHERE type = 'Zone' ORDER BY name");
    $zones = $stmt_zones->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch the main list of all Districts (districts and Zones)
    $sql = "
        SELECT 
            d.id, 
            d.name, 
            d.type, 
            d.parent_id,
            p.name AS parent_name,
            JSON_OBJECT('id', d.id, 'name', d.name, 'type', d.type, 'parent_id', d.parent_id, 'parent_name', p.name) AS district_json
        FROM districts d
        LEFT JOIN districts p ON d.parent_id = p.id
        ORDER BY d.type DESC, d.name ASC
    ";
    $stmt_districts = $pdo->query($sql);
    $districts = $stmt_districts->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>
<?php include_once 'inc/header.php' ?>
<?php include_once 'inc/sidebar.php'; ?>
<div id="main">
    <?php include_once 'inc/nav.php'; ?>
    <div class="container-fluid content pt-2">
        <h1 class="fw-light mb-4"><i class="fas fa-map-marked-alt me-2"></i> District Management (Zones & districts)</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addDistrictModal">
            <i class="fas fa-plus me-2"></i> Add New District
        </button>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <table id="districtTable" class="table table-striped table-hover w-100">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Parent Zone</th>
                            <th>Actions</th>
                            <th class="d-none">Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($districts as $district): ?>
                            <tr data-district-id="<?php echo $district['id']; ?>">
                                <td><?php echo htmlspecialchars($district['id']); ?></td>
                                <td><?php echo htmlspecialchars($district['name']); ?></td>
                                <td><span
                                        class="badge bg-<?php echo ($district['type'] == 'Zone' ? 'success' : 'info'); ?>"><?php echo htmlspecialchars($district['type']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($district['parent_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning edit-btn" data-id="<?php echo $district['id']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#editDistrictModal" title="Edit"><i
                                            class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-danger delete-btn"
                                        data-id="<?php echo $district['id']; ?>" data-bs-toggle="modal"
                                        data-bs-target="#deleteDistrictModal" title="Delete"><i
                                            class="fas fa-trash-alt"></i></button>
                                </td>
                                <td class="d-none district-data-json">
                                    <?php echo htmlspecialchars($district['district_json']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add District Modal -->
<div class="modal fade" id="addDistrictModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addDistrictForm" action="ajax/district_crud.php" method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Add New District</h5>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name"
                            class="form-control" required></div>

                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" id="add_district_type" class="form-select" required>
                            <option value="Zone">Zone</option>
                            <option value="Woreda">Woreda</option>
                        </select>
                    </div>

                    <div class="mb-3" id="add_parent_zone_group" style="display: none;">
                        <label class="form-label">Parent Zone</label>
                        <select name="parent_id" id="add_parent_id" class="form-select">
                            <option value="">Select Parent Zone</option>
                            <?php foreach ($zones as $zone): ?>
                                <option value="<?php echo $zone['id']; ?>"><?php echo htmlspecialchars($zone['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save
                        District</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit District Modal -->
<div class="modal fade" id="editDistrictModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editDistrictForm" action="ajax/district_crud.php" method="POST">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">Edit District</h5>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_district_id">

                    <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name"
                            id="edit_district_name" class="form-control" required></div>

                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" id="edit_district_type" class="form-select" required>
                            <option value="Zone">Zone</option>
                            <option value="Woreda">Woreda</option>
                        </select>
                    </div>

                    <div class="mb-3" id="edit_parent_zone_group" style="display: none;">
                        <label class="form-label">Parent Zone</label>
                        <select name="parent_id" id="edit_parent_id" class="form-select">
                            <option value="">Select Parent Zone</option>
                            <?php foreach ($zones as $zone): ?>
                                <option value="<?php echo $zone['id']; ?>"><?php echo htmlspecialchars($zone['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i> Update
                        District</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete District Modal -->
<div class="modal fade" id="deleteDistrictModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="deleteDistrictForm" action="ajax/district_crud.php" method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Deletion</h5>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_district_id">
                    <p>Are you sure you want to delete the district: **<span id="delete_district_name"
                            class="fw-bold text-danger"></span>**?</p>
                    <div class="alert alert-warning" role="alert">
                        Deleting a District may affect linked users, sessions, and child districts.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt me-1"></i> Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once 'inc/footer.php'; ?>


<script>
    $(document).ready(function() {
        // ----------------------------------------------------
            // MAKE BOOTSTRAP MODALS DRAGGABLE (Your requested feature)
            // ----------------------------------------------------
            $('.modal').on('shown.bs.modal', function() {
                $(this).find('.modal-dialog').draggable({
                    handle: '.modal-header'
                });
            });
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
        const districtTable = $('#districtTable').DataTable({
            responsive: true,
            "order": [[ 2, "desc" ], [ 1, "asc" ]],
            "pageLength": 10,
            "columnDefs": [
                { "visible": false, "targets": 5 }
            ]
        });
        
        // Make modals draggable
        $('.modal').on('shown.bs.modal', function() {
            $(this).find('.modal-dialog').draggable({
                handle: '.modal-header'
            });
        });

        // Type Change Handler
        const toggleParentDropdown = (typeSelectId, parentGroupDivId) => {
            const type = $(typeSelectId).val();
            if (type === 'Woreda') {
                $(parentGroupDivId).show();
                $(parentGroupDivId).find('select').prop('required', true); 
            } else {
                $(parentGroupDivId).hide();
                $(parentGroupDivId).find('select').prop('required', false);
                $(parentGroupDivId).find('select').val(''); 
            }
        };
        
        $('#add_district_type').on('change', () => toggleParentDropdown('#add_district_type', '#add_parent_zone_group'));
        $('#edit_district_type').on('change', () => toggleParentDropdown('#edit_district_type', '#edit_parent_zone_group'));

        // Client-side validation for Add District Form
        $('#addDistrictForm').on('submit', function(e) {
            e.preventDefault();
            
            const districtName = $('input[name="name"]').val().trim();
            const districtType = $('select[name="type"]').val();
            const parentId = $('select[name="parent_id"]').val();

            console.log('Client-side validation - Name:', districtName, 'Type:', districtType, 'Parent:', parentId);

            // Validate district name
            if (!districtName) {
                showAlert('Please enter a district name.', 'error', 4000);
                $('input[name="name"]').focus();
                return;
            }

            // Validate district name length
            if (districtName.length < 2) {
                showAlert('District name must be at least 2 characters long.', 'error', 4000);
                $('input[name="name"]').focus();
                return;
            }

            // Validate district type
            if (!districtType) {
                showAlert('Please select a district type (Zone or Woreda).', 'error', 4000);
                $('select[name="type"]').focus();
                return;
            }

            // Validate parent zone for Woredas
            if (districtType === 'Woreda' && !parentId) {
                showAlert('Please select a parent zone for the Woreda.', 'error', 4000);
                $('#add_parent_id').focus();
                return;
            }

            // If all validations pass, proceed with AJAX submission
            submitAddForm();
        });

        // Client-side validation for Edit District Form
        $('#editDistrictForm').on('submit', function(e) {
            e.preventDefault();
            
            const districtName = $('#edit_district_name').val().trim();
            const districtType = $('#edit_district_type').val();
            const parentId = $('#edit_parent_id').val();

            console.log('Client-side validation (Edit) - Name:', districtName, 'Type:', districtType, 'Parent:', parentId);

            // Validate district name
            if (!districtName) {
                showAlert('Please enter a district name.', 'error', 4000);
                $('#edit_district_name').focus();
                return;
            }

            // Validate district name length
            if (districtName.length < 2) {
                showAlert('District name must be at least 2 characters long.', 'error', 4000);
                $('#edit_district_name').focus();
                return;
            }

            // Validate district type
            if (!districtType) {
                showAlert('Please select a district type (Zone or Woreda).', 'error', 4000);
                $('#edit_district_type').focus();
                return;
            }

            // Validate parent zone for Woredas
            if (districtType === 'Woreda' && !parentId) {
                showAlert('Please select a parent zone for the Woreda.', 'error', 4000);
                $('#edit_parent_id').focus();
                return;
            }

            // If all validations pass, proceed with AJAX submission
            submitEditForm();
        });

        // AJAX submission functions
        function submitAddForm() {
            const form = $('#addDistrictForm');
            const formData = form.serialize();

            const submitBtn = form.find('button[type="submit"]');
            const originalHtml = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

            console.log('Submitting Add Form:', formData);

            $.ajax({
                url: 'ajax/district_crud.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    console.log('Add District Response:', response);
                    if (response.success) {
                        form.closest('.modal').modal('hide'); 
                        showAlert(response.message, 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showAlert(response.message, 'error', 7000);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Add District AJAX Error:", status, error);
                    
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
                complete: function() {
                    submitBtn.prop('disabled', false).html(originalHtml);
                }
            });
        }

        function submitEditForm() {
            const form = $('#editDistrictForm');
            const formData = form.serialize();

            const submitBtn = form.find('button[type="submit"]');
            const originalHtml = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Updating...');

            console.log('Submitting Edit Form:', formData);

            $.ajax({
                url: 'ajax/district_crud.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    console.log('Edit District Response:', response);
                    if (response.success) {
                        form.closest('.modal').modal('hide'); 
                        showAlert(response.message, 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showAlert(response.message, 'error', 7000);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Edit District AJAX Error:", status, error);
                    
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
                complete: function() {
                    submitBtn.prop('disabled', false).html(originalHtml);
                }
            });
        }

        function submitDeleteForm() {
            const form = $('#deleteDistrictForm');
            const formData = form.serialize();

            const submitBtn = form.find('button[type="submit"]');
            const originalHtml = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Deleting...');

            console.log('Submitting Delete Form:', formData);

            $.ajax({
                url: 'ajax/district_crud.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    console.log('Delete District Response:', response);
                    if (response.success) {
                        form.closest('.modal').modal('hide'); 
                        showAlert(response.message, 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showAlert(response.message, 'error', 7000);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Delete District AJAX Error:", status, error);
                    
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
                complete: function() {
                    submitBtn.prop('disabled', false).html(originalHtml);
                }
            });
        }

        // Delete Form Handler
        $('#deleteDistrictForm').on('submit', function(e) {
            e.preventDefault();
            submitDeleteForm();
        });

        // Real-time validation for Add District form
        $('input[name="name"]').on('input', function() {
            const name = $(this).val().trim();
            const feedback = $(this).next('.invalid-feedback');
            
            if (name.length === 0) {
                $(this).removeClass('is-valid is-invalid');
            } else if (name.length < 2) {
                $(this).removeClass('is-valid').addClass('is-invalid');
                if (feedback.length === 0) {
                    $(this).after('<div class="invalid-feedback">District name must be at least 2 characters long.</div>');
                }
            } else {
                $(this).removeClass('is-invalid').addClass('is-valid');
                feedback.remove();
            }
        });

        // Real-time validation for Edit District form
        $('#edit_district_name').on('input', function() {
            const name = $(this).val().trim();
            const feedback = $(this).next('.invalid-feedback');
            
            if (name.length === 0) {
                $(this).removeClass('is-valid is-invalid');
            } else if (name.length < 2) {
                $(this).removeClass('is-valid').addClass('is-invalid');
                if (feedback.length === 0) {
                    $(this).after('<div class="invalid-feedback">District name must be at least 2 characters long.</div>');
                }
            } else {
                $(this).removeClass('is-invalid').addClass('is-valid');
                feedback.remove();
            }
        });

        // EDIT Button Handler
        $('#districtTable tbody').on('click', '.edit-btn', function() {
            const row = $(this).closest('tr');
            const rowData = districtTable.row(row).data();
            const jsonString = rowData[5]; 
            
            try {
                const district = JSON.parse(jsonString);
                
                $('#edit_district_id').val(district.id);
                $('#edit_district_name').val(district.name);
                $('#edit_district_type').val(district.type);
                toggleParentDropdown('#edit_district_type', '#edit_parent_zone_group');
                $('#edit_parent_id').val(district.parent_id);

                // Clear validation states when opening edit modal
                $('#edit_district_name').removeClass('is-invalid is-valid');
                $('.invalid-feedback').remove();

            } catch (e) {
                console.error("Failed to parse district data JSON:", e);
                showAlert("Error preparing edit details.", 'error');
            }
        });

        // DELETE Button Handler
        $('#districtTable tbody').on('click', '.delete-btn', function() {
            const row = $(this).closest('tr');
            const rowData = districtTable.row(row).data();
            const district = JSON.parse(rowData[5]);

            $('#delete_district_id').val(district.id);
            $('#delete_district_name').text(district.name);
        });

        // Clear validation when modals are closed
        $('#addDistrictModal').on('hidden.bs.modal', function() {
            $('input[name="name"]').removeClass('is-invalid is-valid');
            $('.invalid-feedback').remove();
            $('#addDistrictForm')[0].reset();
            toggleParentDropdown('#add_district_type', '#add_parent_zone_group');
        });

        $('#editDistrictModal').on('hidden.bs.modal', function() {
            $('#edit_district_name').removeClass('is-invalid is-valid');
            $('.invalid-feedback').remove();
        });
        
        // Initial call to hide parent dropdown
        toggleParentDropdown('#add_district_type', '#add_parent_zone_group');
    });
</script>

</body>

</html>