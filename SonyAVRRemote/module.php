<?php 
require_once __DIR__ . '/../../RpcTools/libs/rpc_module.inc';
define ( 'MODULEDIR', __DIR__ );
class SonyAVRRemote extends IpsRpcModule{
	/**
	 * {@inheritDoc}
	 * @see IPSModule::Create()
	 */
	public function Create() {
		parent::Create();
		$this->registerPropertyString('GroupNames',json_encode($this->getDefaulteGroups()));
		$this->registerPropertyString('KeyGroups',json_encode($this->getDefaultKeyGroups()));
		$this->registerPropertyString('Macros','');
	}
	/**
	 * {@inheritDoc}
	 * @see IPSModule::RequestAction()
	 */
	public function RequestAction($Ident, $Value) {
		if (in_array($Ident,['MENU','CURSOR','NUMBERS','MEDIA','SOURCE','USER','SWITCH'])){
			if(!$this->CheckOnline())return false;
			$this->SendKeyCodeEx($this->ValidKeys[$Value]); 
			$this->SetValueByIdent($Ident, $Value);
		}else echo "Invalid Ident : $Ident";
	}
	
	/**
	 * {@inheritDoc}
	 * @see IPSModule::Destroy()
	 */
	public function Destroy() {
		parent::Destroy();
		foreach($this->prop_names as $g){
			$profilename='SONY_'.$g.'_'.$this->InstanceID;
			@IPS_DeleteVariableProfile($profilename);
		}
	}
	/**
	 * {@inheritDoc}
	 * @see IPSModule::GetConfigurationForm()
	 */
	
	public function GetConfigurationForm() {
		$f=parent::GetConfigurationForm();
 		$f=preg_replace('/"options_g":\[\]/i', '"options":'.$this->ReadPropertyString('GroupNames'),$f);
 		$options=[];
 		if($keys=json_decode($this->ReadPropertyString('KeyGroups'),true))
 			foreach($keys as $key)$options[]=['value'=>$key['value'],'label'=>sprintf("%-3s %-15s %s",$key['id'],$key['value'],$key['label']) ];
		$f=preg_replace('/"options_keycode":\[\]/i', '"options":'.json_encode($options),$f);
		$options=[];
 		if($keys=json_decode($this->ReadPropertyString('Macros'),true))
 			foreach($keys as $key)$options[]=['value'=>$key['name'],'label'=>$key['name']];
		$f=preg_replace('/"options_macro":\[\]/i', '"options":'.json_encode($options),$f);
		
		
		return $f; 
	}
	/**
	 * @param bool $ResetMacros
	 */
	public function ResetGroups(bool $ResetMacros){
		IPS_SetProperty($this->InstanceID,'GroupNames',json_encode($this->getDefaulteGroups()));
		IPS_SetProperty($this->InstanceID,'KeyGroups',json_encode($this->getDefaultKeyGroups()));
		if($ResetMacros)IPS_SetProperty($this->InstanceID,'Macros','');
		$this->SetProps(0,false,false);
		IPS_ApplyChanges($this->InstanceID);
	}
	/**
	 * @param string $Key
	 * @return boolean
	 */
	public function SendKey(string $Key){
		if(!$this->CheckOnline())return false;
		$this->SendDebug(__FUNCTION__, $Key, 0);
		return $this->SendKeyCodeEx($Key);
	}
	/**
	 * @param string $Name
	 * @return boolean
	 */
	public function SendMacro(string $Name){
		if(!$this->CheckOnline())return false;
		if($ml=json_decode($this->ReadPropertyString('Macros'))){
			$ok=false;
			foreach($ml as $m){
				if($ok= strcasecmp($m->name,$Name)==0)break;
			}
			if(!$ok){
				IPS_LogMessage(IPS_GetName($this->InstanceID),sprintf($this->Translate("Error! Macro %s not found"),$Name));
				return false;	
			}
			$this->SendDebug(__FUNCTION__, $Name, 0);
			return $this->SendKeyCodeMacro($m->macro);
		}
	}

