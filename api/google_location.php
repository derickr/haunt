<?php
class GoogleLocationProvider
{
	const NM = "org.freedesktop.NetworkManager";

	function __construct( Dbus $d )
	{
		$this->d = $d;
	}

	function getWifiTowers()
	{
		$n = $this->d->createProxy( self::NM, "/org/freedesktop/NetworkManager", self::NM);
		$wifi = array();
		foreach ($n->GetDevices()->getData() as $device)
		{
			$device = $device->getData();
			$dev = $this->d->createProxy( self::NM, $device, "org.freedesktop.DBus.Properties");
			$type = $dev->Get(self::NM . ".Device", "DeviceType")->getData();
			if ( $type == 2 ) // WI-FI
			{
				$wifiDev = $this->d->createProxy(self::NM, $device, self::NM . ".Device.Wireless");
				foreach( $wifiDev->GetAccessPoints()->getData() as $ap )
				{
					$apDev = $this->d->createProxy(self::NM, $ap->getData(), "org.freedesktop.DBus.Properties");
					$props = $apDev->GetAll(self::NM . ".AccessPoint")->getData();
					$ssid = '';
					foreach( $props['Ssid']->getData()->getData() as $n )
					{
						$ssid .= chr($n);
					}
					$wifi[] = array('age' => 0, 'signal_strength' => 0 - rand( 60, 101 ), 'ssid' => $ssid, "mac_address" =>  $props['HwAddress']->getData() );
				}
			}
		}
		return array( $wifi[1] );
	}

	function doRequest( $wifi )
	{
		$request = array( 'access_token' => '2:4J2TDruoDO-u9GnB:X7TaSxWBCP1kYuGM', 'version' => '1.1.0', 'host' => 'derickrethans.nl', 'wifi_towers' => $wifi, "radio_type" => "unknown", "request_address" => false );

		$c = curl_init();
		curl_setopt( $c, CURLOPT_URL, 'https://www.google.com/loc/json' );
		curl_setopt( $c, CURLOPT_POST, 1 );
		curl_setopt( $c, CURLOPT_POSTFIELDS, json_encode( $request ) );
		curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $c, CURLOPT_HTTPHEADER, array ( "Content-Type: application/json", "User-Agent: Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US) AppleWebKit/534.16 (KHTML, like Gecko) Chrome/10.0.648.119 Safari/534.16" ) );
		var_dump (curl_exec( $c ) );
		return json_decode( curl_exec( $c ) )->location;
	}

	function doNewRequest( $wifi )
	{
		$url = "https://maps.googleapis.com/maps/api/browserlocation/json?browser=firefox&sensor=true&wifi=mac:{$wifi[0]['mac_address']}|ssid:{$wifi[0]['ssid']}|ss:{$wifi[0]['signal_strength']}";
		$result = json_decode( file_get_contents( $url ) );

		$loc = new stdClass;
		$loc->latitude = $result->location->lat;
		$loc->longitude = $result->location->lng;

		return $loc;
	}

	function getPosition()
	{
		$wifi = $this->GetWifiTowers();
		return $this->doNewRequest( $wifi );
		return $this->doRequest( $wifi );
	}
}
?>
