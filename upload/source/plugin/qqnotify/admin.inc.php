<?php
/**
 * QQ群通知插件 - 后台管理设置页
 *
 * 通过 Discuz! X 后台插件管理入口访问：
 *   admin.php?action=plugins&operation=config&do=edit&identifier=qqnotify
 *
 * 支持的 OneBot 实现（需提前启动并开放 HTTP API）：
 *   - go-cqhttp   https://docs.go-cqhttp.org/
 *   - Lagrange    https://github.com/LagrangeDev/Lagrange.Core
 *   - LLOneBot    https://github.com/LLOneBot/LLOneBot
 *   - NapCatQQ   https://github.com/NapNeko/NapCatQQ
 */

if (!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) {
    exit('Access Denied');
}

// ============================================================
// 处理表单提交
// ============================================================
if (submitcheck('qqnotify_submit')) {

    $newConfig = array(
        'qqnotify_enable'     => intval($_GET['qqnotify_enable']),
        'qqnotify_api_url'    => trim(dhtmlspecialchars($_GET['qqnotify_api_url'])),
        'qqnotify_api_token'  => trim(dhtmlspecialchars($_GET['qqnotify_api_token'])),
        'qqnotify_groups'     => trim(dhtmlspecialchars($_GET['qqnotify_groups'])),
        'qqnotify_fids'       => trim(dhtmlspecialchars($_GET['qqnotify_fids'])),
        'qqnotify_newthread'  => intval($_GET['qqnotify_newthread']),
        'qqnotify_newreply'   => intval($_GET['qqnotify_newreply']),
        'qqnotify_thread_tpl' => trim(dhtmlspecialchars($_GET['qqnotify_thread_tpl'])),
        'qqnotify_reply_tpl'  => trim(dhtmlspecialchars($_GET['qqnotify_reply_tpl'])),
    );

    // 基础校验
    if (!empty($newConfig['qqnotify_api_url']) && !preg_match('#^https?://#i', $newConfig['qqnotify_api_url'])) {
        cpmsg('API地址格式不正确，请以 http:// 或 https:// 开头', '', 'error');
    }

    // 将配置写入 pre_setting 表（通过 Discuz! 标准 API）
    foreach ($newConfig as $key => $value) {
        C::t('common_setting')->update($key, $value);
    }

    // 更新内存缓存
    foreach ($newConfig as $key => $value) {
        $_G['setting'][$key] = $value;
    }

    cpmsg('设置保存成功！', 'action=plugins&operation=config&do=edit&identifier=qqnotify', 'succeed');
}

// ============================================================
// 读取当前配置（数据库值 > plugin.xml 默认值）
// ============================================================
$cfg = array(
    'enable'     => isset($_G['setting']['qqnotify_enable'])    ? intval($_G['setting']['qqnotify_enable'])    : 0,
    'api_url'    => isset($_G['setting']['qqnotify_api_url'])   ? $_G['setting']['qqnotify_api_url']           : 'http://127.0.0.1:5700',
    'api_token'  => isset($_G['setting']['qqnotify_api_token']) ? $_G['setting']['qqnotify_api_token']         : '',
    'groups'     => isset($_G['setting']['qqnotify_groups'])    ? $_G['setting']['qqnotify_groups']            : '',
    'fids'       => isset($_G['setting']['qqnotify_fids'])      ? $_G['setting']['qqnotify_fids']              : '',
    'newthread'  => isset($_G['setting']['qqnotify_newthread']) ? intval($_G['setting']['qqnotify_newthread']) : 1,
    'newreply'   => isset($_G['setting']['qqnotify_newreply'])  ? intval($_G['setting']['qqnotify_newreply'])  : 0,
    'thread_tpl' => isset($_G['setting']['qqnotify_thread_tpl']) ? $_G['setting']['qqnotify_thread_tpl']      : "【新帖】{forumname}\n标题：{subject}\n作者：{author}\n链接：{url}",
    'reply_tpl'  => isset($_G['setting']['qqnotify_reply_tpl'])  ? $_G['setting']['qqnotify_reply_tpl']       : "【回帖】{forumname}\n主题：{subject}\n作者：{author}\n链接：{url}",
);

// 查询所有版块供参考（仅取 fid 和 name，最多 200 个）
$forumList = array();
foreach (C::t('forum_forum')->fetch_all_by_type(array('forum', 'group'), 0, 200) as $f) {
    $forumList[] = $f;
}

// ============================================================
// 输出设置表单
// ============================================================

