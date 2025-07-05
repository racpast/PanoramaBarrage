<?php
/**
 * ============================================================================
 * 举报弹幕 API 接口（report_barrage.php）
 * ============================================================================
 * 
 * @package     PanoramaBarrage\API
 * @author      Racpast
 * @version     1.0.0
 * @license     MIT
 * 
 * @description
 * 该文件仅支持 POST 请求。用于登录用户举报不良弹幕。当一条弹幕的
 * 举报次数达到阈值时，会自动更新其状态并邮件通知管理员。
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
 * 处理举报弹幕的 POST 请求。
 *
 * @param PDO $db 数据库连接实例。
 *
 * @return void
 */
function handleReportRequest(PDO $db): void
{
    // 1. 权限与输入验证。
    if (!isset($_SESSION['user_id'])) {
        send_json_response(['success' => false, 'message' => '请先登录才能举报哦！'], 401);
    }
    $reporter_user_id = $_SESSION['user_id'];
    $barrage_id = (int) ($_POST['barrage_id'] ?? 0);
    if (empty($barrage_id)) {
        send_json_response(['success' => false, 'message' => '无效的弹幕ID。'], 400);
    }

    // 2. 检查被举报的弹幕是否存在。
    $stmt_check_b = $db->prepare("SELECT id FROM barrages WHERE id = :id");
    $stmt_check_b->execute([':id' => $barrage_id]);
    if (!$stmt_check_b->fetch()) {
        send_json_response(['success' => false, 'message' => '您举报的弹幕不存在哦。'], 404); // 404 Not Found
    }

    // 3. 检查用户是否已经举报过此弹幕。
    $stmt_check_r = $db->prepare("SELECT id FROM barrage_reports WHERE barrage_id = :barrage_id AND reporter_user_id = :reporter_user_id");
    $stmt_check_r->execute([':barrage_id' => $barrage_id, ':reporter_user_id' => $reporter_user_id]);
    if ($stmt_check_r->fetch()) {
        // 重复举报不是一个错误，直接返回成功信息。
        send_json_response(['success' => true, 'message' => '您已经举报过这条弹幕啦~']);
    }

    // 4. 使用数据库事务来保证操作的原子性。
    $db->beginTransaction();
    try {
        // 4.1. 插入新的举报记录。
        $stmt_insert = $db->prepare("INSERT INTO barrage_reports (barrage_id, reporter_user_id) VALUES (:barrage_id, :reporter_user_id)");
        $stmt_insert->execute([':barrage_id' => $barrage_id, ':reporter_user_id' => $reporter_user_id]);

        // 4.2. 统计该弹幕当前的总举报数。
        $stmt_count = $db->prepare("SELECT COUNT(*) FROM barrage_reports WHERE barrage_id = :barrage_id");
        $stmt_count->execute([':barrage_id' => $barrage_id]);
        $report_count = $stmt_count->fetchColumn();

        // 4.3. 判断是否达到阈值。
        if ($report_count >= REPORT_THRESHOLD) {
            // 更新弹幕状态为“待审核”。
            $stmt_update = $db->prepare("UPDATE barrages SET status = 'under_review' WHERE id = :barrage_id");
            $stmt_update->execute([':barrage_id' => $barrage_id]);

            // 获取弹幕详情用于发送邮件。
            $stmt_details = $db->prepare("SELECT b.content, u.username as author FROM barrages b JOIN users u ON b.user_id = u.id WHERE b.id = :barrage_id");
            $stmt_details->execute([':barrage_id' => $barrage_id]);
            $barrage_details = $stmt_details->fetch();

            if ($barrage_details) {
                // 准备并发送邮件给管理员。
                $to = ADMIN_EMAIL;
                $subject = "【留言墙】弹幕被多次举报，请尽快审核";
                $body = "<html>
                            <head>
                            <meta charset='UTF-8'>
                            <style>
                                body { font-family:'Segoe UI','Helvetica Neue',sans-serif;line-height:1.6;color:#333;}
                                .container{max-width:600px;margin:0 auto;padding:20px;border:1px solid #eee;border-radius:8px;}
                                .button{display:inline-block;padding:10px 20px;background-color:#ff5722;color:#fff;text-decoration:none;border-radius:5px;}
                                table{width:100%;border-collapse:collapse;margin-top:10px;}
                                th,td{padding:8px;border:1px solid #ddd;text-align:left;}
                                th{background:#f5f5f5;}
                                .footer{font-size:12px;color:#999;margin-top:20px;}
                            </style>
                            </head>
                            <body>
                                <div class='container'>
                                    <h2>弹幕举报提醒</h2>
                                    <p>您好，管理员：</p>
                                    <p>以下弹幕已被用户举报 <strong>{$report_count} 次</strong>，请及时审核处理：</p>
                                    <table>
                                    <tr>
                                        <th>弹幕 ID</th>
                                        <td>{$barrage_id}</td>
                                    </tr>
                                    <tr>
                                        <th>作者</th>
                                        <td>" . htmlspecialchars($barrage_details['author']) . "</td>
                                    </tr>
                                    <tr>
                                        <th>内容</th>
                                        <td>" . nl2br(htmlspecialchars($barrage_details['content'])) . "</td>
                                    </tr>
                                    <tr>
                                        <th>时间</th>
                                        <td>" . date('Y-m-d H:i:s') . "</td>
                                    </tr>
                                    </table>";

                // 暂未实现审核
                /*
                    $body .= "
                    <p style='text-align:center;margin:20px 0;'>
                        <a href='{$reviewLink}' class='button'>立即前往审核</a>
                    </p>";
                */

                $body .= "
                                    <div class='footer'>
                                        <p>本邮件由系统自动发送，请勿直接回复。</p>
                                    </div>
                                </div>
                            </body>
                        </html>";
                send_email($to, $subject, $body);
            }
        }

        // 4.4. 所有操作成功，提交事务。
        $db->commit();

    } catch (Exception $e) {
        // 4.5. 如果任何一步发生异常，回滚所有数据库操作。
        $db->rollBack();
        // 记录错误日志并向用户显示通用错误信息。
        // error_log("Report Barrage Transaction Failed: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '哎呀，举报时发生错误了...'], 500);
    }

    // 5. 成功完成举报。
    send_json_response(['success' => true, 'message' => '举报成功！感谢您的反馈。']);
}


// =======================
// 请求路由处理
// =======================

try {
    // 该接口只接受 POST 请求。
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $db = get_db_connection();
        handleReportRequest($db);
    } else {
        // 对于 GET 或其他方法，返回 405 Method Not Allowed。
        send_json_response(['success' => false, 'message' => '不支持的请求方式！'], 405);
    }
} catch (Exception $e) {
    // 捕获任何意外的异常，防止输出敏感信息。
    // error_log("Report Barrage API Error: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => '服务器发生未知错误。'], 500);
}