<?php
// ============================================================
// webhook.php - النسخة المصححة والنهائية
// ============================================================

// تسجيل كل الطلب (للتتبع)
$input = file_get_contents('php://input');
file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - " . $input . "\n", FILE_APPEND);

$data = json_decode($input, true);

// التحقق من البنية
if (!isset($data['event'])) {
    http_response_code(200);
    echo json_encode(['status' => 'no_event']);
    exit;
}

$event = $data['event'];

// ============================================================
// معالجة الرسائل
// ============================================================
if ($event === 'messages.upsert' || $event === 'messages.received') {
    
    // البيانات في data.messages (وليس data مباشرة)
    $msg = $data['data']['messages'] ?? [];
    
    if (empty($msg)) {
        file_put_contents('webhook_log.txt', "⚠️ لا توجد رسائل في البيانات\n", FILE_APPEND);
        http_response_code(200);
        echo json_encode(['status' => 'no_messages']);
        exit;
    }
    
    // استخراج المعلومات
    $key = $msg['key'] ?? [];
    $fromMe = $key['fromMe'] ?? false;
    $remoteJid = $key['remoteJid'] ?? 'غير معروف';
    $senderPn = $key['senderPn'] ?? $remoteJid;
    $msgId = $key['id'] ?? '';
    
    // تحديد المرسل
    if ($fromMe) {
        $from = 'أنت (' . ($key['cleanedSenderPn'] ?? 'أنت') . ')';
    } else {
        $from = $key['pushName'] ?? $key['cleanedSenderPn'] ?? $senderPn ?? 'غير معروف';
    }
    
    // ============================================================
    // استخراج النص (جميع الاحتمالات)
    // ============================================================
    $text = '';
    
    // 1. messageBody (الأسهل - يحتوي النص جاهز)
    if (!empty($msg['messageBody'])) {
        $text = $msg['messageBody'];
    }
    
    // 2. conversation (نص عادي)
    elseif (isset($msg['message']['conversation'])) {
        $text = $msg['message']['conversation'];
    }
    
    // 3. extendedTextMessage (نص موسّع)
    elseif (isset($msg['message']['extendedTextMessage']['text'])) {
        $text = $msg['message']['extendedTextMessage']['text'];
    }
    
    // 4. imageMessage
    elseif (isset($msg['message']['imageMessage'])) {
        $caption = $msg['message']['imageMessage']['caption'] ?? '';
        $text = '📸 [صورة]' . ($caption ? ' ' . $caption : '');
    }
    
    // 5. videoMessage
    elseif (isset($msg['message']['videoMessage'])) {
        $caption = $msg['message']['videoMessage']['caption'] ?? '';
        $text = '🎥 [فيديو]' . ($caption ? ' ' . $caption : '');
    }
    
    // 6. audioMessage
    elseif (isset($msg['message']['audioMessage'])) {
        $text = '🎤 [رسالة صوتية]';
    }
    
    // 7. documentMessage
    elseif (isset($msg['message']['documentMessage'])) {
        $fileName = $msg['message']['documentMessage']['fileName'] ?? '';
        $text = '📄 [مستند]' . ($fileName ? ' ' . $fileName : '');
    }
    
    // 8. stickerMessage
    elseif (isset($msg['message']['stickerMessage'])) {
        $text = '😊 [ملصق]';
    }
    
    // 9. locationMessage
    elseif (isset($msg['message']['locationMessage'])) {
        $text = '📍 [موقع]';
    }
    
    // 10. contactMessage
    elseif (isset($msg['message']['contactMessage'])) {
        $text = '👤 [جهة اتصال]';
    }
    
    // 11. لا شيء
    else {
        $text = '📎 [وسائط]';
    }
    
    // ============================================================
    // إصلاح ترميز UTF-8
    // ============================================================
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }
    
    // إذا النص لا يزال مشفر
    if (strpos($text, 'Ù') !== false || strpos($text, 'Ø') !== false) {
        $text = @mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
    }
    
    // ============================================================
    // تخزين الرسالة
    // ============================================================
    $messagesFile = 'messages.json';
    $messages = [];
    
    if (file_exists($messagesFile)) {
        $content = file_get_contents($messagesFile);
        $messages = json_decode($content, true) ?? [];
    }
    
    // تجنب التكرار
    $existingIds = array_column($messages, 'id');
    if (in_array($msgId, $existingIds)) {
        http_response_code(200);
        echo json_encode(['status' => 'duplicate']);
        exit;
    }
    
    array_unshift($messages, [
        'id' => $msgId,
        'from' => $from,
        'text' => $text,
        'time' => date('Y-m-d H:i:s'),
        'is_replied' => false,
        'fromMe' => $fromMe
    ]);
    
    // الاحتفاظ بآخر 100 رسالة
    if (count($messages) > 100) {
        $messages = array_slice($messages, 0, 100);
    }
    
    file_put_contents($messagesFile, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    file_put_contents('webhook_log.txt', "✅ تم تخزين: {$from} -> {$text}\n", FILE_APPEND);
}

http_response_code(200);
echo json_encode(['status' => 'received']);
?>