// 转义配置值供 HTML 输出
function _qqn_esc($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

?>
<style>
.qqn-wrap { font-family: "Microsoft YaHei", sans-serif; font-size: 13px; color: #333; }
.qqn-wrap table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
.qqn-wrap th { background: #f5f5f5; padding: 8px 12px; text-align: left; border: 1px solid #ddd; width: 200px; }
.qqn-wrap td { padding: 8px 12px; border: 1px solid #ddd; }
.qqn-wrap .tip { color: #888; font-size: 12px; margin-top: 4px; }
.qqn-wrap h3 { border-left: 4px solid #1e90ff; padding-left: 8px; color: #1e90ff; }
.qqn-wrap textarea { width: 98%; height: 80px; font-size: 13px; }
.qqn-wrap input[type=text] { width: 98%; }
.qqn-wrap .tpl-vars { font-size: 12px; color: #666; background: #f9f9f9; border: 1px solid #eee; padding: 6px 10px; border-radius: 4px; margin-top: 4px; }
.qqn-wrap .forum-ref { max-height: 200px; overflow-y: auto; border: 1px solid #eee; padding: 6px; font-size: 12px; color: #555; background: #fafafa; margin-top: 4px; }
</style>

<div class="qqn-wrap">
<h3>QQ群通知插件设置</h3>

<form method="post" action="admin.php?action=plugins&operation=config&do=edit&identifier=qqnotify">
<input type="hidden" name="formhash" value="<?php echo FORMHASH; ?>">
<input type="hidden" name="qqnotify_submit" value="yes">

<!-- ======== 基础设置 ======== -->
<h3>基础设置</h3>
<table>
  <tr>
    <th>启用插件</th>
    <td>
      <label><input type="radio" name="qqnotify_enable" value="1" <?php echo $cfg['enable'] ? 'checked' : ''; ?>> 启用</label>
      &nbsp;&nbsp;
      <label><input type="radio" name="qqnotify_enable" value="0" <?php echo !$cfg['enable'] ? 'checked' : ''; ?>> 禁用</label>
    </td>
  </tr>
  <tr>
    <th>通知新发主题</th>
    <td>
      <label><input type="radio" name="qqnotify_newthread" value="1" <?php echo $cfg['newthread'] ? 'checked' : ''; ?>> 是</label>
      &nbsp;&nbsp;
      <label><input type="radio" name="qqnotify_newthread" value="0" <?php echo !$cfg['newthread'] ? 'checked' : ''; ?>> 否</label>
    </td>
  </tr>
  <tr>
    <th>通知新回复</th>
    <td>
      <label><input type="radio" name="qqnotify_newreply" value="1" <?php echo $cfg['newreply'] ? 'checked' : ''; ?>> 是</label>
      &nbsp;&nbsp;
      <label><input type="radio" name="qqnotify_newreply" value="0" <?php echo !$cfg['newreply'] ? 'checked' : ''; ?>> 否</label>
      <div class="tip">回复通知量较大，建议仅对重要板块开启。</div>
    </td>
  </tr>
</table>

<!-- ======== OneBot API 设置 ======== -->
<h3>OneBot HTTP API 设置</h3>
<table>
  <tr>
    <th>API 地址</th>
    <td>
      <input type="text" name="qqnotify_api_url" value="<?php echo _qqn_esc($cfg['api_url']); ?>">
      <div class="tip">OneBot 实现的 HTTP API 监听地址，例如 <code>http://127.0.0.1:5700</code>。<br>
        支持：go-cqhttp、Lagrange、LLOneBot、NapCatQQ 等，需提前在服务器上部署并启动。</div>
    </td>
  </tr>
  <tr>
    <th>Access Token</th>
    <td>
      <input type="text" name="qqnotify_api_token" value="<?php echo _qqn_esc($cfg['api_token']); ?>">
      <div class="tip">若 OneBot 配置了 <code>access_token</code>，在此填写；未设置则留空。</div>
    </td>
  </tr>
  <tr>
    <th>目标QQ群号</th>
    <td>
      <input type="text" name="qqnotify_groups" value="<?php echo _qqn_esc($cfg['groups']); ?>">
      <div class="tip">填写要接收通知的QQ群号，多个群号用 <strong>英文逗号</strong> 分隔，例如：<code>123456789,987654321</code><br>
        <strong>注意：</strong>QQ Bot 账号必须已在目标群组中并且具有发言权限。</div>
    </td>
  </tr>
</table>

<!-- ======== 板块过滤 ======== -->
<h3>板块过滤</h3>
<table>
  <tr>
    <th>监控板块（FID）</th>
    <td>
      <input type="text" name="qqnotify_fids" value="<?php echo _qqn_esc($cfg['fids']); ?>">
      <div class="tip"><strong>留空 = 监控全部板块。</strong>指定板块时填写 FID，多个用英文逗号分隔，例如：<code>2,5,8</code></div>
      <?php if (!empty($forumList)): ?>
      <div class="forum-ref">
        <strong>当前版块参考（FID → 名称）：</strong><br>
        <?php foreach ($forumList as $f): ?>
          <span style="display:inline-block;margin:2px 8px 2px 0;">
            <strong><?php echo intval($f['fid']); ?></strong>: <?php echo htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8'); ?>
          </span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </td>
  </tr>
</table>

<!-- ======== 消息模板 ======== -->
<h3>消息模板</h3>
<div class="tpl-vars">
  <strong>可用占位符：</strong>
  <code>{forumname}</code> 板块名称 &nbsp;
  <code>{subject}</code> 主题标题 &nbsp;
  <code>{author}</code> 发帖人 &nbsp;
  <code>{tid}</code> 主题ID &nbsp;
  <code>{pid}</code> 帖子ID &nbsp;
  <code>{url}</code> 帖子链接 &nbsp;
  <code>{sitename}</code> 站点名称
</div>
<table>
  <tr>
    <th>新帖消息模板</th>
    <td>
      <textarea name="qqnotify_thread_tpl"><?php echo _qqn_esc($cfg['thread_tpl']); ?></textarea>
      <div class="tip">每行一段内容，换行符 \n 在发送时会自动转为真实换行。</div>
    </td>
  </tr>
  <tr>
    <th>新回复消息模板</th>
    <td>
      <textarea name="qqnotify_reply_tpl"><?php echo _qqn_esc($cfg['reply_tpl']); ?></textarea>
    </td>
  </tr>
</table>

<div style="text-align:center;margin:20px 0;">
  <input type="submit" value="保存设置" style="padding:8px 30px;font-size:14px;cursor:pointer;background:#1e90ff;color:#fff;border:none;border-radius:4px;">
</div>

</form>

<!-- 简易连通性测试区 -->
<h3>连通性测试</h3>
<table>
  <tr>
    <th>测试发送</th>
    <td>
      <button onclick="qqnTest()" style="padding:6px 20px;cursor:pointer;">发送测试消息到QQ群</button>
      <span id="qqn-test-result" style="margin-left:10px;font-size:12px;"></span>
      <div class="tip">点击后将通过 AJAX 向配置的群发送一条测试消息，用于验证 OneBot API 是否可达。请先保存设置再测试。</div>
    </td>
  </tr>
</table>

<script>
function qqnTest() {
    var el = document.getElementById('qqn-test-result');
    el.style.color = '#888';
    el.innerText = '发送中...';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'admin.php?action=plugins&operation=config&do=edit&identifier=qqnotify&qqntest=1&formhash=<?php echo FORMHASH; ?>', true);
    xhr.onload = function() {
        try {
            var r = JSON.parse(xhr.responseText);
            if (r.success) {
                el.style.color = 'green';
                el.innerText = '✓ 测试消息已发送，请检查QQ群。';
            } else {
                el.style.color = 'red';
                el.innerText = '✗ 发送失败：' + (r.error || '未知错误');
            }
        } catch(e) {
            el.style.color = 'red';
            el.innerText = '✗ 响应解析失败，请检查控制台。';
        }
    };
    xhr.onerror = function() {
        el.style.color = 'red';
        el.innerText = '✗ 请求失败，请检查网络。';
    };
    xhr.send();
}

// 处理测试请求（同页面 AJAX 入口）
<?php
if (!empty($_GET['qqntest']) && $_GET['formhash'] === FORMHASH) {
    // 不渲染 HTML，直接输出 JSON 然后终止
    header('Content-Type: application/json; charset=utf-8');

    require_once DISCUZ_ROOT . './source/plugin/qqnotify/qqnotify.class.php';
    $notifier = new QQNotify();

    if (!$notifier->isEnabled()) {
        echo json_encode(array('success' => false, 'error' => '插件未启用'));
    } elseif (empty($_G['setting']['qqnotify_groups'])) {
        echo json_encode(array('success' => false, 'error' => '未配置任何QQ群号'));
    } else {
        $testMsg = '【QQ群通知测试】来自论坛 ' . $_G['setting']['sitename'] . ' 的连通性测试消息，时间：' . date('Y-m-d H:i:s');
        $notifier->sendToGroups($testMsg);
        echo json_encode(array('success' => true));
    }
    exit;
}
?>
</script>

</div>
