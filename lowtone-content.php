<?php
/*
 * Plugin Name: Content
 * Plugin URI: http://wordpress.lowtone.nl
<<<<<<< .mine
 * Plugin Type: plugin
 * Description: Manage plugins, themes and libraries.
=======
 * Description: Manage plugins, themes and libraries and anything else in the wp-content folder.
>>>>>>> .r585
 * Version: 1.0
 * Author: Lowtone <info@lowtone.nl>
 * Author URI: http://lowtone.nl
 * License: http://wordpress.lowtone.nl/license
 * Requires: lowtone-lib
 */
/**
 * @author Paul van der Meijs <code@lowtone.nl>
 * @copyright Copyright (c) 2011-2012, Paul van der Meijs
 * @license http://wordpress.lowtone.nl/license/
 * @version 1.0
 * @package wordpress\plugins\lowtone\content
 */

namespace lowtone\content {

	if (!defined("LIB_DIR"))
		define("LIB_DIR", WP_CONTENT_DIR . "/libs");

	include_once "repositories/repository.class.php";

	include_once "packages/package.class.php";

	use lowtone\content\libraries\Library;

	function req($libs) {
		return call_user_func_array("lowtone\\content\\packages\\Package::req", func_get_args());
	}

	function dir() {
		return apply_filters("lowtone_content_dir", WP_CONTENT_DIR);
	}

	function repositories() {
		return apply_filters("lowtone_content_repositories", is_array($repositories = get_option("lowtone_content_repositories")) ? $repositories : array("https://github.com/lowtone"));
	}
	
}