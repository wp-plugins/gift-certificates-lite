<?php
/*
Plugin Name: WP Gift Certificate Lite
Plugin URI: https://www.wpgiftcertificatereloaded.com/wp-gift-certificate-reloaded-modular
Description: This plugin allows you to sell printable Gift Certificates Lite as well as manage sold Gift Certificates Lite. Payments are handled and accepted through paypal. A visitor can place up to 10 names per transaction. The certificates are QR code encoded. Use shortcode: [giftcertificateslite].
Version: 1.290
Author: GC Development Team
Author URI: http://www.wpgiftcertificatereloaded.com/
*/
include_once(dirname(__FILE__).'/const.php');
wp_enqueue_script("jquery");
register_activation_hook(__FILE__, array("giftcertificateslite_class", "install"));

class giftcertificateslite_class
{
	var $options;
	var $error;
	var $info;
	
	var $exists;
	var $enable_paypal;
	var $paypal_id;
	var $paypal_sandbox;
	var $use_https;
	var $title;
	var $description;
	var $price;
    var $currency;
	var $validity_period;
	var $owner_email;
	var $from_name;
	var $from_email;
	var	$success_email_subject;
	var $success_email_body;
	var $failed_email_subject;
	var $failed_email_body;
	var $company_title;
	var $company_description;
	var $terms;
	
	var $default_options;
	
	function __construct() {
        $this->options = array(
            "exists",
            "enable_paypal",
            "paypal_id",
            "paypal_sandbox",
            "title",
            "description",
            "price",
            "currency",
            "validity_period",
            "use_https",
            "owner_email",
            "from_name",
            "from_email",
            "success_email_subject",
            "success_email_body",
            "failed_email_subject",
            "failed_email_body",
            "company_title",
            "company_description",
            "terms"
        );
        $this->default_options = array(
            "exists" => 1,
            "enable_paypal" => "on",
            "paypal_id" => "sales@" . str_replace("www.", "", $_SERVER["SERVER_NAME"]),
            "paypal_sandbox" => "off",
            "title" => "Gift Certificate",
            "description" => "",
            "price" => "10.00",
            "currency" => "USD",
            "validity_period" => 365,
            "use_https" => "off",
            "owner_email" => "admin@" . str_replace("www.", "", $_SERVER["SERVER_NAME"]),
            "from_name" => get_bloginfo("name"),
            "from_email" => "noreply@" . str_replace("www.", "", $_SERVER["SERVER_NAME"]),
            "success_email_subject" => "Gift certificate successfully purchased",
            "success_email_body" => "Dear {first_name},\r\n\r\nThank you for purchasing gift certificate(s) \"{certificate_title}\". Please find printable version here:\r\n{certificate_url}\r\n\r\nThanks,\r\nAdministration of " . get_bloginfo("name"),
            "failed_email_subject" => "Payment not completed",
            "failed_email_body" => "Dear {first_name},\r\n\r\nWe would like to inform you that we received payment from you.\r\nPayment status: {payment_status}\r\nOnce the payment is completed and cleared, we send gift certificate to you.\r\n\r\nThanks,\r\nAdministration of " . get_bloginfo("name"),
            "company_title" => get_bloginfo("name"),
            "company_description" => get_bloginfo("name"),
            "terms" => "Insert your own Terms & Conditions here. For example:\r\n1. You can declare that gift certificates are refundable, but some restrictions may apply.\r\n2. It is allowed to change certificate owner name and explain how to do this.\r\netc..."
        );

		if (!empty($_COOKIE["giftcertificateslite_error"]))
		{
			$this->error = stripslashes($_COOKIE["giftcertificateslite_error"]);
			setcookie("giftcertificateslite_error", "", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
		}
		if (!empty($_COOKIE["giftcertificateslite_info"]))
		{
			$this->info = stripslashes($_COOKIE["giftcertificateslite_info"]);
			setcookie("giftcertificateslite_info", "", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
		}

		$this->get_settings();

		if (is_admin()) {
			if ($this->check_settings() !== true) add_action('admin_notices', array(&$this, 'admin_warning'));
			add_action('admin_menu', array(&$this, 'admin_menu'));
			add_action('init', array(&$this, 'admin_request_handler'));
			add_action('admin_head', array(&$this, 'admin_header'), 15);
		} else {
			add_action('init', array(&$this, 'front_init'));
			add_action("wp_head", array(&$this, "front_header"));
			add_shortcode('giftcertificateslite', array(&$this, "shortcode_handler"));
		}
	}

	function install () {
		global $wpdb;

		$table_name = $wpdb->prefix . "gcl_certificates";
		//if($wpdb->get_var("show tables like '".$table_name."'") != $table_name)
		//{
			$sql = "CREATE TABLE " . $table_name . " (
				id int(11) NOT NULL auto_increment,
				tx_str varchar(31) collate utf8_unicode_ci NOT NULL,
				code varchar(31) collate utf8_unicode_ci NOT NULL,
				recipient varchar(255) collate utf8_unicode_ci NOT NULL,
				email varchar(255) collate utf8_unicode_ci NOT NULL,
				price float NOT NULL,
				currency varchar(15) collate utf8_unicode_ci NOT NULL,
				status int(11) NOT NULL,
				registered int(11) NOT NULL,
				blocked int(11) NOT NULL,
				deleted int(11) NULL,
				UNIQUE KEY  id (id)
			);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		//}
		$table_name = $wpdb->prefix . "gcl_transactions";
		if($wpdb->get_var("show tables like '".$table_name."'") != $table_name)
		{
			$sql = "CREATE TABLE " . $table_name . " (
				id int(11) NOT NULL auto_increment,
				tx_str varchar(31) collate utf8_unicode_ci NOT NULL,
				payer_name varchar(255) collate utf8_unicode_ci NOT NULL,
				payer_email varchar(255) collate utf8_unicode_ci NOT NULL,
				gross float NOT NULL,
				currency varchar(15) collate utf8_unicode_ci NOT NULL,
				payment_status varchar(31) collate utf8_unicode_ci NOT NULL,
				transaction_type varchar(31) collate utf8_unicode_ci NOT NULL,
				details text collate utf8_unicode_ci NOT NULL,
				created int(11) NOT NULL,
				UNIQUE KEY  id (id)
			);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		}
	}

	function get_settings() {
		$exists = get_option('giftcertificateslite_exists');
		if ($exists != 1)
		{
			foreach ($this->options as $option) {
				$this->$option = $this->default_options[$option];
			}
		}
		else
		{
			foreach ($this->options as $option) {
				$this->$option = get_option('giftcertificateslite_'.$option);
			}
		}
		//if (empty($this->enable_paypal)) $this->enable_paypal = $this->default_options["enable_paypal"];
		$this->enable_paypal = "on";
		if (empty($this->paypal_sandbox)) $this->paypal_sandbox = $this->default_options["paypal_sandbox"];
	}

	function update_settings() {
		if (current_user_can('manage_options')) {
			foreach ($this->options as $option) {
				update_option('giftcertificateslite_'.$option, $this->$option);
			}
		}
	}

	function populate_settings() {
		foreach ($this->options as $option) {
			if (isset($_POST['giftcertificateslite_'.$option])) {
				$this->$option = stripslashes($_POST['giftcertificateslite_'.$option]);
			}
		}
	}

