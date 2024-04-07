<?php

namespace Zeynallow\Booknetic;

use Zeynallow\Booknetic\Exceptions\BookneticException;

class Booknetic 
{
    private $sandboxUrl;

    public function __construct()
    {
        $this->sandboxUrl = $_ENV["BOOKNETIC_SANDBOX_URL"];
    }

    public function getServices(): array
    {
        $services = [];

        $result = $this->request('bkntc_get_data_service', [
            'tenant_id' => 3,
            'staff' => -1,
            'location' => -1,
        ]);

        if (isset($result['error_msg'])) {
            throw new BookneticException($result['error_msg']);
        }

        $servicesData = $result['html'] ?? null;

        if (!is_null($servicesData)) {
            $services = $this->extractServices($servicesData);
        }

        return $services;
    }

    public function getTimes(int $serviceId, string $date): array
    {
        $times = [];

        $result = $this->request('bkntc_get_data_date_time', [
            'tenant_id' => 3,
            'staff' => -1,
            'location' => -1,
            'cart' => json_encode([['service' => $serviceId]], true),
        ]);

        if (isset($result['error_msg'])) {
            throw new BookneticException($result['error_msg']);
        }

        $dates = $result['data']['dates'][$date] ?? [];

        foreach ($dates as $date) {
            $times[] = [
                'start_time' => $date['start_time'],
                'end_time' => $date['end_time'],
            ];
        }

        return $times;
    }

    public function confirmBooking(int $serviceId, string $date, string $time, array $customerData): array
    {
        $result = $this->request('bkntc_confirm', [
            'tenant_id' => 3,
            'payment_method' => 'local',
            'cart' => json_encode([[
                'service' => $serviceId,
                'date' => $date,
                'time' => $time,
                'staff' => -1,
                'location' => -1,
                'customer_data' => $customerData,
            ]], true),
        ]);

        if (isset($result['error_msg'])) {
            throw new BookneticException($result['error_msg']);
        }

        return [
            'booking_id' => $result['id'] ?? null,
            'google_calendar' => $result['google_calendar_url'] ?? null,
        ];
    }

    private function request(string $action, array $payload): array
    {
        
        $payload['action'] = $action;

        $ch = curl_init($this->sandboxUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new BookneticException("cURL Error: $error");
        }

        return json_decode($response, true) ?: [];
    }

    private function extractServices(string $html): array
    {
        $services = [];
        $dom = new \DOMDocument();
        $dom->loadHTML(htmlspecialchars_decode($html));
        $xpath = new \DOMXPath($dom);
        $divs = $xpath->query("//div[@class='booknetic_service_card demo booknetic_fade']");

        foreach ($divs as $div) {
            $title = $xpath->query(".//span[@class='booknetic_service_title_span']", $div)[0]->nodeValue;
            $id = $div->getAttribute('data-id');
            $price = $xpath->query('.//div[contains(@class,"booknetic_service_card_price")]', $div)[0]->nodeValue;

            $services[] = [
                'id' => trim($id) ?? null,
                'title' => trim($title) ?? null,
                'price' => trim($price) ?? null,
            ];
        }

        return $services;
    }
}
