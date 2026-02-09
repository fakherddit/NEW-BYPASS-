<?php
// telegram_bot.php - Updated with Full Management Features
$telegram_token = "8216359066:AAEt2GFGgTBp3hh_znnJagH3h1nN5A_XQf0";
$admin_chat_id = 7210704553;

$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit();

$message = $update['message'] ?? null;
$callback_query = $update['callback_query'] ?? null;

// Handle callbacks
if ($callback_query) {
    $chat_id = $callback_query['message']['chat']['id'];
    $data = $callback_query['data'];
    $user_id = $callback_query['from']['id'];
    $msg_id = $callback_query['message']['message_id'];

    if ($user_id != $admin_chat_id) {
        sendMessage($chat_id, "⛔ Unauthorized", $telegram_token);
        answerCallbackQuery($callback_query['id'], $telegram_token);
        exit();
    }
    
    // Key Management Callbacks
    if (strpos($data, 'ban_') === 0) {
        $key = substr($data, 4);
        updateKeyStatus($chat_id, $key, 'banned', $telegram_token, $pdo, $msg_id);
    } elseif (strpos($data, 'unban_') === 0) {
        $key = substr($data, 6);
        updateKeyStatus($chat_id, $key, 'active', $telegram_token, $pdo, $msg_id);
    } elseif (strpos($data, 'reset_') === 0) {
        $key = substr($data, 6);
        resetKeyHash($chat_id, $key, $telegram_token, $pdo, $msg_id);
    } elseif (strpos($data, 'del_') === 0) {
        $key = substr($data, 4);
        deleteKey($chat_id, $key, $telegram_token, $pdo, $msg_id);
    } 
    // Generator Callbacks
    elseif (strpos($data, 'gen_') === 0) {
        list($action, $count, $days) = explode('_', $data);
        generateKeys($chat_id, $count, $days, 'standard', $telegram_token, $pdo);
    } elseif (strpos($data, 'global_') === 0) {
        list($action, $type) = explode('_', $data);
        generateGlobalKey($chat_id, $type, $telegram_token, $pdo);
    } 
    // Server Control Callbacks
    elseif ($data === 'server_toggle') {
        toggleServer($chat_id, $telegram_token, $pdo);
    } elseif ($data === 'validation_toggle') {
        toggleValidation($chat_id, $telegram_token, $pdo);
    } elseif ($data === 'creation_toggle') {
        toggleCreation($chat_id, $telegram_token, $pdo);
    } elseif ($data === 'delete_expired') {
        deleteExpiredKeys($chat_id, $telegram_token, $pdo);
    }
    
    answerCallbackQuery($callback_query['id'], $telegram_token);
    exit();
}

// Handle messages
if ($message) {
    $chat_id = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $user_id = $message['from']['id'];
    
    if ($user_id != $admin_chat_id) {
        sendMessage($chat_id, "⛔ Unauthorized", $telegram_token);
        exit();
    }
    
    // Command Parsing
    $parts = explode(' ', $text);
    $command = strtolower($parts[0]);
    $args = array_slice($parts, 1);
    
    switch ($command) {
        case '/start': sendMainMenu($chat_id, $telegram_token); break;
        case '/generate': sendGenerateMenu($chat_id, $telegram_token); break;
        case '/global': sendGlobalKeyMenu($chat_id, $telegram_token); break;
        case '/control': sendControlMenu($chat_id, $telegram_token, $pdo); break;
        case '/stats': sendStats($chat_id, $telegram_token, $pdo); break;
        case '/list': listActiveKeys($chat_id, $telegram_token, $pdo); break;
        
        case '/ban': 
            if (isset($args[0])) updateKeyStatus($chat_id, $args[0], 'banned', $telegram_token, $pdo); 
            else sendMessage($chat_id, "⚠️ Usage: /ban KEY", $telegram_token);
            break;
            
        case '/unban': 
            if (isset($args[0])) updateKeyStatus($chat_id, $args[0], 'active', $telegram_token, $pdo); 
            else sendMessage($chat_id, "⚠️ Usage: /unban KEY", $telegram_token);
            break;
            
        case '/reset': 
            if (isset($args[0])) resetKeyHash($chat_id, $args[0], $telegram_token, $pdo); 
            else sendMessage($chat_id, "⚠️ Usage: /reset KEY", $telegram_token);
            break;
            
        case '/del': 
        case '/delete':
            if (isset($args[0])) deleteKey($chat_id, $args[0], $telegram_token, $pdo); 
            else sendMessage($chat_id, "⚠️ Usage: /del KEY", $telegram_token);
            break;

        default:
            // Auto-detect Key format for info lookup
            if (preg_match('/^[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}$/i', $text) || 
                preg_match('/^GLB-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}$/i', $text)) {
                lookupKey($chat_id, $text, $telegram_token, $pdo);
            }
    }
}

