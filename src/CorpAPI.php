<?php

namespace shophy\wxwork;

use shophy\wxwork\common\Utils;
use shophy\wxwork\common\HttpUtils;
use shophy\wxwork\exception\SysException;
use shophy\wxwork\exception\QyApiException;
use shophy\wxwork\exception\ParameterException;

use shophy\wxwork\structs\User;
use shophy\wxwork\structs\Department;
use shophy\wxwork\structs\Tag;
use shophy\wxwork\structs\Batch;
use shophy\wxwork\structs\BatchJobArgs;
use shophy\wxwork\structs\Agent;
use shophy\wxwork\structs\Menu;
use shophy\wxwork\structs\Message;
use shophy\wxwork\structs\UserInfoByCode;
use shophy\wxwork\structs\UserDetailByUserTicket;
use shophy\wxwork\structs\CheckinOption;
use shophy\wxwork\structs\CheckinDataList;
use shophy\wxwork\structs\ApprovalDataList;
use shophy\wxwork\structs\SendWorkWxRedpackReq;
use shophy\wxwork\structs\QueryWorkWxRedpackReq;
use shophy\wxwork\structs\PayWwSptrans2PocketReq;
use shophy\wxwork\structs\QueryWwSptrans2PocketReq;
use shophy\wxwork\structs\BatchUpdateInvoiceStatusReq;
use shophy\wxwork\structs\BatchGetInvoiceInfoReq;
use shophy\wxwork\structs\Student;
use shophy\wxwork\structs\Parents;
use shophy\wxwork\structs\SchoolDepartment;
use shophy\wxwork\structs\ExternalContact;
use shophy\wxwork\structs\CodeSession;

class CorpAPI extends API
{
    protected $corpId = null;
    protected $secret = null;
    protected $accessToken = null;

    /**
     * @brief __construct : 构造函数，
     * @note 企业进行自定义开发调用, 请传参 corpid + secret, 不用关心accesstoken，本类会自动获取并刷新
     */
    public function __construct($corpId=null, $secret=null)
    {
        Utils::checkNotEmptyStr($corpId, "corpid");
        Utils::checkNotEmptyStr($secret, "secret");

        $this->corpId = $corpId;
        $this->secret = $secret;
    }


    // ------------------------- access token ---------------------------------
    /**
     * @brief GetAccessToken : 获取 accesstoken，不用主动调用
     *
     * @return : string accessToken
     */
    public function GetAccessToken()
    {
        if ( ! Utils::notEmptyStr($this->accessToken)) { 
            $this->RefreshAccessToken();
        } 
        return $this->accessToken;
    }

    protected function RefreshAccessToken($bflush=false)
    {
        if (!Utils::notEmptyStr($this->corpId) || !Utils::notEmptyStr($this->secret))
            throw new ParameterException("invalid corpid or secret");

        // 尝试从缓存读取,corpid 作为key
        $this->accessToken = $bflush ? '' : ''/*\Yii::$app->cache->get($this->corpId.'-'.$this->secret)*/;
        if( ! Utils::notEmptyStr($this->accessToken)) {
            $url = HttpUtils::MakeUrl(
                "/cgi-bin/gettoken?corpid={$this->corpId}&corpsecret={$this->secret}");
            $this->_HttpGetParseToJson($url, false);
            $this->_CheckErrCode();
    
            // 写入缓存
            $this->accessToken = $this->rspJson["access_token"];
            //\Yii::$app->cache->set($this->corpId.'-'.$this->secret, $this->accessToken, $this->rspJson['expires_in']);
        }
    }

    // ------------------------- 成员管理 -------------------------------------
    //
    /**
     * @brief UserCreate : 创建成员
     *
     * @link https://work.weixin.qq.com/api/doc#10018
     *
     * @param $user : User
     */
    public function UserCreate(User $user)
    {
        User::CheckUserCreateArgs($user);
        $args = Utils::Object2Array($user);
        self::_HttpCall(self::USER_CREATE, 'POST', $args);
    }

    /**
     * @brief UserGet : 读取成员
     *
     * @link https://work.weixin.qq.com/api/doc#10019
     *
     * @param $userid : string
     *
     * @return : User
     */
    public function UserGet($userid)
    {
        Utils::checkNotEmptyStr($userid, "userid"); 
        self::_HttpCall(self::USER_GET, 'GET', array('userid' => $userid)); 
        return User::Array2User($this->rspJson);
    }

    /**
     * @brief UserUpdate : 更新成员
     *
     * @link https://work.weixin.qq.com/api/doc#10020
     *
     * @param $user : User
     */
    public function UserUpdate(User $user)
    {
        User::CheckUserUpdateArgs($user);
        $args = Utils::Object2Array($user);
        self::_HttpCall(self::USER_UPDATE, 'POST', $args);
    }

    /**
     * @brief UserDelete : 删除成员
     * 
     * @link https://work.weixin.qq.com/api/doc#10030
     *
     * @param $userid : string
     */
    public function UserDelete($userid)
    {
        Utils::checkNotEmptyStr($userid, "userid");
        self::_HttpCall(self::USER_DELETE, 'GET', array('userid' => $userid)); 
    }

    /**
     * @brief UserBatchDelete : 批量删除成员
     *
     * @link https://work.weixin.qq.com/api/doc#10060
     *
     * @param $userIdList : string array
     *
     */
    public function UserBatchDelete(array $userIdList)
    {
        User::CheckUserBatchDeleteArgs($userIdList);
        $args = array("useridlist" => $userIdList);
        self::_HttpCall(self::USER_BATCH_DELETE, 'POST', $args);
    }

    /**
     * @brief UserSimpleList : 获取部门成员
     *
     * @link https://work.weixin.qq.com/api/doc#10061
     *
     * @param $departmentId : uint
     * @param $fetchChild : 1/0 是否递归获取子部门下面的成员
     *
     * @return : User array
     */
    public function UserSimpleList($department_id, $fetchChild)
    {
        Utils::checkIsUInt($department_id, "department_id"); 
        self::_HttpCall(self::USER_SIMPLE_LIST, 'GET', array('department_id'=>$department_id, 'fetch_child'=>$fetchChild)); 
        return User::Array2UserList($this->rspJson);
    }

