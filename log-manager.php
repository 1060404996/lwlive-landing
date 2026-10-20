<?php
/* ============================================================
   LwLive 更新日志管理（单文件）
   - 放到宝塔站点目录，例如：/www/wwwroot/api.yjtv.top/admin/log.php
   - 数据文件：同站点 data/changelog.json（与 config.json 同级，自动创建）
   - 落地页/Worker 读取该文件展示"更新日志"
   - 首次使用请修改 ADMIN_PASS 为你的密码
   ============================================================ */
session_start();
define('ADMIN_PASS', 'lwlive2026');          // ★ 改成你自己的密码
define('DATA_FILE', dirname(__DIR__) . '/data/changelog.json');

function load_logs(){
  if (!file_exists(DATA_FILE)) return [];
  $s = @file_get_contents(DATA_FILE);
  $j = json_decode((string)$s, true);
  return (is_array($j) && isset($j['list']) && is_array($j['list'])) ? $j['list'] : [];
}
function save_logs($logs){
  $j = json_encode(['list' => array_values($logs)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  @file_put_contents(DATA_FILE, $j);
}

/* ---- 登录 ---- */
if (isset($_POST['action']) && $_POST['action'] === 'login'){
  if (hash_equals(ADMIN_PASS, (string)($_POST['pass'] ?? ''))){
    $_SESSION['lw_log_auth'] = true;
  } else {
    $err = '密码错误';
  }
}
$authed = !empty($_SESSION['lw_log_auth']);
if ($authed && isset($_POST['action']) && $_POST['action'] === 'logout'){
  session_destroy();
  $authed = false;
}

/* ---- 增删改（登录 + CSRF） ---- */
$logs = [];
if ($authed){
  if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(8));
  $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
  if ($ok){
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'add'){
      $date = trim((string)($_POST['date'] ?? ''));
      $content = trim((string)($_POST['content'] ?? ''));
      if ($date !== '' && $content !== ''){
        $logs = load_logs();
        array_unshift($logs, ['date' => $date, 'content' => $content]); // 最新在前
        save_logs($logs);
      }
    } elseif ($action === 'del' && isset($_POST['idx'])){
      $logs = load_logs();
      $idx = (int)$_POST['idx'];
      if (isset($logs[$idx])) { array_splice($logs, $idx, 1); save_logs($logs); }
    } elseif ($action === 'edit' && isset($_POST['idx'])){
      $logs = load_logs();
      $idx = (int)$_POST['idx'];
      if (isset($logs[$idx])){
        $logs[$idx]['date'] = trim((string)($_POST['date'] ?? $logs[$idx]['date']));
        $logs[$idx]['content'] = trim((string)($_POST['content'] ?? $logs[$idx]['content']));
        save_logs($logs);
      }
    }
  }
  $logs = load_logs();
}
$csrf = isset($_SESSION['csrf']) ? $_SESSION['csrf'] : '';
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="zh">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>LwLive · 更新日志管理</title>
<style>
  *{box-sizing:border-box;margin:0}
  body{font-family:-apple-system,"PingFang SC","Microsoft YaHei",sans-serif;background:#0f1115;color:#e8e8ec;min-height:100vh}
  .wrap{max-width:760px;margin:0 auto;padding:28px 16px 60px}
  h1{font-size:20px;margin-bottom:4px}
  .sub{color:#8a8f9c;font-size:12.5px;margin-bottom:22px}
  .card{background:#191c24;border:1px solid #262b36;border-radius:14px;padding:18px;margin-bottom:16px}
  h2{font-size:15px;margin-bottom:14px;color:#f0c878}
  label{display:block;font-size:12.5px;color:#aab;margin-bottom:5px}
  input[type=text],input[type=datetime-local],textarea{
    width:100%;background:#10131a;border:1px solid #2c3342;border-radius:8px;
    color:#eee;padding:10px 12px;font-size:14px;margin-bottom:12px;font-family:inherit}
  textarea{min-height:72px;resize:vertical}
  .btn{display:inline-block;border:0;border-radius:8px;padding:10px 18px;font-size:14px;cursor:pointer;font-family:inherit}
  .btn-primary{background:#f0c878;color:#171204;font-weight:600}
  .btn-danger{background:#3a2530;color:#ff7d93}
  .btn-ghost{background:#242a36;color:#cfd4e0}
  .btn:hover{opacity:.88}
  .item{border:1px solid #262b36;border-radius:10px;background:#13161e;padding:14px 14px 10px;margin-bottom:10px}
  .item-date{font-size:14px;font-weight:600;color:#eef;margin-bottom:6px}
  .item-content{font-size:13px;color:#b8bdcc;line-height:1.6;white-space:pre-wrap;margin-bottom:10px}
  .item-actions{display:flex;gap:8px}
  .empty{color:#6a7080;font-size:13px;padding:18px 0;text-align:center}
  .err{color:#ff7d93;font-size:13px;margin:8px 0}
  .note{color:#6a7080;font-size:12px;margin-top:14px;line-height:1.7}
  form.inline{display:inline}
  .edit-form{display:none;margin-top:10px}
  .item.editing .edit-form{display:block}
  .item.editing .item-view{display:none}
  .btn-sm{padding:7px 12px;font-size:12.5px;border-radius:7px}
  .pass{max-width:320px;margin:80px auto 0;text-align:center}
  .pass h1{margin-bottom:18px}
  .pass input{margin-bottom:14px}
  .pass .btn{width:100%}
</style>
</head>
<body>
<div class="wrap">

<?php if (!$authed): ?>
  <div class="card pass">
    <h1>更新日志管理</h1>
    <?php if (isset($err)): ?><div class="err"><?php echo e($err); ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="action" value="login">
      <input type="password" name="pass" placeholder="管理密码">
      <button class="btn btn-primary" type="submit">进入</button>
    </form>
  </div>
<?php else: ?>
  <h1>更新日志管理</h1>
  <div class="sub">前台落地页「更新日志」展示的就是这里的内容 · 最新一条默认展开</div>

  <div class="card">
    <h2>＋ 添加一条更新</h2>
    <form method="post">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
      <label>更新日期（可手动改格式）</label>
      <input type="text" name="date" placeholder="如 2026-10-20 18:05" value="<?php echo date('Y-m-d H:i'); ?>">
      <label>更新内容</label>
      <textarea name="content" placeholder="这次更新了什么…（多行也可以）"></textarea>
      <button class="btn btn-primary" type="submit">添加</button>
    </form>
  </div>

  <div class="card">
    <h2>更新记录（最新在前）</h2>
    <?php if (!$logs): ?>
      <div class="empty">还没有日志，先在上面添加一条</div>
    <?php else: ?>
      <?php foreach ($logs as $i => $item): ?>
      <div class="item" id="item-<?php echo $i; ?>">
        <div class="item-view">
          <div class="item-date"><?php echo e($item['date']); ?></div>
          <div class="item-content"><?php echo e($item['content']); ?></div>
          <div class="item-actions">
            <button class="btn btn-ghost btn-sm" onclick="document.getElementById('item-<?php echo $i; ?>').classList.add('editing')">编辑</button>
            <form class="inline" method="post" onsubmit="return confirm('删除这条？')">
              <input type="hidden" name="action" value="del">
              <input type="hidden" name="idx" value="<?php echo $i; ?>">
              <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
              <button class="btn btn-danger btn-sm" type="submit">删除</button>
            </form>
          </div>
        </div>
        <div class="edit-form">
          <form method="post">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="idx" value="<?php echo $i; ?>">
            <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
            <label>更新日期</label>
            <input type="text" name="date" value="<?php echo e($item['date']); ?>">
            <label>更新内容</label>
            <textarea name="content"><?php echo e($item['content']); ?></textarea>
            <button class="btn btn-primary btn-sm" type="submit">保存</button>
            <button class="btn btn-ghost btn-sm" type="button" onclick="document.getElementById('item-<?php echo $i; ?>').classList.remove('editing')">取消</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <form method="post"><input type="hidden" name="action" value="logout"><button class="btn btn-ghost btn-sm" type="submit">退出登录</button></form>

  <div class="note">
    数据文件：<?php echo e(DATA_FILE); ?><br>
    修改后前台落地页刷新即自动展示，无需改代码。
  </div>
<?php endif; ?>

</div>
</body>
</html>
