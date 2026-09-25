<?php
// 配置文件
define('DB_PATH', dirname(__FILE__) . '/../data/database.sqlite');
define('UPLOAD_DIR', dirname(__FILE__) . '/../uploads/');
define('MAX_STORAGE', 10 * 1024 * 1024 * 1024); // 10GB
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024 * 1024); // 单文件最大 2GB
define('PASSWORD_SALT', 'clouddrive_salt_2026_php');

// 允许的邮箱后缀
$ALLOWED_EMAIL_DOMAINS = array(
    'gmail.com', 'qq.com', 'vip.qq.com', 'foxmail.com',
    '139.com', '126.com', '163.com', 'yeah.net',
    'sina.com', 'sina.cn', 'sohu.com', 'hotmail.com',
    'outlook.com', 'yahoo.com', 'yahoo.com.cn', 'icloud.com',
    'aliyun.com', '189.cn', 'wo.cn', '21cn.com'
);
?>
