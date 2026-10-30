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
 * \file    htdocs/custom/bddswap/lib/bddswap.lib.php
 * \ingroup bddswap
 * \brief   Library files with common functions for BddSwap
 */

/**
 * Prepare admin pages header
 *
 * @return array<int,array{0:string,1:string,2:string}> Array of tabs
 */
function bddswapAdminPrepareHead(): array
{
	global $langs, $conf;

	$langs->load("bddswap@bddswap");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/bddswap/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath("/bddswap/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'bddswap@bddswap');
	complete_head_from_modules($conf, $langs, null, $head, $h, 'bddswap@bddswap', 'remove');

	return $head;
}

/**
 * Return the list of preset Dolibarr database names that should always be proposed
 * in the setup page (with a button to create them when they do not exist yet).
 *
 * @return string[] List of preset database names
 */
function bddswapGetPresetDatabaseNames(): array
{
	return array(
		'dolibarr-21',
		'dolibarr-20',
		'dolibarr-19',
		'dolibarr-18',
	);
}

/**
 * Create an empty MySQL/MariaDB database with UTF-8 collation.
 *
 * @param	DoliDB	$db				Database handler
 * @param	string	$targetDbName	Database name to create
 * @param	string	$errorMsg		Output error message key if failed
 * @return	int						1 if OK, <0 if KO
 */
function bddswapCreateDatabase($db, string $targetDbName, string &$errorMsg = ''): int
{
	if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $targetDbName)) {
		$errorMsg = 'BddSwapErrorInvalidDatabase';
		return -1;
	}

	$sqlCreate = "CREATE DATABASE IF NOT EXISTS `".$db->escape($targetDbName)."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
	$resql = $db->query($sqlCreate);
	if (!$resql) {
		$errorMsg = 'BddSwapErrorCreateDatabaseFailed';
		return -2;
	}

	dol_syslog("BddSwap: created database ".$targetDbName, LOG_INFO);

	return 1;
}

/**
 * Return the list of available Dolibarr databases on the current MySQL server
 * that contain a table matching the current table prefix + 'const' (or exist in the preset list).
 *
 * @param	DoliDB	$db				Database handler
 * @param	bool	$includePresets	Include preset databases even if not created/initialized yet
 * @return	array<string,array{name:string,version:string,company:string,is_current:bool,exists:bool,has_tables:bool}>	Map of databases indexed by db name
 */
function bddswapGetDatabases($db, bool $includePresets = false)
{
	global $conf;

	$databases = array();
	$systemSchemas = array('information_schema', 'mysql', 'performance_schema', 'sys');
	$prefix = $db->prefix();
	$constTable = $prefix.'const';
	$presetNames = bddswapGetPresetDatabaseNames();

	$sqlShowDatabases = "SHOW DATABASES";
	$resql = $db->query($sqlShowDatabases);
	if (!$resql) {
		return $databases;
	}

	$dbNames = array();
	while ($row = $db->fetch_array($resql)) {
		$candidateName = (string) $row[0];
		if (in_array(dol_strtolower($candidateName), $systemSchemas, true)) {
			continue;
		}
		if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $candidateName)) {
			continue;
		}
		$dbNames[] = $candidateName;
	}

	foreach ($dbNames as $candidateName) {
		$sqlCheckConst = "SHOW TABLES FROM `".$db->escape($candidateName)."` LIKE '".$db->escape($constTable)."'";
		$resCheck = $db->query($sqlCheckConst);
		$hasConstTable = ($resCheck && $db->num_rows($resCheck) > 0);

		if (!$hasConstTable && !in_array($candidateName, $presetNames, true) && $candidateName !== $conf->db->name) {
			continue;
		}

		$version = '';
		$company = '';
		if ($hasConstTable) {
			$sqlConst = "SELECT name, value FROM `".$db->escape($candidateName)."`.`".$db->escape($constTable)."`";
			$sqlConst .= " WHERE name IN ('MAIN_VERSION_LAST_UPGRADE', 'MAIN_VERSION_LAST_INSTALL', 'MAIN_INFO_SOCIETE_NOM')";
			$resConst = $db->query($sqlConst);
			if ($resConst) {
				$lastInstall = '';
				$lastUpgrade = '';
				while ($obj = $db->fetch_object($resConst)) {
					if ($obj->name === 'MAIN_VERSION_LAST_UPGRADE' && !empty($obj->value)) {
						$lastUpgrade = (string) $obj->value;
					} elseif ($obj->name === 'MAIN_VERSION_LAST_INSTALL' && !empty($obj->value)) {
						$lastInstall = (string) $obj->value;
					} elseif ($obj->name === 'MAIN_INFO_SOCIETE_NOM' && empty($company) && !empty($obj->value)) {
						$company = (string) $obj->value;
					}
				}
				$version = !empty($lastUpgrade) ? $lastUpgrade : $lastInstall;
			}
		}

		$databases[$candidateName] = array(
			'name' => $candidateName,
			'version' => $version,
			'company' => $company,
			'is_current' => ($candidateName === $conf->db->name),
			'exists' => true,
			'has_tables' => $hasConstTable,
		);
	}

	if ($includePresets) {
		foreach ($presetNames as $presetName) {
			if (!isset($databases[$presetName])) {
				$databases[$presetName] = array(
					'name' => $presetName,
					'version' => '',
					'company' => '',
					'is_current' => ($presetName === $conf->db->name),
					'exists' => false,
					'has_tables' => false,
				);
			}
		}
	}

	return $databases;
}

