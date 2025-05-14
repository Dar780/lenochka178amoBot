<?php

require_once(__DIR__.'/amo.class.php');
require_once(__DIR__.'/cooper.class.php');
require_once __DIR__.'/../sbm/SberMarketCRM.php';

$crm = new SberMarketCRM(
    __DIR__ . '/../sbm/tokens.json',
    'https://crm-gw.sbermarket.ru/auth/v1/refresh',
    'crm_Xx95aBKc8YXAkAPnMGBKN5e7yeLQFS90qzYYF0AxrxHzv1OYdAydvyh39FxjJLeP',
    ''
);

$token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJmYzI4MTRhZTFhNjJhNjMyNzliYjM1ZmUxZmVmYzNiMmZhN2MyMTM5MTI0OWNkNjU3ODdhY2E0MjFhNWRiZTg4OGFiYjdhZDQ0NjI5OWQwIn0.eyJhdWQiOiIzZjA4YjcxYy1iYTRjLTRlYzUtOGNhMS03YjMyMzk1Y2MwZDciLCJqdGkiOiJiZmMyODE0YWUxYTYyYTYzMjc5YmIzNWZlMWZlZmMzYjJmYTdjMjEzOTEyNDljZDY1Nzg3YWNhNDIxYTVkYmU4ODhhYmI3YWQ0NDYyOTlkMCIsImlhdCI6MTczMjU1MzM0MCwibmJmIjoxNzMyNTUzMzQwLCJleHAiOjE4OTAxNzI4MDAsInN1YiI6IjExMjgzNTMwIiwiZ3JhbnRfdHlwZSI6IiIsImFjY291bnRfaWQiOjMxODUyODQ2LCJiYXNlX2RvbWFpbiI6ImFtb2NybS5ydSIsInZlcnNpb24iOjIsInNjb3BlcyI6WyJjcm0iLCJmaWxlcyIsImZpbGVzX2RlbGV0ZSIsIm5vdGlmaWNhdGlvbnMiLCJwdXNoX25vdGlmaWNhdGlvbnMiXSwiaGFzaF91dWlkIjoiNjMwYmZlNGItYjQ2Yy00MTg4LTkzZTEtMmEwOGYzNzc0Y2Y4IiwiYXBpX2RvbWFpbiI6ImFwaS1iLmFtb2NybS5ydSJ9.nBwyUVHHblJKqjYCDawWmkOEVT8QOPvZGlda-MQJk160WB-ZBrtcOJ_M9VpBh5aR1XyhnfaDTu4FbEcEeQyBaj2UalXv3u7ghGPLw4kIL3IGuAAGn-Tp0KFcAi52rmQJ0V0Va288eX23xAl78Yn1fbuj1YUFPW6SZWdrIC4Ek0RDkuvTLJl1f_mREK5eQXK6yBsgukhDLII2LPM1Be_ImVYpeHV0RD5m3C9CFUwrxW4ujmFnu2cCTFEnwJE2frIlt1TZWVdXDKopaw50DNpr8HHESMTLoZEYuwD27ln5ibVqbjMKTGkDJyXMcK1j5j2i_zskxMPpe4Ogkrj3Ut8QLA'; 

$isCli = php_sapi_name() === 'cli';
$logFile = __DIR__ . '/combined_log.txt';

if (!$isCli) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        die(json_encode(['status' => 'error', 'message' => 'Invalid request method. Only POST is allowed.']) . PHP_EOL);
    }

    $postData = $_POST;

    if (empty($postData)) {
        parse_str(file_get_contents('php://input'), $postData);
    }

    if (empty($postData)) {
        die(json_encode(['status' => 'error', 'message' => 'No data received in the POST request.']) . PHP_EOL);
    }
} else {
    $postData = [
        'leads' => [
            'status' => [
                [
                    'id' => 13438961,
                    'status_id' => 68880494,
                    'pipeline_id' => 8470330,
                    'old_status_id' => 68880678,
                    'old_pipeline_id' => 8470330,
                ]
            ]
        ],
        'account' => [
            'id' => 31852846,
            'subdomain' => 'argospark',
        ],
    ];
}

$subdomain = $postData['account']['subdomain'] ?? null;

