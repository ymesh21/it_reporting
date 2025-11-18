<?php
// ajax/maintenance_crud.php
require_once '../config.php';
require_once '../auth_check.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$response = ['success' => false, 'message' => 'Unknown error occurred.'];

try {
    $action = $_POST['action'] ?? '';
    $user_role = $_SESSION['user_role'];
    $user_id = $_SESSION['user_id'];
    $user_district_id = $_SESSION['user_district_id'] ?? null;

    switch ($action) {
        case 'add':
            if ($user_role == 'Zone') {
                $response['message'] = 'Zone users cannot add maintenance records.';
                break;
            }

            $device_id = $_POST['device_id'];
            $issue_description = trim($_POST['issue_description']);
            $action_taken = trim($_POST['action_taken'] ?? '');
            $status = $_POST['status'];
            $maintenance_date = $_POST['maintenance_date'];
            $remarks = trim($_POST['remarks'] ?? '');

            // Validate device belongs to user's district
            $stmt = $pdo->prepare("SELECT district_id FROM devices WHERE id = ?");
            $stmt->execute([$device_id]);
            $device = $stmt->fetch();

            if (!$device) {
                $response['message'] = 'Device not found.';
                break;
            }

            if ($user_role == 'Woreda' && $device['district_id'] != $user_district_id) {
                $response['message'] = 'You can only add maintenance records for devices in your assigned district.';
                break;
            }

            $stmt = $pdo->prepare("INSERT INTO maintenances (device_id, user_id, issue_description, action_taken, status, maintenance_date, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$device_id, $user_id, $issue_description, $action_taken, $status, $maintenance_date, $remarks])) {
                $response['success'] = true;
                $response['message'] = 'Maintenance record added successfully.';
            } else {
                $response['message'] = 'Failed to add maintenance record.';
            }
            break;

        case 'edit':
            if ($user_role == 'Zone') {
                $response['message'] = 'Zone users cannot edit maintenance records.';
                break;
            }

            $id = $_POST['id'];
            $device_id = $_POST['device_id'];
            $issue_description = trim($_POST['issue_description']);
            $action_taken = trim($_POST['action_taken'] ?? '');
            $status = $_POST['status'];
            $maintenance_date = $_POST['maintenance_date'];
            $remarks = trim($_POST['remarks'] ?? '');

            // Check if user has permission to edit this maintenance record
            $stmt = $pdo->prepare("SELECT m.device_id, d.district_id FROM maintenances m JOIN devices d ON m.device_id = d.id WHERE m.id = ?");
            $stmt->execute([$id]);
            $maintenance = $stmt->fetch();

            if (!$maintenance) {
                $response['message'] = 'Maintenance record not found.';
                break;
            }

            if ($user_role == 'Woreda' && $maintenance['district_id'] != $user_district_id) {
                $response['message'] = 'You can only edit maintenance records for devices in your assigned district.';
                break;
            }

            // Validate new device belongs to user's district
            $stmt = $pdo->prepare("SELECT district_id FROM devices WHERE id = ?");
            $stmt->execute([$device_id]);
            $device = $stmt->fetch();

            if (!$device) {
                $response['message'] = 'Device not found.';
                break;
            }

            if ($user_role == 'Woreda' && $device['district_id'] != $user_district_id) {
                $response['message'] = 'You can only assign maintenance to devices in your assigned district.';
                break;
            }

            $stmt = $pdo->prepare("UPDATE maintenances SET device_id = ?, issue_description = ?, action_taken = ?, status = ?, maintenance_date = ?, remarks = ? WHERE id = ?");
            if ($stmt->execute([$device_id, $issue_description, $action_taken, $status, $maintenance_date, $remarks, $id])) {
                $response['success'] = true;
                $response['message'] = 'Maintenance record updated successfully.';
            } else {
                $response['message'] = 'Failed to update maintenance record.';
            }
            break;

        case 'delete':
            if ($user_role == 'Zone') {
                $response['message'] = 'Zone users cannot delete maintenance records.';
                break;
            }

            $id = $_POST['id'];

            // Check if user has permission to delete this maintenance record
            $stmt = $pdo->prepare("SELECT m.device_id, d.district_id FROM maintenances m JOIN devices d ON m.device_id = d.id WHERE m.id = ?");
            $stmt->execute([$id]);
            $maintenance = $stmt->fetch();

            if (!$maintenance) {
                $response['message'] = 'Maintenance record not found.';
                break;
            }

            if ($user_role == 'Woreda' && $maintenance['district_id'] != $user_district_id) {
                $response['message'] = 'You can only delete maintenance records for devices in your assigned district.';
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM maintenances WHERE id = ?");
            if ($stmt->execute([$id])) {
                $response['success'] = true;
                $response['message'] = 'Maintenance record deleted successfully.';
            } else {
                $response['message'] = 'Failed to delete maintenance record.';
            }
            break;

        default:
            $response['message'] = 'Invalid action.';
            break;
    }

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>