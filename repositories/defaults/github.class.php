<?php
namespace lowtone\content\repositories\defaults;
use lowtone\content\repositories\Repository;

/**
 * GitHub repository module.
 * @author Paul van der Meijs <code@lowtone.nl>
 * @copyright Copyright (c) 2011-2012, Paul van der Meijs
 * @license http://wordpress.lowtone.nl/license/
 * @version 1.0
 * @package wordpress\plugins\lowtone\content\repositories\defaults
 */
class GitHub extends Repository {

	const GITHUB_USER_URL_PATTERN = "#https://github.com/([^/]+)#";
	
	public function is($url) {
		return (bool) preg_match(self::GITHUB_USER_URL_PATTERN);
	}

	public function search($url, $package) {
		if (!preg_match(self::GITHUB_USER_URL_PATTERN, $url, $matches))
			return false;

		$get = function($url) {
			$result = wp_remote_get($url);
			
			if ("" === ($body = wp_remote_retrieve_body($result)))
				return false;

			return json_decode($body);
		};

		$reposUrl = sprintf("https://api.github.com/users/%s/repos", $matches[1]);

		if (!is_array($result = $get($reposUrl)))
			return false;

		$find = str_replace("\\", "-", $package

		foreach ($result as $repository) {
			if (!is_object($repository))
				continue;

			if ($repository->name != $find)
				continue;

			return $repository->html_url . "/zipball/master";
		}

		return false;
	}

}