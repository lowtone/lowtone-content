<?php
namespace lowtone\content\plugins;
use lowtone\Util;

/**
 * @author Paul van der Meijs <code@lowtone.nl>
 * @copyright Copyright (c) 2011-2012, Paul van der Meijs
 * @license http://wordpress.lowtone.nl/license/
 * @version 1.0
 * @package wordpress\plugins\lowtone\content\plugins
 */
abstract class Plugin {

	const INIT_LIBS = "libs",
		INIT_MERGE_PATH = "merge_path",
		INIT_CALLBACK = "callback";
	
	public function init(array $options = NULL) {

		// Include libraries

		try {
			call_user_func_array("lowtone\\content\\req", @$options[self::INIT_LIBS] ?: array("lowtone"));
		} catch (\ErrorException $e) {
			return trigger_error("Required libraries not found", E_USER_ERROR);
		}

		// Add merge path

		if ($mergePath = @$options[self::INIT_MERGE_PATH]) 
			Util::addMergedPath($mergePath);

		$result = true;

		// Execute callback

		if (is_callable($callback = @$options[self::INIT_CALLBACK]))
			$result = Util::call($callback);

		return $result;
	}
	
}