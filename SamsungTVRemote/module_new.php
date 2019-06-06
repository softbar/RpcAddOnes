<?php 
require_once __DIR__.'/../libs/rpc_module.inc';
define ( 'MODULEDIR', __DIR__ );
class SamsungTVRemote extends BaseRpcModule{
	/**
	 * {@inheritDoc}
	 * @see IPSModule::Create()
	 */
	public function Create() {
		parent::Create();
		if($v=Sys_GetNetworkInfo()){
			while(count($v) && empty($v[0]['IP']))array_shift($v);
			$v=empty($v[0])?[]:$v[0];
		}
		$this->registerPropertyString('My_ip',empty($v['IP'])?'':$v['IP']);
		$this->registerPropertyString('My_mac',empty($v['MAC'])?'':$v['MAC']);
		$this->registerPropertyString('GroupNames',json_encode($this->defaultGroupNames));
		$this->registerPropertyString('KeyGroups',json_encode($this->CreateDefaultKeyGroups(true)));
		$this->registerPropertyString('Macros','');
	}
	/**
	 * {@inheritDoc}
	 * @see IPSModule::RequestAction()
	 */
	public function RequestAction($Ident, $Value) {
		if (in_array($Ident,['MENU','CURSOR','NUMBERS','MEDIA','SOURCE'])){
			if(!$this->CheckStatus())return false;
			$this->SendKeyCodeEx($this->ValidKeys[$Value]); 
			SetValue($this->GetIDForIdent($Ident), $Value);
		}else echo "Invalid Ident : $Ident";
	}
	
