<?php
/**
 * ============================================================================
 * 弹幕 API 接口（barrages.php）
 * ============================================================================
 * 
 * @package     PanoramaBarrage\API
 * @author      Racpast
 * @version     1.0.0
 * @license     MIT
 * 
 * @description
 * 该文件负责处理所有与弹幕相关的请求：
 * - GET：用于获取弹幕列表，支持 `since_id` 参数增量获取新弹幕；
 * - POST：用于接收并保存用户发送的新弹幕。
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
 * 处理 GET 请求，获取弹幕数据。
 *
 * @param PDO $db 数据库连接实例。
 *
 * @return void
 */
function handleGetRequest(PDO $db): void
{
    $since_id = (int) ($_GET['since_id'] ?? 0);

    // 基础查询语句，用于联结弹幕表和用户表。
    $base_query = "
        SELECT
            b.id, b.content, b.color, b.bg_color, b.mode, b.speed,
            u.username, u.avatar_url
        FROM barrages b
        JOIN users u ON b.user_id = u.id
        WHERE b.status = 'visible'
    ";

    if ($since_id > 0) {
        // 如果提供了 since_id，则只获取该 ID 之后的新弹幕。
        $stmt = $db->prepare($base_query . " AND b.id > :since_id ORDER BY b.id ASC");
        $stmt->execute([':since_id' => $since_id]);
    } else {
        // 首次加载时，获取最新的 N 条弹幕，并按 ID 升序排列。
        $limit = (int) INITIAL_BARRAGE_LIMIT;
        $stmt = $db->prepare("
            SELECT t.id, t.content, t.color, t.bg_color, t.mode, t.speed, t.username, t.avatar_url
            FROM (
                {$base_query} ORDER BY b.id DESC LIMIT {$limit}
            ) AS t
            ORDER BY t.id ASC
        ");
        $stmt->execute();
    }

    $barrages = $stmt->fetchAll();
    send_json_response($barrages);
}

/**
 * 处理 POST 请求，创建新弹幕。
 *
 * @param PDO $db 数据库连接实例。
 *
 * @return void
 */
function handlePostRequest(PDO $db): void
{
    // 检查用户是否已登录。
    if (!isset($_SESSION['user_id'])) {
        send_json_response(['success' => false, 'message' => '请先登录才能发送弹幕！'], 401);
    }

    // 获取并验证弹幕内容。
    $content = trim($_POST['text'] ?? '');
    if (empty($content)) {
        send_json_response(['success' => false, 'message' => '弹幕内容不能为空！'], 400);
    }
    if (mb_strlen($content, 'UTF-8') > BARRAGE_MAX_LENGTH) {
        $message = '弹幕不能超过 ' . BARRAGE_MAX_LENGTH . ' 个字哦！';
        send_json_response(['success' => false, 'message' => $message], 400);
    }

    // 验证 mode 参数是否合法，默认值为 right。
    $allowed_modes = ['right', 'left', 'center'];
    $mode = $_POST['mode'] ?? 'right';
    if (!in_array($mode, $allowed_modes)) {
        $mode = 'right';
    }

    // 准备弹幕数据。
    $barrageData = [
        ':user_id' => $_SESSION['user_id'],                 // 当前登录用户 ID。
        ':content' => $content,                             // 弹幕文本内容。
        ':color' => $_POST['text_color'] ?? '#ffffff',   // 文字颜色，默认白色。
        ':bg_color' => $_POST['bg_color'] ?? '#000000',     // 背景颜色，默认黑色。
        ':mode' => $mode,                                // 弹幕模式：left、right 或 center。
        ':speed' => (int) ($_POST['speed'] ?? 100),        // 弹幕速度，默认值为 100。
    ];
    
    if (isset($barrageData['color']) && strlen($barrageData['color']) === 9) {
        $barrageData['color'] = substr($barrageData['color'], 0, 7);
    }

    if (!preg_match('/^#[a-fA-F0-9]{6}$/', $barrageData['color'])) {
        $barrageData['color'] = '#FFFFFF';
    }
    
    // 将弹幕数据插入数据库。
    $sql = "INSERT INTO barrages (user_id, content, color, bg_color, mode, speed)
            VALUES (:user_id, :content, :color, :bg_color, :mode, :speed)";
    $stmt = $db->prepare($sql);

    if ($stmt->execute($barrageData)) {
        send_json_response(['success' => true, 'message' => '弹幕发送成功！']);
    } else {
        send_json_response(['success' => false, 'message' => '哎呀，弹幕发送失败了...'], 500);
    }
}


// =======================
// 请求路由处理
// =======================

try {
    $db = get_db_connection(); // 获取数据库连接。
    $request_method = $_SERVER['REQUEST_METHOD'];

    if ($request_method === 'GET') {
        handleGetRequest($db);
    } elseif ($request_method === 'POST') {
        handlePostRequest($db);
    } else {
        // 对于不支持的方法，返回 405。
        send_json_response(['success' => false, 'message' => '不支持的请求方式！'], 405);
    }
} catch (Exception $e) {
    // 捕获异常，避免输出敏感信息。
    // error_log("Barrage API Error: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => '服务器发生未知错误。'], 500);
}
