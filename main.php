<?php
/**
 * Copyright 2013 Nelio Software S.L.
 * This script is distributed under the terms of the GNU General Public
 * License.
 *
 * This script is free software: you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License.
 *
 * This script is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program. If not, see <http://www.gnu.org/licenses/>.
 */

/*
 * Plugin Name: Nelio A/B Testing
 * Description: Optimize your site based on data, not opinions. With this plugin, you will be able to perform A/B testing (and more) on your WordPress site.
 * Version: 3.3.6
 * Author: Nelio Software
 * Author URI: http://neliosoftware.com
 * Plugin URI: http://wp-abtesting.com
 * Text Domain: nelioab
 */

// PLUGIN VERSION
define( 'NELIOAB_PLUGIN_VERSION', '3.3.6' );

// Plugin dir name...
define( 'NELIOAB_PLUGIN_NAME', 'Nelio A/B Testing' );
define( 'NELIOAB_PLUGIN_ID', 'nelio-ab-testing/main.php' );
define( 'NELIOAB_PLUGIN_DIR_NAME', basename( dirname( __FILE__ ) ) );

// Defining a few important directories
define( 'NELIOAB_ROOT_DIR', rtrim( plugin_dir_path( __FILE__ ), '/' ) );
define( 'NELIOAB_DIR', NELIOAB_ROOT_DIR . '/includes' );

define( 'NELIOAB_ADMIN_DIR', NELIOAB_DIR . '/admin' );
define( 'NELIOAB_UTILS_DIR', NELIOAB_DIR . '/utils' );
define( 'NELIOAB_MODELS_DIR', NELIOAB_DIR . '/models' );

// Some URLs...
define( 'NELIOAB_URL', rtrim( plugin_dir_url( __FILE__ ), '/' ) );
define( 'NELIOAB_BACKEND_VERSION', 5 );
define( 'NELIOAB_BACKEND_DOMAIN', 'nelioabtesting.appspot.com' );
define( 'NELIOAB_BACKEND_URL', 'https://' . NELIOAB_BACKEND_DOMAIN . '/_ah/api/nelioab/v' . NELIOAB_BACKEND_VERSION );
define( 'NELIOAB_FEEDBACK_URL', 'https://neliofeedback.appspot.com/_ah/api/feedback/v1');
define( 'NELIOAB_ASSETS_URL', plugins_url() . '/' . NELIOAB_PLUGIN_DIR_NAME . '/assets' );

function nelioab_i18n() {
	load_plugin_textdomain( 'nelioab', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
}
add_action( 'plugins_loaded', 'nelioab_i18n' );

// Looger functions
require_once( NELIOAB_UTILS_DIR . '/logger.php' );

// Including Settings classes
require_once( NELIOAB_MODELS_DIR . '/settings.php' );
require_once( NELIOAB_MODELS_DIR . '/account-settings.php' );

// Including basic functions (custom cookies and helpers)
require_once( NELIOAB_UTILS_DIR . '/essentials.php' );
require_once( NELIOAB_UTILS_DIR . '/cookies.php' );

// Including base controllers
require_once( NELIOAB_DIR . '/controller.php' );
require_once( NELIOAB_ADMIN_DIR . '/admin-controller.php' );

// Making sure all alternatives are hidden when the plugin is deactivated
register_activation_hook( __FILE__, 'nelioab_activate_plugin' );
register_deactivation_hook( __FILE__, 'nelioab_deactivate_plugin' );

// Making sure everything is up-to-date when the plugin is updated
add_action( 'plugins_loaded', 'nelioab_update_plugin_info_if_required' );

add_action( 'wp_ajax_dismiss_upgrade_notice', 'dismiss_upgrade_notice_callback' );
function dismiss_upgrade_notice_callback() {
	NelioABSettings::hide_upgrade_message();
	echo '0';
	die();
}

