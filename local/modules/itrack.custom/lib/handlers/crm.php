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
				false,
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
            $task['TITLE'] = 'Вас назначили ответственным за сделку. Свяжитесь с клиентом!';
            $task['DESCRIPTION'] = 'Вас назначили ответственным за сделку. Свяжитесь с клиентом!';
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

            // установка ПП кол-во просмотренных вебинаров

            self::getWebinarViewedCountUFValues(); // получим инфу по значениям списка
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
                    // если такого значения в списке нет - добавим его
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
                    $obDeal->Update($arDeal['ID'], $arUpdateFields);
                }
            }
        }

        CrmManager::handleDealEvent($arFields, CrmManager::DEAL_AFTER_UPDATE);
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
        CrmManager::handleDealEvent($arFields, CrmManager::DEAL_AFTER_CREATE);
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
        $sDiskFolderName='Файлы приложений';
        $sCategoryDealID=63; //60
        

        if (!empty($fields['COMMENTS']) and $fields["CATEGORY_ID"]==$sCategoryDealID)
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
                
                
                $arFields[$ufDolvnostKompanii]=$strVakancy;
                $arFields[$ufSsilkaNaResume]=$strUlResume;
                $arFields['TITLE']=$strVakancy;
                
                $CCrmEntity = new CCrmDeal(false);

                $res = $CCrmEntity->Update(
                        $sDealId
                        , $arFields
                        ,true,true,$arOptions['CURRENT_USER']=$sDiskStorageUser
                    );
                        if (!$res)
                            // throw new Exception($CCrmEntity->LAST_ERROR);
                            $obj_log_error=($CCrmEntity->LAST_ERROR);			
                // var_dump($res);
                // print_r($sDealId."\n");
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
                

                
                $isDiskEnabled = (
                            \Bitrix\Main\Config\Option::get('disk', 'successfully_converted', false)
                            && CModule::includeModule('disk')
                                ? True
                                : False
                        );	
                $storage = \Bitrix\Disk\Driver::getInstance()->getStorageByUserId($sDiskStorageUser); 
                if ($storage&&$isDiskEnabled) 
                { 
                    $folder = $storage->getChild( 
                        array( 
                            '=NAME' => $sDiskFolderName,  
                            'TYPE' => \Bitrix\Disk\Internals\FolderTable::TYPE_FOLDER 
                        ) 
                    ); 
                    if (empty($folder))
                    {
                        $folder = $storage->getRootObject(); 
                    }
                    $fileArray = \CFile::MakeFileArray($strUlphoto); 
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

                    $file = $folder->uploadFile($fileArray, array(  
                        'CREATED_BY' => $sDiskStorageUser  
                    ));
                    if (empty($file))
                    {
                        $file = $folder->getChild([
                            '=NAME'=>$UrlFileNAME,
                            'TYPE'=>\Bitrix\Disk\Internals\FileTable::TYPE_FILE
                        ]);
                    }
                    
                    // var_dump($file);
                    // print_r("\nfile at storage:".$file->getId()."\n");
                    $stridFileStorage='';
                    $idFileStorage=$file->getId();
                    if (!empty($idFileStorage))
                    {
                        $stridFileStorage='n'.$idFileStorage;
                    }
                    
                    
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