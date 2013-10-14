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


if( !class_exists( NelioABAlternativesExperiment ) ) {

	require_once( NELIOAB_MODELS_DIR . '/experiment.php' );
	require_once( NELIOAB_UTILS_DIR . '/backend.php' );

	class NelioABAlternativesExperiment extends NelioABExperiment {

		private $ori;
		private $goal;
		private $appspot_alternatives;
		private $local_alternatives;

		public function __construct( $id ) {
			parent::__construct();
			$this->id = $id;
			$this->clear();
		}

		public function clear() {
			$this->ori = -1;
			$this->goal = -1;
			$this->appspot_alternatives = array();
			$this->local_alternatives = array();
		}

		public function get_alt_type() {
			$post = get_post( $this->ori, ARRAY_A );
			if ( !isset( $post ) )
				return 'page';
			return $post['post_type'];
		}

		public function get_original() {
			return $this->ori;
		}

		public function set_original( $ori ) {
			$this->ori = $ori;
		}

		public function get_conversion_post() {
			return $this->goal;
		}

		public function set_conversion_post( $conversion_post ) {
			$this->goal = $conversion_post;
		}

		public function get_appspot_alternatives() {
			return $this->appspot_alternatives;
		}

		public function set_appspot_alternatives( $alts ) {
			$this->appspot_alternatives = $alts;
		}

		public function get_alternatives() {
			$result = array();

			foreach ( $this->appspot_alternatives as $alt )
				if ( !$alt->was_removed() )
					array_push( $result, $alt );

			foreach ( $this->local_alternatives as $alt )
				if ( !$alt->was_removed() )
					array_push( $result, $alt );

			return $result;
		}

		public function untrash() {
			$this->update_status_and_save( $this->determine_proper_status() );
		}

		public function update_status_and_save( $status ) {
			if ( $this->get_id() < 0 )
				$this->save();

			$this->set_status( $status );

			$url = sprintf(
					NELIOAB_BACKEND_URL . '/altexp/%s',
					$this->get_id()
				);
			
			$body = array(
					'status' => $this->get_status(),
				);

			$result = NelioABBackend::remote_post( $url, $body );
		}

		public function get_local_alternatives() {
			return $this->local_alternatives;
		}

		public function create_empty_alternative( $name, $post_type ) {
			$post = array(
				'post_type'    => $post_type,
				'post_title'   => $name,
				'post_content' => '',
				'post_excerpt' => '',
				'post_status'  => 'draft',
				'post_name'    => 'nelioab_' . rand(1, 10),
			);

			$post_id = wp_insert_post( $post, true );
			$aux = get_post( $post_id, ARRAY_A );
			$aux['post_name'] = 'nelioab_' . $post_id;
			wp_update_post( $aux );

			add_post_meta( $post_id, '_is_nelioab_alternative', 'true' );

			$alt = new NelioABAlternative();
			$alt->set_name( $name );
			$alt->set_post_id( $post_id );

			$this->add_local_alternative( $alt );
		}

		public function create_alternative_copying_content( $name, $src_post_id, $copy_metadata ) {
			require_once( NELIOAB_UTILS_DIR . '/wp-helper.php' );
			require_once( NELIOAB_MODELS_DIR . '/settings.php' );

			$src_post = get_post( $src_post_id, ARRAY_A );

			$src_post['ID']          = null;
			$src_post['post_status'] = 'draft';
			$src_post['post_name']   = 'nelioab_' . rand(1, 10);

			$new_post_id = wp_insert_post( $src_post, true );
			$aux = get_post( $new_post_id, ARRAY_A );
			$aux['post_name'] = 'nelioab_' . $new_post_id;
			wp_update_post( $aux );

			add_post_meta( $new_post_id, '_is_nelioab_alternative', 'true' );
			add_post_meta( $new_post_id, '_is_nelioab_metadata_duplicated', $copy_metadata );
			if ( $copy_metadata )
				NelioABWpHelper::copy_meta_info( $src_post_id, $new_post_id );

			$alt = new NelioABAlternative();
			$alt->set_name( $name );
			$alt->set_post_id( $new_post_id );

			$this->add_local_alternative( $alt );
		}

		public function add_appspot_alternative( $alt ) {
			array_push( $this->appspot_alternatives, $alt );
		}

		public function add_local_alternative( $alt ) {
			$new_id = count( $this->local_alternatives ) + 1;
			$alt->set_id( -$new_id );
			array_push( $this->local_alternatives, $alt );
		}

		public function encode_appspot_alternatives() {
			$aux = array();
			foreach ( $this->get_appspot_alternatives() as $alt )
				array_push( $aux, $alt->json() );
			return base64_encode( json_encode( $aux ) );
		}

		public function load_encoded_appspot_alternatives( $input ) {
			$data = json_decode( base64_decode( $input ) );
			foreach( $data as $json_alt ) {
				$alt = new NelioABAlternative();
				$alt->load_json( $json_alt );
				array_push( $this->appspot_alternatives, $alt );
			}
		}

		public function encode_local_alternatives() {
			$aux = array();
			foreach ( $this->get_local_alternatives() as $alt )
				array_push( $aux, $alt->json() );
			return base64_encode( json_encode( $aux ) );
		}

		public function load_encoded_local_alternatives( $input ) {
			$data = json_decode( base64_decode( $input ) );
			foreach( $data as $json_alt ) {
				$alt = new NelioABAlternative();
				$alt->load_json( $json_alt );
				array_push( $this->local_alternatives, $alt );
			}
		}

		public function remove_alternative_by_id( $id ) {
			foreach ( $this->get_alternatives() as $alt ) {
				if ( $alt->get_id() == $id ) {
					$alt->mark_as_removed();
					return;
				}
			}
		}

		public function get_results() {
			$results = new NelioABAltExpResults();
			
			$url = sprintf(
				NELIOAB_BACKEND_URL . '/altexp/%s/result',
				$this->get_id()
			);

			$json_data = null;
			$json_data = NelioABBackend::remote_get( $url );
			$json_data = json_decode( $json_data['body'], true );

			$results->set_total_visitors( $json_data['totalVisitors'] );
			$results->set_total_conversions( $json_data['totalConversions'] );
			$results->set_total_conversion_rate( $json_data['totalConversionRate'] );
			$results->set_visitors_history( $json_data['historyVisitors'] );
			$results->set_conversions_history( $json_data['historyConversions'] );
			$results->set_first_update( $json_data['firstUpdate'] );
			$results->set_last_update( $json_data['lastUpdate'] );

			$alt_res = new NelioABAlternativeResults( true ); // Original
			$alt_res->set_name( __( 'Original', 'nelioab' ) );
			$alt_res->set_post_id( $this->get_original() );
			$alt_res->set_num_of_visitors( $json_data['original']['visits'] );
			$alt_res->set_num_of_conversions( $json_data['original']['conversions'] );
			$alt_res->set_conversion_rate( $json_data['original']['conversionRate'] );
			$results->add_alternative_results( $alt_res );

			$visitors_alt           = $json_data['visitorsAlt'];
			$conversions_alt        = $json_data['conversionsAlt'];
			$conversion_rate_alt    = $json_data['conversionRateAlt'];
			$improvement_factor_alt = $json_data['improvementAlt'];
			if ( is_array( $json_data['alternatives'] ) ) {
				foreach ( $json_data['alternatives'] as $json_alt ) {
					$alt_res = new NelioABAlternativeResults();

					$alternative = null;
					foreach ( $this->get_alternatives() as $alt )
						if ( $alt->get_post_id() == $json_alt['name'] )
							$alternative = $alt;

					if ( $alternative == null )
						continue;
	
					$alt_res->set_name( $alternative->get_name() );
					$alt_res->set_post_id( $json_alt['name'] );
					$alt_res->set_num_of_visitors( $json_alt['visits'] );
					$alt_res->set_num_of_conversions( $json_alt['conversions'] );
					$alt_res->set_conversion_rate( $json_alt['conversionRate'] );
					$alt_res->set_improvement_factor( $json_alt['improvementFactor'] );

					$results->add_alternative_results( $alt_res );
				}
			}

			if ( is_array( $json_data['gtestStatistics'] ) ) {
				foreach ( $json_data['gtestStatistics'] as $stats ) {
					$g = new NelioABGStats( $stats['message'], $this->get_original() );
					$g->set_min( $stats['minVersion'] );
					$g->set_max( $stats['maxVersion'] );
					$g->set_gtest( $stats['gtest'] );
					$g->set_pvalue( $stats['pvalue'] );
					$g->set_certainty( $stats['certainty'] );

					$g->set_min_name( __( 'Unknown', 'nelioab' ) );
					$g->set_max_name( __( 'Unknown', 'nelioab' ) );
					foreach( $this->get_alternatives() as $alt ) {
						if ( $alt->get_post_id() == $stats['minVersion'] )
							$g->set_min_name( $alt->get_name() );
						if ( $alt->get_post_id() == $stats['maxVersion'] )
							$g->set_max_name( $alt->get_name() );
					}
					if ( $this->get_original() == $stats['minVersion'] )
						$g->set_min_name( __( 'Original', 'nelioab' ) );
					if ( $this->get_original() == $stats['maxVersion'] )
						$g->set_max_name( __( 'Original', 'nelioab' ) );

					$results->add_gstat( $g );
				}
			}

			return $results;
		}

		protected function determine_proper_status() {
			if ( count( $this->get_alternatives() ) <= 0 )
				return NelioABExperimentStatus::DRAFT;

			if ( $this->get_original() < 0 )
				return NelioABExperimentStatus::DRAFT;

			if ( $this->get_conversion_post() < 0 )
				return NelioABExperimentStatus::DRAFT;

			return NelioABExperimentStatus::READY;
		}

		public function save() {
			require_once( NELIOAB_MODELS_DIR . '/settings.php' );

			// 1. UPDATE OR CREATE THE EXPERIMENT
			$url = '';
			if ( $this->get_id() < 0 ) {
				$url = sprintf(
					NELIOAB_BACKEND_URL . '/site/%s/altexp',
					NelioABSettings::get_site_id()
				);
			}
			else {
				$url = sprintf(
					NELIOAB_BACKEND_URL . '/altexp/%s',
					$this->get_id()
				);
			}

			$this->set_status( $this->determine_proper_status() );

			$body = array(
				'name'           => $this->get_name(),
				'description'    => $this->get_description(),
				'originalPage'   => $this->get_original(),
				'conversionPage' => $this->get_conversion_post(),
				'status'         => $this->get_status(),
			);

			$result = NelioABBackend::remote_post( $url, $body );

			$exp_id = $this->get_id();
			if ( $exp_id < 0 ) {
				if ( is_wp_error( $result ) )
					return;
				$json = json_decode( $result['body'] );
				$exp_id = $json->key->id;
				$this->id = $exp_id;
			}


			// 2. UPDATE THE ALTERNATIVES
			// -------------------------------------------------------------------------

			// 2.1. UPDATE CHANGES ON ALREADY EXISTING APPSPOT ALTERNATIVES
			foreach ( $this->get_appspot_alternatives() as $alt ) {
				if ( $alt->was_removed() || !$alt->is_dirty() )
					continue;

				$body = array( 'name' => $alt->get_name() );
				$result = NelioABBackend::remote_post(
					sprintf( NELIOAB_BACKEND_URL . '/alternative/%s', $alt->get_id() ),
					$body );
			}

			// 2.2. REMOVE FROM APPSPOT THE REMOVED ALTERNATIVES
			foreach ( $this->get_appspot_alternatives() as $alt ) {
				if ( !$alt->was_removed() )
					continue;

				$url = sprintf(
					NELIOAB_BACKEND_URL . '/wp/delete/alternative/%s',
					$alt->get_id()
				);

				$result = NelioABBackend::remote_post( $url );
			}


			// 2.3. CREATE LOCAL ALTERNATIVES IN APPSPOT
			foreach ( $this->get_local_alternatives() as $alt ) {
				if ( $alt->was_removed() )
					continue;

				$body = array(
					'name' => $alt->get_name(),
					'page' => $alt->get_post_id(),
				);

				try {
					$result = NelioABBackend::remote_post(
						sprintf( NELIOAB_BACKEND_URL . '/altexp/%s/alternative', $exp_id ),
						$body );
				}
				catch ( Exception $e ) {
					// If I could not add an alternative... remove the associated page
					wp_delete_post( $alt->get_post_id() );
				}

			}


			// 2.3 REMOVE THE PAGES THAT BELONGED TO AN ALTERNATIVE THAT HAS BEEN DELETED
			$all_alternatives = array_merge(
					$this->get_appspot_alternatives(),
					$this->get_local_alternatives()
				);
			foreach ( $all_alternatives as $alt ) {
				if ( $alt->was_removed() ) {
					// Delete permanently (skipping Trash)
					wp_delete_post( $alt->get_post_id(), true );
				}
			}

			// 2.4 SET META "_is_nelioab_alternative" WITH THE ID OF THE EXPERIMENT
			foreach ( $this->get_alternatives() as $alt ) {
				$value = $this->get_id() . ',' . $this->get_status();
				update_post_meta( $alt->get_post_id(), "_is_nelioab_alternative", $value );
			}
		}

		public function remove() {
			// 1. For each alternative, we first remove its associated page
			foreach ( $this->get_alternatives() as $alt )
				wp_delete_post( $alt->get_post_id(), true );

			// 2. We remove the experiment itself
			$url = sprintf(
				NELIOAB_BACKEND_URL . '/wp/delete/altexp/%s',
				$this->get_id()
			);

			$result = NelioABBackend::remote_post( $url );
		}

		public function discard_changes() {
			foreach ( $this->get_local_alternatives() as $alt ) {
				// Delete permanently (skipping Trash)
				wp_delete_post( $alt->get_post_id(), true );
			} 
		}

		public function start() {
			$ori = get_post( $this->get_original() );
			if ( $ori ) {
				foreach ( $this->get_alternatives() as $alt ) {
					$alt_post = get_post( $alt->get_post_id() );
					if ( $alt_post ) {
						$alt_post->comment_status = $ori->comment_status;
						wp_update_post( $alt_post );
					}
				}
			}
			parent::start();
		}

		public function set_status( $status ) {
			parent::set_status( $status );
			foreach ( $this->get_alternatives() as $alt ) {
				$value = $this->get_id() . ',' . $this->get_status();
				update_post_meta( $alt->get_post_id(), "_is_nelioab_alternative", $value );
			}
		}

	}//NelioABAlternativesExperiment

}

