<?php
namespace EarthAsylumConsulting\Extensions;
/**
 * Extension: simple_cdn - CloudFront support
 *
 * @category	WordPress Plugin
 * @package		{eac}Doojigger\Extensions
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2023 EarthAsylum Consulting <www.EarthAsylum.com>
 * @version 	23.1001.1
 */

class cloudfront_cdn_provider extends simple_cdn_provider
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

		if ($this->parent->isExtension('Simple_AWS') && $this->parent->is_option('simple_cdn_cloudfront_key'))
		{
			\add_action( 'SimpleCDN_purge_cdn_cache', 	[$this,'cloudfront_purge_cdn_cache'], 10, 2  );
		}
	}


	/**
	 * register additional options on options_settings_page
	 *
	 * @return array
	 */
	public function addSettings(): array
	{
		$select 	= [];
		if ($cloudFrontClient = $this->getCloudFrontClient('settings'))
		{
			try {
				$result = $cloudFrontClient->listDistributions([]);
				//echo "<pre>listDistributions ".var_export($result,true)."</pre>";
				foreach ($result['DistributionList']['Items'] as $distribution)
				{
					if ($distribution['DomainName'] == $this->endpoint)
					{
						if ($distribution['Enabled'] && $distribution['Status'] == 'Deployed')
						{
							$select["{$distribution['Id']} ({$distribution['Comment']})"] = $distribution['Id'];
							$this->parent->update_option('simple_cdn_cloudfront_key',$distribution['Id']);
						}
					}
				}
			} catch (\AwsException $e) {
				$this->admin_error('CloudFront',"CloudFront Error: ".$e->getAwsErrorMessage());
				return [];
			}
		}

		if (empty($select))
		{
			$select = ['-no active distribution-'=>''];
		}


		return [
			'_cloudfront_desc'			=> array(
					'type'		=>	'display',
					'label'		=>	'<span class="dashicons dashicons-cloud"></span> CloudFront',
					'default'	=>	'With your Distribution Id, we can connect to CloudFront to purge the CDN cache.',
				),
			'simple_cdn_cloudfront_key'	=> array(
					'type'		=>	(!empty($select)) ? 'select' : 'text',
					'options'	=> 	$select,
					'label'		=>	'Distribution Id',
					'info'		=>	'Your CloudFront Distribution Id',
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
		<details open><summary>CloudFront:</summary>
			If you're using CloudFront and you have the {eac}SimpleAWS extension enabled, you can flush the CDN cache
			(invalidate the distribution) by providing your CloudFront distribution id.
		</details>
		<?php
		return ob_get_clean();
	}


	/**
	 * Purge the CDN cache
	 *
	 * @param	object		$cdn 		interface object
	 * @param	string|bool	$context 	source context or false(non-interactive)
	 * @return	void
	 */
	public function cloudfront_purge_cdn_cache($cdn,$context=false)
	{
		if (! $cloudFrontClient = $this->getCloudFrontClient($context)) return;

		// get aws distribution id
		if (! $awsDistribution = $this->parent->get_option('simple_cdn_cloudfront_key') )
		{
			$this->admin_error($context,"AWS Error: You must provide your CloudFront distribution ID to invalidate");
			return;
		}

		// trigger the invalidation
		try
		{
			$result = $cloudFrontClient->createInvalidation([
				'DistributionId'		=> $awsDistribution, 	// REQUIRED
				'InvalidationBatch'		=>						// REQUIRED
				[
					'CallerReference'	=> time(),				// REQUIRED
					'Paths'				=>						// REQUIRED
					[
						'Items'			=> ['/*'],				// items or paths to invalidate
						'Quantity'		=> 1					// REQUIRED (must be equal to the number of 'Items' in the previus line)
					]
				]
			]);
			$this->admin_success($context,"Purge CDN Success: The CloudFront Invalidation is in progress and may take a few minutes");
		}
		catch (\AwsException $e)
		{
			$this->admin_error($context,"CloudFront Error: ".$e->getAwsErrorMessage());
		}
	}


	/**
	 * get AWS CloudFront client object
	 *
	 * @param	string|bool	$context field name, method name, or false
	 * @return	object|bool
	 */
	private function getCloudFrontClient($context)
	{
		if (! $aws = $this->parent->isExtension('Simple_AWS') )
		{
			$this->admin_warning($context,"Full CloudFront support requires the activation of the {eac}SimpleAWS extension.");
			return false;
		}

		if (! $awsParams = $aws->getAwsClientParams() )
		{
			$this->admin_error($context,"AWS Error: You must provide your AWS credentials in the Amazon Web Services settings");
			return false;
		}

		try
		{
			$cloudFrontClient = new \Aws\CloudFront\CloudFrontClient($awsParams);
		}
		catch (\AwsException $e)
		{
			$this->admin_error($context,"AWS Error: ".$e->getAwsErrorMessage());
			return false;
		}
		return $cloudFrontClient;
	}
}
