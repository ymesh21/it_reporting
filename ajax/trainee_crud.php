<?php
// ajax/trainee_crud.php
require_once '../auth_check.php';
require_once '../config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and has appropriate role
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if user has permission to manage trainees (Zone or Woreda only)
$allowed_roles = ['Zone', 'Woreda'];
if (!in_array($_SESSION['user_role'], $allowed_roles)) {
    echo json_encode(['success' => false, 'message' => 'Access denied. Insufficient permissions.']);
    exit;
}

// Initialize response array
$response = ['success' => false, 'message' => ''];

try {
    // Get the action from POST data
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $response = addTrainee($pdo, $_POST);
            break;

        case 'edit':
            $response = editTrainee($pdo, $_POST);
            break;

        case 'get':
            $response = getTrainee($pdo, $_POST['id']);
            break;

        case 'delete':
            $response = deleteTrainee($pdo, $_POST['id']);
            break;

        default:
            $response = ['success' => false, 'message' => 'Invalid action'];
    }

} catch (PDOException $e) {
    $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
}

// Return JSON response
echo json_encode($response);

/**
 * Add a new trainee
 */
function addTrainee($pdo, $data)
{
    // Validate required fields
    $required = ['fullname', 'session_id', 'gender'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => "Field '$field' is required"];
        }
    }

    // Check if session exists and user has permission
    if (!hasSessionAccess($pdo, $data['session_id'])) {
        return ['success' => false, 'message' => 'Access denied to this session'];
    }

    // Insert trainee
    $sql = "INSERT INTO trainees (fullname, gender, phone, organization, session_id) 
            VALUES (?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        trim($data['fullname']),
        $data['gender'],
        trim($data['phone'] ?? ''),
        trim($data['organization'] ?? ''),
        $data['session_id']
    ]);

    return ['success' => true, 'message' => 'Trainee added successfully'];
}

/**
 * Edit an existing trainee
 */
function editTrainee($pdo, $data)
{
    // Validate required fields
    $required = ['id', 'fullname', 'session_id', 'gender'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => "Field '$field' is required"];
        }
    }

    // Check if trainee exists and user has permission
    if (!hasTraineeAccess($pdo, $data['id'])) {
        return ['success' => false, 'message' => 'Access denied to this trainee'];
    }

    // Check if session exists and user has permission
    if (!hasSessionAccess($pdo, $data['session_id'])) {
        return ['success' => false, 'message' => 'Access denied to this session'];
    }

    // Update trainee
    $sql = "UPDATE trainees 
            SET fullname = ?, gender = ?, phone = ?, organization = ?, session_id = ? 
            WHERE id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        trim($data['fullname']),
        $data['gender'],
        trim($data['phone'] ?? ''),
        trim($data['organization'] ?? ''),
        $data['session_id'],
        $data['id']
    ]);

    return ['success' => true, 'message' => 'Trainee updated successfully'];
}

/**
 * Get trainee data
 */
function getTrainee($pdo, $id)
{
    if (!hasTraineeAccess($pdo, $id)) {
        return ['success' => false, 'message' => 'Access denied to this trainee'];
    }

    $sql = "SELECT t.*, ts.title as session_title, w.name as woreda_name 
            FROM trainees t 
            JOIN training_sessions ts ON t.session_id = ts.id 
            JOIN districts w ON ts.district_id = w.id 
            WHERE t.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $trainee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trainee) {
        return ['success' => false, 'message' => 'Trainee not found'];
    }

    return ['success' => true, 'data' => $trainee];
}

/**
 * Delete a trainee
 */
function deleteTrainee($pdo, $id)
{
    if (!hasTraineeAccess($pdo, $id)) {
        return ['success' => false, 'message' => 'Access denied to this trainee'];
    }

    $sql = "DELETE FROM trainees WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'Trainee deleted successfully'];
    } else {
        return ['success' => false, 'message' => 'Trainee not found or already deleted'];
    }
}

/**
 * Check if user has access to a specific session
 */
function hasSessionAccess($pdo, $session_id)
{
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['user_role'];

    // Zone users have access to all sessions
    if ($role == 'Zone') {
        return true;
    }

    // For Woreda users, check if session belongs to their woreda
    $sql = "SELECT ts.id 
            FROM training_sessions ts 
            JOIN users u ON ts.district_id = u.district_id 
            WHERE ts.id = ? AND u.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$session_id, $user_id]);

    return $stmt->fetch() !== false;
}

/**
 * Check if user has access to a specific trainee
 */
function hasTraineeAccess($pdo, $trainee_id)
{
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['user_role'];

    // Zone users have access to all trainees
    if ($role == 'Zone') {
        return true;
    }

    // For Woreda users, check if trainee's session belongs to their woreda
    $sql = "SELECT t.id 
            FROM trainees t 
            JOIN training_sessions ts ON t.session_id = ts.id 
            JOIN users u ON ts.district_id = u.district_id 
            WHERE t.id = ? AND u.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$trainee_id, $user_id]);

    return $stmt->fetch() !== false;
}
?>