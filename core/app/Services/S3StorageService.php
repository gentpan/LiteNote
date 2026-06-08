<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Http;

final class S3StorageService
{
    public function __construct(private array $config)
    {
    }

    public function testConnection(): array
    {
        $prefix = $this->prefix();
        $result = $this->request('GET', '', [
            'list-type' => '2',
            'max-keys' => '1',
            'prefix' => $prefix,
        ]);

        if (!$result['ok']) {
            throw new \RuntimeException($this->errorMessage($result, '连接失败'));
        }

        return [
            'status' => (int)$result['status'],
            'message' => $prefix !== '' ? '连接成功，前缀可访问：' . $prefix : '连接成功，桶可访问',
        ];
    }

    public function clearPrefix(int $limit = 10000): array
    {
        $deleted = 0;
        $token = '';

        do {
            $query = [
                'list-type' => '2',
                'max-keys' => '1000',
                'prefix' => $this->prefix(),
            ];
            if ($token !== '') {
                $query['continuation-token'] = $token;
            }
            $list = $this->request('GET', '', $query);
            if (!$list['ok']) {
                throw new \RuntimeException($this->errorMessage($list, '读取对象列表失败'));
            }

            $keys = $this->parseObjectKeys((string)$list['body']);
            if ($keys === []) {
                break;
            }

            foreach (array_chunk($keys, 1000) as $chunk) {
                $delete = $this->deleteObjects($chunk);
                if (!$delete['ok']) {
                    throw new \RuntimeException($this->errorMessage($delete, '删除对象失败'));
                }
                $deleted += count($chunk);
                if ($deleted >= $limit) {
                    return [
                        'deleted' => $deleted,
                        'truncated' => true,
                    ];
                }
            }

            $token = $this->parseNextContinuationToken((string)$list['body']);
        } while ($token !== '');

        return [
            'deleted' => $deleted,
            'truncated' => false,
        ];
    }

    public function clearCommand(): string
    {
        $endpoint = rtrim($this->required('attachment_s3_endpoint'), '/');
        $bucket = $this->required('attachment_s3_bucket');
        $prefix = $this->prefix();
        $target = 's3://' . $bucket . ($prefix !== '' ? '/' . $prefix : '');

        return 'AWS_ACCESS_KEY_ID=' . escapeshellarg($this->required('attachment_s3_access_key'))
            . ' AWS_SECRET_ACCESS_KEY=' . escapeshellarg($this->required('attachment_s3_secret_key'))
            . ' aws s3 rm ' . escapeshellarg($target)
            . ' --recursive --endpoint-url ' . escapeshellarg($endpoint);
    }

    public function putObject(string $key, string $body, string $contentType = 'application/octet-stream'): array
    {
        $key = ltrim($key, '/');
        if ($key === '') {
            throw new \InvalidArgumentException('对象路径不能为空');
        }
        $result = $this->request('PUT', $key, [], $body, [
            'Content-Type: ' . $contentType,
        ]);
        if (!$result['ok']) {
            throw new \RuntimeException($this->errorMessage($result, '上传对象失败'));
        }
        return [
            'key' => $key,
            'status' => (int)$result['status'],
        ];
    }

    /**
     * @return array<int,string>
     */
    public function listKeys(string $prefix): array
    {
        $keys = [];
        $token = '';
        do {
            $query = [
                'list-type' => '2',
                'max-keys' => '1000',
                'prefix' => trim($prefix, '/'),
            ];
            if ($token !== '') {
                $query['continuation-token'] = $token;
            }
            $list = $this->request('GET', '', $query);
            if (!$list['ok']) {
                throw new \RuntimeException($this->errorMessage($list, '读取对象列表失败'));
            }
            $keys = array_merge($keys, $this->parseObjectKeys((string)$list['body']));
            $token = $this->parseNextContinuationToken((string)$list['body']);
        } while ($token !== '');

        return array_values(array_unique($keys));
    }

    public function deleteKeys(array $keys): int
    {
        $deleted = 0;
        foreach (array_chunk(array_values(array_unique(array_filter($keys))), 1000) as $chunk) {
            $delete = $this->deleteObjects($chunk);
            if (!$delete['ok']) {
                throw new \RuntimeException($this->errorMessage($delete, '删除对象失败'));
            }
            $deleted += count($chunk);
        }
        return $deleted;
    }

