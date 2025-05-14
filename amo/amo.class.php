<?php

class AmoCRM {
    private string $accessToken;
    private string $subdomain;
    private $session;

    const BASE_URL = 'https://{subdomain}.amocrm.ru/api/v4';

    public function __construct(string $subdomain) {
        $this->subdomain = $subdomain;

        $this->session = curl_init();
        curl_setopt_array($this->session, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_TCP_FASTOPEN => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
        ]);
    }

    public function setToken(string $token) {
        $this->accessToken = $token;
    }

    public function call(string $type, string $method, array $data = []) {
        // Подставляем поддомен в базовый URL
        $endpoint = str_replace('{subdomain}', $this->subdomain, self::BASE_URL) . '/' . $method;

        // Очистка и сброс cURL настроек перед каждым запросом
        curl_reset($this->session);
        curl_setopt($this->session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->session, CURLOPT_ENCODING, '');
        curl_setopt($this->session, CURLOPT_TCP_FASTOPEN, true);
        curl_setopt($this->session, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        if ($type === 'GET' && !empty($data)) {
            // Добавляем параметры в URL для GET-запросов
            $endpoint .= '?' . http_build_query($data);
        }

        // Общие опции cURL
        $options = [
            CURLOPT_URL => $endpoint,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->accessToken
            ],
        ];

        if ($type !== 'GET') {
            $options[CURLOPT_CUSTOMREQUEST] = $type;
            if (!empty($data)) {
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
            } else {
                // Очистка POSTFIELDS, если данных нет
                curl_setopt($this->session, CURLOPT_POSTFIELDS, null);
            }
        }

        // Устанавливаем опции и выполняем запрос
        curl_setopt_array($this->session, $options);

        $responseJSON = curl_exec($this->session);
        $response = json_decode($responseJSON, true);

        // Проверка на ошибки cURL
        if (curl_errno($this->session)) {
            throw new Exception('cURL Error: ' . curl_error($this->session));
        }

        // Возвращаем результат
        return $response;
    }

    public function getLeadById(int $leadId) {
        return $this->call('GET', "leads/$leadId", ['with' => 'contacts']);
    }

    public function getContactById(int $contactId) {
        return $this->call('GET', "contacts/$contactId");
    }

    public function getCustomFieldValue(array $leadData, $field) {
        $values = [];

        if (!isset($leadData['custom_fields_values'])) {
            return $values; // Возвращаем пустой массив, если нет custom fields
        }

        foreach ($leadData['custom_fields_values'] as $customField) {
            if ((is_int($field) && $customField['field_id'] === $field) ||
                (is_string($field) && $customField['field_name'] === $field)) {
                
                foreach ($customField['values'] as $value) {
                    $values[] = $value['value'];
                }
                return $values; // Возвращаем массив значений
            }
        }

        return $values; // Если поле не найдено, возвращаем пустой массив
    }

    public function addNoteToLead(int $leadId, string $noteText) {
        $data = [
            [
                'note_type' => 'common',  // The type of the note (common is used here)
                'params' => [
                    'text' => $noteText  // The content of the note
                ]
            ]
        ];

        // Convert the data array to JSON
        // $data = json_encode($data);

        // Make the request to add the note
        return $this->call('POST', "leads/$leadId/notes", $data);
    }

    /*public function moveLead(int $leadId, int $statusId, int $pipelineId = null) {
        // Формируем данные для запроса
        $data = [
            [
                'id' => $leadId,
                'status_id' => $statusId,
            ]
        ];

        if ($pipelineId !== null) {
            $data['pipeline_id'] = (int) $pipelineId;
        }

        curl_setopt($this->session, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        // Выполняем PATCH запрос для перемещения сделки
        curl_setopt($this->session, CURLOPT_URL, 'https://' . $this->subdomain . '.amocrm.ru/api/v4/leads');
        curl_setopt($this->session, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->accessToken
        ]);
        curl_setopt($this->session, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($this->session, CURLOPT_POSTFIELDS, json_encode($data));

        $responseJSON = curl_exec($this->session);
        $response = json_decode($responseJSON, true);

        // Проверка на ошибки cURL
        if (curl_errno($this->session)) {
            throw new Exception('cURL Error: ' . curl_error($this->session));
        }

        return $response;
    }*/

    public function moveLeads(array $leads) {
        // Prepare the data for the request
        $data = [];

        foreach ($leads as $leadId => $statusId) {
            $data[] = [
                'id' => (int)$leadId,
                'status_id' => (int)$statusId,
            ];
        }

        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Prepare the HTTP context for the request
        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n" .
                             "Authorization: Bearer " . $this->accessToken . "\r\n",
                'method'  => 'PATCH',
                'content' => $jsonData,
                'ignore_errors' => true // Get the response even on error
            ]
        ];
        $context  = stream_context_create($options);

        // Send the request using file_get_contents
        $url = 'https://' . $this->subdomain . '.amocrm.ru/api/v4/leads';
        $result = file_get_contents($url, false, $context);

        if ($result === FALSE) {
            throw new Exception('Error processing the request.');
        }

        // Decode the JSON response
        $response = json_decode($result, true);

        // Return the decoded response
        return $response;
    }
}

