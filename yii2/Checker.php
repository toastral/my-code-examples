<?php

namespace common\modules\webchecker\models;

use Yii;
use \common\modules\webchecker\models\Site;

class Checker
{
    /**
     * @param object $curSite
     * @param object $checkLogManager
     * @return mixed
     */
    public function checkSiteAndUpdateLog($curSite, $checkLogManager)
    {
        $checkDataObject = $this->checkSite($curSite);
        $code = $checkDataObject->response_code;
        $checkLogManager->log($checkDataObject);
        $curSite->cur_response_code = $code;
        $curSite->cur_response_code_stamp = $checkDataObject->stamp;
        if ($code != 200) {
            if ($curSite->parent_id) {
                $curSite->badChildForParent($curSite->parent_id);
            }
            $curSite->last_bad_response_code = $code;
            $curSite->last_bad_response_code_stamp = $checkDataObject->stamp;
        }
        $curSite->save();
        return $code;
    }

    /**
     * @param object $curSite
     * @return object
     */
    public function checkSite($curSite)
    {
        $info = $this->getResponseInfo($curSite->url);
        return (object)[
            'site_id' => $curSite->id,
            'parent_id' => $curSite->parent_id,
            'response_code' => $info["http_code"],
            'response_time' => intval($info["total_time"]),
            'stamp' => time()
        ];
    }

    /**
     * @param string $url
     * @return mixed
     */
    public function getResponseInfo($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0)");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $rt = curl_exec($ch);
        $info = curl_getinfo($ch);
        return $info; // total_time http_code
    }
}
