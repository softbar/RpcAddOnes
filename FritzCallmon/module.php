<?php
require_once __DIR__ . '/../../RpcTools/libs/rpc_module.inc';
define ( 'MODULEDIR', __DIR__ );
/**
 *
 * @author Xavier
 *        
 */
class FritzCallmon extends IPSRpcModule {
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::Create()
	 */
	public function Create() {
		parent::Create ();
		$this->RegisterPropertyBoolean ( 'ShowMessageButton', false );
		$this->RegisterPropertyBoolean ( 'ShowDialButton', false );
		$this->RegisterPropertyString  ( "DialPort", "" );
		$this->RegisterPropertyInteger ( 'DialLines', 0 );
		$this->RegisterPropertyBoolean ( 'Calllist', true );
		$this->RegisterPropertyInteger ( 'CallLines', 10 );
		$this->RegisterPropertyString  ( "Columns", "Icon, Date, Name, Caller" );
		$this->RegisterPropertyBoolean ( 'ShowHeader', false );
		$this->RegisterPropertyBoolean ( 'UpdateOnRing', false );
		$this->RegisterPropertyBoolean ( 'UpdateOnCall', false );
		$this->RegisterPropertyBoolean ( 'UpdateOnConnect', false );
		$this->RegisterPropertyBoolean ( 'UpdateOnDisconnect', true );
		$this->RegisterPropertyBoolean ( "CallType_1", false );
		$this->RegisterPropertyBoolean ( "CallType_2", true );
		$this->RegisterPropertyBoolean ( "CallType_3", false );
		$this->RegisterPropertyBoolean ( "CallType_4", true );
		$this->RegisterPropertyBoolean ( "CallType_5", false );
		$this->RegisterPropertyBoolean ( "CallType_6", false );
		$this->RegisterPropertyBoolean ( "CallType_9", false );
		$this->RegisterPropertyBoolean ( "CallType_10", false );
		$this->RegisterPropertyBoolean ( "CallType_11", false );
	}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::Destroy()
	 */
	public function Destroy(){
		parent::Destroy();
		$this->RegisterHook(__CLASS__.$this->InstanceID, false);
	}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::GetConfigurationForm()
	 */
	public function GetConfigurationForm(){
		$getPhoneOptions = function(){
			if($options=json_decode($this->GetBuffer('PHONE_LIST'),true))return $options;
			if(!($r=$this->CallApi('X_AVM-DE_Dect1.GetDectListPath')) || 
					($xml = simplexml_load_file($this->ReadPropertyString('Host').':49000'.$r))===false){
				IPS_LogMessage ( IPS_GetName( $this->InstanceID ), sprintf($this->Translate("Error load connected phone names from %s"),$r) );
				return $options;
			}
			if(!empty($xml->Item))foreach($xml->Item as $item){
				if(preg_match('/!fon/i',(string)$item->Model))
				$options[]=["value"=>(string)$item->Name,'label'=>sprintf('%s: %s',(string)$item->Id,(string)$item->Name)];
	   		}
	   		$this->SetBuffer('PHONE_LIST', json_encode($options));
	   		return $options;
		};
// 		$this->SetBuffer('PHONE_LIST','');
		if($this->GetStatus()==102 && ($options=$getPhoneOptions())){
			$f=json_decode(parent::GetConfigurationForm(),true);
			foreach($f['elements'] as $id=>$e){
				if(!empty($e['name'])&&$e['name']=='DialPort'){
 					$f['elements'][$id]['type']='Select';
 					$f['elements'][$id]['options']=$options;
					break;
				}
			}
			return json_encode($f);
		} 
		return parent::GetConfigurationForm();
	}
	public function GetConfigurationForParent(){
		return json_encode(['Host'=>parse_url($this->ReadPropertyString('Host'),PHP_URL_HOST),'Port'=>1012]);
	}
	/**
	 * {@inheritDoc}
	 * @see IPSModule::ReceiveData()
	 */
	public function ReceiveData($JSONString) {
		$this->SendDebug(__FUNCTION__,$JSONString,0);
		$this->doDecodeCall(json_decode($JSONString,true)['Buffer']);
	}
	/**
	 * 
	 */
	public function RequestUpdate(){
// 		$this->ReDiscover();
 		$this->RunUpdate();
	}
	
