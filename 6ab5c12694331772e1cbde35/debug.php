<?php
// 逐步调试文件 - 访问 debug.php 看具体哪里出错
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>逐步调试</h1>";
echo "<p>PHP 版本: " . phpversion() . "</p>";

$steps = array(
    'step1' => '基础语法检查',
    'step2' => '加载 config.php',
    'step3' => '加载 helpers.php',
    'step4' => '连接数据库',
    'step5' => '查询管理员账号',
);

$current_step = isset($_GET['step']) ? $_GET['step'] : 'step1';

echo "<h2>当前步骤: " . $steps[$current_step] . "</h2>";

switch ($current_step) {
    case 'step1':
        $test = array('a' => 1, 'b' => 2);
        echo '<p style="color:green">✓ 数组语法正常</p>';
        
        $str = "hello " . $test['a'] . " world";
        echo '<p style="color:green">✓ 字符串插值正常</p>';
        
        function test_func_cd() {
            return 'ok';
        }
        echo '<p style="color:green">✓ 函数定义正常</p>';
        
        echo '<p><a href="?step=step2">下一步 → 加载 config.php</a></p>';
        break;
        
    case 'step2':
        echo '<p>正在加载 config.php...</p>';
        require_once dirname(__FILE__) . '/api/config.php';
        echo '<p style="color:green">✓ config.php 加载成功</p>';
        echo '<p>DB_PATH: ' . DB_PATH . '</p>';
        echo '<p>UPLOAD_DIR: ' . UPLOAD_DIR . '</p>';
        echo '<p>MAX_STORAGE: ' . MAX_STORAGE . '</p>';
        echo '<p>邮箱白名单数量: ' . count($ALLOWED_EMAIL_DOMAINS) . '</p>';
        echo '<p><a href="?step=step3">下一步 → 加载 helpers.php</a></p>';
        break;
        
    case 'step3':
        echo '<p>正在加载 helpers.php...</p>';
        require_once dirname(__FILE__) . '/api/helpers.php';
        echo '<p style="color:green">✓ helpers.php 加载成功</p>';
        
        echo '<p>测试 cd_gen_random: ' . cd_gen_random(4) . '</p>';
        echo '<p>测试 cd_hash_password: ' . cd_hash_password('test123') . '</p>';
        
        $hash = cd_hash_password('test123');
        $verify = cd_verify_password('test123', $hash);
        echo '<p>测试 cd_verify_password: ' . ($verify ? '<span style="color:green">通过</span>' : '<span style="color:red">失败</span>') . '</p>';
        
        echo '<p><a href="?step=step4">下一步 → 连接数据库</a></p>';
        break;
        
    case 'step4':
        require_once dirname(__FILE__) . '/api/helpers.php';
        echo '<p>正在连接数据库...</p>';
        try {
            $db = cd_db_connect();
            echo '<p style="color:green">✓ 数据库连接成功</p>';
            
            $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
            $tables = $stmt->fetchAll();
            $tableNames = array();
            foreach ($tables as $t) {
                $tableNames[] = $t['name'];
            }
            echo '<p>数据表: ' . implode(', ', $tableNames) . '</p>';
            
            echo '<p><a href="?step=step5">下一步 → 查询管理员账号</a></p>';
        } catch (Exception $e) {
            echo '<p style="color:red">✗ 数据库连接失败</p>';
            echo '<p>错误信息: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p>错误文件: ' . htmlspecialchars($e->getFile()) . '</p>';
            echo '<p>错误行号: ' . $e->getLine() . '</p>';
        }
        break;
        
    case 'step5':
        require_once dirname(__FILE__) . '/api/helpers.php';
        $db = cd_db_connect();
        echo '<p>正在查询管理员账号...</p>';
        
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute(array('admin'));
        $user = $stmt->fetch();
        
        if ($user) {
            echo '<p style="color:green">✓ 管理员账号存在</p>';
            echo '<p>ID: ' . $user['id'] . '</p>';
            echo '<p>用户名: ' . $user['username'] . '</p>';
            echo '<p>角色: ' . $user['role'] . '</p>';
            echo '<p>密码哈希长度: ' . strlen($user['password_hash']) . '</p>';
            
            echo '<p>测试密码验证... ';
            if (cd_verify_password('admin123456', $user['password_hash'])) {
                echo '<span style="color:green">通过！</span></p>';
            } else {
                echo '<span style="color:red">失败！</span></p>';
            }
            
            echo '<p>测试创建会话... ';
            $token = cd_create_session($user['id']);
            echo '<span style="color:green">成功，token: ' . substr($token, 0, 16) . '...</span></p>';
            
            echo '<p><a href="api/index.php?r=test">测试 API 接口 /test</a></p>';
            echo '<p><a href="index.html">返回首页，尝试登录</a></p>';
        } else {
            echo '<p style="color:red">✗ 管理员账号不存在</p>';
        }
        break;
}
?>
