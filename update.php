<?php
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
	// TODO
	/*
	global $config;

	$report_email = $config['error_mails_rcpt'];
	$email_from = $config['error_mails_from'];

	ob_start();
	require(dirname(__FILE__) . '/mail_db_error.php');
	$message = ob_get_contents();
	ob_end_clean();

	$headers = "From: $email_from\n";
	$headers .= "Content-Type: text/plain; charset = \"UTF-8\";\n";
	$headers .= "Content-Transfer-Encoding: 8bit\n";

	$subject = 'Database error';

	mail($report_email, $subject, $message, $headers);
	*/
	header('HTTP/1.1 500 Internal Server Error');
	echo "A database error has just occurred. Please don't freak out, the administrator has already been notified.";
	die();
}

require_once(dirname(__FILE__) . '/config.php');
$db = new PDO("mysql:dbname=$db_name;host=$db_host;port=$db_port", $db_username, $db_password);
db_query('SET NAMES utf8');

if(isset($_REQUEST['TZOName'])) {
	foreach(array('TZOName', 'Email', 'TZOKey', 'IPAddress') as $key) {
		if(!isset($_REQUEST[$key])) {
			header('HTTP/1.0 400 Bad Request');
			die();
		}
	}

	$host = $_REQUEST['TZOName'];
	$username = $_REQUEST['Email'];
	$password = $_REQUEST['TZOKey'];
	$ip = $_REQUEST['IPAddress'];
}
else if(isset($_REQUEST['system']) && $_REQUEST['system'] == 'custom') {
	$host = $_REQUEST['hostname'];
	$ip = $_REQUEST['myip'];
	$username = $_REQUEST['username'];
	$password = $_REQUEST['password'];
#	$username = $_SERVER['PHP_AUTH_USER'];
#	$password = $_SERVER['PHP_AUTH_PW'];
	echo "$host $ip $username $password";
}
else {
#	header('HTTP/1.0 400 Bad Request');
	die('Bad Request 1');
}

if(!preg_match('/[a-z]+/', $host)) {
#	header('HTTP/1.0 400 Bad Request');
	die('Bad Request 2');
}
if(!preg_match('/[a-zA-Z0-9]+/', $username)) {
	header('HTTP/1.0 401 Unauthorized' . $username);
	die('Bad Request 3');
}
if(!preg_match('/[a-zA-Z0-9]+/', $password)) {
	header('HTTP/1.0 401 Unauthorized');
	die('Bad Request 4');
}
$source_ip = $_SERVER['REMOTE_ADDR'];
if($ip == '') {
	$ip = $source_ip;
}

if(!preg_match('/[0-2]?[0-9]?[0-9]\.[0-2]?[0-9]?[0-9]\.[0-2]?[0-9]?[0-9]\.[0-2]?[0-9]?[0-9]/', $ip)) {
	header('HTTP/1.0 400 Bad Request');
	die('Bad Request');
}

$data = db_query('SELECT id, password FROM accounts WHERE username = ? AND active = 1', array($username));
if(count($data) != 1 || crypt($password, $data[0]['password']) != $data[0]['password']) {
	header('HTTP/1.0 403 Forbidden');
	die('Forbidden');
}
$user_id = $data[0]['id'];

$data = db_query('SELECT id FROM hosts WHERE name = ?', array($host));
if(count($data) != 1) {
	header('HTTP/1.0 404 Not found');
	die('Not found');
}
$host_id = $data[0]['id'];

db_query('INSERT INTO updates (host, user, source_ip, new_ip) VALUES (?, ?, ?, ?)', array($host_id, $user_id, $source_ip, $ip));
db_query('INSERT INTO current (host, ip) VALUES (?, ?) ON DUPLICATE KEY UPDATE ip = ?', array($host_id, $ip, $ip));

$call = "#!/bin/bash\necho -e \"update delete $host.ddns.rueckgr.at A\\nupdate add $host.ddns.rueckgr.at 60 A $ip\\nsend\"|nsupdate\n";
$filename = tempnam('/tmp','nsupdate_');
file_put_contents($filename, $call);
chmod($filename, 0700);
exec($filename);
unlink($filename);
header('HTTP/1.0 200 OK');
die("TZOName=$host IPAddress=$ip Expiration=12/31/2020");

