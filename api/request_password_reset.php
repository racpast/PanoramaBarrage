<?php
/**
 * ============================================================================
 * 请求密码重置 API 接口（request_password_reset.php）
 * ============================================================================
 * 
 * @package     PanoramaBarrage\API
 * @author      Racpast
 * @version     1.0.0
 * @license     MIT
 * 
 * @description
 * 该文件仅支持 POST 请求。用于用户请求重置密码，系统会生成一个有时效性
 * 的token，并发送一封包含重置链接的邮件到用户邮箱。
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
 * 处理请求密码重置的 POST 请求。
 *
 * @param PDO $db 数据库连接实例。
 *
 * @return void
 */
function handleRequestPasswordReset(PDO $db): void
{
    // 1. 输入验证。
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_json_response(['success' => false, 'message' => '请输入一个有效的邮箱地址！'], 400);
    }
    
    // 2. 查找用户是否存在。
    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // 3. 无论用户是否存在，后续操作都只在“存在”的条件下执行，
    //    但最终返回给前端的成功信息保持一致，以防止邮箱枚举攻击。
    if ($user) {
        // 3.1. 生成安全 token 和过期时间。
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + PASSWORD_RESET_EXPIRY);

        // 3.2. 将 token 更新到数据库。
        $update_stmt = $db->prepare(
            "UPDATE users SET password_reset_token = :token, password_reset_expires = :expires WHERE id = :id"
        );
        $update_stmt->execute([':token' => $token, ':expires' => $expires, ':id' => $user['id']]);

        // 3.3. 准备并发送邮件。
        $reset_link = rtrim(BASE_URL, '/') . '/index.html?token=' . $token;
        $subject = '【留言墙】密码重置请求，请及时处理';
        $body = "<html>
                  <head>
                    <meta charset='UTF-8'>
                    <style>
                    body { font-family: 'Segoe UI', 'Helvetica Neue', sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 8px; }
                    .button {
                      display: inline-block;
                      padding: 10px 20px;
                      background-color: #f44336;
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
                    <h2>密码重置请求</h2>
                    <p>您好，</p>
                    <p>我们收到了您账户的密码重置请求。请在 <strong>1 小时</strong> 内点击下方按钮设置新的密码：</p>
                    <p style='text-align: center;'>
                      <a href='{$reset_link}' class='button'>重置密码</a>
                    </p>
                    <p>若按钮无法点击，您也可以复制以下链接到浏览器中打开：</p>
                    <p><a href='{$reset_link}'>{$reset_link}</a></p>
                    <p>如果您没有请求重置密码，请忽略此邮件。</p>
                    <div class='footer'>
                      <p>本邮件由系统自动发送，请勿直接回复。</p>
                    </div>
                    </div>
                  </body>
                </html>";
        
        // 尝试发送邮件，但不把发送失败的信息直接暴露给用户。
        // 在生产环境中，邮件发送失败应该被记录到日志中。
        if (!send_email($email, $subject, $body)) {
            // error_log("Password reset email failed to send to: {$email}");
        }
    }
    
    // 4. 统一发送成功响应。
    $message = '如果该邮箱已注册，我们已向您发送密码重置邮件，请注意查收。';
    send_json_response(['success' => true, 'message' => $message]);
}


// =======================
// 请求路由处理
// =======================

try {
    // 该接口只接受 POST 请求。
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $db = get_db_connection();
        handleRequestPasswordReset($db);
    } else {
        // 对于 GET 或其他方法，返回 405 Method Not Allowed。
        send_json_response(['success' => false, 'message' => '不支持的请求方式！'], 405);
    }
} catch (Exception $e) {
    // 捕获任何意外的异常，防止输出敏感信息。
    // error_log("Request Password Reset API Error: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => '服务器发生未知错误。'], 500);
}
