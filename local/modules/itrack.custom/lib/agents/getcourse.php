<?php


namespace iTrack\Custom\Agents;


use Itrack\Custom\Helpers\Utils;

class Getcourse
{
    public static function checkupdate()
    {
        $apikey = \COption::GetOptionString('itrack.custom', 'getcourse_api_key');
        if (\Bitrix\Main\Loader::includeModule('crm') && $apikey) {
            $queryParams = [
                //'key' => 'bREzyRLW2ssbqVD0juwfPoIvKxZFbRQf9RwwoWdEyIXMNRrBSRxAvbiatfWIwnNUydmVrhtHh9n9vMGcJ7ahyUxXR8vwDnpheafHwZ17Mt8JXQ9iN0dxLCytlDo3mCZB',
                'key' => $apikey,
                'status_changed_at[from]' => date("Y-m-d"), //'2021-06-16', //date("Y-m-d"),//'2021-05-31',
                'status_changed_at[to]' => date("Y-m-d")//'2021-06-16'
            ];

            $url = 'https://evomgtorg.getcourse.ru/pl/api/account/deals?' . http_build_query($queryParams);

            //print_r($url);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $res = curl_exec($ch);
            $res = json_decode($res, true);

            //echo "<pre>";
            //print_r($res);
            //echo "</pre>";
            curl_close($ch);

            if($res['info']['export_id']) {
                $iterexport = 0;
                do{
                    sleep(10);
                    $queryParams = [
                        'key' => $apikey //'bREzyRLW2ssbqVD0juwfPoIvKxZFbRQf9RwwoWdEyIXMNRrBSRxAvbiatfWIwnNUydmVrhtHh9n9vMGcJ7ahyUxXR8vwDnpheafHwZ17Mt8JXQ9iN0dxLCytlDo3mCZB'
                    ];

                    $url = 'https://evomgtorg.getcourse.ru/pl/api/account/exports/'.$res['info']['export_id'].'?' . http_build_query($queryParams);
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                    $res = curl_exec($ch);
                    $res = json_decode($res, true);

                    if($res['info']['items']) {
                        $deal=new \CCrmDeal(false);
                        foreach ($res['info']['items'] as $item) {
                            echo "<pre>";
                            print_r($item);
                            echo "</pre>";
                            $arFilter = array(
                                "UF_CRM_1623913964"=>$item[0], //???????????????? ???????????????????????? ???????????? ???? ID
                                "CHECK_PERMISSIONS"=>"N" //???? ?????????????????? ?????????? ?????????????? ???????????????? ????????????????????????
                            );
                            $arSelect = array(
                                "ID",
                                "STAGE_ID",
                                "UF_*"
                            );
                            $resd = \CCrmDeal::GetListEx(Array(), $arFilter, false, false, $arSelect);
                            $newdeal = true;
                            $stageid = "C54:NEW";
                            if($item[9]=="????????????????" && $item[7]) {
                                $stageid = "C54:4";
                            } elseif($item[9]=="????????????????" && !$item[7]) {
                                $stageid = "C54:LOSE";
                            }

                            while($arResdeal = $resd->Fetch()) {
                                $newdeal = false;
                                $arParams = array();
                                if($item[9]=="??????????" && $arResdeal["STAGE_ID"]!=$stageid) {
                                    $arParams["STAGE_ID"]=$stageid;
                                } elseif($item[9]=="????????????????" && $item[7] && $arResdeal["STAGE_ID"]!=$stageid) {
                                    $arParams['STAGE_ID']=$stageid;
                                } elseif($item[9]=="????????????????" && !$item[7] && $arResdeal["STAGE_ID"]!=$stageid) {
                                    $arParams["STAGE_ID"]=$stageid;
                                }
                                if($arParams) {
                                    $resdu = $deal->Update($arResdeal['ID'],$arParams);
                                }
                            }
                            if($newdeal) {
                                $dbResultContact = [];
                                $emailv = $item[4];
                                $phonev = $item[5];
                                $cnt = [];
                                if($emailv) {
                                    $cnt = \CCrmFieldMulti::GetList(
                                        array("ID" => "asc"),
                                        array(
                                            "ENTITY_ID" => "CONTACT",
                                            "TYPE_ID" => "EMAIL",
                                            "VALUE" => $emailv
                                        )
                                    )->Fetch();
                                }

                                if($phonev && !$cnt) {
                                    $cnt = \CCrmFieldMulti::GetList(
                                        array("ID" => "asc"),
                                        array(
                                            "ENTITY_ID" => "CONTACT",
                                            "TYPE_ID" => "PHONE",
                                            "VALUE" => $phonev
                                        )
                                    )->Fetch();
                                }

                                if(!$cnt['ELEMENT_ID']) {
                                    $ct=new \CCrmContact(false);
                                    $arParams = array();
                                    if($item[5]) {
                                        $arParams = array("HAS_PHONE"=>"Y");
                                        $arParams["FM"]["PHONE"] = array(
                                            "n0" => array(
                                                "VALUE_TYPE" => "WORK",
                                                "VALUE" => $item[5],
                                            )
                                        );
                                    }
                                    if($item[4]) {
                                        $arParams = array("HAS_EMAIL"=>"Y");
                                        $arParams["FM"]["EMAIL"] = array(
                                            "n0" => array(
                                                "VALUE_TYPE" => "WORK",
                                                "VALUE" => $item[4]
                                            )
                                        );
                                    }
                                    $arParams["NAME"]=$item[3];
                                    $arParams["TYPE_ID"] ='CLIENT';
                                    $arParams["OPENED"] = 'Y';
                                    $arParams["ASSIGNED_BY_ID"] = 22;
                                    $cnt['ELEMENT_ID']=$ct->Add($arParams, true, array('DISABLE_USER_FIELD_CHECK' => true));
                                }

                                $arFields = array(
                                    "TITLE" => "online.evomgt.org",
                                    "ASSIGNED_BY_ID" => 22,
                                    "STAGE_ID" => $stageid,
                                    "CONTACT_ID" => $cnt['ELEMENT_ID'],
                                    'OPPORTUNITY' => $item[10],
                                    "UF_CRM_1623913964" => $item[0],
                                    "COMMENTS" => $item[8],
                                    "CATEGORY_ID" => "54",
                                    "UTM_SOURCE" => $item[31],
                                    "UTM_MEDIUM" => $item[32],
                                    "UTM_CAMPAIGN" => $item[33],
                                    "UTM_CONTENT" => $item[34]
                                );
                                $options = array("CURRENT_USER"=>22); //???? ?????? ????????????
                                $dealid = $deal->Add($arFields,true,$options);
                                echo "<pre>";
                                print_r($dealid);
                                echo "</pre>";
                            }
                        }
                    }
                    if($res['info']['items'] || $iterexport>10) {
                        echo "break";
                        break;
                    }
                    $iterexport++;
                    curl_close($ch);
                } while (0);
            }
        }
        return '\iTrack\Custom\Agents\Getcourse::checkupdate();';
    }

