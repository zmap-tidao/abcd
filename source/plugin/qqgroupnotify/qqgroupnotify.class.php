<?php

if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

class plugin_qqgroupnotify
{
    protected static $alreadyNotified = false;
    protected $identifier = 'qqgroupnotify';

    public function post_newthread_succeed_message($param)
    {
        $this->notifyForNewThread();
    }

    public function global_showmessage($param)
    {
        if (self::$alreadyNotified) {
            return;
        }

        $messageKey = '';
        if (is_array($param) && isset($param['param'][0])) {
            $messageKey = $param['param'][0];
        } elseif (is_array($param) && isset($param[0])) {
            $messageKey = $param[0];
        }

        if ($messageKey === 'post_newthread_succeed') {
            $this->notifyForNewThread();
        }
    }

    protected function notifyForNewThread()
    {
        global $_G;

        if (self::$alreadyNotified) {
            return;
        }
        self::$alreadyNotified = true;

        $setting = isset($_G['cache']['plugin'][$this->identifier]) ? $_G['cache']['plugin'][$this->identifier] : array();
        if (empty($setting['enabled'])) {
            return;
        }

        $thread = $this->resolveCurrentThread();
        if (empty($thread['tid']) || empty($thread['fid'])) {
            return;
        }

        if (!$this->shouldNotifyForum((int) $thread['fid'], $setting)) {
            return;
        }

        $forum = DB::fetch_first(
            "SELECT fid, name FROM " . DB::table('forum_forum') . " WHERE fid='" . dintval($thread['fid']) . "' LIMIT 1"
        );

        $forumName = !empty($forum['name']) ? $forum['name'] : '未知版块';
        $subject = trim((string) $thread['subject']);
        if ($subject === '') {
            $subject = '(无标题)';
        }

        $author = trim((string) $thread['author']);
        if ($author === '') {
            $author = '匿名';
        }

        $siteUrl = rtrim((string) $_G['siteurl'], '/');
        $threadUrl = $siteUrl . '/forum.php?mod=viewthread&tid=' . intval($thread['tid']);
        $postTime = dgmdate((int) $thread['dateline'], 'Y-m-d H:i:s', 8 * 3600);

        $template = trim((string) $setting['message_template']);
        if ($template === '') {
            $template = "【论坛新帖通知】\n版块：{forum}\n标题：{title}\n作者：{author}\n时间：{time}\n链接：{url}";
        }

        $message = strtr($template, array(
            '{forum}' => $forumName,
            '{title}' => $subject,
            '{author}' => $author,
            '{time}' => $postTime,
            '{url}' => $threadUrl,
        ));

        $webhooks = $this->parseWebhookUrls((string) $setting['webhook_urls']);
        if (!$webhooks) {
            return;
        }

        foreach ($webhooks as $webhookUrl) {
            $this->sendWebhookMessage($webhookUrl, $message);
        }
    }

    protected function resolveCurrentThread()
    {
        $tid = isset($_GET['tid']) ? dintval($_GET['tid']) : 0;
        if (!$tid && isset($_POST['tid'])) {
            $tid = dintval($_POST['tid']);
        }

        $fid = isset($_GET['fid']) ? dintval($_GET['fid']) : 0;
        if (!$fid && isset($_POST['fid'])) {
            $fid = dintval($_POST['fid']);
        }

        if (!$tid) {
            // 兜底：极端情况下 URL 无 tid，按用户最近发帖回查一条线程。
            global $_G;
            $uid = isset($_G['uid']) ? dintval($_G['uid']) : 0;
            if ($uid) {
                $where = $fid ? " AND fid='" . $fid . "'" : '';
                $recent = DB::fetch_first(
                    "SELECT tid FROM " . DB::table('forum_thread') .
                    " WHERE authorid='" . $uid . "' " . $where .
                    " ORDER BY dateline DESC LIMIT 1"
                );
                $tid = !empty($recent['tid']) ? dintval($recent['tid']) : 0;
            }
        }

        if (!$tid) {
            return array();
        }

        $thread = DB::fetch_first(
            "SELECT tid, fid, subject, author, dateline, displayorder FROM " . DB::table('forum_thread') .
            " WHERE tid='" . $tid . "' LIMIT 1"
        );
        if (!$thread || (int) $thread['displayorder'] < 0) {
            return array();
        }

        return $thread;
    }

    protected function shouldNotifyForum($fid, $setting)
    {
        if (!empty($setting['notify_all'])) {
            return true;
        }

        $configuredFids = $this->parseForumIds(isset($setting['forum_ids']) ? $setting['forum_ids'] : '');
        if (!$configuredFids) {
            return false;
        }

        return in_array((int) $fid, $configuredFids, true);
    }

    protected function parseForumIds($forumIds)
    {
        $forumIds = str_replace(array("\r", "\n", '，', ';'), array('', ',', ',', ','), (string) $forumIds);
        $parts = explode(',', $forumIds);
        $result = array();

        foreach ($parts as $part) {
            $fid = intval(trim($part));
            if ($fid > 0) {
                $result[$fid] = $fid;
            }
        }

        return array_values($result);
    }

    protected function parseWebhookUrls($raw)
    {
        $raw = str_replace(array("\r\n", "\r"), "\n", (string) $raw);
        $raw = str_replace(',', "\n", $raw);
        $items = explode("\n", $raw);
        $urls = array();

        foreach ($items as $item) {
            $url = trim($item);
            if (!$url) {
                continue;
            }
            if (stripos($url, 'http://') !== 0 && stripos($url, 'https://') !== 0) {
                continue;
            }
            $urls[$url] = $url;
        }

        return array_values($urls);
    }

    protected function sendWebhookMessage($url, $message)
    {
        $payload = array(
            'msg_type' => 'text',
            'text' => array('content' => $message),
            'content' => $message,
            'message' => $message,
        );

        $body = json_encode($payload);
        if ($body === false) {
            return false;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8'));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            curl_exec($ch);
            curl_close($ch);
            return true;
        }

        $context = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-Type: application/json; charset=utf-8\r\n",
                'content' => $body,
                'timeout' => 6,
            ),
        ));
        @file_get_contents($url, false, $context);
        return true;
    }
}

class plugin_qqgroupnotify_forum extends plugin_qqgroupnotify
{
}
