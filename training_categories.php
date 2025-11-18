<?php
// training_category_management.php
require_once 'auth_check.php';
require_once 'config.php';

// Ensure user is logged in
if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

// Check if user has permission to manage training categories (Zone or Woreda)
$allowed_roles = ['Zone', 'Woreda'];
if (!in_array($_SESSION['user_role'], $allowed_roles)) {
    header("Location: unauthorized.php");
    exit;
}

$categories = [];
$error = null;

try {
    // Fetch all training categories
    $stmt = $pdo->query("SELECT id, name FROM training_categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>
<?php include_once 'inc/header.php' ?>
<?php include_once 'inc/sidebar.php'; ?>
<div id="main">
    <?php include_once 'inc/nav.php'; ?>
    <div class="container-fluid content pt-2">
        <h1 class="fw-light mb-4"><i class="fas fa-list-alt me-2"></i> Training Category Management</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="fas fa-plus me-2"></i> Add New Category
        </button>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <table id="categoryTable" class="table table-striped table-hover w-100">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Category Name</th>
                            <th>Actions</th>
                            <th class="d-none">Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category):
                            $category_json = json_encode($category);
                            ?>
                            <tr data-category-id="<?php echo $category['id']; ?>">
                                <td><?php echo htmlspecialchars($category['id']); ?></td>
                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning edit-btn" data-id="<?php echo $category['id']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#editCategoryModal" title="Edit"><i
                                            class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-danger delete-btn"
                                        data-id="<?php echo $category['id']; ?>"
                                        data-bs-toggle="modal" data-bs-target="#deleteCategoryModal" title="Delete"><i
                                            class="fas fa-trash-alt"></i></button>
                                </td>
                                <td class="d-none category-data-json"><?php echo htmlspecialchars($category_json); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ADD Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addCategoryModalLabel"><i class="fas fa-plus me-2"></i> Add New Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addCategoryForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Enter category name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="editCategoryModalLabel"><i class="fas fa-edit me-2"></i> Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editCategoryForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_category_id">
                    <div class="mb-3">
                        <label class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_category_name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i> Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DELETE Category Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteCategoryModalLabel"><i class="fas fa-trash-alt me-2"></i> Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="deleteCategoryForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_category_id">

                    <p class="mb-3">Are you sure you want to delete the category: **<span id="delete_category_name" class="fw-bold text-danger"></span>**?</p>
                    <div class="alert alert-warning" role="alert">
                        Deleting a category may affect linked training sessions. This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="confirmDeleteBtn">Delete Category</button>
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
        const categoryTable = $('#categoryTable').DataTable({
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
                    exportOptions: { columns: [0, 1] }
                },
                {
                    extend: 'csvHtml5',
                    text: '<i class="fas fa-file-csv"></i> CSV',
                    className: 'btn-info btn-sm',
                    exportOptions: { columns: [0, 1] }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> PDF',
                    className: 'btn-danger btn-sm',
                    exportOptions: { columns: [0, 1] }
                }
            ],
            "columnDefs": [{
                "visible": false,
                "targets": 3
            }]
        });

        // Client-side validation for Add Category Form
        $('#addCategoryForm').on('submit', function (e) {
            e.preventDefault();
            
            const categoryName = $('input[name="name"]').val().trim();

            // Validate category name
            if (!categoryName) {
                showAlert('Please enter category name.', 'error', 4000);
                $('input[name="name"]').focus();
                return;
            }

            if (categoryName.length < 2) {
                showAlert('Category name must be at least 2 characters long.', 'error', 4000);
                $('input[name="name"]').focus();
                return;
            }

            // If all validations pass, proceed with AJAX submission
            submitAddForm();
        });

        // Client-side validation for Edit Category Form
        $('#editCategoryForm').on('submit', function (e) {
            e.preventDefault();
            
            const categoryName = $('#edit_category_name').val().trim();

            // Validate category name
            if (!categoryName) {
                showAlert('Please enter category name.', 'error', 4000);
                $('#edit_category_name').focus();
                return;
            }

            if (categoryName.length < 2) {
                showAlert('Category name must be at least 2 characters long.', 'error', 4000);
                $('#edit_category_name').focus();
                return;
            }

            // If all validations pass, proceed with AJAX submission
            submitEditForm();
        });

        // AJAX submission functions
        function submitAddForm() {
            const form = $('#addCategoryForm');
            const formData = form.serialize();

            const submitBtn = form.find('button[type="submit"]');
            const originalHtml = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

            console.log('Submitting Add Category Form:', formData);

            $.ajax({
                url: 'ajax/category_crud.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    console.log('Add Category Response:', response);
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
                    console.error("Add Category AJAX Error:", status, error);
                    
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
                    submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Category');
                }
            });
        }

        function submitEditForm() {
            const form = $('#editCategoryForm');
            const formData = form.serialize();

            const submitBtn = form.find('button[type="submit"]');
            const originalHtml = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Updating...');

            console.log('Submitting Edit Category Form:', formData);

            $.ajax({
                url: 'ajax/category_crud.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    console.log('Edit Category Response:', response);
                    if (response.success) {
                        form.closest('.modal').modal('hide');
                        showAlert(response.message, 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showAlert(response.message, 'error', 7000);
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Edit Category AJAX Error:", status, error);
                    
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
                    submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Update Category');
                }
            });
        }

        function submitDeleteForm() {
            const form = $('#deleteCategoryForm');
            const formData = form.serialize();

            const submitBtn = form.find('#confirmDeleteBtn');
            const originalHtml = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Deleting...');

            console.log('Submitting Delete Category Form:', formData);

            $.ajax({
                url: 'ajax/category_crud.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {
                    console.log('Delete Category Response:', response);
                    if (response.success) {
                        form.closest('.modal').modal('hide');
                        showAlert(response.message, 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showAlert(response.message, 'error', 7000);
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Delete Category AJAX Error:", status, error);
                    
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
                    submitBtn.prop('disabled', false).html('Delete Category');
                }
            });
        }

        // Delete Form Handler
        $('#deleteCategoryForm').on('submit', function (e) {
            e.preventDefault();
            submitDeleteForm();
        });

        // ----------------------------------------------------
        // EDIT Button Handler
        // ----------------------------------------------------
        $('#categoryTable tbody').on('click', '.edit-btn', function () {
            const row = $(this).closest('tr');
            const rowData = categoryTable.row(row).data();
            const jsonString = rowData[3];

            try {
                const category = JSON.parse(jsonString);

                // Populate the edit form
                $('#edit_category_id').val(category.id);
                $('#edit_category_name').val(category.name);

            } catch (e) {
                console.error("Failed to parse category data JSON:", e);
                showAlert("Error loading category data for editing.", 'error');
            }
        });

        // ----------------------------------------------------
        // DELETE Button Handler
        // ----------------------------------------------------
        $('#categoryTable tbody').on('click', '.delete-btn', function () {
            const row = $(this).closest('tr');
            const rowData = categoryTable.row(row).data();
            const jsonString = rowData[3];

            try {
                const category = JSON.parse(jsonString);

                // Set the ID in the hidden input field of the delete form
                $('#delete_category_id').val(category.id);

                // Display the category name for confirmation
                $('#delete_category_name').text(category.name);

            } catch (e) {
                console.error("Failed to parse category data for deletion:", e);
                showAlert("Error preparing deletion details.", 'error');
            }
        });

        // Clear validation when modals are closed
        $('#addCategoryModal').on('hidden.bs.modal', function() {
            $('#addCategoryForm')[0].reset();
            $('.is-invalid').removeClass('is-invalid');
            $('.is-valid').removeClass('is-valid');
            $('.invalid-feedback').remove();
        });

        $('#editCategoryModal').on('hidden.bs.modal', function() {
            $('.is-invalid').removeClass('is-invalid');
            $('.is-valid').removeClass('is-valid');
            $('.invalid-feedback').remove();
        });
    });
</script>
</body>
</html>