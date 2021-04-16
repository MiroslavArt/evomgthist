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
        $sendpulseBooksIntegration = \iTrack\Custom\Integration\Sendpulse\Books::getInstance();
        if($sendpulseBooksIntegration->isEnabled()) {
            $sendpulseBooksIntegration->reIndex();
        }
        return '\iTrack\Custom\Agents\Sendpulse::reindex();';
    }

    public static function senddailylog()
    {

        $sendFilePath = $_SERVER['DOCUMENT_ROOT']."/sendpulse/".date('d.m.Y').".log";

        $fileId = \CFile::SaveFile(
            array(
                "name" => date('d.m.Y').".log",
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
                "DAY" => date('d.m.Y')
            ];
            \CEvent::Send('SENDPULSE_LOG_SEND', SITE_ID, $eventFields, 'N','',array($fileId));
        }

        return '\iTrack\Custom\Agents\Sendpulse::senddailylog();';
    }



}