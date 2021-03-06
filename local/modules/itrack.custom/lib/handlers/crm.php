<?php

namespace iTrack\Custom\Handlers;

use Bitrix\Crm\DealTable;
use Bitrix\Main\Loader;
use iTrack\Custom\Integration\CrmManager;

class Crm
{
    private static $oldAssignedId;
    private static $isFinalStage = false;
    private static $oldFields = [];
    private static $ufWEbinarViewedCountData = [];
    private static $activeHandler = false;

    public static function onBeforeCrmDealUpdate(&$arFields)
    {
        $rsDeal = DealTable::query()
            ->setFilter(['=ID' => $arFields['ID']])
            ->setSelect(['ID','ASSIGNED_BY_ID','STAGE_ID'])
            ->exec();
        $arDeal = $rsDeal->fetch();
        self::$oldFields = $arDeal;
        if(\CCrmDeal::GetSemanticID($arDeal['STAGE_ID']) == \Bitrix\Crm\PhaseSemantics::FAILURE
            || \CCrmDeal::GetSemanticID($arDeal['STAGE_ID']) == \Bitrix\Crm\PhaseSemantics::SUCCESS) {
            self::$isFinalStage = true;
        }
        if(!empty($arDeal['ASSIGNED_BY_ID'])) {
            self::$oldAssignedId = (int)$arDeal['ASSIGNED_BY_ID'];
        }
    }

