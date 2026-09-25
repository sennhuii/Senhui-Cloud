<?php
// 辅助函数
require_once dirname(__FILE__) . '/config.php';

// 错误日志记录
function cd_log($message) {
    $logDir = dirname(DB_PATH);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/error.log';
    $date = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[{$date}] {$message}\n", FILE_APPEND);
}

// 自定义错误处理
function cd_error_handler($errno, $errstr, $errfile, $errline) {
    cd_log("PHP Error [{$errno}]: {$errstr} in {$errfile} line {$errline}");
}
set_error_handler('cd_error_handler');

// 自定义异常处理
function cd_exception_handler($e) {
    cd_log("Uncaught Exception: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('error' => '服务器内部错误，请查看日志'));
    exit;
}
set_exception_handler('cd_exception_handler');

// 连接数据库
function cd_db_connect() {
    global $cd_db;
    if ($cd_db) return $cd_db;
    
    // 检查 PDO_SQLite
    if (!extension_loaded('pdo') || !extension_loaded('pdo_sqlite')) {
        cd_log("PDO_SQLite 扩展未安装");
        throw new Exception("服务器缺少 PDO_SQLite 扩展");
    }
    
    $dbDir = dirname(DB_PATH);
    if (!is_dir($dbDir)) {
        if (!@mkdir($dbDir, 0755, true)) {
            cd_log("无法创建数据目录: {$dbDir}");
            throw new Exception("无法创建数据目录，请检查权限");
        }
    }
    
    if (!is_writable($dbDir)) {
        cd_log("数据目录不可写: {$dbDir}");
        throw new Exception("数据目录不可写，请设置 755 权限");
    }
    
    try {
        $cd_db = new PDO('sqlite:' . DB_PATH);
        $cd_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $cd_db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $cd_db->exec("PRAGMA journal_mode = WAL");
        $cd_db->exec("PRAGMA foreign_keys = ON");
    } catch (Exception $e) {
        cd_log("数据库连接失败: " . $e->getMessage());
        throw new Exception("数据库连接失败: " . $e->getMessage());
    }
    
    // 初始化表
    cd_db_init($cd_db);
    
    return $cd_db;
}

// 初始化数据库表
function cd_db_init($db) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT DEFAULT 'user',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS files (
            id TEXT PRIMARY KEY,
            user_id INTEGER NOT NULL,
            filename TEXT NOT NULL,
            size INTEGER NOT NULL,
            mime_type TEXT,
            storage_path TEXT NOT NULL,
            share_id TEXT UNIQUE,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS sessions (
            token TEXT PRIMARY KEY,
            user_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        
        $db->exec("CREATE INDEX IF NOT EXISTS idx_files_user ON files(user_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_files_share ON files(share_id)");
        
        // 初始化管理员
        $admin = $db->prepare("SELECT id FROM users WHERE username = ?");
        $admin->execute(array('admin'));
        if (!$admin->fetch()) {
            $hash = cd_hash_password('admin123456');
            $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
            $stmt->execute(array('admin', 'admin@clouddrive.local', $hash));
            cd_log("管理员账号已创建: admin");
        }
    } catch (Exception $e) {
        cd_log("数据库初始化失败: " . $e->getMessage());
        throw $e;
    }
}

// JSON 响应
function cd_json_response($data, $code = 200) {
    http_response_code($code);
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    // 兼容 PHP 5.3 以下没有 JSON_UNESCAPED_UNICODE
    if (defined('JSON_UNESCAPED_UNICODE')) {
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode($data);
    }
    exit;
}

// 错误响应
function cd_error_response($message, $code = 400) {
    cd_json_response(array('error' => $message), $code);
}

// 密码哈希（兼容老版本 PHP）
function cd_hash_password($password) {
    if (function_exists('password_hash')) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    // 兼容方案：使用 MD5 + 盐（PHP 5.4 及以下）
    return '$cd$' . md5(PASSWORD_SALT . $password);
}

function cd_verify_password($password, $hash) {
    // 标准 password_hash 格式
    if (function_exists('password_verify') && strpos($hash, '$cd$') !== 0) {
        return password_verify($password, $hash);
    }
    // 兼容旧格式
    if (strpos($hash, '$cd$') === 0) {
        $oldHash = substr($hash, 4);
        return md5(PASSWORD_SALT . $password) === $oldHash;
    }
    return false;
}

// 生成随机 ID
function cd_generate_id($prefix = '') {
    return $prefix . cd_gen_random(8);
}

function cd_generate_share_id() {
    return 'share_' . cd_gen_random(10);
}

// 兼容 PHP 5.x 的随机字节生成
function cd_gen_random($length) {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($length));
    }
    if (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes($length));
    }
    // 兼容方案
    $bytes = '';
    for ($i = 0; $i < $length; $i++) {
        $bytes .= chr(mt_rand(0, 255));
    }
    return bin2hex($bytes);
}

