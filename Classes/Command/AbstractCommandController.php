<?php
namespace B13\DamFalmigration\Command;

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
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Command Controller to execute DAM to FAL Migration scripts
 *
 * @package B13\DamFalmigration
 * @subpackage Controller
 */
class AbstractCommandController extends \TYPO3\CMS\Extbase\Mvc\Controller\CommandController {

	/**
	 * @var int
	 */
	protected $outputCounter = 0;

	/**
	 * echo "whatEver"
	 *
	 * @param string $value
	 * @param boolean $reset
	 * @return void
	 */
	protected function echoValue($value = '.', $reset = FALSE) {
		if ($reset) $this->outputCounter = 0;
		if ($this->outputCounter < 40) {
			echo $value;
			$this->outputCounter++;
		} else {
			echo PHP_EOL . $value;
			$this->outputCounter = 1;
		}
	}

}