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
}