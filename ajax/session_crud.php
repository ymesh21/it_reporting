<?php
// session_crud.php
require_once '../config.php';
require_once '../auth_check.php';

header('Content-Type: application/json');

// --- Central Authorization Check ---
if (!is_logged_in() || (!has_role('Admin') && !has_role('Zone') && !has_role('district'))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];
$user_district_id = null;

// Determine district User's district ID if applicable
if ($user_role == 'district') {
    try {
        $stmt_district = $pdo->prepare("SELECT district_id FROM users WHERE id = ?");
        $stmt_district->execute([$user_id]);
        $user_district_id = $stmt_district->fetchColumn();
    } catch (PDOException $e) {
        // Log error
    }
}

// --- Function to check if a session belongs to the user's district ---
function is_session_owned($pdo, $session_id, $district_id)
{
    if (!$session_id || !$district_id)
        return false;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM training_sessions WHERE id = ? AND district_id = ?");
    $stmt->execute([$session_id, $district_id]);
    return $stmt->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $district_id = filter_input(INPUT_POST, 'district_id', FILTER_VALIDATE_INT);

        // --- ENFORCE district AUTHORIZATION FOR EDIT/DELETE ---
        if (($action === 'edit' || $action === 'delete') && $user_role === 'district') {
            if (!is_session_owned($pdo, $id, $user_district_id)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'You can only manage sessions within your assigned district.']);
                exit;
            }
        }

        // --- ENFORCE district AUTHORIZATION FOR ADD ---
        if ($action === 'add' && $user_role === 'district') {
    // Check if the district ID submitted matches the district user's assigned district ID
    if ($district_id != $user_district_id) { 
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'district users can only create sessions in their assigned district.']);
        exit;
    }
}
        // Admin and Zone users proceed freely

        // --- Input Validation (Common Fields) ---
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
        $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
        $created_by = filter_input(INPUT_POST, 'created_by', FILTER_VALIDATE_INT);
        $start_date = filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_STRING);
        $end_date = filter_input(INPUT_POST, 'end_date', FILTER_SANITIZE_STRING);
        $budget = filter_input(INPUT_POST, 'budget', FILTER_VALIDATE_FLOAT);

        if ($action === 'add') {
            // --- C R E A T E ---
            // Check required fields, including the new created_by field
            if (empty($title) || !$district_id || !$category_id || empty($start_date) || empty($end_date) || !$created_by) {
                echo json_encode(['success' => false, 'message' => 'Required fields (including creator ID) missing.']);
                exit;
            }
            $sql = "INSERT INTO training_sessions 
                    (title, district_id, category_id, start_date, end_date, budget, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)"; // <-- SQL updated with created_by
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $title, 
                $district_id, 
                $category_id, 
                $start_date, 
                $end_date, 
                $budget, 
                $created_by
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Session created.']);

        } elseif ($action === 'edit') {
            // --- U P D A T E ---
            if (!$id || empty($title) || !$district_id || !$category_id || empty($start_date) || empty($end_date)) {
                echo json_encode(['success' => false, 'message' => 'Required fields missing for edit.']);
                exit;
            }
            $sql = "UPDATE training_sessions SET title=?, district_id=?, category_id=?, start_date=?, end_date=?, budget=? WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$title, $district_id, $category_id, $start_date, $end_date, $budget, $id]);
            echo json_encode(['success' => true, 'message' => 'Session updated.']);

        } elseif ($action === 'delete') {
            // --- D E L E T E ---
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Invalid ID for deletion.']);
                exit;
            }

            // 1. Delete associated trainees first (Cascading delete)
            $stmt_trainees = $pdo->prepare("DELETE FROM trainees WHERE session_id = ?");
            $stmt_trainees->execute([$id]);

            // 2. Delete the session
            $stmt_session = $pdo->prepare("DELETE FROM training_sessions WHERE id = ?");
            $stmt_session->execute([$id]);

            if ($stmt_session->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Session and linked trainees deleted.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Session not found.']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Bad request.']);
exit;