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


if ( !class_exists( 'NelioABWidgetAlternativeExperiment' ) ) {

	require_once( NELIOAB_MODELS_DIR . '/alternatives/global-alternative-experiment.php' );

	/**
	 * PHPDOC
	 *
	 * @package \NelioABTesting\Models\Experiments\AB
	 * @since PHPDOC
	 */
	class NelioABWidgetAlternativeExperiment extends NelioABGlobalAlternativeExperiment {

		/**
		 * PHPDOC
		 *
		 * @since PHPDOC
		 * @var array
		 */
		private $new_ids;


		/**
		 * PHPDOC
		 *
		 * @since PHPDOC
		 * @var array
		 */
		private $original_appspot_widget_set;


		// @Override
		public function clear() {
			parent::clear();
			$this->set_type( NelioABExperiment::WIDGET_ALT_EXP );
			$this->new_ids = array();
			$this->original_appspot_widget_set = $this->create_widget_set_alternative( 'FakeOriginalWidget' );
		}


		// @Override
		public function get_original() {
			return $this->original_appspot_widget_set;
		}


		// @Override
		public function get_originals_id() {
			/** @var NelioABAlternative $aux */
			$aux = $this->get_original();
			return $aux->get_id();
		}


		// @Override
		public function set_appspot_alternatives( $alts ) {
			$aux = array();
			if ( count( $alts ) > 0 ) {
				$this->original_appspot_widget_set = $alts[0];
				for ( $i = 1; $i < count( $alts ); $i++ )
					array_push( $aux, $alts[$i] );
			}
			parent::set_appspot_alternatives( $aux );
		}


		/**
		 * PHPDOC
		 *
		 * @param string $name PHPDOC
		 *
		 * @return NelioABAlternative PHPDOC
		 *
		 * @since PHPDOC
		 */
		public function create_widget_set_alternative( $name ) {
			$alts = $this->get_alternatives();
			$fake_post_id = -1;
			foreach ( $alts as $aux ) {
				/** @var NelioABAlternative $aux */
				if ( $aux->get_id() <= $fake_post_id )
					$fake_post_id = $aux->get_id() - 1;
			}
			$alt = new NelioABAlternative();
			$alt->set_id( $fake_post_id );
			$alt->set_name( $name );
			$alt->set_value( '' );
			return $alt;
		}


		// @Override
		protected function determine_proper_status() {
			if ( count( $this->get_alternatives() ) <= 0 )
				return NelioABExperiment::STATUS_DRAFT;
			return parent::determine_proper_status();
		}


		// @Override
		public function save() {
			// 0. WE CHECK WHETHER THE EXPERIMENT IS COMPLETELY NEW OR NOT
			// -------------------------------------------------------------------------
			$is_new = $this->get_id() < 0;


			// 1. SAVE THE EXPERIMENT AND ITS GOALS
			// -------------------------------------------------------------------------
			$exp_id = parent::save();


			// 2. UPDATE THE ALTERNATIVES
			// -------------------------------------------------------------------------

			// 2.0. FIRST OF ALL, WE CREATE A FAKE ORIGINAL FOR NEW EXPERIMENTS

			/** @var NelioABAlternative $original */
			$original = $this->get_original();
			if ( $is_new && $original->get_id() < 0 ) {
				$body = array(
					'name'    => $original->get_name(),
					'content' => '',
					'kind'    => NelioABExperiment::get_textual_type(),
				);
				try {
					/** @var int $result */
					$result = NelioABBackend::remote_post(
						sprintf( NELIOAB_BACKEND_URL . '/exp/global/%s/alternative', $exp_id ),
						$body );
					$original->set_id( $result );
				} catch ( Exception $e ) {}
			}

			// 2.1. UPDATE CHANGES ON ALREADY EXISTING APPSPOT ALTERNATIVES
			foreach ( $this->get_appspot_alternatives() as $alt ) {
				/** @var NelioABAlternative $alt */
				if ( $alt->was_removed() || !$alt->is_dirty() )
					continue;

				$body = array( 'name' => $alt->get_name() );
				NelioABBackend::remote_post(
					sprintf( NELIOAB_BACKEND_URL . '/alternative/%s/update', $alt->get_id() ),
					$body );
			}

			// 2.2. REMOVE FROM APPSPOT THE REMOVED ALTERNATIVES
			foreach ( $this->get_appspot_alternatives() as $alt ) {
				/** @var NelioABAlternative $alt */
				if ( !$alt->was_removed() )
					continue;

				$url = sprintf(
					NELIOAB_BACKEND_URL . '/alternative/%s/delete',
					$alt->get_id()
				);

				try {
					NelioABBackend::remote_post( $url );
				} catch ( Exception $e ) {}
			}

			// 2.3. CREATE LOCAL ALTERNATIVES IN APPSPOT
			$this->new_ids = array();
			foreach ( $this->get_local_alternatives() as $alt ) {
				/** @var NelioABAlternative $alt */
				if ( $alt->was_removed() )
					continue;
				$body = array(
					'name'    => $alt->get_name(),
					'content' => '',
					'kind'    => NelioABExperiment::get_textual_type(),
				);

				try {
					/** @var object|array $result */
					$result = NelioABBackend::remote_post(
						sprintf( NELIOAB_BACKEND_URL . '/exp/global/%s/alternative', $exp_id ),
						$body );
					$result = json_decode( $result['body'] );
					$this->new_ids[$alt->get_id()] = $result->key->id;
				}
				catch ( Exception $e ) {
				}
			}

			require_once( NELIOAB_MODELS_DIR . '/experiments-manager.php' );
			NelioABExperimentsManager::update_experiment( $this );
		}


		/**
		 * Returns PHPDOC
		 *
		 * @param int $id PHPDOC
		 *
		 * @return int PHPDOC
		 *
		 * @since PHPDOC
		 */
		public function get_real_id_for_alt( $id ) {
			if ( isset( $this->new_ids[$id] ) )
				return $this->new_ids[$id];
			else
				return $id;
		}


		/**
		 * Returns PHPDOC
		 *
		 * @param int    $alt_id PHPDOC
		 * @param string $name   PHPDOC
		 *
		 * @return void
		 *
		 * @since PHPDOC
		 */
		public static function update_widget_set_alternative( $alt_id, $name ) {
			$body = array(
				'name'    => $name,
			);
			NelioABBackend::remote_post(
				sprintf( NELIOAB_BACKEND_URL . '/alternative/%s/update', $alt_id ),
				$body );
		}


		// @Override
		public function start() {
			parent::start();
			// This fake widget is inserted to make the system believe that there is
			// at least one alternative with a widget that can be "applied" (and, thus,
			// the Apply and Clean buttons in the progress of the experiment make
			// sense).
			$aux = NelioABWidgetExpAdminController::get_widgets_in_experiments();
			NelioABWidgetExpAdminController::link_widget_to_experiment(
				'nelioab-fake-' . $this->get_id(),
				$this->get_id(), 'no-alternative',
				$aux );
			NelioABWidgetExpAdminController::set_widgets_in_experiments( $aux );
		}


		// @Override
		public function duplicate( $new_name ) {
			$id = parent::duplicate( $new_name );
			if ( -1 == $id )
				return $id;

			require_once( NELIOAB_EXP_CONTROLLERS_DIR . '/widget-experiment-controller.php' );
			require_once( NELIOAB_MODELS_DIR . '/experiments-manager.php' );

			/** @var NelioABMenuAlternativeExperiment $exp */
			$exp = NelioABExperimentsManager::get_experiment_by_id( $id, $this->get_type() );

			$alts_src  = $this->get_alternatives();
			$alts_dest = $exp->get_alternatives();
			for ( $i = 0; $i < count( $alts_src ); ++$i ) {
				/** @var NelioABAlternative $alt_src */
				$alt_src  = $alts_src[$i];
				/** @var NelioABAlternative $alt_dest */
				$alt_dest = $alts_dest[$i];
				NelioABWidgetExpAdminController::duplicate_widgets(
					$this->get_id(), $alt_src->get_id(),
					$exp->get_id(), $alt_dest->get_id() );
			}

			return $id;
		}


		// @Implements
		public static function load( $id ) {
			$json_data = NelioABBackend::remote_get( NELIOAB_BACKEND_URL . '/exp/global/' . $id );
			$json_data = json_decode( $json_data['body'] );

			$exp = new NelioABWidgetAlternativeExperiment( $json_data->key->id );
			$exp->set_type_using_text( $json_data->kind );
			$exp->set_name( $json_data->name );
			if ( isset( $json_data->description ) )
				$exp->set_description( $json_data->description );
			$exp->set_status( $json_data->status );
			$exp->set_finalization_mode( $json_data->finalizationMode );
			if ( isset( $json_data->finalizationModeValue ) )
				$exp->set_finalization_value( $json_data->finalizationModeValue );
			if ( isset( $json_data->start ) )
				$exp->set_start_date( $json_data->start );
			if ( isset( $json_data->finalization ) )
				$exp->set_end_date( $json_data->finalization );

			if ( isset( $json_data->goals ) )
				NelioABExperiment::load_goals_from_json( $exp, $json_data->goals );

			$alternatives = array();
			if ( isset( $json_data->alternatives ) ) {
				foreach ( $json_data->alternatives as $json_alt ) {
					$alt = new NelioABAlternative( $json_alt->key->id );
					$alt->set_name( $json_alt->name );
					if ( isset( $json_alt->content ) )
						$alt->set_value( $json_alt->content->value );
					else
						$alt->set_value( '' );
					array_push ( $alternatives, $alt );
				}
			}
			$exp->set_appspot_alternatives( $alternatives );

			return $exp;
		}


		// @Override
		public function remove() {
			// 1. We remove the experiment itself
			parent::remove();
			// 2. And we now remove all the alternative widgets
			require_once( NELIOAB_EXP_CONTROLLERS_DIR . '/widget-experiment-controller.php' );
			NelioABWidgetExpAdminController::clean_widgets_in_experiment( $this->get_id() );
		}

	}//NelioABWidgetAlternativeExperiment

}

