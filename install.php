<?php
// 环境检测工具 - 访问 /install.php 查看检测结果
error_reporting(E_ALL);
ini_set('display_errors', 1);

$passed = 0;
$failed = 0;
$warnings = 0;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CloudDrive 环境检测</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #f1f5f9; min-height: 100vh; padding: 40px 20px; }
    .container { max-width: 700px; margin: 0 auto; }
    h1 { font-size: 28px; margin-bottom: 8px; background: linear-gradient(135deg, #818cf8, #10b981); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .subtitle { color: #94a3b8; margin-bottom: 32px; }
    .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 24px; margin-bottom: 16px; }
    .item { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #334155; }
    .item:last-child { border-bottom: none; }
    .item-name { font-weight: 500; }
    .item-desc { font-size: 13px; color: #94a3b8; margin-top: 4px; }
    .status { padding: 4px 12px; border-radius: 20px; font-size: 13px; font-weight: 500; }
    .status.ok { background: rgba(16, 185, 129, 0.2); color: #10b981; }
    .status.fail { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
    .status.warn { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
    .summary { display: flex; gap: 16px; margin-top: 24px; }
    .summary-box { flex: 1; background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 20px; text-align: center; }
    .summary-num { font-size: 32px; font-weight: 700; }
    .summary-label { font-size: 13px; color: #94a3b8; margin-top: 4px; }
    .btn { display: inline-block; padding: 10px 20px; background: #6366f1; color: white; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-size: 14px; margin-top: 20px; }
    .btn:hover { background: #4f46e5; }
    code { background: #0f172a; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
</style>
</head>
<body>
<div class="container">
    <h1>CloudDrive 环境检测</h1>
    <p class="subtitle">检测你的服务器是否满足运行条件</p>

    <div class="card">
        <h3 style="margin-bottom: 12px;">PHP 环境</h3>
        <?php
        // PHP 版本
        $phpVersion = phpversion();
        $phpOk = version_compare($phpVersion, '5.6', '>=');
        ?>
        <div class="item">
            <div>
                <div class="item-name">PHP 版本</div>
                <div class="item-desc">当前版本：<?php echo $phpVersion; ?>（要求 5.6+）</div>
            </div>
            <span class="status <?php echo $phpOk ? 'ok' : 'fail'; ?>"><?php echo $phpOk ? '通过' : '不通过'; ?></span>
        </div>
        <?php if ($phpOk) $passed++; else $failed++; ?>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 12px;">必需扩展</h3>
        <?php
        // PDO
        $pdo = extension_loaded('pdo');
        ?>
        <div class="item">
            <div>
                <div class="item-name">PDO 扩展</div>
                <div class="item-desc">数据库操作必需</div>
            </div>
            <span class="status <?php echo $pdo ? 'ok' : 'fail'; ?>"><?php echo $pdo ? '已启用' : '未启用'; ?></span>
        </div>
        <?php if ($pdo) $passed++; else $failed++; ?>

        <?php
        // PDO SQLite
        $pdoSqlite = extension_loaded('pdo_sqlite');
        ?>
        <div class="item">
            <div>
                <div class="item-name">PDO_SQLite 扩展</div>
                <div class="item-desc">SQLite 数据库驱动，核心功能必需</div>
            </div>
            <span class="status <?php echo $pdoSqlite ? 'ok' : 'fail'; ?>"><?php echo $pdoSqlite ? '已启用' : '未启用'; ?></span>
        </div>
        <?php if ($pdoSqlite) $passed++; else $failed++; ?>

        <?php
        // SQLite3
        $sqlite3 = extension_loaded('sqlite3');
        ?>
        <div class="item">
            <div>
                <div class="item-name">SQLite3 扩展</div>
                <div class="item-desc">可选，有 PDO_SQLite 就行</div>
            </div>
            <span class="status <?php echo $sqlite3 ? 'ok' : 'warn'; ?>"><?php echo $sqlite3 ? '已启用' : '未安装'; ?></span>
        </div>
        <?php if ($sqlite3) $passed++; else $warnings++; ?>

        <?php
        // Fileinfo
        $fileinfo = extension_loaded('fileinfo');
        ?>
        <div class="item">
            <div>
                <div class="item-name">Fileinfo 扩展</div>
                <div class="item-desc">文件类型识别</div>
            </div>
            <span class="status <?php echo $fileinfo ? 'ok' : 'warn'; ?>"><?php echo $fileinfo ? '已启用' : '未安装'; ?></span>
        </div>
        <?php if ($fileinfo) $passed++; else $warnings++; ?>

        <?php
        // Mbstring
        $mbstring = extension_loaded('mbstring');
        ?>
        <div class="item">
            <div>
                <div class="item-name">Mbstring 扩展</div>
                <div class="item-desc">多字节字符串处理</div>
            </div>
            <span class="status <?php echo $mbstring ? 'ok' : 'warn'; ?>"><?php echo $mbstring ? '已启用' : '未安装'; ?></span>
        </div>
        <?php if ($mbstring) $passed++; else $warnings++; ?>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 12px;">目录权限</h3>
        <?php
        // 根目录可写
        $rootWritable = is_writable(__DIR__);
        ?>
        <div class="item">
            <div>
                <div class="item-name">网站根目录可写</div>
                <div class="item-desc">用于自动创建 data/ 和 uploads/ 目录</div>
            </div>
            <span class="status <?php echo $rootWritable ? 'ok' : 'warn'; ?>"><?php echo $rootWritable ? '可写' : '不可写'; ?></span>
        </div>
        <?php if ($rootWritable) $passed++; else $warnings++; ?>

        <?php
        // data 目录
        $dataDir = __DIR__ . '/data';
        $dataExists = is_dir($dataDir);
        $dataWritable = $dataExists && is_writable($dataDir);
        if (!$dataExists && $rootWritable) {
            @mkdir($dataDir, 0755, true);
            $dataExists = is_dir($dataDir);
            $dataWritable = is_writable($dataDir);
        }
        ?>
        <div class="item">
            <div>
                <div class="item-name">data/ 目录（数据库）</div>
                <div class="item-desc"><?php echo $dataExists ? '存在' : '不存在（请手动创建并设置 755 权限）'; ?></div>
            </div>
            <span class="status <?php echo $dataWritable ? 'ok' : 'fail'; ?>"><?php echo $dataWritable ? '可写' : '不可写'; ?></span>
        </div>
        <?php if ($dataWritable) $passed++; else $failed++; ?>

        <?php
        // uploads 目录
        $uploadDir = __DIR__ . '/uploads';
        $uploadExists = is_dir($uploadDir);
        $uploadWritable = $uploadExists && is_writable($uploadDir);
        if (!$uploadExists && $rootWritable) {
            @mkdir($uploadDir, 0755, true);
            $uploadExists = is_dir($uploadDir);
            $uploadWritable = is_writable($uploadDir);
        }
        ?>
        <div class="item">
            <div>
                <div class="item-name">uploads/ 目录（文件存储）</div>
                <div class="item-desc"><?php echo $uploadExists ? '存在' : '不存在（请手动创建并设置 755 权限）'; ?></div>
            </div>
            <span class="status <?php echo $uploadWritable ? 'ok' : 'fail'; ?>"><?php echo $uploadWritable ? '可写' : '不可写'; ?></span>
        </div>
        <?php if ($uploadWritable) $passed++; else $failed++; ?>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 12px;">PHP 配置</h3>
        <?php
        // 上传大小
        $uploadMax = ini_get('upload_max_filesize');
        $postMax = ini_get('post_max_size');
        $uploadOk = return_bytes($uploadMax) >= 100 * 1024 * 1024; // 至少 100MB
        ?>
        <div class="item">
            <div>
                <div class="item-name">单文件上传大小</div>
                <div class="item-desc">upload_max_filesize = <?php echo $uploadMax; ?>（建议 2G）</div>
            </div>
            <span class="status <?php echo $uploadOk ? 'ok' : 'warn'; ?>"><?php echo $uploadOk ? '足够' : '偏小'; ?></span>
        </div>
        <?php if ($uploadOk) $passed++; else $warnings++; ?>

        <div class="item">
            <div>
                <div class="item-name">POST 最大大小</div>
                <div class="item-desc">post_max_size = <?php echo $postMax; ?></div>
            </div>
            <span class="status <?php echo return_bytes($postMax) >= 100 * 1024 * 1024 ? 'ok' : 'warn'; ?>"><?php echo return_bytes($postMax) >= 100 * 1024 * 1024 ? '足够' : '偏小'; ?></span>
        </div>
        <?php if (return_bytes($postMax) >= 100 * 1024 * 1024) $passed++; else $warnings++; ?>

        <?php
        $maxExec = ini_get('max_execution_time');
        ?>
        <div class="item">
            <div>
                <div class="item-name">脚本最大执行时间</div>
                <div class="item-desc">max_execution_time = <?php echo $maxExec; ?> 秒（建议 300+）</div>
            </div>
            <span class="status <?php echo $maxExec >= 60 ? 'ok' : 'warn'; ?>"><?php echo $maxExec >= 60 ? '足够' : '偏短'; ?></span>
        </div>
        <?php if ($maxExec >= 60) $passed++; else $warnings++; ?>
    </div>

    <div class="card">
        <h3 style="margin-bottom: 12px;">数据库测试</h3>
        <?php
        $dbTest = false;
        $dbError = '';
        if ($pdoSqlite && $dataWritable) {
            try {
                $dbFile = $dataDir . '/test.sqlite';
                $db = new PDO('sqlite:' . $dbFile);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $db->exec("CREATE TABLE IF NOT EXISTS test (id INTEGER PRIMARY KEY, name TEXT)");
                $db->exec("INSERT INTO test (name) VALUES ('hello')");
                $row = $db->query("SELECT * FROM test LIMIT 1")->fetch();
                $dbTest = $row && $row['name'] === 'hello';
                @unlink($dbFile);
            } catch (Exception $e) {
                $dbError = $e->getMessage();
            }
        }
        ?>
        <div class="item">
            <div>
                <div class="item-name">SQLite 读写测试</div>
                <div class="item-desc"><?php echo $dbTest ? '读写正常' : ($dbError ? '错误：' . $dbError : '前置条件不满足'); ?></div>
            </div>
            <span class="status <?php echo $dbTest ? 'ok' : 'fail'; ?>"><?php echo $dbTest ? '通过' : '失败'; ?></span>
        </div>
        <?php if ($dbTest) $passed++; else $failed++; ?>
    </div>

    <div class="summary">
        <div class="summary-box">
            <div class="summary-num" style="color: #10b981;"><?php echo $passed; ?></div>
            <div class="summary-label">通过</div>
        </div>
        <div class="summary-box">
            <div class="summary-num" style="color: #f59e0b;"><?php echo $warnings; ?></div>
            <div class="summary-label">警告</div>
        </div>
        <div class="summary-box">
            <div class="summary-num" style="color: #ef4444;"><?php echo $failed; ?></div>
            <div class="summary-label">不通过</div>
        </div>
    </div>

    <div style="text-align: center;">
        <?php if ($failed === 0): ?>
            <a href="./" class="btn">✅ 环境正常，进入网盘</a>
        <?php else: ?>
            <p style="color: #ef4444; margin-top: 20px;">有 <?php echo $failed; ?> 项不通过，请根据上面的说明修复后再试</p>
            <p style="color: #94a3b8; margin-top: 10px; font-size: 13px;">最常见的问题是 PDO_SQLite 扩展未启用，请到主机面板的 PHP 设置里开启</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

<?php
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}
?>
