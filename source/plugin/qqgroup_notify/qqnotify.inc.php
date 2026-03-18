<?php
/**
 * QQ群发帖通知 - 独立通知工具
 *
 * 本文件提供独立的通知函数，可被 Discuz 的钩子系统直接调用。
 * 当 hookscript class 方式无法自动触发时，可在 Discuz 的 post_newthread.inc.php
 * 或 post_reply.inc.php 末尾手动 include 此文件作为补充方案。
 *
 * 用法（在 source/include/post/post_newthread.inc.php 末尾追加）：
 *   @include DISCUZ_ROOT.'source/plugin/qqgroup_notify/qqnotify.inc.php';
 */

if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

qqgroup_notify_dispatch();

function qqgroup_notify_dispatch()
{
    global $_G;

    $settings = qqgroup_notify_get_settings();
    if (!$settings['enabled']) {
        return;
    }

    if (!empty($_G['setting']['pluginhooks']['qqgroup_notify_processed']['type'])) {
        return;
    }

    $tid = 0;
    $pid = 0;
    $fid = 0;
    $action = '';

    if (isset($GLOBALS['tid']) && intval($GLOBALS['tid']) > 0) {
        $tid = intval($GLOBALS['tid']);
    } elseif (!empty($_G['tid'])) {
        $tid = intval($_G['tid']);
    }

    if ($tid <= 0) {
        return;
    }

    if (isset($GLOBALS['fid'])) {
        $fid = intval($GLOBALS['fid']);
    } elseif (!empty($_G['fid'])) {
        $fid = intval($_G['fid']);
    }

    if (isset($GLOBALS['pid'])) {
        $pid = intval($GLOBALS['pid']);
    }

    $isNewThread = !empty($GLOBALS['isthread']) || (!empty($_GET['action']) && $_GET['action'] === 'newthread');
    $isReply = !empty($GLOBALS['isreply']) || (!empty($_GET['action']) && $_GET['action'] === 'reply');

    $scriptBasename = basename($_SERVER['SCRIPT_FILENAME'], '.php');
    if (!$isNewThread && !$isReply) {
        $currentFile = basename(__FILE__);
        $includer = '';
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        foreach ($bt as $frame) {
            if (!empty($frame['file']) && basename($frame['file']) !== $currentFile) {
                $includer = basename($frame['file']);
                break;
            }
        }
        if (strpos($includer, 'post_newthread') !== false) {
            $isNewThread = true;
        } elseif (strpos($includer, 'post_reply') !== false || strpos($includer, 'post_newreply') !== false) {
            $isReply = true;
        }
    }

    if (!$isNewThread && !$isReply) {
        return;
    }

    if ($isReply && !$settings['notify_reply']) {
        return;
    }

    $notifyForums = trim($settings['notify_forums']);
    if (!empty($notifyForums) && strtolower($notifyForums) !== 'all') {
        $forumIds = array_map('intval', array_map('trim', explode(',', $notifyForums)));
        if (!in_array(intval($fid), $forumIds)) {
            return;
        }
    }

    $threadData = DB::fetch_first("SELECT subject, author FROM " . DB::table('forum_thread') . " WHERE tid='" . intval($tid) . "'");
    if (empty($threadData)) {
        return;
    }

    $forumName = '';
    if (!empty($_G['forum']['name'])) {
        $forumName = $_G['forum']['name'];
    } else {
        $forumData = DB::fetch_first("SELECT name FROM " . DB::table('forum_forum') . " WHERE fid='" . intval($fid) . "'");
        $forumName = !empty($forumData['name']) ? $forumData['name'] : '';
    }

    $messageContent = '';
    if ($isNewThread) {
        $postData = DB::fetch_first("SELECT message FROM " . DB::table('forum_post') . " WHERE tid='" . intval($tid) . "' AND first=1 ORDER BY dateline ASC LIMIT 1");
        $messageContent = !empty($postData['message']) ? $postData['message'] : '';
        $action = 'newthread';
    } else {
        if ($pid > 0) {
            $postData = DB::fetch_first("SELECT author, message FROM " . DB::table('forum_post') . " WHERE pid='" . intval($pid) . "'");
        } else {
            $postData = DB::fetch_first("SELECT author, message FROM " . DB::table('forum_post') . " WHERE tid='" . intval($tid) . "' ORDER BY dateline DESC LIMIT 1");
        }
        $messageContent = !empty($postData['message']) ? $postData['message'] : '';
        $action = 'reply';
        if (!empty($postData['author'])) {
            $threadData['author'] = $postData['author'];
        }
    }

    $data = array(
        'type'       => $action,
        'tid'        => $tid,
        'pid'        => $pid,
        'fid'        => $fid,
        'subject'    => $threadData['subject'],
        'author'     => $action === 'reply' && !empty($postData['author']) ? $postData['author'] : $threadData['author'],
        'message'    => $messageContent,
        'forum_name' => $forumName,
    );

    qqgroup_notify_send($data, $settings);
}