    public static function checkupdatestudy()
    {
        \Bitrix\Main\Loader::includeModule('crm');
        $deal=new \CCrmDeal(false);
        define("MY_HL_BLOCK_ID", 2);
        $entity_data_class = Utils::GetEntityDataClass(MY_HL_BLOCK_ID);
        $rsData = $entity_data_class::getList(array(
            'select' => array('*'),
            'order' => array('ID' => 'ASC'),
            //'limit' => '10',//???????????????????????? ?????????????? 10-?? ????????????????????
            'filter' => array('UF_PROCESSED' => '0')
        ));
        while($el = $rsData->fetch()){
            $arFilter = array(
                "UF_CRM_1623913964"=>$el['UF_ORDER'], //???????????????? ???????????????????????? ???????????? ???? ID
                "CHECK_PERMISSIONS"=>"N" //???? ?????????????????? ?????????? ?????????????? ???????????????? ????????????????????????
            );
            $arSelect = array(
                "ID",
                "STAGE_ID",
                "UF_*"
            );
            $res = \CCrmDeal::GetListEx(Array(), $arFilter, false, false, $arSelect);
            $arDeal = $res->fetch();
            if($arDeal) {
                $arParams = [];
                if($el['UF_STATUS']=='began' && $arDeal['STAGE_ID']!='C54:4') {
                    $arParams["STAGE_ID"]='C54:4';
                } elseif($el['UF_STATUS']=='60pers' && $arDeal['STAGE_ID']!='C54:6') {
                    $arParams["STAGE_ID"]='C54:6';
                } elseif($el['UF_STATUS']=='completed' && $arDeal['STAGE_ID']!='C54:WON') {
                    $arParams["STAGE_ID"]='C54:WON';
                } elseif($el['UF_STATUS']=='failed' && $arDeal['STAGE_ID']!='C54:5') {
                    $arParams["STAGE_ID"]='C54:5';
                }
                if($arParams) {
                    $res = $deal->Update($arDeal['ID'],$arParams);
                }
                $result = $entity_data_class::update($el['ID'], array(
                    'UF_PROCESSED'       => '1'
                ));
            }

        }
        return '\iTrack\Custom\Agents\Getcourse::checkupdatestudy();';
    }
}