/*$subdomain = 'maratmost1';
$amoCRM = new AmoCRM($subdomain);
$amoCRM->setToken("eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImEzNjg1ZGM3NGI1NGQyNTY2MDUwOWNhNDljYWVkNzYyYzZkZjQxYjRiYTI0ZTkyMTQzMmQ5MjY1ZTE1NDJlOTliM2VjOWNiNWJiYjEwZTkzIn0.eyJhdWQiOiI2NWNhYTdjNy0zYTAxLTRmNmItODk3MS04ZGQ4OWE2ZDg4OTMiLCJqdGkiOiJhMzY4NWRjNzRiNTRkMjU2NjA1MDljYTQ5Y2FlZDc2MmM2ZGY0MWI0YmEyNGU5MjE0MzJkOTI2NWUxNTQyZTk5YjNlYzljYjViYmIxMGU5MyIsImlhdCI6MTc0MTA1NzcyNywibmJmIjoxNzQxMDU3NzI3LCJleHAiOjE4ODYzNzEyMDAsInN1YiI6IjEyMTUyNDM0IiwiZ3JhbnRfdHlwZSI6IiIsImFjY291bnRfaWQiOjMyMjQ3MDEwLCJiYXNlX2RvbWFpbiI6ImFtb2NybS5ydSIsInZlcnNpb24iOjIsInNjb3BlcyI6WyJjcm0iLCJmaWxlcyIsImZpbGVzX2RlbGV0ZSIsIm5vdGlmaWNhdGlvbnMiLCJwdXNoX25vdGlmaWNhdGlvbnMiXSwiaGFzaF91dWlkIjoiZTdjNWQ0NGYtYjFiMy00YTVmLWEwODUtOTUzOGRhYjIzZDU2IiwiYXBpX2RvbWFpbiI6ImFwaS1iLmFtb2NybS5ydSJ9.c3P07oYaBb3rq5MVYXuDAAivh7gY1kkZtgHDMMtSsAohlqQajSrztedOQBEWTmnSq_289-cJ1QdLW8qtrEqAy6txomnmCTMIrKiGHC0RpWIoUFhq4VcTuccDu-KQQU8ROOY5wXJnfKVfsOc6GUa6Bf8s_-pwVAqjPyGfvmg3pzdLw--OAF9ALiOyeNkRc2Ci5lSCYs095x8CpHGwrqxsiUhAaxuHO7xJwtfQwPLoqJxf24IS45Gj_g8nduo7YwZ-B_ru5_4lFhVSUEXoo8wuyW_2O0_llhb5-6Ek_Ne0Luiq2a_3Dd7x2wrzvUtAsxH0BdLtS-Jbvtmhd1XiH7ukMA");

$e = $amoCRM->getLeadById(33058363);
var_dump($e);*/