<?php
namespace EarthAsylumConsulting\Extensions;
/**
 * Extension: {eac}SimpleCDN - CDN support abstract admin interface extended by provider
 *
 * @category	WordPress Plugin
 * @package		{eac}Doojigger\Extensions
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2024 EarthAsylum Consulting <www.EarthAsylum.com>
 * @version 	24.0525.1
 */

abstract class simple_cdn_interface
{
	/**
	 * @var default file types to cache
	 */
	const CACHEABLE_FILE_TYPES = [
		'.avi',		'.avif',	'.bmp',		'.css',		'.flac',
		'.gif',		'.ico',		'.jpeg',	'.jpg',		'.js',
		'.m4v',		'.mov',		'.mp3',		'.mp4',		'.mpg',
		'.ogg',		'.otf',		'.pdf',		'.png',		'.svg',
		'.svgz',	'.swf',		'.tiff',	'.ttf',		'.wav',
		'.webp',	'.woff',	'.woff2',
	];

	/**
	 * @var default file types to cache
	 */
	const CACHEABLE_DOWNLOAD_TYPES = [
		'.apk',		'.bin',		'.bz2',		'.csv',		'.dat',
		'.dmg',		'.doc',		'.docx',	'.eps',		'.gz',
		'.jar',		'.iso',		'.pkg',		'.ppt',		'.pptx',
		'.ps',		'.psd',		'.pub',		'.rar',		'.rtf',
		'.vcf',		'.wma',		'.xls',		'.xlsx',	'.zip',
	];

	/**
	 * @var recommended file types to cache
	 */
	const CACHEABLE_RECOMMENDED = [
		'.css', 	'.jpeg', 	'.jpg', 	'.js', 		'.png', 	'.webp'
	];

	/**
	 * @var extra settings fields
	 */
	const ADMIN_SETTINGS_EXTRAS = [
		'simple_cdn_domains',		'simple_cdn_domains_array',
		'simple_cdn_download',		'simple_cdn_download_preg',
		'simple_cdn_page_include',	'simple_cdn_page_include_preg',
		'simple_cdn_url_include',	'simple_cdn_url_include_preg',
	];

	/**
	 * @var object calling extension (Simple_CDN_extension)
	 */
	public $parent;


	/**
	 * constructor method
	 *
	 * @param	object	$extension Simple_CDN_extension object
	 * @return	void
	 */
	public function __construct(Simple_CDN_extension $extension)
	{
		$this->parent = $extension;

		if ($this->isSettingsPage('Simple CDN'))
		{
			$this->add_action( 'options_settings_page', array($this, '_set_admin_options') );
			// Add contextual help
			$this->add_action( 'options_settings_help', array($this, '_set_admin_help') );
		}

		if ($this->isEnabled() && $this->is_option('simple_cdn_admin_menu'))
		{
			\add_action( 'admin_bar_init', 				array($this, '_get_admin_menu'), 10 );
			\add_action( 'admin_bar_menu', 				array($this, '_set_admin_menu'), 50 );
		}
	}