// --- Functions ---

function lookupKey($chat_id, $key, $token, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        $is_global = strpos($row['key_type'], 'global_') === 0;
        $status_emoji = $row['status'] == 'active' ? '✅' : ($row['status'] == 'banned' ? '🚫' : '⚠️');
        $expiry_status = (strtotime($row['expiry_date']) < time()) ? " (EXPIRED)" : "";
        
        $text = "🔍 <b>Key Details</b>\n\n";
        $text .= "🔑 <code>{$key}</code>\n";
        $text .= "📌 Type: " . ($is_global ? "🌐 Global" : "👤 Standard") . "\n";
        $text .= "📊 Status: {$status_emoji} " . strtoupper($row['status']) . "{$expiry_status}\n";
        $text .= "📅 Expires: " . date('Y-m-d H:i', strtotime($row['expiry_date'])) . "\n";
        $text .= "📱 HWID: " . ($row['hwid'] ? "<code>{$row['hwid']}</code>" : "<i>Not Bound</i>") . "\n";
        $text .= "🕒 Last Used: " . ($row['last_used'] ? date('Y-m-d H:i', strtotime($row['last_used'])) : "Never") . "\n";
        
        // Inline Action Buttons
        $keyboard = ['inline_keyboard' => [
            [
                ['text' => ($row['status'] == 'banned' ? '✅ Unban' : '🚫 Ban'), 'callback_data' => ($row['status'] == 'banned' ? "unban_$key" : "ban_$key")],
                ['text' => '🔄 Reset HWID', 'callback_data' => "reset_$key"]
            ],
            [
                 ['text' => '🗑️ Delete Key', 'callback_data' => "del_$key"]
            ]
        ]];
        
        sendMessage($chat_id, $text, $token, $keyboard);
    } else {
        sendMessage($chat_id, "❌ Key not found.", $token);
    }
}

function updateKeyStatus($chat_id, $key, $status, $token, $pdo, $msg_id = null) {
    $stmt = $pdo->prepare("UPDATE licenses SET status = ? WHERE license_key = ?");
    if ($stmt->execute([$status, $key])) {
        $emoji = $status == 'banned' ? '🚫' : '✅';
        $text = "$emoji Key <code>$key</code> is now " . strtoupper($status);
        if ($msg_id) {
            editMessageText($chat_id, $msg_id, $text, $token);
            // Refresh info after 2 seconds or show new info immediately? 
            // Better to just show confirmation.
        } else {
            sendMessage($chat_id, $text, $token);
        }
    }
}

function resetKeyHash($chat_id, $key, $token, $pdo, $msg_id = null) {
    $stmt = $pdo->prepare("UPDATE licenses SET hwid = NULL WHERE license_key = ?");
    if ($stmt->execute([$key])) {
        $text = "🔄 HWID Reset for <code>$key</code>. Can now be used on a new device.";
        if ($msg_id) editMessageText($chat_id, $msg_id, $text, $token);
        else sendMessage($chat_id, $text, $token);
    }
}

function deleteKey($chat_id, $key, $token, $pdo, $msg_id = null) {
    $stmt = $pdo->prepare("DELETE FROM licenses WHERE license_key = ?");
    if ($stmt->execute([$key])) {
        $text = "🗑️ Deleted key <code>$key</code>.";
        if ($msg_id) editMessageText($chat_id, $msg_id, $text, $token);
        else sendMessage($chat_id, $text, $token);
    }
}

// ... User Interface Functions ...

function sendMessage($chat_id, $text, $token, $reply_markup = null) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_exec($ch);
    curl_close($ch);
}

