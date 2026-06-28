<?php

declare(strict_types=1);

namespace CurlClient;

use CURLFile;
use InvalidArgumentException;

/**
 * CURL 客户端。
 */
class CurlClient
{
    /**
     * 默认配置。
     *
     * @var array<string,mixed>
     */
    private array $defaults = [
        'timeout' => 30,
        'connect_timeout' => 10,
        'verify_ssl' => false,
        'follow_location' => true,
        'max_redirects' => 5,
        'user_agent' => '',
        'headers' => [],
        'curl' => [],
    ];

    /**
     * 构造 CURL 客户端。
     *
     * @param array<string,mixed> $defaults 默认配置，会覆盖内置配置。
     */
    public function __construct(array $defaults = [])
    {
        $this->defaults = array_replace($this->defaults, $defaults);
    }

    /**
     * 将 HTTP 头转换为 CURL 格式。
     *
     * @param array<string,mixed> $headers HTTP 头。
     * @return array<string>
     */
    private function headerToLines(array $headers): array
    {
        $lines = [];
        foreach ($headers as $k => $v) {
            if (is_int($k)) {
                $lines[] = (string)$v;
                continue;
            }
            if (is_array($v)) {
                foreach ($v as $item) {
                    $lines[] = "{$k}: {$item}";
                }
                continue;
            }
            $lines[] = "{$k}: {$v}";
        }
        return $lines;
    }