/**
 * Copy BddSwap activation constants from the current database to the target database
 * so the top-bar selector remains active after switching.
 *
 * @param	DoliDB	$db				Database handler
 * @param	string	$targetDbName	Target database name
 * @return	int						1 if OK, <0 if KO
 */
function bddswapEnsureModuleEnabledOnTarget($db, $targetDbName)
{
	if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $targetDbName)) {
		return -1;
	}

	$constTable = $db->prefix().'const';
	$sqlCheckTable = "SHOW TABLES FROM `".$db->escape($targetDbName)."` LIKE '".$db->escape($constTable)."'";
	$resTable = $db->query($sqlCheckTable);
	if (!$resTable || $db->num_rows($resTable) === 0) {
		return 0;
	}

	$sqlSelect = "SELECT name, entity, value, type, visible, note FROM ".$constTable;
	$sqlSelect .= " WHERE name LIKE 'MAIN_MODULE_BDDSWAP%'";
	$resql = $db->query($sqlSelect);
	if (!$resql) {
		return -1;
	}

	while ($obj = $db->fetch_object($resql)) {
		$sqlCheck = "SELECT rowid FROM `".$db->escape($targetDbName)."`.`".$db->escape($constTable)."`";
		$sqlCheck .= " WHERE name = '".$db->escape($obj->name)."' AND entity = ".((int) $obj->entity);
		$resCheck = $db->query($sqlCheck);
		if ($resCheck && $db->num_rows($resCheck) === 0) {
			$sqlInsert = "INSERT INTO `".$db->escape($targetDbName)."`.`".$db->escape($constTable)."`";
			$sqlInsert .= " (name, entity, value, type, visible, note) VALUES (";
			$sqlInsert .= "'".$db->escape($obj->name)."', ";
			$sqlInsert .= ((int) $obj->entity).", ";
			$sqlInsert .= "'".$db->escape((string) $obj->value)."', ";
			$sqlInsert .= "'".$db->escape((string) $obj->type)."', ";
			$sqlInsert .= ((int) $obj->visible).", ";
			$sqlInsert .= "'".$db->escape((string) $obj->note)."')";
			$db->query($sqlInsert);
		} elseif ($resCheck && $db->num_rows($resCheck) > 0) {
			$sqlUpdate = "UPDATE `".$db->escape($targetDbName)."`.`".$db->escape($constTable)."`";
			$sqlUpdate .= " SET value = '".$db->escape((string) $obj->value)."'";
			$sqlUpdate .= " WHERE name = '".$db->escape($obj->name)."' AND entity = ".((int) $obj->entity);
			$db->query($sqlUpdate);
		}
	}

	return 1;
}

/**
 * Update conf.php to switch $dolibarr_main_db_name to the selected database.
 *
 * @param	DoliDB	$db				Database handler
 * @param	string	$targetDbName	Target database name
 * @param	string	$errorMsg		Output error message key if failed
 * @return	int						1 if OK, <0 if KO
 */
