<?php
namespace TYPO3\CMS\DamFalmigration\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Benjamin Mack <benni@typo3.org>
 *  (c) 2013 Stefan Froemken <froemken@gmail.com>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * @author Benjamin Mack <benni@typo3.org>
 */
abstract class AbstractService {

	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManager
	 * @inject
	 */
	protected $objectManager;

	/**
	 * @var \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected $databaseConnection;

	/**
	 * @inject
	 * @var \TYPO3\CMS\Core\Resource\FileRepository
	 */
	protected $fileRepository;

	/**
	 * @var \TYPO3\CMS\Core\Resource\ResourceStorage
	 */
	protected $storageObject;

	/**
	 * @var integer the storage uid for fileadmin
	 */
	protected $storageUid = 1;

	/**
	 * @var integer amount of migrated records
	 */
	protected $amountOfMigratedRecords = 0;

	/**
	 * initializes this object
	 */
	public function initializeObject() {
		$this->databaseConnection = $GLOBALS['TYPO3_DB'];
		$fileFactory = ResourceFactory::getInstance();
		$this->storageObject = $fileFactory->getStorageObject($this->storageUid);
	}

	/**
	 * check if given table exists in current database
	 * we can't check TCA or for installed extensions because dam and dam_ttcontent are not available for TYPO3 6.2
	 *
	 * @param string $table
	 * @return bool
	 */
	protected function isTableAvailable($table) {
		$tables = $this->databaseConnection->admin_get_tables();
		return array_key_exists($table, $tables);
	}

}