if (!$subdomain) {
    die(json_encode(['status' => 'error', 'message' => 'Subdomain is missing in the webhook payload.']) . PHP_EOL);
}

$amoCRM = new AmoCRM($subdomain);
$amoCRM->setToken($token);

$leadsToImport = [];

foreach ($postData['leads']['status'] as $leadStatus) {
    $leadId = (int)$leadStatus['id'];

    // Fetch the lead data from amoCRM
    $lead = $amoCRM->getLeadById($leadId, ['with' => 'contacts']);
    
    // Ensure we have contact data
    if (!isset($lead['_embedded']['contacts'][0]['id'])) {
        die(json_encode(['status' => 'error', 'message' => 'Contact ID is missing in the lead data.']) . PHP_EOL);
    }

    // Extract the contact ID
    $contactId = (int)$lead['_embedded']['contacts'][0]['id'];
    $contact = $amoCRM->getContactById($contactId);

    // Extract first name, last name, and fallback to the full name if both are missing
    $firstName = isset($contact['first_name']) && !empty($contact['first_name']) ? $contact['first_name'] : NULL;
    $lastName = isset($contact['last_name']) && !empty($contact['last_name']) ? $contact['last_name'] : NULL;
    if ($firstName == NULL && $lastName == NULL) {
        $firstName = !empty($contact['name']) ? $contact['name'] : 'Нет имени';
    }

    // Extract phone number, if available
    $phone = null;
    if (isset($contact['custom_fields_values']) && is_array($contact['custom_fields_values'])) {
        foreach ($contact['custom_fields_values'] as $field) {
            if (isset($field['field_code']) && $field['field_code'] == 'PHONE') {
                if (isset($field['values'][0]['value'])) {
                    $phone = $field['values'][0]['value'];
                    break;
                }
            }
        }
    }

    // Clean the phone number by removing non-numeric characters
    if ($phone) {
        $phone = preg_replace('/\D/', '', $phone); // Removes all non-numeric characters

        // If the phone number starts with 8, change it to 7
        if ($phone[0] == '8') {
            $phone[0] = '7';
        }
    }

    // $phone = '79999999994';

    // Log lead and contact data for debugging
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Lead data: " . print_r($lead, true) . PHP_EOL, FILE_APPEND);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Contact data: " . print_r($contact, true) . PHP_EOL, FILE_APPEND);

    $response = $crm->sendRequest(
        'https://crm-gw.sbermarket.ru/partner-candidate/v1/candidates',
        'POST',
        [
            'phone' => $phone,
            'source_id' => '',
            'city_id' => 80,
            'vacancy_id' => 14,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'middle_name' => '',
        ]
    );

    // print_r($response);

    if (isset($response['error'])) {
        if (isset($response['error']['detail'])) {
            $message = $response['error']['detail'];
        } else {
            $message = $response['detail'];
        }
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Lead ID: {$leadId}, Contact ID: {$contactId}, Message: {$response['error']['detail']}" . PHP_EOL, FILE_APPEND);

        $message = $response['error']['detail'];
        $noteText = "Импорт в Купер (неуспешно):\n{$message}";
        $amoCRM->addNoteToLead($leadId, $noteText);

    } elseif (isset($response['candidate_id'])) {
        $lr = print_r($response, true);
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Lead ID: {$leadId}, Contact ID: {$contactId}, Response: {$lr}" . PHP_EOL, FILE_APPEND);

        $message = $response['candidate_id'] ?? '-';
        $noteText = "Импорт в Купер (успешно):\n{$message}";
        $amoCRM->addNoteToLead($leadId, $noteText);
    } else {
        $lr = print_r($response, true);
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Lead ID: {$leadId}, Contact ID: {$contactId}, Response Unforeseen: {$lr}" . PHP_EOL, FILE_APPEND);
        $message = 'Произошла непредвиденная ошибка';
        $noteText = "Импорт в Купер (неуспешно):\n{$message}";
        $amoCRM->addNoteToLead($leadId, $noteText);
    }

}


// Success response to the webhook
echo json_encode(['status' => 'success', 'message' => 'Webhook processed successfully']) . PHP_EOL;
