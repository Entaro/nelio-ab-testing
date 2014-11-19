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

$nelioab_controller = false;

/**
 * Nelio AB Testing main controller
 *
 * @package Nelio AB Testing
 * @subpackage Experiment
 * @since 0.1
 */
if ( !class_exists( 'NelioABController' ) ) {

	class NelioABController {

		const FRONT_PAGE__YOUR_LATEST_POSTS      = -100;
		const NAVIGATION_ORIGIN_FROM_THE_OUTSIDE = -101;
		const NAVIGATION_ORIGIN_IS_UNKNOWN       = -102;
		const WRONG_NAVIGATION_DESTINATION       = -103;

		private $controllers;
		private $url;

		public function __construct() {
			require_once( NELIOAB_UTILS_DIR . '/dtgtm4wp-support.php' );

			$this->build_current_url();

			$this->controllers = array();
			$directory   = NELIOAB_DIR . '/experiment-controllers';

			require_once( $directory . '/alternative-experiment-controller.php' );
			$this->controllers['alt-exp'] = new NelioABAlternativeExperimentController();

			// Iconography and Menu bar
			add_action( 'admin_bar_init', array( $this, 'add_custom_styles' ), 95 );

			if ( NelioABSettings::is_menu_enabled_for_admin_bar() ) {
				add_action( 'admin_bar_menu',
					array( $this, 'create_nelioab_admin_bar_menu' ), 40 );
				add_action( 'admin_bar_menu',
					array( $this, 'create_nelioab_admin_bar_quickexp_option' ), 999 );
			}

			require_once( $directory . '/heatmap-experiment-controller.php' );
			$aux = new NelioABHeatmapExperimentController();
			$this->controllers['hm'] = $aux;

			// Preparing AJAX callbacks
			$this->prepare_ajax_callbacks();

			if ( isset( $_GET['nelioab_preview_css'] ) ) {
				add_action( 'the_content', array( &$this->controllers['alt-exp'], 'preview_css' ) );
				add_action( 'the_excerpt', array( &$this->controllers['alt-exp'], 'preview_css' ) );
			}

			global $pagenow;
			if ( $this->is_alternative_content_loading_required() || ( isset( $pagenow ) && 'widgets.php' == $pagenow ) ) {
				// We'll filter the widgets as we please
			}
			else {
				// We must show the original widgets only
				add_filter( 'sidebars_widgets', array( &$this->controllers['alt-exp'], 'filter_original_widgets' ) );
			}

		}

		public function add_custom_styles() {
			require_once( NELIOAB_UTILS_DIR . '/wp-helper.php' );
			if ( NelioABWpHelper::is_at_least_version( 3.8 ) ) {
				wp_register_style( 'nelioab_new_icons_css',
					nelioab_admin_asset_link( '/css/nelioab-new-icons.min.css' ) );
				wp_enqueue_style( 'nelioab_new_icons_css' );
			}
		}

		private function prepare_ajax_callbacks() {

			add_action( 'wp_ajax_nopriv_nelioab_send_navigation',
				array( &$this, 'send_navigation' ) );
			add_action( 'wp_ajax_nelioab_send_navigation',
				array( &$this, 'send_navigation' ) );

			add_action( 'wp_ajax_nopriv_nelioab_send_headlines_info',
				array( &$this->controllers['alt-exp'], 'send_headlines_info' ) );
			add_action( 'wp_ajax_nelioab_send_headlines_info',
				array( &$this->controllers['alt-exp'], 'send_headlines_info' ) );

			add_action( 'wp_ajax_nopriv_nelioab_external_page_accessed_action_urls',
				array( &$this->controllers['alt-exp'], 'get_external_page_accessed_action_urls' ) );
			add_action( 'wp_ajax_nelioab_external_page_accessed_action_urls',
				array( &$this->controllers['alt-exp'], 'get_external_page_accessed_action_urls' ) );

			add_action( 'wp_ajax_nopriv_nelioab_sync_cookies_and_check',
				array( &$this, 'sync_cookies_and_check' ) );
			add_action( 'wp_ajax_nelioab_sync_cookies_and_check',
				array( &$this, 'sync_cookies_and_check' ) );

			add_action( 'wp_ajax_nopriv_nelioab_send_heatmap_info',
				array( &$this->controllers['hm'], 'save_heatmap_info_into_cache' ) );
			add_action( 'wp_ajax_nelioab_send_heatmap_info',
				array( &$this->controllers['hm'], 'save_heatmap_info_into_cache' ) );

			add_action( 'wp_ajax_nopriv_nelioab_track_heatmaps_for_post',
				array( &$this->controllers['hm'], 'track_heatmaps_for_post' ) );
			add_action( 'wp_ajax_nelioab_track_heatmaps_for_post',
				array( &$this->controllers['hm'], 'track_heatmaps_for_post' ) );

			add_action( 'wp_ajax_nopriv_nelioab_sync_heatmaps',
				array( &$this->controllers['hm'], 'send_heatmap_info_if_required' ) );
			add_action( 'wp_ajax_nelioab_sync_heatmaps',
				array( &$this->controllers['hm'], 'send_heatmap_info_if_required' ) );

		}

		public function sync_cookies_and_check() {
			// We control that cookies correspond to the last version of the plugin
			$this->version_control();

			// We update the cookies
			$cookies = $this->update_cookies();

			// Finally, we check if we need to load an alternative
			$alt_con  = $this->controllers['alt-exp'];
			$load_alt = $alt_con->check_requires_an_alternative( $_POST['current_url'] );
			$mode     = NelioABSettings::get_alternative_loading_mode();
			$result   = array(
				'cookies'  => $cookies,
				'load_alt' => $load_alt,
				'mode'     => $mode );
			echo json_encode( $result );
			die();
		}

		private function update_cookies() {
			// We assign the current user an ID (if she does not have any)
			require_once( NELIOAB_MODELS_DIR . '/user.php' );
			$user_id = NelioABUser::get_id();

			// And we prepare the other cookies
			$alt_con = $this->controllers['alt-exp'];
			$cookies = $alt_con->sync_cookies();

			// Clean old cookies
			require_once( NELIOAB_MODELS_DIR . '/experiments-manager.php' );
			$running_exps = NelioABExperimentsManager::get_running_experiments_from_cache();
			foreach ( $cookies as $cookie_name => $cookie_value ) {
				$keep_cookie = false;
				foreach ( $running_exps as $exp ) {
					if ( strpos( $cookie_name, 'altexp_' ) === false ||
					     strpos( $cookie_name, $exp->get_id() ) > 0 ) {
						$keep_cookie = true;
						break;
					}
				}
				if ( !$keep_cookie )
					$cookies[$cookie_name] = '__delete_cookie';
			}

			return $cookies;
		}

		public function init() {
			// If the user has been disabled... get out of here
			try {
				$aux = NelioABAccountSettings::check_user_settings();
			}
			catch ( Exception $e ) {
				// It is important we add the check here: if the user was deactivated, but it no
				// longer is, then it's important his settings are rechecked so that we can
				// re-enable it.
				if ( $e->getCode() == NelioABErrCodes::DEACTIVATED_USER )
					return;
			}

			// Trick for proper THEME ALT EXP testing
			require_once( NELIOAB_UTILS_DIR . '/wp-helper.php' );
			// Theme alt exp related
			if ( NelioABWpHelper::is_at_least_version( 3.4 ) ) {
				$aux = $this->controllers['alt-exp'];
				add_filter( 'stylesheet',       array( &$aux, 'modify_stylesheet' ) );
				add_filter( 'template',         array( &$aux, 'modify_template' ) );
				add_filter( 'sidebars_widgets', array( &$aux, 'show_the_appropriate_widgets' ) );
			}

			// If we're previewing a page alternative, it may be the case that it's an
			// alternative of the landing page. Let's make sure the "page_on_front"
			// option is properly updated:
			if ( isset( $_GET['preview'] ) && isset( $_GET['nelioab_original_id'] ) && isset( $_GET['page_id'] ) ) {
				add_filter( 'option_page_on_front', array( &$this, 'fix_page_on_front' ) );
			}

			add_action( 'init', array( &$this, 'do_init' ) );
			add_action( 'init', array( &$this, 'init_admin_stuff' ) );
		}

		public function fix_page_on_front( $page_on_front ) {
			if ( isset( $_GET['preview'] ) && isset( $_GET['nelioab_original_id'] ) && isset( $_GET['page_id'] ) ) {
				if ( $page_on_front == $_GET['nelioab_original_id'] )
					return $_GET['page_id'];
				else
					return $page_on_front;
			}
			else {
				$aux = $this->controllers['alt-exp'];
				return $aux->get_post_alternative( $page_on_front );
			}
		}

		public function send_navigation() {
			$dest_post_id = $this->url_or_front_page_to_postid( $_POST['dest_url'] );
			$referer = '';
			if ( isset( $_POST['ori_url'] ) )
				$referer = $_POST['ori_url'];

			if ( isset( $_POST['is_external_page'] ) )
				$this->send_navigation_if_required( $_POST['dest_url'], $referer, false );
			else if ( $dest_post_id )
				$this->send_navigation_if_required( $dest_post_id, $referer );

			if ( NelioABAccountSettings::get_subscription_plan() >=
			     NelioABAccountSettings::ENTERPRISE_SUBSCRIPTION_PLAN )
				$this->compute_results_for_running_experiments();

			$alt_con = $this->controllers['alt-exp'];
			$alt_con->update_current_winner_for_running_experiments();
			die();
		}

		public function compute_results_for_running_experiments() {
			// 1. Check if the last check was, at least, 5 minutes ago
			$now = time();
			$last_update = get_option( 'nelioab_last_update_of_results', 0 );
			if ( $now - $last_update < 300 )
				return;
			update_option( 'nelioab_last_update_of_results', $now );

			// 2. Check if we have running experiments
			require_once( NELIOAB_MODELS_DIR . '/experiments-manager.php' );
			$running_exps = NelioABExperimentsManager::get_running_experiments_from_cache();
			if ( count( $running_exps ) == 0 )
				return;

			try {
				// 3. Ask AE to recompute the results
				$url = sprintf(
						NELIOAB_BACKEND_URL . '/site/%s/exp/result/compute',
						NelioABAccountSettings::get_site_id()
					);
				$result = NelioABBackend::remote_get( $url );

				// 4. Update the winning alternative info using the latest results available
				$alt_con = $this->controllers['alt-exp'];
				$alt_con->update_current_winner_for_running_experiments( 'force_update' );
			}
			catch ( Exception $e ) {}
		}

		private function send_navigation_if_required( $dest_id, $referer_url, $is_internal = true ) {
			$alt_exp_con = $this->controllers['alt-exp'];
			$nav = $alt_exp_con->prepare_navigation_object( $dest_id, $referer_url, $is_internal );

			if ( $is_internal && !$this->is_relevant( $nav ) )
				return;

			$this->send_navigation_object( $nav );
		}

		public function send_navigation_object( $nav ) {
			require_once( NELIOAB_UTILS_DIR . '/backend.php' );

			// If there's no quota available (and no check is required), quit
			if ( !NelioABAccountSettings::has_quota_left() && !NelioABAccountSettings::is_quota_check_required() )
				return;

			// If the navigation is to the same page it comes from, do not send it
			if ( $nav['origin'] == $nav['destination'] )
				return;

			// Adding more information about navigations
			if ( isset( $_SERVER['HTTP_USER_AGENT'] ) )
				$nav['userAgent'] = $_SERVER['HTTP_USER_AGENT'];
			else
				$nav['userAgent'] = 'unknown';

			if ( function_exists( 'uniqid' ) )
				$nav['session'] = uniqid();
			else
				$nav['session'] = time();

			$url = sprintf(
				NELIOAB_BACKEND_URL . '/site/%s/nav',
				NelioABAccountSettings::get_site_id()
			);

			$data = NelioABBackend::build_json_object_with_credentials( $nav );
			$data['timeout'] = 50;

			for ( $attemp=0; $attemp < 5; ++$attemp ) {
				try {
					$result = NelioABBackend::remote_post_raw( $url, $data );
					NelioABAccountSettings::set_has_quota_left( true );
					break;
				}
				catch ( Exception $e ) {
					// If the navigation could not be sent, it may be the case because
					// there is no more quota available
					if ( $e->getCode() == NelioABErrCodes::NO_MORE_QUOTA ) {
						NelioABAccountSettings::set_has_quota_left( false );
						break;
					}
					// If there was another error... we just keep trying (attemp) up to 5
					// times.
				}
			}
		}

		public function is_relevant( $nav ) {

			$aux = $this->controllers['alt-exp'];
			if ( $aux->is_relevant( $nav ) )
				return true;

			$aux = $this->controllers['hm'];
			if ( $aux->is_relevant( $nav ) )
				return true;

			return false;
		}

		public function get_current_url() {
			return $this->url;
		}

		private function build_current_url() {
			if ( isset( $_POST['current_url'] ) ) {
				$this->url = $_POST['current_url'];
			}
			else {
				$url = 'http';
				if ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == "on" )
					$url .= 's';
				$url .= '://';
				//if ( isset( $_SERVER['SERVER_PORT'] ) && $_SERVER['SERVER_PORT'] != '80')
				//	$url .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
				//else
				//	$url .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
				$url .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
				$this->url = $url;
			}
		}

		public function url_or_front_page_to_postid( $url ) {

			if ( strlen( $url ) === 0 )
				return NelioABController::NAVIGATION_ORIGIN_FROM_THE_OUTSIDE;

			$the_id = url_to_postid( $url );
			if ( 0 === $the_id )
				$the_id = url_to_postid( str_replace( 'https://', 'http://', $url ) );

			// Checking if the source page was the Landing Page
			// This is a special case, because it might be the case that the
			// front page is dynamically built using the last posts info.
			$front_page_url = rtrim( get_bloginfo('url'), '/' );
			$front_page_url = str_replace( 'https://', 'http://', $front_page_url );
			$proper_url     = rtrim( $url, '/' );
			$proper_url     = str_replace( 'https://', 'http://', $proper_url );
			if ( $proper_url == $front_page_url ) {
				$aux = nelioab_get_page_on_front();
				if ( $aux )
					$the_id = $aux;
				if ( !$the_id )
					$the_id = NelioABController::FRONT_PAGE__YOUR_LATEST_POSTS;
			}

			// Custom Permalinks Support: making sure that we get the real ID.
			require_once( NELIOAB_UTILS_DIR . '/custom-permalinks-support.php' );
			if ( NelioABCustomPermalinksSupport::is_plugin_active() ) {
				$custom_permalink_id = NelioABCustomPermalinksSupport::url_to_postid( $url );
				if ( $custom_permalink_id )
					$the_id = $custom_permalink_id;
			}

			if ( 0 === $the_id && function_exists( 'woocommerce_get_page_id' ) ) {
				// I wasn't able to find a post/page whose URL is the provided one.
				// If the user is using WooCommerce, for instance, this happens with the
				// Shop Page (see https://core.trac.wordpress.org/ticket/25136). The
				// reported solution there does not work, so I'll try something new:
				$shop_page_id = woocommerce_get_page_id( 'shop' );
				if ( get_permalink( $shop_page_id ) == $url )
					$the_id = $shop_page_id;
			}

			if ( 0 === $the_id && strpos( $url, '?' ) !== false && strlen( get_option( 'permalink_structure', '' ) ) > 0 ) {
				$url_without_get_params = preg_replace( '/\?.*$/', '', $url );
				$the_id = $this->url_or_front_page_to_postid( $url_without_get_params );
			}

			if ( 0 === $the_id )
				$the_id = NelioABController::NAVIGATION_ORIGIN_FROM_THE_OUTSIDE;

			return $the_id;
		}

		public function url_or_front_page_to_actual_postid_considering_alt_exps( $url ) {
			$post_id = $this->url_or_front_page_to_postid( $url );
			$aux = $this->controllers['alt-exp'];
			if ( $aux->is_post_in_a_post_alt_exp( $post_id ) )
				$post_id = $aux->get_post_alternative( $post_id );
			return $post_id;
		}

		public function init_admin_stuff() {
			if ( !is_super_admin() )
				return;

			$dir = NELIOAB_DIR . '/experiment-controllers';

			// Controller for viewing heatmaps
			require_once( $dir . '/heatmap-controller.php' );
			$conexp_controller = new NelioABHeatMapController();
		}

		public function do_init() {
			// We do not perform AB Testing if the user accessing the page is...
			// ...a ROBOT...
			if ( $this->is_robot() )
				return;

			// ... or an ADMIN
			if ( is_super_admin() )
				return;

			// Custom Permalinks Support: making sure that we are not redirected while
			// loading an alternative...
			if ( $this->is_alternative_content_loading_required() ) {
				require_once( NELIOAB_UTILS_DIR . '/custom-permalinks-support.php' );
				if ( NelioABCustomPermalinksSupport::is_plugin_active() )
					NelioABCustomPermalinksSupport::prevent_template_redirect();
			}

			add_action( 'wp_enqueue_scripts', array( &$this, 'load_jquery' ) );

			// LOAD ALL CONTROLLERS

			// Controller for changing a page using its alternatives:
			$aux = $this->controllers['alt-exp'];
			$aux->hook_to_wordpress();

			// Controller for managing heatmaps (capturing and sending)
			$aux = $this->controllers['hm'];
			$aux->hook_to_wordpress();

		}

		public function is_alternative_content_loading_required() {
			$mode = NelioABSettings::get_alternative_loading_mode();
			switch ( $mode ) {
				case NelioABSettings::POST_ALTERNATIVE_LOADING_MODE:
					if ( isset( $_POST['nelioab_load_alt'] ) )
						return 'nelioab_load_alt';
					if ( isset( $_POST['nelioab_load_consistent_version'] ) )
						return 'nelioab_load_consistent_version';
				case NelioABSettings::GET_ALTERNATIVE_LOADING_MODE:
					if ( isset( $_GET['nelioab_load_alt'] ) )
						return 'nelioab_load_alt';
					if ( isset( $_GET['nelioab_load_consistent_version'] ) )
						return 'nelioab_load_consistent_version';
			}
			return false;
		}

		public function load_jquery() {
			wp_enqueue_script( 'nelioab_alternatives_script_generic',
				nelioab_asset_link( '/js/nelioab-generic.min.js' ) );
			wp_localize_script( 'nelioab_alternatives_script_generic',
				'NelioABGeneric', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
		}

		/**
		 * When a user connects to our site, she gets a set of cookies. These
		 * cookies depend on the version of the plugin. If the last time she
		 * connected the site had an older version, we update the information
		 * so that she can get rid of any old cookies (via JS).
		 */
		private function version_control() {
			global $NELIOAB_COOKIES;
			$cookie_name  = NelioABSettings::cookie_prefix() . 'version';
			$last_version = 0;
			if ( isset( $NELIOAB_COOKIES[$cookie_name] ) )
				$last_version = $NELIOAB_COOKIES[$cookie_name];

			if ( $last_version == NELIOAB_PLUGIN_VERSION )
				return;

			$aux = array();
			$userid_key = NelioABSettings::cookie_prefix() . 'userid';
			if ( isset( $NELIOAB_COOKIES[$userid_key] ) )
				$aux[$userid_key] = $NELIOAB_COOKIES[$userid_key];

			$NELIOAB_COOKIES = $aux;
			nelioab_setcookie( $cookie_name, NELIOAB_PLUGIN_VERSION, time() + (86400*28) );
			nelioab_setcookie( '__nelioab_new_version', 'true' );
		}

		/**
		 * This function creates the admin bar menu.
		 *
		 * @package Nelio AB Testing
		 * @subpackage Main Plugin Controller
		 *
		 * @since 3.3
		 */
		public function create_nelioab_admin_bar_menu() {

			// If the current user is NOT admin or we are in the admin UI, do not show the menu
			if ( !is_super_admin() || is_admin() )
				return;

			global $wp_admin_bar, $post;
			require_once( NELIOAB_MODELS_DIR . '/experiment.php' );

			// Get Current Element (post or page)
			$current_element = '';
			$is_page = false;
			$is_post = false;

			if ( is_singular() )  {
				if ( is_singular( 'post' ) ) {
					$is_post = true;
					$current_element = '&post-id=' . $post->ID;
				}
				else if ( is_page() ) {
					$is_page = true;
					$current_element = '&page-id=' . $post->ID;
				}
			}

			$nelioab_admin_bar_menu = 'nelioab-admin-bar-menu';

			// Main Admin bar menu
			// ----------------------------------------------------------------------
			$wp_admin_bar->add_node( array(
				'id'    => $nelioab_admin_bar_menu,
				'title' => __( 'Nelio A/B Testing', 'nelioab' ),
				'href'  => admin_url( 'admin.php?page=nelioab-dashboard' ),
			) );

			// Add Experiment page
			// ----------------------------------------------------------------------
			$wp_admin_bar->add_node( array(
				'parent' => $nelioab_admin_bar_menu,
				'id'     => 'nelioab_admin_add_experiment',
				'title'  => __( 'Add Experiment', 'nelioab' ),
				'href'   => admin_url( 'admin.php?page=nelioab-add-experiment' ),
			) );

			if ( $is_post ) {
				// -> New A/B Test for Post Headlines
				// ----------------------------------------------------------------------
				$wp_admin_bar->add_node(array(
					'parent' => 'nelioab_admin_add_experiment',
					'id' => 'nelioab_admin_new_exp_titles',
					'title' => __( 'Create Headline Test for this Post', 'nelioab' ),
					'href' => admin_url( 'admin.php?page=nelioab-add-experiment&experiment-type=' . NelioABExperiment::HEADLINE_ALT_EXP . $current_element ),
				));

				// -> New A/B Test for Posts
				// ----------------------------------------------------------------------
				$wp_admin_bar->add_node(array(
					'parent' => 'nelioab_admin_add_experiment',
					'id' => 'nelioab_admin_new_exp_posts',
					'title' => __( 'Create A/B Test for this Post', 'nelioab' ),
					'href' => admin_url( 'admin.php?page=nelioab-add-experiment&experiment-type=' . NelioABExperiment::POST_ALT_EXP . $current_element ),
				));

			}

			if ( $is_page ) {
				// -> New A/B Test for Pages
				// ----------------------------------------------------------------------
				$wp_admin_bar->add_node(array(
					'parent' => 'nelioab_admin_add_experiment',
					'id' => 'nelioab_admin_new_exp_pages',
					'title' => __( 'Create A/B Test for this Page', 'nelioab' ),
					'href' => admin_url( 'admin.php?page=nelioab-add-experiment&experiment-type=' . NelioABExperiment::PAGE_ALT_EXP . $current_element ),
				));
			}

			if ( $is_post || $is_page ) {
				// -> New Heatmap Experiment for Page or Post
				// ----------------------------------------------------------------------
				$wp_admin_bar->add_node( array(
					'parent' => 'nelioab_admin_add_experiment',
					'id'     => 'nelioab_admin_new_exp_heatmaps',
					'title'  => __( 'Create Heatmap Experiment', 'nelioab' ),
					'href'   => admin_url( 'admin.php?page=nelioab-add-experiment&experiment-type=' . NelioABExperiment::HEATMAP_EXP . $current_element ),
				) );
			}

			// Dashboard page
			// ----------------------------------------------------------------------
			$wp_admin_bar->add_node( array(
				'parent' => $nelioab_admin_bar_menu,
				'id'     => 'nelioab_admin_dashboard',
				'title'  => __( 'Dashboard', 'nelioab' ),
				'href'   => admin_url( 'admin.php?page=nelioab-dashboard' ),
			) );

			// Experiments page
			// ----------------------------------------------------------------------
			$wp_admin_bar->add_node( array(
				'parent' => $nelioab_admin_bar_menu,
				'id'     => 'nelioab_admin_experiments',
				'title'  => __( 'Experiments', 'nelioab' ),
				'href'   => admin_url( 'admin.php?page=nelioab-experiments' ),
			) );
		}

		/**
		 * This function creates the admin bar menu.
		 *
		 * @package Nelio AB Testing
		 * @subpackage Main Plugin Controller
		 *
		 * @since 3.3
		 */
		public function create_nelioab_admin_bar_quickexp_option() {
			global $wp_admin_bar, $post;
			require_once( NELIOAB_MODELS_DIR . '/experiment.php' );

			// Get Current Element (post or page)
			$current_element = '';
			$is_page = false;
			$is_post = false;

			if ( is_singular() )  {
				if ( is_singular( 'post' ) ) {
					$is_post = true;
					$current_element = '&post-id=' . $post->ID;
				}
				else if ( is_page() ) {
					$is_page = true;
					$current_element = '&page-id=' . $post->ID;
				}
			}

			// Quick Experiment menu
			// ----------------------------------------------------------------------
			if ( $is_post ) {
				$wp_admin_bar->add_node(array(
					'id' => 'nelioab_admin_bar_quick_menu',
					'title' => __( 'Create Headline Experiment', 'nelioab' ),
					'href' => admin_url( 'admin.php?page=nelioab-add-experiment&experiment-type=' . NelioABExperiment::HEADLINE_ALT_EXP . $current_element ),
				));
			}

			if ( $is_page ) {
				$wp_admin_bar->add_node(array(
					'id' => 'nelioab_admin_bar_quick_menu',
					'title' => __( 'Create A/B Experiment', 'nelioab' ),
					'href' => admin_url( 'admin.php?page=nelioab-add-experiment&experiment-type=' . NelioABExperiment::PAGE_ALT_EXP . $current_element ),
				));
			}
		}


		/**
		 * Quickly detects whether the current user is a bot, based on
		 * User Agent. Keep in mind the function is not very precise.
		 * Do not use for page blocking.
		 *
		 * @return bool true if the user is a bot, false otherwise.
		 */
		private function is_robot() {
			$list = 'bot|crawl|spider|https?:' .
				'|Google|Rambler|Lycos|Y!|Yahoo|accoona|Scooter|AltaVista|yandex' .
				'|ASPSeek|Ask Jeeves|eStyle|Scrubby';

			return preg_match("/$list/i", @$_SERVER['HTTP_USER_AGENT']);
		}

	}//NelioABController

	$nelioab_controller = new NelioABController();
	if ( !is_admin() )
		$nelioab_controller->init();

}
