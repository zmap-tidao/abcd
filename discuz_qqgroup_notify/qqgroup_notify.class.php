<?php
/**
 * QQ群发帖通知插件 - 主类
 * 论坛发帖时自动推送通知到QQ群
 */

if(!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

class plugin_qqgroup_notify {

    /**
     * 发送消息到QQ群
     * @param string $message 要发送的消息内容
     * @return bool 是否发送成功
     */
    public static function send_to_qqgroup($message) {
        global $_G;
        
        $webhook_key = $_G['setting']['qqgroup_webhook_key'];
        if(empty($webhook_key)) {
            return false;
        }

        $url = 'https://app.qun.qq.com/cgi-bin/api/hookrobot_send?key=' . urlencode($webhook_key);
        
        $data = array(
            'content' => array(
                array('type' => 0, 'data' => $message)
            )
        );
        
        $context = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode($data),
                'timeout' => 5
            )
        ));
        
        $result = @file_get_contents($url, false, $context);
        return $result !== false;
    }

    /**
     * 检查板块是否在通知范围内
     * @param int $fid 板块ID
     * @return bool
     */
    public static function should_notify_forum($fid) {
        global $_G;
        
        $forums = trim($_G['setting']['qqgroup_notify_forums']);
        if(empty($forums)) {
            return true; // 留空表示全部板块
        }
        
        $forum_ids = array_map('intval', array_filter(explode(',', $forums)));
        return in_array($fid, $forum_ids);
    }

    /**
     * 格式化并发送新主题通知
     * @param array $thread 主题数据
     * @param array $post 帖子数据
     */
    public static function notify_new_thread($thread, $post) {
        global $_G;
        
        if(!$_G['setting']['qqgroup_notify_newthread']) {
            return;
        }
        
        if(!self::should_notify_forum($thread['fid'])) {
            return;
        }
        
        $forum = C::t('forum_forum')->fetch($thread['fid']);
        $forum_name = $forum ? $forum['name'] : '未知板块';
        
        $subject = dhtmlspecialchars($thread['subject']);
        $author = $thread['author'];
        $message = isset($post['message']) ? $post['message'] : '';
        $message = strip_tags($message);
        $message = preg_replace('/\s+/', ' ', $message);
        $message = mb_substr($message, 0, 100, 'utf-8');
        if(mb_strlen(isset($post['message']) ? $post['message'] : '', 'utf-8') > 100) {
            $message .= '...';
        }
        
        $siteurl = $_G['siteurl'];
        $tid = $thread['tid'];
        $thread_url = $siteurl . 'forum.php?mod=viewthread&tid=' . $tid;
        
        $notify_msg = "【新主题】\n";
        $notify_msg .= "板块：{$forum_name}\n";
        $notify_msg .= "标题：{$subject}\n";
        $notify_msg .= "作者：{$author}\n";
        $notify_msg .= "内容：{$message}\n";
        $notify_msg .= "链接：{$thread_url}";
        
        self::send_to_qqgroup($notify_msg);
    }

    /**
     * 格式化并发送回复通知
     * @param array $thread 主题数据
     * @param array $post 帖子数据
     */
    public static function notify_reply($thread, $post) {
        global $_G;
        
        if(!$_G['setting']['qqgroup_notify_reply']) {
            return;
        }
        
        if(!self::should_notify_forum($thread['fid'])) {
            return;
        }
        
        $forum = C::t('forum_forum')->fetch($thread['fid']);
        $forum_name = $forum ? $forum['name'] : '未知板块';
        
        $subject = dhtmlspecialchars($thread['subject']);
        $author = isset($post['author']) ? $post['author'] : '';
        $message = isset($post['message']) ? $post['message'] : '';
        $message = strip_tags($message);
        $message = preg_replace('/\s+/', ' ', $message);
        $message = mb_substr($message, 0, 80, 'utf-8');
        if(mb_strlen(isset($post['message']) ? $post['message'] : '', 'utf-8') > 80) {
            $message .= '...';
        }
        
        $siteurl = $_G['siteurl'];
        $tid = $thread['tid'];
        $pid = $post['pid'];
        $post_url = $siteurl . 'forum.php?mod=viewthread&tid=' . $tid . '&pid=' . $pid . '#pid' . $pid;
        
        $notify_msg = "【新回复】\n";
        $notify_msg .= "板块：{$forum_name}\n";
        $notify_msg .= "主题：{$subject}\n";
        $notify_msg .= "回复者：{$author}\n";
        $notify_msg .= "内容：{$message}\n";
        $notify_msg .= "链接：{$post_url}";
        
        self::send_to_qqgroup($notify_msg);
    }
}

/**
 * 论坛发帖模块钩子
 * 当 Discuz 调用 hookscript('forum_post_newthread_succeed') 或 hookscript('forum_post_reply_succeed') 时触发
 */
class plugin_qqgroup_notify_forum extends plugin_qqgroup_notify {

    /**
     * 新主题发帖成功钩子
     * 需在 source/module/forum/forum_post.php 中添加: hookscript('forum_post_newthread_succeed', array('tid'=>$tid, 'pid'=>$pid, 'fid'=>$fid, 'thread'=>$thread, 'post'=>$post));
     */
    public function forum_post_newthread_succeed($param) {
        if(!empty($param['thread']) && !empty($param['post'])) {
            parent::notify_new_thread($param['thread'], $param['post']);
        } elseif(!empty($param['tid']) && !empty($param['pid'])) {
            $thread = C::t('forum_thread')->fetch($param['tid']);
            $post = $this->_fetch_post($param['tid'], $param['pid']);
            if($thread && $post) {
                parent::notify_new_thread($thread, $post);
            }
        }
    }

    /**
     * 回复发帖成功钩子
     * 需在 source/module/forum/forum_post.php 中添加: hookscript('forum_post_reply_succeed', array('tid'=>$tid, 'pid'=>$pid, 'fid'=>$fid, 'thread'=>$thread, 'post'=>$post));
     */
    public function forum_post_reply_succeed($param) {
        if(!empty($param['thread']) && !empty($param['post'])) {
            parent::notify_reply($param['thread'], $param['post']);
        } elseif(!empty($param['tid']) && !empty($param['pid'])) {
            $thread = C::t('forum_thread')->fetch($param['tid']);
            $post = $this->_fetch_post($param['tid'], $param['pid']);
            if($thread && $post) {
                parent::notify_reply($thread, $post);
            }
        }
    }

    /**
     * 根据 tid 和 pid 获取帖子内容（兼容分表）
     */
    private function _fetch_post($tid, $pid) {
        $thread = C::t('forum_thread')->fetch($tid);
        if(!$thread) return null;
        $tableid = isset($thread['posttableid']) ? $thread['posttableid'] : 0;
        $posttable = 'forum_post';
        if($tableid) {
            $posttable = 'forum_post_' . $tableid;
        }
        return DB::fetch_first("SELECT * FROM " . DB::table($posttable) . " WHERE tid='".intval($tid)."' AND pid='".intval($pid)."'");
    }
}
