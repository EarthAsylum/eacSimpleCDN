<?php
namespace EarthAsylumConsulting;

/**
 * Add {eac}SimpleCDN extension to {eac}Doojigger
 *
 * @category	WordPress Plugin
 * @package		{eac}SimpleCDN\{eac}Doojigger Extensions
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2023 EarthAsylum Consulting <www.earthasylum.com>
 * @version		1.x
 *
 * @wordpress-plugin
 * Plugin Name:			{eac}SimpleCDN
 * Description:			{eac}SimpleCDN Implement CDN urls in WordPress front-end pages
 * Version:				1.1.4
 * Requires at least:	5.5.0
 * Tested up to:		6.4
 * Requires PHP:		7.2
 * Plugin URI:			https://eacdoojigger.earthasylum.com/eacsimplecdn/
 * Author:				EarthAsylum Consulting
 * Author URI:			http://www.earthasylum.com
 * License:				GPLv3 or later
 * License URI:			https://www.gnu.org/licenses/gpl.html
 */

class eacSimpleCDN
{
	/**
	 * constructor method
	 *
	 * @return	void
	 */
	public function __construct()
	{
		/*
		 * {pluginname}_load_extensions - get the extensions directory to load
		 *
		 * @param	array	$extensionDirectories - array of [plugin_slug => plugin_directory]
		 * @return	array	updated $extensionDirectories
		 */
		add_filter( 'eacDoojigger_load_extensions', function($extensionDirectories)
			{
				/*
    			 * Enable update notice (self hosted or wp hosted)
    			 */
				eacDoojigger::loadPluginUpdater(__FILE__,'wp');

				/*
    			 * Add links on plugins page
    			 */
				add_filter( (is_network_admin() ? 'network_admin_' : '').'plugin_action_links_' . plugin_basename( __FILE__ ),
					function($pluginLinks, $pluginFile, $pluginData) {
						return array_merge(
							[
								'settings'		=> eacDoojigger::getSettingsLink($pluginData,'simple_cdn'),
								'documentation'	=> eacDoojigger::getDocumentationLink($pluginData),
								'support'		=> eacDoojigger::getSupportLink($pluginData),
							],
							$pluginLinks
						);
					},20,3
				);

				/*
    			 * Add our extension to load
    			 */
				$extensionDirectories[ plugin_basename( __FILE__ ) ] = [plugin_dir_path( __FILE__ ).'/Extensions'];
				return $extensionDirectories;
			}
		);
	}
}
new \EarthAsylumConsulting\eacSimpleCDN();
?>
