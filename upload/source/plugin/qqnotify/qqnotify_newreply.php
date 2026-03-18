<?php
/**
 * QQ群通知插件 - 新回复钩子
 * Hook: newreply_postsave
 *
 * Discuz! X 在 source/module/forum/forum_post.php 中，回复保存成功后触发此钩子。
 * 可用变量：
 *   $tid          主题ID
 *   $pid          新回复帖子ID
 *   $post         回复数据数组
 *   $thread       主题数据数组（含 subject 等字段）
 *   $_G['forum']  当前板块信息（含 fid、name 等）
 *   $_G['username'] 回帖人昵称
 *   $_G['setting'] 全站配置（含插件配置项）
 */

if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

// 快速检查：未启用则直接退出
if (empty($_G['setting']['qqnotify_enable']) || empty($_G['setting']['qqnotify_newreply'])) {
    return;
}

require_once DISCUZ_ROOT . './source/plugin/qqnotify/qqnotify.class.php';

$_qqn = new QQNotify();

if (!$_qqn->isEnabled()) {
    return;
}

// 检查当前板块是否在监控范围内
if (!$_qqn->isFidAllowed($_G['forum']['fid'])) {
    return;
}

// 拼接回复帖直链（定位到具体楼层）
$_qqn_url = $_G['siteurl'] . 'redirect.php?goto=findpost&pid=' . $pid . '&ptid=' . $tid;

// 组装占位符变量
$_qqn_vars = array(
    'forumname' => $_G['forum']['name'],
    'subject'   => isset($thread['subject']) ? $thread['subject'] : '',
    'author'    => $_G['username'],
    'tid'       => $tid,
    'pid'       => $pid,
    'url'       => $_qqn_url,
    'sitename'  => $_G['setting']['sitename'],
);

$_qqn_msg = $_qqn->formatMessage($_G['setting']['qqnotify_reply_tpl'], $_qqn_vars);
$_qqn->sendToGroups($_qqn_msg);

// 清理局部变量，避免污染 Discuz! 全局作用域
unset($_qqn, $_qqn_url, $_qqn_vars, $_qqn_msg);
