<?php

namespace shophy\wxwork\structs;

use shophy\wxwork\common\Utils;

class SessionInfo { 
    public $appid = null; // uint array
    public $auth_type = 0; // uint, 授权类型：0 正式授权， 1 测试授权， 默认值为0

    public function FormatArgs()
    { 
        $args = array();

        $args["appid"] = $this->appid;
        $args["auth_type"] = $this->auth_type;

        return $args;
    }
}
