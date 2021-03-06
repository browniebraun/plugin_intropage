<?php
/* vim: ts=4
 +-------------------------------------------------------------------------+
 | Copyright (C) 2015-2021 Petr Macek                                      |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | https://github.com/xmacan/                                              |
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function plugin_intropage_install() {
	api_plugin_register_hook('intropage', 'config_form', 'intropage_config_form', 'include/settings.php');
	api_plugin_register_hook('intropage', 'config_settings', 'intropage_config_settings', 'include/settings.php');
	api_plugin_register_hook('intropage', 'login_options_navigate', 'intropage_login_options_navigate', 'include/settings.php');
	api_plugin_register_hook('intropage', 'top_header_tabs', 'intropage_show_tab', 'include/tab.php');
	api_plugin_register_hook('intropage', 'top_graph_header_tabs', 'intropage_show_tab', 'include/tab.php');
	api_plugin_register_hook('intropage', 'console_after', 'intropage_console_after', 'include/settings.php');
	api_plugin_register_hook('intropage', 'page_head', 'intropage_page_head', 'setup.php');
//	api_plugin_register_hook('intropage', 'user_admin_setup_sql_save', 'intropage_user_admin_setup_sql_save', 'include/settings.php');
	api_plugin_register_hook('intropage', 'user_group_admin_setup_sql_save', 'intropage_user_group_admin_setup_sql_save', 'include/settings.php');
	api_plugin_register_hook('intropage', 'graph_buttons', 'intropage_graph_button', 'include/functions.php');
	api_plugin_register_hook('intropage', 'graph_buttons_thumbnails', 'intropage_graph_button', 'include/functions.php');
	// need for collecting poller time
	api_plugin_register_hook('intropage', 'poller_bottom', 'intropage_poller_bottom', 'setup.php');
	api_plugin_register_hook('intropage', 'user_admin_tab', 'intropage_user_admin_tab', 'includes/settings.php');
	api_plugin_register_hook('intropage', 'user_admin_run_action', 'intropage_user_admin_run_action', 'includes/settings.php');
	//api_plugin_register_hook('intropage', 'user_admin_action', 'intropage_user_admin_action', 'includes/settings.php');
	api_plugin_register_hook('intropage', 'user_admin_user_save', 'intropage_user_admin_user_save', 'includes/settings.php');
	api_plugin_register_hook('intropage', 'user_remove', 'intropage_user_remove', 'setup.php');
	api_plugin_register_realm('intropage', 'intropage.php,intropage_ajax.php', 'Plugin Intropage - view', 1);

	intropage_setup_database();
}



function plugin_intropage_uninstall() {
	global $config;

	include_once($config['base_path'] . '/plugins/intropage/include/database.php');
	intropage_drop_database();
}

function plugin_intropage_version() {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/intropage/INFO', true);
	return $info['info'];
}

function plugin_intropage_upgrade() {
	// Here we will upgrade to the newest version
	intropage_check_upgrade();
	return false;
}

function plugin_intropage_check_config() {
	// Here we will check to ensure everything is configured
	intropage_check_upgrade();
	return true;
}

function intropage_check_upgrade() {
	global $config;

	include_once($config['base_path'] . '/plugins/intropage/include/database.php');
	intropage_upgrade_database();
}

function intropage_page_head() {
	global $config;

	$selectedTheme = get_selected_theme();

	print "<link type='text/css' href='" . $config['url_path'] . "plugins/intropage/themes/common.css' rel='stylesheet'>";

	if (file_exists($config['base_path'] . '/plugins/intropage/themes/' . $selectedTheme . '.css')) {
		print "<link type='text/css' href='" . $config['url_path'] . 'plugins/intropage/themes/' . $selectedTheme . ".css' rel='stylesheet'>";
	}
}

function intropage_setup_database() {
	global $config;

	include_once($config['base_path'] . '/plugins/intropage/include/database.php');

	intropage_initialize_database();
}

function intropage_poller_bottom() {
	global $config;

	include_once($config['library_path'] . '/poller.php');

	$command_string = trim(read_config_option('path_php_binary'));

    	if (trim($command_string) == '') {
        	$command_string = 'php';
	}

	$extra_args = ' -q ' . $config['base_path'] . '/plugins/intropage/poller_intropage.php';

	exec_background($command_string, $extra_args);
}


// add third party panel:
// 1) include this file
// 2) call intropage_add_panel('my_panel','/plugins/your_plugin/file.php','yes',3600,20) {
// panel_id - your name (lowercase, without spaces, unique)
// file - path to your code. It must contain function my_panel() (and my_panel_detail() if your panel has detail)
// example functions are in /plugin/intropage/include/data.php and data_detail.php
// has_detail - yes or no
// refresh_interval - in second, min is 60
// priority - for displaying
// description - small description, it is visible in user auth settings
function intropage_add_panel($panel_id, $file, $has_detail, $refresh_interval, $priority=20, $description='') {
	if (db_execute_prepared('REPLACE INTO plugin_intropage_panel_definition
		(panel_id,file,has_detail,refresh_interval, priority, description)
		VALUES (?,?,?,?,?,?)', array($panel_id,$file,$has_detail,$refresh_interval,$priority,$description)) == 1) {

 		api_plugin_db_add_column('intropage', 'plugin_intropage_user_auth', array('name' => $panel_id, 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'));
		return ('1');
	} else {
		return db_error();
	}
}

// remove third party panel
function intropage_remove_panel($panel_id) {
	db_execute_prepared('DELETE FROM plugin_intropage_panel_data WHERE panel_id= ?', array($panel_id));
	db_execute_prepared('DELETE FROM plugin_intropage_panel_definition WHERE panel_id= ?', array($panel_id));
	db_execute_prepared('ALTER TABLE plugin_intropage_user_auth DROP ?',array($panel_id));

	return ('1');
}

function intropage_user_remove($user_id) {
	db_execute_prepared('DELETE FROM plugin_intropage_panel_data WHERE user_id= ?', array($user_id));
	db_execute_prepared('DELETE FROM plugin_intropage_panel_dashboard WHERE user_id= ?', array($user_id));
	db_execute_prepared('DELETE FROM settings_user WHERE user_id= ?', array($user_id));
}

