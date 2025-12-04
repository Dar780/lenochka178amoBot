<?php

// Включаем буферизацию вывода для предотвращения проблем с заголовками
ob_start();

require_once(__DIR__ . '/amo.class.php');
require_once(__DIR__ . '/rc.class.php'); // Подключаем класс RealtyCalendar

error_reporting(E_ALL);
ini_set('display_errors', 1);

$logFile = __DIR__ . '/handler_log.txt';
$isCli = php_sapi_name() === 'cli';

// Функция для безопасной записи в лог
function safeLog($logFile, $message) {
    if (is_writable(dirname($logFile))) {
        @file_put_contents($logFile, $message, FILE_APPEND);
    } else {
        error_log(strip_tags($message));
    }
}

/**
 * Список полей, которые приходят из Realty и сигнализируют,
 * что данные реально обновились.
 */
$REALTY_FIELD_IDS = [
    833655, // Дата заезда
    833657, // Дата выезда
    833661, // Количество гостей
    833663, // Количество комнат
    833665, // Количество спальных мест
    833667, // Город / адресные данные
    833669, // Адрес (улица+дом)
];

/**
 * Проверяем, содержит ли блок webhook'а изменения по нужным полям.
 */
function hasRealtyFieldChanges(array $leadPayload, array $realtyFieldIds): bool {
    $fieldBlocks = [];

    if (isset($leadPayload['custom_fields']) && is_array($leadPayload['custom_fields'])) {
        $fieldBlocks[] = $leadPayload['custom_fields'];
    }

    if (isset($leadPayload['custom_fields_values']) && is_array($leadPayload['custom_fields_values'])) {
        $fieldBlocks[] = $leadPayload['custom_fields_values'];
    }

    foreach ($fieldBlocks as $fields) {
        foreach ($fields as $field) {
            $fieldId = $field['id'] ?? $field['field_id'] ?? null;
            if ($fieldId === null) {
                continue;
            }

            if (in_array((int)$fieldId, $realtyFieldIds, true)) {
                return true;
            }
        }
    }

    return false;
}

// Проверяем возможность записи в лог
if (!is_writable(dirname($logFile))) {
    error_log("[" . date('Y-m-d H:i:s') . "] Cannot write to log directory: " . dirname($logFile));
} else {
    @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Request registered\n", FILE_APPEND);
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
safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Входящие данные вебхука:\n" . print_r($postData, true) . "\n");

// Захардкоженный subdomain для amoCRM
$subdomain = 'lenasutochno178';
// Инициализируем токен для доступа к amoCRM (тот же, что использовался ранее)
$token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImEzNjg1ZGM3NGI1NGQyNTY2MDUwOWNhNDljYWVkNzYyYzZkZjQxYjRiYTI0ZTkyMTQzMmQ5MjY1ZTE1NDJlOTliM2VjOWNiNWJiYjEwZTkzIn0.eyJhdWQiOiI2NWNhYTdjNy0zYTAxLTRmNmItODk3MS04ZGQ4OWE2ZDg4OTMiLCJqdGkiOiJhMzY4NWRjNzRiNTRkMjU2NjA1MDljYTQ5Y2FlZDc2MmM2ZGY0MWI0YmEyNGU5MjE0MzJkOTI2NWUxNTQyZTk5YjNlYzljYjViYmIxMGU5MyIsImlhdCI6MTc0MTA1NzcyNywibmJmIjoxNzQxMDU3NzI3LCJleHAiOjE4ODYzNzEyMDAsInN1YiI6IjEyMTUyNDM0IiwiZ3JhbnRfdHlwZSI6IiIsImFjY291bnRfaWQiOjMyMjQ3MDEwLCJiYXNlX2RvbWFpbiI6ImFtb2NybS5ydSIsInZlcnNpb24iOjIsInNjb3BlcyI6WyJjcm0iLCJmaWxlcyIsImZpbGVzX2RlbGV0ZSIsIm5vdGlmaWNhdGlvbnMiLCJwdXNoX25vdGlmaWNhdGlvbnMiXSwiaGFzaF91dWlkIjoiZTdjNWQ0NGYtYjFiMy00YTVmLWEwODUtOTUzOGRhYjIzZDU2IiwiYXBpX2RvbWFpbiI6ImFwaS1iLmFtb2NybS5ydSJ9.c3P07oYaBb3rq5MVYXuDAAivh7gY1kkZtgHDMMtSsAohlqQajSrztedOQBEWTmnSq_289-cJ1QdLW8qtrEqAy6txomnmCTMIrKiGHC0RpWIoUFhq4VcTuccDu-KQQU8ROOY5wXJnfKVfsOc6GUa6Bf8s_-pwVAqjPyGfvmg3pzdLw--OAF9ALiOyeNkRc2Ci5lSCYs095x8CpHGwrqxsiUhAaxuHO7xJwtfQwPLoqJxf24IS45Gj_g8nduo7YwZ-B_ru5_4lFhVSUEXoo8wuyW_2O0_llhb5-6Ek_Ne0Luiq2a_3Dd7x2wrzvUtAsxH0BdLtS-Jbvtmhd1XiH7ukMA";

$amoCRM = new AmoCRM($subdomain);
$amoCRM->setToken($token);

// Инициализируем RealtyCalendar с токеном для него (используем нужный для заголовка X-User-Token)
$rc = new RealtyCalendar("vbeHdes1arkNpb5cn9kw"); // замените на ваш токен для RealtyCalendar

$leadsArray = [];
if (isset($postData['leads']) && is_array($postData['leads'])) {
    foreach (['add', 'update', 'status'] as $eventType) {
        if (isset($postData['leads'][$eventType]) && is_array($postData['leads'][$eventType])) {
            foreach ($postData['leads'][$eventType] as $leadPayload) {
                if (!is_array($leadPayload)) {
                    continue;
                }
                $leadPayload['_event_type'] = $eventType;
                $leadsArray[] = $leadPayload;
            }
        }
    }
}

if (empty($leadsArray)) {
    safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Нет сделок для обработки в webhook payload.\n");
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'skipped', 'message' => 'Нет сделок для обработки'], JSON_UNESCAPED_UNICODE);
    exit;
}

