<?php
/**
 * Organization API: POST Settings (Update Profile, Logo, Banner)
 * Endpoint: /config/API/endpoints/index.php?action=update_org_settings
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization login required']);
    if ($isDirectApiCall) exit;
    return;
}

$orgId = (int)$_SESSION['org_id'];

try {
    $orgName     = trim($_POST['OrgName'] ?? '');
    $adviser     = trim($_POST['Adviser'] ?? '');
    $email       = trim($_POST['Email'] ?? '');
    $description = trim($_POST['Description'] ?? '');

    if (empty($orgName)) {
        throw new Exception('Organization Name is required.');
    }

    $uploadDir = __DIR__ . '/../../../../assets/uploads/orgs/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $newLogoPath   = null;
    $newBannerPath = null;

    // Handle Logo upload
    if (isset($_FILES['OrgPicture']) && $_FILES['OrgPicture']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['OrgPicture']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $filename = 'logo_' . $orgId . '_' . time() . '.' . $ext;
            $target = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['OrgPicture']['tmp_name'], $target)) {
                $newLogoPath = 'assets/uploads/orgs/' . $filename;
            }
        }
    }

    // Handle Banner upload
    if (isset($_FILES['OrgBanner']) && $_FILES['OrgBanner']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['OrgBanner']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $filename = 'banner_' . $orgId . '_' . time() . '.' . $ext;
            $target = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['OrgBanner']['tmp_name'], $target)) {
                $newBannerPath = 'assets/uploads/orgs/' . $filename;
            }
        }
    }

    // Build update SQL query
    $sql = "UPDATE organization SET OrgName = ?, Adviser = ?, email = ?, Description = ?";
    $params = [$orgName, $adviser, $email, $description];
    $types = "ssss";

    if ($newLogoPath !== null) {
        $sql .= ", OrgPicture = ?";
        $params[] = $newLogoPath;
        $types .= "s";
    }

    if ($newBannerPath !== null) {
        $sql .= ", OrgBanner = ?";
        $params[] = $newBannerPath;
        $types .= "s";
    }

    $sql .= " WHERE OrgId = ?";
    $params[] = $orgId;
    $types .= "i";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update organization settings in database.');
    }
    $stmt->close();

    // Update Session Variables
    $_SESSION['org_name'] = $orgName;
    if ($newLogoPath !== null) {
        $_SESSION['org_logo'] = $newLogoPath;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Organization profile updated successfully!',
        'org_pic' => $newLogoPath
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

if ($isDirectApiCall) exit;
?>