function qqgroup_notify_send($data, $settings = null)
{
    if ($settings === null) {
        $settings = qqgroup_notify_get_settings();
    }

    if (!$settings['enabled'] || empty($settings['bot_api_url']) || empty($settings['qq_group_ids'])) {
        return false;
    }

    $message = qqgroup_notify_build_message($data, $settings);
    $groupIds = array_map('trim', explode(',', $settings['qq_group_ids']));
    $success = true;

    foreach ($groupIds as $groupId) {
        $groupId = intval($groupId);
        if ($groupId <= 0) {
            continue;
        }

        $apiUrl = rtrim($settings['bot_api_url'], '/') . '/send_group_msg';
        $postData = json_encode(array(
            'group_id' => $groupId,
            'message'  => $message,
        ), JSON_UNESCAPED_UNICODE);

        $headers = array('Content-Type: application/json');
        if (!empty($settings['bot_access_token'])) {
            $headers[] = 'Authorization: Bearer ' . $settings['bot_access_token'];
        }

        $result = qqgroup_notify_http_post($apiUrl, $postData, $headers);

        if ($settings['debug_mode']) {
            qqgroup_notify_log("Notify group {$groupId}, type: {$data['type']}, tid: {$data['tid']}");
            qqgroup_notify_log("Message: " . $message);
            qqgroup_notify_log("Response: " . ($result ?: 'empty'));
        }

        qqgroup_notify_save_log($data, $groupId, $result);

        if ($result === false) {
            $success = false;
        }
    }

    return $success;
}

function qqgroup_notify_build_message($data, $settings)
{
    $template = ($data['type'] === 'reply' && !empty($settings['reply_template']))
        ? $settings['reply_template']
        : $settings['notify_template'];

    if (empty($template)) {
        $template = "📢 新帖通知\n标题：{subject}\n作者：{author}\n板块：{forum}\n链接：{url}";
    }

    $summary = strip_tags($data['message']);
    $summary = preg_replace('/\[.*?\]/', '', $summary);
    $summary = preg_replace('/\s+/', ' ', $summary);
    $summary = trim($summary);
    $maxLen = intval($settings['summary_length']) ?: 100;
    if (mb_strlen($summary, 'UTF-8') > $maxLen) {
        $summary = mb_substr($summary, 0, $maxLen, 'UTF-8') . '...';
    }

    global $_G;
    $siteUrl = rtrim($settings['site_url'], '/');
    if (empty($siteUrl)) {
        $siteUrl = rtrim($_G['siteurl'], '/');
    }

    $url = $siteUrl . '/forum.php?mod=viewthread&tid=' . intval($data['tid']);
    if ($data['type'] === 'reply' && !empty($data['pid'])) {
        $url .= '&pid=' . intval($data['pid']) . '#pid' . intval($data['pid']);
    }

    $replacements = array(
        '{subject}' => $data['subject'],
        '{author}'  => $data['author'],
        '{forum}'   => $data['forum_name'],
        '{url}'     => $url,
        '{summary}' => $summary,
    );

    return str_replace(array_keys($replacements), array_values($replacements), $template);
}

function qqgroup_notify_get_settings()
{
    global $_G;

    $defaults = array(
        'enabled'          => 0,
        'bot_api_url'      => '',
        'bot_access_token' => '',
        'qq_group_ids'     => '',
        'notify_forums'    => 'all',
        'notify_template'  => "📢 论坛新帖通知\n━━━━━━━━━━\n📌 标题：{subject}\n👤 作者：{author}\n📂 板块：{forum}\n🔗 链接：{url}\n📝 摘要：{summary}",
        'notify_reply'     => 0,
        'reply_template'   => "💬 论坛新回帖通知\n━━━━━━━━━━\n📌 主题：{subject}\n👤 回帖：{author}\n📂 板块：{forum}\n🔗 链接：{url}\n📝 摘要：{summary}",
        'summary_length'   => 100,
        'site_url'         => '',
        'debug_mode'       => 0,
    );

    $settings = $defaults;

    if (!empty($_G['cache']['plugin']['qqgroup_notify'])) {
        $settings = array_merge($settings, $_G['cache']['plugin']['qqgroup_notify']);
    } elseif (!empty($_G['setting']['plugins']['qqgroup_notify'])) {
        $settings = array_merge($settings, $_G['setting']['plugins']['qqgroup_notify']);
    }

    return $settings;
}

function qqgroup_notify_http_post($url, $postData, $headers = array())
{
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            qqgroup_notify_log("cURL error: " . $error);
            return false;
        }
        return $response;
    }

    $headerStr = implode("\r\n", $headers);
    $context = stream_context_create(array(
        'http' => array(
            'method'  => 'POST',
            'header'  => $headerStr,
            'content' => $postData,
            'timeout' => 10,
        ),
    ));
    return @file_get_contents($url, false, $context);
}

function qqgroup_notify_save_log($data, $groupId, $result)
{
    $logData = array(
        'tid'      => intval($data['tid']),
        'fid'      => intval($data['fid']),
        'type'     => $data['type'],
        'subject'  => $data['subject'],
        'author'   => $data['author'],
        'group_id' => intval($groupId),
        'status'   => ($result !== false) ? 1 : 0,
        'result'   => is_string($result) ? mb_substr($result, 0, 500, 'UTF-8') : '',
        'dateline' => time(),
    );

    try {
        DB::query("INSERT INTO " . DB::table('qqgroup_notify_log') . " (tid, fid, type, subject, author, group_id, status, result, dateline) VALUES ('" .
            DB::quote($logData['tid']) . "', '" .
            DB::quote($logData['fid']) . "', '" .
            DB::quote($logData['type']) . "', '" .
            DB::quote($logData['subject']) . "', '" .
            DB::quote($logData['author']) . "', '" .
            DB::quote($logData['group_id']) . "', '" .
            DB::quote($logData['status']) . "', '" .
            DB::quote($logData['result']) . "', '" .
            DB::quote($logData['dateline']) . "')"
        );
    } catch (Exception $e) {
        qqgroup_notify_log("Save log failed: " . $e->getMessage());
    }
}

function qqgroup_notify_log($message)
{
    $logDir = DISCUZ_ROOT . 'data/log/';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    $logFile = $logDir . 'qqnotify_' . date('Ymd') . '.log';
    $logLine = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}
