<?php
require 'config.php';
require 'api/twitter.php';
require 'api/google_location.php';
require 'twitter_settings_search.php';
require 'twitter_settings_filter.php';
require 'twitter_settings.php';
require 'twitter.php';

$s = `xdpyinfo | grep resolution`;
//if ( preg_match( "@284@", $s ) )
//{
//	Gtk::rc_parse(dirname(__FILE__).'/theme-mobile.rc');
//}
//else
{
Gtk::rc_parse(dirname(__FILE__).'/theme-laptop.rc');
}

if ( class_exists( 'Dbus' ) )
{
	$dbus = new Dbus( Dbus::BUS_SYSTEM );
	$soundDbus = $dbus->createProxy( 'org.freesmartphone.odeviced', '/org/freesmartphone/Device/Audio', 'org.freesmartphone.Device.Audio' );
	$gpsPos    = $dbus->createProxy( 'org.freedesktop.Gypsy', '/org/freedesktop/Gypsy', 'org.freedesktop.Gypsy.Position' );
	$gpsDevice = $dbus->createProxy( 'org.freedesktop.Gypsy', '/org/freedesktop/Gypsy', 'org.freedesktop.Gypsy.Device' );

	$wlanPos = new GoogleLocationProvider( $dbus );
}
else
{
	$soundDbus = false;
	$gpsPos    = false;
	$gpsDevice = false;
}
$wlanPos = false;

$window = new Twitter;
$window->show_all();
Gtk::main();
?>
