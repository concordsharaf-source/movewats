<?php
// ============================================================
// webhook.php - بدون تحقق من التوقيع (للتجربة)
// ============================================================

$input = file_get_contents('php://input');

// تسجيل كل الطلبات (للتتبع)
file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - " . $input . "\n", FILE_APPEND);

$data = json_decode($input, true);

if (isset($data['event']) && $data['event'] === 'messages.upsert') {
    $msg = $data['data'] ?? [];
    $from = $msg['key']['remoteJid'] ?? 'غير معروف';
    $msgId = $msg['key']['id'] ?? '';
    
    $text = '';
    if (isset($msg['message']['conversation'])) {
        $text = $msg['message']['conversation'];
    } elseif (isset($msg['message']['extendedTextMessage']['text'])) {
        $text = $msg['message']['extendedTextMessage']['text'];
    } else {
        $text = '📎 وسائط';
    }

    $messagesFile = 'messages.json';
    $messages = [];
    if (file_exists($messagesFile)) {
        $messages = json_decode(file_get_contents($messagesFile), true) ?? [];
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

http_response_code(200);
echo json_encode(['status' => 'received']);
?>
