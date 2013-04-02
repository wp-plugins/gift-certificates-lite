<?php
include_once('../../../wp-load.php');
include_once(dirname(__FILE__).'/const.php');

header ('Content-type: text/html; charset=utf-8');

if ($giftcertificateslite->check_settings() === true) {
	$cid = $_GET["cid"];
	$cid = preg_replace('/[^a-zA-Z0-9]/', '', $cid);
	$tid = $_GET["tid"];
	$tid = preg_replace('/[^a-zA-Z0-9]/', '', $tid);
	
	if ($giftcertificateslite->use_https == "on" && ($_SERVER["HTTPS"] == "off" || empty($_SERVER["HTTPS"]))) {
		header("Location: ".str_replace("http://", "https://", plugins_url('/images/gcl_show.php', __FILE__)).(!empty($cid) ? '?cid='.$cid : (!empty($tid) ? '?tid='.$tid : '')));
		exit;
	}
	
	if (!empty($tid)) $certificates = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."gcl_certificates WHERE tx_str = '".$tid."' AND deleted = '0'", ARRAY_A);
	else $certificates = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."gcl_certificates WHERE code = '".$cid."' AND deleted = '0'", ARRAY_A);
	print ('
<html>
<head>
<title>Gift Certificates</title>
<style>
body {font-family: arial, verdana; font-size: 13px; color: #000;}
</style>
</head>
<body>');

	if (sizeof($certificates) > 0) {
		$i = 0;
		foreach ($certificates as $row) {
			$description = htmlspecialchars($giftcertificateslite->company_description, ENT_QUOTES);
			$description = str_replace("\n", "<br />", $description);
			$description = str_replace("\r", "", $description);
			if ($row["status"] == GCL_STATUS_DRAFT) $status = '<span style="color: red; font-weight: bold;">NOT PAID</a>';
			else if ($row["status"] == GCL_STATUS_ACTIVE_REDEEMED) $status = '<span style="color: red; font-weight: bold;">REDEEMED</a>';
			else if (time() > $row["registered"] + 24*3600*$giftcertificateslite->validity_period) $status = '<span style="color: red; font-weight: bold;">EXPIRED</a>';
			else if ($row["status"] >= GCL_STATUS_PENDING) $status = '<span style="color: red; font-weight: bold;">BLOCKED</a>';
			else $status = "";
			print ('
		<table style="border: solid 2px #000;width: 600px; margin-bottom: 20px; border-collapse: collapse">
			<tr>
				<td style="padding: 10px; vertical-align: middle; text-align: center; border: 1px solid #000; width: 150px;">
					<!-- <img src="http://chart.apis.google.com/chart?chs=150x150&cht=qr&chld=|1&chl='.rawurlencode(get_bloginfo("wpurl").'/?gcl-certificate='.$row["code"]).'" alt="QR Code" /> -->
					<img src="'.plugins_url('/phpqrcode/qrcode.php?url='.rawurlencode(get_bloginfo("wpurl").'/?gcl-certificate='.$row["code"]), __FILE__).'" alt="QR Code" width="150" height="150" />
					'.$status.'
				</td>
				<td style="padding: 10px; vertical-align: top; border: 1px solid #000;">
					<table style="width: 100%;">
						<tr>
							<td colspan="2" style="font-size: 16px; font-weight: bold; text-align: center; padding-bottom: 10px;">'.htmlspecialchars($giftcertificateslite->title, ENT_QUOTES).'</td>
						</tr>
						<tr>
							<td style="font-weight: bold; padding-bottom: 10px; width: 50%;">Number:</td>
							<td style="padding-bottom: 10px;">'.htmlspecialchars($row['code'], ENT_QUOTES).'</td>
						</tr>
						<tr>
							<td style="font-weight: bold; padding-bottom: 10px;">Valid until:</td>
							<td style="padding-bottom: 10px;">'.date("F j, Y", $row['registered']+24*3600*$giftcertificateslite->validity_period).'</td>
						</tr>
						<tr>
							<td style="font-weight: bold; padding-bottom: 10px;">Price:</td>
							<td style="padding-bottom: 10px;">'.number_format($giftcertificateslite->price, 2, ".", "").' '.$giftcertificateslite->currency.'</td>
						</tr>
						<tr>
							<td style="font-weight: bold; padding-bottom: 10px;">Owner:</td>
							<td style="padding-bottom: 10px;">'.htmlspecialchars($row['recipient'], ENT_QUOTES).'</td>
						</tr>
					</table>
				</td>
			</tr>
			<tr>
				<td colspan="2" style="text-align: center; border: 1px solid #000; padding: 10px;"><span style="font-size:24px;">'.$giftcertificateslite->company_title.'</span><br/>'.$description.'</td>
			</tr>
		</table>');
			$i++;
			if ($i % 2 == 0) print ('<div style="page-break-after: always;"></div>');
		}
	} else {
		print('No certificates found!');
	}
	print ('
</body>
</html>');
}
?>