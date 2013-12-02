<?php
/**
 * Copyright 2013 Nelio Software S.L.
 * This script is distributed under the terms of the GNU General Public License.
 *
 * This script is free software: you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation, either version 3 of the License.
 * This script is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program.  If not, see <http://www.gnu.org/licenses/>.
 */


if ( !class_exists( 'NelioABTitleAltExpProgressPageController' ) ) {

	require_once( NELIOAB_MODELS_DIR . '/experiment.php' );
	require_once( NELIOAB_MODELS_DIR . '/experiments-manager.php' );

	require_once( NELIOAB_ADMIN_DIR . '/views/progress/title-alt-exp-progress-page.php' );
	require_once( NELIOAB_ADMIN_DIR . '/progress/alt-exp-progress-super-controller.php' );

	class NelioABTitleAltExpProgressPageController extends NelioABAltExpProgressSuperController {

		public static function build() {
			$title = __( 'Results of the Experiment', 'nelioab' );
			$view  = new NelioABTitleAltExpProgressPage( $title );

			if ( isset( $_GET['id'] ) )
				// The ID of the experiment to which the action applies
				$view->keep_request_param( 'exp_id', $_GET['id'] );

			if ( isset( $_GET['exp_type'] ) )
				$view->keep_request_param( 'exp_type', $_GET['exp_type'] );

			if ( isset( $_GET['actual_exp_type'] ) )
				$view->keep_request_param( 'exp_type', $_GET['actual_exp_type'] );

			if ( isset( $_GET['goal'] ) )
				$view->keep_request_param( 'goal', $_GET['goal'] );

			$view->get_content_with_ajax_and_render( __FILE__, __CLASS__ );
		}

		public static function generate_html_content() {

			global $nelioab_admin_controller;
			if ( isset( $nelioab_admin_controller->data ) ) {
				$exp    = $nelioab_admin_controller->data;
				$exp_id = $exp->get_id();
			}
			else {
				$exp_id = -1;
				if ( isset( $_REQUEST['exp_id'] ) )
					$exp_id = $_REQUEST['exp_id'];

				$exp_type = -1;
				if ( isset( $_POST['exp_type'] ) )
					$exp_type = $_POST['exp_type'];

				$mgr = new NelioABExperimentsManager();
				$exp = null;

				try {
					$exp = $mgr->get_experiment_by_id( $exp_id, $exp_type );
				}
				catch ( Exception $e ) {
					require_once( NELIOAB_ADMIN_DIR . '/error-controller.php' );
					NelioABErrorController::build( $e );
				}
			}

			$title = __( 'Results of the Experiment', 'nelioab' );
			$view  = new NelioABTitleAltExpProgressPage( $title );
			$view->set_experiment( $exp );

			$goals = $exp->get_goals();
			$view->set_goals( $goals );

			$goal_id = -1;
			if ( isset( $_REQUEST['goal'] ) )
				$goal_id = $_REQUEST['goal'];
			$view->set_current_selected_goal( $goal_id );

			$view->render_content();

			die();
		}

		public function apply_alternative() {
			if ( isset( $_POST['original'] ) && isset( $_POST['alternative'] ) ) {
				$ori_id    = $_POST['original'];
				$alt_title = urldecode( $_POST['alternative'] );

				$post = get_post( $ori_id, ARRAY_A );
				if ( $post ) {
					$post['post_title'] = $alt_title;
					wp_update_post( $post );
					echo 'OK';
					die();
				}
			}
		}

	}//NelioABTitleAltExpProgressPageController

}

$aux = new NelioABTitleAltExpProgressPageController();
$aux->manage_actions();

?>
