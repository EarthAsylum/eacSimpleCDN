<?php
namespace EarthAsylumConsulting\Extensions;
/**
 * Extension: {eac}SimpleCDN - CDN support abstract provider extended by custom providers
 *
 * @category	WordPress Plugin
 * @package		{eac}Doojigger\Extensions
 * @author		Kevin Burkholder <KBurkholder@EarthAsylum.com>
 * @copyright	Copyright (c) 2023 EarthAsylum Consulting <www.EarthAsylum.com>
 * @version 	23.0523.1
 */

require 'simple_cdn.interface.php';

abstract class simple_cdn_provider extends simple_cdn_interface
{
	/**
	 * @var strings from $cdnParams
	 */
	public $endpoint;	// dxxxxxxxxxxxx.cloudfront.net/path	c2nnnn.r10.cf1.rackcdn.com/path
	public $hostname;	// dxxxxxxxxxxxx.cloudfront.net			c2nnnn.r10.cf1.rackcdn.com
	public $bucket;		// dxxxxxxxxxxxx						c2nnnn
	public $host;		// cloudfront.net						r10.cf1.rackcdn.com
	public $domain;		// cloudfront.net						rackcdn.com
	public $provider;	// CloudFront							Rackspace


	/**
	 * constructor method
	 *
	 * @param	object	$extension Simple_CDN_extension object
	 * @param	object	$cdnParams cdn parameters
	 * @return	void
	 */
	public function __construct(Simple_CDN_extension $extension, object $cdnParams)
	{
		parent::__construct($extension);
		foreach ((array)$cdnParams as $n=>$v)
		{
			$this->{$n} = $v;
		}
	}


	/**
	 * should we add the purge option menu/button
	 *
	 * @return	bool|string (truthy)
	 */
	public function has_purge_action()
	{
		return \has_action('SimpleCDN_purge_cdn_cache');
	}


	/**
	 * purge_cdn_cache helper function, called from $this->parent->flush_cdn_cache
	 *
	 * @param	string|bool	$context 	source context or false(non-interactive)
	 * @return	bool
	 */
	public function purge_cdn_cache($context=false): bool
	{
		if (\has_action('SimpleCDN_purge_cdn_cache'))
		{
			\do_action('SimpleCDN_purge_cdn_cache',$this,$context);
			return true;
		}
		return false;
	}
}
