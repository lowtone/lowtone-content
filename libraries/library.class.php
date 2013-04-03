<?php
namespace lowtone\content\libraries;
use ErrorException;

/**
 * A representation and interface for shared libraries.
 * @author Paul van der Meijs <code@lowtone.nl>
 * @copyright Copyright (c) 2011-2012, Paul van der Meijs
 * @license http://wordpress.lowtone.nl/license/
 * @version 1.0
 * @package wordpress\plugins\lowtone\content\libraries
 */
class Library {

	const OPTION_NAME = "name",
		OPTION_MIN_VERSION = "min_version",
		OPTION_MAX_VERSION = "max_version";

	public function __construct(array $options = NULL) {
		foreach ($options as $name => $value)
			$this->$name = $value;
	}

	/**
	 * Fetch the files for the library from a repository.
	 * @throws ErrorException Throws an error if the required library is not 
	 * available.
	 * @return Library Returns the library on success.
	 */
	public function fetch() {

	}

	/**
	 * Install a library from a zipped file.
	 * @throws ErrorException Throws an error if the zipped library couldn't be
	 * extracted.
	 * @return [type] [description]
	 */
	public function install() {

	}

	/**
	 * Include this library.
	 * @throws ErrorException Throws an error if the library couldn't be found 
	 * or the available library doesn't match the required version.
	 * @return Library Returns the Library object for method chaining.
	 */
	public function incl() {
		if (NULL === ($path = $this->path()))
			return $this;

		if (!include_once $path)
			throw new ErrorException(sprintf("Library %s not found", $this->{static::OPTION_NAME}));

		if ((isset($this->{self::OPTION_MIN_VERSION}) || isset($this->{self::OPTION_MAX_VERSION})) && !static::checkVersion($path, @$this->{static::OPTION_MIN_VERSION}, @$this->{static::OPTION_MAX_VERSION}))
			throw new ErrorException(sprintf("Library %s is not the required verion", $this->{static::OPTION_NAME}));

		return $this;
	}

	/**
	 * Get the path for this library.
	 * @return string|NULL Returns the path to the library on success or NULL on 
	 * failure.
	 */
	public function path() {
		if (!($name = $this->{static::OPTION_NAME}))
			return NULL;

		$name = preg_replace("#[\\\\/]+#", "-", $name);

		return LIB_DIR . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . $name . ".php";
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
	public static function req($libs) {
		$libs = array_map(function($lib) {
			if ($lib instanceof Library)
				return $lib;

			else if (is_array($lib))
				return new Library($lib);

			else if (is_string($lib))
				return new Library(array(Library::OPTION_NAME => $lib));

			return NULL;
		}, is_array($libs) ? $libs : func_get_args());

		foreach ($libs as $lib) {
			if (!($lib instanceof Library))
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

}