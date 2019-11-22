<?php

namespace iTrack\Custom\Controller;

use Bitrix\Main\Engine\Controller;
use iTrack\Custom\Repository\PhoneHelper;

class Phone extends Controller
{
    public function configureActions()
    {
        return [
            'getTimezone' => []
        ];
    }

    public function getTimezoneAction($phone)
    {
        $phoneHelper = new PhoneHelper($phone);
        return $phoneHelper->getTimezone();
    }
}