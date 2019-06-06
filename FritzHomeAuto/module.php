<?
require_once __DIR__ . '/../../RpcTools/libs/rpc_module.inc';
// define ( 'MODULEDIR', __DIR__ );

/**
 * @author Xavier
 *
 */
class FritzHomeAuto extends IPSRpcModule {
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::Create()
	 */
	public function Create() {
		parent::Create();
		$this->RegisterPropertyString('AIN', '');
		$this->RegisterPropertyString('Name', '');
		$this->RegisterPropertyBoolean('EnableSwitchAction', true);
	}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::Destroy()
	 */
	public function Destroy() {
		parent::Destroy();
		if($this->IsLastInstance('{5638FDC0-C112-4108-DE05-201905120FHA}')){
			if(IPS_VariableProfileExists('RPC_PowerW'))IPS_DeleteVariableProfile('RPC_PowerW');
		}
	}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::GetConfigurationForm()
	 */
	public function GetConfigurationForm() {
  		$f=json_decode(parent::GetConfigurationForm(),true);
		$options=json_decode($this->GetBuffer('DEVICE_OPTIONS'),true);
  		if(!is_array($options) || count($options)==0 )
  			$f['elements'][]=['type'=>'ValidationTextBox','name'=>'AIN', 'caption'=>'Device AIN'];
  		else $f['elements'][]=['type'=>'Select','name'=>'AIN', 'caption'=>'Device AIN', 'options'=>$options];
 		$f['elements'][]=["name"=>"EnableSwitchAction","type"=>"CheckBox", "caption"=>"Enable Switch Action"];
 
  		$f['status'][]=["code"=>204, "icon"=>"error", "caption"=>"Device AIN missing or invalid"];
  		return json_encode($f);
	}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::RequestAction()
	 */
	public function RequestAction($Ident, $Value){
		if(parent::RequestAction($Ident, $Value))return true;
		if($Ident=='SWITCH'){
			$this->DoSwitch((bool)$Value);
		}
	}
	/**
	 * 
	 */
	public function RequestUpdate(){
		$this->RunUpdate();
	}
	// --------------------------------------------------------------------------------
	protected $timerDef=['ONLINE_INTERVAL'=>[5,'m'],'OFFLINE_INTERVAL'=>[2,'h']];
	protected $requireLogin=[true,true];
	protected $showRefreshButton=true;
	// --------------------------------------------------------------------------------
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::ValidateConfiguration()
	 */
	protected function ValidateConfiguration(){
		if(!parent::ValidateConfiguration())return false;
		if(empty($this->ReadPropertyString('AIN'))){
  			$this->BuildDeviceAINOptionsBuffer();
			$this->SetStatus(204);
			return false;
		} 
 		if(@$this->GetIDForIdent($this->prop_names[PROP_SWITCH]))		
			$this->MaintainAction($this->prop_names[PROP_SWITCH], $this->ReadPropertyBoolean('EnableSwitchAction'));
		if(empty($this->ReadPropertyString('Name'))){
			if($this->updateData(false)){
				$this->SetBuffer('DO_SKIP',1);
			}
			
		}
		return true;
	}
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::GetDiscoverDeviceOptions()
	 */
	protected function GetDiscoverDeviceOptions(){
		$filter=['X_AVM-DE_Homeauto1.*','DeviceConfig1.X_AVM-DE_CreateUrlSID'];
		return [OPT_SMALCONFIG+OPT_MINIMIZED,$filter,':49000/tr64desc.xml'];		
	}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::GetModuleName()
	 */
	protected function GetModuleName($name,$host){
		return 'FritzHomeAuto ('.parse_url($host,PHP_URL_HOST).')';
	}	
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::DoUpdate()
	 */
	protected function DoUpdate(){
		if($this->GetBuffer('DO_SKIP')==1){
			$this->SetBuffer('DO_SKIP',0);
		}else $this->updateData(true);
	}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::CreateMissedProfile()
	 */
	protected function CreateMissedProfile($name){
		if($name=='RPC_PowerW'){
			UTILS::RegisterProfileFloat('RPC_PowerW', 'Electricity', '', ' W', 0, 0, 1);
			return true;
		}
	}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::GetPropDef()
	 */
	protected function GetPropDef($Ident){
		switch($Ident){
 			case $this->prop_names[PROP_SWITCH]: return [0,'Power','~Switch',0,'Power',PROP_SWITCH,(int)$this->ReadPropertyBoolean('EnableSwitchAction')];
			case $this->prop_names[PROP_TEMP]	: return [2,'Temperature','~Temperature',0,'',PROP_TEMP,0];
			case $this->prop_names[PROP_IST]: return [2,'Current Temperature','~Temperature',0,'',PROP_IST,0];
			case $this->prop_names[PROP_SOLL]: return [2,'Set Temperature','~Temperature',0,'',PROP_SOLL,0];  
			case $this->prop_names[PROP_REDUCED]: return [2,'Reduced Temperature','~Temperature',0,'',PROP_REDUCED,0];
			case $this->prop_names[PROP_COMFORT]: return [2,'Comfort Temperature','~Temperature',0,'',PROP_COMFORT,0];
			case $this->prop_names[PROP_APOWER]: return [2,'Current Power','RPC_PowerW',0,'',PROP_APOWER,0];
			case $this->prop_names[PROP_TPOWER]: return [2,'Total Power','~Power',0,'',PROP_TPOWER,0];
		}
	}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::$prop_names
	 * @var array $prop_names
	 */
	protected $prop_names = [PROP_SWITCH=>'SWITCH',PROP_TEMP=>'TEMP',PROP_IST=>'IST',PROP_SOLL=>'SOLL',PROP_REDUCED=>'REDUCED',PROP_COMFORT=>'COMFORT',PROP_APOWER=>'APOWER',PROP_TPOWER=>'TPOWER'];	
	// --------------------------------------------------------------------------------
	private function DoSwitch($Value){
		if($this->GetDeviceState()<2 && $this->CheckOnline()){
			if($this->CallApi('X_AVM-DE_Homeauto1.SetSwitch',[$this->ReadPropertyString('AIN'),$Value?'ON':'OFF'])){
				$this->SetValueByIdent('SWITCH', $Value);
			}
		}
	}
	private function BuildDeviceAINOptionsBuffer(){
		$options=[];
		while($r=$this->CallApi('X_AVM-DE_Homeauto1.GetGenericDeviceInfos',[count($options)]))
			$options[]=['value'=>$r['NewAIN'],'label'=>$r['NewDeviceName'].' '.$r['NewProductName']];
		if(count($options)==0)$options[]=['value'=>'','label'=>'no Device found'];
		$this->SetBuffer('DEVICE_OPTIONS',json_encode($options));
	}
	private function updateData($doApply){
		$ain=$this->ReadPropertyString('AIN');
		if(empty($ain) || !($device=$this->CallApi('X_AVM-DE_Homeauto1.GetSpecificDeviceInfos',[$ain]))
		) return false;
//   'NewAIN' => '08761 0004638',
//   'NewDeviceId' => '16',
//   'NewFunctionBitMask' => '896',
//   'NewFirmwareVersion' => '04.16',
//   'NewManufacturer' => 'AVM',
//   'NewProductName' => 'FRITZ!DECT 200',
//   'NewDeviceName' => 'EDV-Keller',
//   'NewPresent' => 'CONNECTED',
// 		$this->SendDebug(__FUNCTION__, print_r($device,true), 0);	
// 		if (empty ( $device ['NewAIN'] )) return;
		$pname = ( string ) $device['NewProductName'];
		$name = ( string ) $device['NewDeviceName'];
		$enc = mb_internal_encoding ();
		if ($enc == 'ISO-8859-1') {
			$name = mb_convert_encoding ( $name, 'ISO-8859-1', 'UTF-8' );
		}
		$name = $pname." ($name)";
	
		$save= $doApply;//!IPS_HasChanges($this->InstanceID);
		$save_changes=false;
		if(empty($this->ReadPropertyString('Name'))){
			IPS_SetProperty($this->InstanceID, 'Name', $name);
			IPS_SetName($this->InstanceID, $name);
			$save_changes=true;
		}
		
		$conn = ($device['NewPresent'] == 'CONNECTED');
		if (! $conn) {
			$this->SendDebug ( __FUNCTION__, "Device $name not connected, skip", 0 );
			if($save && $save_changes)IPS_ApplyChanges($this->InstanceID);
			return false;
		}
		$data=[];$props=0;
		if($device['NewMultimeterIsEnabled']=='ENABLED'){
//   'NewMultimeterIsEnabled' => 'ENABLED',
//   'NewMultimeterIsValid' => 'VALID',
//   'NewMultimeterPower' => '5729',
//   'NewMultimeterEnergy' => '2208526',
            $power   = (integer)$device['NewMultimeterPower'];
            $actual  = $power / 100; //mW->W
            $counter = (integer)$device['NewMultimeterEnergy'];
            $new_total = $counter / 10000;//Wh ->KWh
            $prop=PROP_APOWER+PROP_TPOWER;//+PROP_COUNTER;
            $props = $props | $prop;//array_merge($props,explode(';',strtoupper($caps)));
//             $data['COUNTER'] = $counter;
            $data['APOWER'] = $actual;
            $data['TPOWER'] = $new_total;
            $txt = " Power(Act:" . $actual . " W, Power: $power mW, Counter:$new_total); ";
            $this->SendDebug(__FUNCTION__ , $txt,0);
		}
		if($device['NewTemperatureIsEnabled']=='ENABLED'){
			$temperatur = ((integer)$device['NewTemperatureCelsius']) / 10;
             /* offset is already added */
            $offset = ((integer)$device['NewTemperatureOffset']) / 10;
            $prop=PROP_TEMP;
            $props = $props | $prop;
            $data['TEMP'] = $temperatur;
            $txt = sprintf(" Temperature:(Temp:%02.1fC, Offset:%02.1f) ;", $temperatur, $offset);
            $this->SendDebug(__FUNCTION__ , $txt,0);
		}
		if($device['NewSwitchIsEnabled']=='ENABLED'){
			$status = (string)$device['NewSwitchState'];
            $status = ($status == "1");
            $prop=PROP_SWITCH;
            $props = $props|$prop;// array_merge($props,explode(';',strtoupper($caps)));
            $data['SWITCH'] = $status;
            $txt = " Switch(Status:" . ($status ? "On" : "Off") . "); ";
            $this->SendDebug(__FUNCTION__ , $txt,0);
		}
		if($device['NewHkrIsEnabled']=='ENABLED'){
                    //data available
                    //values are 0.5C steps 16-56 or 253(On) or 254(off)
            $tist = $device['NewHkrIsTemperature'] / 2;
            $komfort = $device['NewHkrComfortTemperature'] / 2;
            $absenk = $device['NewHkrReduceTemperature'] / 2;
            $tsoll = $device['NewHkrSetTemperature'];
            if (($tsoll >= 16) && ($tsoll <= 56)) {
               $tsoll = $tsoll / 2;
            } elseif ($tsoll == 254) {
                $tsoll = 0;
            } elseif ($tsoll == 253) {
                  $tsoll = -1;
            }
            $prop = PROP_IST+PROP_SOLL+PROP_REDUCED+PROP_COMFORT;
            $props = $props | $prop;
            $data['IST'] = $tist;
            $data['SOLL'] = $tist;
            $data['REDUCED'] = $absenk;
            $data['COMFORT'] = $komfort;
            $txt = sprintf(" HKR(Act:%02.1fC ,Target:%02.1fC ,Sink:%02.1fC, Comfort:%02.1fC); ", $tist, $tsoll, $absenk, $komfort);
            $this->SendDebug(__FUNCTION__ , $txt,0);
		}
		// Update Props
		$this->SetProps($props,true,$doApply);
		if($save && $save_changes)IPS_ApplyChanges($this->InstanceID);
		// Update Data
		foreach($data as $k=>$v)$this->SetValueByIdent($k, $v);
		return true;
	}
}
CONST
	PROP_SWITCH 	= 1,
	PROP_TEMP   	= 2,
	PROP_IST 	= 4,
	PROP_SOLL	= 8,
	PROP_REDUCED = 16,
	PROP_COMFORT = 32,
	PROP_APOWER  = 64,
	PROP_TPOWER  = 128;

?>