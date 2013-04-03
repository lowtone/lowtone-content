<?php
/*
 * Plugin Name: Content
 * Plugin URI: http://wordpress.lowtone.nl
 * Description: Manage plugins, themes and libraries and anything else in the wp-content folder.
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

	include_once "libraries/library.class.php";
	include_once "plugins/plugin.class.php";

	use lowtone\content\libraries\Library;

	function req($libs) {
		return call_user_func_array("lowtone\\content\\libraries\\Library::req", func_get_args());
	}
	
}