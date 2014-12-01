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

if( !class_exists( 'NelioABExperimentsManager' ) ) {

	require_once( NELIOAB_UTILS_DIR . '/data-manager.php' );
	require_once( NELIOAB_MODELS_DIR . '/quick-experiment.php' );
	require_once( NELIOAB_MODELS_DIR . '/alternatives/post-alternative-experiment.php' );
	require_once( NELIOAB_MODELS_DIR . '/alternatives/headline-alternative-experiment.php' );
	require_once( NELIOAB_MODELS_DIR . '/alternatives/css-alternative-experiment.php' );
	require_once( NELIOAB_MODELS_DIR . '/alternatives/theme-alternative-experiment.php' );
	require_once( NELIOAB_MODELS_DIR . '/alternatives/widget-alternative-experiment.php' );
	require_once( NELIOAB_MODELS_DIR . '/heatmap-experiment.php' );

	class NelioABExperimentsManager implements iNelioABDataManager {

		private static $running_experiments = NULL;
		private static $running_headline_alt_exps = NULL;

		private $experiments;
		private $are_experiments_loaded;

		public function __construct() {
			$this->experiments            = array();
			$this->are_experiments_loaded = false;
		}

		public function get_experiments() {
			if ( $this->are_experiments_loaded )
				return $this->experiments;

			require_once( NELIOAB_UTILS_DIR . '/backend.php' );
			$json_data = NelioABBackend::remote_get( sprintf(
				NELIOAB_BACKEND_URL . '/site/%s/exp',
				NelioABAccountSettings::get_site_id()
			) );

			$json_data = json_decode( $json_data['body'] );
			$aux = array();
			if ( isset( $json_data->items ) ) {
				foreach ( $json_data->items as $json_exp ) {
					$exp = new NelioABQuickExperiment( $json_exp->key->id );
					$exp->set_type_using_text( $json_exp->kind );
					$exp->set_name( $json_exp->name );
					$exp->set_status( $json_exp->status );
					if ( isset( $json_exp->originalPost ) )
						$exp->set_related_post_id( $json_exp->originalPost );
					if ( isset( $json_exp->description ) )
						$exp->set_description( $json_exp->description );
					if ( isset( $json_exp->creation ) ) {
						try { $exp->set_creation_date( $json_exp->creation ); }
						catch ( Exception $exception ) {}
					}
					if ( isset( $json_exp->start ) ) {
						try { $exp->set_start_date( $json_exp->start ); }
						catch ( Exception $exception ) {}
					}
					if ( isset( $json_exp->finalization ) ) {
						try { $exp->set_end_date( $json_exp->finalization ); }
						catch ( Exception $exception ) {}
					}
					if ( isset( $json_exp->daysFinished ) ) {
						try { $exp->set_days_since_finalization( $json_exp->daysFinished ); }
						catch ( Exception $exception ) {}
					}
					array_push( $aux, $exp );
				}
			}

			usort( $aux, array( 'NelioABExperiment', 'cmp_obj' ) );

			$this->experiments = $aux;
			$this->are_experiments_loaded = true;
			return $this->experiments;
		}

		public function get_experiment_by_id( $id, $type ) {

			require_once( NELIOAB_UTILS_DIR . '/backend.php' );
			require_once( NELIOAB_MODELS_DIR . '/goals/goals-manager.php' );

			// PAGE OR POST ALTERNATIVE EXPERIMENT
			switch( $type ) {
				case NelioABExperiment::POST_ALT_EXP:
				case NelioABExperiment::PAGE_ALT_EXP:
				case NelioABExperiment::PAGE_OR_POST_ALT_EXP:
					return NelioABPostAlternativeExperiment::load( $id );

				case NelioABExperiment::HEADLINE_ALT_EXP:
					return NelioABHeadlineAlternativeExperiment::load( $id );

				case NelioABExperiment::THEME_ALT_EXP:
					return NelioABThemeAlternativeExperiment::load( $id );

				case NelioABExperiment::CSS_ALT_EXP:
					return NelioABCssAlternativeExperiment::load( $id );

				case NelioABExperiment::WIDGET_ALT_EXP:
					return NelioABWidgetAlternativeExperiment::load( $id );

				case NelioABExperiment::HEATMAP_EXP:
					return NelioABHeatmapExperiment::load( $id );

				default: // NO EXPERIMENT FOUND
					$err = NelioABErrCodes::EXPERIMENT_ID_NOT_FOUND;
					throw new Exception( NelioABErrCodes::to_string( $err ), $err );
			}
		}

		public function remove_experiment_by_id( $id, $type ) {
			$exp = $this->get_experiment_by_id( $id, $type );
			$exp->remove();
		}

		public function list_elements() {
			return $this->get_experiments();
		}

		public static function reset_running_experiments_cache() {
			update_option( 'nelioab_running_experiments', array() );
			update_option( 'nelioab_running_experiments_date', 0 );
		}

		public static function update_running_experiments_cache( $force_update = false, $running_exps = false ) {
			if ( $force_update )
				update_option( 'nelioab_running_experiments_date', 0 );

			$last_update = get_option( 'nelioab_running_experiments_date', 0 );
			$now = time();
			// If the last update was less than fifteen minutes ago, it's OK
			if ( $now - $last_update < 900 )
				return;

			// If we are forcing the update, or the last update is too old, we
			// perform a new update.
			try {
				$result = NelioABExperimentsManager::get_running_experiments();
				update_option( 'nelioab_running_experiments', $result );
				update_option( 'nelioab_running_experiments_date', $now );

				// UPDATE TO VERSION 1.2
				update_option( 'nelioab_running_experiments_cache_uses_objects', true );
			}
			catch ( Exception $e ) {
				// If we could not retrieve the running experiments, we cannot update
				// the cache...
			}
		}

		public static function get_running_experiments_from_cache() {
			require_once( NELIOAB_MODELS_DIR . '/goals/alternative-experiment-goal.php' );
			if ( self::$running_experiments == NULL ) {
				// UPDATE TO VERSION 1.2: make sure we have objects...
				if ( !get_option( 'nelioab_running_experiments_cache_uses_objects', false ) )
					NelioABExperimentsManager::update_running_experiments_cache( true );
				self::$running_experiments = get_option( 'nelioab_running_experiments', array() );
			}
			return self::$running_experiments;
		}

		private static function get_running_experiments() {
			$mgr = new NelioABExperimentsManager();
			$experiments = $mgr->get_experiments();

			$result = array();
			foreach ( $experiments as $exp_short ) {
				if ( $exp_short->get_status() != NelioABExperimentStatus::RUNNING )
					continue;

				$exp = $mgr->get_experiment_by_id( $exp_short->get_id(), $exp_short->get_type() );
				array_push( $result, $exp );
			}

			return $result;
		}

		public static function get_running_headline_experiments_from_cache() {
			if ( NULL == self::$running_headline_alt_exps ) {
				self::$running_headline_alt_exps = array();
				require_once( NELIOAB_MODELS_DIR . '/experiments-manager.php' );
				$running_exps = NelioABExperimentsManager::get_running_experiments_from_cache();
				foreach ( $running_exps as $exp )
					if ( $exp->get_type() == NelioABExperiment::HEADLINE_ALT_EXP )
						array_push( self::$running_headline_alt_exps, $exp );
			}
			return self::$running_headline_alt_exps;
		}

		public static function get_running_experiments_summary() {
			require_once( NELIOAB_UTILS_DIR . '/backend.php' );
			$json_data = NelioABBackend::remote_get( sprintf(
				NELIOAB_BACKEND_URL . '/site/%s/exp/summary',
				NelioABAccountSettings::get_site_id()
			) );

			// Including types of experiments...
			require_once( NELIOAB_MODELS_DIR . '/summaries/alt-exp-summary.php' );
			require_once( NELIOAB_MODELS_DIR . '/summaries/heatmap-exp-summary.php' );

			$json_data = json_decode( $json_data['body'] );
			$result = array();
			if ( isset( $json_data->items ) ) {
				foreach ( $json_data->items as $item ) {
					$exp = false;
					switch ( $item->kind ) {
						case NelioABExperiment::HEATMAP_EXP_STR:
							$exp = new NelioABHeatmapExpSummary( $item->key->id );
							break;
						default:
							$exp = new NelioABAltExpSummary( $item->key->id );
					}
					if ( $exp ) {
						$exp->load_json4ae( $item );
						array_push( $result, $exp );
					}
				}
			}
			return $result;
		}

	}//NelioABExperimentsManager

}