function editMessageText($chat_id, $message_id, $text, $token, $reply_markup = null) {
    $url = "https://api.telegram.org/bot{$token}/editMessageText";
    $data = ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_exec($ch);
    curl_close($ch);
}

function sendMainMenu($chat_id, $token) {
    $text = "🤖 <b>ST FAMILY Admin Bot v2.0</b>\n\n";
    $text .= "Commands:\n";
    $text .= "🔑 /generate - Create keys\n";
    $text .= "🌍 /global - Global keys\n";
    $text .= "⚙️ /control - Settings\n";
    $text .= "📊 /stats - Database stats\n";
    $text .= "📋 /list - Recent keys\n\n";
    $text .= "Manage Key:\n";
    $text .= "Send any key to see info, or use:\n";
    $text .= "/ban &lt;key&gt; - Ban key\n";
    $text .= "/reset &lt;key&gt; - Reset HWID\n";
    $text .= "/del &lt;key&gt; - Delete key";
    sendMessage($chat_id, $text, $token);
}

function sendGenerateMenu($chat_id, $token) {
    $keyboard = ['inline_keyboard' => [
        [['text' => '1 Key - 7 Days', 'callback_data' => 'gen_1_7'], ['text' => '1 Key - 30 Days', 'callback_data' => 'gen_1_30']],
        [['text' => '5 Keys - 30 Days', 'callback_data' => 'gen_5_30'], ['text' => '10 Keys - 30 Days', 'callback_data' => 'gen_10_30']],
        [['text' => '1 Key - Lifetime', 'callback_data' => 'gen_1_3650'], ['text' => '5 Keys - Lifetime', 'callback_data' => 'gen_5_3650']]
    ]];
    sendMessage($chat_id, "🔑 <b>Generate Standard Keys</b>", $token, $keyboard);
}

function sendGlobalKeyMenu($chat_id, $token) {
    $keyboard = ['inline_keyboard' => [
        [['text' => '🌍 Global Day', 'callback_data' => 'global_day']],
        [['text' => '🌍 Global Week', 'callback_data' => 'global_week']],
        [['text' => '🌍 Global Month', 'callback_data' => 'global_month']]
    ]];
    sendMessage($chat_id, "🌐 <b>Global Keys (Unlimited Devices)</b>", $token, $keyboard);
}

function sendControlMenu($chat_id, $token, $pdo) {
    $server = getStatus($pdo, 'server_enabled');
    $validation = getStatus($pdo, 'key_validation_enabled');
    $creation = getStatus($pdo, 'key_creation_enabled');
    
    $text = "⚙️ <b>Server Controls</b>\n\n";
    $text .= ($server ? '🟢' : '🔴') . " Server Status\n";
    $text .= ($validation ? '🟢' : '🔴') . " Key Validation\n";
    $text .= ($creation ? '🟢' : '🔴') . " Key Gen";
    
    $keyboard = ['inline_keyboard' => [
        [['text' => ($server ? '🔴 Stop Server' : '🟢 Start Server'), 'callback_data' => 'server_toggle']],
        [['text' => ($validation ? '🔴 Disable Validation' : '🟢 Enable Validation'), 'callback_data' => 'validation_toggle']],
        [['text' => ($creation ? '🔴 Disable Creation' : '🟢 Enable Creation'), 'callback_data' => 'creation_toggle']],
        [['text' => '🗑️ Delete Expired Keys', 'callback_data' => 'delete_expired']]
    ]];
    
    sendMessage($chat_id, $text, $token, $keyboard);
}

// ... Core Logic ...

function getStatus($pdo, $key) {
    $stmt = $pdo->query("SELECT value FROM server_settings WHERE key = '$key'");
    return $stmt ? $stmt->fetchColumn() === '1' : true;
}

function toggleStatus($pdo, $key, $chat_id, $token, $name) {
    $current = getStatus($pdo, $key);
    $new = $current ? '0' : '1';
    $pdo->prepare("UPDATE server_settings SET value = ? WHERE key = ?")->execute([$new, $key]);
    sendMessage($chat_id, ($new === '1' ? '🟢' : '🔴') . " $name " . ($new === '1' ? 'enabled' : 'disabled'), $token);
}

