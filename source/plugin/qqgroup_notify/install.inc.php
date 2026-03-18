<?php
/**
 * QQ群发帖通知插件 - 安装脚本
 */

if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

$sql = <<<EOF
CREATE TABLE IF NOT EXISTS %t (
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    tid int(10) unsigned NOT NULL DEFAULT '0',
    fid mediumint(8) unsigned NOT NULL DEFAULT '0',
    type varchar(20) NOT NULL DEFAULT '',
    subject varchar(255) NOT NULL DEFAULT '',
    author varchar(50) NOT NULL DEFAULT '',
    group_id bigint(20) unsigned NOT NULL DEFAULT '0',
    status tinyint(1) NOT NULL DEFAULT '0',
    result text,
    dateline int(10) unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (id),
    KEY idx_tid (tid),
    KEY idx_dateline (dateline),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
EOF;

$tableName = 'qqgroup_notify_log';

if (!DB::query("SHOW TABLES LIKE '%" . $tableName . "'", true)) {
    $sql = str_replace('%t', DB::table($tableName), $sql);
    foreach (explode(';', $sql) as $s) {
        $s = trim($s);
        if (!empty($s)) {
            DB::query($s);
        }
    }
}

$finish = true;
