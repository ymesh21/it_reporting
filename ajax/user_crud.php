<?php
// ajax/user_crud.php
require_once '../auth_check.php';
require_once '../config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and has Admin role
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if user has permission to manage users (Admin only)
if ($_SESSION['user_role'] != 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin privileges required.']);
    exit;
}

// Initialize response array
$response = ['success' => false, 'message' => ''];

try {
    // Get the action from POST data
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $response = addUser($pdo, $_POST);
            break;
            
        case 'edit':
            $response = editUser($pdo, $_POST);
            break;
            
        case 'get': // Make sure this matches the AJAX request
            $id = $_POST['id'] ?? 0;
            if ($id) {
                $response = getUser($pdo, $id);
            } else {
                $response = ['success' => false, 'message' => 'User ID is required'];
            }
            break;
            
        case 'delete':
            $response = deleteUser($pdo, $_POST['id']);
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Invalid action: ' . $action];
    }
    
} catch (PDOException $e) {
    $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
}

// Return JSON response
echo json_encode($response);

/**
 * Add a new user
 */
function addUser($pdo, $data) {
    // Validate required fields
    $required = ['firstname', 'lastname', 'email', 'password', 'role', 'sex', 'district_id'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => "Field '$field' is required"];
        }
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Email already exists'];
    }
    
    // Hash password
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Insert user
    $sql = "INSERT INTO users (firstname, lastname, email, password, role, sex, district_id, phone, position) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        trim($data['firstname']),
        trim($data['lastname']),
        trim($data['email']),
        $hashed_password,
        $data['role'],
        $data['sex'],
        $data['district_id'],
        trim($data['phone'] ?? ''),
        trim($data['position'] ?? '')
    ]);
    
    return ['success' => true, 'message' => 'User added successfully'];
}

/**
 * Edit an existing user
 */
function editUser($pdo, $data) {
    // Validate required fields
    $required = ['id', 'firstname', 'lastname', 'email', 'role', 'sex', 'district_id'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return ['success' => false, 'message' => "Field '$field' is required"];
        }
    }
    
    // Check if user exists
    $user = getUserById($pdo, $data['id']);
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    // Check if email already exists (excluding current user)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$data['email'], $data['id']]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Email already exists'];
    }
    
    // Build update query
    $sql = "UPDATE users 
            SET firstname = ?, lastname = ?, email = ?, role = ?, sex = ?, district_id = ?, phone = ?, position = ?";
    $params = [
        trim($data['firstname']),
        trim($data['lastname']),
        trim($data['email']),
        $data['role'],
        $data['sex'],
        $data['district_id'],
        trim($data['phone'] ?? ''),
        trim($data['position'] ?? '')
    ];
    
    // Update password if provided
    if (!empty($data['password'])) {
        $sql .= ", password = ?";
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
    }
    
    $sql .= " WHERE id = ?";
    $params[] = $data['id'];
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return ['success' => true, 'message' => 'User updated successfully'];
}

/**
 * Get user data
 */
function getUser($pdo, $id) {
    $user = getUserById($pdo, $id);
    
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    return ['success' => true, 'data' => $user];
}

/**
 * Delete a user
 */
function deleteUser($pdo, $id) {
    // Prevent user from deleting themselves
    if ($_SESSION['user_id'] == $id) {
        return ['success' => false, 'message' => 'You cannot delete your own account'];
    }
    
    // Check if user exists
    $user = getUserById($pdo, $id);
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    $sql = "DELETE FROM users WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'User deleted successfully'];
    } else {
        return ['success' => false, 'message' => 'User not found or already deleted'];
    }
}

/**
 * Helper function to get user by ID
 */
function getUserById($pdo, $id) {
    $sql = "SELECT u.*, w.name AS woreda_name 
            FROM users u 
            JOIN districts w ON u.district_id = w.id 
            WHERE u.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>