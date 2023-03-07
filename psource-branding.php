<?php
/*
Plugin Name: ClassicPress Toolkit
Plugin URI: https://n3rds.work/cp_psource/classicpress-toolkit/
Donate link: https://n3rds.work/spendenaktionen/unterstuetze-unsere-psource-free-werke/
Description: Eine komplette White-Label- und Branding-Lösung für Multisite. Adminbar, Loginsreens, Wartungsmodus, Favicons, Entfernen von ClassicPress-Links und Branding und vielem mehr.
Author: WMS N@W
Version: 2.2.9
Author URI: https://n3rds.work/
Text Domain: ub
Domain Path: /languages

Copyright 2020-2023 WMS N@W (https://n3rds.work)


This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.
*/
require 'psource/psource-plugin-update/psource-plugin-updater.php';
use Psource\PluginUpdateChecker\v5\PucFactory;
$MyUpdateChecker = PucFactory::buildUpdateChecker(
	'https://n3rds.work//wp-update-server/?action=get_metadata&slug=psource-branding', 
	__FILE__, 
	'psource-branding' 
);
/**
 * PSOURCE CP Toolkit Version
 */

$ub_version = null;

require_once 'build.php';

// Include the configuration library.
require_once dirname( __FILE__ ) . '/etc/config.php';
// Include the functions library.
if ( file_exists( 'inc/deprecated-functions.php' ) ) {
	require_once 'inc/deprecated-functions.php';
}
require_once 'inc/functions.php';
require_once 'inc/class-pstoolkit-helper.php';

// Set up my location.
set_pstoolkit( __FILE__ );

/**
 * Set ub Version.
 */
function pstoolkit_set_ub_version() {
	global $ub_version;
	$data       = get_plugin_data( __FILE__, false, false );
	$ub_version = $data['Version'];
}

if ( ! defined( 'PSTOOLKIT_SUI_VERSION' ) ) {
	define( 'PSTOOLKIT_SUI_VERSION', '2.9.6' );
}

register_activation_hook( __FILE__, 'pstoolkit_register_activation_hook' );
register_deactivation_hook( __FILE__, 'pstoolkit_register_deactivation_hook' );
register_uninstall_hook( __FILE__, 'pstoolkit_register_uninstall_hook' );

