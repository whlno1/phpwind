<?php
/**
 * 用户头像公共服务
 *
 * @author Jianmin Chen <sky_hold@163.com>
 * @license http://www.phpwind.com
 *
 * @version $Id: WindidAvatarApi.php 32085 2014-08-20 08:48:50Z gao.wanggao $
 */
class WindidAvatarApi
{
    public function getAvatarUrl()
    {
        return WindidApi::open('avatar/getAvatarUrl', []);
    }

    public function getStorages()
    {
        return WindidApi::open('avatar/getStorages', []);
    }

    public function setStorages($storage)
    {
        return WindidApi::open('avatar/setStorages', [], ['storage' => $storage]);
    }

    /**
     * 获取用户头像.
     *
     * @param $uid
     * @param $size big middle small
     *
     * @return string
     */
    public function getAvatar($uid, $size = 'middle')
    {
        return $this->_getService()->getAvatar($uid, $size);
    }

    /**
     * 还原头像.
     *
     * @param int    $uid
     * @param string $type 还原类型-一种默认头像face*,一种是禁止头像ban*
     *
     * @return bool
     */
    public function defaultAvatar($uid, $type = 'face')
    {
        $params = [
            'uid'  => $uid,
            'type' => $type,
        ];

        return WindidApi::open('avatar/default', [], $params);
    }

    /**
     * 获取头像上传代码
     *
     * @param int $uid     用户uid
     * @param int $getHtml 获取代码|配置
     *
     * @return string|array
     */
    public function showFlash($uid, $getHtml = 1)
    {
        return $this->_getService()->showFlash($uid, WINDID_CLIENT_ID, WINDID_CLIENT_KEY, $getHtml);
    }

    public function doAvatar($uid, $file = '')
    {
        $time = Pw::getTime();
        $query = [
            'm'         => 'api',
            'c'         => 'avatar',
            'a'         => 'doavatar',
            'windidkey' => WindidUtility::appKey(WINDID_CLIENT_ID, $time, WINDID_CLIENT_KEY, ['uid' => $uid, 'm' => 'api', 'c' => 'avatar', 'a' => 'doavatar'], []),
            'clientid'  => WINDID_CLIENT_ID,
            'time'      => $time,
            'uid'       => $uid,
        ];
        $url = WINDID_SERVER_URL.'/index.php?'.http_build_query($query);

        $client = new \GuzzleHttp\Client();
        $response = $client->request('POST', $url, [
            'multipart' => [
                'name'     => 'FileData',
                'contents' => fopen($file, 'r'),
            ],
        ]);
        $result = $response->getBody();

        if ($result === false) {
            return WindidError::SERVER_ERROR;
        }

        return Pw::jsonDecode($result);
    }

    protected function _getService()
    {
        return Wekit::load('WSRV:user.srv.WindidUserService');
    }
}
