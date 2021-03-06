<?php


/**
 * 版块相关页面.
 *
 * @author Jianmin Chen <sky_hold@163.com>
 * @license http://www.phpwind.com
 *
 * @version $Id: ForumController.php 28803 2013-05-24 07:58:21Z jieyin $
 */
class ForumController extends PwBaseController
{
    public function run()
    {
        $order = $this->getInput('order', 'get');
        $page = intval($this->getInput('page', 'get'));

        $threadList = new PwThreadList();
        // $this->runHook('c_thread_run', $forumDisplay);
        $threadList->setPage($page)->setPerpage(Wekit::C('bbs', 'thread.perpage'));

        $forbidFids = Wekit::load('forum.srv.PwForumService')->getForbidVisitForum($this->loginUser);
        $dataSource = new PwNewThread($forbidFids);
        if ($order == 'postdate') {
            $dataSource->setOrderBy($order);
        } else {
            $dataSource->setOrderBy('lastpost');
        }
        $threadList->execute($dataSource);
        if ($threadList->total > 12000) {
            Wekit::load('forum.PwThreadIndex')->deleteOver($threadList->total - 10000);
        }
        $threaddb = $threadList->getList();
        $fids = [];
        foreach ($threaddb as $key => $value) {
            $fids[] = $value['fid'];
        }
        $forums = Wekit::load('forum.srv.PwForumService')->fetchForum($fids);

        if ($operateThread = $this->loginUser->getPermission('operate_thread', false, [])) {
            $operateThread = Pw::subArray($operateThread, ['delete']);
        }

        $this->setOutput($threaddb, 'threadList');
        $this->setOutput($forums, 'forums');
        $this->setOutput($threadList->icon, 'icon');
        $this->setOutput($threadList->uploadIcon, 'uploadIcon');
        $this->setOutput(26, 'numofthreadtitle');
        $this->setOutput($order, 'order');
        $this->setOutput($operateThread, 'operateThread');

        $this->setOutput($threadList->page, 'page');
        $this->setOutput($threadList->perpage, 'perpage');
        $this->setOutput($threadList->total, 'count');
        $this->setOutput($threadList->maxPage, 'totalpage');
        $this->setOutput($threadList->getUrlArgs(), 'urlargs');

        // seo设置

        $seoBo = PwSeoBo::getInstance();
        $lang = Wind::getComponent('i18n');
        $threadList->page <= 1 && $seoBo->setDefaultSeo($lang->getMessage('SEO:bbs.forum.run.title'), '', $lang->getMessage('SEO:bbs.forum.run.description'));
        $seoBo->init('bbs', 'new');
        $seoBo->set('{page}', $threadList->page);
        Wekit::setV('seo', $seoBo);
    }

    /**
     * 我的版块.
     */
    public function myAction()
    {
        if (! $this->loginUser->isExists()) {
            $this->forwardAction('u/login/run', ['backurl' => WindUrlHelper::createUrl('bbs/forum/my')]);
        }
        $order = $this->getInput('order', 'get');
        $page = intval($this->getInput('page', 'get'));

        $threadList = new PwThreadList();
        // $this->runHook('c_thread_run', $forumDisplay);
        $threadList->setPage($page)->setPerpage(Wekit::C('bbs', 'thread.perpage'));

        $dataSource = new PwMyForumThread($this->loginUser);
        if ($order == 'postdate') {
            $dataSource->setOrderBy($order);
        } else {
            $dataSource->setOrderBy('lastpost');
        }
        $threadList->execute($dataSource);
        $threaddb = $threadList->getList();
        $fids = [];
        foreach ($threaddb as $key => $value) {
            $fids[] = $value['fid'];
        }
        $forums = Wekit::load('forum.PwForum')->fetchForum($fids);

        $this->setOutput($threaddb, 'threadList');
        $this->setOutput($forums, 'forums');
        $this->setOutput($threadList->icon, 'icon');
        $this->setOutput($threadList->uploadIcon, 'uploadIcon');
        $this->setOutput($order, 'order');

        $this->setOutput($threadList->page, 'page');
        $this->setOutput($threadList->perpage, 'perpage');
        $this->setOutput($threadList->total, 'count');
        $this->setOutput($threadList->maxPage, 'totalpage');
        $this->setOutput($threadList->getUrlArgs(), 'urlargs');

        // seo设置

        $seoBo = PwSeoBo::getInstance();
        $lang = Wind::getComponent('i18n');
        $seoBo->setCustomSeo($lang->getMessage('SEO:bbs.forum.my.title'), '', '');
        Wekit::setV('seo', $seoBo);
    }

