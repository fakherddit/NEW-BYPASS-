<?php
// validate.php - Updated for Global Keys Support

// Get POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

$key = isset($data['key']) ? trim($data['key']) : '';
$hwid = isset($data['hwid']) ? trim($data['hwid']) : '';

if (empty($key) || empty($hwid)) {
    echo json_encode(['valid' => false, 'message' => 'Missing key or HWID']);
    exit();
}

// Check validation setting
$stmt = $pdo->query("SELECT value FROM server_settings WHERE key = 'key_validation_enabled' LIMIT 1");
if ($stmt && $stmt->fetchColumn() === '0') {
    echo json_encode(['valid' => false, 'message' => 'Maintenance Mode']);
    exit();
}

// 1. Fetch Key Details
$stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ?");
$stmt->execute([$key]);
$license = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$license) {
    echo json_encode(['valid' => false, 'message' => 'Key not found']);
    exit();
}

// 2. Check Status & Expiry
if ($license['status'] !== 'active') {
    echo json_encode(['valid' => false, 'message' => 'Key is ' . $license['status']]);
    exit();
}

if (strtotime($license['expiry_date']) < time()) {
    echo json_encode(['valid' => false, 'message' => 'Key expired']);
    exit();
}

// 3. Check HWID Logic based on Type
$is_global = (strpos($license['key_type'], 'global') === 0);
$valid = false;
$msg = "Success";

if ($is_global) {
    // Global Key: Always valid, no binding.
    $valid = true;
    $msg = "Global Key Active";
} else {
    // Standard Key: HWID Locking
    if (empty($license['hwid'])) {
        // First use - Bind HWID
        $update = $pdo->prepare("UPDATE licenses SET hwid = ? WHERE id = ?");
        $update->execute([$hwid, $license['id']]);
        $valid = true;
        $msg = "Key linked to device";
    } elseif ($license['hwid'] === $hwid) {
        // Matching HWID
        $valid = true;
    } else {
        // Mismatch
        $valid = false;
        $msg = "Key bound to another device";
    }
}

// 4. Update Last Used & Respond
if ($valid) {
    $pdo->prepare("UPDATE licenses SET last_used = NOW() WHERE id = ?")->execute([$license['id']]);
    
    echo json_encode([
        'valid' => true,
        'message' => $msg,
        'expiry' => $license['expiry_date'],
        'type' => $license['key_type']
    ]);
} else {
    echo json_encode([
        'valid' => false,
        'message' => $msg
    ]);
}
?>
