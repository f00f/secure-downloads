<?php
/*
Plugin Name: Secure Downloads
Plugin URI: http://www.tauchclub-bamberg.de/
Description: Provides secure file downloads for members.
Version: 0.1
Author: f00f
Author URI: http://uwr1.de/
License: MIT
*/

require_once 'config.php';
$sd_error_msg = '';

// Check if path is sane.
// TODO: see if WP provides this functionality
function sd_is_path_sane($path) {
	global $sd_error_msg;

	if (!$path) {
		$sd_error_msg = 'E:empty';
		return $sd_error_msg;
	}
	if (false !== strpos('..', $path)) {
		$sd_error_msg = 'E:.. forbidden';
		return $sd_error_msg;
	}
	if (!file_exists(SD_DL_ROOT . $path)) {
		$sd_error_msg = 'E:non-exist';
		return $sd_error_msg;
	}
	return true;
}

// Get 'path' argument from shortcode attributes.
function sd_sc_get_path($atts) {
	extract(shortcode_atts(array(
		'path' => '',
	), $atts));
	$sane = sd_is_path_sane($path);
	if (true === $sane) {
		return $path;
	}
	return false;
}

// Read permissions file from directory.
// Param: dir or file path.
// Return: Min. user level for access to the given directory.
function sd_get_permissions($path) {
	global $sd_error_msg;

	$sd_min_level = 100;

	// get dirname of files
	if (!is_dir($path)) {
		$path = trailingslashit(dirname($path));
		if (!is_dir($path)) {
			$sd_error_msg = 'E:non-exist';
			return $sd_min_level;
		}
	}

	$perm_file = $path . '.permissions.php';
	if (!file_exists($perm_file)) {
		$sd_error_msg = 'E:non-exist';
		return $sd_min_level; // TODO: SD_DEFAULT_PERMISSIONS;
	}

	// read permissions file
	include($perm_file);
	
	return $sd_min_level;
}

function sd_check_permissions($path) {
	global $sd_error_msg;
	global $current_user;

	$perm = sd_get_permissions($path);
	if (!is_user_logged_in()) {
		$login_form = wp_login_form( array('echo' => false) );
		$register_link = wp_register('', '', false);
		$sd_error_msg = 'Bitte melde Dich an, um diese Inhalte anzuzeigen. '
						. $login_form
						. ($register_link
								? 'Noch keinen Zugang? Klick hier: ' . $register_link
								: '') . '<br />';
		return false;
	}

	if ($current_user->user_level >= $perm) {
		return true;
	}
	
	$sd_error_msg = 'Du hast nicht die nötigen Rechte um das hier zu sehen.<br />';
	return false;
}

// List contents of a given folder.
// Usage [sd_list_dir path="<relpath>"]
function sd_sc_list_dir($atts) {
	global $sd_error_msg;

	$dir = sd_sc_get_path($atts);
	if (!$dir) { return 'Fehler: Ungültiges Verzeichnis.'; }

	// ensure that $dir starts and ends with a slash.
	$dir = trailingslashit( $dir );
	if ('/' != $dir{0}) { $dir = '/' . $dir; }

	$absdir = untrailingslashit(SD_DL_ROOT) . $dir;
	if (!is_dir($absdir)) { return 'Fehler: Kein Verzeichnis.'.$absdir; };
	if (!is_readable($absdir)) { return 'Fehler: Unlesbares Verzeichnis.'; };

	$access = sd_check_permissions($absdir);
	if (!$access) { return $sd_error_msg; }
	
	$list = '';
	$urlpath = untrailingslashit(SD_DL_BASEURL) . $dir;
	$baseurl = home_url() . (('/' == $urlpath{0}) ? '' : '/') . $urlpath;
	$d = dir($absdir);
	while (false !== ($entry = $d->read())) {
		if ('.' == $entry{0}) { continue; }
		$link = $baseurl . $entry;
		$list .= '<a href="' . $link . '">' . $entry . '</a> ('.$link.')<br />';
	}
	$d->close();

	return $list;
}

// Display contents of a given file.
// Usage [sd_show_file path="<relpath>"]
function sd_sc_show_file($atts) {
	global $sd_error_msg;

	$file = sd_sc_get_path($atts);
	if (!$file) { return 'Fehler: Ungültige Datei.'; }

	// ensure that $file starts with a slash.
	if ('/' != $file{0}) { $file = '/' . $file; }

	$absfile = untrailingslashit(SD_DL_ROOT) . $file;
	if (!is_file($absfile)) { return 'Fehler: Keine Datei.'; };
	if (!is_readable($absfile)) { return 'Fehler: Unlesbare Datei.'; };

	$access = sd_check_permissions($absfile);
	if (!$access) { return $sd_error_msg; }

	ob_start();
	readfile($absfile);
	$content = ob_get_clean();

	return $content;
}

add_shortcode('sd_list_dir', 'sd_sc_list_dir');
add_shortcode('sd_show_file', 'sd_sc_show_file');
?>