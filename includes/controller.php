<?php
/**
 * Copyright 2015 Nelio Software S.L.
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

/**
 * This global variable holds a reference to the Nelio A/B Testing main
 * controller.
 *
 * @since 1.0.10
 * @var NelioABController This controller is responsible of loading the A/B
 * Testing experiment controllers, sending some relevant data to our tracking
 * JavaScripts, and so on.
 */
$nelioab_controller = NULL;

if ( !class_exists( 'NelioABController' ) ) {

	/**
	 * Nelio AB Testing main controller.
	 *
	 * This class is responsible of loading the A/B Testing experiment
	 * controllers, sending some relevant data to our tracking JavaScripts, and
	 * so on.
	 *
	 * @since 1.0.10
	 * @package \NelioABTesting\Controllers
	 */
	class NelioABController {

		/**
		 * Internal ID used for identifying the Latest Posts page.
		 *
		 * This ID is used when the front page is not set to use an actual page.
		 *
		 * @since 1.2.0
		 * @var int
		 */
		const FRONT_PAGE__YOUR_LATEST_POSTS = -100;


		/**
		 * Internal ID used for identifying a Front Page generated by the theme.
		 *
		 * @since 3.4.0
		 * @var int
		 */
		const FRONT_PAGE__THEME_BASED_LANDING = -104;


		/**
		 * Internal ID used for identifying a page or post that we were unable to find.
		 *
		 * Probably, the page or post we're trying to search is WordPress'. If it is,
		 * then we have to be able to obtain its ID, but if we can't, then this
		 * ID is used instead.
		 *
		 * @since 3.4.0
		 * @var int
		 */
		const UNKNOWN_PAGE_ID_FOR_NAVIGATION = -105;


		/**
		 * Internal ID used for identifying the Search Results page.
		 *
		 * @since 4.1.0
		 * @var int
		 */
		const SEARCH_RESULTS_PAGE_ID = -106;


		/**
		 * Internal ID used for identifying a Category, Tag, or any Taxonomy page.
		 *
		 * @since 4.1.0
		 * @var int
		 */
		const CATEGORY_OR_TERM_PAGE_ID = -107;


		/**
		 * List of additional controllers.
		 *
		 * For instance, the controller for managing alternative experiments.
		 *
		 * @since 1.0.10
		 * @var array
		 */
		private $controllers;


		/**
		 * The main query that triggered the current page request.
		 *
		 * @since 1.0.10
		 * @var WP_Query
		 */
		private $main_query;


		/**
		 * The ID of the queried object.
		 *
		 * @since 1.0.10
		 * @var int
		 */
		private $queried_post_id;


		/**
		 * This array is a the JavaScript object named NelioABParams.
		 *
		 * @since 3.3.2
		 * @var array It contains some information required by our tracking
		 *            JavaScript, such as, for instance, the ID and actual ID of
		 *            the current page.
		 */
		private $tracking_script_params;


		/**
		 * It creates a new instance of this controller.
		 *
		 * In principle, this class should be used as if it implemented the
		 * `singleton` pattern.
		 *
		 * @return NelioABController a new instance of this class.
		 *
		 * @see self::init
		 * @since 1.0.10
		 */
		public function __construct() {
			$this->queried_post_id = false;
			$this->controllers = array();
			$this->tracking_script_params = array();

			require_once( NELIOAB_EXP_CONTROLLERS_DIR . '/alternative-experiment-controller.php' );
			$this->controllers['alt-exp'] = new NelioABAlternativeExperimentController();

			// Iconography and Menu bar
			add_action( 'admin_bar_init', array( $this, 'add_custom_styles' ), 95 );

			if ( NelioABSettings::is_menu_enabled_for_admin_bar() ) {
				add_action( 'admin_bar_menu',
					array( $this, 'create_nelioab_admin_bar_menu' ), 40 );
				add_action( 'admin_bar_menu',
					array( $this, 'create_nelioab_admin_bar_quickexp_option' ), 999 );
			}

			if ( isset( $_GET['nelioab_preview_css'] ) )
				add_action( 'wp_footer', array( &$this->controllers['alt-exp'], 'preview_css' ) );
		}


		/**
		 * It registers and enqueues the CSS files that contain our plugin iconography.
		 *
		 * @return void
		 *
		 * @since 3.3.0
		 */
		public function add_custom_styles() {
			require_once( NELIOAB_UTILS_DIR . '/wp-helper.php' );
			if ( NelioABWpHelper::is_at_least_version( 3.8 ) ) {
				wp_register_style( 'nelioab_new_icons_css',
					nelioab_admin_asset_link( '/css/nelioab-new-icons.min.css', false ),
					array(),
					NELIOAB_PLUGIN_VERSION
				);
				wp_enqueue_style( 'nelioab_new_icons_css' );
			}
		}


		/**
		 * It initializes the controller.
		 *
		 * If Nelio's subscription has been deactivated, the plugin will do
		 * nothing at all.
		 *
		 * @return void
		 */
		public function init() {
			// If the user has been disabled... get out of here
			try {
				NelioABAccountSettings::check_user_settings();
			}
			catch ( Exception $e ) {
				// It is important we add the check here: if the user was deactivated, but it no
				// longer is, then it's important his settings are rechecked so that we can
				// re-enable it.
				if ( $e->getCode() == NelioABErrCodes::DEACTIVATED_USER )
					return;
			}

			// Load the Visitor class so that all assigned alternatives are
			require_once( NELIOAB_MODELS_DIR . '/visitor.php' );
			NelioABVisitor::load();

			// Trick for proper THEME ALT EXP testing
			require_once( NELIOAB_UTILS_DIR . '/wp-helper.php' );
			// Theme alt exp related
			if ( NelioABWpHelper::is_at_least_version( 3.4 ) ) {
				$aux = $this->controllers['alt-exp'];
				add_filter( 'stylesheet',       array( &$aux, 'modify_stylesheet' ) );
				add_filter( 'template',         array( &$aux, 'modify_template' ) );
				add_filter( 'sidebars_widgets', array( &$aux, 'show_the_appropriate_widgets' ) );

				add_filter( 'option_stylesheet',    array( &$aux, 'modify_option_stylesheet' ) );
				add_filter( 'option_current_theme', array( &$aux, 'modify_option_current_theme' ) );

				require_once( NELIOAB_UTILS_DIR . '/theme-compatibility-layer.php' );
				NelioABThemeCompatibilityLayer::make_compat();
			}

			add_action( 'init', array( &$this, 'do_init' ) );
			add_action( 'init', array( &$this, 'init_admin_stuff' ) );
		}


		/**
		 * Returns the ID of the front page (which might be an alternative).
		 *
		 * Under certain circumstances, when the front page is under test, we
		 * might need to load the proper alternative. This function is defined
		 * as a callback for the `option_page_on_front` hook. If an admin user is
		 * previewing an alternative of the front page, or viewing some heatmaps,
		 * or if a regular user simply accesses the front page, the function will
		 * return the proper alternative.
		 *
		 * @param int $page_on_front The page on front, as retrieved from the
		 *                           database (assuming there were no hooks
		 *                           modifying it, of course).
		 *
		 * @return int The "actual" page on front (considering alternatives).
		 *
		 *
		 * @since 3.3.0
		 */
		public function fix_page_on_front( $page_on_front ) {
			if ( isset( $_GET['page_id'] ) )
				$current_id = $_GET['page_id'];
			else
				$current_id = $this->get_queried_post_id();
			$original_id = get_post_meta( $current_id, '_nelioab_original_id', true );
			if ( isset( $_GET['preview'] ) || isset( $_GET['nelioab_show_heatmap'] ) ) {
				if ( $page_on_front == $original_id )
					return $current_id;
				else
					return $page_on_front;
			}
			else {
				/** @var $aux NelioABAlternativeExperimentController */
				$aux = $this->controllers['alt-exp'];
				return $aux->get_post_alternative( $page_on_front );
			}
		}


		/**
		 * This function hooks all the remaining Nelio components to WordPress.
		 *
		 * It's a callback of the `init` action, and the reason its hooks are not
		 * in the `NelioABController::init` method is because it ends up using a
		 * WordPress function that is not available until this point.
		 *
		 * @return void
		 *
		 * @see self::init
		 * @see self::can_visitor_be_in_experiment
		 *
		 * @since 2.0.10
		 */
		public function do_init() {
			// We do not perform AB Testing for certain visitors:
			if ( !$this->can_visitor_be_in_experiment() ) {
				add_action( 'wp_enqueue_scripts', array( &$this, 'add_js_for_compatibility' ), 10 );
				return;
			}

			// Custom Permalinks Support: making sure that we are not redirected while
			// loading an alternative...
			require_once( NELIOAB_UTILS_DIR . '/custom-permalinks-support.php' );
			if ( NelioABCustomPermalinksSupport::is_plugin_active() )
				NelioABCustomPermalinksSupport::prevent_template_redirect();

			// If we're previewing a page alternative, it may be the case that it's an
			// alternative of the landing page. Let's make sure the "page_on_front"
			// option is properly updated:
			if ( isset( $_GET['preview'] ) || isset( $_GET['nelioab_show_heatmap'] ) )
				add_filter( 'option_page_on_front', array( &$this, 'fix_page_on_front' ) );

			// Add support for Google Analytics. Make sure GA tracking scripts are loaded
			// after Nelio's.
			require_once( NELIOAB_UTILS_DIR . '/google-analytics-support.php' );
			NelioABGoogleAnalyticsSupport::move_google_analytics_after_nelio();

			add_action( 'wp_enqueue_scripts', array( &$this, 'register_tracking_script' ) );
			add_action( 'wp_enqueue_scripts', array( &$this, 'load_tracking_script' ), 99 );

			add_action( 'pre_get_posts', array( &$this, 'save_main_query' ) );

			// LOAD ALL CONTROLLERS
			// Controller for changing a page using its alternatives:
			/** @var $aux NelioABAlternativeExperimentController */
			$aux = $this->controllers['alt-exp'];
			$aux->hook_to_wordpress();
		}


		/**
		 * It saves the main WP_Query object for later access.
		 *
		 * This function saves the main `WP_Query` query that triggered the current
		 * page request. It's a callback of the `pre_get_posts` action.
		 *
		 * @param WP_Query $query The main query object that triggered the current
		 *                        page request.
		 *
		 * @return void
		 *
		 * @since 4.1.0
		 */
		public function save_main_query( $query ) {
			/** @var WP_Query $query */
			if ( $query->is_main_query() ) {
				remove_action( 'pre_get_posts', array( &$this, 'save_main_query' ) );
				$this->main_query = $query;
				add_filter( 'posts_results', array( &$this, 'obtain_queried_post_id' ) );
			}
		}


		/**
		 * It obtains the ID of the queried WP_Post object.
		 *
		 * This function is a callback of the `posts_results` filter.  Once the
		 * WP_Query object has been able to retrieve the `post` object(s) queried
		 * by the current request, we may extract its ID and save it for later
		 * usage.
		 *
		 * @param array $posts Array of `WP_Post` objects.
		 *
		 * @return array The parameter `$posts`.
		 *
		 * @see self::$queried_post_id
		 *
		 * @since 4.1.0
		 */
		public function obtain_queried_post_id( $posts ) {
			remove_filter( 'posts_results', array( &$this, 'obtain_queried_post_id' ) );

			// If we're on a search...
			if ( isset( $this->main_query->query['s'] ) ) {
				$this->queried_post_id = self::SEARCH_RESULTS_PAGE_ID;
			}

			// If we're on a category or term page...
			else if ( $this->main_query->is_category || $this->main_query->is_tag || $this->main_query->is_tax ) {
				$this->queried_post_id = self::CATEGORY_OR_TERM_PAGE_ID;
			}

			// If we're on the landing page, which shows the latest posts...
			else if ( 'posts' == get_option( 'show_on_front' ) && is_front_page() ) {
				$this->queried_post_id = self::FRONT_PAGE__YOUR_LATEST_POSTS;
			}

			// If we only found one post...
			else if ( count( $posts ) == 1 ) {
				$this->queried_post_id = $posts[0]->ID;
			}

			// If none of the previous rules works...
			else {
				$this->queried_post_id = self::UNKNOWN_PAGE_ID_FOR_NAVIGATION;
			}

			return $posts;
		}

		/**
		 * This function returns the ID of the queried object.
		 *
		 * Note that if the queried object is, for instance, a taxonomy, a category
		 * page, or a search, then internal IDs (defined as constants in this
		 * class) will be used. Therefore, only `post` IDs are returned.
		 *
		 * @return int The ID of the queried `post`.
		 *
		 * @since 4.1.0
		 */
		public function get_queried_post_id() {
			return $this->queried_post_id;
		}


		/**
		 * This function checks whether the current user can participate in the running experiments.
		 *
		 * If the user can manage the plugin (that is, if she's an admin), she
		 * can't participate on any experiment.
		 *
		 * @return boolean Whether the current user can participate in experiments
		 *                 or not.
		 *
		 * @see nelioab_can_user_manage_plugin
		 *
		 * @since 4.0.0
		 */
		public function can_visitor_be_in_experiment() {
			if ( nelioab_can_user_manage_plugin() )
				return false;

			return true;
		}


		/**
		 * This function adds a script with a minimum NelioAB object.
		 *
		 * Some themes might rely on the function
		 * `NelioAB.checker.generateAjaxParams`. If, for some reason, our tracking
		 * JavaScript was not included, then the scripts of our users that rely on
		 * our tracking script would throw an Exception. This function serves as a
		 * guard for those situations.
		 *
		 * @return void
		 *
		 * @since 4.0.0
		 */
		public function add_js_for_compatibility() {
			?><script type="text/javascript">NelioAB={checker:{generateAjaxParams:function(){return {};}}}</script><?php
			echo "\n";
			?><!-- This site uses Nelio A/B Testing <?php echo NELIOAB_PLUGIN_VERSION; ?> - https://nelioabtesting.com --><?php
			echo "\n";
			?><!-- @Webmaster, normally you will find Nelio's tracking scripts in the head section. However, admin users are excluded from tests (which means those scripts are not necessary). --><?php
			echo "\n";
		}


		/**
		 * This function registers Nelio tracking scripts.
		 *
		 * Our tracking scripts are:
		 *
		 * * `nelioab_appengine_script`: a script pointing to Google Cloud Storage,
		 * with all the information about running experiments.
		 * * `nelioab_tracking_script`: our tracking script.
		 *
		 * It also initializes some params that will be used by our tracking
		 * script. They'll be available by means of the object `NelioABParams`.
		 *
		 * @return void
		 *
		 * @see self::load_tracking_script
		 *
		 * @since 3.3.2
		 */
		public function register_tracking_script() {
			wp_register_script( 'nelioab_appengine_script',
				'//storage.googleapis.com/' . NELIOAB_BACKEND_NAME . '/' . NelioABAccountSettings::get_site_id() . '.js',
				array(),
				NELIOAB_PLUGIN_VERSION
			);
			wp_register_script( 'nelioab_tracking_script',
				nelioab_asset_link( '/js/tracking.min.js', false ),
				array( 'jquery', 'nelioab_appengine_script' ),
				NELIOAB_PLUGIN_VERSION
			);

			// Prepare some information for our tracking script (such as the page we're in)
			/** @var $aux NelioABAlternativeExperimentController */
			$aux = $this->controllers['alt-exp'];
			$current_id = $this->get_queried_post_id();
			if ( $aux->is_post_in_a_post_alt_exp( $current_id ) ) {
				$current_actual_id = intval( $aux->get_post_alternative( $current_id ) );
			}
			elseif ( $aux->is_post_in_a_headline_alt_exp( $current_id ) ) {
				$headline_data = $aux->get_headline_experiment_and_alternative( $current_id );
				/** @var NelioABAlternative $alternative */
				$alternative = $headline_data['alt'];
				$val = $alternative->get_value();
				$current_actual_id = $val['id'];
			}
			else {
				$current_actual_id = $current_id;
			}
			$current_page_ids = array(
				'currentId'       => $current_id,
				'currentActualId' => $current_actual_id,
			);

			// OUTWARDS NAVIGATIONS USING TARGET="_BLANK"
			$misc['useOutwardsNavigationsBlank'] = NelioABSettings::use_outwards_navigations_blank();

			$this->tracking_script_params = array(
					'ajaxurl'        => admin_url( 'admin-ajax.php', ( is_ssl() ? 'https' : 'http' ) ),
					'version'        => NELIOAB_PLUGIN_VERSION,
					'customer'       => NelioABAccountSettings::get_customer_id(),
					'site'           => NelioABAccountSettings::get_site_id(),
					'backend'        => array( 'domain'  => NELIOAB_BACKEND_DOMAIN,
					                           'version' => NELIOAB_BACKEND_VERSION ),
					'misc'           => $misc,
					'sync'           => array( 'headlines' => array() ),
					'info'           => $current_page_ids,
					'ieUrl'          => preg_replace( '/^https?:/', '', NELIOAB_URL . '/ajax/iesupport.php' ),
					'wasPostRequest' => ( 'POST' === $_SERVER['REQUEST_METHOD'] )
				);

		}


		/**
		 * This function enqueues Nelio's tracking scripts.
		 *
		 * @return void
		 *
		 * @see self::register_tracking_script
		 *
		 * @since 3.3.2
		 */
		public function load_tracking_script() {
			wp_localize_script( 'nelioab_tracking_script', 'NelioABParams',
				$this->tracking_script_params );
			wp_enqueue_script( 'nelioab_tracking_script' );
		}

		/**
		 * This function adds a Nelio A/B Testing sub-menu on the admin bar menu.
		 *
		 * @return void
		 *
		 * @since 3.3.0
		 */
		public function create_nelioab_admin_bar_menu() {

			// If the current user is NOT admin, do not show the menu
			if ( !nelioab_can_user_manage_plugin() )
				return;

			// If we are in the admin UI, do not show the menu
			if ( is_admin() )
				return;

			/**
			 * @var WP_Admin_Bar $wp_admin_bar
			 * @var WP_Post $post
			 */
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
		 * This function adds a Nelio A/B Testing Quick Experiment sub-menu on the admin bar menu.
		 *
		 * @return void
		 *
		 * @since 3.3.0
		 */
		public function create_nelioab_admin_bar_quickexp_option() {
			require_once( NELIOAB_MODELS_DIR . '/experiment.php' );

			/**
			 * @var WP_Admin_Bar $wp_admin_bar
			 * @var WP_Post $post
			 */
			global $wp_admin_bar, $post;

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
		 * This function initializes some administration stuff.
		 *
		 * For instance, it loads the Heatmap controller, which is used for viewing
		 * heatmaps using Nelio's interface.
		 *
		 * @return void
		 *
		 * @see NelioABHeatmapController
		 *
		 * @since 2.0.10
		 */
		public function init_admin_stuff() {
			if ( !nelioab_can_user_manage_plugin() )
				return;

			// Controller for viewing heatmaps
			require_once( NELIOAB_EXP_CONTROLLERS_DIR . '/heatmap-controller.php' );
			new NelioABHeatmapController();
		}


	}//NelioABController

	// Let's create a new instance of the controller
	$nelioab_controller = new NelioABController();
	if ( !is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) )
		$nelioab_controller->init();

}

