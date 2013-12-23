=== WP Gift Certificate Reloaded Lite ===
Contributors: gcwebsolutions
Plugin Name: WP Gift Certificate Reloaded Lite
Plugin URI: https://www.wpgiftcertificatereloaded.com/wp-gift-certificate-reloaded-modular
Author: gcwebsolutions
Author URI: http://www.wpgiftcertificatereloaded.com/
Tags: gift certificate, gift, certificate, paypal, payment, sell, gift vouchers
Requires at least: 3.0
Tested up to: 3.9.1
Stable tag: 1.290

WP Gift Certificate Reloaded Lite is a plugin which allows you to manage and sell printable gift certificates on your website using QR encoding image.

== Description ==

WP Gift Certificate Reloaded Lite is a plugin which allows you to manage and sell printable gift certificates on your website. You can sell them to your visitors, and accept payments via PayPal. It's easy, install the plugin and open new feature for your customers!

How the plugin works?

FRONT END

The visitor view part of the plugin consists of simple form where your visitors can purchase gift certificates. 

Front end workflow. Let' imagine that I sell gift certificate for visiting museum, and you are the visitor who purchases the certificate.

1. You fill up the form and click "Continue" button. Then system asks you to confirm your action.
2. You click "Confirm" button and being redirected to PayPal, where you can pay for certificate(s).
3. After successfull payment you receive e-mail (it is sent to your PayPal e-mail address) which contains link to printable gift certificate.
4. You click this link and see printable gift certificate(s), see below:
5. You print this certificate and bring it to ticket booth of my museum.
6. I scan QR Code and being redirected to certificate page where I can see certificate details and manage it. (For example, I can see transaction details and mark certificates as redeemed).
7. Then I allow you to visit museum. This is just particular situation how the plugin might be used. You can use it for selling any gift certificates. Unlimited possibilities.

If you require more features/functions, please visit the <a href="https://www.wpgiftcertificatereloaded.com/wp-gift-certificate-reloaded-modular">website</a> for more information.

== Installation ==

Install and activate the plugin like you do with any other plugins.

== Frequently Asked Questions ==

= Shortcode =

To place the certificate in any page or post, use [giftcertificateslite].

= IPN Handler =

WPGC (WP Gift Certificate Reloaded) flow will require IPN to be set in PayPal website.  It needs to be turned on (through PayPal) and place the url of your domain name, you can use http://www.yourowndomain.com/, note that you need to include the http://www and the trailing slash. 

= Add Certificate menu =

The Add Certificate panel creates a manual certificate.  In order to use this function, you need to place an email address, then this certificate will be created and shown under Certificates menu. This function doesn't send/email the certificate to the recipient automatically.

If you require more features/functions, please visit the <a href="https://www.wpgiftcertificatereloaded.com/wp-gift-certificate-reloaded-modular">website</a> for more information.

== Screenshots ==

1. Settings page - part 1.
2. Settings page - part 2.
3. Transactions list.
4. E-mail.

== Changelog ==

= 1.290 =
Added EUR currency support

= 1.289 =
Added PayPal currencies: AUD, CAD, GBP (USD is default)

Updated PayPal ipn handler to cURL

Updated paypal-ipn.php for new http1.1 requirements

= 1.281 =
Minor scan bug fixed due to typo, now corrected.

= 1.28 =
Minor bugs and typos fixed.

= 1.27 =
This is the first version of WP Gift Certificate Reloaded Lite plugin.

== Upgrade Notice ==

= Any version =
Deactivate plugin. Upload new plugin files. Activate plugin.
