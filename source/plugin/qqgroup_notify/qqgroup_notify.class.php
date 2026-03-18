<?php
/**
 * QQ群发帖通知插件 - 钩子类
 * 兼容 go-cqhttp / NapCat / LLOneBot / Lagrange 等 OneBot v11 协议
 */

if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

class plugin_qqgroup_notify
{
    /**
     * 全局钩子 - 在所有模块执行完毕后调用
     * 用于捕获发帖/回帖事件
     */
    function common()
    {
    }

    function global_footer()
    {
        global $_G;

        if (empty($_G['setting']['pluginhooks']['qqgroup_notify_processed'])) {
            return;
        }

        $data = $_G['setting']['pluginhooks']['qqgroup_notify_processed'];
        if (!empty($data['type'])) {
            $this->_sendNotification($data);
        }
    }

    /**
     * 向 QQ 群发送通知
     */
    protected function _sendNotification($data)
    {
        $settings = $this->_getSettings();
        if (!$settings['enabled'] || empty($settings['bot_api_url']) || empty($settings['qq_group_ids'])) {
            return;
        }

        $groupIds = array_map('trim', explode(',', $settings['qq_group_ids']));
        $message = $this->_buildMessage($data, $settings);

        foreach ($groupIds as $groupId) {
            $groupId = intval($groupId);
            if ($groupId <= 0) {
                continue;
            }
            $this->_sendToGroup($settings, $groupId, $message);
        }
    }