	public static function funcOnAfterCrmTimelineCommentAdd($ID)
	{

		$checkData = Bitrix\Crm\Timeline\Entity\TimelineTable::getList(
			array(
				'select' => [
					'ID'
					, 'TYPE_ID'
					, 'BINDING.ENTITY_TYPE_ID'
					// ,'BINDING.ENTITY_ID'
					// ,'AUTHOR_ID'
					// ,'COMMENT'
					// ,'SETTINGS'
				]
			, 'order' => ['CREATED' => 'desc']
			, 'filter' => array(
				'=BINDING.ENTITY_TYPE_ID' => $ownerTypeID = CCrmOwnerType::Deal
			, '=ID' => $ID
				// '=BINDING.ENTITY_ID' => $ownerID =999,
				// 'BINDINGS.ENTITY_ID' => 111388
			),
				'runtime' => array(
					new \Bitrix\Main\Entity\ReferenceField(
						'BINDING',
						'\Bitrix\Crm\Timeline\Entity\TimelineBindingTable',
						array("=ref.OWNER_ID" => "this.ID"),
						array("join_type" => "INNER")
					)
				),
				'limit' => 2
			)
		);
		$strIdDeal = $checkData->fetch()['CRM_TIMELINE_ENTITY_TIMELINE_BINDING_ENTITY_ID'];

		if(!empty($strIdDeal)) {
			$currentDbResult = \CCrmDeal::GetList(
				[],
				['=ID' => $strIdDeal, 'CHECK_PERMISSIONS' => 'N'],
				['CONTACT_ID'],
				false
				);
			$strIdContact = $currentDbResult->Fetch()['CONTACT_ID'];

			$fields = array(
				'OWNER_ID' => $ID,
				'ENTITY_TYPE_ID' => CCrmOwnerType::Contact,
				'ENTITY_ID' => $strIdContact,
			);

			$connection = Bitrix\Main\Application::getConnection();
			$queries = $connection->getSqlHelper()->prepareMerge(
				'b_crm_timeline_bind',
				array('OWNER_ID', 'ENTITY_TYPE_ID', 'ENTITY_ID'),
				$fields,
				$fields
			);

			foreach ($queries as $query) {
				$connection->queryExecute($query);
			}
		}

	}

    
    public static function onAfterCrmDealUpdate(&$arFields)
    {
        if(!empty(self::$oldFields)) {
            $arFields['C_OLD_FIELDS'] = self::$oldFields;
            self::$oldFields = [];
        }

        if(static::$activeHandler === true) {
            return;
        }

        if(!empty($arFields['ASSIGNED_BY_ID']) && !empty(self::$oldAssignedId) && (int)$arFields['ASSIGNED_BY_ID'] !== self::$oldAssignedId && !self::$isFinalStage) {
            Loader::includeModule('tasks');

            $rsTasks = \CTasks::GetList(
                [],
                ['UF_CRM_TASK' => 'D_'.$arFields['ID']],
                ['ID','RESPONSIBLE_ID','STATUS','AUDITORS'],
                ['USER_ID' => 1]
            );
            while($arTask = $rsTasks->Fetch()) {
                if((int)$arTask['RESPONSIBLE_ID'] === self::$oldAssignedId) {
                    $obTask = \CTaskItem::getInstance($arTask['ID'], 1);
                    try {
                        if($arTask['STATUS'] < \CTasks::STATE_SUPPOSEDLY_COMPLETED) {
                            $rs = $obTask->update(array("RESPONSIBLE_ID" => $arFields['ASSIGNED_BY_ID']));
                        } else {
                            $rs = $obTask->update(array("AUDITORS" => [$arFields['ASSIGNED_BY_ID']]));
                        }
                    } catch(\Exception $e) {
                        // log ?
                    }
                }
            }

            $task = new \Bitrix\Tasks\Item\Task(0, 1);
            $deadline = new \Bitrix\Main\Type\DateTime();
            $deadline->add('5 hours');
            $task['TITLE'] = '?????? ?????????????????? ?????????????????????????? ???? ????????????. ?????????????????? ?? ????????????????!';
            $task['DESCRIPTION'] = '?????? ?????????????????? ?????????????????????????? ???? ????????????. ?????????????????? ?? ????????????????!';
            $task['RESPONSIBLE_ID'] = $arFields['ASSIGNED_BY_ID'];
            $task['UF_CRM_TASK'] = ['D_'.$arFields['ID']];
            $task['DEADLINE'] = $deadline;
            $task->save();

            self::$oldAssignedId = null;
        }

        $dbDeal = DealTable::query()
            ->where('ID', $arFields['ID'])
            ->setSelect(['ID','UF_CRM_1582269904','UF_CRM_1591020493'])
            ->exec();
        if($arDeal = $dbDeal->fetch()) {

            // ?????????????????? ???? ??????-???? ?????????????????????????? ??????????????????

            self::getWebinarViewedCountUFValues(); // ?????????????? ???????? ???? ?????????????????? ????????????
            $countCurrent = is_array($arDeal['UF_CRM_1582269904']) ? count($arDeal['UF_CRM_1582269904']) : 0;
            if(!empty($arDeal['UF_CRM_1591020493'])) {
                if((int)self::$ufWEbinarViewedCountData['VALUES_REF'][$arDeal['UF_CRM_1591020493']] !== $countCurrent) {
                    $newCount = $countCurrent;
                }
            } else {
                $newCount = $countCurrent;
            }

            if(isset($newCount)) {
                $enumId = null;
                if(empty(self::$ufWEbinarViewedCountData['VALUES'][$newCount])) {
                    // ???????? ???????????? ???????????????? ?? ???????????? ?????? - ?????????????? ??????
                    $obEnum = new \CUserFieldEnum;
                    $dbUf = \CUserTypeEntity::GetList([], ['FIELD_NAME' => 'UF_CRM_1591020493']);
                    if($arUf = $dbUf->Fetch()){
                        $obEnum->SetEnumValues($arUf['ID'], array(
                            "n0" => array(
                                "VALUE" => $newCount,
                            ),
                        ));
                        self::getWebinarViewedCountUFValues();
                    }
                }

                $enumId = self::$ufWEbinarViewedCountData['VALUES'][$newCount];
                if(!empty($enumId)) {
                    $obDeal = new \CCrmDeal(false);
                    $arUpdateFields = ['UF_CRM_1591020493' => $enumId];
                    $arFields['C_OLD_FIELDS']['UF_CRM_1591020493'] = $arDeal['UF_CRM_1582269904'];
                    static::$activeHandler = true;
                    $obDeal->Update($arDeal['ID'], $arUpdateFields);
                    static::$activeHandler = false;
                }
            }
        }

        static::$activeHandler = true;
        CrmManager::handleDealEvent($arFields, CrmManager::DEAL_AFTER_UPDATE);
        static::$activeHandler = false;
    }

    protected static function getWebinarViewedCountUFValues()
    {
        $rsEnum = \CUserFieldEnum::GetList(array(), array("USER_FIELD_NAME" => 'UF_CRM_1591020493'));
        $arValues = $arValuesRef = [];
        while ($arEnum = $rsEnum->Fetch()) {
            $arValues[(int)$arEnum['VALUE']] = $arEnum['ID'];
            $arValuesRef[$arEnum['ID']] = $arEnum['VALUE'];
        }
        self::$ufWEbinarViewedCountData = ['VALUES' => $arValues, 'VALUES_REF' => $arValuesRef];
    }

