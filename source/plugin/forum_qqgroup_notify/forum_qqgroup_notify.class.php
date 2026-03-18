<?php

if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

class plugin_forum_qqgroup_notify
{
    protected $identifier = 'forum_qqgroup_notify';

    protected function getConfig()
    {
        global $_G;

        static $config = null;
        if ($config === null) {
            $config = isset($_G['cache']['plugin'][$this->identifier]) ? $_G['cache']['plugin'][$this->identifier] : array();
        }

        return $config;
    }

    protected function notifyThread($fid, $tid)
    {
        static $sent = array();

        $tid = intval($tid);
        $fid = intval($fid);
        if ($tid <= 0 || isset($sent[$tid])) {
            return;
        }

        $config = $this->getConfig();
        if (empty($config['enabled']) || empty($config['api_url'])) {
            return;
        }

        $groupIds = $this->parseIdList(isset($config['group_ids']) ? $config['group_ids'] : '');
        if (empty($groupIds)) {
            return;
        }

        $thread = DB::fetch_first(
            "SELECT tid, fid, subject, author, authorid, dateline, posttableid FROM " . DB::table('forum_thread') . " WHERE tid='" . $tid . "'"
        );
        if (empty($thread)) {
            return;
        }

        if ($fid <= 0) {
            $fid = intval($thread['fid']);
        }

        if (!$this->shouldNotifyForum($fid, $config)) {
            return;
        }

        $forum = DB::fetch_first(
            "SELECT fid, name FROM " . DB::table('forum_forum') . " WHERE fid='" . intval($fid) . "'"
        );

        $postTable = DB::table('forum_post');
        if (function_exists('getposttable') && isset($thread['posttableid'])) {
            $postTable = DB::table(getposttable(intval($thread['posttableid'])));
        }

        $post = DB::fetch_first(
            "SELECT message FROM " . $postTable . " WHERE tid='" . $tid . "' AND first='1' ORDER BY pid ASC LIMIT 1"
        );

        $payload = array(
            'fid' => intval($fid),
            'forum_name' => !empty($forum['name']) ? $forum['name'] : ('FID ' . intval($fid)),
            'tid' => $tid,
            'subject' => $this->cleanText($thread['subject']),
            'author' => $this->cleanText($thread['author']),
            'authorid' => intval($thread['authorid']),
            'dateline' => intval($thread['dateline']),
            'publish_time' => intval($thread['dateline']) > 0 ? date('Y-m-d H:i:s', intval($thread['dateline'])) : '',
            'excerpt' => $this->buildExcerpt(isset($post['message']) ? $post['message'] : '', intval(isset($config['excerpt_length']) ? $config['excerpt_length'] : 80)),
            'thread_url' => $this->buildThreadUrl($tid),
        );

        $sent[$tid] = true;
        $message = $this->buildMessage($payload, $config);
        $this->dispatchMessage($message, $groupIds, $payload, $config);
    }

    protected function shouldNotifyForum($fid, $config)
    {
        $scope = isset($config['forum_scope']) ? trim($config['forum_scope']) : 'all';
        if ($scope === 'all') {
            return true;
        }

        $forumIds = $this->parseIdList(isset($config['forum_ids']) ? $config['forum_ids'] : '');
        if (empty($forumIds)) {
            return false;
        }

        return in_array(intval($fid), $forumIds, true);
    }

    protected function buildMessage($payload, $config)
    {
        $template = trim(isset($config['message_template']) ? $config['message_template'] : '');
        if ($template === '') {
            $template = "【论坛新帖通知】\n板块：{forum_name}\n标题：{subject}\n作者：{author}\n摘要：{excerpt}\n时间：{publish_time}\n链接：{thread_url}";
        }

        $replacements = array(
            '{fid}' => strval($payload['fid']),
            '{forum_name}' => $payload['forum_name'],
            '{tid}' => strval($payload['tid']),
            '{subject}' => $payload['subject'],
            '{author}' => $payload['author'],
            '{authorid}' => strval($payload['authorid']),
            '{publish_time}' => $payload['publish_time'],
            '{excerpt}' => $payload['excerpt'],
            '{thread_url}' => $payload['thread_url'],
        );

        return strtr($template, $replacements);
    }

    protected function dispatchMessage($message, $groupIds, $payload, $config)
    {
        $apiType = isset($config['api_type']) ? trim($config['api_type']) : 'cqhttp';

        foreach ($groupIds as $groupId) {
            if ($apiType === 'webhook') {
                $this->sendToWebhook($groupId, $message, $payload, $config);
            } else {
                $this->sendToCqhttp($groupId, $message, $config);
            }
        }
    }

