<?php
/**
 * ============================================================================
 * 修改密码 API 接口（change_password.php）
 * ============================================================================
 * 
 * @package     PanoramaBarrage\API
 * @author      Racpast
 * @version     1.0.0
 * @license     MIT
 * 
 * @description
 * 该文件仅支持 POST 请求，用于已登录用户修改自己的密码。
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
 * 处理修改密码的 POST 请求。
 *
 * @param PDO $db 数据库连接实例。
 *
 * @return void
 */
function handleChangePasswordRequest(PDO $db): void
{
    // 1. 权限检查：确保用户已登录。
    if (!isset($_SESSION['user_id'])) {
        send_json_response(['success' => false, 'message' => '请先登录！'], 401); // 401 Unauthorized
    }

    // 2. 输入验证：检查密码字段是否为空。
    $userId = $_SESSION['user_id'];
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    if (empty($old_password) || empty($new_password)) {
        send_json_response(['success' => false, 'message' => '旧密码和新密码都不能为空！'], 400); // 400 Bad Request
    }

    // 3. 验证旧密码是否正确。
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($old_password, $user['password_hash'])) {
        send_json_response(['success' => false, 'message' => '旧密码不正确！'], 400); // 也可以用 403 Forbidden
    }

    // 4. 旧密码验证成功，哈希并更新新密码。
    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $update_stmt = $db->prepare("UPDATE users SET password_hash = :new_hash WHERE id = :id");
    $isSuccess = $update_stmt->execute([':new_hash' => $new_password_hash, ':id' => $userId]);

    if ($isSuccess) {
        // 密码修改成功后，为了安全，应销毁当前 session，强制用户重新登录。
        session_destroy();
        send_json_response(['success' => true, 'message' => '密码修改成功！请重新登录。']);
    } else {
        send_json_response(['success' => false, 'message' => '哎呀，密码更新失败了...'], 500); // 500 Internal Server Error
    }
}


// =======================
// 请求路由处理
// =======================

try {
    // 该接口只接受 POST 请求。
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $db = get_db_connection();
        handleChangePasswordRequest($db);
    } else {
        // 对于 GET 或其他方法，返回 405 Method Not Allowed。
        send_json_response(['success' => false, 'message' => '不支持的请求方式！'], 405);
    }
} catch (Exception $e) {
    // 捕获任何意外的异常，防止输出敏感信息。
    // error_log("Change Password API Error: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => '服务器发生未知错误。'], 500);
}