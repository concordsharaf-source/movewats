<?php
// ============================================================
// webhook.php - استقبال الرسائل الواردة
// ============================================================

// 🔴 التوكن الخاص بك
$TOKEN = 'b750fdc9152f2462146603f298ff64dd2ef309598ab09e8f79442cab2192ea6f';

// 🔴 السكرت (Secret) الخاص بك
$WEBHOOK_SECRET = 'b9e9d10515ac8bac41bd8286a9f8617d';

// ============================================================
// 1. دالة التحقق من التوقيع
// ============================================================
function verifySignature($payload, $signature) {
    global $WEBHOOK_SECRET;
    if (empty($signature) || empty($WEBHOOK_SECRET)) {
        return false;
    }
    $expected = hash_hmac('sha256', $payload, $WEBHOOK_SECRET);
    return hash_equals($expected, $signature);
}

// ============================================================
// 2. استقبال الطلب
// ============================================================
$input = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';

// تسجيل الطلب
file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - " . $input . "\n", FILE_APPEND);

// التحقق من التوقيع
if (!verifySignature($input, $signature)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

// ============================================================
// 3. معالجة البيانات
// ============================================================
$data = json_decode($input, true);

if (isset($data['event'])) {
    $event = $data['event'];
    file_put_contents('webhook_log.txt', "📌 الحدث: {$event}\n", FILE_APPEND);

    if ($event === 'messages.upsert') {
        $msg = $data['data'] ?? [];
        $from = $msg['key']['remoteJid'] ?? 'غير معروف';
        $msgId = $msg['key']['id'] ?? '';
        
        $text = '';
        if (isset($msg['message']['conversation'])) {
            $text = $msg['message']['conversation'];
        } elseif (isset($msg['message']['extendedTextMessage']['text'])) {
            $text = $msg['message']['extendedTextMessage']['text'];
        } elseif (isset($msg['message']['imageMessage']['caption'])) {
            $text = '📸 [صورة] ' . $msg['message']['imageMessage']['caption'];
        } else {
            $text = '📎 [وسائط]';
        }

        // تخزين الرسالة في ملف JSON
        $messagesFile = 'messages.json';
        $messages = [];
        if (file_exists($messagesFile)) {
            $content = file_get_contents($messagesFile);
            $messages = json_decode($content, true) ?? [];
        }

        array_unshift($messages, [
            'id' => $msgId,
            'from' => $from,
            'text' => $text,
            'time' => date('Y-m-d H:i:s'),
            'is_replied' => false
        ]);

        if (count($messages) > 100) {
            $messages = array_slice($messages, 0, 100);
        }

        file_put_contents($messagesFile, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        file_put_contents('webhook_log.txt', "✅ تم تخزين رسالة من {$from}\n", FILE_APPEND);
    }
}

// ============================================================
// 4. الرد للمنصة
// ============================================================
http_response_code(200);
echo json_encode(['status' => 'received']);
?>
