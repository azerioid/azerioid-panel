<?php
declare(strict_types=1);

namespace LacmpPanel\Broker;

/**
 * Minimal S3/Spaces client (Sig V4). Secrets stay in this process; never logged.
 */
final class SpacesClient
{
    /**
     * Test hook. Receives method, URL, headers, body; returns status/body/headers.
     *
     * @var (callable(string, string, array<int,string>, string): array{status:int, body:string, headers:array<string,string>})|null
     */
    public static $http = null;

    public function __construct(
        private readonly string $endpoint,
        private readonly string $region,
        private readonly string $bucket,
        private readonly string $accessKey,
        private readonly string $secretKey,
    ) {
    }

    public static function fromInput(array $input): self
    {
        $endpoint = rtrim((string) ($input['endpoint'] ?? ''), '/');
        $region = (string) ($input['region'] ?? '');
        $bucket = (string) ($input['bucket'] ?? '');
        $key = (string) ($input['access_key'] ?? $input['spaces_key'] ?? '');
        $secret = (string) ($input['secret'] ?? $input['spaces_secret'] ?? $input['secret_access_key'] ?? '');
        if ($endpoint === '' || $region === '' || $bucket === '' || $key === '' || $secret === '') {
            throw new BrokerException('Spaces credentials are incomplete.', 2);
        }
        if (!preg_match('#^https://[A-Za-z0-9.-]+$#', $endpoint)) {
            throw new BrokerException('Spaces endpoint must be https://host.', 2);
        }
        if (!preg_match('/^[a-z0-9-]{2,32}$/', $region) || !preg_match('/^[A-Za-z0-9.-]{3,63}$/', $bucket)) {
            throw new BrokerException('Invalid Spaces region or bucket.', 2);
        }
        return new self($endpoint, $region, $bucket, $key, $secret);
    }

    public function test(): array
    {
        $res = $this->request('GET', '/' . $this->bucket, ['list-type' => '2', 'max-keys' => '1']);
        if ($res['status'] >= 400) {
            throw new BrokerException('Spaces test failed (HTTP ' . $res['status'] . ').', 1);
        }
        return ['ok' => true, 'bucket' => $this->bucket, 'region' => $this->region];
    }

    public function put(string $key, string $body, string $contentType = 'application/octet-stream'): array
    {
        $key = Validator::objectKey($key);
        $res = $this->request('PUT', '/' . $this->bucket . '/' . $key, [], $body, $contentType);
        if ($res['status'] >= 400) {
            throw new BrokerException('Spaces upload failed (HTTP ' . $res['status'] . ').', 1);
        }
        return ['key' => $key, 'size' => strlen($body), 'etag' => $res['headers']['etag'] ?? null];
    }

    public function get(string $key): string
    {
        $key = Validator::objectKey($key);
        $res = $this->request('GET', '/' . $this->bucket . '/' . $key);
        if ($res['status'] >= 400) {
            throw new BrokerException('Spaces download failed (HTTP ' . $res['status'] . ').', 1);
        }
        return $res['body'];
    }

    public function delete(string $key): void
    {
        $key = Validator::objectKey($key);
        $this->request('DELETE', '/' . $this->bucket . '/' . $key);
    }

    public function list(string $prefix = ''): array
    {
        $query = ['list-type' => '2', 'max-keys' => '200'];
        if ($prefix !== '') {
            $query['prefix'] = $prefix;
        }
        $res = $this->request('GET', '/' . $this->bucket, $query);
        if ($res['status'] >= 400) {
            throw new BrokerException('Spaces list failed (HTTP ' . $res['status'] . ').', 1);
        }
        $objects = [];
        if (preg_match_all('#<Contents>(.*?)</Contents>#s', $res['body'], $blocks)) {
            foreach ($blocks[1] as $block) {
                $objects[] = [
                    'key' => $this->xml($block, 'Key'),
                    'size' => (int) $this->xml($block, 'Size'),
                    'last_modified' => $this->xml($block, 'LastModified'),
                ];
            }
        }
        return ['objects' => $objects];
    }

    private function xml(string $block, string $tag): string
    {
        return preg_match('#<' . $tag . '>([^<]*)</' . $tag . '>#', $block, $m) ? html_entity_decode($m[1], ENT_XML1) : '';
    }

    /**
     * @param  array<string,string>  $query
     * @return array{status:int, body:string, headers:array<string,string>}
     */
    private function request(string $method, string $path, array $query = [], string $body = '', string $contentType = 'application/octet-stream'): array
    {
        $host = parse_url($this->endpoint, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            throw new BrokerException('Invalid Spaces endpoint host.', 2);
        }
        $now = gmdate('Ymd\THis\Z');
        $date = substr($now, 0, 8);
        $payloadHash = hash('sha256', $body);
        $canonicalQuery = '';
        if ($query !== []) {
            ksort($query);
            $parts = [];
            foreach ($query as $k => $v) {
                $parts[] = rawurlencode($k) . '=' . rawurlencode($v);
            }
            $canonicalQuery = implode('&', $parts);
        }
        $canonicalHeaders = "host:{$host}\nx-amz-content-sha256:{$payloadHash}\nx-amz-date:{$now}\n";
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
        $canonical = "{$method}\n{$path}\n{$canonicalQuery}\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        $scope = "{$date}/{$this->region}/s3/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$now}\n{$scope}\n" . hash('sha256', $canonical);
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);
        $auth = "AWS4-HMAC-SHA256 Credential={$this->accessKey}/{$scope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $url = $this->endpoint . $path;
        if ($canonicalQuery !== '') {
            $url .= '?' . $canonicalQuery;
        }
        $headers = [
            'Authorization: ' . $auth,
            'Host: ' . $host,
            'x-amz-content-sha256: ' . $payloadHash,
            'x-amz-date: ' . $now,
            'Content-Type: ' . $contentType,
        ];
        if (self::$http !== null) {
            return (self::$http)($method, $url, $headers, $body);
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 60,
            ],
        ]);
        $result = @file_get_contents($url, false, $ctx);
        $status = 0;
        $respHeaders = [];
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
                $status = (int) $m[1];
            } elseif (str_contains($h, ':')) {
                [$k, $v] = explode(':', $h, 2);
                $respHeaders[strtolower(trim($k))] = trim($v);
            }
        }
        return ['status' => $status, 'body' => is_string($result) ? $result : '', 'headers' => $respHeaders];
    }
}
