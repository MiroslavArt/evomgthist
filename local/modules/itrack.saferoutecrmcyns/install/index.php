<?
use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use \Bitrix\Main\Config as Conf;
use \Bitrix\Main\Config\Option;
use \Bitrix\Main\Entity\Base;

Loc::loadMessages(__FILE__);

Class itrack_saferoutecrmcyns extends CModule{

  public $options;
	var $MODULE_ID = 'itrack.saferoutecrmcyns';
	var $MODULE_VERSION = "1.2.3";
	var $MODULE_VERSION_DATE = "2020-11-09";
	var $MODULE_NAME = "";
	var $MODULE_DESCRIPTION = "";
	var $MODULE_CSS;
	var $MODULE_GROUP_RIGHTS = "N";
	

  function __construct(){
    $arModuleVersion = array();
    include(__DIR__."/version.php");
    
		$this->MODULE_ID = 'itrack.saferoutecrmcyns';
		$this->MODULE_VERSION = $arModuleVersion["VERSION"];
		$this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
		$this->MODULE_NAME = Loc::getMessage("ITRACK_SAFEROUTECRMCYNS_MODULE_NAME");
		$this->MODULE_DESCRIPTION = Loc::getMessage("ITRACK_SAFEROUTECRMCYNS_MODULE_DESC");

		$this->PARTNER_NAME = Loc::getMessage("ITRACK_SAFEROUTECRMCYNS_PARTNER_NAME");
		$this->PARTNER_URI = Loc::getMessage("ITRACK_SAFEROUTECRMCYNS_PARTNER_URI");

    $this->MODULE_SORT = 1;
    $this->SHOW_SUPER_ADMIN_GROUP_RIGHTS='Y';
    $this->MODULE_GROUP_RIGHTS = "Y";

    $this->options = [
      'login_ie' => Option::get($this->MODULE_ID, "login_ie"),
      'password_ie' => Option::get($this->MODULE_ID, "password_ie"),
      'invoice_prop' => Option::get($this->MODULE_ID, "invoice_prop")
    ];
  }

  public function GetPath($notDocumentRoot=false)
  {
      if($notDocumentRoot)
          return str_ireplace(Application::getDocumentRoot(),'',dirname(__DIR__));
      else
          return dirname(__DIR__);
  }

  //Проверяем что система поддерживает D7
  public function isVersionD7()
  {
    return CheckVersion(\Bitrix\Main\ModuleManager::getVersion('main'), '14.00.00');
  }

  function InstallFiles($arParams = array())
  {     
    return true;
  }
  
  function UnInstallFiles()
  {
		return true;
  }
  
  function InstallAgent(){
      AddMessage2Log("InstallAgent 1: ", "SyncCreate");
    CAgent::AddAgent(
      "\\Itrack\\Saferoutecrmcyns\\Common::syncHoockDeals();", 
      $this->MODULE_ID,                          
      "N",                                          // агент не критичен к кол-ву запусков                 
      60,                                           // интервал запуска, сек
      date("d.m.Y H:i", strtotime("+1 min")),       // дата первой проверки на запуск           
      "Y",                                          // агент активен
      date("d.m.Y H:i", strtotime("+1 min")),       // дата первого запуска
      30
    );
  }

  function UnInstallAgent(){
    CAgent::RemoveAgent("\\Itrack\\Saferoutecrmcyns\\Common::syncHoockDeals();", $this->MODULE_ID);
  }

  function InstallEvent() {
    // EventManager::getInstance()->registerEventHandler('crm', 'OnAfterCrmInvoiceAdd', $this->MODULE_ID, '\Itrack\Saferoutecrmcyns\Common', 'InvoiceSyncCreate');
  }

  function UnInstallEvent() {
    // EventManager::getInstance()->unRegisterEventHandler('crm', 'OnAfterCrmInvoiceAdd', $this->MODULE_ID, '\Itrack\Saferoutecrmcyns\Common', 'InvoiceSyncCreate');
  }

  function DoInstall()
  {
	global $APPLICATION;
    if($this->isVersionD7() && in_array('curl', get_loaded_extensions()))
    {
      \Bitrix\Main\ModuleManager::registerModule($this->MODULE_ID);

      // $this->InstallDB();
      $this->InstallEvent();
      $this->InstallAgent();
      $this->InstallFiles();
    }
    else
    {
        if(!in_array('curl', get_loaded_extensions()))
            $APPLICATION->ThrowException(Loc::getMessage("ITRACK_SAFEROUTECRMCYNS_INSTALL_ERROR_CURL"));
        else
            $APPLICATION->ThrowException(Loc::getMessage("ITRACK_SAFEROUTECRMCYNS_INSTALL_ERROR_VERSION"));
    }

    $APPLICATION->IncludeAdminFile(Loc::getMessage("ITRACK_SAFEROUTECRMCYNS_INSTALL_TITLE"), $this->GetPath()."/install/step.php");
  }

  function DoUninstall()
  {
    global $APPLICATION;

    $context = Application::getInstance()->getContext();
    $request = $context->getRequest();

    $this->UnInstallEvent();
    $this->UnInstallAgent();
    $this->UnInstallFiles();

    \Bitrix\Main\ModuleManager::unRegisterModule($this->MODULE_ID);

    $APPLICATION->IncludeAdminFile(Loc::getMessage("ITRACK_SAFEROUTECRMCYNS_UNINSTALL_TITLE"), $this->GetPath()."/install/unstep1.php");
  }
  
  function GetModuleRightList()
  {
    return array(
      "reference_id" => array("D","K","S","W"),
      "reference" => array(
          "[D] ".Loc::getMessage("ITRACK_SAFEROUTECRMCYNS_DENIED"),
          "[K] ".Loc::getMessage("ITRACK_SAFEROUTECRMCYNS_READ_COMPONENT"),
          "[S] ".Loc::getMessage("ITRACK_SAFEROUTECRMCYNS_WRITE_SETTINGS"),
          "[W] ".Loc::getMessage("ITRACK_SAFEROUTECRMCYNS_FULL"))
    );
  }

  
}

?>