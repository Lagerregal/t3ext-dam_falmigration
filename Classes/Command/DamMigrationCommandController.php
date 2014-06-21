<?php

namespace B13\DamFalmigration\Command;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Benjamin Mack <typo3@b13.de>
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
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\DamFalmigration\Service\MigrateRelations;

/**
 * Command Controller to execute DAM to FAL Migration scripts
 *
 * @package B13\DamFalmigration
 * @subpackage Controller
 */
class DamMigrationCommandController extends AbstractCommandController {

	/**
	 * @var \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected $databaseConnection = NULL;

	/**
	 * @return void
	 */
	public function initializeObject() {
		$this->databaseConnection = $GLOBALS['TYPO3_DB'];
	}

	/**
	 * goes through all DAM files and checks if they have a counterpart in the sys_file
	 * table. If not, fetch the file (via the storage, which indexes the file directly)
	 * and update the DAM DB table
	 * Please note that this does not migrate the metadata
	 * this command can be run multiple times
	 *
	 * @param \integer $storageUid the UID of the storage (usually 1, don't modify if you are unsure)
	 */
	public function migrateDamRecordsCommand($storageUid = 1) {

		echo 'Start migrating DAM (tx_dam) records into FAL (sys_file)' . PHP_EOL;

		// create the storage object
		$storageObject = ResourceFactory::getInstance()->getStorageObject($storageUid);
		$migratedFiles = 0;

		// get all DAM records that have not been migrated yet
		$damRecords = $this->databaseConnection->exec_SELECTgetRows(
			'*',
			'tx_dam',
			// check for all FAL records that are there, that have been migrated already
			// seen by the "_migrateddamuid" flag
			'deleted=0 AND uid NOT IN (SELECT _migrateddamuid FROM sys_file WHERE _migrateddamuid > 0)'
		);

		if (is_array($damRecords) && $damRecords !== array()) {
			echo 'We have found ' . count($damRecords) . ' DAM records with no connected sys_file entry' . PHP_EOL;
			foreach ($damRecords as $damRecord) {
				$fileIdentifier = $damRecord['file_path'] . $damRecord['file_name'];

				// right now, only files in fileadmin/ are supported
				if (\TYPO3\CMS\Core\Utility\GeneralUtility::isFirstPartOfStr($fileIdentifier, 'fileadmin/') === FALSE) {
					continue;
				}

				// strip away the "fileadmin/" prefix
				$fullFileName = substr($fileIdentifier, 10);

				// check if the DAM record is already indexed for FAL (based on the filename)
				$fileObject = NULL;
				try {
					$fileObject = $storageObject->getFile($fullFileName);
				} catch(\TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException $e) {
					// file not found jump to next file
					$this->echoValue('N');
					$this->outputLine('File not found: ' . $fullFileName);
					continue;
				} catch(\Exception $e) {
					$this->echoValue('E');
					$this->outputLine('Getting the file throws an Exception: ' . $fullFileName);
					continue;
				}

				// add the migrated uid of the DAM record to the FAL record
				if ($fileObject instanceof File) {
					if ($fileObject->isMissing()) {
						$this->echoValue('M');
						$this->outputLine('FAL did not find any file resource for DAM record. DAM uid: ' . $damRecord['uid'] . ': "' . $fullFileName . '"');
						continue;
					}
					// mark file as migrated
					$this->databaseConnection->exec_UPDATEquery(
						'sys_file',
						'uid=' . (int)$fileObject->getUid(),
						array('_migrateddamuid' => (int)$damRecord['uid'])
					);
					// update Metadata
					$metadata = $fileObject->_getMetaData();
					$metadata['title'] = $damRecord['title'];
					$metadata['description'] = $damRecord['description'];
					$metadata['alternative'] = $damRecord['alt_text'];
					if (ExtensionManagementUtility::isLoaded('filemetadata')) {
						$metadata['creator'] = $damRecord['creator'];
						$metadata['keywords'] = $damRecord['keywords'];
						$metadata['caption'] = $damRecord['caption'];
						$metadata['language'] = $damRecord['language'];
						$metadata['pages'] = $damRecord['pages'];
						$metadata['publisher'] = $damRecord['publisher'];
						$metadata['location_country'] = $damRecord['loc_country'];
						$metadata['location_city'] = $damRecord['loc_city'];
					}

					$this->databaseConnection->exec_UPDATEquery(
						'sys_file_metadata',
						'uid=' . (int)$metadata['uid'],
						$metadata
					);
					$migratedFiles++;
					$this->echoValue();
				} else {
					$this->echoValue('N');
					$this->outputLine('FAL did not find any file resource for DAM record. DAM uid: ' . $damRecord['uid'] . ': "' . $fullFileName . '"');
				}
			}
			$this->echoValue(PHP_EOL);
		}

		// print a message
		if ($migratedFiles > 0) {
			$this->outputLine('Migration successful: Migrated ' . $migratedFiles . ' files.');
		} else {
			$this->outputLine('Migration not needed: All files have been migrated already.');
		}
	}