    public static function onAfterCrmDealAdd(&$arFields)
    {
        if(static::$activeHandler === true) {
            return;
        }

        static::$activeHandler = true;
        CrmManager::handleDealEvent($arFields, CrmManager::DEAL_AFTER_CREATE);
        static::$activeHandler = false;
    }
    
    
    public static function fOnAfterCrmDealAdd($fields){
            /*
                do not use any throw Except in this time
            
                warning !!  $arOptions['CURRENT_USER']=3	VS	SUser->GetID
            
                warning !! $ufDolvnostKompanii, $ufSsilkaNaResume must be exist!!!
            */
            $ufDolvnostKompanii='UF_CRM_1605532823';//'UF_CRM_1605690367';//'UF_CRM_1605532823'; // 'POST';
            $ufSsilkaNaResume='UF_CRM_1605542456';//'UF_CRM_1605690484';//'UF_CRM_1605542456'; //
            $sDiskStorageUser=1;
            $sDiskFolderName='HRManagement';
            $sCategoryDealID=63; //60

if (!empty($fields) and (!is_array($fields))){$fields=[$fields];}
		if (is_array($fields) and (!empty($fields))){
			file_put_contents($_SERVER['DOCUMENT_ROOT'].'/bitrix/fOnAfterCrmDealAdd_1.json', ",".json_encode(['time'=>intval(microtime(true)),'inf'=>$fields]), FILE_APPEND);
		}        

            if (!empty($fields['COMMENTS']) and $fields["CATEGORY_ID"]==$sCategoryDealID)
            {
                
file_put_contents($_SERVER['DOCUMENT_ROOT'].'/bitrix/fOnAfterCrmDealAdd_1_log.json', ",".json_encode(['time'=>intval(microtime(true)),'inf'=>['stroke'=>'228 run','HIDE'=>$fields['COMMENTS']]]), FILE_APPEND);
                
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
                    $ret=explode('<br>????????????????:',$str)[1];
                    $ret=explode('<br>',$ret)[0];
                    $ret=is_array($ret)?'':trim($ret);
                    return $ret;
                    };
                    
                $strVakancy=!empty($sVakancy($incoming))?$sVakancy($incoming):$fields["SOURCE_DESCRIPTION"];	
                $strUlphoto=$sUlphoto($incoming);	
                $strUlResume=$sUlResume($incoming);
                $arFields=[];
                
                if (\Bitrix\Main\Loader::includeModule('wiki')&&\Bitrix\Main\Loader::includeModule('crm'))
                {
                    $strUlphoto=CWikiUtils::htmlspecialchars_decode($strUlphoto);
                    $sContactId=$fields['CONTACT_ID'];
                    $sDealId=$fields['ID'];
                    /*
                    future use
                    $fields['PHOTO']="/abcdef.jpg";
                    $afile=\Bitrix\Main\Application::getDocumentRoot().$fields['PHOTO'];
                    if (\Bitrix\Main\IO\File::isFileExists($afile))
                    {
                        $arFields['PHOTO'] = $afile;
                    }
                    */

                    if (false)
                    {
                        $arFields['PHOTO'] = $arFields['PHOTO']? \CFile::MakeFileArray($strUlphoto): '';
                    }
                   
                    // $strUlphoto="https://hhcdn.ru/photo/580668318.jpeg?t=1605672669&h=ZS5QOtfJcvoWAJE4PtdVeQ";
                    $UrlFileNAME=explode('?',(end(explode('/',$strUlphoto))))[0];	// 580668318.jpeg		
file_put_contents($_SERVER['DOCUMENT_ROOT'].'/bitrix/fOnAfterCrmDealAdd_1_log.json', ",".json_encode(['time'=>intval(microtime(true)),'inf'=>['str'=>281,[$strUlphoto,$UrlFileNAME]]]), FILE_APPEND);                 
                    
                    $arFields[$ufDolvnostKompanii]=$strVakancy;
                    $arFields[$ufSsilkaNaResume]=$strUlResume;
                    $arFields['TITLE']=$strVakancy;
file_put_contents($_SERVER['DOCUMENT_ROOT'].'/bitrix/fOnAfterCrmDealAdd_1_log.json', ",".json_encode(['time'=>intval(microtime(true)),'inf'=>$arFields]), FILE_APPEND);                    
                    $CCrmEntity = new CCrmDeal(false);

                    $res = $CCrmEntity->Update(
                            $sDealId
                            , $arFields
                            ,true,true,$arOptions['CURRENT_USER']=$sDiskStorageUser
                        );
                            if (!$res)
                                // throw new Exception($CCrmEntity->LAST_ERROR);
                                $obj_log_error=($CCrmEntity->LAST_ERROR);			
file_put_contents($_SERVER['DOCUMENT_ROOT'].'/bitrix/fOnAfterCrmDealAdd_1_log.json', ",".json_encode(['time'=>intval(microtime(true)),'inf'=>['res289'=>$res]]), FILE_APPEND); 
                    // var_dump($res);
                    // print_r($sDealId."\n");

                    /*
                    Array
                    (
                        [PHOTO] => 154449
                        [POST] => ownership
                        [~DATE_MODIFY] => now()
                        [MODIFY_BY_ID] => 0
                        [FULL_NAME] => ???????????????? ??????????
                        [ID] => 4940
                    )		
                                */
                    

                    
                    $isDiskEnabled = (
                                \Bitrix\Main\Config\Option::get('disk', 'successfully_converted', false)
                                && CModule::includeModule('disk')
                                    ? True
                                    : False
                            );	
                    // $storage = \Bitrix\Disk\Driver::getInstance()->getStorageByUserId($sDiskStorageUser);
                   
                    if ($isDiskEnabled) 
                    { 
                        $storageID = \Bitrix\Disk\BaseObject::getList(array(
                                    'select' => array("*"),
                                    'filter' => $filter=['=NAME'=> $sDiskFolderName],  
                                ))->Fetch()['ID'];
                        $folder=Bitrix\Disk\BaseObject::loadById($storageID,['STORAGE']);
                        if (empty($folder))
                        {
                            $folder = $storage->getRootObject(); 
                        }

                        $fileArray = \CFile::MakeFileArray($strUlphoto); 
file_put_contents($_SERVER['DOCUMENT_ROOT'].'/bitrix/fOnAfterCrmDealAdd_1_log.json', ",".json_encode(['time'=>intval(microtime(true)),'inf'=>['336fileArray'=>$fileArray]]), FILE_APPEND); 
                        if (!empty($fileArray))
                        {
                            $file =$folder->uploadFile($fileArray, array(  
                                            'CREATED_BY' => $sDiskStorageUser  
                                        )); 
                        }

                        /*
                        
                        warning !!   hh.ru can set state 403 
                        
                        \$fileArray=Array
                        (
                            [name] => 599574120.png
                            [size] => 180232
                            [tmp_name] => docroot/upload/tmp/8f9/tmp.03b395b03651d67c7ae502eb20cd7dba
                            [type] => image/png
                        )
                        
                        
                        18.11.2020 11:00:28	disk	500	375	25673	image/jpeg	disk/5f1	5f1b5b9ace9e4583ddb6342525d208ef
                        18.11.2020 10:57:03	disk	0	0	146		text/html	disk/e65	e654b8e9ddc840c961d2ae467d09f0c8

                        */
                        if (empty($file))
                        {
                            $file = $folder->getChild([
                                '=NAME'=>$UrlFileNAME,
                                'TYPE'=>\Bitrix\Disk\Internals\FileTable::TYPE_FILE
                            ]);
                        }
                        if (get_class($file)=="Bitrix\Disk\File")
                        {
                            $idFileStorage=$file->getId();
                        }
                        
                        $stridFileStorage='';
                        // var_dump($file);
                        // print_r("\nfile at storage:".$file->getId()."\n");
                        if (!empty($idFileStorage))
                        {
                            $stridFileStorage='n'.$idFileStorage;
                        
file_put_contents($_SERVER['DOCUMENT_ROOT'].'/bitrix/fOnAfterCrmDealAdd_1_log.json', ",".json_encode(['time'=>intval(microtime(true)),'inf'=>['380$idFileStorage'=>$idFileStorage]]), FILE_APPEND);                        
                        
                        $entryID = Bitrix\Crm\Timeline\CommentEntry::create(
                            array(
                                'TEXT' => $strMsg = "\n",//$strVakancy." \n ".$strUlResume,
                                'SETTINGS' => ['HAS_FILES' => 'Y'],
                                'AUTHOR_ID' => $sDiskStorageUser,//global$USER->GetID(),
                                'BINDINGS' => [[
                                    'ENTITY_TYPE_ID' => CCrmOwnerType::Deal //CCrmOwnerType::Contact
                                    , 'ENTITY_ID' => $sDealId
                                ]],
                                'FILES'=>array (
                                      0 => $stridFileStorage,//'n136774',
                                    )
                            ));					
                        
                        }
                        
                        
                        
                        
                        
                        
                        
                        
                    } 						
                            
                    
                }


                    
            }
    }

}
