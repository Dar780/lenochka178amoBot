<?php

require_once(__DIR__ . '/amo.class.php');
require_once(__DIR__ . '/config.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

$logFile = __DIR__ . '/checkin_webhook.log';
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Webhook received\n", FILE_APPEND);

// Validate the request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Only POST requests are allowed']);
    exit;
}

// Get and validate webhook data
$postData = $_POST;
if (empty($postData)) {
    parse_str(file_get_contents('php://input'), $postData);
}

if (empty($postData)) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'No data received']);
    exit;
}

// Log incoming webhook data
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Webhook data:\n" . print_r($postData, true) . "\n", FILE_APPEND);

// Initialize AmoCRM API
$subdomain = 'lenasutochno178';
$token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImEzNjg1ZGM3NGI1NGQyNTY2MDUwOWNhNDljYWVkNzYyYzZkZjQxYjRiYTI0ZTkyMTQzMmQ5MjY1ZTE1NDJlOTliM2VjOWNiNWJiYjEwZTkzIn0.eyJhdWQiOiI2NWNhYTdjNy0zYTAxLTRmNmItODk3MS04ZGQ4OWE2ZDg4OTMiLCJqdGkiOiJhMzY4NWRjNzRiNTRkMjU2NjA1MDljYTQ5Y2FlZDc2MmM2ZGY0MWI0YmEyNGU5MjE0MzJkOTI2NWUxNTQyZTk5YjNlYzljYjViYmIxMGU5MyIsImlhdCI6MTc0MTA1NzcyNywibmJmIjoxNzQxMDU3NzI3LCJleHAiOjE4ODYzNzEyMDAsInN1YiI6IjEyMTUyNDM0IiwiZ3JhbnRfdHlwZSI6IiIsImFjY291bnRfaWQiOjMyMjQ3MDEwLCJiYXNlX2RvbWFpbiI6ImFtb2NybS5ydSIsInZlcnNpb24iOjIsInNjb3BlcyI6WyJjcm0iLCJmaWxlcyIsImZpbGVzX2RlbGV0ZSIsIm5vdGlmaWNhdGlvbnMiLCJwdXNoX25vdGlmaWNhdGlvbnMiXSwiaGFzaF91dWlkIjoiZTdjNWQ0NGYtYjFiMy00YTVmLWEwODUtOTUzOGRhYjIzZDU2IiwiYXBpX2RvbWFpbiI6ImFwaS1iLmFtb2NybS5ydSJ9.c3P07oYaBb3rq5MVYXuDAAivh7gY1kkZtgHDMMtSsAohlqQajSrztedOQBEWTmnSq_289-cJ1QdLW8qtrEqAy6txomnmCTMIrKiGHC0RpWIoUFhq4VcTuccDu-KQQU8ROOY5wXJnfKVfsOc6GUa6Bf8s_-pwVAqjPyGfvmg3pzdLw--OAF9ALiOyeNkRc2Ci5lSCYs095x8CpHGwrqxsiUhAaxuHO7xJwtfQwPLoqJxf24IS45Gj_g8nduo7YwZ-B_ru5_4lFhVSUEXoo8wuyW_2O0_llhb5-6Ek_Ne0Luiq2a_3Dd7x2wrzvUtAsxH0BdLtS-Jbvtmhd1XiH7ukMA";

$amoCRM = new AmoCRM($subdomain);
$amoCRM->setToken($token);

// Define status stage IDs
$NEED_INSTRUCTION_STAGE_ID = 76985442;       // "Нужна инструкция" stage ID
$SEND_INSTRUCTION_STAGE_ID = 74364966;       // "Отправка инструкции" stage ID
$PIPELINE_ID = 9266190;                       // RealtyCalendar pipeline ID
$CHECK_IN_DATE_FIELD_ID = 833655;            // check-in date field ID

// Extract lead IDs from the webhook
$leadsArray = [];
if (isset($postData['leads']['add'])) {
    $leadsArray = array_merge($leadsArray, $postData['leads']['add']);
}
if (isset($postData['leads']['status'])) {
    $leadsArray = array_merge($leadsArray, $postData['leads']['status']);
}
if (isset($postData['leads']['update'])) {
    $leadsArray = array_merge($leadsArray, $postData['leads']['update']);
}

// Process each lead
$leadsToMove = [];
$tomorrow = date('Y-m-d', strtotime('+1 day'));

foreach ($leadsArray as $lead) {
    $leadId = (int)$lead['id'];
    
    try {
        // Get lead details including custom fields
        $leadData = $amoCRM->getLeadById($leadId);
        
        if (!$leadData) {
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Failed to get lead data for ID: $leadId\n", FILE_APPEND);
            continue;
        }
        
        // Check pipeline ID
        if ($leadData['pipeline_id'] != $PIPELINE_ID) {
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Lead $leadId is not in the target pipeline\n", FILE_APPEND);
            continue;
        }
        
        // Get check-in date value
        $checkInDateValues = $amoCRM->getCustomFieldValue($leadData, $CHECK_IN_DATE_FIELD_ID);
        
        if (empty($checkInDateValues)) {
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] No check-in date for lead ID: $leadId\n", FILE_APPEND);
            continue;
        }
        
        $checkInDate = $checkInDateValues[0];
        
        // Format date if needed (assuming date is in YYYY-MM-DD format)
        if (strpos($checkInDate, ' ') !== false) {
            // If date has time component, extract just the date part
            $checkInDate = explode(' ', $checkInDate)[0];
        }
        
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Lead $leadId check-in date: $checkInDate, Tomorrow: $tomorrow\n", FILE_APPEND);
        
        // Check if the check-in date is tomorrow
        if ($checkInDate === $tomorrow) {
            // Move to "Отправка инструкции" stage
            $leadsToMove[$leadId] = $SEND_INSTRUCTION_STAGE_ID;
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Lead $leadId has check-in tomorrow, moving to 'Отправка инструкции'\n", FILE_APPEND);
        } else {
            // Move to "Нужна инструкция" stage
            $leadsToMove[$leadId] = $NEED_INSTRUCTION_STAGE_ID;
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Lead $leadId does not have check-in tomorrow, moving to 'Нужна инструкция'\n", FILE_APPEND);
        }
    } catch (Exception $e) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Error processing lead $leadId: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Process lead movements if any
if (!empty($leadsToMove)) {
    try {
        $moveResponse = $amoCRM->moveLeads($leadsToMove);
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Leads movement response:\n" . print_r($moveResponse, true) . "\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Error moving leads: " . $e->getMessage() . "\n", FILE_APPEND);
    }
} else {
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] No leads to move.\n", FILE_APPEND);
}

// Send response
header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'message' => 'Webhook processed']);
exit; 