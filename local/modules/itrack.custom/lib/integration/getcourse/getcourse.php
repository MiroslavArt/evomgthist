<?php

namespace iTrack\Custom\Integration\Getcourse;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Result;
use Bitrix\Main\Error;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Type\DateTime;
use Bitrix\Highloadblock\HighloadBlockTable as HLBT;

class Getcourseimp
{
    private $webhookToken;
    private $enabled = false;
    private $result;


    public function __construct()
    {
        $enabled = Option::get('itrack.custom','getcourse_enable_imp','N');
        $token = Option::get('itrack.custom','getcourse_token','');
        \Bitrix\Main\Loader::includeModule('highloadblock');
        $this->enabled = $enabled === 'Y';
        if(!empty($token)) {
            $this->webhookToken = $token;
        } else {
            $this->enabled = false;
        }
        $this->result = new Result();
    }

    public function processWebhook()
    {
        if($this->enabled) {
            try {
                $request = \Bitrix\Main\HttpApplication::getInstance()->getContext()->getRequest();
                $token = $request->get('token');
                if ($token === $this->webhookToken) {
                    define("MY_HL_BLOCK_ID", 2);
                    $order = $request->get('order');
                    $status = $request->get('status');
                    if($order) {
                        if($status=='began' || $status=='60pers' || $status=='completed' || $status=='failed') {

                        }
                    }
                    $entity_data_class =  $this->GetEntityDataClass(MY_HL_BLOCK_ID);
                    $addResult = $entity_data_class::add(array(
                        'UF_ORDER'         => $order,
                        'UF_STATUS'         => $status,
                        'UF_PROCESSED'        => '0',
                        'UF_DATEIMP' => date("d.m.Y")
                    ));
                    if (!$addResult->isSuccess()) {
                        $this->result->addError(new Error('save error'));
                    }
                } else {
                    $this->result->addError(new Error('access denied'));
                }
            } catch(\Exception $e) {
                $this->result->addError(new Error('internal error'));
            }
        }
        $this->showResponse();
    }

    private function GetEntityDataClass($HlBlockId)
    {
        if (empty($HlBlockId) || $HlBlockId < 1)
        {
            return false;
        }
        $hlblock = HLBT::getById($HlBlockId)->fetch();
        $entity = HLBT::compileEntity($hlblock);
        $entity_data_class = $entity->getDataClass();
        return $entity_data_class;
    }

    private function showResponse()
    {
        $response = ['success' => true];
        if(!$this->result->isSuccess()) {
            $response['success'] = false;
            $response['error'] = true;
            $response['message'] = implode(', ',$this->result->getErrorMessages());
        }

        echo Json::encode($response);
    }

}