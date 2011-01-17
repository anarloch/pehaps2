<?php

defined('SYSPATH') or die('No direct access allowed.');

return array
(
	'default' => array
	(
		'type'       => 'postgresql',
		'connection' => array(
			'hostname'   => '21637.p.tld.pl',
			'username'   => 'pg37_pehaps',
			'password'   => '4q0nCC24',
			'persistent' => false,
			'database'   => 'pg37_pehaps',
		),
		'primary_key'  => '',   // Column to return from INSERT queries, see #2188 and #2273
		'schema'       => '',
		'table_prefix' => '',
		'charset'      => 'utf8',
		'caching'      => false,
		'profiling'    => true,
	),
);

?>