    /**
     * @brief UserList : 获取部门成员详情
     *
     * @link https://work.weixin.qq.com/api/doc#10063
     *
     * @param $departmentId : uint
     * @param $fetchChild : 1/0 是否递归获取子部门下面的成员
     *
     * @return 
     */
    public function UserList($departmentId, $fetchChild)
    {
        Utils::checkIsUInt($departmentId, "departmentId");
        self::_HttpCall(self::USER_LIST, 'GET', array('department_id'=>$departmentId, 'fetch_child'=>$fetchChild));
        return User::Array2UserList($this->rspJson);
    }

    /**
     * @brief UserId2OpenId : userid转openid
     *
     * @link https://work.weixin.qq.com/api/doc#11279
     *
     * @param $userid : input string
     * @param $openId : output string
     * @param $agentid : intput string
     * @param $appId : output string
     */
    public function UserId2OpenId($userid, &$openId, $agentid = null, &$appId = null)
    {
        Utils::checkNotEmptyStr($userid, "userid"); 
        if (is_null($agentid)) { 
            $args = array("userid" => $userid);
        } else { 
            $args = array("userid" => $userid, "agentid" => $agentid);
        } 
        self::_HttpCall(self::USERID_TO_OPENID, 'POST', $args); 
        $openId = Utils::arrayGet($this->rspJson, "openid");
        $appId = Utils::arrayGet($this->rspJson, "appid");
    }

    /**
     * @brief openId2UserId : openid转userid
     *
     * @link https://work.weixin.qq.com/api/doc#11279
     *
     * @param $openId : intput string
     * @param $userid : output string
     */
    public function openId2UserId($openId, &$userid)
    {
        Utils::checkNotEmptyStr($openId, "openid"); 
        $args = array("openid" => $openId);
        self::_HttpCall(self::OPENID_TO_USERID, 'POST', $args); 
        $userid= Utils::arrayGet($this->rspJson, "userid");
    }

    /**
     * @brief UserAuthSuccess : 二次验证
     *
     * @link https://work.weixin.qq.com/api/doc#11378
     *
     * @param $userid : string
     */
    public function UserAuthSuccess($userid)
    {
        Utils::checkNotEmptyStr($userid, "userid");
        self::_HttpCall(self::USER_AUTH_SUCCESS, 'GET', array('userid'=>$userid));
    }

    // ------------------------- 部门管理 -------------------------------------
    //
    /**
     * @brief DepartmentCreate : 创建部门
     *
     * @link https://work.weixin.qq.com/api/doc#10076
     *
     * @param $department : Department
     *
     * @return  : uint departmentId
     */
    public function DepartmentCreate(Department $department)
    {
        Department::CheckDepartmentCreateArgs($department); 
        $args = Department::Department2Array($department);
        self::_HttpCall(self::DEPARTMENT_CREATE, 'POST', $args); 
        return Utils::arrayGet($this->rspJson, "id");
    }

    /**
     * @brief DepartmentUpdate : 更新部门
     *
     * @link https://work.weixin.qq.com/api/doc#10077
     *
     * @param $department : Department
     */
    public function DepartmentUpdate(Department $department)
    {
        Department::CheckDepartmentUpdateArgs($department); 
        $args = Department::Department2Array($department);
        self::_HttpCall(self::DEPARTMENT_UPDATE, 'POST', $args); 
    }

    /**
     * @brief DepartmentDelete : 删除部门
     *
     * @link https://work.weixin.qq.com/api/doc#10079
     *
     * @param $departmentId : uint
     */
    public function DepartmentDelete($departmentId)
    {
        Utils::checkIsUInt($departmentId, "departmentId"); 
        self::_HttpCall(self::DEPARTMENT_DELETE, 'GET', array('id'=>$departmentId));
    }

    /**
     * @brief DepartmentList : 获取部门列表
     *
     * @link https://work.weixin.qq.com/api/doc#10093
     *
     * @param $departmentId : uint, 如果不填，默认获取全量组织架构
     *
     * @return : Department array
     */
    public function DepartmentList($departmentId = null)
    { 
        self::_HttpCall(self::DEPARTMENT_LIST, 'GET', array('id'=>$departmentId));
        return Department::Array2DepartmentList($this->rspJson);
    }

    //
    // ------------------------- 标签管理 -------------------------------------
    //
    /**
     * @brief TagCreate : 创建标签
     *
     * @link https://work.weixin.qq.com/api/doc#10915
     *
     * @param $tag : Tag
     *
     * @return : uint tagid
     */
    public function TagCreate(Tag $tag)
    {
        Tag::CheckTagCreateArgs($tag); 
        $args = Tag::Tag2Array($tag);
        self::_HttpCall(self::TAG_CREATE, 'POST', $args); 
        return Utils::arrayGet($this->rspJson, "tagid");
    }

    /**
     * @brief TagUpdate : 更新标签名字
     *
     * @link https://work.weixin.qq.com/api/doc#10919
     *
     * @param $tag : Tag
     */
    public function TagUpdate(Tag $tag)
    {
        Tag::CheckTagUpdateArgs($tag); 
        $args = Tag::Tag2Array($tag);
        self::_HttpCall(self::TAG_UPDATE, 'POST', $args); 
    }

    /**
     * @brief TagDelete : 删除标签
     *
     * @link https://work.weixin.qq.com/api/doc#10920
     *
     * @param $tagid : uint
     */
    public function TagDelete($tagid)
    {
        Utils::checkIsUInt($tagid, "tagid");
        self::_HttpCall(self::TAG_DELETE, 'GET', array('tagid'=>$tagid));
    }

    /**
     * @brief TagGetUser : 获取标签成员
     *
     * @link https://work.weixin.qq.com/api/doc#10921
     *
     * @param $tagid : uint
     *
     * @return  : Tag
     */
    public function TagGetUser($tagid)
    {
        Utils::checkIsUInt($tagid, "tagid");
        self::_HttpCall(self::TAG_GET_USER, 'GET', array('tagid'=>$tagid));
        return Tag::Array2Tag($this->rspJson);
    }