	/**
	 * register options on options_settings_page
	 *
	 * @access public
	 * @return void
	 */
	public function _set_admin_options()
	{
		/*
		 * Add options/settings fields
		 * hidden fields are formatted versions of their counterparts - here so they can be pushed/pulled
		 */
		$this->parent->registerExtensionOptions( null,
			[
			//	'simple_cdn_type'			=> array(
			//				'type'		=>	'select',
			//				'label'		=>	'CDN Service Provider',
			//				'options'	=>	self::SELECT_CDN_NAMES,
			//				'default'	=> 	'other',
			//	),
				'simple_cdn_host'			=> array(
							'type'		=>	'url',
							'label'		=>	'CDN Host URL',
							'info'		=>	'Use this CDN host for URL rewrites.',
							'help'		=>	'[info]<br>The host url is validated with a \'HTTP HEAD\' request.',
							'attributes'=> 	['placeholder'=>'https://container-id.cdn-host.com'],
							'validate'	=>	[$this,'_on_validate_host'],
				),
				'_simple_cdn_enabled'	=> array(
							'type'		=>	'display',
							'label'		=>	'SimpleCDN Status',
							'default'	=>	'<span class="dashicons dashicons-info-outline"></span>'.
											'Your content delivery network is currently <em>'.
											($this->isEnabled() && $this->is_option('simple_cdn_enabled')
												? 'enabled' : 'disabled').' on this site.</em>',
				),
				'simple_cdn_enabled'		=> array(
							'type'		=>	'hidden',		// set by simplecdn-toggle menu or _on_validate_host
							'default'	=> 	'',
				),
				'simple_cdn_admin_menu'		=> array(
							'type'		=>	'checkbox',
							'label'		=>	'SimpleCDN Menu',
							'options'	=>	['Add Administrator Menu'=>'Enabled'],
							'info'		=>	'Add additional SimpleCDN options to the administrator menu bar.',
				),
				'simple_cdn_admin_extras'	=> array(
							'type'		=>	'hidden',		// set by simplecdn-extras menu
							'default'	=> 	'',
				),
			]
		);

		if (!empty($this->endpoint))
		{
			$settings =
				[
			//		'simple_cdn_domains_array'	=> array(
			//					'type'		=>	'hidden',		// set by _on_domains_to_array
			//					'default'	=> 	'',
			//		),
					'simple_cdn_domains'		=> array(
								'type'		=>	'textarea',
								'label'		=>	'Additional Domains',
								'info'		=>	'Treat additional domains as local domain <small>(one per line)</small>',
								'help'		=>	'[info]<br>Useful with multi-site and/or when your CDN has multiple origins.',
								'validate'	=> 	[$this,'_on_domains_to_array'],
								'attributes'=> 	['placeholder'=>esc_attr($this->parent->getSiteHost())],
								'height'	=>	'2',
					),

					'_cdn_display_1'			=> array(
								'type'		=>	'display',
								'label'		=>	'<span class="dashicons dashicons-images-alt2"></span> File Types',
								'default'	=>	'<em>Determines the file extensions to process when rewritting urls.</em>',
					),

					'simple_cdn_filetypes_preg'	=> array(
								'type'		=>	'hidden',		// set by _on_filetypes_to_preg
								'default'	=> 	'',
					),
					'simple_cdn_filetypes'		=> array(
								'type'		=>	'textarea',
								'label'		=>	'Cacheable File Types',
								'default'	=>	implode(" ",self::CACHEABLE_FILE_TYPES),
								'info'		=>	'Links to these file types will be rewitten and served by the CDN.',
								'help'		=>	'[info]<br>Remove unused file types for improved processing.'.
												'<br><small>Recommended minimum: '.implode(" ",self::CACHEABLE_RECOMMENDED).'</small>',
								'validate'	=> 	[$this,'_on_filetypes_to_preg'],
								'height'	=>	'3',
					),

					'simple_cdn_download_preg'	=> array(
								'type'		=>	'hidden',		// set by _on_filetypes_to_preg
								'default'	=> 	'',
					),
					'simple_cdn_download'		=> array(
								'type'		=>	'textarea',
								'label'		=>	'Cacheable Downloads',
								'default'	=>	implode(" ",self::CACHEABLE_DOWNLOAD_TYPES),
								'info'		=>	'Links to these file types will be rewitten and served by the CDN.',
								'help'		=>	'[info]<br>Remove unused file types for improved processing.',
								'validate'	=> 	[$this,'_on_filetypes_to_preg'],
								'height'	=>	'3',
					),

					'_cdn_display_2'			=> array(
								'type'		=>	'display',
								'label'		=>	'<span class="dashicons dashicons-admin-page"></span> Site/Page URIs',
								'default'	=>	'<em>Determines the pages from your site that will be processed.</em>',
					),

					'simple_cdn_page_include_preg'	=> array(
								'type'		=>	'hidden',		// set by _on_strings_to_preg
								'default'	=> 	'',
					),
					'simple_cdn_page_include'	=> array(
								'type'		=>	'textarea',
								'label'		=>	'Page Inclusions',
								'info'		=>	'Include only page URIs matching any of these strings. <small>(one per line)</small>',
								'help'		=>	'[info]<br>e.g. "/category/" - include only category pages.',
								'attributes'=> 	['placeholder'=>'all pages are included'],
								'validate'	=> 	[$this,'_on_strings_to_preg'],
								'height'	=>	'2',
					),

					'simple_cdn_page_exclude_preg'	=> array(
								'type'		=>	'hidden',		// set by _on_strings_to_preg
								'default'	=> 	'',
					),
					'simple_cdn_page_exclude'	=> array(
								'type'		=>	'textarea',
								'label'		=>	'Page Exclusions',
								'info'		=>	'Exclude any page URIs matching any of these strings. <small>(one per line)</small>',
								'help'		=>	'[info]<br>e.g. "/tag/" - exclude all tag pages.',
								'attributes'=> 	['placeholder'=>'no pages are excluded'],
								'validate'	=> 	[$this,'_on_strings_to_preg'],
								'height'	=>	'2',
					),

					'_cdn_display_3'			=> array(
								'type'		=>	'display',
								'label'		=>	'<span class="dashicons dashicons-admin-links"></span> Asset Links',
								'default'	=>	'<em>Determines the URLs within your pages to be rewritten and served by the CDN.</em>',
					),

					'simple_cdn_url_include_preg'	=> array(
								'type'		=>	'hidden',		// set by _on_strings_to_preg
								'default'	=> 	'',
					),
					'simple_cdn_url_include'	=> array(
								'type'		=>	'textarea',
								'label'		=>	'URL Inclusions',
								'info'		=>	'Include only asset URLs matching any of these strings. <small>(one per line)</small>',
								'help'		=>	'[info]<br>e.g. "/uploads/" - process only files in your media library.',
								'attributes'=> 	['placeholder'=>'all urls are included'],
								'validate'	=> 	[$this,'_on_strings_to_preg'],
								'height'	=>	'2',
					),

					'simple_cdn_url_exclude_preg'	=> array(
								'type'		=>	'hidden',		// set by _on_strings_to_preg
								'default'	=> 	'',
					),
					'simple_cdn_url_exclude'	=> array(
								'type'		=>	'textarea',
								'label'		=>	'URL Exclusions',
								'info'		=>	'Exclude any asset URLs matching any of these strings. <small>(one per line)</small>',
								'help'		=>	'[info]<br>e.g. "/plugins/" - ignore all files from installed plugins.',
								'attributes'=> 	['placeholder'=>'no urls are excluded'],
								'validate'	=> 	[$this,'_on_strings_to_preg'],
								'height'	=>	'2',
					),
				];

			if (!$this->get_option('simple_cdn_admin_extras'))
			{
				foreach (self::ADMIN_SETTINGS_EXTRAS as $option)
				{
					unset($settings[$option]);
				}
			}

			$this->parent->registerExtensionOptions( null, $settings );

			/**
			 * filter SimpleCDN_add_settings -  Add custom options/settings
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
			if ($settings = \apply_filters('SimpleCDN_add_settings',[],$this))
			{
				$this->parent->registerExtensionOptions( null, $settings );
			}

			// possibly add purge button
			if ($settings = $this->_add_purge_button())
			{
				$this->parent->registerExtensionOptions( null, $settings );
			}
		} // empty uri

		if ($this->is_network_enabled() && $this->get_option('simple_cdn_admin_extras'))
		{
			$this->parent->registerExtensionOptions( null,
				[
					'_cdn_network_display'	=> array(
							'type'		=>	'display',
							'label'		=>	'<span class="dashicons dashicons-admin-multisite"></span> Network/Multi-Site',
							'default'	=>	$this->is_network_admin()
											? 'You are the administrator in a multi-site network.'
											: 'This site is part of a multi-site network.',
					),
				]
			);

			if ($this->is_network_admin())
			{
				$this->parent->registerExtensionOptions( null,
					[
						'_cdn_network_push'	=> array(
							'type'		=>	'button',
							'label'		=>	'Network Push',
							'default'	=>	'Save &amp; Push',
							'info'		=>	'Save these settings and push to all network sites.<br>'.
											'<small>Warning: This will overwrite all SimpleCDN site settings.</small>',
							'validate'	=> 	[$this,'_on_network_push'],
							// change the button value so we know it was clicked
							'attributes'=> 	['onmouseup'=>'this.value=\'Network Push\';'],
						)
					]
				);
			}
			else
			{
				$this->parent->registerExtensionOptions( null,
					[
						'_cdn_network_pull'	=> array(
							'type'		=>	'button',
							'label'		=>	'Network Pull',
							'default'	=>	'Discard &amp; Pull',
							'info'		=>	'Discard these settings and pull settings from the network administrator.<br>'.
											'<small>Warning: This will overwrite all SimpleCDN site settings.</small>',
							'validate'	=> 	[$this,'_on_network_pull'],
							// change the button value so we know it was clicked
							'attributes'=> 	['onmouseup'=>'this.value=\'Network Pull\';'],
						)
					]
				);
			}
		}
	}


	/**
	 * Validate cdn host - test a CDN url response
	 *
	 * @param	string $cdnHost simple_cdn_host field value
	 * @param	string $optionKey 'simple_cdn_host'
	 * @param	array  $optionMeta option meta array
	 * @param	string $savedValue prior value
	 * @return	string $cdnHost
	 */
	public function _on_validate_host($cdnHost,$optionKey,$optionMeta,$savedValue)
	{
		if ($cdnHost == $savedValue) return $cdnHost; // no change

		$this->update_option('simple_cdn_enabled',''); 	// disabled until verified

		$cdnHost 	= sanitize_url(rtrim(strtolower(trim($cdnHost)),'/'), ['http', 'https']);
		if (empty($cdnHost)) return $savedValue;

		$siteHost	= $this->parent->getSiteHost();
		if (empty($siteHost)) return $savedValue;

		// try to get a known file from the CDN
		$requestURL = $this->parent->rewrite_url(
			get_stylesheet_uri(),
			str_replace(['http://','https://'],'',$cdnHost), [$siteHost]
		);

		$response = wp_remote_get(
			$requestURL, ['method'=>'HEAD', 'headers'=>['Referer'=>home_url()]]
		);

		if ( is_wp_error( $response ) )
		{
			$this->admin_error($optionKey,"Error ".$response->get_error_code().": Accessing {$cdnHost}\n ".$response->get_error_message());
			return $savedValue;
		}

		$status 	= wp_remote_retrieve_response_code( $response );
		$message 	= wp_remote_retrieve_response_message( $response );

		if ( $status > 499 )	//  server error, invalid
		{
			$this->admin_error($optionKey,"Error: status {$status}, Accessing {$cdnHost}\n {$message}");
			return $savedValue;
		}

		if ( $status > 399 )	// request error, maybe valid
		{
			$this->admin_warning($optionKey,"Warning: status {$status}, Accessing {$cdnHost}\n {$message}");
		}
		else
		{
			$this->admin_success($optionKey,"Success: status {$status}, Accessing {$cdnHost}");
		}

		$this->update_option('simple_cdn_enabled','Enabled');
		$_POST[$optionKey] = $cdnHost; // allow sanitized host to pass validation
		return $cdnHost;
	}


	/**
	 * convert and save string of domains to array
	 *
	 * @param	string $value field value
	 * @param	string $optionKey field name
	 * @param	array  $optionMeta option meta array
	 * @param	string $savedValue prior value
	 * @return	string
	 */
	public function _on_domains_to_array($value,$optionKey,$optionMeta,$savedValue)
	{
		$value = sanitize_textarea_field($value);
		$this->update_option("{$optionKey}_array",$this->parseToArray($value,false));
		$this->logDebug($this->get_option("{$optionKey}_array"),__METHOD__);
		return $value;
	}


	/**
	 * convert and save string of file types to preg_match pattern format
	 *
	 * @param	string $value field value
	 * @param	string $optionKey field name
	 * @param	array  $optionMeta option meta array
	 * @param	string $savedValue prior value
	 * @return	string
	 */
	public function _on_filetypes_to_preg($value,$optionKey,$optionMeta,$savedValue)
	{
		$value = sanitize_textarea_field($value);
		$this->update_option("{$optionKey}_preg",implode('|',$this->parseToArray($value,true)));
		return $value;
	}


	/**
	 * convert and save string of in/exclude to preg_match pattern format
	 *
	 * @param	string $value field value
	 * @param	string $optionKey field name
	 * @param	array  $optionMeta option meta array
	 * @param	string $savedValue prior value
	 * @return	string
	 */
	public function _on_strings_to_preg($value,$optionKey,$optionMeta,$savedValue)
	{
		$value = sanitize_textarea_field($value);
		$this->update_option("{$optionKey}_preg",implode('|',$this->parseToArray($value,false)));
		return $value;
	}


	/**
	 * Network Push - push options to sites
	 *
	 * @param	string $value field value
	 * @param	string $optionKey field name
	 * @param	array  $optionMeta option meta array
	 * @param	string $savedValue prior value
	 * @return	string
	 */
	public function _on_network_push($value,$optionKey,$optionMeta,$savedValue)
	{
		if ($value != 'Network Push') return $value;
		$settings = $this->parent->getNetworkMetaData('Simple_CDN_extension');
		$settings['simple_cdn_host_array'] = ['type'=>'hidden']; // something to force copy
		$push = array();
		foreach ($settings as $name=>$meta)
		{
			if ($meta['type'] != 'display' && !in_array($name[0],['_','-','.']))
			{
				$netValue = $this->get_network_option($name);
				if ($netValue !== false)
				{
					$push[$name] = $netValue;
				}
			}
		}
		$this->forEachNetworkSite(function($settings)
			{
				foreach ($settings as $name => $netValue)
				{
					$this->update_option($name, $netValue);
				}
			},
			$push
		);
		$this->admin_success($optionKey,'Network Push completed successfully');
		return $value;
	}


	/**
	 * Network Pull - pull options from network
	 *
	 * @param	string $value field value
	 * @param	string $optionKey field name
	 * @param	array  $optionMeta option meta array
	 * @param	string $savedValue prior value
	 * @return	string
	 */
	public function _on_network_pull($value,$optionKey,$optionMeta,$savedValue)
	{
		if ($value != 'Network Pull') return $value;
		$settings = $this->parent->getOptionMetaData('Simple_CDN_extension');
		$settings['simple_cdn_host_array'] = ['type'=>'hidden']; // something to force copy
		foreach ($settings as $name=>$meta)
		{
			$this->delete_option($name);
			if ($meta['type'] != 'display' && !in_array($name[0],['_','-','.']))
			{
				$netValue = $this->get_network_option($name);
				if ($netValue !== false)
				{
					$this->update_option($name, $netValue);
				}
			}
		}
		$this->admin_success($optionKey,'Network Pull successful. Please review &amp; save your settings.');
		$this->page_reload(true);
	}


	/**
	 * Add help tab on admin page
	 *
	 * @return	void
	 */
	public function _set_admin_help()
	{
		ob_start();
		?>
			<p>The {eac}SimpleCDN extension rewrites the URLs on your site's front-end pages so that specific contant
			is loaded from your Content Delivery Network rather than your WordPress server.</p>

			<p>{eac}SimpleCDN works with Amazon CloudFront, KeyCDN, Akamai ION, RocketCDN, StackPath, Rackspace, Azure CDN
			and many other Content Delivery Networks as well as many cloud storage services such as Amazon S3 or Google Cloud Storage.</p>

			<p>If the CDN service does not automatically retrieve files from your origin server, you will need to upload all
			cacheable files from your web server to the CDN storage or use some other method to synchronize your files.</p>

			You can test your results with <a href='https://pagespeed.web.dev/' target='_blank'>PageSpeed Insights</a>

			<details><summary>Extra Options:</summary>
			<ul>
				<li>To enable the extra options, first enable the <em>SimpleCDN Menu</em> option, then select
				<em>Enable Extra Options</em> from the <em>SimpleCDN</em> menu.
			</li>
			</details>

			<details><summary>Available Methods:</summary>
			<ul>
				<li><code>flush_cdn_cache()</code></code> tells {eac}SimpleCDN to flush the cdn cache (if supported).
			</li>
			</details>

			<details><summary>Available Filters:</summary>
			<ul>
				<li><code>SimpleCDN_page_enabled</code> enable/disable use of cdn on current page.
				<li><code>SimpleCDN_file_types</code> filter string of included file types.
				<li><code>SimpleCDN_include_strings</code> filter string of included strings.
				<li><code>SimpleCDN_exclude_strings</code> filter string of excluded strings.
			</li>
				<details><summary>For Universal CDNs:</summary>
				<ul>
					<li><code>SimpleCDN_add_settings</code> add additional option fields to the settings page.
					<li><code>SimpleCDN_add_help</code> add additional contextual help.
					<li><code>SimpleCDN_purge_cdn_cache</code> adds support for and custom method to flush (purge) the CDN cache.
				</li>
				</details>
			</details>

			<details><summary>Available Actions:</summary>
			<ul>
				<li><code>SimpleCDN_flush_cache</code> tells SimpleCDN to flush the cdn cache (if supported).
				<li><code>SimpleCDN_cache_flushed</code> triggered when SimpleCDN has flushed the cdn cache.
			</li>
			</details>
		<?php
		$content = ob_get_clean();

		/**
		 * filter SimpleCDN_add_help - Add addition contextual help
		 *
		 * @param	array		$content 	''
		 * @param	object		$cdn 		provider object
		 * 			object 		$cdn->parent	the SimpleCDN class object
		 *			string		$cdn->endpoint	e.g. c2nnnn.r10.cf1.rackcdn.com/path
		 *			string		$cdn->hostname	e.g. c2nnnn.r10.cf1.rackcdn.com
		 *			string		$cdn->bucket	e.g. c2nnnn
		 *			string		$cdn->host		e.g. r10.cf1.rackcdn.com
		 *			string		$cdn->domain	e.g. rackcdn.com
		 *			string		$cdn->provider	e.g. Rackspace
		 */
		$content .= \apply_filters('SimpleCDN_add_help','',$this);

		$this->addPluginHelpTab('Simple CDN',$content,['Simple CDN Extension','open']);

		$this->addPluginSidebarLink(
			"<span class='dashicons dashicons-admin-site'></span>{eac}SimpleCDN",
			"https://eacdoojigger.earthasylum.com/eacsimplecdn/",
			"{eac}SimpleCDN Extension Plugin"
		);
	}


	/**
	 * add the admin bar item
	 *
	 * @param object $admin_bar wp_admin_bar
	 * @return void
	 */
	public function _set_admin_menu($admin_bar)
	{
		if ($admin_bar->get_node('eacDoojigger'))
		{
			$parent = 'eacDoojigger-simplecdn-group';
			$admin_bar->add_group(
				[
					'id' 		=> $parent,
					'parent' 	=> 'eacDoojigger',
				]
			);
		}
		else
		{
			$parent = 'simplecdn-admin';
			$admin_bar->add_menu(
				[
					'id' 		=> $parent,
					'parent' 	=> 'top-secondary',
					'title' 	=> "SimpleCDN",
					'href'		=> $this->getSettingsURL(true,'simple_cdn'),
				]
			);
		}

		$switchTo = $this->is_option('simple_cdn_enabled') ? 'Disable' : 'Enable';
		$admin_bar->add_menu(
			[
				'id'     	=> 'simplecdn-toggle',
				'parent'    => $parent,
				'title' 	=> "{$switchTo} {$this->provider} CDN",
				'href'   	=> wp_nonce_url( add_query_arg(['_simplecdn'=>'cdn_'.strtolower($switchTo)]),'simplecdn' ),
			//	'meta' 		=> ['title' => "CDN currently ".($switchTo=='Enable' ? 'disabled' : 'enabled')]
			]
		);
		if ($this->is_admin())
		{
			$switchTo = $this->is_option('simple_cdn_admin_extras') ? 'Disable' : 'Enable';
			$admin_bar->add_menu(
				[
					'id'     	=> 'simplecdn-extras',
					'parent'    => $parent,
					'title' 	=> "{$switchTo} Extra CDN Options",
					'href'   	=> wp_nonce_url( add_query_arg(['_simplecdn'=>'extras_'.strtolower($switchTo)]),'simplecdn' ),
				//	'meta' 		=> ['title' => $switchTo.' extra options']
				]
			);
		}
		if ($this->has_purge_action())
		{
			$admin_bar->add_menu(
				[
					'id'     	=> 'simplecdn-purge',
					'parent'    => $parent,
					'title' 	=> "Purge CDN Cache",
					'href'   	=> wp_nonce_url( add_query_arg(['_simplecdn'=>'cdn_purge']),'simplecdn' ),
				//	'meta' 		=> ['title' => 'Purge '.$this->provider.' cache']
				]
			);
		}
	}


	/**
	 * process the admin bar item
	 *
	 * @return void
	 */
	public function _get_admin_menu()
	{
		if (!isset($_GET['_simplecdn']) || !isset($_GET['_wpnonce'])) return;

		$menuFN 	= sanitize_text_field($_GET['_simplecdn']);
		$wpnonce 	= sanitize_text_field($_GET['_wpnonce']);
		if (wp_verify_nonce($wpnonce,'simplecdn'))
		{
			switch ($menuFN)
			{
				case 'cdn_enable':
					$switchTo = ($this->is_option('simple_cdn_host')) ? 'Enabled' : '';
					$this->update_option('simple_cdn_enabled',$switchTo);
					break;
				case 'cdn_disable':
					$this->update_option('simple_cdn_enabled','');
					break;
				case 'extras_enable':
					$this->_on_admin_extras(true);
					break;
				case 'extras_disable':
					$this->_on_admin_extras(false);
					break;
				case 'cdn_purge':
					$this->_on_purge_menu_button('Purging Cache','_purge_menu');
					break;
			}
		}
		// so a reload doesn't initiate again
		wp_safe_redirect( remove_query_arg(['_simplecdn','_wpnonce']) );
		exit;
	}


	/**
	 * register purge button option on options_settings_page
	 *
	 * @return array
	 */
	public function _add_purge_button(): array
	{
		if ($this->has_purge_action())
		{
			return [
				'_purge_button'	=> array(
					'type'		=>	'button',
					'label'		=>	'Purge '.$this->provider.' cache',
					'default'	=>	'Purge Cache',
					'info'		=>	'If your CDN does not automatically pull content, purging while enabled may break your site.',
					'validate'	=>	[$this,'_on_purge_menu_button'],
					// change the button value so we know it was clicked
					'attributes'=> 	['onmouseup'=>'this.value=\'Purging Cache\';'],
				)
			];
		}

		return [];
	}


	/**
	 * flush cache on purge menu or button click
	 *
	 * @param	string	$value 		button value
	 * @param	string	$fieldName 	button field name
	 * @return	string	$value
	 */
	public function _on_purge_menu_button($value,$fieldName): string
	{
		if ($value == 'Purging Cache')	// we actually clicked the button
		{
			$this->parent->flush_cdn_cache( ltrim($fieldName,'_') ); // triggers 'SimpleCDN_cache_flushed' action
		}
		return $value;
	}


	/**
	 * Admin extras menu select
	 *
	 * @param	bool	$enabled 	extras enabled
	 * @return	void
	 */
	public function _on_admin_extras(bool $enabled): void
	{
		if ($enabled)	// make sure options are updated
		{
			$this->update_option('simple_cdn_admin_extras','Enabled');
			$this->_set_admin_options();
			$settings = $this->parent->getOptionMetaData('Simple_CDN_extension');
			foreach (self::ADMIN_SETTINGS_EXTRAS as $option)
			{
				if (isset( $settings[$option], $settings[$option]['validate'] ))
				{
					$value = $this->get_option($option,$settings[$option]['default']);
					call_user_func($settings[$option]['validate'],$value,$option,$settings[$option],$value);
				}
			}
		}
		else			// delete disabled options
		{
			$this->update_option('simple_cdn_admin_extras','');
			foreach (self::ADMIN_SETTINGS_EXTRAS as $option)
			{
				$this->delete_option($option);
			}
		}
	}


	/**
	 * parse delimited string to arrray
	 *
	 * @param	string	$string delimited (by ' ', ',' or '\n')
	 * @param	bool 	$filter filter file extensions
	 * @return	array
	 */
	public function parseToArray($string,$filter=true)
	{
		return ($filter)
			? array_filter(
				array_map('trim',explode("\n", str_replace([',',' '],"\n",$string))),
				function($v) {return preg_match("/^\.\w{1,7}$/", $v);}
			)
			: array_filter(
				array_map('trim',explode("\n", str_replace([',',' '],"\n",$string)))
			);
	}


	/**
	 * add_option_error (add_settings_error) to show notice on page
	 *
	 * @param	string	$optionKey the field/option name (or empty)
	 * @param	string	$message (kses'd when displayed)
	 * @param	string	$type (error,warning,notice,success)
	 * @return	void
	 */
	public function admin_error($optionKey,$message,$type='error')
	{
		if (is_admin() && $optionKey)
		{
			$this->add_option_error($optionKey,$message,$type);
		}
	}


	/**
	 * admin warning shortcut
	 *
	 * @param	string	$optionKey
	 * @param	string	$message
	 * @return	void
	 */
	public function admin_warning($optionKey,$message)
	{
		$this->admin_error($optionKey,$message,'warning');
	}


	/**
	 * admin success shortcut
	 *
	 * @param	string	$optionKey
	 * @param	string	$message
	 * @return	void
	 */
	public function admin_success($optionKey,$message)
	{
		$this->admin_error($optionKey,$message,'success');
	}


	/**
	 * magic method to call plugin or extension methods
	 *
	 * @param	mixed 	$method	the method name or [extension,method]
	 * @param	mixed 	$arguments the arguments to method name
	 * @return	mixed	result of method called
	 */
	public function __call($method, $arguments)
	{
		return $this->parent->{$method}(...$arguments);
	}
}
