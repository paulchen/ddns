<?php
# TODO introduce logging
#
# TODO introduce permission system to make sure everyone can only update their own hosts
#
# TODO server-side CLI script for updating IP addresses

function db_query($query, $parameters = array()) {
	global $db;

	if(!($stmt = $db->prepare($query))) {
		$error = $db->errorInfo();
		db_error($error[2], debug_backtrace(), $query, $parameters);
	}
	foreach($parameters as $key => $value) {
		$stmt->bindValue($key+1, $value);
	}
	if(!$stmt->execute()) {
		$error = $stmt->errorInfo();
		db_error($error[2], debug_backtrace(), $query, $parameters);
	}
	$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if(!$stmt->closeCursor()) {
		$error = $stmt->errorInfo();
		db_error($error[2], debug_backtrace(), $query, $parameters);
	}
	return $data;
}

function db_error($error, $stacktrace, $query, $parameters) {
	global $error_mails_from, $error_mails_rcpt;

	$report_email = $error_mails_rcpt;
	$email_from = $error_mails_from;

	ob_start();
	require(dirname(__FILE__) . '/mail_db_error.php');
	$message = ob_get_contents();
	ob_end_clean();

	$headers = "From: $email_from\n";
	$headers .= "Content-Type: text/plain; charset = \"UTF-8\";\n";
	$headers .= "Content-Transfer-Encoding: 8bit\n";

	$subject = 'DDNS :: Database error';

	mail($report_email, $subject, $message, $headers);

	error_internal();
}

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

function validate_host($host) {
	if(!preg_match('/^[a-z]+$/', $host)) {
		error_bad_request();
	}
	$data = db_query('SELECT id FROM hosts WHERE name = ?', array($host));
	if(count($data) != 1) {
		error_not_found();
	}
	return $data[0]['id'];
}

function validate_ipv4($ip) {
	if(!$ip) {
		return;
	}

	if(!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
		error_bad_request();
	}
}

function validate_ipv6($ip6) {
	if(!$ip6) {
		return;
	}

	if(!filter_var($ip6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
		error_bad_request();
	}
}

function validate_ip($ip, $ip6) {
	if(!$ip && !$ip6) {
		error_bad_request();
	}
	validate_ipv4($ip);
	validate_ipv6($ip6);
}

function validate_user($username, $password) {
	$data = db_query('SELECT id, password FROM accounts WHERE username = ? AND active = 1', array($username));
	if(count($data) != 1 || !password_verify($password, $data[0]['password'])) {
		error_unauthorized();
	}
	return $data[0]['id'];
}

function update_host_ipv4($host_id, $user_id, $source_ip, $ip) {
	if(!$ip) {
		return;
	}

	db_query("INSERT INTO updates (host, user, source_ip, new_ip, new_ip6) VALUES (?, ?, ?, ?, '')", array($host_id, $user_id, $source_ip, $ip));
	db_query("INSERT INTO current (host, ip, ip6) VALUES (?, ?, '') ON DUPLICATE KEY UPDATE ip = ?", array($host_id, $ip, $ip));

	update_bind($host_id, 'A', $ip);

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

	update_bind($host_id, 'AAAA', $ip6);

	$data = db_query("SELECT `to` FROM update_dependency WHERE `from` = ? AND ipv6 = 1", array($host_id));
	foreach($data as $row) {
		update_host_ipv4($data['to'], $user_id, $source_ip, $ip6);
	}
}

function update_bind($host_id, $record, $ip) {
	$data = db_query('SELECT name FROM hosts WHERE id = ?', array($host_id));
	$host = $data[0]['name'];

	$call = "#!/bin/bash\n";
	$call .= "echo -e \"";
	$call .= "update delete $host.ddns.rueckgr.at $record\\n";
	$call .= "update add $host.ddns.rueckgr.at 60 $record $ip\\n";
	$call .= "send\"|nsupdate\n";
	$filename = tempnam('/tmp','nsupdate_');
	file_put_contents($filename, $call);
	chmod($filename, 0700);
	exec($filename);
	unlink($filename);
}

require_once(dirname(__FILE__) . '/config.php');
$db = new PDO("mysql:dbname=$db_name;host=$db_host;port=$db_port", $db_username, $db_password);
db_query('SET NAMES utf8');

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

validate_ip($ip, $ip6);
$host_id = validate_host($host);
$user_id = validate_user($username, $password);

update_host_ipv4($host_id, $user_id, $source_ip, $ip);
update_host_ipv6($host_id, $user_id, $source_ip, $ip6);

http_response_code(200);
die('OK');

