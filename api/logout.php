<?php
/**
 * ============================================================================
 * 用户登出 API 接口（logout.php）
 * ============================================================================
 * 
 * @package     PanoramaBarrage\API
 * @author      Racpast
 * @version     1.0.0
 * @license     MIT
 * 
 * @description
 * 该文件仅支持 POST 请求，用于销毁当前用户的会话（Session），实现安全登出。
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
 * 处理用户登出的 POST 请求。
 *
 * @return void
 */
function handleLogoutRequest(): void
{
    // 1. 清除所有 Session 变量。
    session_unset();

    // 2. 销毁 Session。
    session_destroy();

    // 3. 发送成功响应。
    send_json_response(['success' => true, 'message' => '您已成功登出！']);
}


// =======================
// 请求路由处理
// =======================

try {
    // 为安全起见，推荐让改变状态的操作（如登出）使用 POST 请求。
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handleLogoutRequest();
    } else {
        // 对于 GET 或其他方法，返回 405 Method Not Allowed。
        send_json_response(['success' => false, 'message' => '不支持的请求方式！'], 405);
    }
} catch (Exception $e) {
    // 捕获任何意外的异常。
    // error_log("Logout API Error: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => '服务器发生未知错误。'], 500);
}