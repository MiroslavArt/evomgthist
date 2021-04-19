<?php

namespace iTrack\Custom\Agents;

class Sendpulse
{
    public static function checkBooksQueue()
    {
        $sendpulseBooksIntegration = \iTrack\Custom\Integration\Sendpulse\Books::getInstance();
        if($sendpulseBooksIntegration->isEnabled()) {
            $sendpulseBooksIntegration->checkQueue();
        }
        return '\iTrack\Custom\Agents\Sendpulse::checkBooksQueue();';
    }

    public static function reindex()
    {
        \Bitrix\Main\Diag\Debug::writeToFile("Begin", "скрипт запущен ".date('H:i:s'), "sendpulse/".date('d.m.Y').".log");
        $sendpulseBooksIntegration = \iTrack\Custom\Integration\Sendpulse\Books::getInstance();
        if($sendpulseBooksIntegration->isEnabled()) {
            $sendpulseBooksIntegration->reIndex();
        }
        \Bitrix\Main\Diag\Debug::writeToFile("End", "скрипт завершен ".date('H:i:s'), "sendpulse/".date('d.m.Y').".log");
        return '\iTrack\Custom\Agents\Sendpulse::reindex();';
    }

    public static function senddailylog()
    {
        $sendFilePath = $_SERVER['DOCUMENT_ROOT']."/sendpulse/".date('d.m.Y', strtotime('yesterday')).".log";

        $totals = [
            'START' => '',
            'FINISH' => '',
            'SCR_UPD' => 0,
            'SCR_STB' => 0,
            'SCR_DEL' => 0,
            'SCR_ADD' => 0,
            'CUR_UPD' => 0,
            'CUR_STB' => 0,
            'CUR_DEL' => 0,
            'CUR_ADD' => 0
        ];

        $lines = file($sendFilePath);

// Осуществим проход массива и выведем содержимое в виде HTML-кода вместе с номерами строк.
        foreach ($lines as $line_num => $line) {
            if(preg_match("/скрипт запущен/", htmlspecialchars($line))) {
                $totals['START'] = htmlspecialchars($line);
            } elseif(preg_match("/скрипт завершен/", htmlspecialchars($line))) {
                $totals['FINISH'] = htmlspecialchars($line);
            } elseif(preg_match("/UPD/", htmlspecialchars($line)) && preg_match("/агентом/", htmlspecialchars($line))) {
                $totals['SCR_UPD']++;
            } elseif(preg_match("/STABLE/", htmlspecialchars($line)) && preg_match("/агентом/", htmlspecialchars($line))) {
                $totals['SCR_STB']++;
            } elseif(preg_match("/добавлен агентом/", htmlspecialchars($line))) {
                $totals['SCR_ADD']++;
            } elseif(preg_match("/удален агентом/", htmlspecialchars($line))) {
                $totals['SCR_DEL']++;
            } elseif(preg_match("/UPD/", htmlspecialchars($line)) && !preg_match("/агентом/", htmlspecialchars($line))) {
                $totals['CUR_UPD']++;
            } elseif(preg_match("/STABLE/", htmlspecialchars($line)) && !preg_match("/агентом/", htmlspecialchars($line))) {
                $totals['CUR_STB']++;
            } elseif(preg_match("/добавлен/", htmlspecialchars($line)) && !preg_match("/агентом/", htmlspecialchars($line))) {
                $totals['CUR_ADD']++;
            } elseif(preg_match("/удален/", htmlspecialchars($line)) && !preg_match("/агентом/", htmlspecialchars($line))) {
                $totals['CUR_DEL']++;
            }			 //echo "Строка #<b>{$line_num}</b> : " . htmlspecialchars($line) . "<br />\n";
        }

        $fileId = \CFile::SaveFile(
            array(
                "name" => date('d.m.Y', strtotime('yesterday')).".log",
                "tmp_name" => $sendFilePath,
                "old_file" => "0",
                "del" => "N",
                "MODULE_ID" => "",
                "description" => "",
            ),
            'sendpulse',
            false,
            false
        );

        $emails = ['marketing1.mow@evomgt.org', 'segay@itrack.ru'];

        foreach ($emails as $email) {
            $eventFields = [
                "EMAIL_TO" => $email,
                "DAY" => date('d.m.Y', strtotime('yesterday')),
                "TEXT1" => $totals['START'].'-'.$totals['FINISH'],
                'TEXT2' => "обработано: ".($totals['SCR_UPD']+$totals['SCR_STB']+$totals['SCR_ADD']+$totals['SCR_DEL']).' записей, из которых:',
                "TEXT3" => "без изменений - ".$totals['SCR_STB'],
                "TEXT4" => "изменен сегмент- ".$totals['SCR_UPD'],
                "TEXT5" => "добавлено- ".$totals['SCR_ADD'],
                "TEXT6" => "удалено- ".$totals['SCR_DEL'],
                "TEXT7" => "на лету обработано: ".($totals['CUR_UPD']+$totals['CUR_STB']+$totals['CUR_ADD']+$totals['CUR_DEL']).' записей, из которых:',
                "TEXT8" => "без изменений - ".$totals['CUR_STB'],
                "TEXT9" => "изменен сегмент- ".$totals['CUR_UPD'],
                "TEXT10" => "добавлено- ".$totals['CUR_ADD'],
                "TEXT11" => "удалено- ".$totals['CUR_DEL'],
            ];

            \CEvent::Send("SENDPULSE_LOG_SEND", 's1', $eventFields, 'N', '', array($fileId));
        }

        return '\iTrack\Custom\Agents\Sendpulse::senddailylog();';

    }



}