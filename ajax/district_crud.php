<?php
// ajax/district_crud.php
require_once '../config.php';
require_once '../auth_check.php';

// Set header for JSON response
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// --- Security Check ---
if (!is_logged_in()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
    exit;
}

// Check if user has Zone role
if ($_SESSION['user_role'] !== 'Zone') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Zone privileges required.']);
    exit;
}

// Check if it's a POST request with action
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method or missing action.']);
    exit;
}

$action = $_POST['action'];

try {
    if ($action === 'add') {
        // --- C R E A T E ---
        $district_name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING) ?? '');
        $district_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING) ?? '';
        $parent_id = filter_input(INPUT_POST, 'parent_id', FILTER_VALIDATE_INT);

        // Debug logging
        error_log("ADD Action - Name: '$district_name', Type: '$district_type', Parent ID: '$parent_id'");

        // Validation for ADD action
        if (empty($district_name)) {
            echo json_encode(['success' => false, 'message' => 'District name is required.']);
            exit;
        }

        if (!in_array($district_type, ['Zone', 'Woreda'])) {
            echo json_encode(['success' => false, 'message' => 'Please select a valid district type (Zone or Woreda).']);
            exit;
        }
        
        // If the type is Woreda, a parent must be provided
        if ($district_type === 'Woreda') {
            if (!$parent_id) {
                echo json_encode(['success' => false, 'message' => 'Woredas must be assigned to a Parent Zone.']);
                exit;
            }

            // Verify the parent exists and is a Zone
            $stmt = $pdo->prepare("SELECT id, name, type FROM districts WHERE id = ? AND type = 'Zone'");
            $stmt->execute([$parent_id]);
            $parent = $stmt->fetch();
            
            if (!$parent) {
                echo json_encode(['success' => false, 'message' => 'Selected parent zone does not exist or is not a valid Zone.']);
                exit;
            }
        }

        // Set parent_id to NULL if the district is a Zone
        $parent_id_for_db = ($district_type === 'Zone') ? NULL : $parent_id;

        // Check if district name already exists (case-insensitive)
        $stmt = $pdo->prepare("SELECT id, name FROM districts WHERE LOWER(name) = LOWER(?)");
        $stmt->execute([$district_name]);
        $existingDistrict = $stmt->fetch();
        
        if ($existingDistrict) {
            echo json_encode(['success' => false, 'message' => "District name '{$existingDistrict['name']}' already exists."]);
            exit;
        }
        
        // Insert the new district
        $sql = "INSERT INTO districts (name, type, parent_id) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$district_name, $district_type, $parent_id_for_db]);
        
        echo json_encode(['success' => true, 'message' => "District '$district_name' added successfully."]);

    } elseif ($action === 'edit') {
        // --- U P D A T E ---
        $district_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $district_name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING) ?? '');
        $district_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING) ?? '';
        $parent_id = filter_input(INPUT_POST, 'parent_id', FILTER_VALIDATE_INT);

        if (!$district_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid district ID for update.']);
            exit;
        }

        // Validation for EDIT action
        if (empty($district_name)) {
            echo json_encode(['success' => false, 'message' => 'District name is required.']);
            exit;
        }

        if (!in_array($district_type, ['Zone', 'Woreda'])) {
            echo json_encode(['success' => false, 'message' => 'Please select a valid district type (Zone or Woreda).']);
            exit;
        }
        
        // If the type is Woreda, a parent must be provided
        if ($district_type === 'Woreda') {
            if (!$parent_id) {
                echo json_encode(['success' => false, 'message' => 'Woredas must be assigned to a Parent Zone.']);
                exit;
            }

            // Verify the parent exists and is a Zone
            $stmt = $pdo->prepare("SELECT id, name, type FROM districts WHERE id = ? AND type = 'Zone'");
            $stmt->execute([$parent_id]);
            $parent = $stmt->fetch();
            
            if (!$parent) {
                echo json_encode(['success' => false, 'message' => 'Selected parent zone does not exist or is not a valid Zone.']);
                exit;
            }
        }

        // Set parent_id to NULL if the district is a Zone
        $parent_id_for_db = ($district_type === 'Zone') ? NULL : $parent_id;

        // Check if district exists
        $stmt = $pdo->prepare("SELECT id, name, type FROM districts WHERE id = ?");
        $stmt->execute([$district_id]);
        $existingDistrict = $stmt->fetch();
        
        if (!$existingDistrict) {
            echo json_encode(['success' => false, 'message' => 'District not found. It may have been deleted.']);
            exit;
        }
        
        // Check if name already exists (excluding current district, case-insensitive)
        $stmt = $pdo->prepare("SELECT id, name FROM districts WHERE LOWER(name) = LOWER(?) AND id != ?");
        $stmt->execute([$district_name, $district_id]);
        $duplicateDistrict = $stmt->fetch();
        
        if ($duplicateDistrict) {
            echo json_encode(['success' => false, 'message' => "District name '{$duplicateDistrict['name']}' already exists."]);
            exit;
        }
        
        // Update the district
        $sql = "UPDATE districts SET name = ?, type = ?, parent_id = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$district_name, $district_type, $parent_id_for_db, $district_id]);
        
        echo json_encode(['success' => true, 'message' => "District '$district_name' updated successfully."]);

    } elseif ($action === 'delete') {
        // --- D E L E T E ---
        $district_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if (!$district_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid district ID for deletion.']);
            exit;
        }
        
        // Check if district exists
        $stmt = $pdo->prepare("SELECT id, type, name FROM districts WHERE id = ?");
        $stmt->execute([$district_id]);
        $district = $stmt->fetch();
        
        if (!$district) {
            echo json_encode(['success' => false, 'message' => 'District not found. It may have been already deleted.']);
            exit;
        }
        
        // Check if this district is a parent to other districts (both Zones and Woredas)
        $stmt = $pdo->prepare("SELECT id, name, type FROM districts WHERE parent_id = ?");
        $stmt->execute([$district_id]);
        $childDistricts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($childDistricts)) {
            $childCount = count($childDistricts);
            $childTypes = array_count_values(array_column($childDistricts, 'type'));
            
            $childDescription = [];
            if (isset($childTypes['Zone'])) {
                $childDescription[] = $childTypes['Zone'] . ' Zone(s)';
            }
            if (isset($childTypes['Woreda'])) {
                $childDescription[] = $childTypes['Woreda'] . ' Woreda(s)';
            }
            
            $childList = implode(' and ', $childDescription);
            echo json_encode([
                'success' => false, 
                'message' => "Cannot delete {$district['type']} '{$district['name']}'. It is a parent to {$childList}. Please reassign or delete the child districts first."
            ]);
            exit;
        }
        
        // Additional checks before deletion based on district type
        if ($district['type'] === 'Zone') {
            // Check if zone has users assigned to its potential child woredas
            $stmt = $pdo->prepare("SELECT COUNT(*) as user_count FROM users WHERE district_id IN (SELECT id FROM districts WHERE parent_id = ?)");
            $stmt->execute([$district_id]);
            $userResult = $stmt->fetch();
            if ($userResult['user_count'] > 0) {
                echo json_encode(['success' => false, 'message' => "Cannot delete Zone '{$district['name']}'. It has {$userResult['user_count']} user(s) assigned to its Woredas. Please reassign the users first."]);
                exit;
            }
            
            // Check if zone has training sessions assigned to its potential child woredas
            $stmt = $pdo->prepare("SELECT COUNT(*) as session_count FROM training_sessions WHERE district_id IN (SELECT id FROM districts WHERE parent_id = ?)");
            $stmt->execute([$district_id]);
            $sessionResult = $stmt->fetch();
            if ($sessionResult['session_count'] > 0) {
                echo json_encode(['success' => false, 'message' => "Cannot delete Zone '{$district['name']}'. It has {$sessionResult['session_count']} training session(s) assigned to its Woredas. Please reassign the training sessions first."]);
                exit;
            }
        } else {
            // For Woredas, check if used by users
            $stmt = $pdo->prepare("SELECT COUNT(*) as user_count FROM users WHERE district_id = ?");
            $stmt->execute([$district_id]);
            $userResult = $stmt->fetch();
            if ($userResult['user_count'] > 0) {
                echo json_encode(['success' => false, 'message' => "Cannot delete Woreda '{$district['name']}'. It has {$userResult['user_count']} user(s) assigned to it. Please reassign the users first."]);
                exit;
            }
            
            // Check if used by training sessions
            $stmt = $pdo->prepare("SELECT COUNT(*) as session_count FROM training_sessions WHERE district_id = ?");
            $stmt->execute([$district_id]);
            $sessionResult = $stmt->fetch();
            if ($sessionResult['session_count'] > 0) {
                echo json_encode(['success' => false, 'message' => "Cannot delete Woreda '{$district['name']}'. It has {$sessionResult['session_count']} training session(s) assigned to it. Please reassign the training sessions first."]);
                exit;
            }
        }
        
        // If all checks pass, proceed with deletion
        $stmt = $pdo->prepare("DELETE FROM districts WHERE id = ?");
        $stmt->execute([$district_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => "District '{$district['name']}' deleted successfully."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'District could not be deleted. It may have been already removed.']);
        }
        
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
    }
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    http_response_code(500);
    
    // Handle constraint violations
    if ($e->getCode() === '23000') {
        echo json_encode(['success' => false, 'message' => 'Cannot delete this District because it is currently linked to users, training sessions, or is a parent to another Woreda/Zone.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()]);
}