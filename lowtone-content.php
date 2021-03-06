<?php
/*
 * Plugin Name: Content
 * Plugin URI: http://wordpress.lowtone.nl
 * Plugin Type: plugin
 * Description: Manage plugins, themes and libraries.
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

	include_once ABSPATH . "wp-admin/includes/plugin.php";

	include_once "repositories/repository.class.php";

	include_once "packages/package.class.php";

	use lowtone\content\packages\Package;

	if (!defined("LIB_DIR"))
		define("LIB_DIR", WP_CONTENT_DIR . DIRECTORY_SEPARATOR . Package::TYPE_LIB . "s");

	if (!defined("LIB_URL"))
		define("LIB_URL", content_url(Package::TYPE_LIB . "s"));

	// Add plugin functionality when activated.

	if (is_plugin_active("lowtone-content/lowtone-content.php")) {

		// Hooks
	
		add_action("admin_init", function() {
			wp_enqueue_style("lowtone_content", plugins_url("/assets/styles/admin.css", __FILE__));
		});
		 
		add_action("load-plugins.php", function() {

			// Check WordPress version

			if (true !== version_compare(get_bloginfo("version"), "3.5", ">=")) {
				
				add_action("pre_current_active_plugins", function() {
					echo '<div class="error"><p>' . __('To list libraries WordPress version <strong>3.5 or greater</strong> is required.', "lowtone_content") . '</p></div>';
				});

				return false;
			}

			/*
			 * Add libraries to the list of plugins.
			 */
			add_filter("all_plugins", function($plugins) {
				if (false === ($libs = glob(LIB_DIR . DIRECTORY_SEPARATOR . "*")))
					return $plugins;

				foreach ($libs as $path) {
					if (is_dir($path))
						$path .= "/" . basename($path) . ".php";

					$lib = substr($path, strlen(LIB_DIR) + 1);

					if (!is_readable($path))
						continue;

					$data = get_plugin_data($path, false, false );

					if (empty($data["Name"]))
						continue;

					$data["type"] = Package::TYPE_LIB;

					$plugins[$lib] = $data;
				}

				uasort($plugins, "_sort_uname_callback");

				return $plugins;
			}, 9999);

			/*
			 * Replace list table
			 */
			add_action("pre_current_active_plugins", function($active) {
				include_once "packages/listtables/plugins.class.php";

				packages\listtables\Plugins::__switch();

			}, 9999);

			/*
			 * Add library class
			 */
			add_filter("list_table_plugins_row_class", function($class, $data) {
				if (Package::TYPE_LIB === @$data["type"]) {
					$class = preg_replace("/(in)?active/", "", $class);

					$class .= " library";

					$class = trim($class);
				}

				return $class;
			}, 9999, 2);

			/*
			 * Remove activation links.
			 */
			add_filter("plugin_action_links", function($actions, $file, $data) {
				if (Package::TYPE_LIB !== @$data["type"])
					return $actions;

				unset($actions["activate"]);

				return $actions;
			}, 9999, 3);

		});

		// Register textdomain
		
		add_action("plugins_loaded", function() {
			load_plugin_textdomain("lowtone_content", false, basename(__DIR__) . "/assets/languages");
		});
		
	}

	// Functions

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