    /**
     * 版块列表 弹窗.
     */
    public function listAction()
    {
        $withMyforum = $this->getInput('withMyforum');
        $service = Wekit::load('forum.srv.PwForumService');
        $forums = $service->getForumList();
        $map = $service->getForumMap();
        $cate = [];
        $forum = [];
        foreach ($map[0] as $key => $value) {
            if (! $value['isshow']) {
                continue;
            }
            $array = $service->findOptionInMap($value['fid'], $map,
                ['sub' => '--', 'sub2' => '----']);
            $tmp = [];
            foreach ($array as $k => $v) {
                if ($forums[$k]['isshow'] && (! $forums[$k]['allow_post'] || $this->loginUser->inGroup(
                    explode(',', $forums[$k]['allow_post'])))) {
                    $tmp[] = [$k, strip_tags($v)];
                }
            }
            if ($tmp) {
                $cate[] = [$value['fid'], strip_tags($value['name'])];
                $forum[$value['fid']] = $tmp;
            }
        }
        if ($withMyforum && $this->loginUser->isExists()
            && ($joinForum = Wekit::load('forum.PwForumUser')->getFroumByUid($this->loginUser->uid))) {
            $tmp = [];
            foreach ($joinForum as $key => $value) {
                if (! $key) {
                    continue;
                }
                $tmp[] = [$key, strip_tags($forums[$key]['name'])];
            }
            array_unshift($cate, ['my', '我的版块']);
            $forum['my'] = $tmp;
        }
        $response = ['cate' => $cate, 'forum' => $forum];
        $this->setOutput($response, 'data');
        $this->showMessage('success');
    }

    /**
     * 加入版块.
     */
    public function joinAction()
    {
        $fid = $this->getInput('fid', 'post');
        if (! $fid) {
            $this->showError('operate.fail');
        }

        $forum = new PwForumBo($fid);
        if (! $forum->isForum()) {
            $this->showError('BBS:forum.exists.not');
        }
        if (! $this->loginUser->isExists()) {
            $this->showError('login.not');
        }
        if (Wekit::load('forum.PwForumUser')->get($this->loginUser->uid, $fid)) {
            $this->showError('BBS:forum.join.already');
        }
        Wekit::load('forum.PwForumUser')->join($this->loginUser->uid, $fid);
        $this->_addJoionForum($this->loginUser->info, $forum->foruminfo);
        $this->showMessage('success');
    }

    /**
     * 退出版块.
     */
    public function quitAction()
    {
        $fid = $this->getInput('fid', 'post');
        if (! $fid) {
            $this->showError('operate.fail');
        }

        $forum = new PwForumBo($fid);
        if (! $forum->isForum()) {
            $this->showError('BBS:forum.exists.not');
        }
        if (! $this->loginUser->isExists()) {
            $this->showError('login.not');
        }
        if (! Wekit::load('forum.PwForumUser')->get($this->loginUser->uid, $fid)) {
            $this->showError('BBS:forum.join.not');
        }
        Wekit::load('forum.PwForumUser')->quit($this->loginUser->uid, $fid);
        $this->_removeJoionForum($this->loginUser->info, $fid);
        $this->showMessage('success');
    }

