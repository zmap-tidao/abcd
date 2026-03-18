<?php
/**
 * QQ群发帖通知插件 - 卸载脚本
 */

if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

DB::query("DROP TABLE IF EXISTS " . DB::table('qqgroup_notify_log'));

$finish = true;