    /**
     * @brief TagAddUser : 增加标签成员
     *
     * @link https://work.weixin.qq.com/api/doc#10923
     *
     * @param $tagid : uint
     * @param $userIdList : string array
     * @param $partyIdList : uint array
     * @param &$invalidUserIdList : output string array
     * @param &$invalidPartyIdList : output uint array
     *
     * @note 1: userIdList/partyIdList 不能同时为空
     * @note 2: 如果存在不合法的 userid/partyid, 不会throw Exception，但是会填充invalidUserIdList/invalidPartyIdList
     */
    public function TagAddUser($tagid, $userIdList=null, $partyIdList=null, &$invalidUserIdList, &$invalidPartyIdList)
    {
        Tag::CheckTagAddUserArgs($tagid, $userIdList, $partyIdList); 
        $args = Tag::ToTagAddUserArray($tagid, $userIdList, $partyIdList);

        self::_HttpCall(self::TAG_ADD_USER, 'POST', $args); 

        $invalidUserIdList_string = utils::arrayGet($this->rspJson, "invalidlist");
        $invalidUserIdList = explode('|',$invalidUserIdList_string);
        $invalidPartyIdList = utils::arrayGet($this->rspJson, "invalidparty");
    }

    /**
     * @brief TagDeleteUser : 删除标签成员
     *
     * @link https://work.weixin.qq.com/api/doc#10925
     *
     * @param $tagid : uint
     * @param $userIdList : string array
     * @param $partyIdList : uint array
     * @param &$invalidUserIdList : output string array
     * @param &$invalidPartyIdList : output uint array
     *
     * @note 1: userIdList/partyIdList 不能同时为空
     * @note 2: 如果存在不合法的 userid/partyid, 不会throw Exception，但是会填充invalidUserIdList/invalidPartyIdList
     */
    public function TagDeleteUser($tagid, $userIdList, $partyIdList, &$invalidUserIdList, &$invalidPartyIdList)
    {
        Tag::CheckTagAddUserArgs($tagid, $userIdList, $partyIdList); 
        $args = Tag::ToTagAddUserArray($tagid, $userIdList, $partyIdList);

        self::_HttpCall(self::TAG_DELETE_USER, 'POST', $args); 

        $invalidUserIdList_string = utils::arrayGet($this->rspJson, "invalidlist");
        $invalidUserIdList = explode('|',$invalidUserIdList_string);
        $invalidPartyIdList = utils::arrayGet($this->rspJson, "invalidparty");
    }

    /**
     * @brief TagGetList : 获取标签列表
     *
     * @link https://work.weixin.qq.com/api/doc#10926
     *
     * @return  : Tag array
     */
    public function TagGetList()
    {
        self::_HttpCall(self::TAG_GET_LIST, 'GET', null); 
        return Tag::Array2TagList($this->rspJson);
    }

    //
    // ------------------------- 异步任务 -------------------------------------
    //

    private function BatchJob(BatchJobArgs $batchJobArgs, $jobType)
    {
        Batch::CheckBatchJobArgs($batchJobArgs);

        $args = Utils::Object2Array($batchJobArgs);

        $url = HttpUtils::MakeUrl("/cgi-bin/batch/{$jobType}?access_token=ACCESS_TOKEN");
        $this->_HttpPostParseToJson($url, $args);
        $this->_CheckErrCode();

        return Utils::arrayGet($this->rspJson, "jobid");
    } 

    /**
     * @brief BatchSyncUser : 增量更新成员
     *
     * @link https://work.weixin.qq.com/api/doc#10138/增量更新成员
     *
     * @param $batchJobArgs : BatchJobArgs
     *
     * @return : string jobid
     */
    public function BatchSyncUser(BatchJobArgs $batchJobArgs)
    {
        return self::BatchJob($batchJobArgs, "syncuser");
    }

    /**
     * @brief BatchReplaceUser : 全量覆盖成员
     *
     * @link https://work.weixin.qq.com/api/doc#10138/全量覆盖成员
     *
     * @param $batchJobArgs : BatchJobArgs
     *
     * @return  : string jobid
     */
    public function BatchReplaceUser(BatchJobArgs $batchJobArgs)
    {
        return self::BatchJob($batchJobArgs, "replaceuser");
    }

    /**
     * @brief BatchReplaceParty : 全量覆盖部门
     *
     * @link https://work.weixin.qq.com/api/doc#10138/全量覆盖部门
     *
     * @param $batchJobArgs : BatchJobArgs
     *
     * @return  : string jobid
     */
    public function BatchReplaceParty(BatchJobArgs $batchJobArgs)
    {
        return self::BatchJob($batchJobArgs, "replaceparty");
    }

    /**
     * @brief BatchJobGetResult : 获取异步任务结果
     *
     * @link https://work.weixin.qq.com/api/doc#10138/获取异步任务结果
     *
     * @param $jobId : string
     *
     * @return  : BatchJobResult
     */
    public function BatchJobGetResult($jobId)
    {
        self::_HttpCall(self::BATCH_JOB_GET_RESULT, 'GET', array('jobid'=>$jobId));
        return Batch::Array2BatchJobResult($this->rspJson);
    }

    //
    // ------------------------- 邀请成员 --------------------------------------
    // 
    /**
        * @brief BatchInvite : 邀请成员
        *
        * @link https://work.weixin.qq.com/api/doc#12543
        *
        * @param $userIdList : input string array
        * @param $partyIdList : input uint array
        * @param $tagIdList : input uint array
        *
        * @param $invalidUserIdList : output string array
        * @param $invalidPartyIdList : output uint array
        * @param $invalidTagIdList : output uint array
     */
    public function BatchInvite(
        $userIdList=null, $partyIdList=null, $tagIdList=null,
        &$invalidUserIdList, &$invalidPartyIdList, &$invalidTagIdList)
    {
        if (is_null($userIdList) && is_null($partyIdList) && is_null($tagIdList)) {
            throw QyApiException("input can not be all null");
        }
        $args = array('user'=>$userIdList, 'party'=>$partyIdList, 'tag'=>$tagIdList);
        self::_HttpCall(self::BATCH_INVITE, 'POST', $args); 

        $invalidUserIdList = Utils::arrayGet($this->rspJson, 'invaliduser');
        $invalidPartyIdList = Utils::arrayGet($this->rspJson, 'invalidparty');
        $invalidTagIdList = Utils::arrayGet($this->rspJson, 'invalidtag');
    } 

