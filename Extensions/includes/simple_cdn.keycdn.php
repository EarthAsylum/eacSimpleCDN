<?php
namespace EarthAsylumConsulting\Extensions;
/**
 * Extension: simple_cdn - KeyCDN support
 *
 * @category	WordPress Plugin
 * @package		{eac}Doojigger\Extensions
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2023 EarthAsylum Consulting <www.EarthAsylum.com>
 * @version 	23.1001.1
 */

class keycdn_cdn_provider extends simple_cdn_provider
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

		if ($this->parent->get_option('simple_cdn_keycdn_zone') && $this->parent->get_option_decrypt('simple_cdn_keycdn_key'))
		{
			\add_action( 'SimpleCDN_purge_cdn_cache', 	[$this,'keycdn_purge_cdn_cache'], 10, 2  );
		}
	}


	/**
	 * register additional options on options_settings_page
	 *
	 * @return array
	 */
	public function addSettings(): array
	{
		return [
			'_keycdn_desc'				=> array(
					'type'		=>	'display',
					'label'		=>	'<span class="dashicons dashicons-cloud"></span> KeyCDN',
					'default'	=>	'With your Zone ID and API Key, we can connect to KeyCDN to flush the CDN cache.',
				),
			'simple_cdn_keycdn_zone'  	=> array(
					'type'		=>	'text',
					'label'		=>	'Zone Id',
					'info'		=>	'Your KeyCDN Zone Id',
					'attributes'=> 	['autocomplete'=>'new-password'],
				),
			'simple_cdn_keycdn_key'		=> array(
					'type'		=>	'password',
					'label'		=>	'API Key',
					'info'		=>	'Your KeyCDN API Key <small>(encrypted when stored)</small>',
					'attributes'=> 	['autocomplete'=>'new-password'],
					'encrypt'	=> 	true,
				),
		];
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
		<details open><summary>KeyCDN:</summary>
			If you're using KeyCDN, you can flush the CDN cache by providing your KeyCDN zone id and API key.
		</details>
		<?php
		return ob_get_clean();
	}


	/**
	 * Purge the CDN cache
	 *
	 * @param	object		$cdn 		interface object
	 * @param	string|bool	$context 	source context or false(non-interactive)
	 * @return 	void
	 */
	public function keycdn_purge_cdn_cache($cdn,$context=false)
	{
		// get api key
		if (! $apiKey = $this->parent->get_option_decrypt('simple_cdn_keycdn_key') )
		{
			$this->admin_error($context,"KeyCDN Error: You must provide your KeyCDN API Key");
			return;
		}

		// get zone id
		if (! $zoneId = $this->parent->get_option('simple_cdn_keycdn_zone') )
		{
			$this->admin_error($context,"KeyCDN Error: You must provide your KeyCDN Zone Id");
			return;
		}

		$response = wp_remote_get(
			"https://api.keycdn.com/zones/purge/{$zoneId}.json",
			['headers' => ['Authorization' => 'Basic ' . base64_encode( $apiKey . ':' )]]
		);

        if ( is_wp_error( $response ) )
        {
			$this->admin_error($context,"KeyCDN Error: " . $response->get_error_message());
			return;
		}

		$status = wp_remote_retrieve_response_code( $response );

		if ($status > 399)
		{
			switch ($status)
			{
				case 401:
					$message = 'Invalid API key';
					break;
				case 403:
					$message = 'Invalid Zone Id';
					break;
				case 429:
					$message = 'API rate limit exceeded';
					break;
				case 451:
					$message = 'Too many failed attempts';
					break;
				default:
					$message = wp_remote_retrieve_response_message( $response );
			}
			$this->admin_error($context,"KeyCDN Error: status {$status}, " . $message);
			return;
		}

		$this->admin_success($context,"Purge CDN Success: The KeyCDN purge is in progress and may take a few minutes");
	}
}