// 邮箱域名验证
function cd_validate_email_domain($email) {
    global $ALLOWED_EMAIL_DOMAINS;
    $parts = explode('@', $email);
    if (count($parts) !== 2) return false;
    $domain = strtolower($parts[1]);
    return in_array($domain, $ALLOWED_EMAIL_DOMAINS);
}

// 获取当前登录用户
function cd_get_current_user() {
    $token = '';
    // 方式1：从 $_SERVER 取（大多数情况）
    if (isset($_SERVER['HTTP_X_SESSION_TOKEN'])) {
        $token = $_SERVER['HTTP_X_SESSION_TOKEN'];
    }
    // 方式2：getallheaders（Nginx/fastcgi 可能需要）
    if (!$token && function_exists('getallheaders')) {
        $headers = @getallheaders();
        if ($headers) {
            if (isset($headers['X-Session-Token'])) {
                $token = $headers['X-Session-Token'];
            } elseif (isset($headers['x-session-token'])) {
                $token = $headers['x-session-token'];
            }
        }
    }
    // 方式3：apache_request_headers
    if (!$token && function_exists('apache_request_headers')) {
        $headers = @apache_request_headers();
        if ($headers) {
            if (isset($headers['X-Session-Token'])) {
                $token = $headers['X-Session-Token'];
            } elseif (isset($headers['x-session-token'])) {
                $token = $headers['x-session-token'];
            }
        }
    }
    // 方式4：URL 参数（下载时用）
    if (!$token && isset($_GET['token'])) {
        $token = $_GET['token'];
    }
    
    if (!$token) return null;
    
    try {
        $db = cd_db_connect();
        $stmt = $db->prepare("SELECT u.* FROM sessions s JOIN users u ON s.user_id = u.id WHERE s.token = ?");
        $stmt->execute(array($token));
        $user = $stmt->fetch();
        
        if (!$user) return null;
        
        return array(
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role']
        );
    } catch (Exception $e) {
        cd_log("获取用户失败: " . $e->getMessage());
        return null;
    }
}

// 要求登录
function cd_require_auth() {
    $user = cd_get_current_user();
    if (!$user) {
        cd_error_response('请先登录', 401);
    }
    return $user;
}

// 要求管理员
function cd_require_admin() {
    $user = cd_require_auth();
    if ($user['role'] !== 'admin') {
        cd_error_response('无权限', 403);
    }
    return $user;
}

// 创建会话
function cd_create_session($user_id) {
    $token = cd_gen_random(32);
    $db = cd_db_connect();
    $stmt = $db->prepare("INSERT INTO sessions (token, user_id) VALUES (?, ?)");
    $stmt->execute(array($token, $user_id));
    return $token;
}

// 销毁会话
function cd_destroy_session() {
    $token = isset($_SERVER['HTTP_X_SESSION_TOKEN']) ? $_SERVER['HTTP_X_SESSION_TOKEN'] : '';
    if (!$token && function_exists('getallheaders')) {
        $headers = @getallheaders();
        if ($headers && isset($headers['X-Session-Token'])) {
            $token = $headers['X-Session-Token'];
        }
    }
    if ($token) {
        try {
            $db = cd_db_connect();
            $stmt = $db->prepare("DELETE FROM sessions WHERE token = ?");
            $stmt->execute(array($token));
        } catch (Exception $e) {
            cd_log("销毁会话失败: " . $e->getMessage());
        }
    }
}

// 格式化文件大小
function cd_format_size($bytes) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// 获取 POST JSON 数据
function cd_get_json_input() {
    // 方式1：从 php://input 读取（标准 JSON POST）
    $input = @file_get_contents('php://input');
    if ($input) {
        $data = json_decode($input, true);
        if (is_array($data)) {
            return $data;
        }
    }
    
    // 方式2：如果是 form-data 或 x-www-form-urlencoded
    if (!empty($_POST)) {
        return $_POST;
    }
    
    return array();
}
?>