    //
    // ------------------------- 应用管理 --------------------------------------
    //
    /**
     * @brief AgentGet : 获取应用
     *
     * @link https://work.weixin.qq.com/api/doc#10087
     *
     * @param $agentid : string
     *
     * @return  : Agent
     */
    public function AgentGet($agentid)
    {
        self::_HttpCall(self::AGENT_GET, 'GET', array('agentid'=>$agentid)); 
        return Agent::Array2Agent($this->rspJson);
    }

    /**
     * @brief AgentSet : 设置应用
     *
     * @link https://work.weixin.qq.com/api/doc#10088
     *
     * @param $agent : Agent
     */
    public function AgentSet($agent)
    {
        Agent::CheckAgentSetArgs($agent); 
        $args = Agent::Agent2Array($agent);
        self::_HttpCall(self::AGENT_SET, 'POST', $args); 
    } 

    /**
     * @brief AgentList : 获取应用列表
     *
     * @link https://work.weixin.qq.com/api/doc#11214
     *
     * @return : AgentList
     */
    public function AgentGetList()
    {
        self::_HttpCall(self::AGENT_GET_LIST, 'GET', null); 
        return Agent::Array2AgentList($this->rspJson);
    }

    //
    // ------------------------- 自定义菜单 -----------------------------------
    //
    //
    /**
     * @brief MenuCreate : 创建菜单
     *
     * @link https://work.weixin.qq.com/api/doc#10786
     *
     * @param $agentid : uint
     * @param $menu : Menu
     */
    public function MenuCreate($agentid, Menu $menu)
    {
        Menu::CheckMenuCreateArgs($agentid, $menu); 
        $args = Utils::Object2Array($menu);
        self::_HttpCall(self::MENU_CREATE."&agentid={$agentid}", 'POST', $args); 
    } 

    /**
     * @brief MenuGet : 获取菜单
     *
     * @link https://work.weixin.qq.com/api/doc#10787
     *
     * @param $agentid : string
     *
     * @return : Menu
     */
    public function MenuGet($agentid)
    {
        self::_HttpCall(self::MENU_GET, 'GET', array('agentid'=>$agentid)); 
        return Menu::Array2Menu($this->rspJson);
    }

    /**
     * @brief MenuGet : 删除菜单
     *
     * @link https://work.weixin.qq.com/api/doc#10788
     *
     * @param $agentid : string
     */
    public function MenuDelete($agentid)
    {
        self::_HttpCall(self::MENU_DELETE, 'GET', array('agentid'=>$agentid)); 
    }

    //
    // --------------------------- 消息推送 -----------------------------------
    //
    //
    /**
     * @brief MessageSend : 发送消息
     *
     * @link https://work.weixin.qq.com/api/doc#10167
     *
     * @param $message : Message
     * @param $invalidUserIdList : string array
     * @param $invalidPartyIdList : uint array
     * @param $invalidTagIdList : uint array
     *
     * @return 
     */
    public function MessageSend(Message $message, &$invalidUserIdList, &$invalidPartyIdList, &$invalidTagIdList)
    {
        $message->CheckMessageSendArgs(); 
        $args = $message->Message2Array();

        self::_HttpCall(self::MESSAGE_SEND, 'POST', $args); 

        $invalidUserIdList_string = utils::arrayGet($this->rspJson, "invaliduser");
        $invalidUserIdList = explode('|', $invalidUserIdList_string);

        $invalidPartyIdList_string = utils::arrayGet($this->rspJson, "invalidparty");
        $temp = explode('|', $invalidPartyIdList_string);
        foreach($temp as $item) {
            $invalidPartyIdList[] = intval($item);
        }

        $invalidTagIdList_string = utils::arrayGet($this->rspJson, "invalidtag");
        $temp = explode('|', $invalidTagIdList_string);
        foreach($temp as $item) {
            $invalidTagIdList[] = intval($item);
        }
    } 
    //
    // --------------------------- 素材管理 -----------------------------------
    //
    //
    /**
     * @brief MediaUpload : 上传临时素材
     *
     * @link https://work.weixin.qq.com/api/doc#10112
     *
     * @param $filePath : string, 文件路径
     * @param $type : string, 媒体文件类型，分别有图片（image）、语音（voice）、视频（video），普通文件（file）
     *
     * @return : string media_id
     */
    public function MediaUpload($filePath, $type)
    {
        Utils::checkNotEmptyStr($filePath, "filePath");
        Utils::checkNotEmptyStr($type, "type");
        if ( ! file_exists($filePath)) { 
            throw new QyApiException("file not exists");
        }

        // 兼容php5.3-5.6 curl模块的上传操作
		$args = null;
        if (class_exists('\CURLFile')) {
            $args = array('media' => new \CURLFile(realpath($filePath)));
        } else {
            $args = array('media' => '@' . realpath($filePath));
        }

        $url = HttpUtils::MakeUrl("/cgi-bin/media/upload?access_token=ACCESS_TOKEN&type={$type}");
        $this->_HttpPostParseToJson($url, $args, true, true/*isPostFile*/);
        $this->_CheckErrCode();

        return $this->rspJson["media_id"];
    }

    public function MediaUploadByBuffer($buffer, $type)
    {
        $tmpPath = self::WriteTmpFile($buffer);

        try {
            $ret = $this->mediaUpload($tmpPath, $type);
            unlink($tmpPath);
            return $ret;
        }
        catch (Exception $ex) {
            unlink($tmpPath);
            throw $ex;
        }
    }

