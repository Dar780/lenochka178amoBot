<?php

require_once(__DIR__ . '/amo.class.php');
require_once(__DIR__ . '/rc.class.php'); // Подключаем класс RealtyCalendar

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
            // При создании сделки данные приходят в "add", при изменении – в "status"
            'add' => [
                [
                    'id'           => 33066753,
                    'status_id'    => 74364962,
                    'pipeline_id'  => 9266190,
                    'title'        => 'Бронь #100883942'
                ]
            ]
        ],
        'account' => [
            'id'        => 32247010,
            'subdomain' => 'lenasutochno178'
        ]
    ];
}

// Логируем входящие данные вебхука
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Входящие данные вебхука:\n" . print_r($postData, true) . "\n", FILE_APPEND);

// Захардкоженный subdomain для amoCRM
$subdomain = 'lenasutochno178';
// Инициализируем токен для доступа к amoCRM (тот же, что использовался ранее)
$token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImEzNjg1ZGM3NGI1NGQyNTY2MDUwOWNhNDljYWVkNzYyYzZkZjQxYjRiYTI0ZTkyMTQzMmQ5MjY1ZTE1NDJlOTliM2VjOWNiNWJiYjEwZTkzIn0.eyJhdWQiOiI2NWNhYTdjNy0zYTAxLTRmNmItODk3MS04ZGQ4OWE2ZDg4OTMiLCJqdGkiOiJhMzY4NWRjNzRiNTRkMjU2NjA1MDljYTQ5Y2FlZDc2MmM2ZGY0MWI0YmEyNGU5MjE0MzJkOTI2NWUxNTQyZTk5YjNlYzljYjViYmIxMGU5MyIsImlhdCI6MTc0MTA1NzcyNywibmJmIjoxNzQxMDU3NzI3LCJleHAiOjE4ODYzNzEyMDAsInN1YiI6IjEyMTUyNDM0IiwiZ3JhbnRfdHlwZSI6IiIsImFjY291bnRfaWQiOjMyMjQ3MDEwLCJiYXNlX2RvbWFpbiI6ImFtb2NybS5ydSIsInZlcnNpb24iOjIsInNjb3BlcyI6WyJjcm0iLCJmaWxlcyIsImZpbGVzX2RlbGV0ZSIsIm5vdGlmaWNhdGlvbnMiLCJwdXNoX25vdGlmaWNhdGlvbnMiXSwiaGFzaF91dWlkIjoiZTdjNWQ0NGYtYjFiMy00YTVmLWEwODUtOTUzOGRhYjIzZDU2IiwiYXBpX2RvbWFpbiI6ImFwaS1iLmFtb2NybS5ydSJ9.c3P07oYaBb3rq5MVYXuDAAivh7gY1kkZtgHDMMtSsAohlqQajSrztedOQBEWTmnSq_289-cJ1QdLW8qtrEqAy6txomnmCTMIrKiGHC0RpWIoUFhq4VcTuccDu-KQQU8ROOY5wXJnfKVfsOc6GUa6Bf8s_-pwVAqjPyGfvmg3pzdLw--OAF9ALiOyeNkRc2Ci5lSCYs095x8CpHGwrqxsiUhAaxuHO7xJwtfQwPLoqJxf24IS45Gj_g8nduo7YwZ-B_ru5_4lFhVSUEXoo8wuyW_2O0_llhb5-6Ek_Ne0Luiq2a_3Dd7x2wrzvUtAsxH0BdLtS-Jbvtmhd1XiH7ukMA";

$amoCRM = new AmoCRM($subdomain);
$amoCRM->setToken($token);

