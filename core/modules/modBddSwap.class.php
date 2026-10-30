<?php
/* Copyright (C) 2026 Laurent Magnin
 * Copyright (C) 2026 Eoxia <dev@eoxia.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */
declare(strict_types=1);

/**
 * \defgroup   bddswap     Module BddSwap
 * \brief      BddSwap module descriptor.
 *
 * \file       htdocs/custom/bddswap/core/modules/modBddSwap.class.php
 * \ingroup    bddswap
 * \brief      Description and activation file for module BddSwap
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';


/**
 * Description and activation class for module BddSwap
 */
class modBddSwap extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;
		$this->numero = 436390;
		$this->rights_class = 'bddswap';
		$this->family = 'Eoxia';
		$this->module_position = '90';
		$this->familyinfo = array(
			'Eoxia' => array(
				'position' => '01',
				'label' => 'Eoxia',
			),
		);
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'ModuleBddSwapDesc';
		$this->descriptionlong = 'ModuleBddSwapDescLong';
		$this->editor_name = 'Eoxia';
		$this->editor_url = 'https://eoxia.com';
		$this->version = '1.0.2';
		$this->const_name = 'MAIN_MODULE_'.dol_strtoupper($this->name);
		$this->picto = 'database';

		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'printing' => 0,
			'theme' => 0,
			'css' => array(
				'/bddswap/css/bddswap.css.php',
			),
			'js' => array(
				'/bddswap/js/bddswap.js',
			),
			'hooks' => array(
				'toprightmenu',
			),
			'moduleforexternal' => 0,
		);

		$this->dirs = array();
		$this->config_page_url = array("setup.php@bddswap");
		$this->hidden = false;
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array("bddswap@bddswap");
		$this->phpmin = array(7, 2);
		$this->need_dolibarr_version = array(19, -3);

		$this->const = array(
			1 => array('BDDSWAP_AUTO_ENABLE_ON_TARGET', 'chaine', '1', 'Automatically keep BddSwap enabled on target database', 1, 'allentities', 0),
		);

		if (!isModEnabled("bddswap")) {
			$conf->bddswap = new stdClass();
			$conf->bddswap->enabled = 0;
		}

		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();
		$this->rights = array();
		$this->menu = array();
	}

	/**
	 * Function called when module is enabled.
	 *
	 * @param  string    $options Options when enabling module ('', 'noboxes')
	 * @return int<-1,1>          1 if OK, <=0 if KO
	 */
	public function init($options = '')
	{
		$sql = array();
		return $this->_init($sql, $options);
	}

	/**
	 * Function called when module is disabled.
	 *
	 * @param  string    $options Options when enabling module ('', 'noboxes')
	 * @return int<-1,1>          1 if OK, <=0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