    /**
     * @brief MediaGet : 获取临时素材
     *
     * @link https://work.weixin.qq.com/api/doc#10115
     *
     * @param $media_id : string
     *
     * @return : string
     */
    public function MediaGet($media_id)
    {
        Utils::checkNotEmptyStr($media_id, "media_id"); 
        self::_HttpCall(self::MEDIA_GET, 'GET', array('media_id'=>$media_id));
        return $this->rspRawStr;
    }
    //
    // --------------------------- 身份验证 -----------------------------------
    //
    //
    /**
     * @brief GetUserInfoByCode : 根据code获取成员信息
     *
     * @link https://work.weixin.qq.com/api/doc#10028/根据code获取成员信息
     *
     * @param $code : string
     *
     * @return : UserInfoByCode
     */
    public function GetUserInfoByCode($code)
    {
        Utils::checkNotEmptyStr($code, "code");
        self::_HttpCall(self::GET_USER_INFO_BY_CODE, 'GET', array('code'=>$code));
        return UserInfoByCode::Array2UserInfoByCode($this->rspJson);
    }

    /**
     * @brief GetUserDetailByUserTicket : 使用user_ticket获取成员详情
     *
     * @link https://work.weixin.qq.com/api/doc#10028/使用user_ticket获取成员详情
     *
     * @param $ticket : string
     *
     * @return : UserDetailByUserTicket
     */
    public function GetUserDetailByUserTicket($ticket)
    {
        Utils::checkNotEmptyStr($ticket, "ticket");
        $args = array("user_ticket" => $ticket);
        self::_HttpCall(self::GET_USER_DETAIL, 'POST', $args); 
        return UserDetailByUserTicket::Array2UserDetailByUserTicket($this->rspJson);
    }

    //
    // ---------------------- 移动端SDK ---------------------------------------
    //
    //
    /**
     * @brief TicketGet : 获取电子发票ticket
     *
     * @link https://work.weixin.qq.com/api/doc#10029/获取电子发票ticket
     *
     * @return : string ticket
     */
    public function TicketGet()
    {
        self::_HttpCall(self::GET_TICKET, 'GET', array('type'=>'wx_card'));
        return $this->rspJson["ticket"];
    }

    /**
     * @brief JsApiTicketGet : 获取jsapi_ticket
     *
     * @link https://work.weixin.qq.com/api/doc#10029/获取jsapi_ticket
     *
     * @return : string ticket
     */
    public function JsApiTicketGet()
    {
        self::_HttpCall(self::GET_JSAPI_TICKET, 'GET', null); 
        return $this->rspJson["ticket"];
    }
	
    /**
     * @brief JsApiSignatureGet : 计算jsapi的签名
     *
     * @link https://work.weixin.qq.com/api/doc#10029/%E7%AD%BE%E5%90%8D%E7%AE%97%E6%B3%95 
         *
     * @param $jsapiTicket : string
     * @param $nonceStr : string
     * @param $timestamp : string
     * @param $url : string
     *
     * @return : string ticket
     */
    public function JsApiSignatureGet($jsapiTicket, $nonceStr, $timestamp, $url)
    {
    	$string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
        return sha1($string);
    }

    //
    // ---------------------- OA数据接口 --------------------------------------
    //
    //
    /**
     * @brief CheckinOptionGet : 获取打卡规则
     *
     * @link https://work.weixin.qq.com/api/doc#12423
     *
     * @param $datetime : uint
     * @param $useridlist : string array
     *
     * @return  : CheckinOption
     */
    public function CheckinOptionGet($datetime, $useridlist)
    { 
        Utils::checkIsUInt($datetime, "datetime");
        Utils::checkNotEmptyArray($useridlist, "useridlist");
        if (count($useridlist) > 100) throw new QyApiException("no more than 100 user once"); 
        $args = array("datetime" => $datetime, "useridlist" => $useridlist);

        self::_HttpCall(self::GET_CHECKIN_OPTION, 'POST', $args); 

        return CheckinOption::ParseFromArray($this->rspJson);
    }

    /**
     * @brief CheckinDataGet : 获取打卡数据
     *
     * @link https://work.weixin.qq.com/api/doc#11196
     *
     * @param $opencheckindatatype : uint
     * @param $starttime : uint
     * @param $endtime : uint
     * @param $useridlist : string array
     *
     * @return  : CheckinDataList
     */
    public function CheckinDataGet($opencheckindatatype, $starttime, $endtime, $useridlist)
    { 
        Utils::checkIsUInt($opencheckindatatype, "opencheckindatatype");
        Utils::checkIsUInt($starttime, "starttime");
        Utils::checkIsUInt($endtime, "endtime");
        Utils::checkNotEmptyArray($useridlist, "useridlist");
        if (count($useridlist) > 100) throw new QyApiException("no more than 100 user once");

        $args = array(
            "opencheckindatatype" => $opencheckindatatype, 
            "starttime" => $starttime,
            "endtime" => $endtime,
            "useridlist" => $useridlist,
        );
        self::_HttpCall(self::GET_CHECKIN_DATA, 'POST', $args); 
        return CheckinDataList::ParseFromArray($this->rspJson);
    }

    /**
     * @brief ApprovalDataGet : 获取审批数据
     *
     * @link https://work.weixin.qq.com/api/doc#11228
     *
     * @param $starttime : uint
     * @param $endtime : uint
     * @param $next_spnum : uint
     *
     * @return  : ApprovalDataList
     */
    public function ApprovalDataGet($starttime, $endtime, $next_spnum=null)
    { 
        Utils::checkIsUInt($starttime, "starttime");
        Utils::checkIsUInt($endtime, "endtime");

        $args = array();
        Utils::setIfNotNull($starttime, "starttime", $args);
        Utils::setIfNotNull($endtime, "endtime", $args);
        Utils::setIfNotNull($next_spnum, "next_spnum", $args);

        self::_HttpCall(self::GET_APPROVAL_DATA, 'POST', $args); 
        return ApprovalDataList::ParseFromArray($this->rspJson);
    }

    //
    // ---------------------- 企业支付 ----------------------------------------
    //
    static private function _HttpPostXml($url, $args)
    {
        $postData = Utils::Array2Xml("xml", $args); 
        $this->rspRawStr = HttpUtils::httpPost($url, $postData);
        return Utils::Xml2Array($this->rspRawStr);
    } 
    static private function _CheckXmlRetCode($rsp)
    {
        if ($rsp["return_code"] != "SUCCESS") { 
            throw new QyApiException("response error:" . $rsp);
        }
    }

