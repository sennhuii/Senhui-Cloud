<?php
// API 入口
error_reporting(E_ALL);
ini_set('display_errors', 1); // 调试模式：打开错误显示
ini_set('html_errors', 0);

// 捕获致命错误
function cd_fatal_handler() {
    $error = error_get_last();
    if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('HTTP/1.1 500 Internal Server Error');
        }
        echo json_encode(array(
            'error' => 'PHP致命错误',
            'details' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ));
    }
}
register_shutdown_function('cd_fatal_handler');

require_once dirname(__FILE__) . '/helpers.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, X-Session-Token, x-session-token');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Max-Age: 86400');
}

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 兼容 IIS 等没有 REQUEST_URI 的情况
if (!isset($_SERVER['REQUEST_URI'])) {
    $_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'];
    if (!empty($_SERVER['QUERY_STRING'])) {
        $_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
    }
}

// 解析请求路径 - 兼容各种部署方式
$uri = '/';

// 方式1（最高优先级）：通过 query 参数 ?r=xxx （零配置，任何服务器都能用）
if (isset($_GET['r']) && $_GET['r'] !== '') {
    $uri = '/' . ltrim($_GET['r'], '/');
}
// 方式2：PATH_INFO（如 api/index.php/login）
elseif (!empty($_SERVER['PATH_INFO'])) {
    $uri = $_SERVER['PATH_INFO'];
}
// 方式3：URL 路径中包含 /api/xxx（需要伪静态支持）
else {
    $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (preg_match('#/api/(.+)$#', $requestPath, $m)) {
        $rest = $m[1];
        // 排除 index.php 这种文件名
        if (strpos($rest, '.php') === false && $rest !== '') {
            $uri = '/' . $rest;
        }
    }
}

$uri = rtrim($uri, '/');
if ($uri === '') $uri = '/';

$method = $_SERVER['REQUEST_METHOD'];

