<?php
namespace EarthAsylumConsulting\Extensions;
/**
 * Extension: simple_cdn
 *
 * @category	WordPress Plugin
 * @package		{eac}Doojigger\Extensions
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2023 EarthAsylum Consulting <www.EarthAsylum.com>
 * @version 	23.0519.1
 */

class generic_cdn_provider extends simple_cdn_provider
{
	/**
	 * constructor method
	 *
	 * @param	object	$extension Simple_CDN_extension object
	 * @param	object	$cdnParams cdn parameters
	 * @return	void
	 */
	public function __construct(Simple_CDN_extension $extension, object $cdnParams)
	{
		parent::__construct($extension, $cdnParams);

		add_filter('SimpleCDN_add_settings',	[$this,'addSettings'], 10, 2);
		add_filter('SimpleCDN_add_help',		[$this,'addHelp'], 10, 2);
	}


	/**
	 * register additional options on options_settings_page
	 *
	 * @return array
	 */
	public function addSettings(): array
	{
	/* when debugging
		return [
				'_generic_desc'		=> array(
					'type'		=>	'display',
					'label'		=>	'<span class="dashicons dashicons-cloud"></span>'.$this->provider.' CDN',
					'default'	=>	'Host: '.esc_attr($this->host). ', Container: '.esc_attr($this->bucket).', Provider: '.esc_attr($this->provider),
				),
		];
	*/
		return [];
	}


	/**
	 * additional contextual help
	 *
	 * @return	string additional help
	 */
	public function addHelp(): string
	{
		ob_start();
		?>
		<details open><summary><?php echo esc_attr($this->provider) ?> CDN:</summary>
			<p>Most CDNs will work with {eac}SimpleCDN provided that the CDN will store and serve
			all cacheable site content in a directory structure matching your web server.</P>
		</details>
		<?php
		return ob_get_clean();
	}
}
