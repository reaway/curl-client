# CurlClient

一个简单易用的 PHP HTTP Curl 客户端，支持 GET/POST/PUT/DELETE/PATCH 等请求方法，支持 JSON、表单、文件上传等多种数据格式。

## 特性

- 支持多种 HTTP 请求方法（GET、POST、PUT、DELETE、PATCH 等）
- 支持多种数据格式（JSON、表单、原始 body、文件上传）
- 支持自定义请求头和请求配置
- 支持代理设置
- 支持 HTTP Basic 认证
- 支持 SSL 验证配置
- 自动处理重定向
- 返回详细的响应信息（状态码、响应头、响应体、错误信息等）

## 安装

```bash
composer require reaway/curl-client
```

## 环境要求

- PHP >= 8.1
- ext-curl
- ext-json

## 用法

### 基础用法

```php
<?php

use CurlClient\CurlClient;

require_once 'vendor/autoload.php';

$client = new CurlClient();
```

### GET 请求

```php
// 简单 GET 请求
$response = $client->get('https://api.example.com/users');

// 带 Query 参数的 GET 请求
$response = $client->get('https://api.example.com/users', [
    'page' => 1,
    'limit' => 10
]);

// 带自定义请求头的 GET 请求
$response = $client->get('https://api.example.com/users', [], [
    'Authorization' => 'Bearer token123',
    'Accept' => 'application/json'
]);
```

### POST 请求

```php
// 发送原始 body
$response = $client->post('https://api.example.com/users', 'raw body data');

// 发送表单数据（application/x-www-form-urlencoded）
$response = $client->postForm('https://api.example.com/login', [
    'username' => 'admin',
    'password' => '123456'
]);

// 发送 JSON 数据
$response = $client->postJson('https://api.example.com/users', [
    'name' => '张三',
    'email' => 'zhangsan@example.com'
]);
```

### 文件上传

```php
// 单文件上传
$response = $client->postMultipart(
    'https://api.example.com/upload',
    ['avatar' => '/path/to/image.jpg'],  // 文件字段
    ['description' => '用户头像']          // 普通表单字段
);

// 多文件上传
$response = $client->postMultipart(
    'https://api.example.com/upload',
    [
        'images' => [
            '/path/to/image1.jpg',
            '/path/to/image2.jpg'
        ]
    ],
    ['category' => 'gallery']
);
```

### 文件下载

```php
$url = 'https://api.example.com/file.zip'

// 直接输出到浏览器（边下载边输出，不在内存堆积）
$options = [
    'sink' => fopen('php://output', 'wb'),
    'stream' => true
];
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($url) . '"');
$client->post($url, null, [], $options);

// 保存到本地文件
$options = [
    'sink' => fopen(__DIR__ . DIRECTORY_SEPARATOR . basename($url), 'wb'), // 直接写入文件,不进内存,
];
$client->post($url, null, [], $options);
```

### 通用请求方法

```php
// 发送 PUT 请求
$response = $client->sendRequest('PUT', 'https://api.example.com/users/1', [
    'name' => '李四'
]);

// 发送 DELETE 请求
$response = $client->sendRequest('DELETE', 'https://api.example.com/users/1');

// 发送 PATCH 请求
$response = $client->sendRequest('PATCH', 'https://api.example.com/users/1', [
    'email' => 'lisi@example.com'
]);
```

### 响应数据结构

所有请求方法都返回一个包含以下字段的数组：

```php
[
    'status'   => 200,              // HTTP 状态码
    'headers'  => [...],            // 响应头数组
    'body'     => '...',            // 响应体内容
    'errno'    => 0,                // Curl 错误码
    'error'    => '',               // Curl 错误信息
    'info'     => [...],            // Curl 请求信息
]
```

### 全局配置

```php
// 创建客户端时设置默认配置
$client = new CurlClient([
    'timeout' => 60,              // 请求超时时间（秒）
    'connect_timeout' => 10,      // 连接超时时间（秒）
    'verify_ssl' => true,         // 是否验证 SSL 证书
    'follow_location' => true,    // 是否跟随重定向
    'max_redirects' => 5,         // 最大重定向次数
    'user_agent' => 'MyApp/1.0',  // User-Agent
    'headers' => [                // 默认请求头
        'Accept' => 'application/json'
    ],
]);
```

### 单次请求配置覆盖

```php
// 单次请求覆盖配置
$response = $client->get('https://api.example.com/users', [], [], [
    'timeout' => 120,
    'proxy' => 'http://proxy.example.com:8080',
    'basic_auth' => [
        'user' => 'username',
        'pass' => 'password'
    ]
]);
```

## 配置选项说明

| 选项 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| timeout | int | 30 | 请求超时时间（秒） |
| connect_timeout | int | 10 | 连接超时时间（秒） |
| verify_ssl | bool | false | 是否验证 SSL 证书 |
| follow_location | bool | true | 是否跟随重定向 |
| max_redirects | int | 5 | 最大重定向次数 |
| user_agent | string | '' | User-Agent |
| headers | array | [] | 默认请求头 |
| proxy | string | null | 代理服务器地址 |
| basic_auth | array | null | HTTP Basic 认证信息 |
| curl | array | [] | 额外的 Curl 选项 |

## 许可证

Apache-2.0