    /**
     * 构建 CURL 选项。
     *
     * @param string $url 请求 URL。
     * @param array<string,mixed> $headers HTTP 头。
     * @param array<string,mixed> $options 请求选项。
     * @param array<string,mixed> $responseHeaders 响应头。
     * @return array<string,mixed>
     */
    private function buildBaseOptions(string $url, array $headers, array $options, array &$responseHeaders): array
    {
        $cfg = array_replace($this->defaults, $options);
        $headers = array_replace($this->defaults['headers'] ?? [], $headers);

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_CONNECTTIMEOUT => (int)$cfg['connect_timeout'],
            CURLOPT_TIMEOUT => (int)$cfg['timeout'],
            CURLOPT_FOLLOWLOCATION => (bool)$cfg['follow_location'],
            CURLOPT_MAXREDIRS => (int)$cfg['max_redirects'],
            CURLOPT_USERAGENT => (string)$cfg['user_agent'],
            CURLOPT_SSL_VERIFYPEER => (bool)$cfg['verify_ssl'],
            CURLOPT_SSL_VERIFYHOST => (bool)$cfg['verify_ssl'] ? 2 : 0,
            CURLOPT_HTTPHEADER => $this->headerToLines($headers),
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$responseHeaders): int {
                $len = strlen($line);
                $line = trim($line);
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[trim($name)][] = trim($value);
                }
                return $len;
            },
            CURLINFO_HEADER_OUT => true, // curl_getinfo带请求头部
            CURLOPT_RETURNTRANSFER => true, // 返回响应体
        ];

        // 下载文件
        if (isset($cfg['sink']) && is_resource($cfg['sink'])) {
            $curlOptions[CURLOPT_RETURNTRANSFER] = false; // 不返回响应体
            if (isset($cfg['stream']) && $cfg['stream'] === true) {
                $curlOptions[CURLOPT_WRITEFUNCTION] = function ($ch, $chunk) use ($cfg) {
                    fwrite($cfg['sink'], $chunk);
                    return strlen($chunk);
                };
                $curlOptions[CURLOPT_BUFFERSIZE] = 65536;
            } else {
                $curlOptions[CURLOPT_FILE] = $cfg['sink'];
            }
        }

        if (isset($cfg['proxy'])) {
            $curlOptions[CURLOPT_PROXY] = (string)$cfg['proxy'];
        }

        if (isset($cfg['basic_auth']) && is_array($cfg['basic_auth'])) {
            $curlOptions[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $curlOptions[CURLOPT_USERPWD] = ($cfg['basic_auth']['user'] ?? '') . ':' . ($cfg['basic_auth']['pass'] ?? '');
        }

        if (!empty($cfg['curl']) && is_array($cfg['curl'])) {
            $curlOptions = array_replace($curlOptions, $cfg['curl']);
        }

        return $curlOptions;
    }

    /**
     * 通用 HTTP 请求方法（支持 GET/POST/PUT/DELETE/PATCH...）。
     *
     * @param string $method HTTP 方法
     * @param string $url 请求地址
     * @param string|array<string,mixed>|null $body 请求体
     * @param array<string,string|array<int,string>> $headers 请求头
     * @param array<string,mixed> $options 本次请求覆盖配置
     * @return array
     * @throws ClientException
     */
    public function sendRequest(string $method, string $url, string|array|null $body = null, array $headers = [], array $options = []): array
    {
        $ch = curl_init();

        $responseHeaders = [];
        $opts = $this->buildBaseOptions($url, $headers, $options, $responseHeaders);
        $opts = array_replace($opts, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ]);
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $opts);

        $responseBody = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $info = curl_getinfo($ch);

        if ($responseBody === false) {
            throw new ClientException(
                'CURL error: ' . curl_error($ch) . ' (URL: ' . $url . ').',
                curl_errno($ch),
            );
        }

        /**
         * PHP8.5.0起弃用
         * PHP8.0.0是NOP（空操作）
         */
        // curl_close($ch);

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => $responseBody,
            'info' => $info,
        ];
    }

    /**
     * 发送 GET 请求。
     *
     * @param string $url 请求地址
     * @param array<string,scalar|null> $query Query 参数
     * @param array<string,string|array<int,string>> $headers 请求头
     * @param array<string,mixed> $options 本次请求覆盖配置
     * @return array
     * @throws ClientException
     */
    public function get(string $url, array $query = [], array $headers = [], array $options = []): array
    {
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }
        return $this->sendRequest('GET', $url, null, $headers, $options);
    }

    /**
     * 发送 POST 请求（原始 body）。
     *
     * @param string $url 请求地址
     * @param string|array<string,mixed>|null $body 请求体
     * @param array<string,string|array<int,string>> $headers 请求头
     * @param array<string,mixed> $options 本次请求覆盖配置
     * @return array
     * @throws ClientException
     */
    public function post(string $url, string|array|null $body = null, array $headers = [], array $options = []): array
    {
        return $this->sendRequest('POST', $url, $body, $headers, $options);
    }

    /**
     * 发送 application/json POST 请求。
     *
     * @param string $url 请求地址
     * @param array<string,mixed>|object $data JSON 数据
     * @param array<string,string|array<int,string>> $headers 请求头
     * @param array<string,mixed> $options 本次请求覆盖配置
     * @param int $jsonEncodeOptions
     * @return array
     * @throws ClientException
     */
    public function postJson(string $url, array|object $data, array $headers = [], array $options = [], int $jsonEncodeOptions = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE): array
    {
        $headers['Content-Type'] = 'application/json;charset=UTF-8';
        $json = json_encode($data, $jsonEncodeOptions);
        return $this->post($url, $json, $headers, $options);
    }

    /**
     * 发送 application/x-www-form-urlencoded POST 请求。
     *
     * @param string $url 请求地址
     * @param array<string,scalar|null> $form 表单字段
     * @param array<string,string|array<int,string>> $headers 请求头
     * @param array<string,mixed> $options 本次请求覆盖配置
     * @return array
     * @throws ClientException
     */
    public function postForm(string $url, array $form = [], array $headers = [], array $options = []): array
    {
        $body = http_build_query($form);
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        return $this->post($url, $body, $headers, $options);
    }

    /**
     * 发送 POST 请求（Multipart 表单，用于文件上传）
     *
     * @param string $url 请求 URL
     * @param array $fields 普通表单字段 [name => value]
     * @param array $files 文件字段 [name => ['path1', 'path2', 'path3']
     * @param array $headers 自定义请求头
     * @param array $options 本次请求覆盖配置
     * @return array
     * @throws InvalidArgumentException
     * @throws ClientException
     */
    public function postMultipart(string $url, array $files = [], array $fields = [], array $headers = [], array $options = []): array
    {
        $body = $fields;
        if (!empty($files)) {
            foreach ($files as $name => $paths) {
                if (!is_string($name) || $name === '') {
                    throw new InvalidArgumentException('Invalid file field name');
                }

                $list = is_array($paths) ? array_values($paths) : [$paths];
                foreach ($list as $index => $path) {
                    if (!is_string($path) || $path === '' || !is_file($path)) {
                        throw new InvalidArgumentException('File not found: ' . (string)$path);
                    }

                    $key = count($list) > 1 ? $name . '[' . $index . ']' : $name;
                    $body[$key] = new CURLFile($path, null, basename($path));
                }
            }
        }
        return $this->post($url, $body, $headers, $options);
    }
}
