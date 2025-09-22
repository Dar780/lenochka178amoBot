<?php

require_once(__DIR__ . '/amo.class.php');
require_once(__DIR__ . '/config.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

$logFile = __DIR__ . '/checkin_cron.log';
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Daily check-in CRON job started\n", FILE_APPEND);

// Initialize AmoCRM API
$subdomain = 'lenasutochno178';
$token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImEzNjg1ZGM3NGI1NGQyNTY2MDUwOWNhNDljYWVkNzYyYzZkZjQxYjRiYTI0ZTkyMTQzMmQ5MjY1ZTE1NDJlOTliM2VjOWNiNWJiYjEwZTkzIn0.eyJhdWQiOiI2NWNhYTdjNy0zYTAxLTRmNmItODk3MS04ZGQ4OWE2ZDg4OTMiLCJqdGkiOiJhMzY4NWRjNzRiNTRkMjU2NjA1MDljYTQ5Y2FlZDc2MmM2ZGY0MWI0YmEyNGU5MjE0MzJkOTI2NWUxNTQyZTk5YjNlYzljYjViYmIxMGU5MyIsImlhdCI6MTc0MTA1NzcyNywibmJmIjoxNzQxMDU3NzI3LCJleHAiOjE4ODYzNzEyMDAsInN1YiI6IjEyMTUyNDM0IiwiZ3JhbnRfdHlwZSI6IiIsImFjY291bnRfaWQiOjMyMjQ3MDEwLCJiYXNlX2RvbWFpbiI6ImFtb2NybS5ydSIsInZlcnNpb24iOjIsInNjb3BlcyI6WyJjcm0iLCJmaWxlcyIsImZpbGVzX2RlbGV0ZSIsIm5vdGlmaWNhdGlvbnMiLCJwdXNoX25vdGlmaWNhdGlvbnMiXSwiaGFzaF91dWlkIjoiZTdjNWQ0NGYtYjFiMy00YTVmLWEwODUtOTUzOGRhYjIzZDU2IiwiYXBpX2RvbWFpbiI6ImFwaS1iLmFtb2NybS5ydSJ9.c3P07oYaBb3rq5MVYXuDAAivh7gY1kkZtgHDMMtSsAohlqQajSrztedOQBEWTmnSq_289-cJ1QdLW8qtrEqAy6txomnmCTMIrKiGHC0RpWIoUFhq4VcTuccDu-KQQU8ROOY5wXJnfKVfsOc6GUa6Bf8s_-pwVAqjPyGfvmg3pzdLw--OAF9ALiOyeNkRc2Ci5lSCYs095x8CpHGwrqxsiUhAaxuHO7xJwtfQwPLoqJxf24IS45Gj_g8nduo7YwZ-B_ru5_4lFhVSUEXoo8wuyW_2O0_llhb5-6Ek_Ne0Luiq2a_3Dd7x2wrzvUtAsxH0BdLtS-Jbvtmhd1XiH7ukMA";

$amoCRM = new AmoCRM($subdomain);
$amoCRM->setToken($token);

// Define status stage IDs
$NEED_INSTRUCTION_STAGE_ID = 76985442;       // "Отложенная инструкция" stage ID
$SEND_INSTRUCTION_STAGE_ID = 74364966;       // "Отправка инструкции" stage ID
$PIPELINE_ID = 9266190;                      // RealtyCalendar pipeline ID
$CHECK_IN_DATE_FIELD_ID = 833655;            // check-in date field ID

// Статусы, которые нужно исключить из любой автоматической обработки
$EXCLUDED_STATUS_IDS = [77524106, 76864146, 79570730, 79570734, 79893902];

// Function to get all open leads in a specific pipeline
function getOpenLeadsInPipeline($amoCRM, $pipelineId) {
    // This is a simplified approach - in reality, you'd need to paginate through all leads
    // The actual implementation would depend on AmoCRM's API limits and your specific needs
    
    // Get leads from the pipeline (you may need to adjust this based on the actual API method)
    $page = 1;
    $limit = 250; // Adjust based on API limits
    $allLeads = [];
    
    do {
        $params = [
            'filter' => [
                'pipeline_id' => $pipelineId
            ],
            'page' => $page,
            'limit' => $limit,
        ];
        
        $response = $amoCRM->call('GET', 'leads', $params);
        
        if (!isset($response['_embedded']['leads']) || empty($response['_embedded']['leads'])) {
            break;
        }
        
        $allLeads = array_merge($allLeads, $response['_embedded']['leads']);
        $page++;
        
        // Break if we've got fewer leads than the limit (meaning it's the last page)
        if (count($response['_embedded']['leads']) < $limit) {
            break;
        }
        
    } while (true);
    
    return $allLeads;
}

try {
    // Get all open leads in the RealtyCalendar pipeline
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Fetching open leads in pipeline $PIPELINE_ID\n", FILE_APPEND);
    $openLeads = getOpenLeadsInPipeline($amoCRM, $PIPELINE_ID);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Found " . count($openLeads) . " leads in the pipeline\n", FILE_APPEND);
    
    if (empty($openLeads)) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] No open leads found. Exiting.\n", FILE_APPEND);
        exit;
    }
    
    // Process each lead
    $leadsToMove = [];
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    foreach ($openLeads as $lead) {
        $leadId = (int)$lead['id'];

        // Пропускаем сделки из исключённых статусов
        if (isset($lead['status_id']) && in_array((int)$lead['status_id'], $EXCLUDED_STATUS_IDS, true)) {
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] SKIP: Lead $leadId is in an excluded status (" . $lead['status_id'] . ")\n", FILE_APPEND);
            continue;
        }

        // Get check-in date value
        $checkInDateValues = $amoCRM->getCustomFieldValue($lead, $CHECK_IN_DATE_FIELD_ID);
        
        if (empty($checkInDateValues)) {
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] No check-in date for lead ID: $leadId\n", FILE_APPEND);
            continue;
        }
        
        $checkInDate = $checkInDateValues[0];
        
        // Проверяем, является ли значение числовым (timestamp)
        if (is_numeric($checkInDate)) {
            // AmoCRM передает timestamp в UTC, но нам нужно перевести его в московское время
            // Добавляем смещение для московской временной зоны (UTC+3)
            $checkInDate = date('Y-m-d', $checkInDate + (3*3600)); // +3 часа в секундах
        } elseif (strpos($checkInDate, ' ') !== false) {
            // Если дата содержит пробел (формат с временем), извлекаем только дату
            $checkInDate = explode(' ', $checkInDate)[0];
        }
        
        // Добавляем отладочный вывод
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] DEBUG: Timestamp: $checkInDateValues[0], После преобразования: $checkInDate, Tomorrow: $tomorrow\n", FILE_APPEND);
        
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Lead $leadId check-in date: $checkInDate, Tomorrow: $tomorrow\n", FILE_APPEND);
        
        // Check if the check-in date is tomorrow
        if ($checkInDate === $tomorrow) {
            // Move to "Отправка инструкции" stage
            $leadsToMove[$leadId] = $SEND_INSTRUCTION_STAGE_ID;
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Lead $leadId has check-in tomorrow, moving to 'Отправка инструкции'\n", FILE_APPEND);
        }
        // We don't move leads to "Отложенная инструкция" in the daily cron job, only in the webhook handler
    }
    
    // Process lead movements if any
    if (!empty($leadsToMove)) {
        $moveResponse = $amoCRM->moveLeads($leadsToMove);
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Leads movement response:\n" . print_r($moveResponse, true) . "\n", FILE_APPEND);
    } else {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] No leads to move.\n", FILE_APPEND);
    }
    
} catch (Exception $e) {
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n", FILE_APPEND);
}

file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Daily check-in CRON job completed\n", FILE_APPEND);
echo "Done\n"; 