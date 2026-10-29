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
 * \file    htdocs/custom/bddswap/swap.php
 * \ingroup bddswap
 * \brief   Endpoint to switch active Dolibarr database in conf.php
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */
'@phan-var-force Conf $conf';
'@phan-var-force DoliDB $db';
'@phan-var-force Translate $langs';
'@phan-var-force User $user';

require_once __DIR__.'/lib/bddswap.lib.php';

$langs->loadLangs(array("admin", "bddswap@bddswap"));

if (empty($user->admin)) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$targetDb = GETPOST('target_db', 'alphanohtml');
$backtopage = GETPOST('backtopage', ' sanitize');


/*
 * Actions
 */

if ($action === 'swapdb' && !empty($targetDb)) {
	$errorMsgKey = '';
	$result = bddswapSwitchDatabase($db, $targetDb, $errorMsgKey);
	if ($result > 0) {
		unset($_SESSION['dol_events']);
		setEventMessages($langs->trans("BddSwapSwitchedSuccess", $targetDb), null, 'mesgs');
	} else {
		setEventMessages($langs->trans($errorMsgKey, $targetDb), null, 'errors');
	}

	$redirectUrl = !empty($backtopage) ? $backtopage : dol_buildpath('/bddswap/admin/setup.php', 1);
	header("Location: ".$redirectUrl);
	exit;
}


/*
 * View
 */

header("Location: ".dol_buildpath('/bddswap/admin/setup.php', 1));
exit;