// Инициализируем RealtyCalendar с токеном для него (используем нужный для заголовка X-User-Token)
$rc = new RealtyCalendar("vbeHdes1arkNpb5cn9kw"); // замените на ваш токен для RealtyCalendar

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
    
    // Получаем информацию по брони через RealtyCalendar
    try {
        $bookingInfo = $rc->getBookingInfo($bookingNumber);
        // Добавляем идентификатор сделки и флаг is_moved_amo (0 по умолчанию)
        $bookingInfo['lead_id'] = $leadId;
        $bookingInfo['is_moved_amo'] = 0;

        // Удаляем проверку на apartment_id == 209505, чтобы обрабатывать все квартиры
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Бронь $bookingNumber: Инфо:\n" . print_r($bookingInfo, true) . "\n", FILE_APPEND);
        
        // Сохраняем JSON с данными брони в папке bookings под именем {bookingId}.json
        $bookingsDir = __DIR__ . '/bookings';
        if (!is_dir($bookingsDir)) {
            mkdir($bookingsDir, 0777, true);
        }
        $bookingFile = $bookingsDir . '/' . $bookingInfo['id'] . '.json';
        file_put_contents($bookingFile, json_encode($bookingInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } catch (Exception $e) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Ошибка при получении инфо брони $bookingNumber: " . $e->getMessage() . "\n", FILE_APPEND);
        continue;
    }
    
    try {
        $paymentInfo = $rc->getPaymentInfo($bookingNumber);
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Бронь $bookingNumber: Платежи:\n" . print_r($paymentInfo, true) . "\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Ошибка при получении платежей для брони $bookingNumber: " . $e->getMessage() . "\n", FILE_APPEND);
        continue;
    }
    
    // Извлекаем сумму оплаты (берем первое найденное значение)
    $paymentAmount = 0;
    if (isset($paymentInfo['data']) && is_array($paymentInfo['data']) && count($paymentInfo['data']) > 0) {
        foreach ($paymentInfo['data'] as $record) {
            if (isset($record['payment']['amount'])) {
                $paymentAmount = $record['payment']['amount'];
                break;
            }
        }
    }
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Сделка ID $leadId: Внесенная оплата: $paymentAmount.\n", FILE_APPEND);
    
    // Из общей информации получаем общую сумму брони
    if (!isset($bookingInfo['amount'])) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Нет данных о сумме для брони $bookingNumber.\n", FILE_APPEND);
        continue;
    }
    $totalAmount = $bookingInfo['amount'];
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Бронь $bookingNumber: Общая сумма (amount): $totalAmount.\n", FILE_APPEND);
    
    // Вычисляем остаток: если внесенная оплата есть, то остаток = totalAmount - paymentAmount, иначе остаток = totalAmount
    $remaining = ($paymentAmount > 0) ? ($totalAmount - $paymentAmount) : $totalAmount;
    
    // Формируем массив для обновления кастомных полей
    $updateFields = [
        [
            'field_id' => 850717,
            'values'   => [
                ['value' => (string)$totalAmount]
            ]
        ],
        [
            'field_id' => 850719,
            'values'   => [
                ['value' => (string)$remaining]
            ]
        ],
        [
            'field_id' => 848327,
            'values'   => [
                ['value' => (string)$paymentAmount]
            ]
        ]
    ];
    
    // Если в ответе брони присутствует apartment_id, проверяем БД на наличие квартиры
    if (isset($bookingInfo['apartment_id'])) {
        require_once(__DIR__ . '/config.php'); // Подключаем файл конфигурации БД
        $apartmentId = $bookingInfo['apartment_id'];
        $stmt = $db->prepare("SELECT * FROM apartments WHERE realty_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $apartmentId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $apartmentData = $result->fetch_assoc();
                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Найдена квартира для realty_id $apartmentId:\n" . print_r($apartmentData, true) . "\n", FILE_APPEND);
                
                $updateFields[] = ['field_id' => 852841, 'values' => [['value' => $apartmentData['street']]]];
                $updateFields[] = ['field_id' => 852843, 'values' => [['value' => $apartmentData['house_number']]]];
                $updateFields[] = ['field_id' => 852845, 'values' => [['value' => $apartmentData['apartment_number']]]];
                $updateFields[] = ['field_id' => 852847, 'values' => [['value' => $apartmentData['gate_code']]]];
                $updateFields[] = ['field_id' => 852849, 'values' => [['value' => $apartmentData['intercom_code']]]];
                $updateFields[] = ['field_id' => 852851, 'values' => [['value' => $apartmentData['deposit_amount']]]];
                $updateFields[] = ['field_id' => 852853, 'values' => [['value' => $apartmentData['cleaning_fee']]]];
                $updateFields[] = ['field_id' => 852855, 'values' => [['value' => $apartmentData['bank']]]];
                $updateFields[] = ['field_id' => 852857, 'values' => [['value' => $apartmentData['recipient']]]];
                $updateFields[] = ['field_id' => 873617, 'values' => [['value' => $apartmentData['wifi_name']]]];
                $updateFields[] = ['field_id' => 873619, 'values' => [['value' => $apartmentData['wifi_password']]]];
            }
            $stmt->close();
        } else {
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Ошибка подготовки запроса: " . $db->error . "\n", FILE_APPEND);
        }
    }
    
    // Формируем итоговый массив для обновления сделки
    $updateData = [
        'custom_fields_values' => $updateFields
    ];
    
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Сделка ID $leadId: Данные обновления:\n" . print_r($updateData, true) . "\n", FILE_APPEND);
    
    $updateResponse = $amoCRM->call('PATCH', "leads/{$leadId}", $updateData);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Сделка ID $leadId: Ответ обновления сделки:\n" . print_r($updateResponse, true) . "\n", FILE_APPEND);
}

header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'Webhook обработан']);
exit;
