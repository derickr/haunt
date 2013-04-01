<?php
require '/home/derick/dev/zetacomponents/trunk/Base/src/ezc_bootstrap.php';

if ( $argc == 2 )
{
	$file = $argv[1];
}
else
{
	$file = 'twitter.sqlite';
}

$d = ezcDbFactory::create( 'sqlite://' . dirname( __FILE__ ) . DIRECTORY_SEPARATOR . $file );
?>
