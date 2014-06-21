<?php

if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

if (TYPO3_MODE == 'BE') {
	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = 'B13\\DamFalmigration\\Command\\DamMigrationCommandController';
}

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['GLOBAL']['extTablesInclusion-PostProcessing'][] = 'EXT:dam_falmigration/Classes/Hooks/TcaCategory.php:Tx_DamFalmigration_Hooks_TcaCategory';
