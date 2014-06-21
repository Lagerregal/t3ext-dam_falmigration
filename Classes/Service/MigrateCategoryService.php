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
 * Migrate DAM categories to sys_category
 */
class MigrateCategoryService extends AbstractService {

	/**
	 * main function
	 *
	 * @param integer $initialParentUid
	 * @param integer $storeOnPid
	 * @throws \Exception
	 */
	public function start($initialParentUid, $storeOnPid) {
		if (!$this->checkIfInitialParentIsAvailable($initialParentUid)) {
			$initialParentUid = 0;
		}

		// $parrentUidMap[oldUid] = 'newUid';
		$parentUidMap = array();
		$parentUidMap[0] = $initialParentUid;

		$amountOfMigratedCategories = 0;
		$damCategories = $this->getAllDamCategories();
		if (!empty($damCategories)) {
			echo 'Start sorting categories' . PHP_EOL;
			$damCategories = $this->sortingCategories($damCategories, 0);
			echo 'Start migrating categories:' . PHP_EOL;
			foreach ($damCategories as $category) {
				$newParentUid = $parentUidMap[$category['parent_id']];

				// here the new category gets created in table sys_category
				$newUid = $this->createNewCategory($category, $newParentUid, $storeOnPid);
				$parentUidMap[$category['uid']] = $newUid;
				$amountOfMigratedCategories++;
				echo '.';
			}
			echo PHP_EOL . 'We have migrated ' . $amountOfMigratedCategories . ' DAM categories to sys_category.' . PHP_EOL;
		} else {
			echo 'There are no DAM categories to migrate.' . PHP_EOL;
		}
	}

	/**
	 * Gets all available (not deleted) DAM categories.
	 * Returns array with all categories.
	 *
	 * @return array
	 */
	protected function getAllDamCategories() {
		echo 'Retrieving not migrated DAM categories' . PHP_EOL;
		// this query can also count all related categories (sys_category.items)
		$damCategories = $this->databaseConnection->exec_SELECTgetRows(
			'tx_dam_cat.*',
			'tx_dam_cat LEFT JOIN sys_category ON (tx_dam_cat.uid = sys_category._migrateddamcatuid)',
			'sys_category.uid IS NULL AND tx_dam_cat.deleted = 0',
			'', 'parent_id', ''
		);
		return $damCategories;
	}

	/**
	 * Adds new categorie in table sys_category. Requires the record array and the
	 * new parent_id for it has changed from DAM to FAL migration.
	 *
	 * @param array $record
	 * @param integer $newParentUid
	 * @param integer $storeOnPid
	 * @return integer UID of sys_category
	 */
	protected function createNewCategory($record, $newParentUid, $storeOnPid) {
		$sysCategory = array(
			'pid' => (int)$storeOnPid,
			'parent' => (int)$newParentUid,
			'tstamp' => (int)$record['tstamp'],
			'sorting' => (int)$record['sorting'],
			'crdate' => (int)$record['crdate'],
			'cruser_id' => (int)$record['cruser_id'],
			'hidden' => (int)$record['hidden'],
			'title' => $record['title'],
			'description' => (string)$record['description'],
			'items' => (int)$record['items'],
			'_migrateddamcatuid' => (int)$record['uid']
		);

		$this->databaseConnection->exec_INSERTquery('sys_category', $sysCategory);
		$newUid = $this->databaseConnection->sql_insert_id();
		return $newUid;
	}


	/**
	 * Resorts the category array for we have the parent categories BEFORE the subcategories!
	 * Runs recursively down the cat-tree.
	 *
	 * @param $damCategories
	 * @param $parentUid
	 * @return array
	 */
	protected function sortingCategories($damCategories, $parentUid) {
		// New array for sorting dam records
		$sortedDamCategories = array();
		// Remember the uids for finding sub-categories
		$rememberUids = array();

		// Find all categories for the given parent_uid
		foreach($damCategories as $key =>$category) {
			if($category['parent_id'] == $parentUid) {
				$sortedDamCategories[] = $category;
				$rememberUids[] = $category['uid'];

				// The current entry isn't needed anymore, so remove it from the array.
				unset($damCategories[$key]);
			}
		}

		// Search for sub-categories recursivliy
		foreach ($rememberUids as $nextLevelUid) {
			$subCategories = $this->sortingCategories($damCategories,$nextLevelUid);
			if (count($subCategories) > 0) {
				foreach ($subCategories as $newCategory) {
					$sortedDamCategories[] = $newCategory;
				}
			}
		}

		return $sortedDamCategories;
	}

	/**
	 * Check if the wanted parent uid is available.
	 *
	 * @param integer $initialParent
	 * @return boolean
	 */
	protected function checkIfInitialParentIsAvailable($initialParent) {
		// a parent of 0 is always available
		if ($initialParent === 0) {
			return TRUE;
		} else {
			$amount = $this->databaseConnection->exec_SELECTcountRows(
				'*',
				'sys_category',
				'uid=' . (int)$initialParent . ' AND deleted=0'
			);
			if ($amount > 0) {
				return TRUE;
			} else {
				return FALSE;
			}
		}
	}

}