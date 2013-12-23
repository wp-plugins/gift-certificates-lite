<?php
include_once('../../../wp-load.php');
include_once(dirname(__FILE__).'/const.php');

$request = "cmd=_notify-validate";
foreach ($_POST as $key => $value) {
	$value = urlencode(stripslashes($value));
	$request .= "&".$key."=".$value;
}

		$paypalurl = ($giftcertificateslite->paypal_sandbox == "on" ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr');
		$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, $paypalurl);
					//BOF IPN - HTTP 1.1 LINE ADDED
					curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
					//EOF IPN - HTTP 1.1 LINE ADDED
					curl_setopt($ch, CURLOPT_POST, true);
					curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_HEADER, false);
					curl_setopt($ch, CURLOPT_TIMEOUT, 20);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
					//BOF IPN - HTTP 1.1 LINE ADDED
					curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));
					//EOF IPN - HTTP 1.1 LINE ADDED
		$result = curl_exec($ch);
		curl_close($ch);                
		if (substr(trim($result), 0, 8) != "VERIFIED") die();

		$item_number = stripslashes($_POST['item_number']);
		$item_name = stripslashes($_POST['item_name']);
		$payment_status = stripslashes($_POST['payment_status']);
		$transaction_type = stripslashes($_POST['txn_type']);
		$seller_paypal = stripslashes($_POST['business']);
		$payer_paypal = stripslashes($_POST['payer_email']);
		$gross_total = stripslashes($_POST['mc_gross']);
		$mc_currency = stripslashes($_POST['mc_currency']);
		$first_name = stripslashes($_POST['first_name']);
		$last_name = stripslashes($_POST['last_name']);
		$tx_str = stripslashes($_POST['custom']);
		$tx_str = preg_replace('/[^a-zA-Z0-9]/', '', $tx_str);
		
		$certificates = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."gcl_certificates WHERE tx_str = '".$tx_str."'", ARRAY_A);
		if ($transaction_type == "web_accept" && $payment_status == "Completed")
		{
			if (sizeof($certificates) == 0) $payment_status = "Unrecognized";
			else
			{
				if (empty($seller_paypal)) {
					$tx_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."gcl_transactions WHERE details LIKE '%txn_id=".$txn_id."%' AND payment_status != 'Unrecognized'", ARRAY_A);
					if (intval($tx_details["id"]) != 0) $seller_paypal = $giftcertificateslite->paypal_id;
				}
				if (strtolower($seller_paypal) != strtolower($giftcertificateslite->paypal_id)) $payment_status = "Unrecognized";
				else {
					$total = 0;
					foreach ($certificates as $certificate) {
						$total += floatval($giftcertificateslite->price);
						$currency = $giftcertificateslite->currency;
						$campaign_title = $giftcertificateslite->title;
					}
					if (floatval($gross_total) < $total || $mc_currency != $currency) $payment_status = "Unrecognized";
				}
			}
		}
		$sql = "INSERT INTO ".$wpdb->prefix."gcl_transactions (
			tx_str, payer_name, payer_email, gross, currency, payment_status, transaction_type, details, created) VALUES (
			'".$tx_str."',
			'".mysql_real_escape_string($first_name).' '.mysql_real_escape_string($last_name)."',
			'".mysql_real_escape_string($payer_paypal)."',
			'".floatval($gross_total)."',
			'".$mc_currency."',
			'".$payment_status."',
			'".$transaction_type."',
			'".mysql_real_escape_string($request)."',
			'".time()."'
		)";
		$wpdb->query($sql);
		if ($transaction_type == "web_accept")
		{
			if ($payment_status == "Completed") {
				$sql = "UPDATE ".$wpdb->prefix."gcl_certificates SET 
					status = '".GCL_STATUS_ACTIVE_BYUSER."',
					registered = '".time()."',
					email = '".mysql_real_escape_string($payer_paypal)."',
					blocked = '0'
					WHERE tx_str = '".$tx_str."'";
					
				if ($wpdb->query($sql) !== false) {
					$tags = array("{first_name}", "{last_name}", "{payer_email}", "{certificate_title}", "{certificate_url}", "{price}", "{currency}", "{transaction_date}");
					$vals = array($first_name, $last_name, $payer_paypal, $campaign_title, ($giftcertificateslite->use_https == "on" ? str_replace("http://", "https://", plugins_url('/gcl_show.php', __FILE__)) : plugins_url('/gcl_show.php', __FILE__)).'?tid='.$tx_str, $gross_total, $mc_currency, date("Y-m-d H:i:s")." (server time)");
					$body = str_replace($tags, $vals, $giftcertificateslite->success_email_body);
					$mail_headers = "Content-Type: text/plain; charset=utf-8\r\n";
					$mail_headers .= "From: ".$giftcertificateslite->from_name." <".$giftcertificateslite->from_email.">\r\n";
					$mail_headers .= "X-Mailer: PHP/".phpversion()."\r\n";
					wp_mail($payer_paypal, $giftcertificateslite->success_email_subject, $body, $mail_headers);
					
					$body = str_replace($tags, $vals, "Dear Administrator,\r\n\r\nWe would like to inform you that {first_name} {last_name} ({payer_email}) paid {price} {currency} for gift certificate \"{certificate_title}\". Printable version: {certificate_url}. Payment date: {transaction_date}.\r\n\r\nThanks,\r\nAdministrator");
					$mail_headers = "Content-Type: text/plain; charset=utf-8\r\n";
					$mail_headers .= "From: ".$giftcertificateslite->from_name." <".$giftcertificateslite->from_email.">\r\n";
					$mail_headers .= "X-Mailer: PHP/".phpversion()."\r\n";
					wp_mail($giftcertificateslite->owner_email, "Completed payment received", $body, $mail_headers);
				} else {
					$tags = array("{first_name}", "{last_name}", "{payer_email}", "{certificate_title}", "{certificate_url}", "{price}", "{currency}", "{payment_status}", "{transaction_date}");
					$vals = array($first_name, $last_name, $payer_paypal, $campaign_title, ($giftcertificateslite->use_https == "on" ? str_replace("http://", "https://", plugins_url('/gcl_show.php', __FILE__)) : plugins_url('/gcl_show.php', __FILE__)).'?tid='.$tx_str, $gross_total, $mc_currency, "Server fail", date("Y-m-d H:i:s")." (server time)");
					$body = str_replace($tags, $vals, $giftcertificateslite->failed_email_body);
					$mail_headers = "Content-Type: text/plain; charset=utf-8\r\n";
					$mail_headers .= "From: ".$giftcertificateslite->from_name." <".$giftcertificateslite->from_email.">\r\n";
					$mail_headers .= "X-Mailer: PHP/".phpversion()."\r\n";
					wp_mail($payer_paypal, $giftcertificateslite->failed_email_subject, $body, $mail_headers);

					$body = str_replace($tags, $vals, "Dear Administrator,\r\n\r\nWe would like to inform you that {first_name} {last_name} ({payer_email}) paid {price} {currency} for gift certificate \"{certificate_title}\". Printable version: {certificate_url}. Payment date: {transaction_date}. The payment was completed. But some server fails exists. Please activate certificate manually.\r\n\r\nThanks,\r\nAdministrator");
					$mail_headers = "Content-Type: text/plain; charset=utf-8\r\n";
					$mail_headers .= "From: ".$giftcertificateslite->from_name." <".$giftcertificateslite->from_email.">\r\n";
					$mail_headers .= "X-Mailer: PHP/".phpversion()."\r\n";
					wp_mail($giftcertificateslite->owner_email, "Completed payment received, server fails", $body, $mail_headers);
				}
			} else if ($payment_status == "Failed" || $payment_status == "Pending" || $payment_status == "Processed" || $payment_status == "Unrecognized") {
				$sql = "UPDATE ".$wpdb->prefix."gcl_certificates SET 
					status = '".GCL_STATUS_PENDING_PAYMENT."',
					registered = '".time()."',
					email = '".mysql_real_escape_string($payer_paypal)."',
					blocked = '".time()."'
					WHERE tx_str = '".$tx_str."'";
				$wpdb->query($sql);
				$tags = array("{first_name}", "{last_name}", "{payer_email}", "{certificate_title}", "{certificate_url}", "{price}", "{currency}", "{payment_status}", "{transaction_date}");
				$vals = array($first_name, $last_name, $payer_paypal, $campaign_title, ($giftcertificateslite->use_https == "on" ? str_replace("http://", "https://", plugins_url('/gcl_show.php', __FILE__)) : plugins_url('/gcl_show.php', __FILE__)).'?tid='.$tx_str, $gross_total, $mc_currency, $payment_status, date("Y-m-d H:i:s")." (server time)");

				$body = str_replace($tags, $vals, $giftcertificateslite->failed_email_body);
				$mail_headers = "Content-Type: text/plain; charset=utf-8\r\n";
				$mail_headers .= "From: ".$giftcertificateslite->from_name." <".$giftcertificateslite->from_email.">\r\n";
				$mail_headers .= "X-Mailer: PHP/".phpversion()."\r\n";
				wp_mail($payer_paypal, $giftcertificateslite->failed_email_subject, $body, $mail_headers);

				$body = str_replace($tags, $vals, "Dear Administrator,\r\n\r\nWe would like to inform you that {first_name} {last_name} ({payer_email}) paid {price} {currency} for gift certificate \"{certificate_title}\". Printable version: {certificate_url}. Payment date: {transaction_date}.\r\nPayment status: {payment_status}.\r\n\r\nThanks,\r\nAdministrator");
				$mail_headers = "Content-Type: text/plain; charset=utf-8\r\n";
				$mail_headers .= "From: ".$giftcertificateslite->from_name." <".$giftcertificateslite->from_email.">\r\n";
				$mail_headers .= "X-Mailer: PHP/".phpversion()."\r\n";
				wp_mail($giftcertificateslite->owner_email, "Non-completed payment received", $body, $mail_headers);
			}
		}
?>