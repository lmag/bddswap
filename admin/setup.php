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
 * \file    htdocs/custom/bddswap/admin/setup.php
 * \ingroup bddswap
 * \brief   BddSwap setup and database switcher page
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
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
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

require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once __DIR__.'/../lib/bddswap.lib.php';

$langs->loadLangs(array("admin", "companies", "bddswap@bddswap"));

if (empty($user->admin)) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$targetDb = GETPOST('target_db', 'alphanohtml');
$targetBranch = GETPOST('target_branch', 'alphanohtml');
$backtopage = GETPOST('backtopage', 'alpha');


/*
 * Actions
 */

if ($action === 'swapdb' && !empty($targetDb)) {
	$errorMsgKey = '';
	$result = bddswapSwitchDatabase($db, $targetDb, $errorMsgKey);
	if ($result > 0) {
		unset($_SESSION['dol_events']);
		setEventMessages($langs->trans("BddSwapSwitchedSuccess", $targetDb), null, 'mesgs');
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	} else {
		setEventMessages($langs->trans($errorMsgKey, $targetDb), null, 'errors');
	}
} elseif ($action === 'createdb' && !empty($targetDb)) {
	$errorMsgKey = '';
	$result = bddswapCreateDatabase($db, $targetDb, $errorMsgKey);
	if ($result > 0) {
		setEventMessages($langs->trans("BddSwapCreatedSuccess", $targetDb), null, 'mesgs');
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	} else {
		setEventMessages($langs->trans($errorMsgKey, $targetDb), null, 'errors');
	}
} elseif ($action === 'gitcheckout' && !empty($targetBranch)) {
	$errorDetail = '';
	$result = bddswapCheckoutGitBranch($db, $targetBranch, $errorDetail);
	if ($result > 0) {
		setEventMessages($langs->trans("BddSwapGitCheckoutSuccess", $targetBranch), null, 'mesgs');
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	} else {
		setEventMessages($langs->trans("BddSwapErrorGitCheckoutFailed", $targetBranch, $errorDetail), null, 'errors');
	}
}


/*
 * View
 */

$page_name = "BddSwapSetup";
llxHeader('', $langs->trans($page_name));

$linkback = '<a href="'.dolPrintHTMLForAttributeUrl($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

$head = bddswapAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($page_name), -1, 'fa-database');

$databases = bddswapGetDatabases($db, true);
$gitInfo = bddswapGetGitStatus($db);

print '<div class="opacitymedium marginbottomonly">'.dolPrintLabel($langs->trans("BddSwapSetupPage")).'</div>';

// Quick selector form
print '<form method="POST" action="'.dolPrintHTMLForAttributeUrl($_SERVER["PHP_SELF"]).'">';
print '<input type="hidden" name="token" value="'.dolPrintHTMLForAttribute(newToken()).'">';
print '<input type="hidden" name="action" value="swapdb">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.dolPrintLabel($langs->trans("Parameter")).'</td>';
print '<td>'.dolPrintLabel($langs->trans("Value")).'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.dolPrintLabel($langs->trans("BddSwapCurrentDatabase")).'</td>';
print '<td><strong>'.dolPrintLabel($conf->db->name).'</strong></td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.dolPrintLabel($langs->trans("BddSwapCurrentSourceVersionAndGit")).'</td>';
print '<td>';
print '<span class="badge badge-info"><strong>'.dolPrintLabel(DOL_VERSION).'</strong></span>';
if (!empty($gitInfo['branch'])) {
	print ' &mdash; <strong>'.dolPrintLabel($langs->trans("BddSwapGitBranch")).' :</strong> <code>'.dolPrintLabel($gitInfo['branch']).'</code>';
}
if (!empty($gitInfo['commit'])) {
	print ' &mdash; <strong>'.dolPrintLabel($langs->trans("BddSwapGitCommit")).' :</strong> <span class="opacitymedium">'.dolPrintLabel($gitInfo['commit']).'</span>';
}
if (!empty($gitInfo['branch'])) {
	if ($gitInfo['is_clean']) {
		print ' <span class="badge badge-status4 badge-status">'.dolPrintLabel($langs->trans("BddSwapGitStatusClean")).'</span>';
	} else {
		print ' <span class="badge badge-status1 badge-status" title="'.dolPrintHTMLForAttribute($gitInfo['status']).'">'.dolPrintLabel($langs->trans("BddSwapGitStatusModified")).'</span>';
	}
}
print '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.dolPrintLabel($langs->trans("BddSwapTargetDatabase")).'</td>';
print '<td>';
print '<select name="target_db" class="flat minwidth200">';
foreach ($databases as $dbInfo) {
	if (empty($dbInfo['exists'])) {
		continue;
	}
	$label = $dbInfo['name'].(!empty($dbInfo['version']) ? ' ('.$dbInfo['version'].')' : '');
	$selected = !empty($dbInfo['is_current']) ? ' selected="selected"' : '';
	print '<option value="'.dolPrintHTMLForAttribute($dbInfo['name']).'"'.$selected.'>'.dolPrintLabel($label).'</option>';
}
print '</select> ';
print '<input type="submit" class="button button-save" value="'.dolPrintHTMLForAttribute($langs->trans("BddSwapSwitch")).'">';
print '</td>';
print '</tr>';
print '</table>';
print '</form>';

print '<br>';