	function check_settings() {
		$errors = array();
		if ($this->enable_paypal == "on")
		{
			if (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i", $this->paypal_id) || strlen($this->paypal_id) == 0) $errors[] = "PayPal ID must be valid e-mail address";
		}
		if (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i", $this->owner_email) || strlen($this->owner_email) == 0) $errors[] = "Admin e-mail must be valid e-mail address";
		if (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i", $this->from_email) || strlen($this->from_email) == 0) $errors[] = "Sender e-mail must be valid e-mail address";
		if (strlen($this->title) < 3) $errors[] = "Certificate title is too short";
		if (!is_numeric($this->price) || floatval($this->price) <= 0) $errors[] = "Certificate price is invalid";
		if (!is_numeric($this->validity_period) || floatval($this->validity_period) <= 0) $errors[] = "Validity period is invalid";
		if (strlen($this->from_name) < 3) $errors[] = "Sender name is too short";
		if (strlen($this->success_email_subject) < 3) $errors[] = "Successful payment e-mail subject must contain at least 3 characters";
		else if (strlen($this->success_email_subject) > 64) $errors[] = "Successful payment e-mail subject must contain maximum 64 characters";
		if (strlen($this->success_email_body) < 3) $errors[] = "Successful payment e-mail body must contain at least 3 characters";
		if (strlen($this->failed_email_subject) < 3) $errors[] = "Failed payment e-mail subject must contain at least 3 characters";
		else if (strlen($this->failed_email_subject) > 64) $errors[] = "Failed payment e-mail subject must contain maximum 64 characters";
		if (strlen($this->failed_email_body) < 3) $errors[] = "Failed payment e-mail body must contain at least 3 characters";

		if (empty($errors)) return true;
		return $errors;
	}

	function admin_menu() {
		if (get_bloginfo('version') >= 3.0) {
			define("gcl_PERMISSION", "add_users");
		}
		else{
			define("gcl_PERMISSION", "edit_themes");
		}	
		add_menu_page(
			"Gift Certificates Lite"
			, "Gift Cert Lite"
			, gcl_PERMISSION
			, "gc-lite"
			, array(&$this, 'admin_settings')
		);
		add_submenu_page(
			"gc-lite"
			, "Settings"
			, "Settings"
			, gcl_PERMISSION
			, "gc-lite"
			, array(&$this, 'admin_settings')
		);
		add_submenu_page(
			"gc-lite"
			, "Certificates"
			, "Certificates"
			, gcl_PERMISSION
			, "gc-lite-certificates"
			, array(&$this, 'admin_certificates')
		);
		add_submenu_page(
			"gc-lite"
			, "Add Certificate"
			, "Add Certificate"
			, gcl_PERMISSION
			, "gc-lite-add"
			, array(&$this, 'admin_add_certificate')
		);
		add_submenu_page(
			"gc-lite"
			, "Transactions"
			, "Transactions"
			, gcl_PERMISSION
			, "gc-lite-transactions"
			, array(&$this, 'admin_transactions')
		);
	}

	function admin_settings() {
		global $wpdb;
		$message = "";
		$errors = array();
		if (!empty($this->error)) $message = "<div class='error'><p><strong>ERROR</strong>: ".$this->error."</p></div>";
		else
		{
			$errors = $this->check_settings();
			if (is_array($errors)) echo "<div class='error'><p>The following error(s) exists:<br />- ".implode("<br />- ", $errors)."</p></div>";
		}
		if ($_GET["updated"] == "true")
		{
			$message = '<div class="updated"><p>Plugin settings successfully <strong>updated</strong>.</p></div>';
		}
		print ('
		<div class="wrap admin_giftcertificateslite_wrap">
			<div id="icon-options-general" class="icon32"><br /></div><h2>Gift Certificates Lite - Settings</h2>
			'.$message.'
			<form enctype="multipart/form-data" method="post" style="margin: 0px" action="'.get_bloginfo('wpurl').'/wp-admin/admin.php">
			<div class="postbox-container" style="width: 260px; float: right;">
				<div class="metabox-holder">
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<!--<div class="handlediv" title="Click to toggle"><br></div>-->
							<h3 class="hndle" style="cursor: default;"><span>WP Gift Certificate Modular</span></h3>
							<div class="inside">
								<ul>
									<li style="display: list-item;"><a href="https://www.wpgiftcertificatereloaded.com/wp-gift-certificate-reloaded-modular" target="_blank">Overview of features</a></li>
									<li style="display: list-item;"><a href="https://www.wpgiftcertificatereloaded.com/wp-gift-certificate-reloaded-modular" target="_blank">Checkout our modules</a></li>
									<li style="display: list-item;"><a href="https://www.wpgiftcertificatereloaded.com/wp-gift-certificate-reloaded-modular" target="_blank">Screenshots</a></li>
									</ul>
								<center>
									<a href="https://www.wpgiftcertificatereloaded.com/wp-gift-certificate-reloaded-modular" target="_blank"><img src="'.plugins_url('/images/gift-certificate.jpg', __FILE__).'" alt="WP Gift Certificate Reloaded Plus"></a>
								</center>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="postbox-container" style="margin-right: 280px; float: none;">
				<div class="metabox-holder">
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<!--<div class="handlediv" title="Click to toggle"><br></div>-->
							<h3 class="hndle" style="cursor: default;"><span>General Settings</span></h3>
							<div class="inside">
								<table class="giftcertificateslite_useroptions">
									<tr>
										<th>PayPal ID:</th>
										<td><input type="text" id="giftcertificateslite_paypal_id" name="giftcertificateslite_paypal_id" value="'.htmlspecialchars($this->paypal_id, ENT_QUOTES).'" style="width: 98%;"><br /><em>Please enter valid PayPal e-mail, all payments are sent to this account.</em></td>
									</tr>
									<tr>
										<th>Sandbox mode:</th>
										<td><input type="checkbox" id="giftcertificateslite_paypal_sandbox" name="giftcertificateslite_paypal_sandbox" '.($this->paypal_sandbox == "on" ? 'checked="checked"' : '').'> Enable PayPal sandbox mode<br /><em>Please tick checkbox if you would like to test PayPal service.</em></td>
									</tr>
									<tr>
										<th>Certificate title:</th>
										<td><input type="text" name="giftcertificateslite_title" id="giftcertificateslite_title" value="'.htmlspecialchars($this->title, ENT_QUOTES).'" style="width: 98%;"><br /><em>Enter campaign title. The title is displayed on sign up page and printed on certificate.</em></td>
									</tr>
									<tr>
										<th>Description:</th>
										<td><textarea id="giftcertificateslite_description" name="giftcertificateslite_description" style="width: 98%; height: 120px;">'.htmlspecialchars($this->description, ENT_QUOTES).'</textarea><br /><em>Describe campaign. The description is displayed on sign up page.</em></td>
									</tr>
									<tr>
										<th>Price:</th>
										<td>
										<input type="text" name="giftcertificateslite_price" id="giftcertificateslite_price" value="'.htmlspecialchars($this->price, ENT_QUOTES).'" style="width: 60px; text-align: right;">
										<select name="giftcertificateslite_currency" id="giftcertificateslite_currency">
                                            <option value="AUD"' . (($this->currency == "AUD") ?  "selected":"") .' >AUD</option>
                                            <option value="CAD"' . (($this->currency == "CAD") ?  "selected":"") .' >CAD</option>
					                        <option value="EUR"' . (($this->currency == "EUR") ?  "selected":"") .' >EUR</option>
                                            <option value="GBP"' . (($this->currency == "GBP") ?  "selected":"") .' >GBP</option>
                                            <option value="USD" '.(($this->currency == "USD" || $this->currency == "")? "selected":"").' >USD</option>
										</select>
										<br /><em>Enter price per one gift certificate.</em></td>
									</tr>
									<tr>
										<th>Validity period (days):</th>
										<td><input type="text" name="giftcertificateslite_validity_period" id="giftcertificateslite_validity_period" value="'.htmlspecialchars($this->validity_period, ENT_QUOTES).'" style="width: 60px; text-align: right;"><br /><em>Enter validity period for certificate (days).</em></td>
									</tr>
									<tr>
										<th>Company title:</th>
										<td><input type="text" id="giftcertificateslite_company_title" name="giftcertificateslite_company_title" value="'.htmlspecialchars($this->company_title, ENT_QUOTES).'" style="width: 98%;"><br /><em>Enter the title of your company. The title is placed on gift certificate.</em></td>
									</tr>
									<tr>
										<th>Company description:</th>
										<td><textarea id="giftcertificateslite_company_description" name="giftcertificateslite_company_description" style="width: 98%; height: 120px;">'.htmlspecialchars($this->company_description, ENT_QUOTES).'</textarea><br /><em>Describe your company. This text is placed below company title on gift certificate.</em></td>
									</tr>
									<tr>
										<th>Terms & Conditions:</th>
										<td><textarea id="giftcertificateslite_terms" name="giftcertificateslite_terms" style="width: 98%; height: 120px;">'.htmlspecialchars($this->terms, ENT_QUOTES).'</textarea><br /><em>Your customers must be agree with Terms & Conditions before purchasing gif certificate. Leave this field blank if you don\'t need Terms & Conditions box to be shown.</em></td>
									</tr>
									<tr>
										<th>Use HTTPS:</th>
										<td><input type="checkbox" id="giftcertificateslite_use_https" name="giftcertificateslite_use_https" '.($this->use_https == "on" ? 'checked="checked"' : '').'> Display certificate page via HTTPS<br /><em>Do not activate this option if you do not have SSL certificate for your domain.</em></td>
									</tr>
								</table>
								<div class="alignright">
								<input type="hidden" name="ak_action" value="giftcertificateslite_update_settings" />
								<input type="hidden" name="giftcertificateslite_exists" value="1" />
								<input type="submit" class="button-primary" name="submit" value="Update Settings">
								</div>
								<br class="clear">
							</div>
						</div>

						<div class="postbox">
							<!--<div class="handlediv" title="Click to toggle"><br></div>-->
							<h3 class="hndle" style="cursor: default;"><span>E-mail templates</span></h3>
							<div class="inside">
								<table class="giftcertificateslite_useroptions">
									<tr>
										<th>Admin e-mail:</th>
										<td><input type="text" id="giftcertificateslite_owner_email" name="giftcertificateslite_owner_email" value="'.htmlspecialchars($this->owner_email, ENT_QUOTES).'" style="width: 98%;"><br /><em>Please enter your e-mail. All alerts about completed/failed payments are sent to this e-mail address.</em></td>
									</tr>
									<tr>
										<th>Sender name:</th>
										<td><input type="text" id="giftcertificateslite_from_name" name="giftcertificateslite_from_name" value="'.htmlspecialchars($this->from_name, ENT_QUOTES).'" style="width: 98%;"><br /><em>Please enter sender name. All messages are sent using this name as "FROM:" header value.</em></td>
									</tr>
									<tr>
										<th>Sender e-mail:</th>
										<td><input type="text" id="giftcertificateslite_from_email" name="giftcertificateslite_from_email" value="'.htmlspecialchars($this->from_email, ENT_QUOTES).'" style="width: 98%;"><br /><em>Please enter sender e-mail. All messages are sent using this e-mail as "FROM:" header value.</em></td>
									</tr>
									<tr>
										<th>Successful payment e-mail subject:</th>
										<td><input type="text" id="giftcertificateslite_success_email_subject" name="giftcertificateslite_success_email_subject" value="'.htmlspecialchars($this->success_email_subject, ENT_QUOTES).'" style="width: 98%;"><br /><em>In case of successful and cleared payment, your customers receive e-mail message about successful that. This is subject field of the message.</em></td>
									</tr>
									<tr>
										<th>Successful payment e-mail body:</th>
										<td><textarea id="giftcertificateslite_success_email_body" name="giftcertificateslite_success_email_body" style="width: 98%; height: 120px;">'.htmlspecialchars($this->success_email_body, ENT_QUOTES).'</textarea><br /><em>This e-mail message is sent to your customers in case of successful and cleared payment. You can use the following keywords: {first_name}, {last_name}, {payer_email}, {certificate_title}, {certificate_url}.</em></td>
									</tr>
									<tr>
										<th>Failed purchasing e-mail subject:</th>
										<td><input type="text" id="giftcertificateslite_failed_email_subject" name="giftcertificateslite_failed_email_subject" value="'.htmlspecialchars($this->failed_email_subject, ENT_QUOTES).'" style="width: 98%;"><br /><em>In case of pending, non-cleared or fake payment, your customers receive e-mail message about that. This is subject field of the message.</em></td>
									</tr>
									<tr>
										<th>Failed purchasing e-mail body:</th>
										<td><textarea id="giftcertificateslite_failed_email_body" name="giftcertificateslite_failed_email_body" style="width: 98%; height: 120px;">'.htmlspecialchars($this->failed_email_body, ENT_QUOTES).'</textarea><br /><em>This e-mail message is sent to your customers in case of pending, non-cleared or fake payment. You can use the following keywords: {first_name}, {last_name}, {payer_email}, {payment_status}.</em></td>
									</tr>
								</table>
								<div class="alignright">
									<input type="submit" class="button-primary" name="submit" value="Update Settings">
								</div>
								<br class="clear">
							</div>
						</div>
					</div>
				</div>
			</div>
			</form>
		</div>
		');
	}

	function admin_certificates() {
		global $wpdb;

		if (isset($_GET["s"])) $search_query = trim(stripslashes($_GET["s"]));
		else $search_query = "";
		$tmp = $wpdb->get_row("SELECT COUNT(*) AS total FROM ".$wpdb->prefix."gcl_certificates WHERE status != '".GCL_STATUS_DRAFT."' AND deleted='0'".((strlen($search_query) > 0) ? " AND (code LIKE '%".addslashes($search_query)."%' OR recipient LIKE '%".addslashes($search_query)."%' OR email LIKE '%".addslashes($search_query)."%')" : ""), ARRAY_A);
		$total = $tmp["total"];
		$totalpages = ceil($total/GCL_ROWS_PER_PAGE);
		if ($totalpages == 0) $totalpages = 1;
		if (isset($_GET["p"])) $page = intval($_GET["p"]);
		else $page = 1;
		if ($page < 1 || $page > $totalpages) $page = 1;
		$switcher = $this->page_switcher(get_bloginfo("wpurl")."/wp-admin/admin.php?page=gc-lite-certificates".((strlen($search_query) > 0) ? "&s=".rawurlencode($search_query) : ""), $page, $totalpages);

		$sql = "SELECT * FROM ".$wpdb->prefix."gcl_certificates WHERE status != '".GCL_STATUS_DRAFT."' AND deleted='0'".((strlen($search_query) > 0) ? " AND (code LIKE '%".addslashes($search_query)."%' OR recipient LIKE '%".addslashes($search_query)."%' OR email LIKE '%".addslashes($search_query)."%')" : "")." ORDER BY registered DESC LIMIT ".(($page-1)*GCL_ROWS_PER_PAGE).", ".GCL_ROWS_PER_PAGE;
		$rows = $wpdb->get_results($sql, ARRAY_A);
		if (!empty($this->error)) $message = "<div class='error'><p><strong>ERROR</strong>: ".$this->error."</p></div>";
		if (!empty($this->info)) $message = "<div class='updated'><p>".$this->info."</p></div>";

		print ('
			<div class="wrap admin_giftcertificateslite_wrap">
				<div id="icon-upload" class="icon32"><br /></div><h2>Gift Certificates Lite - Certificates</h2><br />
				'.$message.'
				<form action="'.get_bloginfo("wpurl").'/wp-admin/admin.php" method="get" style="margin-bottom: 10px;">
				<input type="hidden" name="page" value="gc-lite-certificates" />
				Search: <input type="text" name="s" value="'.htmlspecialchars($search_query, ENT_QUOTES).'">
				<input type="submit" class="button-secondary action" value="Search" />
				'.((strlen($search_query) > 0) ? '<input type="button" class="button-secondary action" value="Reset search results" onclick="window.location.href=\''.get_bloginfo("wpurl").'/wp-admin/admin.php?page=gc-lite-certificates\';" />' : '').'
				</form>
				<div class="giftcertificateslite_buttons"><a class="button" href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=gc-lite-add">Create New Certificate</a></div>
				<div class="giftcertificateslite_pageswitcher">'.$switcher.'</div>
				<table class="giftcertificateslite_strings">
				<tr>
					<th>Certificate</th>
					<th>Recipient</th>
					<th style="width: 120px;">Actions</th>
				</tr>
		');
		if (sizeof($rows) > 0)
		{
			foreach ($rows as $row)
			{
				$bg_color = "";
				if ($row["status"] == GCL_STATUS_ACTIVE_REDEEMED) $bg_color = "#F0FFF0";
				else if (time() > $row["registered"] + 24*3600*$this->validity_period) $bg_color = "#E0E0E0";
				else if ($row["status"] >= GCL_STATUS_PENDING) $bg_color = "#FFF0F0";
				else if ($row["status"] == GCL_STATUS_ACTIVE_BYADMIN) $bg_color = "#F0F0FF";
				
				if ($row["status"] == GCL_STATUS_ACTIVE_BYUSER || $row["status"] == GCL_STATUS_ACTIVE_BYADMIN) {
					if (time() <= $row["registered"] + 24*3600*$this->validity_period) $expired = "Expires in ".$this->period_to_string($row["registered"] + 24*3600*$this->validity_period - time());
					else $expired = "Expired!";
				} else if ($row["status"] == GCL_STATUS_ACTIVE_REDEEMED) {
					$expired = "Redeemed ".date("Y-m-d", $row["blocked"])."";
				} else $expired = "Blocked ".date("Y-m-d", $row["blocked"])."";
				
				print ('
				<tr'.(!empty($bg_color) ? ' style="background-color: '.$bg_color.';"': '').'>
					<td><strong>'.$row["code"].'</strong>'.(!empty($expired) ? '<br /><em style="font-size: 12px; line-height: 14px;">'.$expired.'</em>' : '').'</td>
					<td>'.htmlspecialchars((empty($row['recipient']) ? 'Unknown recipient' : $row['recipient']), ENT_QUOTES).'<br /><em style="font-size: 12px; line-height: 14px;">'.htmlspecialchars($row['email'], ENT_QUOTES).'</em></td>
					<td style="text-align: center;">
						<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=gc-lite-add&id='.$row['id'].'" title="Edit certificate"><img src="'.plugins_url('/images/edit.png', __FILE__).'" alt="Edit certificate" border="0"></a>
						<a target="_blank" href="'.plugins_url('/gcl_show.php', __FILE__).'?cid='.$row["code"].'" title="Display certificate"><img src="'.plugins_url('/images/certificate.png', __FILE__).'" alt="Display certificate" border="0"></a>
						<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=gc-lite-transactions&tid='.$row['tx_str'].'" title="Payment transactions"><img src="'.plugins_url('/images/transactions.png', __FILE__).'" alt="Payment transactions" border="0"></a>
						'.(((time() <= $row["registered"] + 24*3600*$this->validity_period) && ($row["status"] == GCL_STATUS_ACTIVE_BYUSER || $row["status"] == GCL_STATUS_ACTIVE_BYADMIN)) ? '<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?ak_action=giftcertificateslite_block&id='.$row['id'].'" title="Block certificate" onclick="return giftcertificateslite_submitOperation();"><img src="'.plugins_url('/images/block.png', __FILE__).'" alt="Block certificate" border="0"></a>' : '').'
						'.(((time() <= $row["registered"] + 24*3600*$this->validity_period) && ($row["status"] == GCL_STATUS_ACTIVE_BYUSER || $row["status"] == GCL_STATUS_ACTIVE_BYADMIN)) ? '<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?ak_action=giftcertificateslite_redeem&id='.$row['id'].'" title="Mark certificate as redeemed" onclick="return giftcertificateslite_submitOperation();"><img src="'.plugins_url('/images/redeem.png', __FILE__).'" alt="Mark certificate as redeemed" border="0"></a>' : '').'
						'.(($row["status"] >= GCL_STATUS_PENDING) ? '<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?ak_action=giftcertificateslite_unblock&id='.$row['id'].'" title="Unblock certificate" onclick="return giftcertificateslite_submitOperation();"><img src="'.plugins_url('/images/unblock.png', __FILE__).'" alt="Unblock certificate" border="0"></a>' : '').'
						<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?ak_action=giftcertificateslite_delete&id='.$row['id'].'" title="Delete certificate" onclick="return giftcertificateslite_submitOperation();"><img src="'.plugins_url('/images/delete.png', __FILE__).'" alt="Delete certificate" border="0"></a>
					</td>
				</tr>
				');
			}
		}
		else
		{
			print ('
				<tr><td colspan="4" style="padding: 20px; text-align: center;">'.((strlen($search_query) > 0) ? 'No results found for "<strong>'.htmlspecialchars($search_query, ENT_QUOTES).'</strong>"' : 'List is empty. Click <a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=gc-lite-add">here</a> to create new certificate.').'</td></tr>
			');
		}
		print ('
				</table>
				<div class="giftcertificateslite_buttons"><a class="button" href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=gc-lite-add">Create New Certificate</a></div>
				<div class="giftcertificateslite_pageswitcher">'.$switcher.'</div>
				<div class="giftcertificateslite_legend">
				<strong>Legend:</strong>
					<p><img src="'.plugins_url('/images/edit.png', __FILE__).'" alt="Edit certificate details" border="0"> Edit certificate details</p>
					<p><img src="'.plugins_url('/images/certificate.png', __FILE__).'" alt="Display certificate" border="0"> Display certificate</p>
					<p><img src="'.plugins_url('/images/transactions.png', __FILE__).'" alt="Payment transactions" border="0"> Show payment transactions</p>
					<p><img src="'.plugins_url('/images/redeem.png', __FILE__).'" alt="Mark certificate as redeemed" border="0"> Mark certificate as redeemed</p>
					<p><img src="'.plugins_url('/images/block.png', __FILE__).'" alt="Block certificate" border="0"> Block certificate</p>
					<p><img src="'.plugins_url('/images/unblock.png', __FILE__).'" alt="Unblock certificate" border="0"> Unblock certificate</p>
					<p><img src="'.plugins_url('/images/delete.png', __FILE__).'" alt="Delete certificate" border="0"> Delete certificate</p>
					<br />
					<div style="width: 14px; height: 14px; float: left; border: 1px solid #CCC; margin: 0px 10px 0px 0px;"></div> Active certificate, purchased by customer<br />
					<div style="width: 14px; height: 14px; float: left; border: 1px solid #CCC; margin: 0px 10px 0px 0px; background-color: #F0F0FF;"></div> Active certificate, created by administrator<br />
					<div style="width: 14px; height: 14px; float: left; border: 1px solid #CCC; margin: 0px 10px 0px 0px; background-color: #F0FFF0;"></div> Redeemed certificate<br />
					<div style="width: 14px; height: 14px; float: left; border: 1px solid #CCC; margin: 0px 10px 0px 0px; background-color: #FFF0F0;"></div> Blocked/Pending certificate<br />
					<div style="width: 14px; height: 14px; float: left; border: 1px solid #CCC; margin: 0px 10px 0px 0px; background-color: #E0E0E0;"></div> Expired certificate
				</div>
			</div>
		');
	}

	function admin_add_certificate() {
		global $wpdb;

		unset($id);
		$status = "";
		if (isset($_GET["id"]) && !empty($_GET["id"])) {
			$id = intval($_GET["id"]);
			$certificate_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."gcl_certificates WHERE id = '".$id."' AND deleted = '0'", ARRAY_A);
			if (intval($certificate_details["id"]) == 0) unset($id);
			else {
				$status = "Active, created by user";
				if ($certificate_details["status"] == GCL_STATUS_ACTIVE_REDEEMED) $status = "Redeemed";
				else if (time() > $certificate_details["registered"] + 24*3600*$this->validity_period) $status = "Expired";
				else if ($certificate_details["status"] >= GCL_STATUS_PENDING) $status = "Blocked/Pending";
				else if ($certificate_details["status"] == GCL_STATUS_ACTIVE_BYADMIN) $status = "Active, created by admin";
			}
		}
		$errors = true;
		if (!empty($this->error)) $message = "<div class='error'><p><strong>ERROR</strong>: ".$this->error."</p></div>";
		else if ($errors !== true) {
			$message = "<div class='error'><p>The following error(s) exists:<br />- ".implode("<br />- ", $errors)."</p></div>";
		} else if (!empty($this->info)) $message = "<div class='updated'><p>".$this->info."</p></div>";

		print ('
		<div class="wrap admin_giftcertificateslite_wrap">
			<div id="icon-options-general" class="icon32"><br /></div><h2>Gift Certificates Lite - '.(!empty($id) ? 'Edit certificate' : 'Create new certificate').'</h2>
			'.$message.'
			<form enctype="multipart/form-data" method="post" style="margin: 0px" action="'.get_bloginfo('wpurl').'/wp-admin/admin.php">
			<div class="postbox-container" style="width: 100%;">
				<div class="metabox-holder">
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<!--<div class="handlediv" title="Click to toggle"><br></div>-->
							<h3 class="hndle" style="cursor: default;"><span>'.(!empty($id) ? 'Edit certificate' : 'Create new certificate').'</span></h3>
							<div class="inside">
								<table class="giftcertificateslite_useroptions">
									'.(!empty($id) ? '
									<tr>
										<th>Certificate number:</th>
										<td><strong>'.htmlspecialchars($certificate_details['code'], ENT_QUOTES).'</strong></td>
									</tr>
									<tr>
										<th>Certificate status:</th>
										<td style="padding-bottom: 24px;"><strong>'.$status.'</strong></td>
									</tr>' : '').'
									<tr>
										<th>Recipient:</th>
										<td><input type="text" name="giftcertificateslite_recipient" id="giftcertificateslite_recipient" value="'.htmlspecialchars($certificate_details['recipient'], ENT_QUOTES).'" style="width: 98%;"><br /><em>Enter recipient\'s name.</em></td>
									</tr>
									<tr>
										<th>E-mail:</th>
										<td><input type="text" name="giftcertificateslite_email" id="giftcertificateslite_email" value="'.htmlspecialchars($certificate_details['email'], ENT_QUOTES).'" style="width: 98%;"><br /><em>Enter recipient\'s e-mail.</em></td>
									</tr>
								</table>
								<div class="alignright">
								<input type="hidden" name="ak_action" value="giftcertificateslite_update_certificate" />
								'.(!empty($id) ? '<input type="hidden" name="giftcertificateslite_id" value="'.$id.'" />' : '').'
								<input type="submit" class="button-primary" name="submit" value="Submit details">
								</div>
								<br class="clear">
							</div>
						</div>
					</div>
				</div>
			</div>
			</form>
		</div>');
	}

	function admin_transactions() {
		global $wpdb;
		if (isset($_GET["s"])) $search_query = trim(stripslashes($_GET["s"]));
		else $search_query = "";
		if (isset($_GET["tid"])) $transaction_id = trim(stripslashes($_GET["tid"]));
		else $transaction_id = "";
		$tmp = $wpdb->get_row("SELECT COUNT(*) AS total FROM ".$wpdb->prefix."gcl_transactions WHERE id > 0".(strlen($transaction_id) > 0 ? " AND tx_str = '".$transaction_id."'" : "").((strlen($search_query) > 0) ? " AND (payer_name LIKE '%".addslashes($search_query)."%' OR payer_email LIKE '%".addslashes($search_query)."%')" : ""), ARRAY_A);
		$total = $tmp["total"];
		$totalpages = ceil($total/GCL_ROWS_PER_PAGE);
		if ($totalpages == 0) $totalpages = 1;
		if (isset($_GET["p"])) $page = intval($_GET["p"]);
		else $page = 1;
		if ($page < 1 || $page > $totalpages) $page = 1;
		$switcher = $this->page_switcher(get_bloginfo("wpurl")."/wp-admin/admin.php?page=gc-lite-transactions".((strlen($search_query) > 0) ? "&s=".rawurlencode($search_query) : "").(strlen($transaction_id) > 0 ? "&tid=".$transaction_id : ""), $page, $totalpages);

		$sql = "SELECT * FROM ".$wpdb->prefix."gcl_transactions WHERE id > 0".(strlen($transaction_id) > 0 ? " AND tx_str = '".$transaction_id."'" : "").((strlen($search_query) > 0) ? " AND (payer_name LIKE '%".addslashes($search_query)."%' OR payer_email LIKE '%".addslashes($search_query)."%')" : "")." ORDER BY created DESC LIMIT ".(($page-1)*GCL_ROWS_PER_PAGE).", ".GCL_ROWS_PER_PAGE;
		$rows = $wpdb->get_results($sql, ARRAY_A);

		print ('
			<div class="wrap admin_giftcertificateslite_wrap">
				<div id="icon-edit-pages" class="icon32"><br /></div><h2>Gift Certificates Lite - Transactions</h2><br />
				<form action="'.get_bloginfo("wpurl").'/wp-admin/admin.php" method="get" style="margin-bottom: 10px;">
				<input type="hidden" name="page" value="gc-lite-transactions" />
				'.(strlen($transaction_id) > 0 ? '<input type="hidden" name="tid" value="'.$transaction_id.'" />' : '').'
				Search: <input type="text" name="s" value="'.htmlspecialchars($search_query, ENT_QUOTES).'">
				<input type="submit" class="button-secondary action" value="Search" />
				'.((strlen($search_query) > 0) ? '<input type="button" class="button-secondary action" value="Reset search results" onclick="window.location.href=\''.get_bloginfo("wpurl").'/wp-admin/admin.php?page=gc-lite-transactions'.(strlen($transaction_id) > 0 ? '&tid='.$transaction_id : '').'\';" />' : '').'
				</form>
				<div class="giftcertificateslite_pageswitcher">'.$switcher.'</div>
				<table class="giftcertificateslite_strings">
				<tr>
					<th>Certificates</th>
					<th>Payer</th>
					<th style="width: 100px;">Amount</th>
					<th style="width: 120px;">Status</th>
					<th style="width: 130px;">Created*</th>
				</tr>
		');
		if (sizeof($rows) > 0) {
			foreach ($rows as $row) {
				$certificates = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."gcl_certificates WHERE tx_str = '".$row["tx_str"]."'", ARRAY_A);
				$list = array();
				foreach ($certificates as $certificate) {
					if ($certificate["deleted"] == 0) $list[] = '<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=gc-lite-certificates&s='.$certificate["code"].'">'.$certificate["code"].'</a>';
					else $list[] = '*'.$certificate["code"];
				}
				print ('
				<tr>
					<td>'.implode(", ", $list).'</td>
					<td>'.htmlspecialchars($row['payer_name'], ENT_QUOTES).'<br /><em style="font-size: 12px; line-height: 14px;">'.htmlspecialchars($row['payer_email'], ENT_QUOTES).'</em></td>
					<td style="text-align: right;">'.number_format($row['gross'], 2, ".", "").' '.$row['currency'].'</td>
					<td>'.$row["payment_status"].'<br /><em style="font-size: 12px; line-height: 14px;">'.$row["transaction_type"].'</em></td>
					<td>'.date("Y-m-d H:i:s", $row["created"]).'</td>
				</tr>
				');
			}
		} else {
			print ('
				<tr><td colspan="5" style="padding: 20px; text-align: center;">'.((strlen($search_query) > 0) ? 'No results found for "<strong>'.htmlspecialchars($search_query, ENT_QUOTES).'</strong>"' : 'List is empty.').'</td></tr>
			');
		}
		print ('
				</table>
				<div class="giftcertificateslite_pageswitcher">'.$switcher.'</div>
			</div>');
	}
	
	function admin_request_handler() {
		global $wpdb;
		if (!empty($_POST['ak_action'])) {
			switch($_POST['ak_action']) {
				case 'giftcertificateslite_update_settings':
					$this->populate_settings();
					if (isset($_POST["giftcertificateslite_paypal_sandbox"])) $this->paypal_sandbox = "on";
					else $this->paypal_sandbox = "off";
					if (isset($_POST["giftcertificateslite_use_https"])) $this->use_https = "on";
					else $this->use_https = "off";
					$errors = $this->check_settings();
					if ($errors === true) {
						$this->update_settings();
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=gc-lite&updated=true');
						die();
					} else {
						$this->update_settings();
						$message = "";
						if (is_array($errors)) $message = "The following error(s) occured:<br />- ".implode("<br />- ", $errors);
						setcookie("giftcertificateslite_error", $message, time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=gc-lite');
						die();
					}
					break;
				case "giftcertificateslite_update_certificate":
					unset($id);
					if (isset($_POST["giftcertificateslite_id"]) && !empty($_POST["giftcertificateslite_id"])) {
						$id = intval($_POST["giftcertificateslite_id"]);
						$certificate_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."gcl_certificates WHERE id = '".$id."' AND deleted = '0'", ARRAY_A);
						if (intval($certificate_details["id"]) == 0) unset($id);
					}
					$recipient = trim(stripslashes($_POST["giftcertificateslite_recipient"]));
					$email = trim(stripslashes($_POST["giftcertificateslite_email"]));

					unset($errors);
					if (strlen($recipient) < 2) $errors[] = "recipient's name is too short";
					else if (strlen($recipient) > 128) $errors[] = "recipient's name is too long";
					if (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i", $email) || strlen($email) == 0) $errors[] = "e-mail must be valid e-mail address";

					if (empty($errors)) {
						if (!empty($id)) {
							$sql = "UPDATE ".$wpdb->prefix."gcl_certificates SET 
								recipient = '".mysql_real_escape_string($recipient)."',
								email = '".mysql_real_escape_string($email)."'
								WHERE id = '".$id."'";
							if ($wpdb->query($sql) !== false) {
								setcookie("giftcertificateslite_info", "Certificate successfully updated", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
								header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=gc-lite-certificates');
								die();
							} else {
								setcookie("giftcertificateslite_error", "Service is not available", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
								header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=gc-lite-add&id='.$id);
								die();
							}
						} else {
							$code = $this->generate_certificate();
							$sql = "INSERT INTO ".$wpdb->prefix."gcl_certificates (
								tx_str, code, recipient, email, price, currency, status, registered, blocked, deleted) VALUES (
								'".$code."',
								'".$code."',
								'".mysql_real_escape_string($recipient)."',
								'".mysql_real_escape_string($email)."',
								'0',
								'',
								'".GCL_STATUS_ACTIVE_BYADMIN."',
								'".time()."', '0', '0'
								)";
							if ($wpdb->query($sql) !== false) {
								$message = "Certificate successfully added";
								setcookie("giftcertificateslite_info", $message, time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
								header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=gc-lite-certificates');
								die();
							} else {
								setcookie("giftcertificateslite_error", "Service is not available", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
								header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=gc-lite-add');
								die();
							}
						}
					} else {
						$message = "The following error(s) occured:<br />- ".implode("<br />- ", $errors);
						setcookie("giftcertificateslite_error", $message, time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=gc-lite-add'.(!empty($id) ? "&id=".$id : ""));
						die();
					}
					break;
			}
		}
		if (!empty($_GET['ak_action'])) {
			switch($_GET['ak_action']) {
				case 'giftcertificateslite_delete':
					$id = intval($_GET["id"]);
					$certificate_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix . "gcl_certificates WHERE id = '".$id."' AND deleted = '0'", ARRAY_A);
					if (intval($certificate_details["id"]) == 0) {
						setcookie("giftcertificateslite_error", "Invalid service call", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=gc-lite-certificates');
						die();
					}
					$sql = "UPDATE ".$wpdb->prefix."gcl_certificates SET deleted = '1' WHERE id = '".$id."'";
					if ($wpdb->query($sql) !== false) {
						setcookie("giftcertificateslite_info", "Certificate successfully removed", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=gc-lite-certificates');
						die();
					} else {
						setcookie("giftcertificateslite_error", "Invalid service call", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=gc-lite-certificates');
						die();
					}
					break;

				case 'giftcertificateslite_block':
					$id = intval($_GET["id"]);
					$certificate_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."gcl_certificates WHERE id = '".$id."' AND deleted = '0'", ARRAY_A);
					if (intval($certificate_details["id"]) == 0) {
						setcookie("giftcertificateslite_error", "Invalid service call", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=gc-lite-certificates');
						die();
					}
					if (time() <= $certificate_details["registered"] + 24*3600*$this->validity_period && ($certificate_details["status"] == GCL_STATUS_ACTIVE_BYUSER || $certificate_details["status"] == GCL_STATUS_ACTIVE_BYADMIN)) {
						$sql = "UPDATE ".$wpdb->prefix."gcl_certificates SET status = '".GCL_STATUS_PENDING_BLOCKED."', blocked = '".time()."' WHERE id = '".$id."'";
						$wpdb->query($sql);
						setcookie("giftcertificateslite_info", "Certificate successfully blocked", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=gc-lite-certificates');
						die();
					} else {
						setcookie("giftcertificateslite_error", "You can not block this certificate", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=gc-lite-certificates');
						die();
					}
					break;
					
				case 'giftcertificateslite_unblock':
					$id = intval($_GET["id"]);
					$certificate_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."gcl_certificates WHERE id = '".$id."' AND deleted = '0'", ARRAY_A);
					if (intval($certificate_details["id"]) == 0) {
						setcookie("giftcertificateslite_error", "Invalid service call", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=gc-lite-certificates');
						die();
					}
					if ($certificate_details["status"] == GCL_STATUS_PENDING_PAYMENT || $certificate_details["status"] == GCL_STATUS_PENDING_BLOCKED) {
						if (intval($certificate_details["blocked"]) >= $certificate_details["registered"]) {
							$registered = time() - $certificate_details["blocked"] + $certificate_details["registered"];
						} else $registered = $certificate_details["registered"];
						$sql = "UPDATE ".$wpdb->prefix."gcl_certificates SET status = '".($certificate_details["price"] > 0 ? GCL_STATUS_ACTIVE_BYUSER : GCL_STATUS_ACTIVE_BYADMIN)."', registered = '".$registered."' WHERE id = '".$id."'";
						$wpdb->query($sql);
						setcookie("giftcertificateslite_info", "Certificate successfully unblocked", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=gc-lite-certificates');
						die();
					} else {
						setcookie("giftcertificateslite_error", "You can not unblock this certificate", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=gc-lite-certificates');
						die();
					}
					break;
				case 'giftcertificateslite_redeem':
					$id = intval($_GET["id"]);
					$certificate_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."gcl_certificates WHERE id = '".$id."' AND deleted = '0'", ARRAY_A);
					if (intval($certificate_details["id"]) == 0) {
						setcookie("giftcertificateslite_error", "Invalid service call", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=gc-lite-certificates');
						die();
					}
					if (time() <= $certificate_details["registered"] + 24*3600*$this->validity_period && ($certificate_details["status"] == GCL_STATUS_ACTIVE_BYUSER || $certificate_details["status"] == GCL_STATUS_ACTIVE_BYADMIN)) {
						$sql = "UPDATE ".$wpdb->prefix."gcl_certificates SET status = '".GCL_STATUS_ACTIVE_REDEEMED."', blocked = '".time()."' WHERE id = '".$id."'";
						$wpdb->query($sql);
						setcookie("giftcertificateslite_info", "Certificate successfully marked as redeemed", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=gc-lite-certificates');
						die();
					} else {
						setcookie("giftcertificateslite_error", "You can not mark this certificate as redeemed", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=gc-lite-certificates');
						die();
					}
					break;
				default:
					break;
			}
		}
	}

	function admin_warning() {
		echo '
		<div class="updated"><p><strong>Gift Certificates Lite plugin almost ready.</strong> You must do some <a href="admin.php?page=gc-lite">settings</a> for it to work.</p></div>
		';
	}

	function admin_header() {
		global $wpdb;
		echo '
		<link rel="stylesheet" type="text/css" href="'.plugins_url('/css/style.css', __FILE__).'?ver=1.28" media="screen" />
		<script type="text/javascript">
			function giftcertificateslite_submitOperation() {
				var answer = confirm("Do you really want to continue?")
				if (answer) return true;
				else return false;
			}
		</script>';
	}

	function front_init() {
		global $wpdb;
		if ($_POST["giftcertificateslite_signup_action"] == "yes") {
			header ('Content-type: text/html; charset=utf-8');
			unset($errors);
			$recipients = array();
			for ($i=0; $i<10; $i++) {
				if (isset($_POST["giftcertificateslite_signup_recipient".$i])) {
					$tmp = trim(stripslashes($_POST["giftcertificateslite_signup_recipient".$i]));
					if (strlen($tmp) > 0) $recipients[] = $tmp;
				}
			}
			if (sizeof($recipients) == 0) $errors[] = "please enter at least one recipient's name";
			for ($i=0; $i<sizeof($recipients); $i++) {
				if (strlen($recipients[$i]) > 63) {
					$errors[] = "one of recipient's name is too long";
					break;
				}
			}
			if (!empty($errors)) {
				echo "ERRORS: ".ucfirst(implode(", ", $errors)).".";
				die();
			}
			$price = number_format(sizeof($recipients)*$this->price, 2, ".", "");
			$items = array();
			$tx_str = $this->generate_certificate();
			for ($i=0; $i<sizeof($recipients); $i++) {
				$code = $this->generate_certificate();
				$sql = "INSERT INTO ".$wpdb->prefix."gcl_certificates (
					tx_str, code, recipient, email, price, currency, status, registered, blocked, deleted) VALUES (
					'".$tx_str."',
					'".$code."',
					'".mysql_real_escape_string($recipients[$i])."',
					'',
					'".$this->price."',
					'".$this->currency."',
					'".GCL_STATUS_DRAFT."',
					'".time()."', '".time()."', '0'
					)";
				if ($wpdb->query($sql) !== false) {
					$items[] = htmlspecialchars($recipients[$i], ENT_QUOTES).' <span>(<a target="_blank" href="'.($this->use_https == "on" ? str_replace("http://", "https://", plugins_url('/gcl_show.php', __FILE__)) : plugins_url('/gcl_show.php', __FILE__)).'?cid='.$code.'">certificate preview</a>)</span>';
				}
			}
			if (sizeof($items) == 0) {
				echo "ERRORS: Sevice temporarily not available.";
				die();
			}
			echo '
<div class="giftcertificateslite_confirmation_info">
	<table class="giftcertificateslite_confirmation_table">
		<tr><td style="width: 170px">Gift Certificate:</td><td class="giftcertificateslite_confirmation_data">'.htmlspecialchars($this->title, ENT_QUOTES).' ('.number_format($this->price, 2, ".", "").' '.$this->currency.')'.(strlen($this->description) > 0 ? '<br /><em>'.htmlspecialchars($this->description, ENT_QUOTES).'</em><br />' : '').'</td></tr>
		<tr><td>Expires on:</td><td class="giftcertificateslite_confirmation_data">'.date("F j, Y", time()+24*3600*$this->validity_period).'</td></tr>
		<tr><td>Recipients:</td><td class="giftcertificateslite_confirmation_data">'.implode("<br />", $items).'</td></tr>
		<tr><td>Total price:</td><td class="giftcertificateslite_confirmation_price">'.$price.' '.$this->currency.'</td></tr>
	</table>
	<div class="giftcertificateslite_signup_buttons">
		<input type="button" class="giftcertificateslite_signup_button" id="giftcertificateslite_signup_pay" name="giftcertificateslite_signup_pay" value="Purchase" onclick="jQuery(\'#giftcertificateslite_buynow\').click();">
		<input type="button" class="giftcertificateslite_signup_button" id="giftcertificateslite_signup_edit" name="giftcertificateslite_signup_edit" value="Edit info" onclick="giftcertificateslite_edit();">
	</div>
	<form action="'.(($this->paypal_sandbox == "on") ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr').'" method="post" style="display: none;">
		<input type="hidden" name="cmd" value="_xclick">
		<input type="hidden" name="business" value="'.$this->paypal_id.'">
		<input type="hidden" name="no_shipping" value="1">
		<input type="hidden" name="lc" value="US">
		<input type="hidden" name="rm" value="2">
		<input type="hidden" name="item_name" value="Gift Certificate ('.sizeof($recipients).(sizeof($recipients) > 1 ? ' persons' : ' person').')">
		<input type="hidden" name="item_number" value="1">
		<input type="hidden" name="amount" value="'.$price.'">
		<input type="hidden" name="currency_code" value="'.$this->currency.'">
		<input type="hidden" name="custom" value="'.$tx_str.'">
		<input type="hidden" name="bn" value="PP-BuyNowBF:btn_buynow_LG.gif:NonHostedGuest">
		<input type="hidden" name="return" value="http://'.$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"].'">
		<input type="hidden" name="cancel_return" value="http://'.$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"].'">
		<input type="hidden" name="notify_url" value="'.plugins_url('/paypal_ipn.php', __FILE__).'">
		<input type="submit" id="giftcertificateslite_buynow" value="Submit">
	</form>
	<em>Printable gift certificate'.(sizeof($recipients) > 1 ? 's' : '').' will be sent to your PayPal e-mail.</em>
</div>';
			die();
		} else if (isset($_GET["gcl-certificate"])) {
			$cid = $_GET["gcl-certificate"];
			$cid = preg_replace('/[^a-zA-Z0-9]/', '', $cid);
			$certificate_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."gcl_certificates WHERE code = '".$cid."' AND deleted = '0'", ARRAY_A);
			if (intval($certificate_details["id"]) != 0) {
				if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge|maemo|midp|mmp|opera m(ob|in)i|palm( os)?|phone|pixi|plucker|pocket|psp|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/iu', $_SERVER['HTTP_USER_AGENT']))
					header("Location: ".($this->use_https == "on" ? str_replace("http://", "https://", plugins_url('/gcl_show.php', __FILE__)) : plugins_url('/gcl_show.php', __FILE__)).'?cid='.$certificate_details["code"]);
				else 
					header("Location: ".get_bloginfo("wpurl").'/wp-admin/admin.php?page=gc-lite-certificates&s='.$certificate_details["code"]);
				exit;
			}
		}
	}
	
	function front_header() {
		echo '
		<link rel="stylesheet" type="text/css" href="'.plugins_url('/css/front.css?ver=1.28', __FILE__).'" media="screen" />';
	}

	function shortcode_handler($_atts) {
		global $wpdb;
		$form = "";
		if ($this->check_settings() === true)
		{
			$id = intval($_atts["id"]);
			
			$terms = htmlspecialchars($this->terms, ENT_QUOTES);
			$terms = str_replace("\n", "<br />", $terms);
			$terms = str_replace("\r", "", $terms);

			$form = '
<script type="text/javascript">
function giftcertificateslite_addrecipient() {
	var fields = jQuery(".giftcertificateslite_additional");
	for(i=0; i<fields.length; i++) {
		if (!jQuery(fields[i]).is(":visible")) {
			jQuery(fields[i]).toggle(200);
			if (i == fields.length-1) jQuery(".giftcertificateslite_addrecipient").toggle(200);
			return;
		}
	}
}
function giftcertificateslite_edit() {
	jQuery("#giftcertificateslite_confirmation_container").fadeOut(500, function() {
		jQuery("#giftcertificateslite_signup_form").fadeIn(500, function() {});
	});
}
function giftcertificateslite_presubmit() {
	jQuery("#giftcertificateslite_signup_submit").css("display", "none");
	jQuery("#giftcertificateslite_signup_spinner").css("display", "block");
}
jQuery(document).ready(function() {
	jQuery("#giftcertificateslite_signup_iframe").load(function() {
		var data = jQuery("#giftcertificateslite_signup_iframe").contents().find("html").html();
		if (data.indexOf("ERRORS:") >= 0) {
			jQuery("#giftcertificateslite_signup_errorbox").html(data);
			jQuery("#giftcertificateslite_signup_errorbox").css("display", "block");
			jQuery("#giftcertificateslite_signup_spinner").css("display", "none");
			jQuery("#giftcertificateslite_signup_submit").css("display", "inline-block");
		} else if (data.indexOf("giftcertificateslite_confirmation_info") >= 0) {
			jQuery("#giftcertificateslite_signup_form").fadeOut(500, function() {
				jQuery("#giftcertificateslite_signup_errorbox").css("display", "none");
				jQuery("#giftcertificateslite_signup_spinner").css("display", "none");
				jQuery("#giftcertificateslite_signup_submit").css("display", "inline-block");
				jQuery("#giftcertificateslite_confirmation_container").html(data);
				jQuery("#giftcertificateslite_confirmation_container").fadeIn(500, function() {});
			});
		} else {
			jQuery("#giftcertificateslite_signup_errorbox").css("display", "none");
			jQuery("#giftcertificateslite_signup_spinner").css("display", "none");
			jQuery("#giftcertificateslite_signup_submit").css("display", "inline-block");
		}
	});
});
</script>
<div class="giftcertificateslite_signup_box">
	<div id="giftcertificateslite_confirmation_container"></div>
	<form action="" target="giftcertificateslite_signup_iframe" enctype="multipart/form-data" method="post" id="giftcertificateslite_signup_form" onsubmit="giftcertificateslite_presubmit(); return true;">
	<label class="giftcertificateslite_bigfont">'.htmlspecialchars($this->title, ENT_QUOTES).' ('.number_format($this->price, 2, ".", "").' '.$this->currency.')</label>
	'.(strlen($this->description) > 0 ? '<br /><em>'.htmlspecialchars($this->description, ENT_QUOTES).'</em><br />' : '').'
	<br /><br />
	<label for="giftcertificateslite_signup_string">Recipient\'s name <span>(mandatory)</span></label><br />
	<input type="text" class="giftcertificateslite_signup_long" id="giftcertificateslite_signup_recipient0" name="giftcertificateslite_signup_recipient0" value=""><br />
	<em>Enter recipient\'s name. This name is printed on gift certificate.</em>
	<div class="giftcertificateslite_additional giftcertificateslite_hidden"><input type="text" class="giftcertificateslite_signup_long" id="giftcertificateslite_signup_recipient1" name="giftcertificateslite_signup_recipient1" value=""></div>
	<div class="giftcertificateslite_additional giftcertificateslite_hidden"><input type="text" class="giftcertificateslite_signup_long" id="giftcertificateslite_signup_recipient2" name="giftcertificateslite_signup_recipient2" value=""></div>
	<div class="giftcertificateslite_additional giftcertificateslite_hidden"><input type="text" class="giftcertificateslite_signup_long" id="giftcertificateslite_signup_recipient3" name="giftcertificateslite_signup_recipient3" value=""></div>
	<div class="giftcertificateslite_additional giftcertificateslite_hidden"><input type="text" class="giftcertificateslite_signup_long" id="giftcertificateslite_signup_recipient4" name="giftcertificateslite_signup_recipient4" value=""></div>
	<div class="giftcertificateslite_additional giftcertificateslite_hidden"><input type="text" class="giftcertificateslite_signup_long" id="giftcertificateslite_signup_recipient5" name="giftcertificateslite_signup_recipient5" value=""></div>
	<div class="giftcertificateslite_additional giftcertificateslite_hidden"><input type="text" class="giftcertificateslite_signup_long" id="giftcertificateslite_signup_recipient6" name="giftcertificateslite_signup_recipient6" value=""></div>
	<div class="giftcertificateslite_additional giftcertificateslite_hidden"><input type="text" class="giftcertificateslite_signup_long" id="giftcertificateslite_signup_recipient7" name="giftcertificateslite_signup_recipient7" value=""></div>
	<div class="giftcertificateslite_additional giftcertificateslite_hidden"><input type="text" class="giftcertificateslite_signup_long" id="giftcertificateslite_signup_recipient8" name="giftcertificateslite_signup_recipient8" value=""></div>
	<div class="giftcertificateslite_additional giftcertificateslite_hidden"><input type="text" class="giftcertificateslite_signup_long" id="giftcertificateslite_signup_recipient9" name="giftcertificateslite_signup_recipient9" value=""></div>
	<br /><br />
	<a class="giftcertificateslite_addrecipient" href="#" onclick="giftcertificateslite_addrecipient(); return false;">Add recipient</a>
	<br /><br />';
			if (!empty($this->terms)) $form .= '
	<div id="giftcertificateslite_signup_terms_box" style="display: none;">
	<label for="giftcertificateslite_signup_link">Terms & Conditions</label><br />
	<div class="giftcertificateslite_signup_terms">'.$terms.'</div>
	<br /></div><p class="giftcertificateslite_text">By clicking "Continue" button I agree with <a href="#" onclick="jQuery(\'#giftcertificateslite_signup_terms_box\').toggle(300); return false;">Terms & Conditions</a>.</p>';
			$form .= '	
	<div class="giftcertificateslite_signup_buttons">
		<input type="hidden" name="giftcertificateslite_signup_action" value="yes">
		<input type="submit" class="giftcertificateslite_signup_button" id="giftcertificateslite_signup_submit" name="giftcertificateslite_signup_submit" value="Continue">
		<div id="giftcertificateslite_signup_spinner"></div>
	</div>
	<div id="giftcertificateslite_signup_errorbox"></div>
	</form>
</div>
<iframe id="giftcertificateslite_signup_iframe" name="giftcertificateslite_signup_iframe" style="border: 0px; height: 0px; width: 0px; margin: 0px; padding: 0px;"></iframe>';
		}
		return $form;
	}	
	
	function page_switcher ($_urlbase, $_currentpage, $_totalpages)
	{
		$pageswitcher = "";
		if ($_totalpages > 1)
		{
			$pageswitcher = "<div class='tablenav bottom'><div class='tablenav-pages'>Pages: <span class='pagiation-links'>";
			if (strpos($_urlbase,"?") !== false) $_urlbase .= "&amp;";
			else $_urlbase .= "?";
			if ($_currentpage == 1) $pageswitcher .= "<a class='page disabled'>1</a> ";
			else $pageswitcher .= " <a class='page' href='".$_urlbase."p=1'>1</a> ";

			$start = max($_currentpage-3, 2);
			$end = min(max($_currentpage+3,$start+6), $_totalpages-1);
			$start = max(min($start,$end-6), 2);
			if ($start > 2) $pageswitcher .= " <b>...</b> ";
			for ($i=$start; $i<=$end; $i++)
			{
				if ($_currentpage == $i) $pageswitcher .= " <a class='page disabled'>".$i."</a> ";
				else $pageswitcher .= " <a class='page' href='".$_urlbase."p=".$i."'>".$i."</a> ";
			}
			if ($end < $_totalpages-1) $pageswitcher .= " <b>...</b> ";

			if ($_currentpage == $_totalpages) $pageswitcher .= " <a class='page disabled'>".$_totalpages."</a> ";
			else $pageswitcher .= " <a class='page' href='".$_urlbase."p=".$_totalpages."'>".$_totalpages."</a> ";
			$pageswitcher .= "</span></div></div>";
		}
		return $pageswitcher;
	}
	
	function cut_string($_string, $_limit=40) {
		if (strlen($_string) > $_limit) return substr($_string, 0, $_limit-3)."...";
		return $_string;
	}
	
	function period_to_string($period) {
		$period_str = "";
		$days = floor($period/(24*3600));
		$period -= $days*24*3600;
		$hours = floor($period/3600);
		$period -= $hours*3600;
		$minutes = floor($period/60);
		if ($days > 1) $period_str = $days." days, ";
		else if ($days == 1) $period_str = $days." day, ";
		if ($hours > 1) $period_str .= $hours." hours, ";
		else if ($hours == 1) $period_str .= $hours." hour, ";
		else if (!empty($period_str)) $period_str .= "0 hours, ";
		if ($minutes > 1) $period_str .= $minutes." minutes";
		else if ($minutes == 1) $period_str .= $minutes." minute";
		else $period_str .= "0 minutes";
		return $period_str;
	}
	
	function add_url_parameters($_base, $_params) {
		if (strpos($_base, "?")) $glue = "&";
		else $glue = "?";
		$result = $_base;
		if (is_array($_params)) {
			foreach ($_params as $key => $value) {
				$result .= $glue.rawurlencode($key)."=".rawurlencode($value);
				$glue = "&";
			}
		}
		return $result;
	}
	
	function generate_certificate() {
		$symbols = '123456789ABCDEFGHGKLMNPQRSTUWVXYZ';
		$code = "";
		for ($i=0; $i<12; $i++) {
			$code .= $symbols[rand(0, strlen($symbols)-1)];
		}
		return $code;
	}
}
$giftcertificateslite = new giftcertificateslite_class();
?>