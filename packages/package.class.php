<?php
namespace lowtone\content\packages;
use ErrorException,
	lowtone\Util,
	lowtone\content\repositories\Repository;

include_once "exceptions/downloadexception.class.php";
include_once "exceptions/installexception.class.php";
include_once "exceptions/notfoundexception.class.php";
include_once "exceptions/versionexception.class.php";

include_once "libraries/library.class.php";
include_once "plugins/plugin.class.php";
include_once "themes/theme.class.php";

include_once ABSPATH . "/wp-admin/includes/file.php";

/**
 * @author Paul van der Meijs <code@lowtone.nl>
 * @copyright Copyright (c) 2011-2012, Paul van der Meijs
 * @license http://wordpress.lowtone.nl/license/
 * @version 1.0
 * @package wordpress\plugins\lowtone\content\packages
 */
class Package {

	const OPTION_NAME = "name",
		OPTION_MIN_VERSION = "min_version",
		OPTION_MAX_VERSION = "max_version";

	const TYPE_LIB = "lib",
		TYPE_PLUGIN = "plugin",
		TYPE_THEME = "theme";

	const INIT_PACKAGES = "packages",
		INIT_MERGED_PATH = "merged_path",
		INIT_SUCCESS = "success",
		INIT_ERROR = "error";

	public function __construct(array $options = NULL) {
		foreach ($options as $name => $value)
			$this->$name = $value;
	}

	public function search() {
		$handlers = Repository::modules();

		$package = $this;

		$checkRepository = function($repository) use ($package, $handlers) {
			foreach ($handlers as $handler) {
				if (false === ($result = $handler->search($repository, $package->{Package::OPTION_NAME})))
					continue;

				return $result;
			}

			return false;
		};

		foreach ((array) \lowtone\content\repositories() as $repository) {
			if (false === ($src = $checkRepository($repository)))
				continue;

			return $src;
		}

		throw new exceptions\NotFoundException(sprintf("Package %s not in repositories", $this->{static::OPTION_NAME}));
	}

	/**
	 * Fetch the files for the library from a repository.
	 * @throws ErrorException Throws an error if the required library is not 
	 * available.
	 * @return Library Returns the library on success.
	 */
	public function fetch($src) {
		$file = wp_tempnam($src);

		if (!$file)
			throw new exceptions\DownloadException("Temporary file couldn't be created");

		$response = wp_remote_get($src, array( 
				"timeout" => 300, 
				"stream" => true, 
				"filename" => $file
			));

		if (is_wp_error($response)) {
			unlink($file);

			throw new exceptions\DownloadException("Package couldn't be downloaded");
		}

		if (200 != wp_remote_retrieve_response_code($response)){
			unlink($file);

			throw new exceptions\DownloadException("Package couldn't be downloaded");
		}

		return $file;
	}

	/**
	 * Install a library from a zipped file.
	 * @throws ErrorException Throws an error if the zipped library couldn't be
	 * extracted.
	 * @return [type] [description]
	 */
	public function install($file) {
		$folder = WP_CONTENT_DIR  . DIRECTORY_SEPARATOR . "upgrade" . DIRECTORY_SEPARATOR . basename($file);

		WP_Filesystem();

		global $wp_filesystem;

		$dest;

		$cleanAndThrow = function($message) use ($wp_filesystem, $folder, &$dest) {
			$wp_filesystem->delete($folder, true);

			if (isset($dest))
				$wp_filesystem->delete($dest, true);

			throw new exceptions\InstallException($message);
		};

		if ($wp_filesystem->is_dir($folder))
			$wp_filesystem->delete($folder, true);

		$result = unzip_file($file, $folder);

		unlink($file);

		$folders = $wp_filesystem->dirlist($folder);

		if (empty($folders))
			$cleanAndThrow("Empty package");

		$main = reset($folders);

		$package = $this;

		$name = preg_replace("#[\\\\/]+#", "-", $package->{Package::OPTION_NAME});

		$type = function() use ($package, $main, $folder, $name) {
			$path = $folder . DIRECTORY_SEPARATOR . $main["name"] . DIRECTORY_SEPARATOR . $name . ".php";

			$fileData = get_file_data($path, array("type" => "Plugin Type"));

			return strtolower(trim(@$fileData["type"])) ?: Package::TYPE_PLUGIN;
		};

		$src = $folder . DIRECTORY_SEPARATOR . $main["name"];
		$dest = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $type() . "s" . DIRECTORY_SEPARATOR . $name;

		@mkdir($dest, 0777, true);

		if (is_wp_error(copy_dir($src, $dest)))
			$cleanAndThrow("Couldn't move package");

		$wp_filesystem->delete($folder, true);

		$this->log(sprintf("Installed %s in %s", $this->{self::OPTION_NAME}, realpath($dest)));

		return true;
	}

