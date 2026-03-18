<?php
/**
 * QQ群发帖通知插件 - 设置扩展
 * 此文件在后台插件设置页面加载时被执行
 */

if (!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) {
    exit('Access Denied');
}

/**
 * 测试发送功能
 */
if (!empty($_GET['pmod']) && $_GET['pmod'] === 'qqnotify_test') {
    $settings = qqgroup_notify_admin_get_settings();

    if (empty($settings['bot_api_url']) || empty($settings['qq_group_ids'])) {
        cpmsg('请先填写机器人API地址和QQ群号', 'action=plugins&operation=config&identifier=qqgroup_notify', 'error');
    }

    $testMessage = "🔔 QQ群通知插件测试\n━━━━━━━━━━\n✅ 恭喜！通知功能配置正确。\n⏰ 时间：" . date('Y-m-d H:i:s') . "\n📋 此消息由 Discuz 论坛 QQ群通知插件 发出。";

    $groupIds = array_map('trim', explode(',', $settings['qq_group_ids']));
    $allSuccess = true;

    foreach ($groupIds as $groupId) {
        $groupId = intval($groupId);
        if ($groupId <= 0) {
            continue;
        }

        $apiUrl = rtrim($settings['bot_api_url'], '/') . '/send_group_msg';
        $postData = json_encode(array(
            'group_id' => $groupId,
            'message'  => $testMessage,
        ), JSON_UNESCAPED_UNICODE);

        $headers = array('Content-Type: application/json');
        if (!empty($settings['bot_access_token'])) {
            $headers[] = 'Authorization: Bearer ' . $settings['bot_access_token'];
        }

        $result = qqgroup_notify_admin_http_post($apiUrl, $postData, $headers);
        if ($result === false) {
            $allSuccess = false;
        }
    }

    if ($allSuccess) {
        cpmsg('测试消息已发送，请检查QQ群是否收到消息。', 'action=plugins&operation=config&identifier=qqgroup_notify', 'succeed');
    } else {
        cpmsg('发送失败，请检查API地址和网络连接。', 'action=plugins&operation=config&identifier=qqgroup_notify', 'error');
    }
}

function qqgroup_notify_admin_get_settings()
{
    global $_G;

    $defaults = array(
        'enabled'          => 0,
        'bot_api_url'      => '',
        'bot_access_token' => '',
        'qq_group_ids'     => '',
        'notify_forums'    => 'all',
        'notify_template'  => '',
        'notify_reply'     => 0,
        'reply_template'   => '',
        'summary_length'   => 100,
        'site_url'         => '',
        'debug_mode'       => 0,
    );

    $settings = $defaults;
    if (!empty($_G['cache']['plugin']['qqgroup_notify'])) {
        $settings = array_merge($settings, $_G['cache']['plugin']['qqgroup_notify']);
    }

    return $settings;
}

function qqgroup_notify_admin_get_forums()
{
    $forums = array();
    $query = DB::query("SELECT fid, name, type FROM " . DB::table('forum_forum') . " WHERE type='forum' OR type='sub' ORDER BY displayorder, fid");
    while ($row = DB::fetch($query)) {
        $forums[] = $row;
    }
    return $forums;
}

function qqgroup_notify_admin_get_logs($limit = 20)
{
    $logs = array();
    $tableName = DB::table('qqgroup_notify_log');

    $tableExists = DB::result_first("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=database() AND table_name='" . DB::quote($tableName) . "'");
    if (!$tableExists) {
        return $logs;
    }

    $query = DB::query("SELECT * FROM {$tableName} ORDER BY id DESC LIMIT " . intval($limit));
    while ($row = DB::fetch($query)) {
        $logs[] = $row;
    }
    return $logs;
}

function qqgroup_notify_admin_http_post($url, $postData, $headers = array())
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
