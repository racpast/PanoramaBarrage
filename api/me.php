<?php
/**
 * ============================================================================
 * 获取当前用户信息 API 接口（me.php）
 * ============================================================================
 * 
 * @package     PanoramaBarrage\API
 * @author      Racpast
 * @version     1.0.0
 * @license     MIT
 * 
 * @description
 * 该文件仅支持 GET 请求。用于检查当前用户的登录状态，并返回其基本信息
 * 和一些前端需要的公共网站配置。
 */

// 引入配置文件，它会自动开启 session。
require '../config.php';

// 设置响应头为 JSON 类型。
header('Content-Type: application/json; charset=UTF-8');


// =======================
// 核心函数定义
// =======================

/**
 * 发送 JSON 格式的响应并退出脚本。
 *
 * @param array $data       要编码为 JSON 的数据。
 * @param int   $statusCode HTTP 状态码，默认 200。
 *
 * @return void
 */
function send_json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

/**
 * 处理获取当前用户信息的 GET 请求。
 *
 * @return void
 */
function handleMeRequest(): void
{
    // 1. 准备需要发送给前端的公共配置信息。
    $publicConfig = [
        'barrageMaxLength' => BARRAGE_MAX_LENGTH,
    ];

    // 2. 准备基础响应数据结构。
    $responseData = [
        'isLoggedIn' => false,
        'config' => $publicConfig,
    ];

    // 3. 检查 Session，判断用户是否已登录。
    if (isset($_SESSION['user_id'])) {
        // 如果已登录，则更新响应数据。
        $responseData['isLoggedIn'] = true;
        $responseData['user'] = [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'avatar' => $_SESSION['avatar_url'],
        ];
    }

    // 4. 发送最终的响应数据。
    send_json_response($responseData);
}


// =======================
// 请求路由处理
// =======================

try {
    // 该接口只应该通过 GET 方法访问。
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleMeRequest();
    } else {
        // 对于 POST 或其他方法，返回 405 Method Not Allowed。
        send_json_response(['success' => false, 'message' => '不支持的请求方式！'], 405);
    }
} catch (Exception $e) {
    // 捕获任何意外的异常，防止输出敏感信息。
    // error_log("Me API Error: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => '服务器发生未知错误。'], 500);
}