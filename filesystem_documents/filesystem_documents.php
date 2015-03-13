<?php
/**
 * @version		$Id: filesystem_documents.php 1075 2010-10-18 20:16:00Z robs $
 * @package		JXtended.Finder
 * @subpackage	plgFinderFileSystem_Documents
 * @copyright	Copyright (C) 2007 - 2010 JXtended, LLC. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 * @link		http://jxtended.com
 */

defined('JPATH_BASE') or die;

// Load the base adapter.
require_once JPATH_ADMINISTRATOR.'/components/com_finder/helpers/indexer/adapter.php';
// Set the adapter support flag.
define('FINDER_ADAPTER_DOC_SUPPORT', true);
// Set the adapter support flag.
define('FINDER_ADAPTER_PDF_SUPPORT', true);
// Load the language files for the adapter.
$lang = JFactory::getLanguage();
$lang->load('plg_finder_filesystem_documents');
$lang->load('plg_finder_filesystem_documents.custom');

/**
 * Finder adapter for DOCman Documents.
 *
 * @package		JXtended.Finder
 * @subpackage	plgFinderFileSystem_Documents
 */
class plgFinderFileSystem_Documents extends FinderIndexerAdapter
{
	/**
	 * @var		string		The plugin identifier.
	 */
	protected $_context = 'FileSystem_Documents';

	/**
	 * @var		string		The type of content the adapter indexes.
	 */
	protected $_type_title = 'Document';

	/**
	 * @var		string		The sublayout to use when rendering the results.
	 */
	protected $_layout = 'document';

	/**
	 * @var		array		The files to index.
	 */
	private $_files = array();

	/**
	 * @var		string		The file pattern to match.
	 */
	private $_match = '(.doc$)|(.docx$)|(.pdf$)|(.htm$)|(.html$)|(.txt$)|(.xml$)';

	/**
	 * Method to index an item. The item must be a FinderIndexerResult object.
	 *
	 * @param	object		The item to index as an FinderIndexerResult object.
	 * @throws	Exception on database error.
	 */
	protected function index(FinderIndexerResult $item)
	{
		//print_r($item);die('here');
		// Set the file name and base path.
		$item->filename	= basename($item->fullpath);
		$item->filepath = JPath::clean(str_replace(JPATH_SITE, '', $item->fullpath));
		$item->filepath = ltrim($item->filepath, '/');

		// Set the mime type (not a real mime type but it will do).
		$item->mime		= strtolower(JFile::getExt($item->filename));

		// Build the necessary route and path information.
		
		$item->url		= $this->getURL($item->filepath,0,0);
		//echo $item->url;die;
		//$item->url		= $this->getURL($item->id, $this->extension, $this->layout);
		$item->route	= $this->getURL($item->filepath,0,0);
		//$item->route	= $this->getURL($item->id, $this->extension, $this->layout);
		$item->path		= $item->route;

		// Set the link data.
		$item->title	= $item->filename;
		$item->state	= 1;
		//$item->access	= 0;
		//updated by Mukta to set access 1
		$item->access	= 1;
		
		// Add the meta-data processing instructions.
		$item->addInstruction(FinderIndexer::META_CONTEXT, 'fullpath');

		// Get the physical file size.
		$item->size = filesize($item->fullpath);
		// Open the stream.
		$item->body = $this->openFile($item->fullpath, $item->mime);
		//print_r($item->body);die;
		// Read the first 2K from the stream for the summary.
		if (is_resource($item->body)) {
			$item->summary = fread($item->body, 2048);
		}

		// Set the language.
		$item->language	= FinderIndexerHelper::getDefaultLanguage();

		// If a mime type is defined for this item, use that as the item type.
		if (!empty($item->mime))
		{
			// Override the adapter mime type.
			$this->_mime = $item->mime;

			// Override the type title.
			$this->_type_title = strtoupper($item->mime);

			// Check if the file type is already defined.
			$this->_type_id = $this->getTypeId();

			// Add the type and get the new type id if necessary.
			if (empty($this->_type_id)) {
				$this->_type_id = FinderIndexerHelper::addContentType($this->_type_title, $this->_mime);
			}

			// Override the type id and layout for the result item.
			$item->type_id	= $this->_type_id;
			$item->layout	= $this->_mime;

			// Add the type taxonomy data.
			$item->addTaxonomy('Type', $this->_type_title);
		}
		// If no file type is defined, use the base type of "Document".
		else
		{
			// Add the type taxonomy data.
			$item->addTaxonomy('Type', 'Document');
		}

		// Index the item.
		$this->indexer->index($item);

		// Close the file.
		if (is_resource($item->body)) {
			pclose($item->body);
		}
	}