	/**
	 * {@inheritDoc}
	 * @see IPSModule::ApplyChanges()
	 */
	public function ApplyChanges() {
		parent::ApplyChanges();
		if(empty($host=$this->ReadPropertyString('Host')))
			$this->SetStatus(200);
		elseif(empty($myip=$this->ReadPropertyString('My_ip')))
			$this->SetStatus(201);
		elseif(empty($mymac=$this->ReadPropertyString('My_mac')))
			$this->SetStatus(202);
		else if(stripos($host,'http')!==false){
			$host=substr($host,stripos($host,'//')+2);
			IPS_SetProperty($this->InstanceID,'HOST',$host);
 			IPS_ApplyChanges($this->InstanceID);
		}else{
			$this->SetStatus(102);
			$this->UpdateProfiles();
		}
			
// 			if($this->GetBuffer('MY_IP')!=$myip){
// 			$n=Sys_GetNetworkInfo (); $found=false;
// 			$this->SetBuffer('MY_IP',$myip);
// 			foreach($n as $i)if($i['IP']==$myip){
// 				IPS_SetProperty($this->InstanceID,'MY_MAC',$i['MAC']);
// 				$found=true;break;
// 			}
// 			if($found)
// 				IPS_ApplyChanges($this->InstanceID);
// 			else $this->SetStatus(202);
	}
	/**
	 * {@inheritDoc}
	 * @see IPSModule::Destroy()
	 */
	public function Destroy() {
		parent::Destroy();
		foreach($this->defaultGroupNames as $g){
			$profilename='SAMSUNG_'.$g["value"].'_'.$this->InstanceID;
			@IPS_DeleteVariableProfile($profilename);
		}
	}
	/**
	 * {@inheritDoc}
	 * @see IPSModule::GetConfigurationForm()
	 */
	public function GetConfigurationForm() {
		$f=file_get_contents(__DIR__.'/form.json');
 		$f=preg_replace('/"options_g"(.*)\[[ ]*\]/i', '"options":'.$this->ReadPropertyString('GroupNames'),$f);
 		$f=json_decode($f,true);
 		if($v=Sys_GetNetworkInfo()){
 			$ips=$macs=[];
 			foreach($v as $n){
				$ips[]=['label'=>$n['IP'].'|'.$n['MAC'],'value'=>$n['IP']];
				$macs[]=['label'=>$n['MAC'].'|'.$n['IP'],'value'=>$n['MAC']];
			}
			$f['elements'][1]['type']='Select';	$f['elements'][1]['options']=$ips;
			$f['elements'][2]['type']='Select';	$f['elements'][2]['options']=$macs;
		}
 		
// 		$f["actions"]=[
//         	[ "type"=>"Label", "caption"=>"first start you must show at TV Screen and confirm message popup with ok"],
// 			["type"=>"Button", "label"=>"Show TV Menu", "onClick"=>"SAMFB_SendKey(\$id, 'KEY_MENU');"],
//     	];
		return json_encode($f);
	}
	/**
	 * @param bool $ResetMacros
	 */
	public function ResetGroups(bool $ResetMacros){
		IPS_SetProperty($this->InstanceID,'GroupNames',json_encode($this->defaultGroupNames));
		IPS_SetProperty($this->InstanceID,'KeyGroups',json_encode($this->CreateDefaultKeyGroups(true)));
		if($ResetMacros)IPS_SetProperty($this->InstanceID,'Macros','');
		IPS_ApplyChanges($this->InstanceID);
	}
	/**
	 * @param string $Key
	 * @return boolean
	 */
	public function SendKey(string $Key){
		if(!$this->CheckStatus())return false;
		if(is_numeric($Key) && isset($this->ValidKeys[$Key]))
			$Key=$this->ValidKeys[$Key];
		$this->SendDebug(__FUNCTION__, $Key, 0);
		return $this->SendKeyCodeEx($Key);
	}
	/**
	 * @param string $Name
	 * @return boolean
	 */
	public function SendMacro(string $Name){
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
	protected function ApplyHost($host,$doApply){
		if(!parent::ApplyHost($host,$doApply))return false;
		if(stripos($host,'http')===false){
			$host='http://'.$host;
			IPS_SetProperty($this->InstanceID,'Host', $host);
// $this->SendDebug(__FUNCTION__, "Apply host $host SAVE: ".($save?'true':'false'), 0)	;		
			if($doApply)IPS_ApplyChanges($this->InstanceID);
		}	
	}
	protected function GetPropDef($Ident){
		$profilename='SAMSUNG_'.$Ident.'_'.$this->InstanceID;
		switch($Ident){
 			case $this->prop_names[PROP_MENU]: return  [1,'Menu',$profilename,0,'',PROP_MENU,1];
 			case $this->prop_names[PROP_CURSOR]: return  [1,'Cursor',$profilename,0,'',PROP_CURSOR,1];
 			case $this->prop_names[PROP_NUMBERS]: return  [1,'Numbers',$profilename,0,'',PROP_NUMBERS,1];
 			case $this->prop_names[PROP_MEDIA]: return  [1,'Media',$profilename,0,'',PROP_MEDIA,1];
 			case $this->prop_names[PROP_SOURCE]: return  [1,'Source',$profilename,0,'',PROP_SOURCE,1]; 	
 			case $this->prop_names[PROP_USER]: return  [1,'User',$profilename,0,'',PROP_USER,1];
 		}
	}
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::$prop_names
	 * @var array $prop_names
	 */
	protected $prop_names = [PROP_MENU =>'MENU',PROP_CURSOR=>'CURSOR',PROP_NUMBERS=>'NUMBERS',PROP_MEDIA=>'MEDIA',PROP_SOURCE=>'SOURCE',PROP_USER=>'USER']; 	
	
	
	
	
	
	
	
	
	
	
	private $ValidKeys=['KEY_0','KEY_1','KEY_2','KEY_3','KEY_4','KEY_5','KEY_6','KEY_7','KEY_8','KEY_9','KEY_POWEROFF','KEY_MUTE','KEY_ENTER','KEY_EXIT','KEY_MENU','KEY_GUIDE','KEY_INFO','KEY_RETURN','KEY_SOURCE','KEY_TV','KEY_HDMI','KEY_HDMI2','KEY_RECORD','KEY_TOOLS','KEY_CHUP','KEY_CHDOWN','KEY_PLAY','KEY_PAUSE','KEY_STOP','KEY_NEXT','KEY_PREVIOUIS','KEY_FF','KEY_REWIND','KEY_VOLUP','KEY_VOLDOWN','KEY_UP','KEY_DOWN','KEY_LEFT','KEY_RIGHT'];		
	private $defaultGroupNames = [
		["label"=>"Menu","value"=>"MENU", "enabled"=>true],
		["label"=>"Cursor","value"=>"CURSOR", "enabled"=>true],
		["label"=>"Numbers","value"=>"NUMBERS", "enabled"=>true],
		["label"=>"Media","value"=>"MEDIA", "enabled"=>true],
		["label"=>"Source","value"=>"SOURCE", "enabled"=>true]	
	];
	
	private function CheckStatus(){
		$host=$this->ReadPropertyString('Host');
		$status=IPS_GetInstance($this->InstanceID)['InstanceStatus'];
		if($status!=203 && $status!=102)return false;
		$online= !empty($host) && @Sys_Ping($host,1);
		if($online){
			if($status!=102)$this->SetStatus($status);			
			return true;
		} else if($status!=203)$this->SetStatus($status=203);
		return false;
	}
	private function CreateDefaultKeyGroups($returnValue=false){
		$groups=[];
		foreach($this->ValidKeys as $id=>$key){
			$name=ucfirst(strtolower(substr($key,strpos($key,'_')+1)));
			
			if(is_numeric($name)){
	 			$groups[]=["value"=>$key,"label"=>$name ,"group"=>"NUMBERS","id"=>$id, "enabled"=>true];
			}
			if(in_array($key,['KEY_MUTE','KEY_VOLUP','KEY_VOLDOWN','KEY_CHUP','KEY_CHDOWN','KEY_PLAY','KEY_PAUSE','KEY_STOP','KEY_NEXT','KEY_PREVIOUIS','KEY_FF','KEY_REWIND','KEY_RECORD'])){
	 			$groups[]=["value"=>$key,"label"=>$name ,"group"=>"MEDIA","id"=>$id, "enabled"=>true];
			}
			if(in_array($key,['KEY_TV','KEY_HDMI','KEY_HDMI2','KEY_SOURCE'])){
	 			$groups[]=["value"=>$key,"label"=>$name ,"group"=>"SOURCE","id"=>$id, "enabled"=>true];
			}
			if(in_array($key,['KEY_ENTER','KEY_EXIT','KEY_MENU','KEY_TOOLS','KEY_RETURN','KEY_INFO','KEY_GUIDE','KEY_POWEROFF'])){
	 			$groups[]=["value"=>$key,"label"=>$name ,"group"=>"MENU","id"=>$id, "enabled"=>true];
			}
			if(in_array($key,['KEY_UP','KEY_DOWN','KEY_LEFT','KEY_RIGHT'])){
	 			$groups[]=["value"=>$key,"label"=>$name ,"group"=>"CURSOR","id"=>$id, "enabled"=>true];
			}
		}
		if($returnValue)return $groups;
		IPS_SetProperty($this->InstanceID, 'KeyGroups', json_encode($groups));
		IPS_ApplyChanges($this->InstanceID);
	}
	
	private function UpdateProfiles(){
		$groups = json_decode($this->ReadPropertyString('GroupNames'),true);
		$keysGroups   = json_decode($this->ReadPropertyString('KeyGroups'),true);
// 			foreach($keys as $key){
// 				$this->SendDebug($key['value'],$key['enabled'],0);				
// 			}
		$profiles = [];
		$delIdent=function($Ident){
			@$this->DisableAction($Ident);
			@$this->UnregisterVariable($Ident);
			@IPS_DeleteVariableProfile('SAMSUNG_'.$Ident.'_'.$this->InstanceID);
		};
		foreach($groups as $id=>$group){
			$gident=$group['value'];
			unset($groups[$id]);
			$groups[$gident]=$group;
			if(!$group['enabled']){
				$delIdent($gident);
				continue;
			}
			$profiles[$gident]=[];
			foreach($keysGroups as $key){
				if($key['enabled'] && ($key['group']==$group['value']))
					$profiles[$gident][$key['id']]=$key['label'];
			}
			if(count($profiles[$gident])==0){
				unset($profiles[$gident]);
				$delIdent($gident);
			}
			
		}
		foreach($profiles as $gident=>$keys){
			$profilename='SAMSUNG_'.$gident.'_'.$this->InstanceID;
			@IPS_DeleteVariableProfile($profilename);
			@IPS_CreateVariableProfile($profilename,1);
			foreach($keys as $id=>$name){
	 			IPS_SetVariableProfileAssociation($profilename,$id, $name, '', -1);
			}
			if(!($id=@$this->GetIDForIdent($gident))){
				$id=$this->RegisterVariableInteger($gident, $this->Translate($groups[$gident]['label']), $profilename, 0);	
				$this->EnableAction($gident);
			}
		}
		
		
		
	}
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
		if(!$this->CheckStatus())return false;
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
		if(!in_array($k,$this->ValidKeys)){echo "Invalid Key $k";return false;}
		$ie=base64_encode($this->ReadPropertyString('My_ip'));
		$me=base64_encode($this->ReadPropertyString('My_mac'));
		$k=base64_encode($k);
		if(!($sock=fsockopen($this->ReadPropertyString('Host'),55000)))return false;
		stream_set_timeout($sock,2);
		$a="iphone..iapp.samsung";$t="iphone.UE55C8000.iapp.samsung";$r=base64_encode('IPS Remote Control');
		$m=chr(0x64).chr(0x00).chr(strlen($ie)).chr(0x00).$ie.chr(strlen($me)).chr(0x00).$me.chr(strlen($r)).chr(0x00).$r;
		$p=chr(0x00).chr(strlen($a)).chr(0x00).$a.chr(strlen($m)).chr(0x00).$m;
		fwrite($sock,$p);
		$m=chr(0xc8).chr(0x00);
		$p=chr(0x00).chr(strlen($a)).chr(0x00).$a.chr(strlen($m)).chr(0x00).$m;
		fwrite($sock,$p);
		$m=chr(0x00).chr(0x00).chr(0x00).chr(strlen($k)).chr(0x00).$k;
		$p=chr(0x00).chr(strlen($t)).chr(0x00).$t.chr(strlen($m)).chr(0x00).$m;
		fwrite($sock,$p);
		fclose($sock);
		return true;
	}
}
CONST 
	PROP_MENU 	= 1,
	PROP_CURSOR = 2,
	PROP_NUMBERS = 4,
	PROP_MEDIA 	= 8,
	PROP_SOURCE = 16,
	PROP_USER 	= 32;
?>