	// --------------------------------------------------------------------------------
	protected $timerDef=['ONLINE_INTERVAL'=>[1,'h'],'OFFLINE_INTERVAL'=>[12,'h']];
	protected $requireLogin=[true,true];
	protected $showRefreshButton=true;
	// --------------------------------------------------------------------------------
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::GetDiscoverDeviceOptions()
	 */
	protected function GetDiscoverDeviceOptions(){
		$filter=[
				'X_AVM-DE_OnTel1.GetInfo','X_AVM-DE_OnTel1.GetCallList',
 				'X_AVM-DE_TAM1.GetList','X_AVM-DE_TAM1.GetInfo','X_AVM-DE_TAM1.GetMessageList','X_AVM-DE_TAM1.DeleteMessage','X_AVM-DE_TAM1.MarkMessage',
				'X_AVM-DE_Dect1.GetDectListPath','X_AVM-DE_Dect1.GetNumberOfDectEntries','X_AVM-DE_Dect1.GetGenericDectEntry',
				'X_VoIP1.X_AVM-DE_DialGetConfig','X_VoIP1.X_AVM-DE_DialSetConfig','X_VoIP1.X_AVM-DE_DialNumber','X_VoIP1.X_AVM-DE_DialHangup',
				'DeviceConfig1.X_AVM-DE_CreateUrlSID'
		];
		return [OPT_MINIMIZED+OPT_SMALCONFIG,$filter,':49000/tr64desc.xml'];
	}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::GetModuleName()
	 */
	protected function GetModuleName($name,$host){
		return 'FritzCallmon ('.parse_url($host,PHP_URL_HOST).')';
	}	
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::UpdateProps()
	 */
	protected function UpdateProps($doApply=true){
		$props=PROP_CALLLIST+PROP_MISSED+PROP_MESSAGES ;
		if($lines=$this->ReadPropertyInteger('DialLines'))
			for($j=0;$j<$lines;$j++) $props+=constant("PROP_LINE_$j");			
		$ok=!$this->SetProps($props,true,$doApply);
		if($this->ReadPropertyBoolean('ShowDialButton')||$this->ReadPropertyBoolean('ShowMessageButton')){
			$this->RegisterHook(__CLASS__.$this->InstanceID, true);
		}
		return $ok;
	}
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::DoUpdate()
	 */
	protected function DoUpdate(){
		if(!($r=$this->CallApi('X_AVM-DE_OnTel1.GetCallList')))return;
		$xml = @simplexml_load_file ( $r );
		if ($xml === false) {
			IPS_LogMessage ( IPS_GetName( $this->InstanceID ), sprintf($this->Translate("Error load call list from %s"),$r) );
			return false;
		}
		$xml = new simpleXMLElement ( $xml->asXML () );
		$timelimit = 0;
// 		if ($id = $this->ReadPropertyInteger ( "TimeLimit" )) $timelimit = GetValue ( $id );
		$callList = array ();
		foreach ( $xml->Call as $call ) {
			if ($timelimit > 0) { // filter calls by timestamp
				$parts = preg_split ( '/[ .:]/', ( string ) $call->Date );
				$callTimestamp = mktime ( ( int ) $parts [3], ( int ) $parts [4], 0, ( int ) $parts [1], ( int ) $parts [0], ( int ) $parts [2] );
				if ($callTimestamp < $timelimit) continue;
			}
			$callList [] = $call;
		}		
		$messageList=null;
        for ($i=0;$i<5;$i++){
            $GetInfo = $this->CallApi('X_AVM-DE_TAM1.GetInfo',["NewIndex"=>$i]);
            if ($GetInfo["NewName"] <> ""){
                $r = $this->CallApi('X_AVM-DE_TAM1.GetMessageList',["NewIndex"=>$i]);
                $xml = @simplexml_load_file($r);
                if ($xml === false){
					IPS_LogMessage ( IPS_GetName( $this->InstanceID ), sprintf($this->Translate("Error load awnsering message list from %s"),$r) );
                    return false;
                }
                $xml = new simpleXMLElement($xml->asXML());
                $messageList[40+$i]=$xml;
            }
        }
		for($i = 0; $i < count ( $callList ); $i ++) {
			// Switch numbers if needed
			if (( int ) $callList [$i]->Type == 3) {
				$tmp = ( string ) $callList [$i]->Caller;
				$callList [$i]->Caller = ( string ) $callList [$i]->Called;
				$callList [$i]->Called = $tmp;
			}
			// Clear own number for example ISDN: POTS: SIP: etc...
			$callList [$i]->Called = str_replace ( strtoupper ( ( string ) $callList [$i]->Numbertype ) . ": ", "", ( string ) $callList [$i]->Called );
			$callList [$i]->addChild ( "AB" ); // create empty message entry
			if ((( int ) $callList [$i]->Port >= 40) && (( int ) $callList [$i]->Port <= 44)) {
				$callList [$i]->Type = 6; // set message deleted as preset
				if (strlen ( ( string ) $callList [$i]->Path ) != 0) { // get same message from calllist
					$message = empty($messageList [( int ) $callList [$i]->Port])?null:$messageList [( int ) $callList [$i]->Port];
					$callList [$i]->Duration = "---";
					if(!empty($message->Message)){
						$message=$message->Message;
						$callList [$i]->addChild ( "Tam" );
						$callList [$i]->Tam->addAttribute ( "index", ( string ) $message[0]->Tam);
						$callList [$i]->Tam->addChild ( "TamIndex", ( string ) $message[0]->Index);
						$callList [$i]->Type = ($message[0]->New == "1" ? "4" : "5");
						$callList [$i]->AB = "1";
						if ($message[0]['New'] == "1") {
							$callList [$i]->Path = "?path=" . urlencode ( ( string ) $callList [$i]->Path ) . "&tam=" .  $message[0]->Tam . "&index=" . $message[0]->Index . "&action=mark";
						} else {
							$callList [$i]->Path = "?path=" . urlencode ( ( string ) $callList [$i]->Path );
						}
						
					}else $callList [$i]->Path ='';
				}
			}
		}
		$this->RenderCallList($callList);
 	}
	/**
	 * {@inheritDoc}
	 * @see BaseRpcModule::GetPropDef()
	 */
	protected function GetPropDef($Ident){
		switch($Ident){
 			case $this->prop_names[PROP_CALLLIST]: return [3,'Callerlist','~HTMLBox',0,'Telephone',PROP_CALLLIST,0];
			case $this->prop_names[PROP_MISSED]	: return [1,'Missed calls','',7,'Talk',PROP_MISSED,0];
			case $this->prop_names[PROP_MESSAGES]: return  [1,'New messages','',8,'Talk',PROP_MESSAGES,0];
			default : $m=null;if(preg_match('/line_(\d)/i',$Ident,$m))return [3,'Line '.($m[1]+1),'',$m[1],'HollowDoubleArrowRight',constant('PROP_LINE_'.$m[1]),0];

		}
	}
	protected $prop_names = [PROP_CALLLIST=>'CALLLIST',PROP_MISSED=>'MISSED',PROP_MESSAGES=>'MESSAGES',  PROP_LINE_0=>'LINE_0',PROP_LINE_1=>'LINE_1',PROP_LINE_2=>'LINE_2',PROP_LINE_3=>'LINE_3',PROP_LINE_4=>'LINE_4',PROP_LINE_5=>'LINE_5'	];
	/**
	 * {@inheritDoc}
	 * @see IPSRpcModule::ProcessHookData()
	 */
	protected function ProcessHookData() {	
		if(parent::ProcessHookData())return true;
		$this->SendDebug(__FUNCTION__,print_r($_GET,true),0);
		if(isset($_GET['cmd'])){
			if($_GET['cmd']=='message'){
				if (isset ($_GET['path'])){
		            $sid = $this->CallApi('DeviceConfig1.X_AVM-DE_CreateUrlSID');
		            $file = $this->ReadPropertyString("Host").":49000".urldecode($_GET['path'])."&".$sid;
		            $this->SendDebug(__FUNCTION__,"Send File => $file",0);
		            @header("Content-type: audio/wave");
		            @header('Content-Transfer-Encoding: binary');
		            @header('Cache-Control: must-revalidate');
		            readfile($file);
		        }
		    	if (isset($_GET['index']) && isset($_GET['action'])){
		    		if($_GET['action']=="delete") {
		    	   		$r=$this->CallApi('X_AVM-DE_TAM1.DeleteMessage',[(int)$_GET['tam'],(int)$_GET['index']]);
		    		}elseif($_GET['action']=="mark") {
                		$r=$this->CallApi('X_AVM-DE_TAM1.MarkMessage',[(int)$_GET['tam'],(int)$_GET['index'],1]);
            		}else return;
            		if($r)$this->RunUpdate();
        		}
			}
			else if($_GET['cmd']=='dial'){
				if(isset($_GET['number'])){
					$this->doDial($_GET['number']);
					
				}
				 @header("Content-type: text/javascript");
				 echo PHP_EOL;
			}
 		}
	}	
	// --------------------------------------------------------------------------------
	private function doDial(string $Number){
		$DialPort=$this->ReadPropertyString('DialPort');
		
		if(!($api=$this->CreateApi()))return null;
       	$dialConfig = $api->__call("X_VoIP1.X_AVM-DE_DialGetConfig",[]);
       	if($api->LastError())return null;
        if(empty($dialConfig) || $dialConfig == "unconfigured")
            $dialConfig = "";
 
       	if(empty($DialPort) && empty($dialConfig)){
           IPS_LogMessage(IPS_GetName($this->InstanceID),sprintf($this->Translate('Error can not call to %s ! Dial device not set'),$Number));
           return false;
        }
		$DialPort='DECT: '.$DialPort;
        $this->SendDebug(__FUNCTION__, "Number $Number on ".($DialPort?$DialPort:$dialConfig), 0);
        if($DialPort && $DialPort!=$dialConfig){
	        $api->__call("X_VoIP1.X_AVM-DE_DialSetConfig",[$DialPort]);
	       	if($api->LastError())return null;
			sleep(0.5);
        }
		$result = $api->__call("X_VoIP1.X_AVM-DE_DialNumber",[$Number]);
        if($DialPort && $DialPort!=$dialConfig){
        	sleep(0.5);
        	$api->__call("X_VoIP1.X_AVM-DE_DialSetConfig",[$dialConfig]);
        }
        if($api->LastError())return null;
        return $result;
    }
   	private function RenderCallList($callList=null) {
		$RenderDialDiv= function ($callnumber){
			$visible=empty($callnumber)?'hidden':'visible';
			return  '<div class="ipsContainer text colored" style="background-color: rgba(255, 255, 255, 0.3);visibility:'.$visible.';" title="'.$callnumber.'" data-nr="'.$callnumber.'" onclick="dial(this);">'.$this->Translate('Dial')."</div>";
		};
		$RenderMesageDiv = function ($call, $visible=true){
       		 $visible=$visible&&strlen(trim($call->Path)) > 0?'visible':'hidden';
        	return '<div class="ipsContainer text colored" style="background-color: rgba(255, 255, 255, 0.3);visibility: '.$visible.';" data-id="'.$call->Id."\" data-messagepath=\"".$call->Path."\" onclick=\"playMessage(this);\">".$this->Translate('Play')."</div>";
		};
	 	$RenderDialScript= function (){
			$hookURL="/hook/".__CLASS__.$this->InstanceID."/";
			return "<script>
		function dial(obj) {
			var messagePath = window.location.origin+'$hookURL/?cmd=dial&number='+obj.getAttribute('data-nr');
			//console.log(messagePath);
			var s=document.createElement('SCRIPT');
			s.src=messagePath;
			try{document.body.appendChild(s)} catch(e){}
			window.setTimeout(function(){document.body.removeChild(s)},100);
	    }
	</script>";
		}; 		
		$RenderHTML=function ($detailDIV,$msgDIV,$linkDIV, $extendedDIV='', $childDIV=''){
	           return "<div class=\"ipsContainer container nestedEven ipsVariable\" style=\"border-color: rgba(255,255,255,0.15); border-style: solid; border-width: 0 0 1px;\">
		<div class=\"content tr\">
			<div class=\"title td\" style=\"width: 100%\">
				<div style=\"min-width: 300px; width: 100%;\">$detailDIV</div>
			</div>
			<div class=\"visual td\">$msgDIV</div>
			<div class=\"visual td\">$linkDIV</div>
		</div>
		<div class=\"extended empty\">$extendedDIV</div>
		<div class=\"childContainers empty\">$childDIV</div>
	</div>";
		};
		
   		$missedCallsCounter = 0;
        $messageCounter = 0;
		$showMessages=$this->ReadPropertyBoolean('ShowMessageButton');
		$showDial=$this->ReadPropertyBoolean('ShowDialButton');
		$headRedered=!$this->ReadPropertyBoolean('ShowHeader');
		$HTML='';
		$hookURL="/hook/".__CLASS__.$this->InstanceID."/";
		if($showMessages){
	        $HTML.= "<script>
	                    function playMessage(obj) {
	                        var messagePath = window.location.origin+'$hookURL'+obj.getAttribute('data-messagepath')+'&cmd=message';
	                        console.warn(messagePath);
	                        
	                        if(messagePath.length > 0) {
	                            var audio = new Audio(messagePath);
	                            audio.play();
	                        }
	                    }
	                 </script>";
        }
        if($showDial)$HTML.= $RenderDialScript();
        $maxCaller = $this->ReadPropertyInteger('CallLines');
		if($maxCaller<1)$maxCaller=1;
        if($callList != null) {    
	        $columns = explode(",", str_replace(" ", "", IPS_GetProperty($this->InstanceID, "Columns")));
	        $colcount=count($columns);
	        $columnWidth = 100/$colcount."%";
            $props=['',$this->ReadPropertyBoolean("CallType_1"),$this->ReadPropertyBoolean("CallType_2"),
            		$this->ReadPropertyBoolean("CallType_3"),$this->ReadPropertyBoolean("CallType_4"),
            		$this->ReadPropertyBoolean("CallType_5"),$this->ReadPropertyBoolean("CallType_6"),
            		false,false,$this->ReadPropertyBoolean("CallType_9"),
            		$this->ReadPropertyBoolean("CallType_10"),$this->ReadPropertyBoolean("CallType_11")
            ];
            
            foreach ($callList as $call) {
                $proceed = false;
                if($props[1] && $call->Type == 1)
                    $proceed = true;
                else if($props[2] && $call->Type == 2) {
                    $missedCallsCounter++;
                    $proceed = true;
                }else if($props[3] && $call->Type == 3)
                    $proceed = true;
                else if($props[4] && $call->Type == 4) {
                    $messageCounter++;
                    $proceed = true;
                }else if($props[5] && $call->Type == 5)
                    $proceed = true;
                else if($props[6] && $call->Type == 6)
                    $proceed = true;
                else if($props[9] && $call->Type == 9)
                    $proceed = true;
                else if($props[10] && $call->Type == 10) {
                    $missedCallsCounter++;
                    $proceed = true;
                }else if($props[11] && $call->Type == 11)
                    $proceed = true;
                if(!$proceed || $maxCaller<1)
                    continue;
				$maxCaller--;
                      
                $rowIcon = "";
                switch ($call->Type) {
                    case '1':
                        $rowIcon = "ipsIconHollowArrowRight";
                        break;
                    case '2':
                        $rowIcon = "ipsIconCross";
                        break;
                    case '3':
                        $rowIcon = "ipsIconHollowArrowLeft";
                        break;
                    case '4':
                        $rowIcon = "ipsIconMail";
                        break;
                    case '5':
                        $rowIcon = "ipsIconMail";
                        break;
                    case '6':
                        $rowIcon = "ipsIconMail";
                        break;
                    case '9':
                        $rowIcon = "ipsIconHollowArrowRight";
                        break;
                    case '10':
                        $rowIcon = "ipsIconCross";
                        break;
                    case '11':
                        $rowIcon = "ipsIconHollowArrowLeft";
                        break;
                }
                $detailDIV = "";
                $i=1;
			
                
                $callname=empty(trim((string)$call->Name))?'Unknown':(string)$call->Name;
                $callnumber=trim((string)$call->Caller);
// 		
                $head='';
                foreach ($columns as $column) {
                    $style=($i <$colcount)?
                        "float: left; width: $columnWidth; overflow-x: hidden; margin-right: 10px;" :
                    	"float: none; width: auto; overflow-x: hidden;";
                    $as='';
                    switch (strtolower($column)) {
                        case 'icon'	 : 
                        	$detailDIV .= "<div class=\"icon td $rowIcon\" style=\"$style height: 35px; width: 35px !important;\"></div>"; 
                        	if(!$headRedered)$head.="<div style=\"$style overflow: hidden;height: 35px; width: 35px !important;\">".$this->Translate($column)."</div>";
                        	break;
                        case 'name'	 : 
                        	$detailDIV .= "<div style=\"$style\">$callname</div>"; 
                        	if(!$headRedered)$head.="<div style=\"$style overflow: hidden;\">".$this->Translate($column)."</div>";
                        	break;
                        case 'date'	 : 
                        	$as='width: 120px !important;';
                        case 'caller': 
                        	if(empty($as))$as='width: 150px !important;';
                        	
                        default: 
                        	
                        	$detailDIV .= "<div style=\"$style $as\">{$call->$column}</div>";
                        	if(!$headRedered)$head.="<div style=\"$style $as overflow: hidden;\">".$this->Translate($column)."</div>";
                        	
                    }
                    $i++;
                }
                
                $dialDIV=$showDial?$RenderDialDiv($callnumber):'';
                $msgDIV=$showMessages?$RenderMesageDiv($call,true):'';
                if(!$headRedered){
					$m=$showMessages?$RenderMesageDiv($call,false):'';            
                	$d=$showDial?$RenderDialDiv(0):'';
               		$HTML.=$RenderHTML($head, $m,$d);    
                	$headRedered=true;
                }
                
                $HTML .=$RenderHTML($detailDIV, $msgDIV,$dialDIV,'');            }    
        }
        $this->SetValueByIdent("MISSED", $missedCallsCounter);
        $this->SetValueByIdent("MESSAGES", $messageCounter);
        $this->SetValueByIdent("CALLLIST", $HTML);
    }
	private function doDecodeCall($data){
		if($this->GetStatus()!=102){
			IPS_LogMessage(IPS_GetName($this->InstanceID),sprintf($this->Translate('Error Instance not ready, skip call info => %S'),$data) );
			return;
		}
		if(!($cfg=json_decode($this->GetBuffer('CALL_INFO'),true)))$cfg=[null,null,0,0,0];
		$arr =explode(';',$data.';');
		array_shift($arr);//$date =array_shift($arr); 
		$cmd =array_shift($arr);
		$line =array_shift($arr);
		$txt=$msnInfo=$nameInfo=null;
		switch($cmd){
			case 'RING':
				$quelle=array_shift($arr);
				$ziel=array_shift($arr);
				$txt=sprintf($this->Translate( "Call from %s for %s"),$quelle,$ziel);
				
			case 'CALL':	
				if(empty($txt)){
					array_shift($arr);
					$ziel=array_shift($arr);
					$quelle=array_shift($arr);
					$txt=sprintf($this->Translate("Call from %s to %s"),$quelle,$ziel);
				}
		// 		if($nameInfo=self::PersonInfo($quelle))$a.=" ({$nameInfo['name']})";
		// 		if($msnInfo=self::MsnInfo($ziel))$b.=" ({$msnInfo['name']})";
		 		$cfg[$line]=[$nameInfo, $msnInfo, $quelle,$ziel,$cmd];
				break;
			case 'CONNECT':
				array_shift($arr); // Unknown Value 10
				$txt=$this->Translate("%s connected with %s");
			case 'DISCONNECT': 
				list($nameInfo, $msnInfo, $quelle, $ziel,$ident)=$cfg[$line];
				if(!$txt){
					$dauer=(int)array_shift($arr);
					if($dauer > 3600)$dauer-=3600;
					$dauer=$dauer > 3600 ? date('h:i:s',$dauer):date('i:s',$dauer);
					$txt=$this->Translate($ident=='RING'?'Incoming':'Outgoing')." ".sprintf($this->Translate("connection %s with %s closed. Duration %s"),$quelle,$ziel,$dauer);
					unset($cfg[$line]);
				}	
				$a=$quelle;	$b=$ziel;
				if($nameInfo)$a.=" ({$nameInfo['name']})";
				if($msnInfo)$b.=" ({$msnInfo['name']})";
				$txt=sprintf($txt,$a,$b);
				break;
			
		}
		$this->SendDebug(__FUNCTION__, $txt, 0);
		if($cmd!='CONNECT')$this->SetBuffer('CALL_INFO',json_encode($cfg));
		
		if($maxLines=$this->ReadPropertyInteger('DialLines')){	
		
			if($line < $maxLines){
				$this->SetValueByIdent('LINE_'.$line, $txt);
			}
		}
			
	
		if( ($cmd=='RING' && $this->ReadPropertyBoolean('UpdateOnRing')) || 
			($cmd=='CALL' && $this->ReadPropertyBoolean('UpdateOnCall')) ||
			($cmd=='CONNECT' && $this->ReadPropertyBoolean('UpdateOnConnect')) ||
			($cmd=='DISCONNECT' && $this->ReadPropertyBoolean('UpdateOnDisconnect'))
		) $this->RunUpdate();

			
		
		

		return $txt;
	}
}
CONST
	PROP_CALLLIST  		= 16,
	PROP_MISSED  		= 32,
	PROP_MESSAGES  		= 64,

	PROP_LINE_0 = 128,
	PROP_LINE_1 = 256,
	PROP_LINE_2 = 512,
	PROP_LINE_3 = 1024,
	PROP_LINE_4 = 2048,
 	PROP_LINE_5 = 4096;

?>