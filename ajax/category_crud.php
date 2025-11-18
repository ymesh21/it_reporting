<?php
// category_crud.php
require_once '../config.php';
require_once '../auth_check.php';

// Set header for JSON response
header('Content-Type: application/json');

// Check if user has permission to manage trainees (Zone or Woreda only)
$allowed_roles = ['Zone', 'Woreda'];
if (!in_array($_SESSION['user_role'], $allowed_roles)) {
    echo json_encode(['success' => false, 'message' => 'Access denied. Insufficient permissions.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    // Use filter_input for security/sanitization
    $category_name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $category_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    try {
        if ($action === 'add') {
            // --- C R E A T E ---
            if (empty($category_name)) {
                echo json_encode(['success' => false, 'message' => 'Category name is required.']);
                exit;
            }
            $stmt = $pdo->prepare("INSERT INTO training_categories (name) VALUES (?)");
            $stmt->execute([$category_name]);
            echo json_encode(['success' => true, 'message' => 'Category added successfully.']);

        } elseif ($action === 'edit') {
            // --- U P D A T E ---
            if (!$category_id || empty($category_name)) {
                echo json_encode(['success' => false, 'message' => 'Invalid ID or name.']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE training_categories SET name = ? WHERE id = ?");
            $stmt->execute([$category_name, $category_id]);
            echo json_encode(['success' => true, 'message' => 'Category updated successfully.']);

        } elseif ($action === 'delete') {
            // --- D E L E T E ---
            if (!$category_id) {
                echo json_encode(['success' => false, 'message' => 'Invalid ID for deletion.']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM training_categories WHERE id = ?");
            $stmt->execute([$category_id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Category deleted successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Category not found or could not be deleted.']);
            }

        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
        }

    } catch (PDOException $e) {
        http_response_code(500);

        // TEMPORARY DEBUG: Return the exact PDO message
        echo json_encode([
            'success' => false,
            'message' => 'Database operation failed.',
            'debug_error' => $e->getMessage(),
            'error_code' => $e->getCode()
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);

        // TEMPORARY DEBUG: Return the exact general error message
        echo json_encode([
            'success' => false,
            'message' => 'An unexpected server error occurred.',
            'debug_error' => $e->getMessage()
        ]);
        exit;
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Bad request.']);
exit;