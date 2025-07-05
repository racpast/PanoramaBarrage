<?php
/**
 * ============================================================================
 * 重置密码 API 接口（reset_password.php）
 * ============================================================================
 * 
 * @package     PanoramaBarrage\API
 * @author      Racpast
 * @version     1.0.0
 * @license     MIT
 * 
 * @description
 * 该文件仅支持 POST 请求。用于用户通过邮件中的 token 来设置新的密码。
 * 成功后，该 token 将被立即销毁。
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
 * 处理通过 token 重置密码的 POST 请求。
 *
 * @param PDO $db 数据库连接实例。
 *
 * @return void
 */
function handleResetPasswordRequest(PDO $db): void
{
    // 1. 输入验证。
    $token = $_POST['token'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    if (empty($token) || empty($new_password)) {
        send_json_response(['success' => false, 'message' => '无效的请求，缺少必要参数。'], 400);
    }

    // 2. 查找有效且未过期的 token。
    // NOW() 函数确保只查找 password_reset_expires 在当前时间之后的记录。
    $sql = "SELECT id FROM users WHERE password_reset_token = :token AND password_reset_expires > NOW()";
    $stmt = $db->prepare($sql);
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();

    if (!$user) {
        // 如果找不到对应的用户，说明 token 无效或已过期。
        send_json_response(['success' => false, 'message' => '链接无效或已过期，请重新申请。'], 400);
    }

    // 3. Token 验证成功，更新密码并销毁 token。
    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

    // 将更新密码和销毁 token 放在同一个 SQL 语句中，保证原子性。
    $update_sql = "UPDATE users SET 
                       password_hash = :hash, 
                       password_reset_token = NULL, 
                       password_reset_expires = NULL 
                   WHERE id = :id";
    $update_stmt = $db->prepare($update_sql);
    $isSuccess = $update_stmt->execute([':hash' => $new_password_hash, ':id' => $user['id']]);

    if ($isSuccess) {
        send_json_response(['success' => true, 'message' => '密码重置成功！现在您可以用新密码登录了。']);
    } else {
        send_json_response(['success' => false, 'message' => '哎呀，更新密码时发生错误...'], 500);
    }
}


// =======================
// 请求路由处理
// =======================

try {
    // 该接口只接受 POST 请求。
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $db = get_db_connection();
        handleResetPasswordRequest($db);
    } else {
        // 对于 GET 或其他方法，返回 405 Method Not Allowed。
        send_json_response(['success' => false, 'message' => '不支持的请求方式！'], 405);
    }
} catch (Exception $e) {
    // 捕获任何意外的异常，防止输出敏感信息。
    // error_log("Reset Password API Error: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => '服务器发生未知错误。'], 500);
}