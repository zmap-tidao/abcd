<?php

if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

$identifier = 'qqgroupnotify';
$plugin = DB::fetch_first(
    "SELECT pluginid FROM " . DB::table('common_plugin') . " WHERE identifier='" . addslashes($identifier) . "' LIMIT 1"
);

if (!empty($plugin['pluginid'])) {
    $pluginid = dintval($plugin['pluginid']);
    $vars = array(
        array(
            'displayorder' => 1,
            'title' => '开启通知',
            'description' => '1=开启，0=关闭',
            'variable' => 'enabled',
            'type' => 'radio',
            'value' => '1',
            'extra' => "1=开启\n0=关闭",
        ),
        array(
            'displayorder' => 2,
            'title' => '通知全部版块',
            'description' => '1=全部通知，0=仅通知指定版块',
            'variable' => 'notify_all',
            'type' => 'radio',
            'value' => '0',
            'extra' => "1=是\n0=否",
        ),
        array(
            'displayorder' => 3,
            'title' => '指定版块ID',
            'description' => '仅在“通知全部版块=否”时生效；多个 fid 用英文逗号分隔，例如：2,36,58',
            'variable' => 'forum_ids',
            'type' => 'text',
            'value' => '',
            'extra' => '',
        ),
        array(
            'displayorder' => 4,
            'title' => '推送模式',
            'description' => 'napcat=NapCat HTTP 接口，webhook=通用 Webhook',
            'variable' => 'push_mode',
            'type' => 'radio',
            'value' => 'napcat',
            'extra' => "napcat=NapCat HTTP\nwebhook=通用Webhook",
        ),
        array(
            'displayorder' => 5,
            'title' => 'NapCat API地址',
            'description' => '支持填基础地址（如 http://127.0.0.1:3000）或完整地址（.../send_group_msg）',
            'variable' => 'napcat_api_url',
            'type' => 'text',
            'value' => 'http://127.0.0.1:3000',
            'extra' => '',
        ),
        array(
            'displayorder' => 6,
            'title' => 'NapCat Access Token',
            'description' => '可留空；如 NapCat 开启鉴权则填写',
            'variable' => 'napcat_access_token',
            'type' => 'text',
            'value' => '',
            'extra' => '',
        ),
        array(
            'displayorder' => 7,
            'title' => 'NapCat 群号',
            'description' => '多个 group_id 用英文逗号分隔，例如：123456,234567',
            'variable' => 'napcat_group_ids',
            'type' => 'text',
            'value' => '',
            'extra' => '',
        ),
        array(
            'displayorder' => 8,
            'title' => 'Webhook 地址',
            'description' => '仅在推送模式=webhook时生效；可填写多个地址，每行一个（或英文逗号分隔）',
            'variable' => 'webhook_urls',
            'type' => 'textarea',
            'value' => '',
            'extra' => '',
        ),
        array(
            'displayorder' => 9,
            'title' => '消息模板',
            'description' => '支持占位符：{forum} {title} {author} {time} {url}',
            'variable' => 'message_template',
            'type' => 'textarea',
            'value' => "【论坛新帖通知】\n版块：{forum}\n标题：{title}\n作者：{author}\n时间：{time}\n链接：{url}",
            'extra' => '',
        ),
    );

    foreach ($vars as $var) {
        DB::insert('common_pluginvar', array(
            'pluginid' => $pluginid,
            'displayorder' => $var['displayorder'],
            'title' => $var['title'],
            'description' => $var['description'],
            'variable' => $var['variable'],
            'type' => $var['type'],
            'value' => $var['value'],
            'extra' => $var['extra'],
        ), false, true);
    }
}

$finish = true;