    /**
     * @brief SendWorkWxRedpack : 发放企业红包
     *
     * @link https://work.weixin.qq.com/api/doc#11543
     *
     * @param $SendWorkWxRedpackReq
     *
     * @return : SendWorkWxRedpackRsp
     *  
     * @note : 本接口只检查通信是否正常，业务结果需调用方自行判断，参看文档
     */
    static public function SendWorkWxRedpack(SendWorkWxRedpackReq $SendWorkWxRedpackReq)
    {
        $url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/sendworkwxredpack"; 
        $args = Utils::Object2Array($SendWorkWxRedpackReq);
        $SendWorkWxRedpackRsp = self::_HttpPostXml($url, $args); 
        self::_CheckXmlRetCode($SendWorkWxRedpackRsp);
        return $SendWorkWxRedpackRsp;
    }

    /**
     * @brief QueryWorkWxRedpack : 查询红包记录
     *
     * @link https://work.weixin.qq.com/api/doc#11544
     *
     * @param $QueryWorkWxRedpackReq
     *
     * @return : QueryWorkWxRedpackRsp
     */
    static public function QueryWorkWxRedpack(QueryWorkWxRedpackReq $QueryWorkWxRedpackReq)
    {
        $url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/queryworkwxredpack"; 
        $args = Utils::Object2Array($QueryWorkWxRedpackReq);
        $QueryWorkWxRedpackRsp = self::_HttpPostXml($url, $args); 
        self::_CheckXmlRetCode($QueryWorkWxRedpackRsp);
        return $QueryWorkWxRedpackRsp;
    }

    /**
     * @brief PayWwSptrans2Pocket : 向员工付款
     *
     * @link https://work.weixin.qq.com/api/doc#11545
     *
     * @param $PayWwSptrans2PocketReq
     *
     * @return : PayWwSptrans2PocketRsp
     */
    static public function PayWwSptrans2Pocket(PayWwSptrans2PocketReq $PayWwSptrans2PocketReq)
    {
        $url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/queryworkwxredpack"; 
        $args = Utils::Object2Array($PayWwSptrans2PocketReq);
        $PayWwSptrans2PocketRsp = self::_HttpPostXml($url, $args); 
        self::_CheckXmlRetCode($PayWwSptrans2PocketRsp);
        return $PayWwSptrans2PocketRsp;
    }

    /**
     * @brief QueryWwSptrans2Pocket : 查询付款记录
     *
     * @link https://work.weixin.qq.com/api/doc#11546
     *
     * @param $QueryWwSptrans2PocketReq
     *
     * @return : QueryWwSptrans2Pocketsp
     */
    static public function QueryWwSptrans2Pocket(QueryWwSptrans2PocketReq $QueryWwSptrans2PocketReq)
    {
        $url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/querywwsptrans2pocket"; 
        $args = Utils::Object2Array($QueryWwSptrans2PocketReq);
        $QueryWwSptrans2Pocketsp = self::_HttpPostXml($url, $args); 
        self::_CheckXmlRetCode($QueryWwSptrans2Pocketsp);
        return $QueryWwSptrans2Pocketsp;
    }
    //
    // ---------------------- 电子发票 ----------------------------------------
    //
    //
    /**
     * @brief GetInvoiceInfo : 查询电子发票
     *
     * @link https://work.weixin.qq.com/api/doc#11631
     *
     * @param $card_id : string
     * @param $encrypt_code : string
     *
     * @return : InvoiceInfo
     */
    public function GetInvoiceInfo($card_id, $encrypt_code)
    { 
        Utils::checkNotEmptyStr($card_id, "card_id");
        Utils::checkNotEmptyStr($encrypt_code, "encrypt_code"); 
        $args = array("card_id" => $card_id, "encrypt_code" => $encrypt_code); 
        self::_HttpCall(self::GET_INVOICE_INFO, 'POST', $args); 
        return Utils::Array2Object($this->rspJson);
    }

    /**
     * @brief UpdateInvoiceStatus : 更新发票状态
     *
     * @link https://work.weixin.qq.com/api/doc#11633
     *
     * @param $card_id : string
     * @param $encrypt_code : string
     * @param $reimburse_status : string
     *
     * @return 
     */
    public function UpdateInvoiceStatus($card_id, $encrypt_code, $reimburse_status)
    { 
        Utils::checkNotEmptyStr($card_id, "card_id");
        Utils::checkNotEmptyStr($encrypt_code, "encrypt_code");
        Utils::checkNotEmptyStr($reimburse_status, "reimburse_status"); 
        $args = array("card_id" => $card_id, "encrypt_code" => $encrypt_code, "reimburse_status" => $reimburse_status); 
        self::_HttpCall(self::UPDATE_INVOICE_STATUS, 'POST', $args); 
    }

    /**
     * @brief BatchUpdateInvoiceStatus : 批量更新发票状态
     *
     * @link https://work.weixin.qq.com/api/doc#11634
     *
     * @param $BatchUpdateInvoiceStatusReq
     *
     */
    public function BatchUpdateInvoiceStatus(BatchUpdateInvoiceStatusReq $BatchUpdateInvoiceStatusReq)
    { 
        $args = Utils::Object2Array($BatchUpdateInvoiceStatusReq); 
        self::_HttpCall(self::BATCH_UPDATE_INVOICE_STATUS, 'POST', $args); 
    }

    /**
     * @brief BatchGetInvoiceInfo : 批量查询电子发票
     *
     * @link https://work.weixin.qq.com/api/doc#11974
     *
     * @param $BatchGetInvoiceInfoReq
     *
     * @return : BatchGetInvoiceInfoRsp
     */
    public function BatchGetInvoiceInfo(BatchGetInvoiceInfoReq $BatchGetInvoiceInfoReq)
    { 
        $args = Utils::Object2Array($BatchGetInvoiceInfoReq); 
        self::_HttpCall(self::BATCH_GET_INVOICE_INFO, 'POST', $args); 
        return Utils::Array2Object($this->rspJson);
    }

    //
    // ------------------------- private --------------------------------------
    //

