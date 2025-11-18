<?php
// ajax/device_crud.php
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
                $response['message'] = 'Zone users cannot add devices.';
                break;
            }

            $device_code = trim($_POST['device_code']);
            $name = trim($_POST['name']);
            $brand = trim($_POST['brand']);
            $model = trim($_POST['model']);
            $serial_number = trim($_POST['serial_number'] ?? '');
            $device_type = trim($_POST['device_type']);
            $description = trim($_POST['description'] ?? '');
            // Use the current user's district_id
            // Get district_id from the form submission (not from user session)
            $district_id = $_POST['district_id'] ?? null;

            if (!$district_id) {
                $response['message'] = 'Please select a district.';
                break;
            }

            // For Woreda users, verify they are only using their assigned district
            if ($user_role == 'Woreda') {
                if ($district_id != $user_district_id) {
                    $response['message'] = 'You can only add devices to your assigned district.';
                    break;
                }
            }

            // For Zone users, verify the district is under their zone
            if ($user_role == 'Zone' && $user_district_id) {
                $stmt = $pdo->prepare("SELECT id FROM districts WHERE id = ? AND parent_id = ?");
                $stmt->execute([$district_id, $user_district_id]);
                if (!$stmt->fetch()) {
                    $response['message'] = 'You can only add devices to districts under your zone.';
                    break;
                }
            }

            // Check if device code already exists
            $stmt = $pdo->prepare("SELECT id FROM devices WHERE device_code = ?");
            $stmt->execute([$device_code]);
            if ($stmt->fetch()) {
                $response['message'] = 'Device code already exists.';
                break;
            }

            $stmt = $pdo->prepare("INSERT INTO devices (device_code, name, brand, model, serial_number, device_type, description, district_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$device_code, $name, $brand, $model, $serial_number, $device_type, $description, $district_id])) {
                $response['success'] = true;
                $response['message'] = 'Device added successfully.';
            } else {
                $response['message'] = 'Failed to add device.';
            }
            break;

        case 'edit':
            if ($user_role == 'Zone') {
                $response['message'] = 'Zone users cannot edit devices.';
                break;
            }

            $id = $_POST['id'];
            $device_code = trim($_POST['device_code']);
            $name = trim($_POST['name']);
            $brand = trim($_POST['brand']);
            $model = trim($_POST['model']);
            $serial_number = trim($_POST['serial_number'] ?? '');
            $device_type = trim($_POST['device_type']);
            $description = trim($_POST['description'] ?? '');
            $district_id = $_POST['district_id'] ?? null;

            if (!$district_id) {
                $response['message'] = 'Please select a district.';
                break;
            }

            // Check if user has permission to edit this device
            $stmt = $pdo->prepare("SELECT district_id FROM devices WHERE id = ?");
            $stmt->execute([$id]);
            $device = $stmt->fetch();

            if (!$device) {
                $response['message'] = 'Device not found.';
                break;
            }

            // For Woreda users, verify they are only editing devices in their district
            if ($user_role == 'Woreda') {
                if ($device['district_id'] != $user_district_id) {
                    $response['message'] = 'You can only edit devices in your assigned district.';
                    break;
                }
                // Also verify they are not changing the district
                if ($district_id != $user_district_id) {
                    $response['message'] = 'You cannot change the district of a device.';
                    break;
                }
            }

            // For Zone users, verify the new district is under their zone
            if ($user_role == 'Zone' && $user_district_id) {
                $stmt = $pdo->prepare("SELECT id FROM districts WHERE id = ? AND parent_id = ?");
                $stmt->execute([$district_id, $user_district_id]);
                if (!$stmt->fetch()) {
                    $response['message'] = 'You can only assign devices to districts under your zone.';
                    break;
                }
            }

            // Check if device code already exists (excluding current device)
            $stmt = $pdo->prepare("SELECT id FROM devices WHERE device_code = ? AND id != ?");
            $stmt->execute([$device_code, $id]);
            if ($stmt->fetch()) {
                $response['message'] = 'Device code already exists.';
                break;
            }

            $stmt = $pdo->prepare("UPDATE devices SET device_code = ?, name = ?, brand = ?, model = ?, serial_number = ?, device_type = ?, description = ?, district_id = ? WHERE id = ?");
            if ($stmt->execute([$device_code, $name, $brand, $model, $serial_number, $device_type, $description, $district_id, $id])) {
                $response['success'] = true;
                $response['message'] = 'Device updated successfully.';
            } else {
                $response['message'] = 'Failed to update device.';
            }
            break;

        case 'delete':
            if ($user_role == 'Zone') {
                $response['message'] = 'Zone users cannot delete devices.';
                break;
            }

            $id = $_POST['id'];

            // Check if user has permission to delete this device
            $stmt = $pdo->prepare("SELECT district_id FROM devices WHERE id = ?");
            $stmt->execute([$id]);
            $device = $stmt->fetch();

            if (!$device) {
                $response['message'] = 'Device not found.';
                break;
            }

            if ($user_role == 'Woreda' && $device['district_id'] != $user_district_id) {
                $response['message'] = 'You can only delete devices in your assigned district.';
                break;
            }

            // Check if device has maintenance records
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM maintenances WHERE device_id = ?");
            $stmt->execute([$id]);
            $maintenance_count = $stmt->fetchColumn();

            if ($maintenance_count > 0) {
                $response['message'] = 'Cannot delete device with existing maintenance records.';
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM devices WHERE id = ?");
            if ($stmt->execute([$id])) {
                $response['success'] = true;
                $response['message'] = 'Device deleted successfully.';
            } else {
                $response['message'] = 'Failed to delete device.';
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