if( !class_exists( NelioABAlternative ) ) {

	class NelioABAlternative {
		private $id;
		private $name;
		private $post_id;
		private $was_removed;
		private $is_dirty;

		public function __construct( $id = -1 ) {
			$this->id          = $id;
			$this->name        = '';
			$this->post_id     = -1;
			$this->was_removed = false;
			$this->is_dirty    = false;
		}

		public function set_id( $id ) {
			$this->id = $id;
		}

		public function get_id() {
			return $this->id;
		}

		public function set_name( $name ) {
			$this->name = $name;
		}

		public function get_name() {
			return $this->name;
		}

		public function set_post_id( $post_id ) {
			$this->post_id = $post_id;
		}

		public function get_post_id() {
			return $this->post_id;
		}

		public function mark_as_removed() {
			$this->was_removed = true;
		}

		public function was_removed() {
			return $this->was_removed;
		}

		public function mark_as_dirty() {
			$this->is_dirty = true;
		}

		public function is_dirty() {
			return $this->is_dirty;
		}

		public function json() {
			return array(
				'id'            => $this->id,
				'name'          => $this->name,
				'post_id'       => $this->post_id,
				'was_removed'   => $this->was_removed,
				'is_dirty'      => $this->is_dirty,
			);
		}

		public function load_json( $json ) {
			$this->name          = $json->name;
			$this->post_id       = $json->post_id;
			$this->id            = $json->id;
			$this->was_removed   = $json->was_removed;
			$this->is_dirty      = $json->is_dirty;
		}

	}//NelioABAlternative

}

