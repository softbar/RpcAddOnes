<?
require_once __DIR__ . '/../../RpcTools/libs/rpc_module.inc';
define ( 'MODULEDIR', __DIR__ );
/**
 * @author Xavier
 *
 */
class FritzLog extends IPSRpcModule {
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::Create()
	 */
	public function Create() {
		parent::Create();
		$this->RegisterPropertyString('Filter', '');
		$this->RegisterPropertyInteger('MaxLines', 20);
	}
	/**
	 * 
	 */
	public function RequestUpdate(){
		$this->RunUpdate();
	}
	
	// --------------------------------------------------------------------------------
	protected $timerDef=['ONLINE_INTERVAL'=>[15,'m'],'OFFLINE_INTERVAL'=>[6,'h']];
	protected $requireLogin=[true,true];
	protected $showRefreshButton=true;
	// --------------------------------------------------------------------------------
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::GetDiscoverDeviceOptions()
	 */
	protected function GetDiscoverDeviceOptions(){
		$filter=['DeviceInfo1.GetDeviceLog'	];
		return [OPT_MINIMIZED+OPT_SMALCONFIG,$filter,':49000/tr64desc.xml'];
	}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::GetModuleName()
	 */
	protected function GetModuleName($name,$host){
		return 'FritzLog ('.parse_url($host,PHP_URL_HOST).')';
	}	
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::DoUpdate()
	 */
	protected function DoUpdate(){
		$log=$this->CallApi('DeviceInfo1.GetDeviceLog',[]);
		if($maxLines=$this->ReadPropertyInteger('MaxLines')){
			$log=explode("\n",$log);
			array_splice($log, $maxLines);
			$log=implode("\n",$log);												
		}
		$this->SetValueByIdent('LOGDATA', nl2br($log));
	}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::GetPropDef()
	 */
	protected function GetPropDef($Ident){
		switch($Ident){
 			case $this->prop_names[PROP_LOGDATA]: return [3,'Systemlog','~HTMLBox',0,'',PROP_LOGDATA,0];
		}
	}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::$prop_names
	 * @var array $prop_names
	 */
	protected $prop_names = [PROP_LOGDATA =>'LOGDATA'];
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::UpdateProps()
	 */
	protected function UpdateProps($doApply=true){
		return !$this->SetProps(PROP_LOGDATA,true,$doApply);
	}
	// --------------------------------------------------------------------------------
}
CONST 
	PROP_LOGDATA = 1;

?>