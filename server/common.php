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

function update_bind($host, $record, $ip) {
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