if( !class_exists( NelioABAltExpResults ) ) {

	class NelioABAltExpResults {

		private $total_visitors;
		private $total_conversions;
		private $total_conversion_rate;
		private $alternatives;
		private $gstats;
		private $visitors_history;
		private $conversions_history;
		private $first_update;
		private $last_update;

		public function __construct() {
			$this->total_visitors        = 0;
			$this->total_conversions     = 0;
			$this->total_conversion_rate = 0;
			$this->alternatives          = array();
			$this->gstats                = array();
		}

		public function add_alternative_results( $alternative_results ) {
			array_push( $this->alternatives, $alternative_results );
		}

		public function get_alternative_results() {
			return $this->alternatives;
		}

		public function set_total_visitors( $total_visitors ) {
			$this->total_visitors = $total_visitors;
		}

		public function get_total_visitors() {
			return $this->total_visitors;
		}

		public function set_total_conversions( $total_conversions ) {
			$this->total_conversions = $total_conversions;
		}

		public function get_total_conversions() {
			return $this->total_conversions;
		}

		public function set_total_conversion_rate( $total_conversion_rate ) {
			$this->total_conversion_rate = $total_conversion_rate;
		}

		public function get_total_conversion_rate() {
			return $this->total_conversion_rate;
		}

		public function add_gstat( $g ) {
			array_push( $this->gstats, $g );
		}

		public function get_gstats() {
			return $this->gstats;
		}

		public function set_visitors_history( $visitors_history ) {
			$this->visitors_history = $visitors_history;
		}

		public function get_visitors_history() {
			return $this->visitors_history;
		}

		public function set_conversions_history( $conversions_history ) {
			$this->conversions_history = $conversions_history;
		}

		public function get_conversions_history() {
			return $this->conversions_history;
		}

		public function set_first_update( $first_update ) {
			$this->first_update = $first_update;
		}

		public function get_first_update() {
			return $this->first_update;
		}

		public function set_last_update( $last_update ) {
			$this->last_update = $last_update;
		}

		public function get_last_update() {
			return $this->last_update;
		}

	}//NelioABAltExpResults 

}

