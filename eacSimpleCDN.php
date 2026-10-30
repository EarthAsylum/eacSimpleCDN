<?php
namespace EarthAsylumConsulting;

/**
 * Add {eac}SimpleCDN extension to {eac}Doojigger
 *
 * @category	WordPress Plugin
 * @package		{eac}Doojigger Extensions\{eac}SimpleCDN
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2026 EarthAsylum Consulting <www.earthasylum.com>
 * @link		https://eacdoojigger.earthasylum.com/eacsimplecdn/
 * @link 		https://wordpress.org/plugins/eacsimplecdn
 * @link 		https://github.com/EarthAsylum/eacSimpleCDN
 *
 * @wordpress-plugin
 * Plugin Name:			{eac}SimpleCDN
 * Description:			{eac}SimpleCDN Implement CDN urls in WordPress front-end pages
 * Version:				1.1.12
 * Requires at least:	5.8
 * Tested up to:		7.1
 * Requires PHP:		8.1
 * Plugin URI:			https://eacdoojigger.earthasylum.com/eacsimplecdn/
 * Author:				EarthAsylum Consulting
 * Author URI:			http://www.earthasylum.com
 * License:				GPLv3 or later
 * License URI:			https://www.gnu.org/licenses/gpl.html
 */

if (!defined( 'EACDOOJIGGER_VERSION' ) )
{
	require_once 'dependency.php';
	return dependency::notice([
		'plugin'	=>	[ '{eac}SimpleCDN' 		=> plugin_basename( __FILE__ ) ],
		'requires'	=> 	[ '{eac}Doojigger' 		=> 'eacDoojigger/eacDoojigger.php' ],
		'manifest'	=> 'https://eacdoojigger.earthasylum.com/software-updates/eacdoojigger.json',
		'after'		=> self_admin_url('/admin.php?page=eacdoojigger-settings&tab=registration')
	]);
}

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
				if (is_admin())
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
									'settings'		=> eacDoojigger::getSettingsLink($pluginData,'cdn'),
									'documentation'	=> eacDoojigger::getDocumentationLink($pluginData),
									'support'		=> eacDoojigger::getSupportLink($pluginData),
								],
								$pluginLinks
							);
						},20,3
					);
				}

				/*
    			 * Add our extension to load
    			 */
				$extensionDirectories[ plugin_basename( __FILE__ ) ] = [plugin_dir_path( __FILE__ )];
				return $extensionDirectories;
			}
		);
	}
}
new \EarthAsylumConsulting\eacSimpleCDN();
?>
