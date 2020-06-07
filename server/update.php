<?php
# TODO introduce logging
#
# TODO introduce permission system to make sure everyone can only update their own hosts

require_once(dirname(__FILE__) . '/common.php');

function error_internal() {
	http_response_code(500);
	die('Internal Server Error');
}

function error_unauthorized() {
	header('WWW-Authenticate: Basic realm="DDNS"');
	http_response_code(401);
	die('Unauthorized');
}

function error_bad_request() {
	http_response_code(400);
	die('Bad Request');
}

function error_not_found() {
	http_response_code(404);
	die('Not Found');
}

function get_host_by_id($host_id) {
	$data = db_query('SELECT name FROM hosts WHERE id = ?', array($host_id));
	return $data[0]['name'];
}

function update_host_ipv4($host_id, $user_id, $source_ip, $ip) {
	if(!$ip) {
		return;
	}

	db_query("INSERT INTO updates (host, user, source_ip, new_ip, new_ip6) VALUES (?, ?, ?, ?, '')", array($host_id, $user_id, $source_ip, $ip));
	db_query("INSERT INTO current (host, ip, ip6) VALUES (?, ?, '') ON DUPLICATE KEY UPDATE ip = ?", array($host_id, $ip, $ip));

	update_bind(get_host_by_id($host_id), 'A', $ip);

	$data = db_query("SELECT `to` FROM update_dependency WHERE `from` = ? AND ipv4 = 1", array($host_id));
	foreach($data as $row) {
		update_host_ipv4($row['to'], $user_id, $source_ip, $ip);
	}
}

function update_host_ipv6($host_id, $user_id, $source_ip, $ip6) {
	if(!$ip6) {
		return;
	}

	db_query("INSERT INTO updates (host, user, source_ip, new_ip, new_ip6) VALUES (?, ?, ?, '', ?)", array($host_id, $user_id, $source_ip, $ip6));
	db_query("INSERT INTO current (host, ip, ip6) VALUES (?, '', ?) ON DUPLICATE KEY UPDATE ip6 = ?", array($host_id, $ip6, $ip6));

	update_bind(get_host_by_id($host_id), 'AAAA', $ip6);

	$data = db_query("SELECT `to` FROM update_dependency WHERE `from` = ? AND ipv6 = 1", array($host_id));
	foreach($data as $row) {
		update_host_ipv4($data['to'], $user_id, $source_ip, $ip6);
	}
}

$source_ip = $_SERVER['REMOTE_ADDR'];

$ip = '';
$ip6 = '';
$host = isset($_REQUEST['hostname']) ? $_REQUEST['hostname'] : '';
$username = '';
$password = '';
if(isset($_REQUEST['myip']) || isset($_REQUEST['myip6'])) {
	$ip = isset($_REQUEST['myip']) ? $_REQUEST['myip'] : '';
	$ip6 = isset($_REQUEST['myip6']) ? $_REQUEST['myip6'] : '';
}
else {
	# https://stackoverflow.com/questions/1448871/how-to-know-which-version-of-the-internet-protocol-ip-a-client-is-using-when-c
	if(filter_var($source_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
		$ip6 = $source_ip;
	}
	else {
		$ip = $source_ip;
	}
}

if(isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
	$username = $_SERVER['PHP_AUTH_USER'];
	$password = $_SERVER['PHP_AUTH_PW'];
}
else if(isset($_REQUEST['username']) && isset($_REQUEST['password'])) {	
	$username = $_REQUEST['username'];
	$password = $_REQUEST['password'];
}

if(!$username && !$password) {
	error_unauthorized();
}

if(!validate_ip($ip, $ip6)) {
	error_bad_request();
}
if (!($host_id = validate_host($host))) {
	error_bad_request();
}
if (!($user_id = validate_user($username, $password))) {
	error_unauthorized();
}

update_host_ipv4($host_id, $user_id, $source_ip, $ip);
update_host_ipv6($host_id, $user_id, $source_ip, $ip6);

http_response_code(200);
die('OK');

