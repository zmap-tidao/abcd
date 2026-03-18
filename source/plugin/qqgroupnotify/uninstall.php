<?php

if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

$identifier = 'qqgroupnotify';
$plugin = DB::fetch_first(
    "SELECT pluginid FROM " . DB::table('common_plugin') . " WHERE identifier='" . addslashes($identifier) . "' LIMIT 1"
);

if (!empty($plugin['pluginid'])) {
    DB::delete('common_pluginvar', "pluginid='" . dintval($plugin['pluginid']) . "'");
}

$finish = true;