    /**
     * 构造通知消息
     */
    protected function _buildMessage($data, $settings)
    {
        $template = ($data['type'] === 'reply' && !empty($settings['reply_template']))
            ? $settings['reply_template']
            : $settings['notify_template'];

        $summary = strip_tags($data['message']);
        $summary = preg_replace('/\s+/', ' ', $summary);
        $maxLen = intval($settings['summary_length']) ?: 100;
        if (mb_strlen($summary, 'UTF-8') > $maxLen) {
            $summary = mb_substr($summary, 0, $maxLen, 'UTF-8') . '...';
        }

        $siteUrl = rtrim($settings['site_url'], '/');
        if (empty($siteUrl)) {
            global $_G;
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

    /**
     * 通过 OneBot HTTP API 发送群消息
     */
    protected function _sendToGroup($settings, $groupId, $message)
    {
        $apiUrl = rtrim($settings['bot_api_url'], '/') . '/send_group_msg';

        $postData = json_encode(array(
            'group_id' => $groupId,
            'message'  => $message,
        ), JSON_UNESCAPED_UNICODE);

        $headers = array(
            'Content-Type: application/json',
        );
        if (!empty($settings['bot_access_token'])) {
            $headers[] = 'Authorization: Bearer ' . $settings['bot_access_token'];
        }

        $result = $this->_httpPost($apiUrl, $postData, $headers);

        if ($settings['debug_mode']) {
            $this->_log("Send to group {$groupId}: " . $message);
            $this->_log("API response: " . $result);
        }
    }

    /**
     * HTTP POST 请求
     */
    protected function _httpPost($url, $postData, $headers = array())
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
                $this->_log("cURL error: " . $error);
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

    /**
     * 获取插件设置
     */
    protected function _getSettings()
    {
        global $_G;

        static $settings = null;
        if ($settings !== null) {
            return $settings;
        }

        $settings = array(
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

        if (!empty($_G['cache']['plugin']['qqgroup_notify'])) {
            $settings = array_merge($settings, $_G['cache']['plugin']['qqgroup_notify']);
        } elseif (!empty($_G['setting']['plugins']['qqgroup_notify'])) {
            $settings = array_merge($settings, $_G['setting']['plugins']['qqgroup_notify']);
        }

        return $settings;
    }

    /**
     * 检查板块是否需要通知
     */
    protected function _shouldNotifyForum($fid)
    {
        $settings = $this->_getSettings();
        $notifyForums = trim($settings['notify_forums']);

        if (empty($notifyForums) || strtolower($notifyForums) === 'all') {
            return true;
        }

        $forumIds = array_map('intval', array_map('trim', explode(',', $notifyForums)));
        return in_array(intval($fid), $forumIds);
    }

    /**
     * 写入调试日志
     */
    protected function _log($message)
    {
        $logDir = DISCUZ_ROOT . 'data/log/';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . 'qqnotify_' . date('Ymd') . '.log';
        $logLine = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
        @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Forum 脚本嵌入点类
 * 当访问 forum.php 时被调用
 */
class plugin_qqgroup_notify_forum extends plugin_qqgroup_notify
{
    /**
     * post 模块嵌入点 - 在发帖/回帖操作时调用
     */
    function post_output()
    {
        $this->_processPost();
    }

    /**
     * 处理发帖/回帖事件
     */
    protected function _processPost()
    {
        global $_G;

        $settings = $this->_getSettings();
        if (!$settings['enabled']) {
            return;
        }

        $action = !empty($_GET['action']) ? $_GET['action'] : '';
        $isMobile = defined('IN_MOBILE') && IN_MOBILE;

        if ($action === 'newthread' || $action === 'reply') {
            $this->_checkAndNotify($action);
        }
    }

    /**
     * 检查并发送通知
     */
    protected function _checkAndNotify($action)
    {
        global $_G;

        $settings = $this->_getSettings();

        if ($action === 'reply' && !$settings['notify_reply']) {
            return;
        }

        $tid = !empty($_G['tid']) ? intval($_G['tid']) : (isset($GLOBALS['tid']) ? intval($GLOBALS['tid']) : 0);
        $fid = !empty($_G['fid']) ? intval($_G['fid']) : (isset($GLOBALS['fid']) ? intval($GLOBALS['fid']) : 0);

        if ($tid <= 0) {
            return;
        }

        if (!$this->_shouldNotifyForum($fid)) {
            return;
        }

        $threadInfo = $this->_getThreadInfo($tid);
        if (empty($threadInfo)) {
            return;
        }

        $forumName = $this->_getForumName($fid);

        if ($action === 'newthread') {
            $data = array(
                'type'       => 'newthread',
                'tid'        => $tid,
                'fid'        => $fid,
                'subject'    => $threadInfo['subject'],
                'author'     => $threadInfo['author'],
                'message'    => $threadInfo['message'],
                'forum_name' => $forumName,
            );
        } else {
            $pid = !empty($_G['pid']) ? intval($_G['pid']) : (isset($GLOBALS['pid']) ? intval($GLOBALS['pid']) : 0);
            $replyInfo = $this->_getReplyInfo($tid, $pid);
            $data = array(
                'type'       => 'reply',
                'tid'        => $tid,
                'pid'        => $pid,
                'fid'        => $fid,
                'subject'    => $threadInfo['subject'],
                'author'     => !empty($replyInfo['author']) ? $replyInfo['author'] : $_G['member']['username'],
                'message'    => !empty($replyInfo['message']) ? $replyInfo['message'] : '',
                'forum_name' => $forumName,
            );
        }

        $_G['setting']['pluginhooks']['qqgroup_notify_processed'] = $data;
        $this->_sendNotification($data);
    }

    /**
     * 获取帖子信息
     */
    protected function _getThreadInfo($tid)
    {
        if (class_exists('C') && method_exists('C', 't')) {
            try {
                $thread = C::t('forum_thread')->fetch($tid);
                if ($thread) {
                    $post = C::t('forum_post')->fetch_threadpost_by_tid_invisible($tid);
                    $firstPost = is_array($post) ? reset($post) : $post;
                    return array(
                        'subject' => $thread['subject'],
                        'author'  => $thread['author'],
                        'message' => !empty($firstPost['message']) ? $firstPost['message'] : '',
                    );
                }
            } catch (Exception $e) {
                $this->_log("Error fetching thread info: " . $e->getMessage());
            }
        }

        $tid = intval($tid);
        $threadData = DB::fetch_first("SELECT subject, author FROM " . DB::table('forum_thread') . " WHERE tid='{$tid}'");
        if ($threadData) {
            $postData = DB::fetch_first("SELECT message FROM " . DB::table('forum_post') . " WHERE tid='{$tid}' AND first=1 ORDER BY dateline ASC LIMIT 1");
            return array(
                'subject' => $threadData['subject'],
                'author'  => $threadData['author'],
                'message' => !empty($postData['message']) ? $postData['message'] : '',
            );
        }

        return null;
    }

    /**
     * 获取回帖信息
     */
    protected function _getReplyInfo($tid, $pid)
    {
        if ($pid > 0) {
            if (class_exists('C') && method_exists('C', 't')) {
                try {
                    $post = C::t('forum_post')->fetch('tid:' . $tid, $pid);
                    if ($post) {
                        return array(
                            'author'  => $post['author'],
                            'message' => $post['message'],
                        );
                    }
                } catch (Exception $e) {
                    $this->_log("Error fetching reply info: " . $e->getMessage());
                }
            }

            $pid = intval($pid);
            $postData = DB::fetch_first("SELECT author, message FROM " . DB::table('forum_post') . " WHERE pid='{$pid}'");
            if ($postData) {
                return array(
                    'author'  => $postData['author'],
                    'message' => $postData['message'],
                );
            }
        }

        return null;
    }

    /**
     * 获取板块名称
     */
    protected function _getForumName($fid)
    {
        global $_G;

        if (!empty($_G['forum']['name'])) {
            return $_G['forum']['name'];
        }

        if (class_exists('C') && method_exists('C', 't')) {
            try {
                $forum = C::t('forum_forum')->fetch($fid);
                if ($forum) {
                    return $forum['name'];
                }
            } catch (Exception $e) {
                $this->_log("Error fetching forum name: " . $e->getMessage());
            }
        }

        $fid = intval($fid);
        $forumData = DB::fetch_first("SELECT name FROM " . DB::table('forum_forum') . " WHERE fid='{$fid}'");
        return !empty($forumData['name']) ? $forumData['name'] : '未知板块';
    }
}
