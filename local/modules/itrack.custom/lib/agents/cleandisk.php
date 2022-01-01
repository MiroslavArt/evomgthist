<?php
$datebeg = '01.01.2019';
$dateend = '31.12.2019';

\Bitrix\Main\Loader::includeModule('disk');
$resObjects = \Bitrix\Disk\Internals\ObjectTable::getList([
    //'select' => [],
    'filter' => [
        //'ID' => 2405,
        'STORAGE_ID' => 17,
        '<SIZE' => 60000,
        '>CREATE_TIME'=>ConvertDateTime($datebeg, "DD.MM.YYYY")." 00:00:00",
        '<CREATE_TIME'=>ConvertDateTime($dateend, "DD.MM.YYYY")." 23:59:59"
    ]
]);

while ($arObject = $resObjects->Fetch()) {
    if(preg_match("/mp3/", $arObject['NAME'])) {
        echo "<pre>";
        print_r($arObject);
        echo "</pre>";
        echo $arObject["FILE_ID"];
        $deletedBy = 1;

        $diskFile = \Bitrix\Disk\File::load([
            '=FILE_ID' => $arObject["FILE_ID"]
        ]);

        if ( $diskFile instanceof \Bitrix\Disk\BaseObject )
        {
            $result = $diskFile->delete($deletedBy);

            if ( $result )
            {
                echo "Успешно удален";
                die();
            }
            else
            {
                echo "Удаление не произведено";
                die();
            }
        }
        else
        {
            echo "Файл диска не найден";
            die();
        }

    }
}