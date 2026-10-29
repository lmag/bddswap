<?php
/* Copyright (C) 2026 Laurent Magnin
 * Copyright (C) 2026 Eoxia <dev@eoxia.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
declare(strict_types=1);

/**
 * \file    htdocs/custom/bddswap/class/actions_bddswap.class.php
 * \ingroup bddswap
 * \brief   Hook actions for BddSwap module (top bar database selector)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';
require_once __DIR__.'/../lib/bddswap.lib.php';

/**
 * Class ActionsBddSwap
 */
class ActionsBddSwap extends CommonHookActions
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var string[] Errors
	 */
	public $errors = array();

	/**
	 * @var mixed[] Hook results
	 */
	public $results = array();

	/**
	 * @var ?string String displayed by executeHook()
	 */
	public $resprints;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Output the database selector in the top right menu bar for administrators
	 *
	 * @param	array<string,mixed>	$parameters		Hook parameters
	 * @param	?CommonObject		$object			Current object
	 * @param	?string				$action			Current action
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									0 on success
	 */
	public function printTopRightMenu($parameters, &$object, &$action, $hookmanager)
	{
		global $user, $langs;

		if (empty($user->admin)) {
			return 0;
		}

		$langs->load("bddswap@bddswap");
		$databases = bddswapGetDatabases($this->db);
		if (empty($databases)) {
			return 0;
		}

		$swapUrl = dol_buildpath('/bddswap/swap.php', 1);
		$setupUrl = dol_buildpath('/bddswap/admin/setup.php', 1);
		$currentUrl = $_SERVER["REQUEST_URI"] ?? '';

		$out = '<div class="login_block_elem bddswap-top-container">';
		$out .= '<form method="POST" action="'.dolPrintHTMLForAttributeUrl($swapUrl).'" class="bddswap-top-form">';
		$out .= '<input type="hidden" name="token" value="'.dolPrintHTMLForAttribute(newToken()).'">';
		$out .= '<input type="hidden" name="action" value="swapdb">';
		$out .= '<input type="hidden" name="backtopage" value="'.dolPrintHTMLForAttribute($currentUrl).'">';
		$out .= '<a href="'.dolPrintHTMLForAttributeUrl($setupUrl).'" class="bddswap-setup-link" title="'.dolPrintHTMLForAttribute($langs->trans("BddSwapSetup")).'">';
		$out .= img_picto($langs->trans("BddSwapSetup"), 'database', 'class="atoplogin valignmiddle"');
		$out .= '</a>';
		$out .= '<select name="target_db" class="flat bddswap-select" title="'.dolPrintHTMLForAttribute($langs->trans("BddSwapSwitch")).'">';
		$currentDbVersion = '';
		foreach ($databases as $dbInfo) {
			if (!empty($dbInfo['is_current'])) {
				$currentDbVersion = $dbInfo['version'];
			}
			$label = $dbInfo['name'].(!empty($dbInfo['version']) ? ' ('.$dbInfo['version'].')' : '');
			$selected = !empty($dbInfo['is_current']) ? ' selected="selected"' : '';
			$out .= '<option value="'.dolPrintHTMLForAttribute($dbInfo['name']).'"'.$selected.'>'.dolPrintLabel($label).'</option>';
		}
		$out .= '</select>';
		if (preg_match('/^(\d+)/', $currentDbVersion, $mDb) && preg_match('/^(\d+)/', constant('DOL_VERSION'), $mProg) && $mDb[1] !== $mProg[1]) {
			$warnTitle = $langs->transnoentities("BddSwapVersionMismatchTooltip", $mDb[1], $currentDbVersion, $mProg[1], constant('DOL_VERSION'));
			$warnBadge = $langs->transnoentities("BddSwapVersionMismatchBadge", $mDb[1], $mProg[1]);
			$out .= ' <span class="badge badge-warning" title="'.dolPrintHTMLForAttribute($warnTitle).'" style="cursor: help;">&#9888; '.dolPrintLabel($warnBadge).'</span>';
		}
		$out .= '</form>';
		$out .= '</div>';

		$this->resprints = $out;

		return 0;
	}
}
