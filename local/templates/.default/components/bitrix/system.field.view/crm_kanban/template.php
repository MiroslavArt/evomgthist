<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
\Bitrix\Main\Page\Asset::getInstance()->addCss('/bitrix/js/crm/css/crm.css');
$publicMode = isset($arParams["PUBLIC_MODE"]) && $arParams["PUBLIC_MODE"] === true;
?><table cellpadding="0" cellspacing="0" class="field_crm"><?
    $_suf = rand(1, 100);
    foreach ($arResult["VALUE"] as $entityType => $arEntity):
        ?><tr><?
        /*if($arParams['PREFIX']):
            ?><td class="field_crm_entity_type">
            <?=GetMessage('CRM_ENTITY_TYPE_'.$entityType)?>:
            </td><?
        endif;*/
        ?><td class="field_crm_entity">
        
        <?
        $links = '';
        $fios = '';
        $first = true;
        $n = 1;
        foreach ($arEntity as $entityId => $entity)
        {
            $links .= !$first ? ', ': '';
            if(!empty($fios) && !$first) {
                $fios .= ', ';
            }

            if ($publicMode)
            {
                ?><?=htmlspecialcharsbx($entity['ENTITY_TITLE'])?><?
            }
            else
            {
                $entityTypeLower = strtolower($entityType);

                if($entityType == 'ORDER')
                {
                    $url = '/bitrix/components/bitrix/crm.order.details/card.ajax.php';
                }
                else
                {
                    $url = '/bitrix/components/bitrix/crm.'.$entityTypeLower.'.show/card.ajax.php';
                }
                $links .= '<a href="'.htmlspecialcharsbx($entity['ENTITY_LINK']).'" target="_blank" bx-tooltip-user-id="'
                    .htmlspecialcharsbx($entityId).'" bx-tooltip-loader="'.htmlspecialcharsbx($url).'" bx-tooltip-classname="crm_balloon'
                    .($entityType == 'LEAD' || $entityType == 'DEAL' ? '_no_photo': '_'.$entityTypeLower).'">'
                    .GetMessage('CRM_ENTITY_TYPE_'.$entityType).' '.(count($arEntity) > 1 ? $n : '')
                    .'</a>';
                if(!empty($entity['CONTACT'])) {
                    foreach($entity['CONTACT'] as $entityContact) {
                        $tooltipUrl = '/bitrix/components/bitrix/crm.contact.show/card.ajax.php';
                        $fios .= '<a href="'.htmlspecialcharsbx($entityContact['ENTITY_LINK']).'" target="_blank" bx-tooltip-user-id="'
                            .htmlspecialcharsbx($entityContact['ID']).'" bx-tooltip-loader="'.htmlspecialcharsbx($tooltipUrl).'" bx-tooltip-classname="crm_balloon_contact">'
                            .$entityContact['ENTITY_TITLE']
                            .'</a>';
                    }
                } else {
                    if (!empty($entity['FIO'])) {
                        $fios .= $entity['FIO'];
                    }
                }
            }
            $n++;
            $first = false;
        };
        ?>
        <?if(!empty($fios)){?>
            <div class="itrack-custom-crm-taskskanban__additional-fields-fio"><?=$fios;?></div>
        <?}?>
        <div><?=$links;?></div>
        </td>
        </tr><?
    endforeach;
    ?></table>