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

function validate_host($host) {
	if(!preg_match('/^[a-z]+$/', $host)) {
		return false;
	}
	$data = db_query('SELECT id FROM hosts WHERE name = ?', array($host));
	if(count($data) != 1) {
		return false;
	}
	return $data[0]['id'];
}

function validate_ipv4($ip) {
	if(!$ip) {
		return true;
	}

	if(!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
		return false;
	}

	return true;
}

function validate_ipv6($ip6) {
	if(!$ip6) {
		return true;
	}

	if(!filter_var($ip6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
		return false;
	}

	return true;
}

function validate_ip($ip, $ip6) {
	if(!$ip && !$ip6) {
		error_bad_request();
	}
	return validate_ipv4($ip) && validate_ipv6($ip6);
}

function validate_user($username, $password, $host_id) {
	$data = db_query('SELECT a.id id, a.password password
			FROM accounts a
				JOIN accounts_hosts ah ON (a.id = ah.account AND ah.host = ?)
			WHERE username = ?
				AND active = 1', array($host_id, $username));
	if(count($data) != 1 || !password_verify($password, $data[0]['password'])) {
		error_unauthorized();
	}
	return $data[0]['id'];
}

function update_bind($host, $record, $ip) {
	$call = "#!/bin/bash\n";
	$call .= "echo -e \"";
	$call .= "update delete $host.ddns.rueckgr.at $record\\n";
	$call .= "update add $host.ddns.rueckgr.at 60 $record $ip\\n";
	$call .= "send\"|nsupdate 2>&1 \n";
	$filename = tempnam('/tmp','nsupdate_');
	file_put_contents($filename, $call);
	chmod($filename, 0700);
	$output = '';
	$return_code = 0;
	exec($filename, $output, $return_code);
	unlink($filename);
	$complete_output = implode("\n", $output);
	syslog(LOG_INFO, "Return code from nsupdate: $return_code; output: $complete_output");

	return $return_code == 0;
}

require_once(dirname(__FILE__) . '/config.php');
$db = new PDO("mysql:dbname=$db_name;host=$db_host;port=$db_port", $db_username, $db_password);
db_query('SET NAMES utf8');