function bddswapSwitchDatabase($db, $targetDbName, &$errorMsg = '')
{
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

	$availableDbs = bddswapGetDatabases($db, true);
	if (empty($targetDbName) || !isset($availableDbs[$targetDbName]) || empty($availableDbs[$targetDbName]['exists'])) {
		$errorMsg = 'BddSwapErrorInvalidDatabase';
		return -1;
	}

	$confFile = DOL_DOCUMENT_ROOT.'/conf/conf.php';
	if (!dol_is_file($confFile)) {
		$errorMsg = 'BddSwapErrorConfNotWritable';
		return -2;
	}

	if (!is_writable($confFile)) {
		@chmod($confFile, 0664);
		if (!is_writable($confFile)) {
			$errorMsg = 'BddSwapErrorConfNotWritable';
			return -2;
		}
	}

	$content = file_get_contents($confFile);
	if ($content === false) {
		$errorMsg = 'BddSwapErrorConfNotWritable';
		return -2;
	}

	$lines = preg_split("/(\r\n|\n|\r)/", $content, -1, PREG_SPLIT_DELIM_CAPTURE);
	if (!is_array($lines)) {
		$errorMsg = 'BddSwapErrorConfWriteFailed';
		return -3;
	}

	// Check if target database already has a commented or active line in conf.php
	$hasTargetLine = false;
	foreach ($lines as $line) {
		if (preg_match('/^\s*(?:\/\/\s*)?\$dolibarr_main_db_name\s*=\s*[\'"]'.preg_quote($targetDbName, '/').'[\'"]\s*;/', $line)) {
			$hasTargetLine = true;
			break;
		}
	}

	$updated = false;
	$lastDbLineIndex = -1;
	$lastIndent = '';
	$countLines = count($lines);
	for ($i = 0; $i < $countLines; $i += 2) {
		$line = $lines[$i];
		if (preg_match('/^(\s*)(?:\/\/\s*)?(\$dolibarr_main_db_name\s*=\s*[\'"]([^\'"]+)[\'"]\s*;.*)$/', $line, $m)) {
			$indent = $m[1];
			$rest = $m[2];
			$dbInLine = $m[3];
			$lastDbLineIndex = $i;
			$lastIndent = $indent;
			if ($hasTargetLine && $dbInLine === $targetDbName) {
				$lines[$i] = $indent.$rest;
				$updated = true;
			} else {
				$lines[$i] = $indent.'//'.$rest;
			}
		}
	}

	if (!$hasTargetLine && $lastDbLineIndex >= 0) {
		$lineEnding = isset($lines[$lastDbLineIndex + 1]) ? $lines[$lastDbLineIndex + 1] : "\n";
		$lines[$lastDbLineIndex] .= $lineEnding.$lastIndent."\$dolibarr_main_db_name='".$targetDbName."';";
		$updated = true;
	}

	if (!$updated) {
		$errorMsg = 'BddSwapErrorConfWriteFailed';
		return -3;
	}

	$newContent = implode('', $lines);
	if (strpos($newContent, 'bddswap_install.inc.php') === false) {
		$newContent = rtrim($newContent)."\nif (defined('DOL_INC_FOR_VERSION_ERROR') && file_exists(__DIR__.'/../custom/bddswap/lib/bddswap_install.inc.php')) {\n\tinclude_once __DIR__.'/../custom/bddswap/lib/bddswap_install.inc.php';\n}\n";
	}
	if (file_put_contents($confFile, $newContent) === false) {
		$errorMsg = 'BddSwapErrorConfWriteFailed';
		return -3;
	}

	if (function_exists('opcache_invalidate')) {
		@opcache_invalidate($confFile, true);
	}

	if (getDolGlobalInt('BDDSWAP_AUTO_ENABLE_ON_TARGET', 1)) {
		bddswapEnsureModuleEnabledOnTarget($db, $targetDbName);
	}

	dol_syslog("BddSwap: switched database in conf.php to ".$targetDbName, LOG_INFO);

	return 1;
}

/**
 * Retrieve git repository information (branch, commit hash) for the current Dolibarr root
 * by reading .git/HEAD directly (and optionally git status via Utils::executeCLI when allowed).
 *
 * @param	DoliDB	$db	Database handler
 * @return	array{branch:string,commit:string,status:string,is_clean:bool}
 */