    static private function WriteTmpFile($buffer)
    {
        Utils::checkNotEmptyStr($buffer, "buffer");

        $tmpPath = tempnam(sys_get_temp_dir(), "qytmpfile");
        $handle = null;

        try {
            $handle = fopen($tmpPath, "wb");
            if ($handle === false) {
                throw new SysException("create tmp file failed");
            }

            $writeBytes = fwrite($handle, $buffer);
            if ($writeBytes === false) {
                throw new SysException("write tmp file failed");
            }
            while ($writeBytes < count($buffer)) {
                $n = fwrite($handle, substr($buffer, $writeBytes));
                if ($n === false) {
                    throw new SysException("write tmp file failed");
                }
                $writeBytes += $n;
            }
            fclose($handle);
        }
        catch (Exception $ex) {
            if (!is_null($handle)) {
                fclose($handle);
                unlink($tmpPath);
            }
            throw $ex;
        }
        return $tmpPath;
    }

    //
    // ------------------------- 家校沟通 - 学生管理 -------------------------------------
    /**
     * @brief SchoolStudentCreate : 创建学生
     *
     * @link https://work.weixin.qq.com/api/doc#90001/90143/92035
     *
     * @param $student : Student
     */
    public function SchoolStudentCreate(Student $student)
    {
        Student::CheckStudentCreateArgs($student);
        $args = Utils::Object2Array($student);
        self::_HttpCall(self::SCHOOL_CREATE_STUDENT, 'POST', $args);
    }

    /**
     * @brief SchoolStudentUpdate : 更新学生
     *
     * @link https://work.weixin.qq.com/api/doc#90001/90143/92041
     *
     * @param $student : Student
     */
    public function SchoolStudentUpdate(Student $student)
    {
        Student::CheckStudentUpdateArgs($student);
        $args = Utils::Object2Array($student);
        self::_HttpCall(self::SCHOOL_UPDATE_STUDENT, 'POST', $args);
    }

    /**
     * @brief SchoolStudentDelete : 删除学生
     * 
     * @link https://work.weixin.qq.com/api/doc#90001/90143/92039
     *
     * @param $userid : string
     */
    public function SchoolStudentDelete($userid)
    {
        Utils::checkNotEmptyStr($userid, "userid");
        self::_HttpCall(self::SCHOOL_DELETE_STUDENT, 'GET', array('userid' => $userid)); 
    }

    /**
     * @brief SchoolStudentBatchCreate : 批量创建学生
     *
     * @link https://work.weixin.qq.com/api/doc#10060
     *
     * @param $students : Student array
     *
     */
    public function SchoolStudentBatchCreate(array $students)
    {
        Student::CheckStudentBatchCreateArgs($students);
        foreach ($students as $student) {
            $args['students'][] = Utils::Object2Array($student);
        }
        self::_HttpCall(self::SCHOOL_BATCH_CREATE_STUDENT, 'POST', $args);
    }

    /**
     * @brief SchoolStudentBatchUpdate : 批量更新学生
     *
     * @link https://work.weixin.qq.com/api/doc#90001/90143/92042
     *
     * @param $students : Student array
     *
     */
    public function SchoolStudentBatchUpdate(array $students)
    {
        Student::CheckStudentBatchUpdateArgs($students);
        foreach ($students as $student) {
            $args['students'][] = Utils::Object2Array($student);
        }
        self::_HttpCall(self::SCHOOL_BATCH_UPDATE_STUDENT, 'POST', $args);
    }

    /**
     * @brief UserBatchDelete : 批量删除学生
     *
     * @link https://work.weixin.qq.com/api/doc#90001/90143/92040
     *
     * @param $userIdList : string array
     *
     */
    public function SchoolStudentBatchDelete(array $userIdList)
    {
        Student::CheckStudentBatchDeleteArgs($userIdList);
        $args = array("useridlist" => $userIdList);
        self::_HttpCall(self::SCHOOL_BATCH_DELETE_STUDENT, 'POST', $args);
    }

    //
    // ------------------------- 家校沟通 - 家长管理 -------------------------------------
    /**
     * @brief SchoolParentCreate : 创建家长
     *
     * @link https://work.weixin.qq.com/api/doc#90001/90143/92077
     *
     * @param $parent : Parents
     */
    public function SchoolParentCreate(Parents $parent)
    {
        Parents::CheckParentsCreateArgs($parent);
        $args = Parents::Parents2Array($parent);
        self::_HttpCall(self::SCHOOL_CREATE_PARENT, 'POST', $args);
    }

    /**
     * @brief SchoolParentUpdate : 更新家长
     *
     * @link https://work.weixin.qq.com/api/doc#90001/90143/92081
     *
     * @param $parent : Parents
     */
    public function SchoolParentUpdate(Parents $parent)
    {
        Parents::CheckParentsUpdateArgs($parent);
        $args = Parents::Parents2Array($parent);
        self::_HttpCall(self::SCHOOL_UPDATE_PARENT, 'POST', $args);
    }

    /**
     * @brief SchoolParentDelete : 删除家长
     * 
     * @link https://work.weixin.qq.com/api/doc#90001/90143/92079
     *
     * @param $userid : string
     */
    public function SchoolParentDelete($userid)
    {
        Utils::checkNotEmptyStr($userid, "userid");
        self::_HttpCall(self::SCHOOL_DELETE_PARENT, 'GET', array('userid' => $userid)); 
    }

    /**
     * @brief SchoolParentBatchCreate : 批量创建家长
     *
     * @link https://work.weixin.qq.com/api/doc#90001/90143/92078
     *
     * @param $parents : Parents array
     *
     */
    public function SchoolParentBatchCreate(array $parents)
    {
        Parents::CheckParentsBatchCreateArgs($parents);
        foreach ($parents as $parent) {
            $args['parents'][] = Parents::Parents2Array($parent);
        }
        self::_HttpCall(self::SCHOOL_BATCH_CREATE_PARENT, 'POST', $args);
    }

    /**
     * @brief SchoolStudentBatchUpdate : 批量更新家长
     *
     * @link https://work.weixin.qq.com/api/doc#90001/90143/92042
     *
     * @param $parents : Parents array
     *
     */
    public function SchoolParentBatchUpdate(array $parents)
    {
        Parents::CheckStudentBatchUpdateArgs($parents);
        foreach ($parents as $parent) {
            $args['parents'][] = Parents::Parents2Array($parent);
        }
        self::_HttpCall(self::SCHOOL_BATCH_UPDATE_PARENT, 'POST', $args);
    }

