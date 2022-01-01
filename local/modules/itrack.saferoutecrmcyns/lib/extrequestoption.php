<?

namespace Itrack\Saferoutecrmcyns;

use Bitrix\Main\Config\Option
	, Bitrix\Main\Loader, Bitrix\Main\ORM\Data;


class ExtrequestOptionTable extends Data\DataManager{
	
	public static function getTableName()
	{
		return "b_option";
	}

	public static function getMap()
	{
		return  [
            'MODULE_ID' => [
                'data_type' => 'string',
                'primary' => true,
                'title' => 'MODULE_ID',
            ],
            'NAME' => array(
                'data_type' => 'string',
				'primary' => true,
                'title' => 'NAME',
            ),
            'VALUE' => array(
                'data_type' => 'string',
                'title' => 'VALUE',
            )
		
        ];		
	}
}


?>