<?php
/**
 * ============================================================================
 * 邮箱验证页面（verify.php）
 * ============================================================================
 * 
 * @package     PanoramaBarrage\API
 * @author      Racpast
 * @version     1.0.0
 * @license     MIT
 * 
 * @description
 * 该文件是一个面向用户的HTML页面。用户通过点击注册邮件中的链接访问此
 * 页面，以验证其邮箱地址的有效性。
 */

// 引入配置文件，它会自动开启 session。
require '../config.php';

// =======================
// 核心函数定义
// =======================

/**
 * 渲染一个 HTML 响应页面。
 *
 * @param string $title         页面标题。
 * @param string $message       要在页面上显示的核心信息。
 * @param bool   $showLoginLink 是否显示返回首页的链接。
 *
 * @return void
 */
function render_verification_page(string $title, string $message, bool $showLoginLink = false): void
{
    // 使用 heredoc 语法来方便地编写 HTML 模板
    echo <<<HTML
            <!DOCTYPE html>
            <html lang="zh-CN">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>{$title} - 苹果酱的留言墙</title>
                <style>
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                        background-color: #f0f2f5;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        height: 100vh;
                        margin: 0;
                        color: #333;
                    }
                    .container {
                        text-align: center;
                        background-color: #ffffff;
                        padding: 40px;
                        border-radius: 12px;
                        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                        max-width: 500px;
                        width: 90%;
                    }
                    h1 {
                        font-size: 1.8em;
                        color: #1c1e21;
                        margin-bottom: 20px;
                    }
                    p {
                        font-size: 1.1em;
                        color: #606770;
                    }
                    a {
                        display: inline-block;
                        margin-top: 25px;
                        padding: 10px 20px;
                        background-color: #007bff;
                        color: #ffffff;
                        text-decoration: none;
                        border-radius: 6px;
                        font-weight: bold;
                        transition: background-color 0.3s;
                    }
                    a:hover {
                        background-color: #0056b3;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <h1>{$message}</h1>
        HTML;

    if ($showLoginLink) {
        // 使用配置中的 BASE_URL 来生成链接
        $homeUrl = rtrim(BASE_URL, '/');
        echo "<a href='{$homeUrl}'>返回首页登录</a>";
    }

    echo <<<HTML
                </div>
            </body>
        </html>
    HTML;
    exit;
}


// =======================
// 主逻辑处理
// =======================

try {
    // 1. 检查 'code' 参数是否存在。
    if (!isset($_GET['code']) || empty($_GET['code'])) {
        render_verification_page('验证失败', '无效的请求，缺少验证码。');
    }

    $code = $_GET['code'];
    $db = get_db_connection();

    // 2. 查找与验证码匹配的用户。
    $stmt = $db->prepare("SELECT id, is_verified FROM users WHERE verification_code = :code");
    $stmt->execute([':code' => $code]);
    $user = $stmt->fetch();

    if (!$user) {
        // 2.1. 如果找不到用户，说明链接无效或已过期。
        render_verification_page('验证失败', '无效的验证链接，或者已经过期了。');
    }

    if ($user['is_verified']) {
        // 2.2. 如果用户已经验证过。
        render_verification_page('操作提醒', '您的邮箱已经验证过啦，无需重复操作！', true);
    }

    // 3. 用户存在且未验证，执行验证操作。
    // 更新用户状态，并清空验证码，防止重复使用。
    $update_stmt = $db->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = :id");
    $update_stmt->execute([':id' => $user['id']]);

    render_verification_page('验证成功', '邮箱验证成功！您现在可以登录啦！', true);

} catch (Exception $e) {
    // 捕获任何意外的异常，向用户显示一个通用的错误页面。
    // error_log("Verification Page Error: " . $e->getMessage());
    render_verification_page('系统错误', '服务器开小差啦，请稍后再试！');
}