<?php
/**
 * ============================================================================
 * 上传头像 API 接口（upload_avatar.php）
 * ============================================================================
 * 
 * @package     PanoramaBarrage\API
 * @author      Racpast
 * @version     1.0.0
 * @license     MIT
 * 
 * @description
 * 该文件仅支持 POST 请求。用于已登录用户上传、更新自己的头像。
 * 它会处理文件验证、旧头像清理、新头像保存及数据库更新。
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
 * 处理上传头像的 POST 请求。
 *
 * @param PDO $db 数据库连接实例。
 *
 * @return void
 */
function handleAvatarUploadRequest(PDO $db): void
{
    // 1. 权限检查，确保用户已登录。
    if (!isset($_SESSION['user_id'])) {
        send_json_response(['success' => false, 'message' => '请先登录！'], 401);
    }
    $userId = $_SESSION['user_id'];

    // 2. 文件上传基础验证。
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = '没有收到文件或上传出错，错误码：' . ($_FILES['avatar']['error'] ?? '未知');
        send_json_response(['success' => false, 'message' => $errorMessage], 400);
    }

    // 3. 文件类型和大小的详细验证。
    $allowedTypes = json_decode(AVATAR_ALLOWED_TYPES, true);
    if (!in_array($_FILES['avatar']['type'], $allowedTypes)) {
        send_json_response(['success' => false, 'message' => '图片格式不支持！请上传 JPG，PNG，或 GIF 格式。'], 415); // 415 Unsupported Media Type
    }
    if ($_FILES['avatar']['size'] > AVATAR_MAX_SIZE) {
        $maxSizeMB = round(AVATAR_MAX_SIZE / 1024 / 1024, 1);
        send_json_response(['success' => false, 'message' => "图片太大了！请上传小于 {$maxSizeMB}MB 的图片。"], 413); // 413 Payload Too Large
    }

    // 4. 准备文件路径和名称，全部从配置中读取。
    $uploadDir = UPLOADS_PATH_AVATARS;
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true); // 如果目录不存在，尝试创建它
    }
    if (!is_writable($uploadDir)) {
        // error_log("Upload directory is not writable: " . $uploadDir);
        send_json_response(['success' => false, 'message' => '服务器上传目录配置错误或不可写！'], 500);
    }
    $fileExtension = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
    $newFileName = $userId . '_' . time() . '.' . $fileExtension;
    $newFilePath = "{$uploadDir}{$newFileName}";
    $dbPath = UPLOADS_URL_AVATARS . $newFileName; // 存入数据库的相对路径

    // 5. 使用事务处理文件移动、数据库更新和旧文件删除。
    $db->beginTransaction();
    try {
        // 5.1. 获取旧头像路径。
        $stmt = $db->prepare("SELECT avatar_url FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $oldAvatarUrl = $stmt->fetchColumn();

        // 5.2. 移动新上传的文件。
        if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $newFilePath)) {
            throw new RuntimeException('移动上传文件失败！');
        }

        // 5.3. 更新数据库。
        $update_stmt = $db->prepare("UPDATE users SET avatar_url = :avatar_url WHERE id = :user_id");
        $update_stmt->execute([':avatar_url' => $dbPath, ':user_id' => $userId]);

        // 5.4. 提交数据库事务。
        $db->commit();

        // 5.5. 安全地删除旧头像文件。
        if ($oldAvatarUrl && !str_contains($oldAvatarUrl, 'default-avatar.png')) {
            // 从 URL 路径反推物理路径
            $oldAvatarPath = UPLOADS_PATH_AVATARS . basename($oldAvatarUrl);
            if (file_exists($oldAvatarPath)) {
                unlink($oldAvatarPath);
            }
        }

    } catch (Exception $e) {
        // 5.6. 如果任何一步失败，回滚数据库操作。
        $db->rollBack();
        // 并且，如果新文件已经被创建，也应该删除它，保持环境干净。
        if (file_exists($newFilePath)) {
            unlink($newFilePath);
        }
        // error_log("Avatar Upload Failed: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => '处理头像时发生严重错误！'], 500);
    }

    // 6. 更新当前会话中的头像信息并返回成功响应。
    $_SESSION['avatar_url'] = $dbPath;
    send_json_response([
        'success' => true,
        'message' => '头像更新成功！',
        'newAvatarUrl' => $dbPath
    ]);
}

// =======================
// 请求路由处理
// =======================

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $db = get_db_connection();
        handleAvatarUploadRequest($db);
    } else {
        send_json_response(['success' => false, 'message' => '不支持的请求方式！'], 405);
    }
} catch (Exception $e) {
    // error_log("Upload Avatar API Error: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => '服务器发生未知错误。'], 500);
}