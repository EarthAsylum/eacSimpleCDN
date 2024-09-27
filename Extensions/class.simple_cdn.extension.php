<?php
namespace EarthAsylumConsulting\Extensions;

if (! class_exists(__NAMESPACE__.'\Simple_CDN_extension', false) )
{
	/**
	 * Extension: simple_cdn - Implement CDN urls in WordPress front-end pages
	 *
	 * @category	WordPress Plugin
	 * @package		{eac}Doojigger\Extensions
	 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
	 * @copyright	Copyright (c) 2024 EarthAsylum Consulting <www.EarthAsylum.com>
	 * @version		1.x
	 * @link		https://eacDoojigger.earthasylum.com/
	 */

	class Simple_CDN_extension extends \EarthAsylumConsulting\abstract_extension
	{
		/**
		 * @var string extension version
		 */
		const VERSION	= '24.0927.1';

		/**
		 * @var required when no provider (yet)
		 */
		const NO_CDN_PROVIDER = [
				'endpoint'	=> null,
				'provider' 	=> 'Universal',
				'helper'	=> 'generic',
		];

		/**
		 * @var object custom cdn class
		 */
		private $cdn;


		/**
		 * constructor method
		 *
		 * @param	object	$plugin main plugin object
		 * @return	void
		 */
		public function __construct($plugin)
		{
			parent::__construct($plugin, self::DEFAULT_DISABLED|self::ALLOW_NETWORK);

			if (defined('WP_INSTALLING'))
			{
				return $this->isEnabled(false);
			}

			// the current CDN properties
			$this->cdn = (object)$this->get_option('simple_cdn_host_array',self::NO_CDN_PROVIDER);

			if (current_user_can('manage_options'))
			{
				$this->registerExtension( [$this->className,'Simple CDN'] );
				$this->load_cdn_helper();
			}
		}


		/**
		 * Register this extension and options
		 *
		 * @param	string|array 	$optionGroup group name or [groupname, tabname]]
		 * @param	array 			$optionMeta group option meta
		 * @return	void
		 */
		public function registerExtensionOptions($optionGroup, $optionMeta = array())
		{
			parent::registerExtensionOptions( [$this->className,'Simple CDN'], $optionMeta );
		}


		/**
		 * get & load the cdn helper class
		 *
		 * @todo - add custom helpers for each cdn provider
		 * @return void
		 */
		private function load_cdn_helper()
		{
			static $cdnProviders =
			[
			// 	provider name			sub-domain(s)		top domain				provider helper
				'CloudFront'	=>  [	"", 				'cloudfront.net',		'cloudfront'	],
				'KeyCDN'		=>  [	"", 				'kxcdn.com',			'keycdn'		],
				'Akamai_ION'	=>  [	"",					'akamaized.net',		'generic'		],
				'RocketCDN'		=>  [	"",					'rocketcdn.net',		'generic'		],
				'StackPath'		=>  [	"",					'stackpath.com',		'generic'		],
				'CloudFlare'	=>  [	"cdn\.", 			'cloudflare.net',		'generic'		],
				'Rackspace'		=>  [	"\w{3}\.\w{3}\.",	'rackcdn.com',			'generic'		],
				'Azure'			=>  [	"",					'azureedge.net',		'generic'		],
				'Amazon_S3'		=>  [	"(s3[-\.]).*\.",	'amazonaws.com',		'generic'		],
				'Amazon_EC2'	=>  [	"(compute-\d).*\.",	'amazonaws.com',		'generic'		],
				'Google_Cloud'	=>  [	"storage\.",		'googleapis.com',		'generic'		],

				'Universal'		=>  [	"",					'-',					'generic'		],
			];
			/*
			 * get the name and helper of the CDN to possibly include cdn-specific code
			 */
			if ($hostURL = sanitize_url( $_POST['simple_cdn_host'] ?? $this->get_option('simple_cdn_host'), ['http', 'https'] ))
			{
				$hostURL 	= rtrim(strtolower(trim($hostURL)),'/');
				$cdnHost 	= parse_url($hostURL,PHP_URL_HOST);
				$hostURL	= str_replace(['http://','https://'],'',$hostURL);
			}

			// new provider host
			if ($hostURL !== $this->cdn->endpoint)
			{
				$cdn	= self::NO_CDN_PROVIDER; 	// default provider

				if (isset($cdnHost) && !empty($cdnHost))
				{
					// typically {bucket}.host.name.provider.com, we need bucket and provider.com
					$cdnPreg = array_map(function($sub,$tld,$id)
						{
							return $sub . "(?P<{$id}>".preg_quote($tld).')';
						},
						array_column($cdnProviders, 0),array_column($cdnProviders, 1),array_keys($cdnProviders)
					);

					if (preg_match("#^(.*)\.(". implode('|',$cdnPreg) . ")$#", $cdnHost, $matched))
					{
						//$this->add_admin_notice( var_export($matched,true) );
						$cdn = [
							'endpoint' 	=> $hostURL,						// host url sans scheme
							'hostname' 	=> $matched[0],						// endpoint sans path
							'bucket' 	=> $matched[1],						// container/bucket id
							'host'		=> $matched[2],						// host name sans bucket
							'domain' 	=> array_pop($matched),				// last element=top domain
							'provider' 	=> key(array_slice($matched, -1))	// key of (now) last element=provider name
						];
					}
					else
					{
						$cdn = [
							'endpoint' 	=> $hostURL,
							'hostname' 	=> $cdnHost,
							'bucket'	=> '',
							'host'		=> $cdnHost,
							'domain'	=> '',
							'provider'	=> 'Universal',
						];
						// best guess
						if (substr_count($cdnHost,'.') > 1)
						{
							list($cdn['bucket'],$cdn['host']) = explode('.',$cdnHost,2);
						}
						$cdn['domain'] = $cdn['host'];
					}
				}

				$cdn['helper'] 		= end($cdnProviders[ $cdn['provider'] ]);
				$cdn['provider'] 	= str_replace('_',' ',$cdn['provider']);

				$this->update_option('simple_cdn_host_array',$cdn);
				$this->cdn = (object)$cdn;
				//$this->add_admin_notice( var_export($this->cdn,true) );
			}
			require 'includes/simple_cdn.provider.php';
			require 'includes/simple_cdn.'.$this->cdn->helper.'.php';
			$cdnClass 	= __NAMESPACE__.'\\'.$this->cdn->helper.'_cdn_provider';
			$this->cdn 	= new $cdnClass($this,$this->cdn);
		}


		/**
		 * Add filters and actions - called from main plugin
		 *
		 * @return	void
		 */
		public function addActionsAndFilters()
		{
			parent::addActionsAndFilters();

			/**
			 * action SimpleCDN_flush_cache - flush the cdn cache.
			 * @return	void
			 */
			\add_action('SimpleCDN_flush_cache',[$this,'flush_cdn_cache']);

			if ($this->isCacheableRequest())
			{
				// add cdn host to dns-prefetch, preconnect
				\add_filter( 'wp_resource_hints', function( $urls, $relation_type)
					{
						if ($relation_type == 'dns-prefetch'
						or	$relation_type == 'preconnect')
						{
							$urls[] = [ 'href' => '//'.$this->cdn->hostname ];
						}
						return $urls;
					},10,2
				);

				// allow cdn host in CORS
				add_filter( 'allowed_http_origins', function ($allowed)
					{
						$origin  = (is_ssl()) ? 'https://' : 'http://';
						$origin .= $this->cdn->hostname;
						$allowed[] = $origin;
						return $allowed;
					}
				);

				// do this as late as possible but before any output...
				\add_action('send_headers',[$this,'setup_cdn_page'], 20);
			}
		}


		/**
		 * Get the site host name
		 *
		 * @return	string
		 */
		public function getSiteHost()
		{
			$siteHost = sanitize_url( $_SERVER['HTTP_HOST'] ?: home_url(), ['http', 'https'] );
			return parse_url($siteHost, PHP_URL_HOST);
		}


		/**
		 * is request cacheable
		 *
		 * @return	void
		 */
		private function isCacheableRequest()
		{
			/* resons to not use cdn */
			return (
				   ( empty($this->cdn->endpoint) )
				or ( $this->is_admin() )
				or ( !isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] != 'GET' )
				or ( isset($_SERVER['HTTP_X_SIMPLE_CDN']) && $this->isFalse(sanitize_text_field($_SERVER['HTTP_X_SIMPLE_CDN'])) )
				or ( !$this->is_option('simple_cdn_enabled') )
			) ? false : true;
		}


		/**
		 * Start CDN processing
		 *
		 * @return	void
		 */
		public function setup_cdn_page()
		{
			/* resons to not use cdn */
			if ( (is_trackback() || is_robots() || is_preview() || is_customize_preview()) ) return;

			$requestURI = sanitize_url($_SERVER['REQUEST_URI']);
			/* check page include/exclue */
			$pages		= $this->get_option('simple_cdn_page_include_preg');
			$include 	= (!empty($pages))
						? preg_match("#(".$pages.")#i", $requestURI)
						: true;
			$pages		= $this->get_option('simple_cdn_page_exclude_preg');
			$exclude 	= (!empty($pages))
						? preg_match("#(".$pages.")#i", $requestURI)
						: false;

			/**
			 * filter SimpleCDN_enabled - is cdn use enabled on this page.
			 * @param	bool active on this page
			 * @return	bool active on this page
			 */
			if (\apply_filters('SimpleCDN_page_enabled',$include && !$exclude))
			{
				if (!headers_sent())
				{
					header('x-Simple-CDN: on');
				}
				ob_start( [$this,'get_page_buffer'] );
			}
		}


		/**
		 * Get output buffer and replace urls
		 *
		 * @param	string	$buffer page content
		 * @param	int		$phase output handler phase
		 * @return	string
		 */
		public function get_page_buffer(string $buffer=null, int $phase): string
		{
			if (!empty($buffer))
			{
				$buffer = $this->rewrite_page($buffer);
			}

			return $buffer;
		}


		/**
		 * rewrite the local/relative urls in the page buffer
		 *
		 * @param	string	$buffer page content
		 * @return	string
		 */
		public function rewrite_page(string $buffer=null): string
		{
			$cdnHost	= $this->cdn->endpoint;

			$siteHost	= $this->getSiteHost();
			if (empty($siteHost)) return $buffer;

			// parsed domains textarea to array of domains
			$domains	= $this->get_option('simple_cdn_domains_array',[]);
			array_unshift($domains, $siteHost);

			// parsed file types textarea to delimited string of file extensions
			$filetypes	= $this->get_option('simple_cdn_filetypes_preg','');
			$download	= $this->get_option('simple_cdn_download_preg','');
			/**
			 * filter SimpleCDN_file_types - filter array of file types.
			 * @param	string file extensions delimited by '|'
			 * @return	string file extensions delimited by '|'
			 */
			$filetypes 	= quotemeta( \apply_filters('SimpleCDN_file_types',trim($filetypes.'|'.$download,'|')) );
			if (empty($filetypes)) return $buffer;

			// parsed include strings textarea to delimited string of strings
			$include	= $this->get_option('simple_cdn_url_include_preg','');
			/**
			 * filter SimpleCDN_include_strings - url strings to include.
			 * @param	string strings to include delimited by '|'
			 * @return	string strings to include delimited by '|'
			 */
			$include 	= quotemeta( \apply_filters('SimpleCDN_include_strings',$include) );

			// parsed exclude strings textarea to delimited string of strings
			$exclude	= $this->get_option('simple_cdn_url_exclude_preg','');
			/**
			 * filter SimpleCDN_exclude_strings - url strings to exclude.
			 * @param	string strings to exclude delimited by '|'
			 * @return	string strings to exclude delimited by '|'
			 */
			$exclude 	= quotemeta( \apply_filters('SimpleCDN_exclude_strings',$exclude) );

			// Any sufficiently advanced technology is indistinguishable from magic.
			$pattern	= '#(?:(?:[\"\'\s=>,;]|url\()\K|^)[^\"\'\s(=>,;]+(' .
						$filetypes .
						')(\?[^\/?\\\"\'\s)>,]+)?(?:(?=\/?[?\\\"\'\s)>,&])|$)#i';

			return preg_replace_callback( $pattern, function($matches) use ($cdnHost,$domains,$include,$exclude)
				{
					return $this->rewrite_url($matches[0],$cdnHost,$domains,$include,$exclude);
				},
				$buffer
			);
		}


		/**
		 * rewrite url callback
		 *
		 * @param	string	$fileURL url to rewrite
		 * @param	string	$cdnHost cdn host url
		 * @param	array	$domains local domains
		 * @param	string	$include strings to include
		 * @param	string	$exclude strings to exclude
		 * @return	string
		 */
		public function rewrite_url($fileURL,$cdnHost,$domains,$include='',$exclude=''): string
		{
			// not contains included string(s)
			if (!empty($include))
			{
				if (!preg_match("#(".$include.")#i", $fileURL)) return $fileURL;
			}

			// contains excluded string(s)
			if (!empty($exclude))
			{
				if (preg_match("#(".$exclude.")#i", $fileURL)) return $fileURL;
			}

			// rewrite relative URL - /wp-content/...
			if ($fileURL[0] == '/' && $fileURL[1] != '/')
			{
				return '//' . $cdnHost . $fileURL;
			}
			else if (strpos( $fileURL, '\/\/') !== 0 && strpos($fileURL, '\/') === 0)
			{
                return '\/\/' . $cdnHost . $fileURL;
			}

			// rewrite full URL - (http:|https:)//www.{site}.com/wp-content/...
			foreach ($domains as $siteHost)
			{
				if (stripos($fileURL, '//'.$siteHost) !== false || stripos($fileURL, '\/\/'.$siteHost) !== false)
				{
					return substr_replace( $fileURL, $cdnHost, stripos($fileURL, $siteHost), strlen($siteHost) );
				}
			}

			return $fileURL;
		}


		/**
		 * flush_cdn_cache helper function
		 *
		 * @param	string|bool	$context 	context ('_purge_button'|'purge_menu') or false (non-interactive)
		 * @return	void
		 */
		public function flush_cdn_cache($context=false)
		{
			if ($this->cdn && $this->cdn->purge_cdn_cache($context))
			{
				/**
				 * action SimpleCDN_cache_flushed - the cdn cache has been flushed.
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
				\do_action('SimpleCDN_cache_flushed',$this->cdn,$context);
			}
		}
	}
}
/*
 * return a new instance of this class
 */
if (isset($this)) return new Simple_CDN_extension($this);
?>