    public function topictypeAction()
    {
        $fid = $this->getInput('fid');
        $topictypes = Wekit::load('forum.PwTopicType')->getTopicTypesByFid($fid, ! $this->loginUser->getPermission('operate_thread.type'));
        $data = [];
        foreach ($topictypes['topic_types'] as $key => $value) {
            $tmp = ['title' => strip_tags($value['name']), 'val' => $value['id']];
            if (isset($topictypes['sub_topic_types'][$value['id']])) {
                $sub = [];
                foreach ($topictypes['sub_topic_types'][$value['id']] as $k => $v) {
                    $sub[] = ['title' => strip_tags($v['name']), 'val' => $v['id']];
                }
                $tmp['items'] = $sub;
            }
            $data[] = $tmp;
        }
        $this->setOutput($data, 'data');
        $this->showMessage('success');
    }

    /**
     * 进入版块的密码
     */
    public function passwordAction()
    {
        $fid = $this->getInput('fid');
        $this->setOutput($fid, 'fid');
        $this->setLayout('TPL:common.layout_error');
    }

    /**
     * 验证版块密码
     */
    public function verifyAction()
    {
        $fid = $this->getInput('fid');
        $password = $this->getInput('password', 'post');

        $forum = new PwForumBo($fid);
        if (! $forum->isForum(true)) {
            $this->showError('BBS:forum.exists.not');
        }
        if (md5($password) != $forum->foruminfo['password']) {
            $this->showError('BBS:forum.password.error');
        }
        Pw::setCookie('fp_'.$fid, Pw::getPwdCode(md5($password)), 86400);
        $this->showMessage('success');
    }

    /**
     * 格式化数据  把字符串"1,版块1,2,版块2"格式化为数组.
     *
     * @param string $string
     *
     * @return array
     */
    public static function splitStringToArray($string)
    {
        $a = explode(',', $string);
        $l = count($a);
        $l % 2 == 1 && $l--;
        $r = [];
        for ($i = 0; $i < $l; $i += 2) {
            $r[$a[$i]] = $a[$i + 1];
        }

        return $r;
    }

    /**
     * 加入版块 - 更新我的版块缓存数据.
     *
     * @param array $userInfo
     * @param array $foruminfo
     *
     * @return bool
     */
    private function _addJoionForum($userInfo, $foruminfo)
    {
        // 更新用户data表信息
        $array = [];
        $userInfo['join_forum'] && $array = self::splitStringToArray($userInfo['join_forum']);
        $array = [$foruminfo['fid'] => $foruminfo['name']] + $array;
        count($array) > 20 && $array = array_slice($array, 0, 20, true);

        $this->_updateMyForumCache($userInfo['uid'], $array);

        return true;
    }

    /**
     * 推出版块 - 更新我的版块缓存数据.
     *
     * @param array $userInfo
     * @param int   $fid
     *
     * @return bool
     */
    private function _removeJoionForum($userInfo, $fid)
    {
        // 更新用户data表信息
        $userInfo['join_forum'] && $array = self::splitStringToArray($userInfo['join_forum']);
        unset($array[$fid]);

        $this->_updateMyForumCache($userInfo['uid'], $array);

        return true;
    }

    private function _updateMyForumCache($uid, $array)
    {
        $joinForums = Wekit::load('forum.srv.PwForumService')->getJoinForum($uid);
        $_tmpArray = [];
        foreach ($array as $k => $v) {
            if (! isset($joinForums[$k])) {
                continue;
            }
            $_tmpArray[$k] = strip_tags($joinForums[$k]);
        }

        $dm = new PwUserInfoDm($uid);
        $dm->setJoinForum(self::_formatJoinForum($_tmpArray));

        return $this->_getUserDs()->editUser($dm, PwUser::FETCH_DATA);
    }

    /**
     * 格式化我的版块缓存数据结构.
     *
     * @param array $array 格式化成"1,版块1,2,版块2"
     *
     * @return string
     */
    private static function _formatJoinForum($array)
    {
        if (! $array) {
            return false;
        }
        $user = '';
        foreach ($array as $fid => $name) {
            $myForum .= $fid.','.$name.',';
        }

        return rtrim($myForum, ',');
    }

    /**
     * @return PwUser
     */
    private function _getUserDs()
    {
        return Wekit::load('user.PwUser');
    }

    /**
     * @return PwForum
     */
    private function _getForumService()
    {
        return Wekit::load('forum.PwForum');
    }
}
