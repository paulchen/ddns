<?php

function usage($message = '') {
	if($message) {
		echo "$message\n";
	}
	echo "Usage: update-cli.php <host> {A|AAAA} <ip>\n";
	die(1);
}

require_once(dirname(__FILE__) . '/common.php');

if(!isset($argv)) {
	die(1);
}
if(count($argv) != 4) {
	usage();
}

$host = $argv[1];
$record = $argv[2];
$ip = $argv[3];

if(!validate_host($host)) {
	usage("Invalid or unknown host");
}

switch($record) {
	case 'A':
		if(!$ip || !validate_ipv4($ip)) {
			usage("Invalid IPv4 address");
		}
		break;

	case 'AAAA':
		if(!$ip || !validate_ipv6($ip)) {
			usage("Invalid IPv6 address");
		}
		break;

	default:
		usage("Invalid record type");
}

update_bind($host, $record, $ip);

echo "Update successful\n";