	/**
	 * Method to setup the indexer to be run.
	 *
	 * @return	boolean		True on success, false on failure.
	 */
	protected function setup()
	{
		// Load dependent classes.
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');
		jimport('joomla.filesystem.path');

		// Load the directories.
		$this->loadFiles();

		return true;
	}

	/**
	 * Method to get the number of content items available to index.
	 *
	 * @return	integer		The number of content items available to index.
	 */
	protected function getContentCount()
	{
		// Load the files.
		$this->loadFiles();

		return count($this->_files);
	}

	/**
	 * Method to get a list of content items to index.
	 *
	 * @param	integer		The list offset.
	 * @param	integer		The list limit.
	 * @return	array		An array of FinderIndexerResult objects.
	 * @throws	Exception on database error.
	 */
	protected function getItems($offset, $limit, $sql = null)
	{
		//echo "test";die;
		$items = array();

		// Load the files.
		$this->loadFiles();

		// Get the items to index.
		$rows = array_slice($this->_files, $offset, $limit);

		// Convert the items to result objects.
		foreach ($rows as $row)
		{
			// Convert the item to a result object.
			$item = new FinderIndexerResult();

			// Set the item type.
			$item->type_id	= $this->_type_id;

			// Set the mime type.
			$item->mime		= $this->_mime;

			// Set the item layout.
			$item->layout	= $this->_layout;

			// Set the full path.
			$item->fullpath	= JPath::clean($row);

			// Add the item to the stack.
			$items[] = $item;
		}

		return $items;
	}

	/**
	 * Method to get the URL for the item. The URL is how we look up the link
	 * in the Finder index.
	 *
	 * @param	path		The path of the item.
	 * @return	string		The URL of the item.
	 */
	protected function getURL($path, $extension, $view)
	{
		return $path;
	}

	/**
	 * Method to load the files from the directories, sanitize their paths, and
	 * verify that they are at least 100 bytes in size.
	 *
	 * @return	boolean		True on success.
	 */
	private function loadFiles()
	{
		// Check if the files are already loaded.
		if (count($this->_files)) {
			return true;
		}

		$files = array();

		// Load dependent classes.
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');
		jimport('joomla.filesystem.path');

		// Get the directories.
		// Changed by mukta to get multiple directories to index.
		$dirs = $this->params->get('directories');
		//$dirs = empty($dirs) ? array() : (array)$dirs;
		$dirs = empty($dirs) ? array() : explode("\n",$dirs);

		// Sanitize the directories.
		for ($i = 0, $c = count($dirs); $i < $c; $i++)
		{
			// Clean the directory and prefix the base path.
			$dirs[$i] = JPATH_SITE.DS.JPath::clean($dirs[$i]);

			// Remove the directory if it does not exist.
			if (!JFolder::exists($dirs[$i])) {
				unset($dirs[$i]);
			}
		}

		// Rekey the directories.
		$dirs = array_values($dirs);

		// Get the files in each directory.
		foreach ($dirs as $dir)
		{
			// Get the files.
			$results = JFolder::files($dir, $this->_match, false, true);

			// Check the file.
			for ($i = 0, $c = count($results); $i < $c; $i++)
			{
				// Remove the file if it does not exist.
				if (!JFile::exists($results[$i])) {
					unset($results[$i]);
				}

				// Remove the file if less than 100 bytes.
				if (filesize($results[$i]) < 100) {
					unset($results[$i]);
				}
			}

			// Merge in the remaining files.
			if (!empty($results)) {
				$files = array_merge($files, $results);
			}
		}

		// Set the files.
		$this->_files = array_values($files);
		return true;
	}