if( !class_exists( NelioABAlternativeResults ) ) {

	class NelioABAlternativeResults {

		private $name;
		private $post_id;
		private $num_of_visitors;
		private $num_of_conversions;
		private $conversion_rate;
		private $improvement_factor;
		private $is_original;

		public function __construct( $is_original = 0 ) {
			$this->is_original = $is_original;
		}

		public function set_name( $name ) {
			$this->name = $name;
		}

		public function get_name() {
			return $this->name;
		}

		public function set_post_id( $post_id ) {
			$this->post_id = $post_id;;
		}

		public function get_post_id() {
			return $this->post_id;
		}

		public function set_num_of_visitors( $num_of_visitors ) {
			$this->num_of_visitors = $num_of_visitors;
		}

		public function get_num_of_visitors() {
			return $this->num_of_visitors;
		}

		public function set_num_of_conversions( $num_of_conversions ) {
			$this->num_of_conversions = $num_of_conversions;
		}

		public function get_num_of_conversions() {
			return $this->num_of_conversions;
		}

		public function set_conversion_rate( $conversion_rate ) {
			$this->conversion_rate = $conversion_rate;
		}

		public function get_conversion_rate() {
			return number_format( floatval( $this->conversion_rate ), 2 );
		}

		public function set_improvement_factor( $improvement_factor ) {
			$this->improvement_factor = $improvement_factor;
		}

		public function get_improvement_factor() {
			if ( $this->is_original() )
				return '-';
			return number_format( floatval( $this->improvement_factor ), 2 );
		}

		public function get_conversion_rate_text() {
			return  $this->get_conversion_rate() . ' %';
		}

		public function get_improvement_factor_text() {
			if ( $this->is_original() )
				return '-';
			return  $this->get_improvement_factor() . ' %';
		}

		public function is_original() {
			return $this->is_original;
		}

	}//NelioABAlternativeResults 

}

