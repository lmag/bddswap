<?php
/* Copyright (C) 2026 Laurent Magnin
 * Copyright (C) 2026 Eoxia <dev@eoxia.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */
declare(strict_types=1);

/**
 * \file    htdocs/custom/bddswap/lib/bddswap_install.inc.php
 * \ingroup bddswap
 * \brief   Injects the BddSwap selector and version mismatch warning on Dolibarr /install/ pages
 */

if (!defined('DOL_INC_FOR_VERSION_ERROR') || php_sapi_name() === 'cli') {
	return;
}

require_once __DIR__.'/bddswap.lib.php';

/**
 * Connect to the currently configured database from install context
 *
 * @return ?DoliDB Database instance or null on failure
 */
function bddswapGetInstallDb()
{
	global $conf;
	global $dolibarr_main_db_type, $dolibarr_main_db_host, $dolibarr_main_db_user;
	global $dolibarr_main_db_pass, $dolibarr_main_db_name, $dolibarr_main_db_port;
	global $dolibarr_main_db_prefix, $dolibarr_main_db_character_set, $dolibarr_main_db_collation;
	global $dolibarr_main_document_root;

	$dbType = !empty($dolibarr_main_db_type) ? $dolibarr_main_db_type : 'mysqli';
	$docRoot = !empty($dolibarr_main_document_root) ? $dolibarr_main_document_root : dirname(__DIR__, 3);
	require_once $docRoot.'/core/lib/functions.lib.php';

	if (!isset($conf) || !is_object($conf)) {
		$conf = new stdClass();
	}
	if (!isset($conf->db) || !is_object($conf->db)) {
		$conf->db = new stdClass();
	}
	if (!isset($conf->file) || !is_object($conf->file)) {
		$conf->file = new stdClass();
	}
	$conf->db->type = $dbType;
	$conf->db->name = (string) $dolibarr_main_db_name;
	$conf->db->prefix = !empty($dolibarr_main_db_prefix) ? (string) $dolibarr_main_db_prefix : 'llx_';
	$conf->db->character_set = !empty($dolibarr_main_db_character_set) ? (string) $dolibarr_main_db_character_set : 'utf8';
	$conf->db->dolibarr_main_db_collation = !empty($dolibarr_main_db_collation) ? (string) $dolibarr_main_db_collation : 'utf8_unicode_ci';
	if (empty($conf->file->character_set_client)) {
		$conf->file->character_set_client = 'UTF-8';
	}
	if (!defined('MAIN_DB_PREFIX')) {
		define('MAIN_DB_PREFIX', $conf->db->prefix);
	}
	if (!defined('DOL_DOCUMENT_ROOT')) {
		define('DOL_DOCUMENT_ROOT', $docRoot);
	}

	$db = getDoliDBInstance(
		$dbType,
		(string) $dolibarr_main_db_host,
		(string) $dolibarr_main_db_user,
		(string) $dolibarr_main_db_pass,
		(string) $dolibarr_main_db_name,
		(int) $dolibarr_main_db_port
	);
	if (!is_object($db) || !$db->connected) {
		return null;
	}
	return $db;
}

// Handle database switch submitted directly from an /install/ page
if (!empty($_POST['bddswap_install_action']) && $_POST['bddswap_install_action'] === 'swapdb' && !empty($_POST['target_db'])) {
	$targetDb = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $_POST['target_db']);
	$db = bddswapGetInstallDb();
	if ($db !== null && !empty($targetDb)) {
		global $conf, $dolibarr_main_db_name, $dolibarr_main_db_prefix;
		if (!isset($conf) || !is_object($conf)) {
			$conf = new stdClass();
		}
		if (!isset($conf->db) || !is_object($conf->db)) {
			$conf->db = new stdClass();
		}
		$conf->db->name = $dolibarr_main_db_name;
		$conf->db->prefix = !empty($dolibarr_main_db_prefix) ? $dolibarr_main_db_prefix : 'llx_';

		$err = '';
		if (bddswapSwitchDatabase($db, $targetDb, $err) > 0) {
			if (session_status() !== PHP_SESSION_ACTIVE) {
				foreach ($_COOKIE as $cookieName => $cookieValue) {
					if (strpos((string) $cookieName, 'DOLSESSID_') === 0) {
						@session_name((string) $cookieName);
						@session_start();
						break;
					}
				}
			}
			if (session_status() === PHP_SESSION_ACTIVE) {
				unset($_SESSION['dol_loginmesg'], $_SESSION['dol_events']);
			}
			header("Location: ../index.php");
			exit;
		}
	}
}

