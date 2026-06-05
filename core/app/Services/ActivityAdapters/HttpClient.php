<?php
declare(strict_types=1);

namespace App\Services\ActivityAdapters;

final class HttpClient
{
    public function getJson(string $url, array $headers = [], int $timeout = 15): array
    {
        $body = $this->request('GET', $url, $headers, null, $timeout);
        if ($body === '') {
            return [];
        }
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }

    public function postForm(string $url, array $fields, array $headers = [], int $timeout = 15): array
    {
        $body = $this->request('POST', $url, array_merge([
            'Content-Type: application/x-www-form-urlencoded',
        ], $headers), http_build_query($fields), $timeout);
        if ($body === '') {
            return [];
        }
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }

    public function getText(string $url, array $headers = [], int $timeout = 15): string
    {
        return $this->request('GET', $url, $headers, null, $timeout);
    }

    private function request(string $method, string $url, array $headers, ?string $body, int $timeout): string
    {
        $headers = array_merge([
            'Accept: application/json,text/plain,*/*',
            'User-Agent: LiteNote ActivitySync/1.0',
        ], $headers);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $response = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            unset($ch);
            return is_string($response) && $status >= 200 && $status < 300 ? $response : '';
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'timeout' => $timeout,
                'ignore_errors' => false,
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $body ?? '',
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        return is_string($response) ? $response : '';
    }
}