foreach ($leadsArray as $leadStatus) {
    $leadId = (int)$leadStatus['id'];
    $eventType = $leadStatus['_event_type'] ?? 'unknown';

    if ($eventType === 'status' && !hasRealtyFieldChanges($leadStatus, $REALTY_FIELD_IDS)) {
        safeLog(
            $logFile,
            "[" . date('Y-m-d H:i:s') . "] SKIP: Сделка $leadId пришла только со сменой статуса, поля Realty не менялись.\n"
        );
        continue;
    }
    
    // Получаем данные сделки из amoCRM
    $leadData = $amoCRM->getLeadById($leadId);
    safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Данные сделки для ID $leadId:\n" . print_r($leadData, true) . "\n");
    
    if (!isset($leadData['name'])) {
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Сделке с ID $leadId не найдено поле name.\n");
        continue;
    }
    
    $leadName = $leadData['name'];
    safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Сделка ID $leadId: Название сделки: '$leadName'\n");
    
    // Если название сделки начинается с "Бронь", извлекаем номер брони
    // Улучшенное регулярное выражение для различных вариантов написания
    if (!preg_match('/^(?:Бронь|бронь)\s*#?(\d+)/ui', $leadName, $matches)) {
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Сделка ID $leadId не является бронью. Название: '$leadName'\n");
        continue;
    }
    $bookingNumber = $matches[1];
    safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Сделка ID $leadId: Извлечён номер брони: $bookingNumber.\n");
    
    // Получаем информацию по брони через RealtyCalendar
    try {
        $bookingInfo = $rc->getBookingInfo($bookingNumber);
        // Добавляем идентификатор сделки и флаг is_moved_amo (0 по умолчанию)
        $bookingInfo['lead_id'] = $leadId;
        $bookingInfo['is_moved_amo'] = 0;

        // Удаляем проверку на apartment_id == 209505, чтобы обрабатывать все квартиры
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Бронь $bookingNumber: Инфо:\n" . print_r($bookingInfo, true) . "\n");
        
        // Сохраняем JSON с данными брони в папке bookings под именем {bookingId}.json
        $bookingsDir = __DIR__ . '/bookings';
        if (!is_dir($bookingsDir)) {
            mkdir($bookingsDir, 0777, true);
        }
        $bookingFile = $bookingsDir . '/' . $bookingInfo['id'] . '.json';
        file_put_contents($bookingFile, json_encode($bookingInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } catch (Exception $e) {
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Ошибка при получении инфо брони $bookingNumber: " . $e->getMessage() . "\n");
        continue;
    }
    
    try {
        $paymentInfo = $rc->getPaymentInfo($bookingNumber);
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Бронь $bookingNumber: Платежи:\n" . print_r($paymentInfo, true) . "\n");
    } catch (Exception $e) {
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Ошибка при получении платежей для брони $bookingNumber: " . $e->getMessage() . "\n");
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
    safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Сделка ID $leadId: Внесенная оплата: $paymentAmount.\n");
    
    // Из общей информации получаем общую сумму брони
    if (!isset($bookingInfo['amount'])) {
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Нет данных о сумме для брони $bookingNumber.\n");
        continue;
    }
    $totalAmount = $bookingInfo['amount'];
    safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Бронь $bookingNumber: Общая сумма (amount): $totalAmount.\n");
    
    // Вычисляем остаток: если внесенная оплата есть, то остаток = totalAmount - paymentAmount, иначе остаток = totalAmount
    $remaining = ($paymentAmount > 0) ? ($totalAmount - $paymentAmount) : $totalAmount;
    
    // Извлекаем источник бронирования из JSON (многоуровневый поиск)
    $bookingSource = 'unknown';
    
    // 1. Ищем в прямом поле (по документации API)
    if (isset($bookingInfo['source']) && !empty($bookingInfo['source'])) {
        $bookingSource = $bookingInfo['source'];
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Бронь $bookingNumber: Найден источник в прямом поле: '$bookingSource'\n");
    }
    // 2. Ищем в booking_origin (по документации API)
    elseif (isset($bookingInfo['booking_origin']['name']) && !empty($bookingInfo['booking_origin']['name'])) {
        $bookingSource = $bookingInfo['booking_origin']['name'];
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Бронь $bookingNumber: Найден источник в booking_origin: '$bookingSource'\n");
    }
    // 3. Ищем в истории изменений "Источник бронирования" (наш текущий метод)
    elseif (isset($bookingInfo['audits']) && is_array($bookingInfo['audits'])) {
        foreach ($bookingInfo['audits'] as $auditRecord) {
            if (isset($auditRecord['changes']) && is_array($auditRecord['changes'])) {
                foreach ($auditRecord['changes'] as $change) {
                    if (isset($change[0]) && $change[0] === 'Источник бронирования' && isset($change[1][1])) {
                        $bookingSource = $change[1][1];
                        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Бронь $bookingNumber: Найден источник в истории 'Источник бронирования': '$bookingSource'\n");
                        break 2;
                    }
                }
            }
        }
    }
    // 4. Ищем в истории изменений "Источник" (fallback)
    if ($bookingSource === 'unknown' && isset($bookingInfo['audits']) && is_array($bookingInfo['audits'])) {
        foreach ($bookingInfo['audits'] as $auditRecord) {
            if (isset($auditRecord['changes']) && is_array($auditRecord['changes'])) {
                foreach ($auditRecord['changes'] as $change) {
                    if (isset($change[0]) && $change[0] === 'Источник' && isset($change[1][1])) {
                        $bookingSource = $change[1][1];
                        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Бронь $bookingNumber: Найден источник в истории 'Источник': '$bookingSource'\n");
                        break 2;
                    }
                }
            }
        }
    }
    
    safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Бронь $bookingNumber: Итоговый источник: '$bookingSource'\n");

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
        ],
        [
            'field_id' => 977507, // RCS источник
            'values'   => [
                ['value' => $bookingSource]
            ]
        ]
    ];
    
    // Если в ответе брони присутствует apartment_id, проверяем БД на наличие квартиры
    if (isset($bookingInfo['apartment_id'])) {
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Бронь $bookingNumber: Найден apartment_id = {$bookingInfo['apartment_id']}\n");
        require_once(__DIR__ . '/config.php'); // Подключаем файл конфигурации БД
        $apartmentId = $bookingInfo['apartment_id'];
        if ($db === null) {
            safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] ВНИМАНИЕ: БД недоступна, пропускаем заполнение адресных полей.\n");
            // продолжаем без адресных полей
        } else {
        $stmt = $db->prepare("SELECT * FROM apartments WHERE realty_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $apartmentId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $apartmentData = $result->fetch_assoc();
                safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Найдена квартира для realty_id $apartmentId:\n" . print_r($apartmentData, true) . "\n");
                
                $updateFields[] = ['field_id' => 852841, 'values' => [['value' => $apartmentData['street']]]];
                $updateFields[] = ['field_id' => 852843, 'values' => [['value' => $apartmentData['house_number']]]];
                $updateFields[] = ['field_id' => 852845, 'values' => [['value' => $apartmentData['apartment_number']]]];
                $updateFields[] = ['field_id' => 852847, 'values' => [['value' => $apartmentData['gate_code']]]];
                $updateFields[] = ['field_id' => 852849, 'values' => [['value' => $apartmentData['intercom_code']]]];
                $updateFields[] = ['field_id' => 852851, 'values' => [['value' => $apartmentData['deposit_amount']]]];
                // Для источников sutochno.ru и Bronevik.com уборка = 0
                $isZeroCleaningSource = (stripos($bookingSource, 'sutochno.ru') !== false) || (stripos($bookingSource, 'bronevik.com') !== false);
                $cleaningFeeValue     = $isZeroCleaningSource ? '0' : (string)$apartmentData['cleaning_fee']; // Передаем как строку!
                
                if ($isZeroCleaningSource) {
                    safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Источник '$bookingSource' - устанавливаем уборку = '0' (было {$apartmentData['cleaning_fee']})\n");
                } else {
                    safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Источник '$bookingSource' - оставляем уборку из БД = '{$apartmentData['cleaning_fee']}'\n");
                }
                
                $updateFields[] = ['field_id' => 852853, 'values' => [['value' => $cleaningFeeValue]]];
                $updateFields[] = ['field_id' => 852855, 'values' => [['value' => $apartmentData['bank']]]];
                $updateFields[] = ['field_id' => 852857, 'values' => [['value' => $apartmentData['recipient']]]];
                $updateFields[] = ['field_id' => 873617, 'values' => [['value' => $apartmentData['wifi_name']]]];
                $updateFields[] = ['field_id' => 873619, 'values' => [['value' => $apartmentData['wifi_password']]]];
                
                // Безопасная обработка новых полей
                if (isset($apartmentData['keybox_code']) && !empty($apartmentData['keybox_code'])) {
                    $updateFields[] = ['field_id' => 977279, 'values' => [['value' => $apartmentData['keybox_code']]]];
                }
                if (isset($apartmentData['entrance_number']) && !empty($apartmentData['entrance_number'])) {
                    $updateFields[] = ['field_id' => 977281, 'values' => [['value' => $apartmentData['entrance_number']]]];
                }
                if (isset($apartmentData['floor_number']) && !empty($apartmentData['floor_number'])) {
                    $updateFields[] = ['field_id' => 977283, 'values' => [['value' => $apartmentData['floor_number']]]];
                }
            } else {
                safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] ВНИМАНИЕ: Квартира с realty_id $apartmentId НЕ НАЙДЕНА в БД! Дополнительные поля не будут заполнены.\n");
            }
            $stmt->close();
        } else {
            safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Ошибка подготовки запроса: " . $db->error . "\n");
        }
        }
    } else {
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] ВНИМАНИЕ: Бронь $bookingNumber не содержит apartment_id! Дополнительные поля не будут заполнены.\n");
    }
    
    // Формируем итоговый массив для обновления сделки
    $updateData = [
        'custom_fields_values' => $updateFields
    ];
    
    safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Сделка ID $leadId: Данные обновления (источник: '$bookingSource'):\n" . json_encode($updateData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    
    try {
        $updateResponse = $amoCRM->call('PATCH', "leads/{$leadId}", $updateData);
        safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] Сделка ID $leadId: Ответ обновления сделки:\n" . print_r($updateResponse, true) . "\n");
        
        // Проверяем успешность обновления
        if (isset($updateResponse['_embedded']['leads'][0]['id'])) {
            safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] ✅ Сделка ID $leadId успешно обновлена (источник: '$bookingSource')\n");
        } else {
            safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] ❌ Ошибка обновления сделки ID $leadId (источник: '$bookingSource')\n");
        }
         } catch (Exception $e) {
         safeLog($logFile, "[" . date('Y-m-d H:i:s') . "] ❌ ИСКЛЮЧЕНИЕ при обновлении сделки ID $leadId (источник: '$bookingSource'): " . $e->getMessage() . "\n");
     }
}

// Очищаем буфер вывода
ob_clean();

// Отправляем правильные заголовки и ответ
$processedLeads = is_array($leadsArray) ? count($leadsArray) : 0;
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'success', 'message' => 'Webhook обработан', 'processed_leads' => $processedLeads], JSON_UNESCAPED_UNICODE);
} else {
    // Если заголовки уже отправлены, просто выводим JSON
    echo json_encode(['status' => 'success', 'message' => 'Webhook обработан', 'processed_leads' => $processedLeads], JSON_UNESCAPED_UNICODE);
}
exit;