/**
 * Output buffer callback that injects the database selector and warning banner
 * right below the Dolibarr logo and version in /install/ pages.
 *
 * @param  string $buffer HTML output buffer
 * @return string         Modified HTML output buffer
 */
function bddswapInstallBufferCallback($buffer)
{
	if (strpos($buffer, 'divlogoinstall') === false) {
		return $buffer;
	}

	global $conf, $dolibarr_main_db_name, $dolibarr_main_db_prefix;
	$db = bddswapGetInstallDb();
	if ($db === null) {
		return $buffer;
	}

	if (!isset($conf) || !is_object($conf)) {
		$conf = new stdClass();
	}
	if (!isset($conf->db) || !is_object($conf->db)) {
		$conf->db = new stdClass();
	}
	$conf->db->name = $dolibarr_main_db_name;
	$conf->db->prefix = !empty($dolibarr_main_db_prefix) ? $dolibarr_main_db_prefix : 'llx_';

	$databases = bddswapGetDatabases($db);
	if (empty($databases)) {
		return $buffer;
	}

	$currentDbVersion = isset($databases[$dolibarr_main_db_name]['version']) ? $databases[$dolibarr_main_db_name]['version'] : '';
	$dbMajor = '';
	if (preg_match('/^(\d+)/', $currentDbVersion, $mDb)) {
		$dbMajor = $mDb[1];
	}
	$progMajor = '';
	if (defined('DOL_VERSION') && preg_match('/^(\d+)/', constant('DOL_VERSION'), $mProg)) {
		$progMajor = $mProg[1];
	}

	$injectHtml = '<div class="bddswap-install-box" style="margin: 12px auto 4px auto; max-width: 680px; text-align: center; font-family: sans-serif;">';
	$injectHtml .= '<form method="POST" action="" style="display: inline-flex; align-items: center; gap: 8px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 12px;">';
	$injectHtml .= '<input type="hidden" name="bddswap_install_action" value="swapdb">';
	$injectHtml .= '<span class="fa fa-database" style="color: #334155;"></span>';
	$injectHtml .= '<label for="bddswap_install_select" style="font-size: 13px; font-weight: 600; color: #334155;">Base de données :</label>';
	$injectHtml .= '<select id="bddswap_install_select" name="target_db" onchange="this.form.submit()" style="padding: 4px 8px; border: 1px solid #94a3b8; border-radius: 4px; font-size: 13px; cursor: pointer;">';
	foreach ($databases as $dbInfo) {
		$label = $dbInfo['name'].(!empty($dbInfo['version']) ? ' ('.$dbInfo['version'].')' : '');
		$selected = !empty($dbInfo['is_current']) ? ' selected="selected"' : '';
		$injectHtml .= '<option value="'.htmlspecialchars($dbInfo['name'], ENT_QUOTES).'"'.$selected.'>'.htmlspecialchars($label, ENT_QUOTES).'</option>';
	}
	$injectHtml .= '</select>';
	$injectHtml .= '</form>';

	if ($dbMajor !== '' && $progMajor !== '' && $dbMajor !== $progMajor) {
		$injectHtml .= '<div style="margin-top: 10px; padding: 10px 14px; background-color: #fef2f2; border: 1px solid #ef4444; border-left: 5px solid #dc2626; border-radius: 6px; color: #991b1b; font-size: 13.5px; line-height: 1.5; text-align: center;">';
		$injectHtml .= '<strong>&#9888; Attention : vous avez une BDD en '.htmlspecialchars($dbMajor, ENT_QUOTES).' (<code>'.htmlspecialchars((string) $dolibarr_main_db_name, ENT_QUOTES).'</code> - '.htmlspecialchars($currentDbVersion, ENT_QUOTES).') pour des scripts en '.htmlspecialchars($progMajor, ENT_QUOTES).' ('.htmlspecialchars(constant('DOL_VERSION'), ENT_QUOTES).') !</strong><br>';
		$injectHtml .= 'Si vous faites la mise à jour, vous perdrez la BDD en '.htmlspecialchars($dbMajor, ENT_QUOTES).' !';
		$injectHtml .= '</div>';
	}

	$injectHtml .= '</div>';

	$pattern = '/(<div class="divlogoinstall"[^>]*>.*?<\/div>)/s';
	if (preg_match($pattern, $buffer)) {
		$buffer = (string) preg_replace($pattern, '$1'.$injectHtml, $buffer, 1);
	}

	return $buffer;
}

ob_start('bddswapInstallBufferCallback');
