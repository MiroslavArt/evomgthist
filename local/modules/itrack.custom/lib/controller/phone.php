<?php

namespace iTrack\Custom\Controller;

use Bitrix\Main\Engine\Controller;
use iTrack\Custom\Repository\PhoneHelper;

class Phone extends Controller
{
    public function configureActions()
    {
        return [
            'getTimezone' => [],
            'getTimezoneCollection' => []
        ];
    }

    public function getTimezoneAction($phone)
    {
        $phoneHelper = new PhoneHelper($phone);
        return $phoneHelper->getTimezone();
    }

    public function getTimezoneCollectionAction($phones)
    {
        $result = [];
        if(!empty($phones)) {
            foreach($phones as $phone) {
                $phoneHelper = new PhoneHelper($phone);
                $result[] = [
                    'phone' => $phone,
                    'timezone' => $phoneHelper->getTimezone()
                ];
            }
        }
        return $result;
    }
}