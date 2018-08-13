<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao;

/**
 * Extracts DCA information and cache it
 *
 * The class parses the DCA files and stores various extracts like relations
 * in the cache directory. This meta data can then be loaded and used in the
 * application (e.g. the Model classes).
 *
 * Usage:
 *
 *     $user = DcaExtractor::getInstance('tl_user');
 *
 *     if ($user->hasRelations())
 *     {
 *         print_r($user->getRelations());
 *     }
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class DcaExtractor extends \Controller
{

	/**
	 * Instances
	 * @var DcaExtractor[]
	 */
	protected static $arrInstances = array();

	/**
	 * Table name
	 * @var string
	 */
	protected $strTable;

	/**
	 * Meta data
	 * @var array
	 */
	protected $arrMeta = array();

	/**
	 * Fields
	 * @var array
	 */
	protected $arrFields = array();

	/**
	 * Order fields
	 * @var array
	 */
	protected $arrOrderFields = array();

	/**
	 * Unique fields
	 * @var array
	 */
	protected $arrUniqueFields = array();

	/**
	 * Keys
	 * @var array
	 */
	protected $arrKeys = array();

	/**
	 * Relations
	 * @var array
	 */
	protected $arrRelations = array();

	/**
	 * SQL buffer
	 * @var array
	 */
	protected static $arrSql = array();

	/**
	 * Database table
	 * @var boolean
	 */
	protected $blnIsDbTable = false;

	/**
	 * Load or create the extract
	 *
	 * @param string $strTable The table name
	 *
	 * @throws \Exception If $strTable is empty
	 */
	protected function __construct($strTable)
	{
		if ($strTable == '')
		{
			throw new \Exception('The table name must not be empty');
		}

		parent::__construct();

		$this->strTable = $strTable;

		$strFile = \System::getContainer()->getParameter('kernel.cache_dir') . '/contao/sql/' . $strTable . '.php';

		// Try to load from cache
		if (file_exists($strFile))
		{
			include $strFile;
		}
		else
		{
			$this->createExtract();
		}
	}

	/**
	 * Prevent cloning of the object (Singleton)
	 */
	final public function __clone() {}

	/**
	 * Get one object instance per table
	 *
	 * @param string $strTable The table name
	 *
	 * @return DcaExtractor The object instance
	 */
	public static function getInstance($strTable)
	{
		if (!isset(static::$arrInstances[$strTable]))
		{
			static::$arrInstances[$strTable] = new static($strTable);
		}

		return static::$arrInstances[$strTable];
	}

	/**
	 * Return the meta data as array
	 *
	 * @return array The meta data
	 */
	public function getMeta()
	{
		return $this->arrMeta;
	}

	/**
	 * Return true if there is meta data
	 *
	 * @return boolean True if there is meta data
	 */
	public function hasMeta()
	{
		return !empty($this->arrMeta);
	}

	/**
	 * Return the fields as array
	 *
	 * @return array The fields array
	 */
	public function getFields()
	{
		return $this->arrFields;
	}

	/**
	 * Return true if there are fields
	 *
	 * @return boolean True if there are fields
	 */
	public function hasFields()
	{
		return !empty($this->arrFields);
	}

	/**
	 * Return the order fields as array
	 *
	 * @return array The order fields array
	 */
	public function getOrderFields()
	{
		return $this->arrOrderFields;
	}

	/**
	 * Return true if there are order fields
	 *
	 * @return boolean True if there are order fields
	 */
	public function hasOrderFields()
	{
		return !empty($this->arrOrderFields);
	}

	/**
	 * Return an array of unique columns
	 *
	 * @return array
	 */
	public function getUniqueFields()
	{
		return $this->arrUniqueFields;
	}

	/**
	 * Return true if there are unique fields
	 *
	 * @return boolean True if there are unique fields
	 */
	public function hasUniqueFields()
	{
		return !empty($this->arrUniqueFields);
	}

	/**
	 * Return the keys as array
	 *
	 * @return array The keys array
	 */
	public function getKeys()
	{
		return $this->arrKeys;
	}

	/**
	 * Return true if there are keys
	 *
	 * @return boolean True if there are keys
	 */
	public function hasKeys()
	{
		return !empty($this->arrKeys);
	}

	/**
	 * Return the relations as array
	 *
	 * @return array The relations array
	 */
	public function getRelations()
	{
		return $this->arrRelations;
	}

	/**
	 * Return true if there are relations
	 *
	 * @return boolean True if there are relations
	 */
	public function hasRelations()
	{
		return !empty($this->arrRelations);
	}

	/**
	 * Return true if the extract relates to a database table
	 *
	 * @return boolean True if the extract relates to a database table
	 */
	public function isDbTable()
	{
		return $this->blnIsDbTable;
	}

	/**
	 * Return an array that can be used by the database installer
	 *
	 * @return array The data array
	 */
	public function getDbInstallerArray()
	{
		$return = array();

		// Fields
		foreach ($this->arrFields as $k=>$v)
		{
			if (\is_array($v))
			{
				if (!isset($v['name']))
				{
					$v['name'] = $k;
				}

				$return['SCHEMA_FIELDS'][$k] = $v;
			}
			else
			{
				$return['TABLE_FIELDS'][$k] = '`' . $k . '` ' . $v;
			}
		}

		$quote = function ($item) { return '`' . $item . '`'; };

		// Keys
		foreach ($this->arrKeys as $k=>$v)
		{
			// Handle multi-column indexes (see #5556)
			if (strpos($k, ',') !== false)
			{
				$f = array_map($quote, \StringUtil::trimsplit(',', $k));
				$k = str_replace(',', '_', $k);
			}
			else
			{
				$f = array($quote($k));
			}

			// Handle key lengths (see #221)
			if (preg_match('/\([0-9]+\)/', $v))
			{
				list($v, $length) = explode('(', rtrim($v, ')'));
				$f = array($quote($k) . '(' . $length . ')');
			}

			if ($v == 'primary')
			{
				$k = 'PRIMARY';
				$v = 'PRIMARY KEY  (' . implode(', ', $f) . ')';
			}
			elseif ($v == 'index')
			{
				$v = 'KEY `' . $k . '` (' . implode(', ', $f) . ')';
			}
			else
			{
				$v = strtoupper($v) . ' KEY `' . $k . '` (' . implode(', ', $f) . ')';
			}

			$return['TABLE_CREATE_DEFINITIONS'][$k] = $v;
		}

		$return['TABLE_OPTIONS'] = '';

		// Options
		foreach ($this->arrMeta as $k=>$v)
		{
			if ($k == 'engine')
			{
				$return['TABLE_OPTIONS'] .= ' ENGINE=' . $v;
			}
			elseif ($k == 'charset')
			{
				$return['TABLE_OPTIONS'] .= ' DEFAULT CHARSET=' . $v;
			}
		}

		return $return;
	}

	/**
	 * Create the extract from the DCA or the database.sql files
	 */
	protected function createExtract()
	{
		// Load the default language file (see #7202)
		if (empty($GLOBALS['TL_LANG']['MSC']))
		{
			System::loadLanguageFile('default');
		}

		// Load the data container
		if (!isset($GLOBALS['loadDataContainer'][$this->strTable]))
		{
			$this->loadDataContainer($this->strTable);
		}

		// Return if the DC type is "File"
		if ($GLOBALS['TL_DCA'][$this->strTable]['config']['dataContainer'] == 'File')
		{
			return;
		}

		// Return if the DC type is "Folder" and the DC is not database assisted
		if ($GLOBALS['TL_DCA'][$this->strTable]['config']['dataContainer'] == 'Folder' && empty($GLOBALS['TL_DCA'][$this->strTable]['config']['databaseAssisted']))
		{
			return;
		}

		$blnFromFile = false;
		$arrRelations = array();

		// Check whether there are fields (see #4826)
		if (isset($GLOBALS['TL_DCA'][$this->strTable]['fields']))
		{
			foreach ($GLOBALS['TL_DCA'][$this->strTable]['fields'] as $field=>$config)
			{
				// Check whether all fields have an SQL definition
				if (!isset($config['sql']) && isset($config['inputType']))
				{
					$blnFromFile = true;
				}

				// Check whether there is a relation (see #6524)
				if (isset($config['relation']))
				{
					$table = substr($config['foreignKey'], 0, strrpos($config['foreignKey'], '.'));
					$arrRelations[$field] = array_merge(array('table'=>$table, 'field'=>'id'), $config['relation']);

					// Table name and field name are mandatory
					if (empty($arrRelations[$field]['table']) || empty($arrRelations[$field]['field']))
					{
						throw new \Exception('Incomplete relation defined for ' . $this->strTable . '.' . $field);
					}
				}
			}
		}

		$sql = $GLOBALS['TL_DCA'][$this->strTable]['config']['sql'] ?: array();
		$fields = $GLOBALS['TL_DCA'][$this->strTable]['fields'] ?: array();

		// Deprecated since Contao 4.0, to be removed in Contao 5.0
		if ($blnFromFile)
		{
			@trigger_error('Using database.sql files has been deprecated and will no longer work in Contao 5.0. Use a DCA file instead.', E_USER_DEPRECATED);

			if (!isset(static::$arrSql[$this->strTable]))
			{
				$arrSql = array();

				try
				{
					$files = \System::getContainer()->get('contao.resource_locator')->locate('config/database.sql', null, false);
				}
				catch (\InvalidArgumentException $e)
				{
					$files = array();
				}

				foreach ($files as $file)
				{
					$arrSql = array_merge_recursive($arrSql, \SqlFileParser::parse($file));
				}

				static::$arrSql = $arrSql;
			}

			$arrTable = static::$arrSql[$this->strTable];

			if (\is_array($arrTable['TABLE_OPTIONS']))
			{
				$arrTable['TABLE_OPTIONS'] = $arrTable['TABLE_OPTIONS'][0]; // see #324
			}

			list($engine, , $charset) = explode(' ', trim($arrTable['TABLE_OPTIONS']));

			if ($engine != '')
			{
				$sql['engine'] = str_replace('ENGINE=', '', $engine);
			}
			if ($charset != '')
			{
				$sql['charset'] = str_replace('CHARSET=', '', $charset);
			}

			// Fields
			if (isset($arrTable['TABLE_FIELDS']))
			{
				foreach ($arrTable['TABLE_FIELDS'] as $k=>$v)
				{
					$fields[$k]['sql'] = str_replace('`' . $k . '` ', '', $v);
				}
			}

			// Keys
			if (isset($arrTable['TABLE_CREATE_DEFINITIONS']))
			{
				foreach ($arrTable['TABLE_CREATE_DEFINITIONS'] as $strKey)
				{
					if (preg_match('/^([A-Z]+ )?KEY .+\(([^)]+)\)$/', $strKey, $arrMatches) && preg_match_all('/`([^`]+)`/', $arrMatches[2], $arrFields))
					{
						$type = trim($arrMatches[1]);
						$field = implode(',', $arrFields[1]);
						$sql['keys'][$field] = ($type != '') ? strtolower($type) : 'index';
					}
				}
			}
		}

		// Not a database table or no field information
		if (empty($sql) || empty($fields))
		{
			return;
		}

		// Add the default engine and charset if none is given
		if (empty($sql['engine']))
		{
			$sql['engine'] = 'MyISAM';
		}
		if (empty($sql['charset']))
		{
			$sql['charset'] = \Config::get('dbCharset');
		}

		// Meta
		$this->arrMeta = array
		(
			'engine'  => $sql['engine'],
			'charset' => $sql['charset']
		);

		// Fields
		if (!empty($fields))
		{
			$this->arrFields = array();
			$this->arrOrderFields = array();

			foreach ($fields as $field=>$config)
			{
				if (isset($config['sql']))
				{
					$this->arrFields[$field] = $config['sql'];
				}

				// Only add order fields of binary fields (see #7785)
				if (isset($config['inputType']) && $config['inputType'] == 'fileTree' && isset($config['eval']['orderField']))
				{
					$this->arrOrderFields[] = $config['eval']['orderField'];
				}

				if (isset($config['eval']['unique']) && $config['eval']['unique'])
				{
					$this->arrUniqueFields[] = $field;
				}
			}
		}

		// Keys
		if (!empty($sql['keys']) && \is_array($sql['keys']))
		{
			$this->arrKeys = array();

			foreach ($sql['keys'] as $field=>$type)
			{
				$this->arrKeys[$field] = $type;

				if ($type == 'unique')
				{
					$this->arrUniqueFields[] = $field;
				}
			}
		}

		// Relations
		if (!empty($arrRelations))
		{
			$this->arrRelations = array();

			foreach ($arrRelations as $field=>$config)
			{
				$this->arrRelations[$field] = array();

				foreach ($config as $k=>$v)
				{
					$this->arrRelations[$field][$k] = $v;
				}
			}
		}

		$this->arrUniqueFields = array_unique($this->arrUniqueFields);
		$this->blnIsDbTable = true;
	}
}