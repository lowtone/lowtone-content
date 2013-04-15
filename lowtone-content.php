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

	include_once "repositories/repository.class.php";

	include_once "packages/package.class.php";

	use lowtone\content\packages\Package;

	if (!defined("LIB_DIR"))
		define("LIB_DIR", WP_CONTENT_DIR . DIRECTORY_SEPARATOR . Package::TYPE_LIB . "s");

	if (!defined("LIB_URL"))
		define("LIB_URL", content_url(Package::TYPE_LIB . "s"));

	// Hooks
	
	add_action("admin_init", function() {
		wp_enqueue_style("lowtone_content", plugins_url("/assets/styles/admin.css", __FILE__));
	});
	 
	add_action("load-plugins.php", function() {

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
		 * Update totals.
		 */
		add_action("pre_current_active_plugins", function($active) {
			global $wp_list_table, $plugins, $totals;

			// var_dump($wp_list_table);

			foreach ($plugins["all"] as &$plugin) {
				if (Package::TYPE_LIB !== @$plugin["type"])
					continue;

				$plugins[Package::TYPE_LIB] = $plugin;

				@$totals[Package::TYPE_LIB]++;
			}

			$totals["inactive"] -= @$totals[Package::TYPE_LIB];


			include_once "packages/listtables/plugins.class.php";

			packages\listtables\Plugins::__switch();
		}, 9999);

		/*
		 * Update libraries filter title.
		 */
		add_filter("views_plugins", function($views) {
			if (!isset($views[Package::TYPE_LIB]))
				return $views;

			global $totals, $status;

			$views[Package::TYPE_LIB] = vsprintf("<a href='%s' %s>%s</a>", array(
					add_query_arg("plugin_status", Package::TYPE_LIB, "plugins.php"),
					Package::TYPE_LIB == $status ? ' class="current"' : '',
					sprintf(__('Libraries <span class="count">(%s)</span>', "lowtone_content"), number_format_i18n($totals[Package::TYPE_LIB]))
				));

			return $views;
		}, 9999);

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