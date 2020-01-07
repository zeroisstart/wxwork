<?php

namespace wxwork\structs;

use wxwork\common\Utils;

class CheckinDate { 
    public $workdays = null; // int array
    public $checkintime = null; // CheckinTime array
    public $flex_time = null; // int
    public $noneed_offwork = null; // bool 
    public $limit_aheadtime = null; // uint 

    public static function ParseFromArray($arr)
    {
        $info = new CheckinDate();

        $info->workdays = Utils::arrayGet($arr, "workdays"); 
        foreach($arr["checkintime"] as $item) { 
            $info->checkintime[] = CheckinTime::ParseFromArray($item);
        }
        $info->flex_time = Utils::arrayGet($arr, "flex_time"); 
        $info->noneed_offwork = Utils::arrayGet($arr, "noneed_offwork"); 
        $info->limit_aheadtime = Utils::arrayGet($arr, "limit_aheadtime"); 

        return $info;
    }
}