function generateKeys($chat_id, $count, $days, $type, $token, $pdo) {
    $keys = [];
    for ($i = 0; $i < $count; $i++) {
        $char = "ABCDEF0123456789";
        $keyParts = [];
        for($p=0; $p<4; $p++) {
            $part = "";
            for($k=0; $k<4; $k++) $part .= $char[rand(0, 15)];
            $keyParts[] = $part;
        }
        $key = implode("-", $keyParts);
        
        $expiry = date('Y-m-d H:i:s', strtotime("+$days days"));
        $stmt = $pdo->prepare("INSERT INTO licenses (license_key, expiry_date, key_type) VALUES (?, ?, ?)");
        if ($stmt->execute([$key, $expiry, $type])) $keys[] = $key;
    }
    
    $duration = $days >= 3650 ? 'Lifetime' : "$days Days";
    $text = "✅ Generated {$count} Key(s) - {$duration}\n\n<code>" . implode("\n", $keys) . "</code>";
    sendMessage($chat_id, $text, $token);
}

function generateGlobalKey($chat_id, $type, $token, $pdo) {
    $days = ['day' => 1, 'week' => 7, 'month' => 30][$type];
    
    $char = "ABCDEF0123456789";
    $keyParts = [];
    for($p=0; $p<3; $p++) {
        $part = "";
        for($k=0; $k<4; $k++) $part .= $char[rand(0, 15)];
        $keyParts[] = $part;
    }
    $key = "GLB-" . implode("-", $keyParts);
    
    $expiry = date('Y-m-d H:i:s', strtotime("+$days days"));
    
    $stmt = $pdo->prepare("INSERT INTO licenses (license_key, expiry_date, key_type) VALUES (?, ?, ?)");
    if ($stmt->execute([$key, $expiry, "global_$type"])) {
        $text = "🌐 Global {$type} Key\n\n<code>{$key}</code>\n\n✅ Unlimited users/devices\n⏰ {$days} day(s)";
        sendMessage($chat_id, $text, $token);
    }
}

function deleteExpiredKeys($chat_id, $token, $pdo) {
    $stmt = $pdo->prepare("DELETE FROM licenses WHERE expiry_date < NOW()");
    $stmt->execute();
    sendMessage($chat_id, "🗑️ Deleted " . $stmt->rowCount() . " expired keys", $token);
}

function sendStats($chat_id, $token, $pdo) {
    $total = $pdo->query("SELECT COUNT(*) FROM licenses")->fetchColumn();
    $active = $pdo->query("SELECT COUNT(*) FROM licenses WHERE status = 'active' AND expiry_date > NOW()")->fetchColumn();
    $used = $pdo->query("SELECT COUNT(*) FROM licenses WHERE hwid IS NOT NULL")->fetchColumn();
    $global = $pdo->query("SELECT COUNT(*) FROM licenses WHERE key_type LIKE 'global_%'")->fetchColumn();
    $banned = $pdo->query("SELECT COUNT(*) FROM licenses WHERE status = 'banned'")->fetchColumn();
    
    $text = "📊 <b>Statistics</b>\n\nBOXES: {$total}\n✅ Active: {$active}\n🔗 In-Use: {$used}\n🌐 Global: {$global}\n🚫 Banned: {$banned}";
    sendMessage($chat_id, $text, $token);
}

function listActiveKeys($chat_id, $token, $pdo) {
    $stmt = $pdo->query("SELECT license_key, hwid, status, expiry_date, key_type FROM licenses WHERE expiry_date > NOW() ORDER BY created_at DESC LIMIT 15");
    $text = "📋 <b>Recent Keys</b>\n\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $icon = $row['status'] == 'banned' ? '🚫' : ($row['hwid'] ? '🔗' : '⚪');
        if (strpos($row['key_type'], 'global') !== false) $icon = '🌐';
        $text .= "$icon <code>{$row['license_key']}</code>\n";
    }
    sendMessage($chat_id, $text, $token);
}

function answerCallbackQuery($callback_id, $token) {
    $ch = curl_init("https://api.telegram.org/bot{$token}/answerCallbackQuery");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['callback_query_id' => $callback_id]);
    curl_exec($ch);
    curl_close($ch);
}
?>
