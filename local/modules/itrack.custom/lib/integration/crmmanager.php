<?php

namespace iTrack\Custom\Integration;

class CrmManager
{
    const DEAL_BEFORE_CREATE = 1;
    const DEAL_AFTER_CREATE = 2;
    const DEAL_BEFORE_UPDATE = 3;
    const DEAL_AFTER_UPDATE = 4;

    public static function handleDealEvent($arFields, $type)
    {
        $sendpulseBooksIntegration = Sendpulse\Books::getInstance();
        if($sendpulseBooksIntegration->isEnabled()) {
            // TODO: реализовать очередь в бд, и вытащить обработку на агента
            $sendpulseBooksIntegration->handleDeal($arFields['ID'], $type, $arFields);
        }
    }
}