	/**
	 * Include this library.
	 * @throws ErrorException Throws an error if the library couldn't be found 
	 * or the available library doesn't match the required version.
	 * @return Library Returns the Library object for method chaining.
	 */
	public function incl() {
		if (!($paths = array_filter(array_map(array($this, "path"), self::__types()))))
			return $this;

		$incl = function() use ($paths) {
			$dir = \lowtone\content\dir();

			foreach ($paths as $path) {
				if (@include_once $dir . DIRECTORY_SEPARATOR . $path)
					return $path;
			}

			return false;
		};

		if (false === ($path = $incl())) {

			// Try install
			
			try {

				$src = $this->search();
				$file = $this->fetch($src);
				$this->install($file);

			} catch (\Exception $e) {

			}

			// Last try

			if (false === ($path = $incl()))
				throw new exceptions\NotFoundException(sprintf("Package %s not found", $this->{static::OPTION_NAME}));

		}

		if ((isset($this->{self::OPTION_MIN_VERSION}) || isset($this->{self::OPTION_MAX_VERSION})) && !static::checkVersion($path, @$this->{static::OPTION_MIN_VERSION}, @$this->{static::OPTION_MAX_VERSION}))
			throw new exceptions\VersionException(sprintf("Package %s is not the required verion", $this->{static::OPTION_NAME}));

		return $this;
	}

	/**
	 * Get the path for this library.
	 * @return string|NULL Returns the path to the library on success or NULL on 
	 * failure.
	 */
	public function path($type = NULL) {
		if (!($name = $this->{static::OPTION_NAME}))
			return NULL;

		$name = preg_replace("#[\\\\/]+#", "-", $name);

		if (!isset($type))
			$type = static::__type();

		return $type . "s" . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . $name . ".php";
	}

	private function log($message) {
		$file = WP_CONTENT_DIR . "/logs/lowtone-content.log";

		$line = sprintf("[%s] %s", date("Y-m-d H:i:s O"), $message) . PHP_EOL;

		if (false === file_put_contents($file, $line, FILE_APPEND | LOCK_EX))
			return false;

		return true;
	}

	// Static
	
	/**
	 * Include one or more libraries.
	 * @param string|array|Library $libs Either the name for a library, an array 
	 * with its properties or a library object.
	 * @throws ErrorException Throws an error when including any of the 
	 * libraries failed.
	 * @return bool Returns TRUE on success.
	 */
	public static function req($packages) {
		$class = get_called_class();

		$packages = array_map(function($lib) use ($class) {
			if ($lib instanceof $class)
				return $lib;

			else if (is_array($lib))
				return new $class($lib);

			else if (is_string($lib))
				return new $class(array(Package::OPTION_NAME => $lib));

			return NULL;
		}, is_array($packages) ? $packages : func_get_args());

		foreach ($packages as $lib) {
			if (!($lib instanceof static))
				continue;

			$lib->incl();
		}

		return true;
	}

	/**
	 * Check the version for the file at a given path.
	 * @param string $path The path of the file to be checked.
	 * @param string $minVersion The minimum required version number.
	 * @param string $maxVersion The maximum allowed version number.
	 * @param string $version A reference to a variable that will hold the 
	 * version number for the file.
	 * @return bool Returns TRUE if the file matches the required version or 
	 * FALSE if not.
	 */
	public static function checkVersion($path, $minVersion = NULL, $maxVersion = NULL, &$version = NULL) {
		$fileData = get_file_data($path, array("version" => "Version"));
		
		$version = @$fileData["version"];
		
		if (!is_null($minVersion) && version_compare($version, $minVersion, "<"))
			return false;

		if (!is_null($maxVersion) && version_compare($version, $maxVersion, ">"))
			return false;

		return true;
	}

	public static function __types() {
		return apply_filters("lowtone_content_package_types", array(
				self::TYPE_LIB,
				self::TYPE_PLUGIN,
				self::TYPE_THEME
			));
	}

	public static function __type() {
		switch (get_called_class()) {
			case "lowtone\\content\\libraries\\Library":
				return self::TYPE_LIB;

			case "lowtone\\content\\themes\\Theme":
				return self::TYPE_THEME;
				
		}

		return self::TYPE_PLUGIN;
	}
	
	/**
	 * Package init helper.
	 * @todo Requires lowtone\Util??
	 */
	public function init(array $options = NULL) {
		$options = array_merge(array(
				self::INIT_ERROR => function($e) {
					$message = "An unknown error occurred during package initalization";

					if ($e instanceof exceptions\NotFoundException || $e instanceof exceptions\VersionException)
						$message = "Required packages not found";

					$message = sprintf("%s (%s)", $message, $e->getMessage());

					trigger_error($message, E_USER_ERROR);
					var_dump($message);
					flush();
				}
			), (array) $options);

		$error = function(\ErrorException $e) use ($options) {
			if (!is_callable($handler = @$options[self::INIT_ERROR]))
				return false;

			call_user_func($handler, $e);

			return true;
		};


		// Include libraries

		try {
			call_user_func_array("lowtone\\content\\req", @$options[self::INIT_PACKAGES] ?: array("lowtone"));
		} catch (\ErrorException $e) {
			
			$error($e);

			return false;
		}

		do_action("lowtone_content_package_init", $options);

		$result = true;

		// Execute callback

		if (is_callable($success = @$options[self::INIT_SUCCESS]))
			$result = call_user_func($success);

		return $result;
	}

}