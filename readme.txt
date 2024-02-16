=== {eac}Doojigger Simple CDN Extension for WordPress ===
Plugin URI:         https://eacdoojigger.earthasylum.com/eacsimplecdn/
Author:             [EarthAsylum Consulting](https://www.earthasylum.com)
Stable tag:         1.1.4
Last Updated:       10-Dec-2023
Requires at least:  5.5.0
Tested up to:       6.4
Requires PHP:       7.2
Requires EAC:       2.3
Contributors:       kevinburkholder
License:            GPLv3 or later
License URI:        https://www.gnu.org/licenses/gpl.html
Tags:               cdn, content delivery network, caching, CloudFront, KeyCDN, RocketCDN, Akamai, Rackspace, Azure, {eac}Doojigger
WordPress URI:		https://wordpress.org/plugins/eacsimplecdn
GitHub URI:			https://github.com/EarthAsylum/eacSimplaCDN

{eac}SimpleCDN enables the use of Content Delivery Network assets on your WordPress site, significantly decreasing your page load times and improving the user experience.

== Description ==

_{eac}SimpleCDN_ is an [{eac}Doojigger](https://eacDoojigger.earthasylum.com/) extension which rewrites the URLs on your site's front-end pages so that specific content is loaded from your Content Delivery Network rather than your WordPress server.

> What is a CDN?
A content delivery network, or content distribution network, is a geographically distributed network of proxy servers and their data centers. The goal is to provide high availability and performance by distributing the service spatially relative to end users. [Wikipedia](https://en.wikipedia.org/wiki/Content_delivery_network)

{eac}SimpleCDN is not a content delivery network. What it does is filter your web pages replacing local http addresses with the address of your CDN so that your site assets (images, scripts, etc.) are served from your CDN rather than directly from your web server.

You can specify what file types should be served from the CDN and add url string exclusions to prevent specific urls from being served from the CDN.

{eac}SimpleCDN works with Amazon CloudFront, KeyCDN, Akamai ION, RocketCDN, StackPath, Rackspace, Azure CDN
and many other Content Delivery Networks as well as many cloud storage services such as Amazon S3 or Google Cloud Storage.

If you're using CloudFront and you have the [{eac}SimpleAWS](https://dev.earthasylum.net/eacsimpleaws/) extension enabled, you can flush the CDN cache (invalidate the distribution) by providing your CloudFront distribution id.

If you're using any other supported CDN, you can flush the CDN cache by providing your API credentials specific to the CDN you're using.

Unsupported, or *Universal* CDNs will work just as well with {eac}SimpleCDN provided that the CDN will store and serve all cacheable site content in a directory structure matching your web server. The only difference is that the API to purge the CDN cache has not been implemented (yet).

If the CDN service does not automatically retrieve files from your origin server, you will need to upload all cacheable files from your web server to the CDN storage or use some other method to synchronize your files.

= Extra Options =

The extra options include:

+	*Additional Domains* treated as local domain(s), useful with multi-site and/or when your CDN has multiple origins.
+	*Cacheable Downloads* additional file type(s) to be rewitten with the CDN host.
+	*Page Inclusions* to only include specific page URIs based on string(s) in the URI.
+	*URL Inclusions* to limit URLs on the page to be rewritten by string(s) in the URL.

To enable the extra options, first enable the *SimpleCDN Menu* option, then select *Enable Extra Options* from the *SimpleCDN* menu.

= Network/Multisite =

On a multi-site server, when {eac}SimpleCDN is *network enabled*, the network administrator has the option to push settings to all individual sites. The individual site administrators have the option to pull settings from the network administrator.

= Available Methods =

`flush_cdn_cache()` tells {eac}SimpleCDN to flush the cdn cache (if supported).

= Available Filters =

`SimpleCDN_page_enabled` enable/disable use of cdn on current page.

`SimpleCDN_file_types` filter string of included file types (delimited by '|').

`SimpleCDN_include_strings` filter string of included strings (delimited by '|').

`SimpleCDN_exclude_strings` filter string of excluded strings (delimited by '|').

= Available Actions =

`SimpleCDN_flush_cache` tells SimpleCDN to flush the cdn cache (if supported).

`SimpleCDN_cache_flushed` triggered when SimpleCDN has flushed the cdn cache.

__Universal CDNs__

A *Universal* CDN is a CDN not fully supported by {eac}SimpleCDN and is treated universally. No additional options, no purge API is included.

With your custom code and the following filters, additional options and purging may be added.

`SimpleCDN_add_settings` add additional option fields to the settings page.

`SimpleCDN_add_help` add additional contextual help.

`SimpleCDN_purge_cdn_cache` adds support for and custom method to flush (purge) the CDN cache.
This is a special-use case for *Universal* CDNs where custom code can be added to both indicate that the cache can be purged and to provide the method for doing the purge. When this hook has an action, the "Purge" button and menu items are automatically added. The hook should be added on or before the `admin_init` action.

See the [examples](#examples) for more detail.

= HTTP Headers =

An http request may include a header to disable the CDN...

	x-Simple-CDN: off

{eac}SimpleCDN includes an http response header...

	x-Simple-CDN: on

= Important Notes =

+	*Time To First Byte (TTFB) may be slightly longer.* Your pages are not being served by your CDN and {eac}SimpleCDN captures and buffers the page, then rewrites the asset URLs in the page, before any content is delivered to the browser. Although {eac}SimpleCDN endevores to do this as quickly and efficiently as possible, doing it takes a little more time than not doing it.

+	*Load time may be deceiving.* For example, my business network and my web server are in the same zone as the closest CDN edge server (maybe in the same datacenter). Checking load times with and without the CDN from my location produces negligible difference. However, the CDN produces significant load time reduction in more distant zones.

= Results =

You can test your results here: [PageSpeed Insights](https://pagespeed.web.dev/)

![PageSpeed Insights](https://ps.w.org/eacsimplecdn/assets/pagespeed.png)


== Examples ==

= All CDNs =

	// disable CDN use
	\add_filter('SimpleCDN_page_enabled','__return_false');


	// flush the cdn cache (if supported)
	\do_action('SimpleCDN_flush_cache');
	// or...
	if ($cdn = $this->getExtension('Simple_CDN')) {
		$cdn->flush_cdn_cache();
	}


	// do action whenever the cdn cache is flushed
	\add_action('SimpleCDN_cache_flushed', 'my_cdn_flushed', 10, 2);

	/**
	 * After purging the CDN cache
	 *
	 * @param	object		$cdn 		provider object
	 * 			object 		$cdn->parent	the SimpleCDN class object
	 *			string		$cdn->endpoint	e.g. c2nnnn.r10.cf1.rackcdn.com/path
	 *			string		$cdn->hostname	e.g. c2nnnn.r10.cf1.rackcdn.com
	 *			string		$cdn->bucket	e.g. c2nnnn
	 *			string		$cdn->host		e.g. r10.cf1.rackcdn.com
	 *			string		$cdn->domain	e.g. rackcdn.com
	 *			string		$cdn->provider	e.g. Rackspace
	 * @param	string|bool	$context 	context ('purge_button'|'purge_menu') or false (non-interactive)
	 */
	function my_cdn_flushed($cdn,$context)
	{
		$cdn->admin_success($context,"Success: The CDN purge is in progress");
	}


	// limit the file types being cached by the CDN
	\add_filter('SimpleCDN_file_types', function($types)
		{
			return '.css|.jpeg|.jpg|.js|.png|.webp';
		}
	);


	// add plugins folder to excluded strings
	\add_filter('SimpleCDN_exclude_strings', function($exclude)
		{
			return $exclude . '|/plugins/';
		}
	);


= Universal CDNs =

	// add additional settings fields
	\add_filter( 'SimpleCDN_add_settings', 'my_add_cdn_fields', 10, 2 );

	/**
	 * Add custom options/settings
	 *
	 * @param	array		$options 	array()
	 * @param	object		$cdn 		provider object
	 * 			object 		$cdn->parent	the SimpleCDN class object
	 *			string		$cdn->endpoint	e.g. c2nnnn.r10.cf1.rackcdn.com/path
	 *			string		$cdn->hostname	e.g. c2nnnn.r10.cf1.rackcdn.com
	 *			string		$cdn->bucket	e.g. c2nnnn
	 *			string		$cdn->host		e.g. r10.cf1.rackcdn.com
	 *			string		$cdn->domain	e.g. rackcdn.com
	 *			string		$cdn->provider	e.g. Rackspace
	 */
	function my_add_cdn_fields($options,$cdn)
	{
		$options['simple_cdn_universal_key'] = array(
				'type'		=>	'text',
				'label'		=>	'API Key',
				'info'		=>	'Your '.$cdn->provider.' API key',
		);
		return $options;
	}


	// add custom code to flush/purge the CDN
	\add_action( 'admin_init', 	function()
	{
		\add_action( 'SimpleCDN_purge_cdn_cache', 'my_purge_cdn_cache', 10, 2 );
	});

	/**
	 * Purge the CDN cache (custom)
	 *
	 * @param	object		$cdn 		provider object
	 * 			object 		$cdn->parent	the SimpleCDN class object
	 *			string		$cdn->endpoint	e.g. c2nnnn.r10.cf1.rackcdn.com/path
	 *			string		$cdn->hostname	e.g. c2nnnn.r10.cf1.rackcdn.com
	 *			string		$cdn->bucket	e.g. c2nnnn
	 *			string		$cdn->host		e.g. r10.cf1.rackcdn.com
	 *			string		$cdn->domain	e.g. rackcdn.com
	 *			string		$cdn->provider	e.g. Rackspace
	 * @param	string|bool	$context 	context ('purge_button'|'purge_menu') or false (non-interactive)
	 */
	function my_purge_cdn_cache($cdn,$context)
	{
		if ($apiKey = $cdn->get_option('simple_cdn_universal_key'))
		{
			/* code to purge the cdn cache */
		}

		if (/* error condition */)
		{
			$cdn->admin_error($context,"Error: status {$status}, " . $message);
		}
		else
		{
			$cdn->admin_success($context,"Success: The CDN purge is in progress");
		}
	}


== Installation ==

**{eac}SimpleCDN Extension** is an extension plugin to and requires installation and registration of [{eac}Doojigger](https://eacDoojigger.earthasylum.com/).

= Automatic Plugin Installation =

This plugin is available from the [WordPress Plugin Repository](https://wordpress.org/plugins/search/earthasylum/) and can be installed from the WordPress Dashboard » *Plugins* » *Add New* page. Search for 'EarthAsylum', click the plugin's [Install] button and, once installed, click [Activate].

See [Managing Plugins -> Automatic Plugin Installation](https://wordpress.org/support/article/managing-plugins/#automatic-plugin-installation-1)

= Upload via WordPress Dashboard =

Installation of this plugin can be managed from the WordPress Dashboard » *Plugins* » *Add New* page. Click the [Upload Plugin] button, then select the eacsimplecdn.zip file from your computer.

See [Managing Plugins -> Upload via WordPress Admin](https://wordpress.org/support/article/managing-plugins/#upload-via-wordpress-admin)

= Manual Plugin Installation =

You can install the plugin manually by extracting the eacsimplecdn.zip file and uploading the 'eacsimplecdn' folder to the 'wp-content/plugins' folder on your WordPress server.

See [Managing Plugins -> Manual Plugin Installation](https://wordpress.org/support/article/managing-plugins/#manual-plugin-installation-1)

= Settings =

Once installed and activated options for this extension will show in the 'Simple CDN' tab of {eac}Doojigger settings.


== Screenshots ==

1. Simple CDN
![{eac}SimpleCDN Extension](https://ps.w.org/eacsimplecdn/assets/screenshot-1.png)

2. Simple CDN - Network Admin
![{eac}SimpleCDN Extension](https://ps.w.org/eacsimplecdn/assets/screenshot-2.png)

3. Simple CDN - Help
![{eac}SimpleCDN Help](https://ps.w.org/eacsimplecdn/assets/screenshot-3.png)


== Other Notes ==

= Additional Information =

+   {eac}SimpleCDN is an extension plugin to and requires installation and registration of [{eac}Doojigger](https://eacDoojigger.earthasylum.com/).


== Copyright ==

= Copyright © 2023, EarthAsylum Consulting, distributed under the terms of the GNU GPL. =

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should receive a copy of the GNU General Public License along with this program. If not, see [https://www.gnu.org/licenses/](https://www.gnu.org/licenses/).


== Changelog ==

= Version 1.1.4 – December 10, 2023 =

+	Fixed array error on extra domains.
	+	Rearranged hidden fields.
+	Use 'validate' instead of 'sanitize' callback.
	+	Sanitize not called on empty field.
+	Use smaller textarea fields.

= Version 1.1.3 – December 1, 2023 =

+	Fixed a critical error when a) extra options are enabled (from menu), b) options are not updated, c) options are pushed (or pulled) to other site(s) in the network.

= Version 1.1.2 – October 3, 2023 =

+	Removed now redundant 'admin_notices' action.
+	Enhanced admin bar menu (using {eac}Doojigger menu if present).
+	Allow purge filter on front-end (w/admin bar).

= Version 1.1.1 – September 21, 2023 =

+	Security updates.
+	Fixed action for purge from admin bar.

= Version 1.1.0 – June 8, 2023 =

+	Removed unnecessary plugin_update_notice trait.
+	Reworked host/endpoint loader.
+	Changed $cdn property names.
+	Isolated Amazon S3 and EC2.
+	Added RocketCDN and StackPath (universal).

= Version 1.0.0 – May 22, 2023 =

+	First public release.
