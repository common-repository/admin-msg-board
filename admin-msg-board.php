<?php

/*
Plugin Name: Admin Msg Board
Plugin URI: http://bitsignals.com/2008/01/14/admin-msg-board/
Description: Adds a message board to facilitate communication between bloggers of a blog. Look for the Admin Msg Board option under Manage or <a href="edit.php?page=amsgboard-menu.php">click here</a>.
Author: Julian Yanover
Version: 1.05
Author URI: http://bitsignals.com/
*/

/*  Copyright 2008  Julian Yanover  (email : julian@inicioglobal.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Declaration of globals to work with WP 2.5
	global $wpdb;
	global $amsgboard_db_version;

// Version number
$amsgboard_db_version = "1.05";

// Call the language function and load the textdomain
function amsgboard_textdomain() {
	load_plugin_textdomain('wp-admin-msg-board', 'wp-content/plugins/admin-msg-board');
}
add_action('init', 'amsgboard_textdomain');

// Install the plugin
function amsgboard_install() {
	global $wpdb;
	global $amsgboard_db_version;
	if ( $wpdb->get_var("SHOW TABLES LIKE 'wp_amsgboard'") != 'wp_amsgboard' ) {
		$sql = "CREATE TABLE wp_amsgboard (
			id INT(11) NOT NULL AUTO_INCREMENT,
			dateposted INT(11) NOT NULL,
			msgposted VARCHAR(140) NOT NULL,
			sentto VARCHAR(255) NOT NULL,
			readby VARCHAR(255) NOT NULL,
			UNIQUE KEY id (id)
		);";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	$installedamsgboard = get_option( "amsgboard_db_version" );
	if ( $installedamsgboard != $amsgboard_db_version )
		update_option( "amsgboard_db_version", $amsgboard_db_version );
	else
		add_option("amsgboard_db_version", $amsgboard_db_version);
}
 
// Where you add the messages, the Admin Msg Board per se
function amsgboard_menu() {
    global $wpdb;
    include 'amsgboard-menu.php';
}

// Add the link to the Admin Msg Board under 'Manage'
function amsgboard_actions() {
	add_management_page('Admin Msg Board', 'Admin Msg Board', 1, 'amsgboard-menu.php', 'amsgboard_menu');
}

// Check if the current user read the last story and decide whether to alert him or not
function amsgboard_newmsg() {
	global $current_user;
	global $wpdb;
	$amsgboardlast = $wpdb->get_row("SELECT readby, sentto FROM wp_amsgboard order by id DESC limit 1", ARRAY_A);
	if ( !in_array($current_user->ID, explode("-",$amsgboardlast["readby"])) and "subscriber" != $current_user->roles[0] and ( in_array($current_user->ID, explode("-",$amsgboardlast["sentto"])) or ("all users" == $amsgboardlast["sentto"]) ) ) {
		echo "<div id='update-nag'>";
		_e('There is a new message in the Admin Msg Board.', 'wp-admin-msg-board');
		echo " <a href='edit.php?page=amsgboard-menu.php'>";
		_e('Read it', 'wp-admin-msg-board');
		echo "</a>.</div>";
 }
}


// Calls the function to install the plugin
register_activation_hook('admin-msg-board/admin-msg-board.php', 'amsgboard_install');

// Calls the function to add the link under 'Manage'
add_action('admin_menu', 'amsgboard_actions');

// Calls the function to alert the user of new messages
$current_plugins = get_option('active_plugins');
if (in_array('admin-msg-board/admin-msg-board.php', $current_plugins))
	add_action('admin_notices', 'amsgboard_newmsg');

?>