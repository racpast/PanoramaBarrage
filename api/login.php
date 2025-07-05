<?php
/**
 * ============================================================================
 * 用户登录 API 接口（login.php）
 * ============================================================================
 * 
 * @package     PanoramaBarrage\API
 * @author      Racpast
 * @version     1.0.0
 * @license     MIT
 * 
 * @description
 * 该文件仅支持 POST 请求，用于验证用户凭据并建立会话（Session）。
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
 * 处理用户登录的 POST 请求。
 *
 * @param PDO $db 数据库连接实例。
 *
 * @return void
 */
function handleLoginRequest(PDO $db): void
{
    // 1. 输入验证：检查邮箱和密码是否为空。
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        send_json_response(['success' => false, 'message' => '邮箱和密码不能为空！'], 400); // 400 Bad Request
    }

    // 2. 从数据库查询用户信息。
    $sql = "SELECT id, username, password_hash, is_verified, avatar_url FROM users WHERE email = :email";
    $stmt = $db->prepare($sql);
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // 3. 验证用户是否存在以及密码是否正确。
    if (!$user || !password_verify($password, $user['password_hash'])) {
        send_json_response(['success' => false, 'message' => '邮箱或密码错误！'], 401); // 401 Unauthorized
    }

    // 4. 检查用户邮箱是否已验证。
    if (!$user['is_verified']) {
        $message = '您的邮箱还未验证，请先检查邮箱完成验证！';
        send_json_response(['success' => false, 'message' => $message], 403); // 403 Forbidden
    }

    // 5. 验证全部通过，登录成功！建立 Session。
    // 在写入 Session 之前，可以调用 session_regenerate_id(true) 来防止会话固定攻击。
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['avatar_url'] = $user['avatar_url'];

    // 准备返回给前端的数据。
    $responseData = [
        'success' => true,
        'message' => '登录成功！欢迎回来，' . htmlspecialchars($user['username']) . '！',
        'user' => [
            'username' => $user['username'],
            'avatar' => $user['avatar_url']
        ]
    ];

    send_json_response($responseData);
}


// =======================
// 请求路由处理
// =======================

try {
    // 该接口只接受 POST 请求。
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $db = get_db_connection();
        handleLoginRequest($db);
    } else {
        // 对于 GET 或其他方法，返回 405 Method Not Allowed。
        send_json_response(['success' => false, 'message' => '不支持的请求方式！'], 405);
    }
} catch (Exception $e) {
    // 捕获任何意外的异常，防止输出敏感信息。
    // error_log("Login API Error: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => '服务器发生未知错误。'], 500);
}