	/**
	 * Method to open a file as a stream.
	 *
	 * @param	string		The file path to open.
	 * @param	string		The file mime type.
	 * @return	mixed		A popen() resource on success, false on failure.
	 */
	private function openFile($path, $mime)
	{
		$return = false;

		// Make sure the path is clean!
		$path = JPath::clean($path);

		// Handle the supported mime types.
		switch (strtoupper($mime))
		{
			/*
			 * Handle DOC files using antiword.
			 */
			case 'DOC':
			{
				
				// Check if DOC support is available.
				if (!defined('FINDER_ADAPTER_DOC_SUPPORT')) break;

				// Handle Windows.
				if (JApplication::isWinOS()) {
					$command = dirname(__FILE__).DS.'antiword'.DS.'antiword.exe';
				}
				// Handle FreeBSD.
				elseif (php_uname('s') == 'FreeBSD') {
					$command = dirname(__FILE__).DS.'antiword'.DS.'antiword-freebsd';
				}
				// Handle Apple OS X.
				elseif (php_uname('s') == 'Darwin') {
					$command = dirname(__FILE__).DS.'antiword'.DS.'antiword-darwin';
				}
				// Default to Linux.
				else {
					$command = dirname(__FILE__).DS.'antiword'.DS.'antiword-linux';
				}

				// Antiword requires the HOME environment variable to be set.
				if (!getenv('HOME')) {
					putenv('HOME="'.dirname(__FILE__).'"');
				}

				// Set the ANTIWORDHOME environment variable.
				putenv('ANTIWORDHOME='.dirname(__FILE__).DS.'antiword'.DS.'resources'.DS);

				// Open the file as a process resource.
				//echo $command;
				//echo $path;die('mukta');
				if (JFile::exists($command) && JFile::exists($path)) {
				//	$return = popen(sprintf('%s -m UTF-8 "%s" 2>&1', $command, $path), 'r');
							$handle = popen(sprintf('%s -m UTF-8 "%s" 2>&1', $command, $path), 'r');
						echo "'$handle'; " . gettype($handle) . "\n";
						$read = fread($handle, 2096);
						echo $read;die('mukta');
						pclose($handle);
				}
				
			} break;

			/*
			 * Handle DOCX files using docx2txt.
			 */
			case 'DOCX':
			{
				// Handle all environments.
				$command = dirname(__FILE__).'/docx2txt/docx2txt.pl';

				// Open the file as a process resource.
				if (JFile::exists($command) && JFile::exists($path)) {
					$return = popen(sprintf('perl %s "%s" - 2>&1', $command, $path), 'r');
				}
			} break;

			/*
			 * Handle PDF files using pdftotext.
			 */
			case 'PDF':
			{
				// Check if PDF support is available.
				if (!defined('FINDER_ADAPTER_PDF_SUPPORT')) break;

				// Handle Windows.
				if (JApplication::isWinOS()) {
					$command = dirname(__FILE__).'/xpdf/pdftotext.exe';
				}
				// Handle FreeBSD.
				elseif (php_uname('s') == 'FreeBSD') {
					$command = dirname(__FILE__).'/xpdf/pdftotext-freebsd';
				}
				// Handle Apple OS X.
				elseif (php_uname('s') == 'Darwin') {
					$command = dirname(__FILE__).'/xpdf/pdftotext-darwin';
				}
				// Default to Linux.
				else {
					
					$command = dirname(__FILE__).'/xpdf/pdftotext-linux';
					
				}

				// Open the file as a process resource.
				//echo $command;die;
				if (JFile::exists($command) && JFile::exists($path)) {
				//echo "command :".$command.'<br/>';
					//echo "path :".$path.'<br/>';//die;
					$return = popen(sprintf('%s -enc "UTF-8" -eol unix -nopgbrk "%s" - 2>&1', $command, $path), 'r');
					/*
					 * 'o/p : Resource id #142'; resource
						Segmentation fault (core dumped) */
					/*	$handle = popen(sprintf('%s -enc "UTF-8" -eol unix -nopgbrk "%s" - 2>&1', $command, $path), 'r');
						echo "'$handle'; " . gettype($handle) . "\n";
						$read = fread($handle, 2096);
						echo $read;
						pclose($handle);*/
				}
			} break;

			/*
			 * Handle TXT, HTML, and XML files.
			 */
			case 'HTM':
			case 'HTML':
			case 'TXT':
			case 'XML':
			{
				// Open the file as a resource.
				if (JFile::exists($path)) {
					$return = @fopen($path, 'r');
				}
			} break;
		}
//echo $return;die('return');
		return $return;
	}
}