if( !class_exists( NelioABGStats ) ) {

	class NelioABGStats {
		const UNKNOWN           = 1;
		const NO_CLEAR_WINNER   = 2;
		const NOT_ENOUGH_VISITS = 3;
		const DROP_VERSION      = 4;
		const WINNER            = 5;

		private $type;
		private $original;
		private $min;
		private $min_name;
		private $max;
		private $max_name;
		private $gtest;
		private $pvalue;
		private $certainty;

		public function __construct( $type, $original ) {
			$this->type     = NelioABGStats::UNKNOWN;
			$this->original = $original;

			if ( $type == 'NO_CLEAR_WINNER' )
				$this->type = NelioABGStats::NO_CLEAR_WINNER;
			else if ( $type == 'NOT_ENOUGH_VISITS' )
				$this->type = NelioABGStats::NOT_ENOUGH_VISITS;
			else if ( $type == 'DROP_VERSION' )
				$this->type = NelioABGStats::DROP_VERSION;
			else if ( $type == 'WINNER' )
				$this->type = NelioABGStats::WINNER;
		}

		public function is_original_the_best() {
			return $this->original == $this->max;
		}

		public function get_type() {
			return $this->type;
		}

		public function set_min( $min ) {
			$this->min = $min;
		}

		public function get_min() {
			return $this->min;
		}

		public function set_min_name( $min_name ) {
			$this->min_name = $min_name;
		}

		public function set_max( $max ) {
			$this->max = $max;
		}

		public function get_max() {
			return $this->max;
		}

		public function set_max_name( $max_name ) {
			$this->max_name = $max_name;
		}

		public function set_gtest( $gtest ) {
			$this->gtest = $gtest;
		}

		public function set_pvalue( $pvalue ) {
			$this->pvalue = $pvalue;
		}

		public function set_certainty( $certainty ) {
			$this->certainty = $certainty;
		}

		public function get_certainty() {
			return $this->certainty;
		}

		public function to_string() {
			switch( $this->type ) {
			case NelioABGStats::NO_CLEAR_WINNER:
				return __( 'No alternative is better than the rest.', 'nelioab' );
			case NelioABGStats::NOT_ENOUGH_VISITS:
				return __( 'No statistic results available due to too few visits.', 'nelioab' );
			case NelioABGStats::DROP_VERSION:
				return sprintf(
					__( '«%1$s» beats «%2$s» with a %3$s%% confidence.',
						'nelioab' ),
						$this->max_name,
						$this->min_name,
						$this->certainty
					);
			case NelioABGStats::WINNER:
					$string = __( '«%1$s» beats «%2$s» with a %3$s%% confidence. Therefore, ' .
						'we can conclude that «%1$s» is the best alternative, but with a low ' .
						'confidence value <small>(<a href="http://wp-abtesting.com/faqs/what-' .
						'is-the-meaning-of-the-confidence-value-you-provide-together-with-' .
						'the-results/">why is this important?</a>)</small>.',
						'nelioab' );
					$aux = $this->certainty;
					if ( is_string( $aux ) )
						$aux = floatval( $aux );
					if ( $aux >= 90 ) {
						$string = __( '«%1$s» beats «%2$s» with a %3$s%% confidence. Therefore, ' .
							'we can conclude that «%1$s» is the best alternative.',
							'nelioab' );
					}
					return sprintf( $string,
						$this->max_name,
						$this->min_name,
						$this->certainty
					);
		default:
				return __( 'There was an error while processing the statistics.', 'nelioab' );
			}
		}

	}//NelioABGStats
}


?>
