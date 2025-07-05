<?php
/**
 * ============================================================================
 * 用户注册 API 接口（register.php）
 * ============================================================================
 * 
 * @package     PanoramaBarrage\API
 * @author      Racpast
 * @version     1.0.0
 * @license     MIT
 * 
 * @description
 * 该文件仅支持 POST 请求。用于处理新用户注册，包括数据验证、
 * 创建用户记录，并发送一封邮箱验证邮件。
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
 * 处理新用户注册的 POST 请求。
 *
 * @param PDO $db 数据库连接实例。
 *
 * @return void
 */
function handleRegisterRequest(PDO $db): void
{
    // 1. 输入验证。
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        send_json_response(['success' => false, 'message' => '用户名、邮箱和密码都不能为空！'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_json_response(['success' => false, 'message' => '邮箱格式不正确！'], 400);
    }

    // 2. 检查用户名或邮箱是否已存在。
    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
    $stmt->execute([':username' => $username, ':email' => $email]);
    if ($stmt->fetch()) {
        send_json_response(['success' => false, 'message' => '用户名或邮箱已经被注册啦！'], 409); // 409 Conflict
    }

    // 3. 准备新用户数据。
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $verification_code = bin2hex(random_bytes(16));

    // 4. 插入新用户数据到数据库。
    $sql = "INSERT INTO users (username, email, password_hash, verification_code) 
            VALUES (:username, :email, :password_hash, :verification_code)";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $password_hash,
        ':verification_code' => $verification_code
    ]);

    // 5. 发送验证邮件。
    $subject = '【留言墙】欢迎注册，请验证您的邮箱';
    $verification_link = rtrim(BASE_URL, '/') . '/api/verify.php?code=' . $verification_code;
    $body = "<html>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body { font-family: 'Segoe UI', 'Helvetica Neue', sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 8px; }
                        .button {
                        display: inline-block;
                        padding: 10px 20px;
                        background-color: #4CAF50;
                        color: white;
                        text-decoration: none;
                        border-radius: 5px;
                        }
                        .footer { font-size: 12px; color: #999; margin-top: 20px; }
                        p { margin: 0 0 1em 0;  }
                    </style>
                </head>
                <body>
                <div class='container'>
                    <h2>欢迎加入留言墙！</h2>
                    <p>亲爱的 <strong>{$username}</strong>：</p>
                    <p>感谢您注册留言墙账号！为了完成注册，请点击下方按钮验证您的邮箱：</p>
                    <p style='text-align: center;'>
                    <a href='{$verification_link}' class='button'>立即验证邮箱</a>
                    </p>
                    <p>或者您也可以复制下面的链接到浏览器中打开：</p>
                    <p><a href='{$verification_link}'>{$verification_link}</a></p>
                    <p>此链接在 24 小时内有效，请尽快完成验证。</p>
                    <p>如果您并未注册留言墙，请忽略此邮件。</p>
                    <div class='footer'>
                        <p>此邮件由系统自动发送，请勿直接回复。</p>
                    </div>
                </div>
                </body>
            </html>";

    if (send_email($to = $email, $subject, $body)) {
        $message = '注册成功！验证邮件已发送到您的邮箱，请注意查收哦~';
        send_json_response(['success' => true, 'message' => $message], 201); // 201 Created
    } else {
        // 如果邮件发送失败，这是一个服务端问题。
        // 在生产环境中，这里应该有更完善的处理，比如将邮件任务加入队列重试。
        $message = '注册成功，但验证邮件发送失败了... 请稍后尝试联系管理员。';
        send_json_response(['success' => false, 'message' => $message], 500);
    }
}


// =======================
// 请求路由处理
// =======================

try {
    // 该接口只接受 POST 请求。
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $db = get_db_connection();
        handleRegisterRequest($db);
    } else {
        // 对于 GET 或其他方法，返回 405 Method Not Allowed。
        send_json_response(['success' => false, 'message' => '不支持的请求方式！'], 405);
    }
} catch (PDOException $e) {
    // 捕获数据库异常，防止泄露敏感信息。
    // error_log("Register API Database Error: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => '服务器开小差了，请稍后再试喵~'], 500);
} catch (Exception $e) {
    // 捕获其他所有意外的异常。
    // error_log("Register API General Error: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => '服务器发生未知错误。'], 500);
}
