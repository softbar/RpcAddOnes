# Zusatz Module
	- FritzStatus		Zeigt verschieden Infos wie externe ip, up-down stream ect  außerdem schalten der ABs ,WLAN, Reboot,Reconnect ... 
	- FritzLog		Zeigt das Fritzbox System log
	- FritzCallmon		Zeigt Anrufliste mit der Möglichkeit AB abzuhören, Aktive anrufe ....
	- FritzHomeAuto		Fritzbox DECT Aktoren, erlaubt das schalten und zeigt werte wie Power,Energie,Temperatur ...  abhängig vom Aktor
	- SamsungTVRemote	Erlaubt das schalten eines Samung TV F-Serie (UE55F6470) übers Netzwerk ohne Rpc,  zb. ganz wichtig TV ausschalten ;-)
	
Nicht benötigte Zusatzmodule können einfach gelöscht werden, werden jedoch während eines Updates neu installiert

Bei Verwendung eines Fritzbox-Zusatzmoduls genügt, beim manuellen erstellen,  die Angabe des Hosts oder der IP. 
Als Beispiel Host = http://fritz.box oder 192.168.178.1


Anmerkung: Um die Konfigdateien der Geräte im Cache klein zu halten , werden nicht benötigte Funktionen und Statusvariablen beim verarbeiten der XML Dateien ignoriert.
Wer tiefer eintauchen möchte findet alles dazu in der discrover.inc Datei, außerdem habe ich die Module im Quellkode (für meine Verhältnisse *lach* ) gut dokumentiert.

# RpcTools

findest du hier <https://github.com/softbar/RpcTools> 

