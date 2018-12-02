<?php
require 'vendor/autoload.php';

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
