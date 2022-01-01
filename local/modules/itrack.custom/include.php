<?php
\CJSCore::RegisterExt('itrack_crm_styles_ext', [
    'css' => '/local/js/css/itrack_crm_styles_ext.css'
]);
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
\CJSCore::RegisterExt('itrack_crm_contact_widget_ext',
    [
        'js' => '/local/js/itrack_crm_contact_widget_ext.js',
        'css' => '/local/js/css/itrack_crm_contact_widget_ext.css'
    ]
);
\CJSCore::RegisterExt('itrack_crm_tasks_kanban_ext',
    [
        'js' => '/local/js/itrack_crm_tasks_kanban_ext.js',
        'css' => '/local/js/css/itrack_crm_tasks_kanban_ext.css'
    ]
);
\CJSCore::RegisterExt('itrack_crm_detail_editor_ext',
    [
        'js' => '/local/js/itrack_crm_detail_editor_ext.js'
        //'css' => '/local/js/css/itrack_crm_detail_editor_ext.css'
    ]
);
