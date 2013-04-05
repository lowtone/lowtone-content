<?php
namespace lowtone\content\repositories;
use ReflectionClass;

include "interfaces/repository.interface.php";

/**
 * Copied functionality from lowtone\util\modules\Module()
 * @author Paul van der Meijs <code@lowtone.nl>
 * @copyright Copyright (c) 2011-2012, Paul van der Meijs
 * @license http://wordpress.lowtone.nl/license/
 * @version 1.0
 * @package wordpress\plugins\lowtone\content\repositories
 */
abstract class Repository implements interfaces\Repository {
	
	public function enabled() {
		return true;
	}

	// Static

	public static final function modules() {
		$static = get_called_class();
		$reflector = new ReflectionClass($static);
		$class = $reflector->getShortName();
		$namespace = $reflector->getNamespaceName();
		$dir = dirname($reflector->getFileName());
		$plugin = reset(explode("/", plugin_basename($dir)));
		$filter = sprintf("lowtone_%s_%ss", $plugin, strtolower($class));
		
		$default = array_filter(array_map(function($file) use ($namespace) {
			if (!include_once $file)
				return false;

			$className = $namespace . "\\defaults\\" . basename($file, ".class.php");

			$instance = new $className();

			return $instance;
		}, glob(implode(DIRECTORY_SEPARATOR, array($dir, "defaults", "*.class.php")))));

		return array_filter((array) apply_filters($filter, $default), function($module) use ($static) {
			return ($module instanceof $static) && $module->enabled();
		});
	}

}