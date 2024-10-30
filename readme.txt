===BTC===
Contributors: mwdmeyer
Tags: admin, btev, events, event viewer, stats, statistics, soap, bluetrait, btc
Requires at least: 2.1.0
Tested up to: 2.8.5
Stable tag: 0.3

Bluetrait Connector (BTC) allows you to sync events from Bluetrait Event Viewer to a central server.

== Description ==

Bluetrait Connector (BTC) allows you to sync events from Bluetrait Event Viewer to a central server via a SOAP connection.

The central Bluetrait server can also display the current version of WordPress installed on any connected site.

More features will come in future.

WordPress 2.3.0 or higher is recommended.

PLEASE NOTE. This is currently a beta version. PHP5 is currently required although may not be required for future versions.

For more information please go here: http://www.bluetrait.com/page/bluetrait-connector-for-wordpress/

== Installation ==

1. Download
1. Unzip (zip contains btc.php, btc-nusoap.php, btc-soap.class.php, btc-soap-client.class.php)
1. Upload to wp-content/plugins
1. Activate within Wordpress Admin Panel
1. Enter SOAP connection details of Bluetrait Server

== Frequently Asked Questions ==

= What does LOCKDOWN mode do? =

The LOCKDOWN mode is designed to make it more difficult to disable BTC.  
It might be required for extra security or if you don't want users with Administrator permissions to be able to disable the plugin.
In LOCKDOWN mode the follow options are disabled:

1. Unregister Server
1. Update Settings
1. Uninstall
1. Deactivate

This can make it more affective in synchronising changes that occur in your site.

= How do I enable LOCKDOWN mode? =

open btc.php and find the line (near the top) that says:

		define('BTC_LOCKDOWN', FALSE);
and change it to:

		define('BTC_LOCKDOWN', TRUE);
		
Remember to upload the file if you edited it locally.

= What limitations does LOCKDOWN mode have? =

1. Please be aware that LOCKDOWN mode is NOT a guarantee that BTC will say active if you site is hacked.
1. An extra layer of security is added but there are many other ways to disabled BTC.  
1. It is recommended that btc.php is NOT writable so that the file cannot be editted from within WordPress.
1. Please be aware that LOCKDOWN mode is NOT a guarantee that BTC will say active if you site is hacked.

= BTC has a bug and/or I want to suggest some new features. How? =

Please contact me here: http://www.bluetrait.com/contact/

== Changelog ==

= 0.3 - 28/10/2009 =

* Fixed events not correctly syncing every hour  

= 0.2 - 25/07/2009 =

* Fixed events not syncing every hour  
* Updated changelog format

= 0.1 - 23/07/2009 =

* First Release