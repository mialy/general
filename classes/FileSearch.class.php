<?php
/**
 * File Searching class
 *
 */

/**
 * Open directory and search whithin for files
 *
 * Required PHP 5.2 or newer.
 *
 * @package File System
 * @subpackage Searching
 * @version 1.0
 * @author Mikhail Kyosev <mialygk@gmail.com>
 * @copyright 2012 Mikhail Kyosev
 * @license http://opensource.org/licenses/BSD-2-Clause BSD 2-clause
 */
class FileSearch {
	/**
	 * Directory path
	 * @var string
	 */
	private $_dir;

	/**
	 * Using recursion flag
	 * @var bool
	 */
	private $_recursion;

	/**
	 * Max path depth to searching
	 * @var int
	 */
	private $_maxDepth;

	/**
	 * Sort result by asc, desc or none (return result as is)
	 * @var string
	 */
	private $_sort;

	/**
	 * Show files only on true, or files AND directories on false
	 * @var bool
	 */
	private $_filesOnly;

	/**
	 * Select directory and change options for search
	 *
	 * @param string $baseDir Parent directory from which to start searching
	 * @param array $options Extended options:
	 * <ul>
	 *   <li>bool recursive [fasle] - choose between speed method (looping, default) or slower (recursive)</li>
	 *   <li>int maxDepth [-1]      - search until reach maxDepth depth of subdirectories; -1 w/o limit</li>
	 *   <li>string sort [asc]      - choose sort method - asc, desc or none</li>
	 *   <li>bool filesOnly [true]  - get only files list or files and directories</li>
	 * </ul>
	 */
	public function __construct($baseDir, $options = array()) {
		$this->_recursion = false;
		$this->_maxDepth = -1;
		$this->_dir = $baseDir;
		$this->_sort = 'asc';
		$this->_filesOnly = true;

		if (isset($options['recursion']) && is_bool($options['recursion'])) {
			$this->_recursion = $options['recursion'];
		}

		if (isset($options['maxDepth'])) {
			$this->_maxDepth = (int) $options['maxDepth'];
		}

		if (isset($options['sort'])) {
			$options['sort'] = strtolower($options['sort']);
			if (in_array($options['sort'], array('asc', 'desc', 'none'))) {
				$this->_sort = $options['sort'];
			}
		}

		if (isset($options['filesOnly']) && is_bool($options['filesOnly'])) {
			$this->_filesOnly = $options['filesOnly'];
		}
	}

	/**
	 * Search for files whithin directory and return result as array (w/ path)
	 *
	 * @param string $filter Which files to search (Regular Expression)
	 * @return array found files
	 */
	public function search($filter = "") {
		$files = ($this->_recursion)
			? $this->_searchRec($this->_dir, $filter)
			: $this->_searchLoop($this->_dir, $filter);

		if ($this->_sort === 'asc') {
			sort($files);
		} else if ($this->_sort === 'desc') {
			rsort($files);
		} else {
			// none
		}

		return $files;
	}

	/**
	 * Search files in subdirectories via simple looping
	 *
	 * @param string $dir Parent directory
	 * @param string $filter filter files
	 * @return array found files
	 */
	private function _searchLoop($dir, $filter) {
		$files = array();

		$dirs[] = array($dir, 0);

		while ($dirs) {
			list($dir, $depth) = array_pop($dirs);

			// directories always ends with / even on Windows
			$dir = rtrim(str_replace("\\", '/', $dir), '/') . '/';

			// proceed with next found entry in $dirs
			if (!is_dir($dir) || !($dirHandle = @opendir($dir))) {
				continue;
			}

			while (($entry = readdir($dirHandle)) !== false) {
				$file = $dir . $entry;

				$isTooDepth = $this->_maxDepth >= 0
					&& $depth >= $this->_maxDepth;

				// ignore current (./) and parent (../) directories
				if ($entry === '.' || $entry === '..') {
					continue;
				}
				else if (is_dir($file) && !$isTooDepth) {
					// dynamic add new item to $dirs
					$dirs[] = array($file, $depth + 1);

					if (!$this->_filesOnly) {
						$files[] = $file . '/';
					}
				}
				else if ($this->_isFileFound($file, $filter)) {
					$files[] = $file;
				}
			}

			@closedir($dirHandle);
		}

		return $files;
	}

	/**
	 * Search files in subdirectories via recursion
	 *
	 * @param string $dir Parent directory
	 * @param string $filter filter files
	 * @param int $depth Current depth of recursion
	 * @return array found files
	 */
	private function _searchRec($dir, $filter, $depth = 0) {
		$files = array();

		// directories always ends with / even on Windows
		$dir = rtrim(str_replace("\\", '/', $dir), '/') . '/';

		if (!is_dir($dir) || !($dirHandle = @opendir($dir))) {
			return $files;
		}

		while (($entry = readdir($dirHandle)) !== false) {
			$file = $dir . $entry;

			$isTooDepth = $this->_maxDepth >= 0
				&& $depth >= $this->_maxDepth;

			// ignore current (./) and parent (../) directories
			if ($entry === '.' || $entry === '..') {
				continue;
			}
			else if (is_dir($file) && !$isTooDepth) {
				$newFiles = $this->_searchRec($file, $filter, $depth + 1);
				$files = array_merge($files, $newFiles);

				if (!$this->_filesOnly) {
					$files[] = $file . '/';
				}
			}
			else if ($this->_isFileFound($file, $filter)) {
				$files[] = $file;
			}
		}

		@closedir($dirHandle);

		return $files;
	}

	/**
	 * Check if entry is file and filename match the filter
	 *
	 * @param string $entry File to checking (w/ path)
	 * @param string $filter Filter to match filename
	 * @return boolean True on success, false - otherwise
	 */
	private function _isFileFound($entry, $filter) {
		return is_file($entry)
			&& ( $filter === '' || preg_match($filter, $entry) );
	}
}
