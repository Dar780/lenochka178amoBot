<?php

sleep(1);

require_once(__DIR__ . '/amo.class.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

$logFile = __DIR__ . '/handler_log.txt';
$isCli = php_sapi_name() === 'cli';

file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Request registered\n", FILE_APPEND);

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
            'status' => [
                [
                    'id'    => 33058363,
                    'title' => 'Бронь #100730563'
                ]
            ]
        ],
        'account' => [
            'subdomain' => 'maratmost1'
        ]
    ];
}

// Логируем входящие данные вебхука
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Входящие данные вебхука:\n" . print_r($postData, true) . "\n", FILE_APPEND);

// Захардкоженный subdomain для amoCRM
$subdomain = 'maratmost1';

// Инициализируем токен для доступа к amoCRM (один и тот же для всех запросов)
$token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImEzNjg1ZGM3NGI1NGQyNTY2MDUwOWNhNDljYWVkNzYyYzZkZjQxYjRiYTI0ZTkyMTQzMmQ5MjY1ZTE1NDJlOTliM2VjOWNiNWJiYjEwZTkzIn0.eyJhdWQiOiI2NWNhYTdjNy0zYTAxLTRmNmItODk3MS04ZGQ4OWE2ZDg4OTMiLCJqdGkiOiJhMzY4NWRjNzRiNTRkMjU2NjA1MDljYTQ5Y2FlZDc2MmM2ZGY0MWI0YmEyNGU5MjE0MzJkOTI2NWUxNTQyZTk5YjNlYzljYjViYmIxMGU5MyIsImlhdCI6MTc0MTA1NzcyNywibmJmIjoxNzQxMDU3NzI3LCJleHAiOjE4ODYzNzEyMDAsInN1YiI6IjEyMTUyNDM0IiwiZ3JhbnRfdHlwZSI6IiIsImFjY291bnRfaWQiOjMyMjQ3MDEwLCJiYXNlX2RvbWFpbiI6ImFtb2NybS5ydSIsInZlcnNpb24iOjIsInNjb3BlcyI6WyJjcm0iLCJmaWxlcyIsImZpbGVzX2RlbGV0ZSIsIm5vdGlmaWNhdGlvbnMiLCJwdXNoX25vdGlmaWNhdGlvbnMiXSwiaGFzaF91dWlkIjoiZTdjNWQ0NGYtYjFiMy00YTVmLWEwODUtOTUzOGRhYjIzZDU2IiwiYXBpX2RvbWFpbiI6ImFwaS1iLmFtb2NybS5ydSJ9.c3P07oYaBb3rq5MVYXuDAAivh7gY1kkZtgHDMMtSsAohlqQajSrztedOQBEWTmnSq_289-cJ1QdLW8qtrEqAy6txomnmCTMIrKiGHC0RpWIoUFhq4VcTuccDu-KQQU8ROOY5wXJnfKVfsOc6GUa6Bf8s_-pwVAqjPyGfvmg3pzdLw--OAF9ALiOyeNkRc2Ci5lSCYs095x8CpHGwrqxsiUhAaxuHO7xJwtfQwPLoqJxf24IS45Gj_g8nduo7YwZ-B_ru5_4lFhVSUEXoo8wuyW_2O0_llhb5-6Ek_Ne0Luiq2a_3Dd7x2wrzvUtAsxH0BdLtS-Jbvtmhd1XiH7ukMA";

$amoCRM = new AmoCRM($subdomain);
$amoCRM->setToken($token);

// Обрабатываем каждую сделку, пришедшую в вебхуке
$leadsArray = isset($postData['leads']['status']) ? $postData['leads']['status'] : $postData['leads']['add'];
foreach ($leadsArray as $leadStatus) {
    $leadId = (int)$leadStatus['id'];
    
    // Получаем данные сделки из amoCRM
    $leadData = $amoCRM->getLeadById($leadId);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Данные сделки для ID $leadId:\n" . print_r($leadData, true) . "\n", FILE_APPEND);
    
    if (!isset($leadData['name'])) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Сделке с ID $leadId не найдено поле name.\n", FILE_APPEND);
        continue;
    }
    
    $leadName = $leadData['name'];
    
    // Если название сделки начинается с "Бронь", извлекаем номер брони
    if (!preg_match('/^Бронь\s+#?(\d+)/u', $leadName, $matches)) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Сделка ID $leadId не является бронью.\n", FILE_APPEND);
        continue;
    }
    $bookingNumber = $matches[1];
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Сделка ID $leadId: Извлечён номер брони: $bookingNumber.\n", FILE_APPEND);
    
    // Формируем URL для запроса к API realtycalendar.ru
    $url = "https://realtycalendar.ru/v2/event_calendars/{$bookingNumber}/payments";
    
    // Выполняем запрос к realtycalendar.ru
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:126.0) Gecko/20100101 Firefox/126.0 Herring/91.1.1890.10',
        'Accept: */*',
        'Accept-Language: en-US,en;q=0.5',
        // 'Accept-Encoding: gzip, deflate, br, zstd',
        'Referer: https://realtycalendar.ru/chessmate/event/' . $bookingNumber,
        'X-Locale: ru',
        'X-User-Token: EJWGHqN759xHk8kJADRC',
        'Content-Type: application/json',
        'DNT: 1',
        'Sec-GPC: 1',
        'Connection: keep-alive',
        'Sec-Fetch-Dest: empty',
        'Sec-Fetch-Mode: cors',
        'Sec-Fetch-Site: same-origin'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Ошибка cURL для сделки ID $leadId: $error\n", FILE_APPEND);
        curl_close($ch);
        continue;
    }
    curl_close($ch);
    
    $paymentData = json_decode($response, true);
    if (!$paymentData || empty($paymentData['data'])) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Нет данных об оплате для брони $bookingNumber (сделка ID $leadId).\n", FILE_APPEND);
        continue;
    }
    
    // Ищем сумму оплаты (amount) в полученных данных (берем первое найденное значение)
    $paymentAmount = null;
    foreach ($paymentData['data'] as $record) {
        if (isset($record['payment']['amount'])) {
            $paymentAmount = $record['payment']['amount'];
            break;
        }
    }
    if ($paymentAmount === null) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Сумма оплаты не найдена для брони $bookingNumber (сделка ID $leadId).\n", FILE_APPEND);
        continue;
    }
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Сделка ID $leadId: Сумма оплаты: $paymentAmount.\n", FILE_APPEND);
    
    // Обновляем сделку в amoCRM: записываем сумму оплаты в кастомное поле с ID 848327
    $updateData = [
        'custom_fields_values' => [
            [
                'field_id' => 848327,
                'values'   => [
                    ['value' => (string)$paymentAmount]
                ]
            ]
        ]
    ];
    
    $updateResponse = $amoCRM->call('PATCH', "leads/{$leadId}", $updateData);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Сделка ID $leadId: Ответ обновления сделки:\n" . print_r($updateResponse, true) . "\n", FILE_APPEND);
}

header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'Webhook обработан']);
exit;
