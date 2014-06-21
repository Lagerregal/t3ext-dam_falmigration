<?php
namespace TYPO3\CMS\DamFalmigration\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Stefan Froemken <froemken@gmail.com>
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
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Messaging\FlashMessage;

/**
 * Migrate DAM category relations to sys_category_record_mm
 */
class MigrateCategoryRelationService extends AbstractService {

	/**
	 * main function
	 *
	 * @return boolean
	 * @throws \Exception
	 */
	public function start() {
		$categoryRelations = $this->getCategoryRelationsWhereSysCategoryExists();
		$amountOfMigratedRelations = 0;
		echo 'Start migrating DAM category relations to sys_category_record_mm:' . PHP_EOL;
		foreach ($categoryRelations as $categoryRelation) {
			$insertData = array(
				'uid_local' => $categoryRelation['sys_category_uid'],
				'uid_foreign' => $this->getUidOfRelatedMetadata($categoryRelation['sys_file_uid']),
				'sorting' => $categoryRelation['sorting'],
				'sorting_foreign' => $categoryRelation['sorting_foreign'],
				'tablenames' => 'sys_file_metadata',
				'fieldname' => 'categories'
			);

			if (!$this->checkIfSysCategoryRelationExists($categoryRelation)) {
				$this->databaseConnection->exec_INSERTquery(
					'sys_category_record_mm',
					$insertData
				);
				echo '.';
				$amountOfMigratedRelations++;
			}
		}
		echo PHP_EOL . 'We have migrated ' . $amountOfMigratedRelations . ' DAM category relations to sys_category_record_mm:' . PHP_EOL;
	}

	/**
	 * get UID of Metadata of given sys_file-record
	 *
	 * @param integer $sysFile
	 * @return integer The UID of sys_file_metadata
	 */
	protected function getUidOfRelatedMetadata($sysFile) {
		$metadata = $this->databaseConnection->exec_SELECTgetSingleRow(
			'uid',
			'sys_file_metadata',
			'file=' . (int)$sysFile
		);
		return $metadata['uid'];
	}

	/**
	 * After a migration of tx_dam_cat -> sys_category the col _migrateddamcatuid is filled with dam category uid
	 * Now we can search in dam category relations for dam categories which have already been migrated to sys_category
	 *
	 * @throws \Exception
	 * @return array
	 */
	protected function getCategoryRelationsWhereSysCategoryExists() {
		$rows = $this->databaseConnection->exec_SELECTgetRows(
			'MM.*, SF.uid as sys_file_uid, SC.uid as sys_category_uid',
			'tx_dam_mm_cat MM, sys_file SF, sys_category SC',
			'SC._migrateddamcatuid = MM.uid_foreign AND SF._migrateddamuid = MM.uid_local'
		);
		if ($rows === NULL) {
			throw new \Exception('SQL-Error in getCategoryRelationsWhereSysCategoryExists()', 1382968725);
		} elseif (count($rows) === 0) {
			throw new \Exception('There are no migrated dam categories in sys_category. Please start to migrate DAM Cat -> sys_category first. Or, maybe there are no dam categories to migrate', 1382968775);
		} else return $rows;
	}

	/**
	 * check if a sys_category_record_mm already exists
	 *
	 * @param array $categoryRelation
	 * @return boolean
	 */
	protected function checkIfSysCategoryRelationExists(array $categoryRelation) {
		$amountOfExistingRecords = $this->databaseConnection->exec_SELECTcountRows(
			'*',
			'sys_category_record_mm',
			'uid_local = ' . $categoryRelation['sys_file_uid'] .
			' AND uid_foreign = ' . $categoryRelation['sys_category_uid'] .
			' AND tablenames = "sys_file_metadata"'
		);
		if ($amountOfExistingRecords) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

}