    protected function sendToCqhttp($groupId, $message, $config)
    {
        $endpoint = rtrim(trim($config['api_url']), '/');
        if (!preg_match('#/send_group_msg(?:_async)?$#', $endpoint)) {
            $endpoint .= '/send_group_msg';
        }

        $headers = array();
        if (!empty($config['api_token'])) {
            $headers[] = 'Authorization: Bearer ' . trim($config['api_token']);
        }

        $payload = array(
            'group_id' => strval($groupId),
            'message' => $message,
            'auto_escape' => false,
        );

        $this->requestJson($endpoint, $payload, $config, $headers);
    }

    protected function sendToWebhook($groupId, $message, $payload, $config)
    {
        $body = array(
            'event' => 'new_thread',
            'group_id' => strval($groupId),
            'forum_id' => intval($payload['fid']),
            'forum_name' => $payload['forum_name'],
            'thread_id' => intval($payload['tid']),
            'subject' => $payload['subject'],
            'author' => $payload['author'],
            'author_id' => intval($payload['authorid']),
            'publish_time' => $payload['publish_time'],
            'excerpt' => $payload['excerpt'],
            'thread_url' => $payload['thread_url'],
            'message' => $message,
        );

        $headers = array();
        if (!empty($config['api_token'])) {
            $headers[] = 'Authorization: Bearer ' . trim($config['api_token']);
        }

        $this->requestJson(trim($config['api_url']), $body, $config, $headers);
    }

    protected function requestJson($url, $payload, $config, $extraHeaders)
    {
        $timeout = max(1, intval(isset($config['timeout']) ? $config['timeout'] : 5));
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $headers = array_merge(
            array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json),
            ),
            $extraHeaders
        );

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_exec($ch);
            curl_close($ch);

            return;
        }

        $headerString = implode("\r\n", $headers);
        $context = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => $headerString,
                'content' => $json,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ),
        ));

        @file_get_contents($url, false, $context);
    }

    protected function buildThreadUrl($tid)
    {
        global $_G;

        $siteUrl = !empty($_G['siteurl']) ? rtrim($_G['siteurl'], '/') : '';
        return $siteUrl . '/forum.php?mod=viewthread&tid=' . intval($tid);
    }

    protected function parseIdList($value)
    {
        $items = preg_split('/[\s,，]+/', strval($value));
        $ids = array();
        foreach ($items as $item) {
            $item = trim($item);
            if ($item === '' || !preg_match('/^\d+$/', $item)) {
                continue;
            }
            $ids[] = intval($item);
        }

        return array_values(array_unique($ids));
    }

    protected function buildExcerpt($message, $length)
    {
        $length = max(0, intval($length));
        if ($length === 0) {
            return '';
        }

        $message = $this->cleanText($message);
        $message = preg_replace('/\[(\/)?[a-z0-9_\*\#\=\.\,\:\-\s]+\]/i', '', $message);
        $message = preg_replace('/\s+/u', ' ', $message);
        $message = trim($message);

        if ($message === '') {
            return '';
        }

        if (function_exists('dstrlen') && function_exists('cutstr')) {
            return dstrlen($message) > $length ? cutstr($message, $length, '') . '...' : $message;
        }

        return strlen($message) > $length ? substr($message, 0, $length) . '...' : $message;
    }

    protected function cleanText($value)
    {
        $charset = defined('CHARSET') ? CHARSET : 'UTF-8';
        $value = html_entity_decode(strip_tags(strval($value)), ENT_QUOTES, $charset);
        $value = str_replace(array("\r\n", "\r", "\n"), ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim($value);
    }
}

class plugin_forum_qqgroup_notify_forum extends plugin_forum_qqgroup_notify
{
    protected function notifyFromGlobals()
    {
        global $_G;

        $fid = 0;
        $tid = 0;

        if (!empty($_G['fid'])) {
            $fid = intval($_G['fid']);
        } elseif (isset($_GET['fid'])) {
            $fid = intval($_GET['fid']);
        }

        if (!empty($_G['tid'])) {
            $tid = intval($_G['tid']);
        } elseif (isset($_GET['tid'])) {
            $tid = intval($_GET['tid']);
        }

        $this->notifyThread($fid, $tid);
    }

    public function post_newthread_succeed()
    {
        $this->notifyFromGlobals();
    }

    public function post_newthread_mod_succeed()
    {
        $this->notifyFromGlobals();
    }

    public function newthread_submit_end($fid, $tid)
    {
        $this->notifyThread($fid, $tid);
    }
}