// Table of available Dolibarr databases
$progMajor = '';
if (preg_match('/^(\d+)/', constant('DOL_VERSION'), $mProg)) {
	$progMajor = $mProg[1];
}

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.dolPrintLabel($langs->trans("BddSwapDbName")).'</td>';
print '<td>'.dolPrintLabel($langs->trans("BddSwapDbVersion")).'</td>';
print '<td>'.dolPrintLabel($langs->trans("BddSwapFilesVersion")).'</td>';
print '<td>'.dolPrintLabel($langs->trans("BddSwapMasterEntityName")).'</td>';
print '<td class="center">'.dolPrintLabel($langs->trans("Status")).'</td>';
print '<td class="right">'.dolPrintLabel($langs->trans("Action")).'</td>';
print '</tr>';

foreach ($databases as $dbInfo) {
	$dbExists = !empty($dbInfo['exists']);
	$hasTables = !empty($dbInfo['has_tables']);
	$dbMajor = '';
	if (preg_match('/^(\d+)/', $dbInfo['version'], $mDb)) {
		$dbMajor = $mDb[1];
	}
	$isVersionMatch = ($dbMajor !== '' && $progMajor !== '' && $dbMajor === $progMajor);

	print '<tr class="oddeven">';
	print '<td>'.img_picto('', 'fa-database', 'class="pictofixedwidth"').'<strong>'.dolPrintLabel($dbInfo['name']).'</strong></td>';
	print '<td>';
	if (!$dbExists) {
		print '<span class="opacitymedium">-</span>';
	} elseif (!$hasTables) {
		print '<span class="opacitymedium">'.dolPrintLabel($langs->trans("BddSwapEmptyDatabase")).'</span>';
	} else {
		print dolPrintLabel($dbInfo['version']);
	}
	print '</td>';
	print '<td>';
	if (!$dbExists) {
		print '<span class="opacitymedium">-</span>';
	} elseif ($isVersionMatch) {
		print '<span class="badge badge-status4">'.dolPrintLabel(DOL_VERSION).'</span>';
	} else {
		print '<span class="badge badge-warning" title="'.dolPrintHTMLForAttribute($langs->transnoentities("BddSwapVersionMismatchTooltip", $dbMajor ?: '?', $dbInfo['version'] ?: '-', $progMajor, DOL_VERSION)).'">&#9888; '.dolPrintLabel(DOL_VERSION).'</span>';
	}
	print '</td>';
	print '<td>'.($dbInfo['company'] !== '' ? dolPrintLabel($dbInfo['company']) : '<span class="opacitymedium">-</span>').'</td>';
	print '<td class="center">';
	if (!empty($dbInfo['is_current'])) {
		print '<span class="badge badge-status4 badge-status">'.dolPrintLabel($langs->trans("BddSwapActive")).'</span>';
	} elseif (!$dbExists) {
		print '<span class="badge badge-status0 badge-status">'.dolPrintLabel($langs->trans("BddSwapNotCreated")).'</span>';
	} else {
		print '-';
	}
	print '</td>';
	print '<td class="right nowraponall">';
	$correspondingBranch = bddswapGetTargetGitBranch($dbInfo['name'], $dbInfo['version']);
	if ($correspondingBranch !== '' && $correspondingBranch !== $gitInfo['branch']) {
		print '<form method="POST" action="'.dolPrintHTMLForAttributeUrl($_SERVER["PHP_SELF"]).'" class="inline-block marginrightonly">';
		print '<input type="hidden" name="token" value="'.dolPrintHTMLForAttribute(newToken()).'">';
		print '<input type="hidden" name="action" value="gitcheckout">';
		print '<input type="hidden" name="target_branch" value="'.dolPrintHTMLForAttribute($correspondingBranch).'">';
		print '<input type="submit" class="button smallpaddingimp" value="'.dolPrintHTMLForAttribute($langs->trans("BddSwapGitCheckoutBranch", $correspondingBranch)).'">';
		print '</form>';
	}
	if (!$dbExists) {
		print '<form method="POST" action="'.dolPrintHTMLForAttributeUrl($_SERVER["PHP_SELF"]).'" class="inline-block">';
		print '<input type="hidden" name="token" value="'.dolPrintHTMLForAttribute(newToken()).'">';
		print '<input type="hidden" name="action" value="createdb">';
		print '<input type="hidden" name="target_db" value="'.dolPrintHTMLForAttribute($dbInfo['name']).'">';
		print '<input type="submit" class="button smallpaddingimp" value="'.dolPrintHTMLForAttribute($langs->trans("BddSwapCreateDatabase")).'">';
		print '</form>';
	} elseif (empty($dbInfo['is_current'])) {
		print '<form method="POST" action="'.dolPrintHTMLForAttributeUrl($_SERVER["PHP_SELF"]).'" class="inline-block">';
		print '<input type="hidden" name="token" value="'.dolPrintHTMLForAttribute(newToken()).'">';
		print '<input type="hidden" name="action" value="swapdb">';
		print '<input type="hidden" name="target_db" value="'.dolPrintHTMLForAttribute($dbInfo['name']).'">';
		print '<input type="submit" class="button smallpaddingimp" value="'.dolPrintHTMLForAttribute($langs->trans("BddSwapSwitch")).'">';
		print '</form>';
	}
	print '</td>';
	print '</tr>';
}

print '</table>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