	// --------------------------------------------------------------------------------
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::$showLogin
	 * @var array $showLogin
	 */
	protected $showLogin=[false,false];
	// --------------------------------------------------------------------------------
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::GetModuleName()
	 */
	protected function GetModuleName($name,$host){
		return __CLASS__. ' ('.parse_url($host,PHP_URL_HOST).')';
	}	
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::GetDiscoverDeviceOptions()
	 */
	protected function GetDiscoverDeviceOptions(){
		$filter=['IRCC.X_SendIRCC'	];
		return [OPT_MINIMIZED+OPT_SMALCONFIG,$filter,':8080/description.xml'];
	}
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::DoUpdate()
	 */
	protected function DoUpdate(){}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::UpdateProps()
	 */
	protected function UpdateProps($doApply=true){
		$groups = json_decode($this->ReadPropertyString('GroupNames'),true);
		$keysGroups   = json_decode($this->ReadPropertyString('KeyGroups'),true);
		$profiles = [];
		$props=0;
		foreach($groups as $id=>$group){
			$prop=$group['value'];
			if(!$group['enabled']){
				continue;
			}
			$p=[];
			foreach($keysGroups as $key){
				if($key['enabled'] && ($key['prop']==$prop))
					$p[$key['id']]=$key['label'];
			}
			if(count($p)==0){
				continue;
			}
			$profiles[$prop]=$p;
			$props=$props|$prop;
		}	
		foreach($profiles as $prop=>$keys){
			$gident=$this->prop_names[$prop];
			$profilename='SONY_'.$gident.'_'.$this->InstanceID;
			@IPS_DeleteVariableProfile($profilename);
			@IPS_CreateVariableProfile($profilename,1);
			foreach($keys as $id=>$name){
	 			IPS_SetVariableProfileAssociation($profilename,$id, $name, '', -1);
			}
		}
		return !$this->SetProps($props,true,$doApply);
	}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::GetPropDef()
	 */
	protected function GetPropDef($Ident){
		$profilename='SONY_'.$Ident.'_'.$this->InstanceID;
		switch($Ident){
 			case $this->prop_names[PROP_MENU]: return  [1,'Menu',$profilename,0,'',PROP_MENU,1];
 			case $this->prop_names[PROP_CURSOR]: return  [1,'Cursor',$profilename,0,'',PROP_CURSOR,1];
 			case $this->prop_names[PROP_NUMBERS]: return  [1,'Numbers',$profilename,0,'',PROP_NUMBERS,1];
 			case $this->prop_names[PROP_MEDIA]: return  [1,'Media',$profilename,0,'',PROP_MEDIA,1];
 			case $this->prop_names[PROP_SOURCE]: return  [1,'Source',$profilename,0,'',PROP_SOURCE,1]; 	
			case $this->prop_names[PROP_SWITCH]: return  [1,'Switch',$profilename,0,'',PROP_SOURCE,1]; 	
 			case $this->prop_names[PROP_USER]: return  [1,'User',$profilename,0,'',PROP_USER,1];
 		}
	}
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::$prop_names
	 * @var array $prop_names
	 */
	protected $prop_names = [PROP_MENU =>'MENU',PROP_CURSOR=>'CURSOR',PROP_NUMBERS=>'NUMBERS',PROP_MEDIA=>'MEDIA',PROP_SOURCE=>'SOURCE',PROP_SWITCH=>'SWITCH', PROP_USER=>'USER']; 	
	private function getDefaulteGroups(){
		return [
			["label"=>$this->Translate("Menu"),		"value"=>PROP_MENU, 	"enabled"=>true],
			["label"=>$this->Translate("Cursor"),	"value"=>PROP_CURSOR,	"enabled"=>true],
			["label"=>$this->Translate("Numbers"),	"value"=>PROP_NUMBERS, 	"enabled"=>true],
			["label"=>$this->Translate("Media"),	"value"=>PROP_MEDIA, 	"enabled"=>true],
			["label"=>$this->Translate("Source"),	"value"=>PROP_SOURCE, 	"enabled"=>true],
			["label"=>$this->Translate("Switch"),	"value"=>PROP_SWITCH, 	"enabled"=>true],
			["label"=>$this->Translate("User"),		"value"=>PROP_USER, 	"enabled"=>true]
		];
	}
	private function getDefaultKeyGroups(){
		$groups=[];
		foreach($this->ValidKeys as $id=>$key){
			$name=ucfirst(strtolower(substr($key,strpos($key,'_')+1)));
			
			if(is_numeric($name)){
	 			$groups[]=["value"=>$key,"label"=>$name ,"prop"=>PROP_NUMBERS, "id"=>$id, "enabled"=>true];
			}
			if(in_array($key,['KEY_MUTE','KEY_VOLUP','KEY_VOLDOWN','KEY_CHUP','KEY_CHDOWN'])){
	 			$groups[]=["value"=>$key,"label"=>$name ,"prop"=>PROP_SWITCH,"id"=>$id, "enabled"=>true];
			}
			if(in_array($key,['KEY_PLAY','KEY_PAUSE','KEY_STOP','KEY_NEXT','KEY_PREV','KEY_FF','KEY_FR','KEY_REPEAT','KEY_SHUFFLE'])){
	 			$groups[]=["value"=>$key,"label"=>$name ,"prop"=>PROP_MEDIA,"id"=>$id, "enabled"=>true];
			}
			if(in_array($key,['KEY_SRCUP','KEY_SRCDOWN'])){
	 			$groups[]=["value"=>$key,"label"=>$name ,"prop"=>PROP_SOURCE,"id"=>$id, "enabled"=>true];
			}
			if(in_array($key,['KEY_OK','KEY_MENU','KEY_OPTIONS','KEY_RETURN','KEY_INFO','KEY_POWER'])){
	 			$groups[]=["value"=>$key,"label"=>$name ,"prop"=>PROP_MENU,"id"=>$id, "enabled"=>true];
			}
			if(in_array($key,['KEY_UP','KEY_DOWN','KEY_LEFT','KEY_RIGHT'])){
	 			$groups[]=["value"=>$key,"label"=>$name ,"prop"=>PROP_CURSOR,"id"=>$id, "enabled"=>true];
			}
		}
		return $groups;
	}
	private $ValidKeys=['KEY_0','KEY_1','KEY_2','KEY_3','KEY_4','KEY_5','KEY_6','KEY_7','KEY_8','KEY_9','KEY_POWER','KEY_MUTE','KEY_OK','KEY_MENU','KEY_INFO','KEY_RETURN','KEY_OPTIONS','KEY_SRCUP','KEY_SRCDOWN','KEY_PLAY','KEY_PAUSE','KEY_STOP','KEY_NEXT','KEY_PREV','KEY_SHUFFLE','KEY_REPEAT','KEY_FF','KEY_FR','KEY_VOLUP','KEY_VOLDOWN','KEY_UP','KEY_DOWN','KEY_LEFT','KEY_RIGHT'];		
	private $KeyValues=['AAAAAgAAADAAAAAJAQ==','AAAAAgAAADAAAAAAAQ==','AAAAAgAAADAAAAABAQ==','AAAAAgAAADAAAAACAQ==','AAAAAgAAADAAAAADAQ==','AAAAAgAAADAAAAAEAQ==','AAAAAgAAADAAAAAFAQ==','AAAAAgAAADAAAAAGAQ==','AAAAAgAAADAAAAAHAQ==','AAAAAgAAADAAAAAIAQ==','AAAAAgAAADAAAAAVAQ==','AAAAAgAAADAAAAAUAQ==','AAAAAgAAADAAAAAMAQ==','AAAAAgAAADAAAABTAQ==','AAAAAgAAADAAAABLAQ==','AAAAAwAAARAAAAB9AQ==','AAAAAwAAARAAAABzAQ==','AAAAAgAAALAAAABqAQ==','AAAAAgAAALAAAABpAQ==','AAAAAwAAARAAAAAyAQ==','AAAAAwAAARAAAAA5AQ==','AAAAAwAAARAAAAA4AQ==','AAAAAwAAARAAAAAxAQ==','AAAAAwAAARAAAAAwAQ==','AAAAAwAAARAAAAAqAQ==','AAAAAwAAARAAAAAsAQ==','AAAAAwAAARAAAAA0AQ==','AAAAAwAAARAAAAAzAQ==','AAAAAgAAADAAAAASAQ==','AAAAAgAAADAAAAATAQ==','AAAAAgAAALAAAAB4AQ==','AAAAAgAAALAAAAB5AQ==','AAAAAgAAALAAAAB6AQ==','AAAAAgAAALAAAAB7AQ=='];
	/*
	 * macros format
	 * KEY,KEY,KEY 
	 * or
	 * KEY;delay,KEY;delay where delay in ms
	 * or mixed
	 * KEY,KEY;delay,KEY,KEY,KEY
	 * 
	 */
	private function SendKeyCodeMacro($macros){
		$macros=explode(',',$macros);
		$count=count($macros)-1;  
		foreach($macros as $c=>$m){
			$delay=$c==0&&$count>1?1000:($c<$count?500:0);
			$m=explode(';',$m);
			if(!empty($m[1])&&intval($m[1])>99)$delay=intval($m[1]);
	 		if(!$this->SendKeyCodeEx($m[0]))return false;
	 		IPS_Sleep($delay);
	// echo "send $m[0] sleep $m[1]\n";
		}
		return true;
	}
	private function SendKeyCodeEx($k){
		if(!is_numeric($k)) $k=array_search($k, $this->ValidKeys);
		
		if($k<0 || !isset($this->KeyValues[$k])){echo "Invalid Key $k";return false;}
	
		return $this->CallApi('IRCC.X_SendIRCC',[$this->KeyValues[$k]]);
	}
}



CONST 
	PROP_MENU 	= 1,
	PROP_CURSOR = 2,
	PROP_NUMBERS = 4,
	PROP_MEDIA 	= 8,
	PROP_SOURCE = 16,
	PROP_USER 	= 64,
	PROP_SWITCH = 32;
?>