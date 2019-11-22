<?php

\CJSCore::RegisterExt('moment', [
   'js' => '/local/js/moment.min.js'
]);
\CJSCore::RegisterExt('moment_timezone', [
    'js' => '/local/js/moment.timezone.min.js',
    'rel' => ['moment']
]);
\CJSCore::RegisterExt('crm_phone_timezone',
    [
        'js' => '/local/js/crm_phone_timezone.js',
        'lang' => '/local/js/lang/' . LANGUAGE_ID . '/crm_phone_timezone.js.php',
        'css' => '/local/js/css/crm_phone_timezone.css',
        'rel' => ['moment_timezone']
    ]
);