// 路由
try {
    // ===== 测试接口 =====
    if ($uri === '/test' && $method === 'GET') {
        cd_json_response(array(
            'success' => true,
            'message' => 'API 运行正常',
            'php_version' => phpversion(),
            'pdo_sqlite' => extension_loaded('pdo_sqlite'),
            'uri' => $uri,
            'method' => $method,
            'server_uri' => $_SERVER['REQUEST_URI']
        ));
    }
    
    // ===== 用户相关 =====
    if ($uri === '/register' && $method === 'POST') {
        $input = cd_get_json_input();
        $username = trim(isset($input['username']) ? $input['username'] : '');
        $email = trim(isset($input['email']) ? $input['email'] : '');
        $password = isset($input['password']) ? $input['password'] : '';
        
        if (!$username || !$email || !$password) {
            cd_error_response('请填写完整信息');
        }
        if (strlen($username) < 3 || strlen($username) > 20) {
            cd_error_response('用户名长度需在3-20位之间');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            cd_error_response('请输入有效的邮箱地址');
        }
        if (!cd_validate_email_domain($email)) {
            $emailParts = explode('@', $email);
            $domain = $emailParts[1];
            cd_error_response("不支持的邮箱后缀：{$domain}，请使用主流邮箱注册");
        }
        if (strlen($password) < 6) {
            cd_error_response('密码至少6位');
        }
        
        $db = cd_db_connect();
        
        // 检查用户名和邮箱是否已存在
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute(array($username, strtolower($email)));
        if ($stmt->fetch()) {
            cd_error_response('用户名或邮箱已被注册');
        }
        
        $hash = cd_hash_password($password);
        $stmt = $db->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
        $stmt->execute(array($username, strtolower($email), $hash));
        
        cd_json_response(array('success' => true, 'message' => '注册成功'));
    }
    
    elseif ($uri === '/login' && $method === 'POST') {
        $input = cd_get_json_input();
        $username = trim(isset($input['username']) ? $input['username'] : '');
        $password = isset($input['password']) ? $input['password'] : '';
        
        if (!$username || !$password) {
            cd_error_response('请输入用户名和密码');
        }
        
        $db = cd_db_connect();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute(array($username));
        $user = $stmt->fetch();
        
        if (!$user || !cd_verify_password($password, $user['password_hash'])) {
            cd_error_response('用户名或密码错误', 401);
        }
        
        $token = cd_create_session($user['id']);
        
        cd_json_response(array(
            'success' => true,
            'token' => $token,
            'user' => array(
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            )
        ));
    }
    
    elseif ($uri === '/logout' && $method === 'POST') {
        cd_destroy_session();
        cd_json_response(array('success' => true));
    }
    
    elseif ($uri === '/user' && $method === 'GET') {
        $user = cd_require_auth();
        cd_json_response(array('user' => $user));
    }
    
    // ===== 文件相关 =====
    elseif ($uri === '/files' && $method === 'GET') {
        $user = cd_require_auth();
        $db = cd_db_connect();
        
        $stmt = $db->prepare("SELECT id, filename, size, mime_type, share_id, uploaded_at FROM files WHERE user_id = ? ORDER BY uploaded_at DESC");
        $stmt->execute(array($user['id']));
        $files = $stmt->fetchAll();
        
        // 计算存储使用
        $stmt = $db->prepare("SELECT COALESCE(SUM(size), 0) as total FROM files WHERE user_id = ?");
        $stmt->execute(array($user['id']));
        $storage = $stmt->fetch();
        
        cd_json_response(array(
            'files' => $files,
            'storageUsed' => intval($storage['total']),
            'storageMax' => MAX_STORAGE
        ));
    }
    
    elseif ($uri === '/upload' && $method === 'POST') {
        $user = cd_require_auth();
        $db = cd_db_connect();
        
        if (!isset($_FILES['files']) || empty($_FILES['files']['name'])) {
            cd_error_response('请选择文件');
        }
        
        // 检查存储配额
        $stmt = $db->prepare("SELECT COALESCE(SUM(size), 0) as total FROM files WHERE user_id = ?");
        $stmt->execute(array($user['id']));
        $storageRow = $stmt->fetch();
        $currentStorage = intval($storageRow['total']);
        
        $totalSize = 0;
        $fileCount = is_array($_FILES['files']['name']) ? count($_FILES['files']['name']) : 1;
        
        if (is_array($_FILES['files']['name'])) {
            for ($i = 0; $i < $fileCount; $i++) {
                $totalSize += $_FILES['files']['size'][$i];
            }
        } else {
            $totalSize = $_FILES['files']['size'];
        }
        
        if ($currentStorage + $totalSize > MAX_STORAGE) {
            cd_error_response('存储空间不足');
        }
        
        // 确保上传目录存在
        if (!is_dir(UPLOAD_DIR)) {
            @mkdir(UPLOAD_DIR, 0755, true);
        }
        
        $insertedFiles = array();
        $insertStmt = $db->prepare("INSERT INTO files (id, user_id, filename, size, mime_type, storage_path) VALUES (?, ?, ?, ?, ?, ?)");
        
        // 处理单文件或多文件
        if (is_array($_FILES['files']['name'])) {
            $count = count($_FILES['files']['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }
                
                $fileId = 'file_' . time() . '_' . cd_gen_random(4);
                $originalName = $_FILES['files']['name'][$i];
                $size = $_FILES['files']['size'][$i];
                $mimeType = $_FILES['files']['type'][$i];
                $tmpName = $_FILES['files']['tmp_name'][$i];
                
                $storagePath = UPLOAD_DIR . $fileId . '_' . basename($originalName);
                move_uploaded_file($tmpName, $storagePath);
                
                $insertStmt->execute(array($fileId, $user['id'], $originalName, $size, $mimeType, $storagePath));
                
                $insertedFiles[] = array(
                    'id' => $fileId,
                    'name' => $originalName,
                    'size' => $size,
                    'type' => $mimeType,
                    'uploadedAt' => date('c'),
                    'shareId' => null
                );
            }
        } else {
            if ($_FILES['files']['error'] === UPLOAD_ERR_OK) {
                $fileId = 'file_' . time() . '_' . cd_gen_random(4);
                $originalName = $_FILES['files']['name'];
                $size = $_FILES['files']['size'];
                $mimeType = $_FILES['files']['type'];
                $tmpName = $_FILES['files']['tmp_name'];
                
                $storagePath = UPLOAD_DIR . $fileId . '_' . basename($originalName);
                move_uploaded_file($tmpName, $storagePath);
                
                $insertStmt->execute(array($fileId, $user['id'], $originalName, $size, $mimeType, $storagePath));
                
                $insertedFiles[] = array(
                    'id' => $fileId,
                    'name' => $originalName,
                    'size' => $size,
                    'type' => $mimeType,
                    'uploadedAt' => date('c'),
                    'shareId' => null
                );
            }
        }
        
        cd_json_response(array(
            'success' => true,
            'message' => '成功上传 ' . count($insertedFiles) . ' 个文件',
            'files' => $insertedFiles
        ));
    }
    
    elseif (preg_match('#^/download/(.+)$#', $uri, $m) && $method === 'GET') {
        $user = cd_require_auth();
        $fileId = $m[1];
        
        $db = cd_db_connect();
        $stmt = $db->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
        $stmt->execute(array($fileId, $user['id']));
        $file = $stmt->fetch();
        
        if (!$file) {
            cd_error_response('文件不存在', 404);
        }
        
        if (!file_exists($file['storage_path'])) {
            cd_log("下载文件不存在: " . $file['storage_path']);
            cd_error_response('文件数据丢失', 404);
        }
        
        // 清除所有之前的 header
        if (function_exists('header_remove')) {
            @header_remove('Content-Type');
            @header_remove('X-Powered-By');
        }
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        
        $filename = $file['filename'];
        $filesize = $file['size'];
        $mime = $file['mime_type'] ? $file['mime_type'] : 'application/octet-stream';
        
        // 处理中文文件名，兼容各种浏览器
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        if (preg_match('/MSIE/', $ua) || preg_match('/Trident/', $ua)) {
            $encodedFilename = rawurlencode($filename);
            header('Content-Disposition: attachment; filename="' . $encodedFilename . '"');
        } elseif (preg_match('/Firefox/', $ua)) {
            header('Content-Disposition: attachment; filename*="UTF-8\'\'' . rawurlencode($filename) . '"');
        } elseif (preg_match('/Safari/', $ua) && !preg_match('/Chrome/', $ua)) {
            $encodedFilename = rawurlencode($filename);
            header('Content-Disposition: attachment; filename="' . $encodedFilename . '"');
        } else {
            header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        }
        
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . $filesize);
        header('Accept-Ranges: bytes');
        header('Cache-Control: public, must-revalidate, max-age=0');
        header('Pragma: public');
        header('Expires: 0');
        
        // 流式输出文件
        $handle = fopen($file['storage_path'], 'rb');
        if ($handle) {
            while (!feof($handle)) {
                echo fread($handle, 8192);
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
            fclose($handle);
        } else {
            cd_log("无法打开文件: " . $file['storage_path']);
            cd_error_response('无法读取文件', 500);
        }
        exit;
    }
    
    elseif (preg_match('#^/files/(.+)$#', $uri, $m) && $method === 'DELETE') {
        $user = cd_require_auth();
        $fileId = $m[1];
        
        $db = cd_db_connect();
        $stmt = $db->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
        $stmt->execute(array($fileId, $user['id']));
        $file = $stmt->fetch();
        
        if (!$file) {
            cd_error_response('文件不存在', 404);
        }
        
        // 删除物理文件
        if (file_exists($file['storage_path'])) {
            @unlink($file['storage_path']);
        }
        
        $stmt = $db->prepare("DELETE FROM files WHERE id = ?");
        $stmt->execute(array($fileId));
        
        cd_json_response(array('success' => true, 'message' => '文件已删除'));
    }
    
    // ===== 分享相关 =====
    elseif (preg_match('#^/share/(.+)$#', $uri, $m) && $method === 'POST') {
        $user = cd_require_auth();
        $fileId = $m[1];
        
        $db = cd_db_connect();
        $stmt = $db->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
        $stmt->execute(array($fileId, $user['id']));
        $file = $stmt->fetch();
        
        if (!$file) {
            cd_error_response('文件不存在', 404);
        }
        
        $shareId = $file['share_id'];
        if (!$shareId) {
            $shareId = cd_generate_share_id();
            $stmt = $db->prepare("UPDATE files SET share_id = ? WHERE id = ?");
            $stmt->execute(array($shareId, $fileId));
        }
        
        cd_json_response(array(
            'success' => true,
            'shareId' => $shareId
        ));
    }
    
    elseif (preg_match('#^/share/(.+)$#', $uri, $m) && $method === 'DELETE') {
        $user = cd_require_auth();
        $fileId = $m[1];
        
        $db = cd_db_connect();
        $stmt = $db->prepare("SELECT * FROM files WHERE id = ? AND user_id = ?");
        $stmt->execute(array($fileId, $user['id']));
        $file = $stmt->fetch();
        
        if (!$file) {
            cd_error_response('文件不存在', 404);
        }
        
        $stmt = $db->prepare("UPDATE files SET share_id = NULL WHERE id = ?");
        $stmt->execute(array($fileId));
        
        cd_json_response(array('success' => true, 'message' => '已取消分享'));
    }
    
    // 获取分享信息（公开）
    elseif (preg_match('#^/share/info/(.+)$#', $uri, $m) && $method === 'GET') {
        $shareId = $m[1];
        
        $db = cd_db_connect();
        $stmt = $db->prepare("SELECT f.id, f.filename, f.size, f.mime_type, f.uploaded_at, u.username 
                               FROM files f JOIN users u ON f.user_id = u.id 
                               WHERE f.share_id = ?");
        $stmt->execute(array($shareId));
        $file = $stmt->fetch();
        
        if (!$file) {
            cd_error_response('分享不存在或已取消', 404);
        }
        
        cd_json_response(array(
            'file' => array(
                'id' => $file['id'],
                'name' => $file['filename'],
                'size' => intval($file['size']),
                'type' => $file['mime_type'],
                'uploadedAt' => $file['uploaded_at'],
                'uploader' => $file['username']
            ),
            'shareId' => $shareId
        ));
    }
    
    // 分享下载（公开）
    elseif (preg_match('#^/share/(.+)/download$#', $uri, $m) && $method === 'GET') {
        $shareId = $m[1];
        
        $db = cd_db_connect();
        $stmt = $db->prepare("SELECT * FROM files WHERE share_id = ?");
        $stmt->execute(array($shareId));
        $file = $stmt->fetch();
        
        if (!$file) {
            cd_error_response('分享不存在或已取消', 404);
        }
        
        if (!file_exists($file['storage_path'])) {
            cd_log("分享下载文件不存在: " . $file['storage_path']);
            cd_error_response('文件数据丢失', 404);
        }
        
        // 清除所有之前的 header，确保下载干净
        if (function_exists('header_remove')) {
            @header_remove('Content-Type');
            @header_remove('X-Powered-By');
        }
        // 关掉输出缓冲
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        
        $filename = $file['filename'];
        $filesize = $file['size'];
        $mime = $file['mime_type'] ? $file['mime_type'] : 'application/octet-stream';
        
        // 处理中文文件名，兼容各种浏览器
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        if (preg_match('/MSIE/', $ua) || preg_match('/Trident/', $ua)) {
            // IE
            $encodedFilename = rawurlencode($filename);
            header('Content-Disposition: attachment; filename="' . $encodedFilename . '"');
        } elseif (preg_match('/Firefox/', $ua)) {
            // Firefox
            header('Content-Disposition: attachment; filename*="UTF-8\'\'' . rawurlencode($filename) . '"');
        } elseif (preg_match('/Safari/', $ua) && !preg_match('/Chrome/', $ua)) {
            // Safari
            $encodedFilename = rawurlencode($filename);
            header('Content-Disposition: attachment; filename="' . $encodedFilename . '"');
        } else {
            // Chrome, Edge 等现代浏览器
            header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        }
        
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . $filesize);
        header('Accept-Ranges: bytes');
        header('Cache-Control: public, must-revalidate, max-age=0');
        header('Pragma: public');
        header('Expires: 0');
        
        // 流式输出文件
        $handle = fopen($file['storage_path'], 'rb');
        if ($handle) {
            while (!feof($handle)) {
                echo fread($handle, 8192);
                // 确保及时输出，避免内存溢出
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
            fclose($handle);
        } else {
            cd_log("无法打开文件: " . $file['storage_path']);
            cd_error_response('无法读取文件', 500);
        }
        exit;
    }
    
    // 我的分享列表
    elseif ($uri === '/shares' && $method === 'GET') {
        $user = cd_require_auth();
        $db = cd_db_connect();
        
        $stmt = $db->prepare("SELECT id, filename, size, share_id, uploaded_at FROM files WHERE user_id = ? AND share_id IS NOT NULL ORDER BY uploaded_at DESC");
        $stmt->execute(array($user['id']));
        $files = $stmt->fetchAll();
        
        cd_json_response(array('files' => $files));
    }
    
    // ===== 管理员 =====
    elseif ($uri === '/admin/users' && $method === 'GET') {
        $admin = cd_require_admin();
        $db = cd_db_connect();
        
        $stmt = $db->query("SELECT u.id, u.username, u.email, u.role, u.created_at,
                                   COUNT(f.id) as file_count,
                                   COALESCE(SUM(f.size), 0) as storage_used
                            FROM users u
                            LEFT JOIN files f ON u.id = f.user_id
                            GROUP BY u.id
                            ORDER BY u.created_at DESC");
        $users = $stmt->fetchAll();
        
        $stats = $db->query("SELECT
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM files) as total_files,
            (SELECT COALESCE(SUM(size), 0) FROM files) as total_storage,
            (SELECT COUNT(*) FROM files WHERE share_id IS NOT NULL) as total_shares
        ")->fetch();
        
        cd_json_response(array('users' => $users, 'stats' => $stats));
    }
    
    elseif (preg_match('#^/admin/users/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        $admin = cd_require_admin();
        $userId = intval($m[1]);
        
        if ($userId === intval($admin['id'])) {
            cd_error_response('不能删除自己');
        }
        
        $db = cd_db_connect();
        
        // 获取用户所有文件并删除
        $stmt = $db->prepare("SELECT * FROM files WHERE user_id = ?");
        $stmt->execute(array($userId));
        $files = $stmt->fetchAll();
        
        foreach ($files as $file) {
            if (file_exists($file['storage_path'])) {
                @unlink($file['storage_path']);
            }
        }
        
        // 删除用户（级联删除文件和会话）
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute(array($userId));
        
        cd_json_response(array('success' => true, 'message' => '用户已删除'));
    }
    
    // 404
    else {
        cd_error_response('接口不存在 - URI: ' . $uri, 404);
    }
    
} catch (Exception $e) {
    cd_log("API 异常: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
    cd_error_response('服务器错误: ' . $e->getMessage(), 500);
}
?>
