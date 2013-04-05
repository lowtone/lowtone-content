<?php
namespace lowtone\content\repositories\interfaces;

/**
 * @author Paul van der Meijs <code@lowtone.nl>
 * @copyright Copyright (c) 2011-2012, Paul van der Meijs
 * @license http://wordpress.lowtone.nl/license/
 * @version 1.0
 * @package wordpress\plugins\lowtone\content\repositories\interfaces
 */
interface Repository {
	
	public function is($url);

	public function search($url, $package);

}