    private function deleteObjects(array $keys): array
    {
        $objects = '';
        foreach ($keys as $key) {
            $objects .= '<Object><Key>' . htmlspecialchars($key, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</Key></Object>';
        }
        $body = '<?xml version="1.0" encoding="UTF-8"?><Delete xmlns="http://s3.amazonaws.com/doc/2006-03-01/">' . $objects . '</Delete>';

        return $this->request('POST', '', ['delete' => ''], $body, [
            'Content-Type: application/xml',
            'Content-MD5: ' . base64_encode(md5($body, true)),
        ]);
    }

    private function request(string $method, string $path = '', array $query = [], ?string $body = null, array $extraHeaders = []): array
    {
        $endpoint = rtrim($this->required('attachment_s3_endpoint'), '/');
        $bucket = trim($this->required('attachment_s3_bucket'), '/');
        $uri = '/' . rawurlencode($bucket) . ($path !== '' ? '/' . $this->encodePath(ltrim($path, '/')) : '');
        $url = $endpoint . $uri;
        $queryString = $this->canonicalQuery($query);
        if ($queryString !== '') {
            $url .= '?' . $queryString;
        }

        $payloadHash = hash('sha256', $body ?? '');
        $dateTime = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $host = (string)parse_url($endpoint, PHP_URL_HOST);
        $region = $this->region();

        $headers = [
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $dateTime,
        ];
        foreach ($extraHeaders as $line) {
            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
            $headers[strtolower(trim($name))] = trim($value);
        }
        ksort($headers);

        $canonicalHeaders = '';
        foreach ($headers as $name => $value) {
            $canonicalHeaders .= $name . ':' . preg_replace('/\s+/', ' ', $value) . "\n";
        }
        $signedHeaders = implode(';', array_keys($headers));
        $canonicalRequest = strtoupper($method) . "\n"
            . $uri . "\n"
            . $queryString . "\n"
            . $canonicalHeaders . "\n"
            . $signedHeaders . "\n"
            . $payloadHash;
        $scope = $date . '/' . $region . '/s3/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n" . $dateTime . "\n" . $scope . "\n" . hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $stringToSign, $this->signingKey($date, $region));

        $requestHeaders = [];
        foreach ($headers as $name => $value) {
            $requestHeaders[] = $name . ': ' . $value;
        }
        $requestHeaders[] = 'Authorization: AWS4-HMAC-SHA256 Credential='
            . $this->required('attachment_s3_access_key') . '/' . $scope
            . ', SignedHeaders=' . $signedHeaders
            . ', Signature=' . $signature;

        return Http::request($method, $url, [
            'headers' => $requestHeaders,
            'body' => $body,
            'timeout' => 20,
            'default_headers' => false,
        ]);
    }

    private function signingKey(string $date, string $region): string
    {
        $secret = $this->required('attachment_s3_secret_key');
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    private function canonicalQuery(array $query): string
    {
        ksort($query);
        $pairs = [];
        foreach ($query as $key => $value) {
            $pairs[] = rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
        }
        return implode('&', $pairs);
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    private function parseObjectKeys(string $xml): array
    {
        preg_match_all('~<Key>(.*?)</Key>~s', $xml, $matches);
        return array_map(static fn(string $key): string => html_entity_decode($key, ENT_QUOTES | ENT_XML1, 'UTF-8'), $matches[1] ?? []);
    }

    private function parseNextContinuationToken(string $xml): string
    {
        if (!preg_match('~<NextContinuationToken>(.*?)</NextContinuationToken>~s', $xml, $match)) {
            return '';
        }
        return html_entity_decode($match[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function errorMessage(array $response, string $fallback): string
    {
        $body = trim(strip_tags((string)($response['body'] ?? '')));
        $detail = $body !== '' ? mb_substr(preg_replace('/\s+/', ' ', $body), 0, 180) : (string)($response['error'] ?? '');
        return $fallback . '，HTTP ' . (int)($response['status'] ?? 0) . ($detail !== '' ? '：' . $detail : '');
    }

    private function prefix(): string
    {
        return trim((string)($this->config['attachment_s3_prefix'] ?? ''), "/ \t\n\r\0\x0B");
    }

    private function region(): string
    {
        $region = trim((string)($this->config['attachment_s3_region'] ?? 'auto'));
        return $region !== '' ? $region : 'auto';
    }

    private function required(string $key): string
    {
        $value = trim((string)($this->config[$key] ?? ''));
        if ($value === '') {
            $labels = [
                'attachment_s3_endpoint' => 'Endpoint',
                'attachment_s3_bucket' => 'Bucket',
                'attachment_s3_access_key' => 'Access Key',
                'attachment_s3_secret_key' => 'Secret Key',
            ];
            throw new \InvalidArgumentException('请先填写 ' . ($labels[$key] ?? '必要配置'));
        }
        return $value;
    }
}
