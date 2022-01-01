<?php

namespace iTrack\Custom\Repository;

use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\ORM\Query\Query;
use iTrack\Custom\Entity\MobilePhoneTimezoneTable;

class PhoneHelper
{
    protected $originalPhone;
    protected $parsedPhone;

    public function __construct($phone)
    {
        if(strlen($phone) !== 0) {
            $this->originalPhone = $phone;
        } else {
            throw new ArgumentNullException('Phone must be not null');
        }

        $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        $this->parsedPhone = $phoneUtil->parse($this->originalPhone, "RU");
    }

    public function getTimezone()
    {
        $nationalNumber = $this->parsedPhone->getNationalNumber();
        $firstDigit = substr($nationalNumber, 0, 1);
        if((int)$firstDigit === 9 && $this->parsedPhone->getCountryCode() == 7) {
            $operatorCode = (int)substr($nationalNumber, 0, 3);
            $number = (int)str_replace($operatorCode, '', $nationalNumber);
            $obQuery = new Query(MobilePhoneTimezoneTable::getEntity());
            $obQuery->setFilter([
                '=DEF_CODE' => $operatorCode,
                '<=FROM_CODE' => $number,
                '>=TO_CODE' => $number
            ]);
            $obQuery->setSelect(['TIMEZONE']);
            $dbResult = $obQuery->exec();
            if($arResult = $dbResult->fetch()) {
                return $arResult['TIMEZONE'];
            } else {
                return '';
            }
        } else {
            $timeZoneMapper = \libphonenumber\PhoneNumberToTimeZonesMapper::getInstance();
            $timeZones = $timeZoneMapper->getTimeZonesForNumber($this->parsedPhone);
            return !empty($timeZones) ? $timeZones[0] : '';
        }
    }
}