	/**
	 * migrate relations to dam records that dam_ttcontent
	 * and dam_uploads introduced
	 *
	 * it is highly recommended to update the ref index afterwards
	 */
	public function migrateRelationsCommand() {
		/** @var \TYPO3\CMS\DamFalmigration\Service\MigrateRelationService $migrateRelationService */
		$migrateRelationService = $this->objectManager->get('TYPO3\\CMS\\DamFalmigration\\Service\\MigrateRelationService');
		$migrateRelationService->start();
	}

	/**
	 * migrate all DAM categories to sys_category records,
	 *
	 * @param integer $initialParentUid Per default new categories will have 0 as parent category. With this setting you can define, that the migrated categories should be subcategories of another category UID
	 * @param integer $storeOnPid Store new sys_category records on this PID
	 * @return void
	 */
	public function migrateDamCategoriesCommand($initialParentUid = 0, $storeOnPid = 0) {
		/** @var \TYPO3\CMS\DamFalmigration\Service\MigrateCategoryService $migrateCategoryService */
		$migrateCategoryService = $this->objectManager->get('TYPO3\\CMS\\DamFalmigration\\Service\\MigrateCategoryService');
		$migrateCategoryService->start($initialParentUid, $storeOnPid);
	}

	/**
	 * migrate all DAM category relations to sys_category_record_mm,
	 *
	 * @return void
	 */
	public function migrateDamCategoryRelationsCommand() {
		/** @var \TYPO3\CMS\DamFalmigration\Service\MigrateCategoryRelationService $migrateCategoryRelationService */
		$migrateCategoryRelationService = $this->objectManager->get('TYPO3\\CMS\\DamFalmigration\\Service\\MigrateCategoryRelationService');
		$migrateCategoryRelationService->start();
	}

	/**
	 * migrate all DAM selections to sys_collection,
	 *
	 * @return void
	 */
	public function migrateDamSelectionsCommand() {
		/** @var \TYPO3\CMS\DamFalmigration\Service\MigrateSelectionService $migrateSelectionService */
		$migrateSelectionService = $this->objectManager->get('TYPO3\\CMS\\DamFalmigration\\Service\\MigrateSelectionService');
		$migrateSelectionService->start();
	}

	/**
	 * migrates the <media DAM_UID target title>Linktext</media>
	 * to <link file:29643 - download>My link to a file</link>
	 *
	 * @param \string $table the table to look for
	 * @param \string $field the DB field to look for
	 */
	public function migrateMediaTagsInRteCommand($table = 'tt_content', $field = 'bodytext') {
		$recordsToMigrate = $this->databaseConnection->exec_SELECTgetRows(
			'uid, ' . $field,
			$table,
			'deleted=0 AND ' . $field . ' LIKE "%<media%"'
		);

		if (is_array($recordsToMigrate) && $recordsToMigrate !== array()) {
			echo 'Found ' . count($recordsToMigrate) . ' ' . $table . ' records that have a "<media>" tag in the field ' . $field . PHP_EOL;
			foreach ($recordsToMigrate as $rec) {
				$originalContent = $rec[$field];
				$finalContent = $originalContent;
				$results = array();
				preg_match_all('/<media ([0-9]+)([^>]*)>(.*?)<\/media>/', $originalContent, $results, PREG_SET_ORDER);
				if (is_array($results) && $results !== array()) {
					foreach ($results as $result) {
						$searchString = $result[0];
						$damUid = $result[1];
						// see EXT:dam/mediatag/class.tx_dam_rtetransform_mediatag.php
						list($linkTarget, $linkClass, $linkTitle) = explode(' ', trim($result[2]), 3);
						$linkText = $result[3];
						echo 'Replacing "' . $result[0] . '" with DAM UID ' . $damUid . ' (target ' . $linkTarget . '; class ' . $linkClass . '; title "' . $linkTitle . '") and linktext "' . $linkText . '"' . PHP_EOL;
						// fetch the DAM uid from sys_file
						// and replace the full tag with a valid href="file:FALUID"
						// <link file:29643 - download>My link to a file</link>
						$falRecord = $this->databaseConnection->exec_SELECTgetSingleRow(
							'uid',
							'sys_file',
							'_migrateddamuid=' . (int)$damUid
						);
						if (is_array($falRecord)) {
							$replaceString = '<link file:' . $falRecord['uid'] . ' ' . $result[2] . '>' . $linkText . '</link>';
							$finalContent = str_replace($searchString, $replaceString, $finalContent);
						}
					}
					// update the record
					if ($finalContent !== $originalContent) {
						$this->databaseConnection->exec_UPDATEquery(
							$table,
							'uid=' . (int)$rec['uid'],
							array($field => $finalContent)
						);
					}
				} else {
					$this->outputLine('Nothing found: ' . $originalContent);
				}
			}
			$this->outputLine('DONE');
		} else {
			$this->outputLine('No records with <media>-tag found');
		}
	}

}
