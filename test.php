<?php
// 最简单的测试文件 - 访问 test.php 看能不能正常显示
// 如果你连这个页面都打不开，说明 PHP 环境有问题，不是代码的问题

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PHP 运行正常</h1>";
echo "<p>PHP 版本: " . phpversion() . "</p>";
echo "<p>操作系统: " . PHP_OS . "</p>";
echo "<p>服务器软件: " . (isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '未知') . "</p>";

echo "<h2>扩展检查</h2>";
$exts = array('pdo', 'pdo_sqlite', 'sqlite3', 'json', 'mbstring', 'fileinfo');
foreach ($exts as $ext) {
    $status = extension_loaded($ext) ? '<span style="color:green">✓ 已启用</span>' : '<span style="color:red">✗ 未启用</span>';
    echo "<p>{$ext}: {$status}</p>";
}

echo "<h2>REQUEST_URI 检查</h2>";
echo "<p>REQUEST_URI: " . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '<span style="color:red">不存在！</span>') . "</p>";
echo "<p>PHP_SELF: " . (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '不存在') . "</p>";
echo "<p>SCRIPT_NAME: " . (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '不存在') . "</p>";

echo "<h2>目录权限检查</h2>";
$root = dirname(__FILE__);
echo "<p>根目录: {$root}</p>";
echo "<p>根目录可写: " . (is_writable($root) ? '<span style="color:green">是</span>' : '<span style="color:red">否</span>') . "</p>";

$dataDir = $root . '/data';
if (is_dir($dataDir)) {
    echo "<p>data/ 目录可写: " . (is_writable($dataDir) ? '<span style="color:green">是</span>' : '<span style="color:red">否</span>') . "</p>";
} else {
    echo "<p>data/ 目录: 不存在</p>";
}

echo "<h2>数据库测试</h2>";
if (extension_loaded('pdo') && extension_loaded('pdo_sqlite')) {
    try {
        $dbFile = $root . '/data/test.sqlite';
        if (!is_dir(dirname($dbFile))) {
            @mkdir(dirname($dbFile), 0755, true);
        }
        $db = new PDO('sqlite:' . $dbFile);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec("CREATE TABLE IF NOT EXISTS test (id INTEGER PRIMARY KEY, name TEXT)");
        $db->exec("INSERT INTO test (name) VALUES ('hello')");
        $row = $db->query("SELECT * FROM test LIMIT 1")->fetch();
        if ($row && $row['name'] === 'hello') {
            echo '<p style="color:green">✓ SQLite 数据库读写正常</p>';
        } else {
            echo '<p style="color:red">✗ SQLite 查询失败</p>';
        }
        @unlink($dbFile);
    } catch (Exception $e) {
        echo '<p style="color:red">✗ 数据库错误: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
} else {
    echo '<p style="color:red">✗ PDO_SQLite 扩展未安装</p>';
}

echo "<h2>下一步</h2>";
echo "<p>如果上面都正常，<a href='api/index.php?r=test'>点这里测试 API</a></p>";
?>
