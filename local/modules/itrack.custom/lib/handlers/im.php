<?php

namespace iTrack\Custom\Handlers;

class Im
{
    public static function onBeforeMessageNotifyAdd(&$arFields)
    {
        /*if($arFields['NOTIFY_MODULE'] == 'tasks') {
            return false;
        }*/
        //file_put_contents($_SERVER['DOCUMENT_ROOT'].'/temp_im_log.log', print_r($arFields, true), FILE_APPEND);
    }
}