function bddswapGetGitStatus($db): array
{
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/utils.class.php';

	$info = array(
		'branch' => '',
		'commit' => '',
		'status' => '',
		'is_clean' => true,
	);

	$repoDir = dirname(DOL_DOCUMENT_ROOT);
	$gitDir = $repoDir.'/.git';
	if (!dol_is_dir($gitDir)) {
		return $info;
	}

	// Read current branch / HEAD commit directly from .git/HEAD
	$headFile = $gitDir.'/HEAD';
	if (dol_is_file($headFile)) {
		$headContent = trim((string) file_get_contents($headFile));
		if (preg_match('/^ref:\s*refs\/heads\/(.+)$/', $headContent, $m)) {
			$info['branch'] = trim($m[1]);
			$refFile = $gitDir.'/refs/heads/'.$info['branch'];
			if (dol_is_file($refFile)) {
				$fullHash = trim((string) file_get_contents($refFile));
				$info['commit'] = dol_substr($fullHash, 0, 7);
			} elseif (dol_is_file($gitDir.'/packed-refs')) {
				$packedRefs = (string) file_get_contents($gitDir.'/packed-refs');
				if (preg_match('/^([0-9a-f]{40})\s+refs\/heads\/'.preg_quote($info['branch'], '/').'\s*$/m', $packedRefs, $mp)) {
					$info['commit'] = dol_substr($mp[1], 0, 7);
				}
			}
		} else {
			$info['branch'] = 'HEAD';
			$info['commit'] = dol_substr($headContent, 0, 7);
		}
	}

	// Read last commit message from .git/logs/HEAD if available
	$headLogFile = $gitDir.'/logs/HEAD';
	if (dol_is_file($headLogFile)) {
		$lines = file($headLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if (!empty($lines)) {
			$lastLine = (string) end($lines);
			if (preg_match('/^([0-9a-f]{40})\s+([0-9a-f]{40})\s+.*?\t(.*)$/', $lastLine, $ml)) {
				if (empty($info['commit'])) {
					$info['commit'] = dol_substr($ml[2], 0, 7);
				}
				$info['commit'] .= ' ('.$ml[3].')';
			}
		}
	}

	// If git is allowed in $dolibarr_main_restrict_os_commands, enrich with commit details and working tree status
	global $dolibarr_main_restrict_os_commands;
	$allowedCmds = !empty($dolibarr_main_restrict_os_commands) ? array_map('trim', explode(',', (string) $dolibarr_main_restrict_os_commands)) : array();
	if (empty($allowedCmds) || in_array('git', $allowedCmds, true) || in_array('git.exe', $allowedCmds, true)) {
		$utils = new Utils($db);
		$gitCmdPrefix = bddswapGetGitCommandPrefix($repoDir);
		$resCommit = $utils->executeCLI($gitCmdPrefix.' log -1 --format="%h - %s (%ci)"', '', 0, null, 1);
		if ($resCommit['result'] === 0 && !empty($resCommit['output'])) {
			$info['commit'] = trim((string) $resCommit['output']);
		}
		$resStatus = $utils->executeCLI($gitCmdPrefix.' status --short', '', 0, null, 1);
		if ($resStatus['result'] === 0) {
			$statusOut = trim((string) $resStatus['output']);
			$info['status'] = $statusOut;
			$info['is_clean'] = ($statusOut === '');
		}
	}

	return $info;
}

/**
 * Return the git binary command prefix including `-c safe.directory=<repoDir> -C <repoDir>`
 * so git works reliably under Apache/WAMP service accounts on Windows as well as Linux.
 *
 * @param	string	$repoDir	Repository root directory
 * @return	string				Git command prefix
 */
function bddswapGetGitCommandPrefix(string $repoDir): string
{
	$gitBin = 'git';
	$windowsGitCandidates = array(
		'C:/Program Files/Git/cmd/git.exe',
		'C:/Program Files/Git/bin/git.exe',
		'C:/Program Files (x86)/Git/cmd/git.exe',
	);
	foreach ($windowsGitCandidates as $candidate) {
		if (dol_is_file($candidate)) {
			$gitBin = '"'.$candidate.'"';
			break;
		}
	}

	$normalizedRepo = str_replace('\\', '/', $repoDir);

	return $gitBin.' -c '.escapeshellarg('safe.directory='.$normalizedRepo).' -C '.escapeshellarg($normalizedRepo);
}

/**
 * Determine the target Dolibarr git branch corresponding to a database entry
 * (based on its Dolibarr version or database name).
 *
 * @param	string	$dbName		Database name (e.g. 'dolibarr22', 'dolibarr-19', 'fork-dolibarr-develop')
 * @param	string	$dbVersion	Dolibarr version recorded in DB (e.g. '22.0.4', '25.0.0-alpha')
 * @return	string				Target git branch name (e.g. '22.0', '19.0', 'develop') or '' if unknown
 */
function bddswapGetTargetGitBranch(string $dbName, string $dbVersion = ''): string
{
	if (stripos($dbName, 'develop') !== false || stripos($dbVersion, 'alpha') !== false) {
		return 'develop';
	}

	if (preg_match('/^(\d+)\./', $dbVersion, $mVer)) {
		$major = (int) $mVer[1];
		if ($major >= 25) {
			return 'develop';
		}
		return $major.'.0';
	}

	if (preg_match('/(\d{2})$/', $dbName, $mName)) {
		$major = (int) $mName[1];
		if ($major >= 25) {
			return 'develop';
		}
		return $major.'.0';
	}

	return '';
}

/**
 * Ensure `git` and `git.exe` are present in $dolibarr_main_restrict_os_commands in conf.php
 * so Utils::executeCLI() is allowed to run git commands.
 *
 * @return void
 */
function bddswapAllowGitInConf(): void
{
	global $dolibarr_main_restrict_os_commands;

	if (empty($dolibarr_main_restrict_os_commands)) {
		return;
	}

	$allowedCmds = array_map('trim', explode(',', (string) $dolibarr_main_restrict_os_commands));
	if (in_array('git', $allowedCmds, true) && in_array('git.exe', $allowedCmds, true)) {
		return;
	}

	$dolibarr_main_restrict_os_commands .= ', git, git.exe';

	$confFile = DOL_DOCUMENT_ROOT.'/conf/conf.php';
	if (dol_is_file($confFile) && is_writable($confFile)) {
		$content = file_get_contents($confFile);
		if ($content !== false) {
			$newContent = preg_replace(
				'/(\$dolibarr_main_restrict_os_commands\s*=\s*[\'"])([^\'"]*?)([\'"]\s*;)/',
				'$1$2, git, git.exe$3',
				$content,
				1
			);
			if ($newContent !== null && $newContent !== $content) {
				file_put_contents($confFile, $newContent);
			}
		}
	}
}

/**
 * Checkout the specified Dolibarr git branch on the root Dolibarr repository
 * using Dolibarr's Utils::executeCLI().
 *
 * @param	DoliDB	$db				Database handler
 * @param	string	$targetBranch	Git branch name (e.g. '22.0', '23.0', '24.0', 'develop')
 * @param	string	$errorDetail	Output error detail if checkout fails
 * @return	int						1 if OK, <0 if KO
 */
function bddswapCheckoutGitBranch($db, string $targetBranch, string &$errorDetail = ''): int
{
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/utils.class.php';

	if (!preg_match('/^[a-zA-Z0-9._\/\-]+$/', $targetBranch)) {
		$errorDetail = 'Invalid branch name';
		return -1;
	}

	$repoDir = dirname(DOL_DOCUMENT_ROOT);
	if (!dol_is_dir($repoDir.'/.git')) {
		$errorDetail = 'Not a git repository';
		return -2;
	}

	bddswapAllowGitInConf();

	$utils = new Utils($db);
	$gitCmdPrefix = bddswapGetGitCommandPrefix($repoDir);
	$escapedBranch = escapeshellarg($targetBranch);

	// 1. Check if local branch exists
	$resLocal = $utils->executeCLI($gitCmdPrefix.' show-ref --verify --quiet '.escapeshellarg('refs/heads/'.$targetBranch), '', 0, null, 1);
	if ($resLocal['result'] === 0) {
		$resCheckout = $utils->executeCLI($gitCmdPrefix.' checkout '.$escapedBranch, '', 0, null, 1);
		if ($resCheckout['result'] === 0) {
			dol_syslog("BddSwap: checked out local git branch ".$targetBranch, LOG_INFO);
			return 1;
		}
		$errorDetail = trim((string) $resCheckout['output']);
		return -3;
	}

	// 2. Otherwise check upstream/<branch> or origin/<branch>
	$lastVerifyOutput = '';
	foreach (array('upstream', 'origin') as $remote) {
		$resRemote = $utils->executeCLI($gitCmdPrefix.' show-ref --verify --quiet '.escapeshellarg('refs/remotes/'.$remote.'/'.$targetBranch), '', 0, null, 1);
		if ($resRemote['result'] === 0) {
			$resCheckout = $utils->executeCLI($gitCmdPrefix.' checkout -b '.$escapedBranch.' '.escapeshellarg($remote.'/'.$targetBranch), '', 0, null, 1);
			if ($resCheckout['result'] === 0) {
				dol_syslog("BddSwap: created and checked out git branch ".$targetBranch." from ".$remote."/".$targetBranch, LOG_INFO);
				return 1;
			}
			$errorDetail = trim((string) $resCheckout['output']);
			return -3;
		}
		if (!empty($resRemote['output'])) {
			$lastVerifyOutput = trim((string) $resRemote['output']);
		}
	}

	$errorDetail = !empty($lastVerifyOutput) ? $lastVerifyOutput : 'Branch '.$targetBranch.' not found locally or in upstream/origin';
	return -4;
}
