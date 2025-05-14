<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$logFile = __DIR__ . '/callManager.log';
$isCli = php_sapi_name() === 'cli';

file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Request registered\n", FILE_APPEND);

// Telegram Bot Configuration
$telegramBotToken = '7941498566:AAHixc9pmOeWD1tvQXGK2aoZAwpV51kbP7w';
// $telegramChatId = '489143238';
$telegramChatId = -4770119853;

// Function to send message to Telegram
function sendTelegramMessage($message) {
    global $telegramBotToken, $telegramChatId;
    $url = "https://api.telegram.org/bot$telegramBotToken/sendMessage";
    $data = [
        'chat_id' => $telegramChatId,
        'text' => $message
    ];
    $options = [
        'http' => [
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ]
    ];
    $context  = stream_context_create($options);
    file_get_contents($url, false, $context);
}

// Получаем данные вебхука
if (!$isCli) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Только POST запросы разрешены']);
        exit;
    }
    $postData = $_POST;
    if (empty($postData)) {
        parse_str(file_get_contents('php://input'), $postData);
    }
    if (empty($postData)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Нет полученных данных']);
        exit;
    }
} else {
    // Тестовый payload для CLI
    $postData = [
        'leads' => [
            'add' => [
                ['id' => 12345678, 'status_id' => 12345, 'pipeline_id' => 67890]
            ]
        ],
        'account' => [
            'id' => 32247010,
            'subdomain' => 'maratmost1'
        ]
    ];
}

// Логируем входящие данные вебхука
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Входящие данные вебхука:\n" . print_r($postData, true) . "\n", FILE_APPEND);

// Извлекаем ID сделок
$leadsArray = [];
if (isset($postData['leads']['add'])) {
    $leadsArray = array_merge($leadsArray, $postData['leads']['add']);
}
if (isset($postData['leads']['status'])) {
    $leadsArray = array_merge($leadsArray, $postData['leads']['status']);
}

foreach ($leadsArray as $lead) {
    $leadId = (int)$lead['id'];
    $subdomain = $postData['account']['subdomain'] ?? 'unknown';
    $message = "Вызов менеджера в сделку https://$subdomain.amocrm.ru/leads/detail/$leadId";
    sendTelegramMessage($message);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Отправлено сообщение в Telegram: $message\n", FILE_APPEND);
}

header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'Webhook обработан']);
exit;