    /**
     * @brief SchoolParentBatchDelete : 批量删除家长
     *
     * @link https://work.weixin.qq.com/api/doc#90001/90143/92080
     *
     * @param $userIdList : string array
     *
     */
    public function SchoolParentBatchDelete(array $userIdList)
    {
        Parents::CheckParentsBatchDeleteArgs($userIdList);
        $args = array("useridlist" => $userIdList);
        self::_HttpCall(self::SCHOOL_BATCH_DELETE_PARENT, 'POST', $args);
    }

    //
    // ------------------------- 家校沟通 - 读取成员 -------------------------------------
    /**
     * @brief SchoolUserGet : 读取家校成员
     *
     * @link https://work.weixin.qq.com/api/doc#90001/90143/92038
     *
     * @param $userid : string
     *
     * @return : Student | Parents | null
     */
    public function SchoolUserGet($userid)
    {
        Utils::checkNotEmptyStr($userid, "userid"); 
        self::_HttpCall(self::SCHOOL_USER_GET, 'GET', array('userid' => $userid)); 

        $userType = Utils::arrayGet($this->rspJson, "user_type");
        if ($userType == 1) {
            return Student::Array2Student($this->rspJson['student']);
        } elseif ($userType == 2) {
            return Parents::Array2Parents($this->rspJson['parent']);
        }

        return null;
    }

    /**
     * @brief SchoolUserList : 获取部门家校成员
     *
     * @link https://work.weixin.qq.com/api/doc#90001/90143/92043
     *
     * @param $department_id : uint
     * @param $fetchChild : 1/0 是否递归获取子部门下面的成员
     *
     * @return : User array
     */
    public function SchoolUserList($department_id, $fetchChild)
    {
        Utils::checkIsUInt($department_id, "department_id"); 
        self::_HttpCall(self::SCHOOL_USER_LIST, 'GET', array('department_id'=>$department_id, 'fetch_child'=>$fetchChild)); 
        return Student::Array2StudentList($this->rspJson);
    }

    //
    // ------------------------- 家校沟通 - 部门管理 -------------------------------------
    /**
     * @brief SchoolDepartmentCreate : 创建部门
     *
     * @link https://work.weixin.qq.com/api/doc#90001/90143/92296
     *
     * @param $department : SchoolDepartment
     *
     * @return  : uint departmentId
     */
    public function SchoolDepartmentCreate(SchoolDepartment $department)
    {
        SchoolDepartment::CheckDepartmentCreateArgs($department); 
        $args = SchoolDepartment::Department2Array($department);
        self::_HttpCall(self::SCHOOL_DEPARTMENT_CREATE, 'POST', $args); 
        return Utils::arrayGet($this->rspJson, "id");
    }

    /**
     * @brief DepartmentUpdate : 更新部门
     *
     * @link https://work.weixin.qq.com/api/doc#90001/90143/92297
     *
     * @param $department : SchoolDepartment
     */
    public function SchoolDepartmentUpdate(SchoolDepartment $department)
    {
        SchoolDepartment::CheckDepartmentUpdateArgs($department); 
        $args = SchoolDepartment::Department2Array($department);
        self::_HttpCall(self::SCHOOL_DEPARTMENT_UPDATE, 'POST', $args); 
    }

    /**
     * @brief DepartmentDelete : 删除部门
     *
     * @link https://work.weixin.qq.com/api/doc#90001/90143/92298
     *
     * @param $departmentId : uint
     */
    public function SchoolDepartmentDelete($departmentId)
    {
        Utils::checkIsUInt($departmentId, "departmentId"); 
        self::_HttpCall(self::SCHOOL_DEPARTMENT_DELETE, 'GET', array('id'=>$departmentId));
    }

    /**
     * @brief SchoolDepartmentList : 获取部门列表
     *
     * @link https://work.weixin.qq.com/api/doc#90001/90143/92299
     *
     * @param $departmentId : uint, 如果不填，默认获取全量组织架构
     *
     * @return : SchoolDepartment array
     */
    public function SchoolDepartmentList($departmentId = null)
    { 
        self::_HttpCall(self::SCHOOL_DEPARTMENT_LIST, 'GET', array('id'=>$departmentId));
        return SchoolDepartment::Array2DepartmentList($this->rspJson);
    }

    //
    // ------------------------- 家校沟通 - 设置通讯录自动同步模式 -------------------------------------
    /**
     * @brief SchoolSetSyncMode : 设置家校通讯录自动同步模式
     * 
     * @link https://work.weixin.qq.com/api/doc#90001/90143/92083
     * 
     * @param $arch_sync_mode : uint, 家校通讯录同步模式：1-禁止将标签同步至家校通讯录，2-禁止将家校通讯录同步至标签，3-禁止家校通讯录和标签相互同步
     *
     * @return : SchoolDepartment array
     */
    public function SchoolSetSyncMode($arch_sync_mode)
    {
        Utils::checkIsUInt($arch_sync_mode, "arch_sync_mode"); 
        self::_HttpCall(self::SCHOOL_SET_SYNC_MODE, 'POST', array('arch_sync_mode'=>$arch_sync_mode));
    }

    /**
     * code2Session
     * @param $external_userid 外部联系人userid
     */
    public function JscodeToSession($js_code)
    {
        Utils::checkNotEmptyStr($js_code, "js_code");
        self::_HttpCall(self::MINIPROGRAM_CODETOSESSION, 'GET', ['js_code' => $js_code, 'grant_type' => 'authorization_code']);
        return CodeSession::Array2CodeSession($this->rspJson);
    }

    /**
     * 获取客户详情
     * @param $external_userid 外部联系人userid
     */
    public function ExternalContactGet($external_userid)
    {
        Utils::checkNotEmptyStr($external_userid, "external_userid");
        self::_HttpCall(self::EXTERNAL_CONTACT_GET, 'GET', array('external_userid' => $external_userid));
        return ExternalContact::Array2ExternalContact($this->rspJson);
    }
}
