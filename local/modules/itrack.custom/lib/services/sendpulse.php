<?php

namespace iTrack\Custom\Services;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Config\Option;
use Sendpulse\RestApi\ApiClient;
use Sendpulse\RestApi\Storage\FileStorage;

class Sendpulse
{
    protected $api = null;

    public function __construct() {
        $accId = Option::get('itrack.custom', 'sendpulse_id', '');
        $token = Option::get('itrack.custom', 'sendpulse_token', '');
        if(empty($accId) || empty($token)) {
            throw new ArgumentException('Auth options is not set');
        }

        $this->api = new ApiClient($accId, $token, new FileStorage($_SERVER['DOCUMENT_ROOT'].'/local/modules/itrack.custom/tmp/'));
    }

    public function __call($name, $arguments)
    {
        if(method_exists($this->api, $name)) {
            return call_user_func_array([$this->api, $name], $arguments);
        } else {
            throw new \BadMethodCallException('Unknown api method');
        }
    }
}