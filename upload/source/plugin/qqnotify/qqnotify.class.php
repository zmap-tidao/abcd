<?php
/**
 * QQ群通知插件 - 核心类
 * 通过 OneBot HTTP API（go-cqhttp / Lagrange / LLOneBot 等）向QQ群发送通知。
 *
 * 支持的变量占位符（消息模板中使用）：
 *   {forumname} 板块名称
 *   {subject}   主题标题
 *   {author}    发帖人昵称
 *   {tid}       主题ID
 *   {pid}       帖子ID
 *   {url}       帖子直链
 *   {sitename}  站点名称
 */

if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

class QQNotify
{
    /** @var string OneBot HTTP API 根地址 */
    private $apiUrl;

    /** @var string 鉴权 Token（可为空） */
    private $token;

    /** @var array 目标QQ群号列表 */
    private $groups;

    /** @var array 允许触发通知的板块FID列表（空=全部） */
    private $allowedFids;

    /** @var bool 插件是否已启用 */
    private $enabled;

    public function __construct()
    {
        global $_G;

        $this->enabled     = !empty($_G['setting']['qqnotify_enable']);
        $this->apiUrl      = rtrim((string)$_G['setting']['qqnotify_api_url'], '/');
        $this->token       = (string)$_G['setting']['qqnotify_api_token'];
        $this->groups      = $this->parseList($_G['setting']['qqnotify_groups']);
        $this->allowedFids = $this->parseList($_G['setting']['qqnotify_fids']);
    }

    // -------------------------------------------------------------------------
    // 公开方法
    // -------------------------------------------------------------------------

    /**
     * 插件是否启用
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * 当前板块（fid）是否在监控范围内
     *
     * @param int|string $fid 板块ID
     * @return bool
     */
    public function isFidAllowed($fid)
    {
        if (empty($this->allowedFids)) {
            return true;
        }
        return in_array((string)$fid, $this->allowedFids, true);
    }

    /**
     * 将消息模板中的占位符替换为实际值
     *
     * @param string $tpl  消息模板
     * @param array  $vars 变量键值对
     * @return string
     */
    public function formatMessage($tpl, array $vars)
    {
        foreach ($vars as $key => $value) {
            $tpl = str_replace('{' . $key . '}', (string)$value, $tpl);
        }
        return $tpl;
    }

    /**
     * 向所有配置的QQ群发送消息
     *
     * @param string $message 消息文本
     */
    public function sendToGroups($message)
    {
        if (empty($this->groups) || empty($message)) {
            return;
        }

        foreach ($this->groups as $groupId) {
            $this->sendGroupMessage((int)$groupId, $message);
        }
    }

    // -------------------------------------------------------------------------
    // 私有辅助方法
    // -------------------------------------------------------------------------

    /**
     * 解析逗号分隔的字符串为数组，过滤空元素
     *
     * @param string $raw
     * @return array
     */
    private function parseList($raw)
    {
        if (empty($raw)) {
            return array();
        }
        return array_values(array_filter(array_map('trim', explode(',', (string)$raw))));
    }

    /**
     * 调用 OneBot /send_group_msg 接口向单个群发消息
     *
     * @param int    $groupId QQ群号
     * @param string $message 消息内容
     * @return string|false API 响应或 false
     */
    private function sendGroupMessage($groupId, $message)
    {
        if (empty($this->apiUrl)) {
            return false;
        }

        $url     = $this->apiUrl . '/send_group_msg';
        $payload = json_encode(array(
            'group_id' => $groupId,
            'message'  => $message,
        ));

        $headers = array('Content-Type: application/json');
        if (!empty($this->token)) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        if (function_exists('curl_init')) {
            return $this->curlPost($url, $payload, $headers);
        }

        return $this->fileGetPost($url, $payload, $headers);
    }

    /**
     * 使用 cURL 发送 HTTP POST 请求
     */
    private function curlPost($url, $payload, array $headers)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ));
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    /**
     * 使用 file_get_contents 回退方案（当 cURL 不可用时）
     */
    private function fileGetPost($url, $payload, array $headers)
    {
        $httpHeaders = implode("\r\n", $headers);
        $context = stream_context_create(array(
            'http' => array(
                'method'  => 'POST',
                'header'  => $httpHeaders,
                'content' => $payload,
                'timeout' => 5,
                'ignore_errors' => true,
            ),
            'ssl' => array(
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ),
        ));
        return @file_get_contents($url, false, $context);
    }
}
