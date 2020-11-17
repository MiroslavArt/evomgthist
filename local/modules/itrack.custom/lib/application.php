<?php

namespace iTrack\Custom;

use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\Page\Asset;

function fOnAfterCrmDealAdd($fields){
	/*
		do not use any throw Except in this time
	
		warning !!  $arOptions['CURRENT_USER']=3	VS	SUser->GetID
	
		warning !! $ufDolvnostKompanii, $ufSsilkaNaResume must be exist!!!
	*/
	$ufDolvnostKompanii='UF_CRM_1605532823'; // 'POST';
	$ufSsilkaNaResume='UF_CRM_1605542456'; //
	

	if (!empty($fields['COMMENTS']))
	{
		$incoming=$fields['COMMENTS'];
		$sUlResume=function($str){
			$retUrl='';
			if(preg_match("|<a.*(?=href=\"([^\"]*)\")[^>]*>([^<]*)</a>|i", $str, $matches)){
				$retUrl=$matches[1];
				}
			return $retUrl;
			};
		$sUlphoto=function($str){
			$retUrl='';
			if(preg_match("|<img.*(?=src=\"([^\"]*)\")[^>]*>([^<]*)|i", $str, $matches)){
				$retUrl=$matches[1];
				}
			return $retUrl;
			};
			
		$sVakancy=function($str){
			$retUrl='';
			$ret=explode('<br>Вакансия:',$str)[1];
			$ret=explode('<br>',$ret)[0];
			$ret=is_array($ret)?'':trim($ret);
			return $ret;
			};
			
		$strVakancy=!empty($sVakancy($incoming))?$sVakancy($incoming):$fields["SOURCE_DESCRIPTION"];	
		$strUlphoto=$sUlphoto($incoming);	
		$strUlResume=$sUlResume($incoming);
		$arFields=[];
		
		if (\Bitrix\Main\Loader::includeModule('wiki')&&\Bitrix\Main\Loader::includeModule('crm')&&!empty($fields['CONTACT_ID']))
		{
			$strUlphoto=CWikiUtils::htmlspecialchars_decode($strUlphoto);
			$sContactId=$fields['CONTACT_ID'];
			/*
			future use
			$fields['PHOTO']="/abcdef.jpg";
			$afile=\Bitrix\Main\Application::getDocumentRoot().$fields['PHOTO'];
			if (\Bitrix\Main\IO\File::isFileExists($afile))
			{
				$arFields['PHOTO'] = $afile;
			}
			*/
			$arFields['PHOTO'] = $arFields['PHOTO']? \CFile::MakeFileArray($strUlphoto): '';
			$arFields[$ufDolvnostKompanii]=$strVakancy;
			$arFields[$ufSsilkaNaResume]=$strUlResume;
			
			$CCrmEntity = new CCrmContact(false);

			$res = $CCrmEntity->Update(
					$sContactId
					, $arFields
					,true,true,$arOptions['CURRENT_USER']=3
				);
					if (!$res)
						// throw new Exception($CCrmEntity->LAST_ERROR);
						$obj_log_error=($CCrmEntity->LAST_ERROR);			
			// var_dump($res);
			// print_r($arFields);
			/*
			Array
			(
				[PHOTO] => 154449
				[POST] => ownership
				[~DATE_MODIFY] => now()
				[MODIFY_BY_ID] => 0
				[FULL_NAME] => Морозова Ольга
				[ID] => 4940
			)		
						*/
			
			
		}


			
	}
}

class Application
{
    const DEAL_CATEGORY_EDUCATION_ID = 47;
    const DEAL_CATEGORY_WEBINAR_ID = 50;
    const DEAL_CATEGORY_ONLINE_PRACTICE_ID = 3;
    const DEAL_CATEGORY_SEMINAR_PRACTICE_ID = 1;
    const DEAL_CATEGORY_BE4MSK_ID = 30;
    const DEAL_CATEGORY_PROJECT_BE_ID = 56;

    public static function init()
    {
        self::initJsHandlers();
        self::initEventHandlers();
    }

    protected static function initJsHandlers()
    {

    }

    public static function initEventHandlers()
    {
        $eventManager = EventManager::getInstance();

        $eventManager->addEventHandler('tasks','OnTaskAdd', ['\iTrack\Custom\Handlers\Tasks','onTaskAdd']);
        $eventManager->addEventHandler('crm','OnBeforeCrmDealUpdate', ['\iTrack\Custom\Handlers\Crm','onBeforeCrmDealUpdate']);
        $eventManager->addEventHandler('crm','OnAfterCrmDealUpdate', ['\iTrack\Custom\Handlers\Crm','onAfterCrmDealUpdate']);
        $eventManager->addEventHandler('crm','OnAfterCrmDealAdd', ['\iTrack\Custom\Handlers\Crm','onAfterCrmDealAdd']);
        $eventManager->addEventHandler('main','OnProlog', ['\iTrack\Custom\Handlers\Main','onProlog']);
        $eventManager->addEventHandler('main','OnEpilog', ['\iTrack\Custom\Handlers\Main','onEpilog']);
        $eventManager->addEventHandler('im','OnBeforeMessageNotifyAdd', ['\iTrack\Custom\Handlers\Im','onBeforeMessageNotifyAdd']);
        // $eventManager->addEventHandler("crm", "OnAfterCrmTimelineCommentAdd", ['\iTrack\Custom\Handlers\Crm','funcOnAfterCrmTimelineCommentAdd']);
        


    }

    public static function log($msg, $file = 'main.log')
    {
        file_put_contents($_SERVER['DOCUMENT_ROOT'].'/local/logs/'.$file, date(DATE_COOKIE).': '.$msg."\n", FILE_APPEND);
    }
}