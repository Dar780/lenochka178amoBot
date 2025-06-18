<?php

class RealtyCalendar {
    private string $baseUrl;
    private string $userToken;
    
    public function __construct(string $userToken, string $baseUrl = 'https://realtycalendar.ru/v2/event_calendars')
    {
        $this->userToken = $userToken;
        // Убираем конечный слэш, если он есть
        $this->baseUrl = rtrim($baseUrl, '/');
    }
    
    /**
     * Выполняет GET-запрос и возвращает результат в виде массива.
     *
     * @param string $url
     * @return array
     * @throws Exception
     */
    private function request(string $url): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:126.0) Gecko/20100101 Firefox/126.0 Herring/91.1.1890.10',
            'Accept: */*',
            'Accept-Language: en-US,en;q=0.5',
            'Referer: ' . $url,
            'X-Locale: ru',
            'X-User-Token: ' . $this->userToken,
            'Content-Type: application/json',
            'DNT: 1',
            'Sec-GPC: 1',
            'Connection: keep-alive'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Curl error: $error");
        }
        curl_close($ch);
        
        $data = json_decode($response, true);
        return $data ?? [];
    }
    
    /**
     * Получает общую информацию по брони.
     *
     * @param string|int $bookingNumber
     * @return array
     */
    public function getBookingInfo($bookingNumber): array
    {
        $url = $this->baseUrl . '/' . $bookingNumber;
        return $this->request($url);
    }
    
    /**
     * Получает сведения о платежах по брони.
     *
     * @param string|int $bookingNumber
     * @return array
     */
    public function getPaymentInfo($bookingNumber): array
    {
        $url = $this->baseUrl . '/' . $bookingNumber . '/payments';
        return $this->request($url);
    }
}


/*$e = new RealtyCalendar('RYvxEsd46T9gycFUzpe8');
$f = $e->getBookingInfo(101834488);
var_dump($f);*/