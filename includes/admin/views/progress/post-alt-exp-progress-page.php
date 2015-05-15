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

if ( !class_exists( 'NelioABPostAltExpProgressPage' ) ) {

	require_once( NELIOAB_MODELS_DIR . '/experiment.php' );
	require_once( NELIOAB_ADMIN_DIR . '/views/progress/alt-exp-progress-page.php' );

	class NelioABPostAltExpProgressPage extends NelioABAltExpProgressPage {

		protected $ori;
		protected $post_type;

		public function __construct( $title ) {
			parent::__construct( $title );
			$this->set_icon( 'icon-nelioab' );
			$this->exp          = null;
			$this->results      = null;
			$this->post_type = array(
				'name'     => 'page',
				'singular' => 'Page',
				'plural'   => 'Pages'
			);
		}

		public function set_experiment( $exp ) {
			parent::set_experiment( $exp );

			switch ( $exp->get_post_type() ) {
				case 'page':
					$this->post_type = array(
						'name'     => 'page',
						'singular' => __( 'Page', 'nelioab' ),
						'plural'   => __( 'Pages', 'nelioab' )
					);
					break;
				case 'post';
					$this->post_type = array(
						'name'     => 'post',
						'singular' => __( 'Post', 'nelioab' ),
						'plural'   => __( 'Posts', 'nelioab' )
					);
					break;
				default:
					require_once( NELIOAB_UTILS_DIR . '/wp-helper.php' );
					$ptn = $exp->get_post_type();
					$pt = NelioABWpHelper::get_custom_post_types( $ptn );
					$this->post_type = array(
						'name'     => $pt->name,
						'singular' => __( $pt->labels->singular_name, 'nelioab' ),
						'plural'   => __( $pt->labels->name, 'nelioab' )
					);

			}
		}

		protected function print_experiment_details_title() {
			if ( 'page' == $this->post_type['name'] )
				_e( 'Details of the Page Experiment', 'nelioab' );
			else if ( 'post' == $this->post_type['name'] )
				_e( 'Details of the Post Experiment', 'nelioab' );
			else
				printf( __( 'Details of the %s Post Type Experiment', 'nelioab' ), $this->post_type['singular'] );
		}

		protected function get_original_name() {
			// Original title
			$exp = $this->exp;
			$aux = get_post( $exp->get_originals_id() );
			$this->ori = sprintf( __( 'Unknown (post_id is %s)', 'nelioab' ), $exp->get_originals_id() );
			$this->is_ori_page = true;
			if ( $aux ) {
				$this->ori = trim( $aux->post_title );
				if ( $aux->post_type == 'post' )
					$this->is_ori_page = false;
			}
			return $this->ori;
		}

		protected function get_original_value() {
			return $this->exp->get_originals_id();
		}

		protected function print_js_function_for_post_data_overwriting() { ?>
			function nelioab_confirm_overwriting(id) {
				jQuery("#apply_alternative #alternative").attr("value",id);
				nelioab_show_the_dialog_for_overwriting(id);
			}
			<?php
		}

		protected function print_winner_info() {
			// Winner (if any) details
			$the_winner            = $this->who_wins();
			$the_winner_confidence = $this->get_winning_confidence();

			$exp = $this->exp;
			if ( $exp->get_status() == NelioABExperiment::STATUS_RUNNING ) {
				if ( $the_winner == 0 ) {
					echo '<p><b>' . sprintf( __( 'Right now, no alternative is beating the original %s.', 'nelioab' ), $this->post_type['name'] ) . '</b></p>';
				}
				if ( $the_winner > 0 ) {
					echo '<p><b>' . sprintf( __( 'Right now, alternative %s is better than the original %s.', 'nelioab' ), $the_winner, $this->post_type['name'] ) . '</b></p>';
				}
			}
			else {
				if ( $the_winner == 0 ) {
					echo '<p><b>' . sprintf( __( 'No alternative was better the original %s.', 'nelioab' ), $this->post_type['name'] ) . '</b></p>';
				}
				if ( $the_winner > 0 ) {
					echo '<p><b>' . sprintf( __( 'Alternative %s was better than the original %s.', 'nelioab' ), $the_winner, $this->post_type['name'] ) . '</b></p>';
				}
			}
		}


		protected function print_alternatives_block() {
			echo '<table id="alternatives-in-progress">';
			$this->print_the_original_alternative();
			$this->print_the_real_alternatives();
			echo '</table>';
		}


		private function make_link_for_heatmap( $exp, $id ) {
			include_once( NELIOAB_UTILS_DIR . '/wp-helper.php' );
			$url = sprintf(
				str_replace(
					'https://', 'http://',
					admin_url( 'admin.php?nelioab-page=heatmaps&id=%1$s&exp_type=%2$s&post=%3$s' ) ),
				$exp->get_id(), $exp->get_type(), $id );
			return sprintf( ' <a href="%1$s">%2$s</a>', $url,
				__( 'View Heatmap', 'nelioab' ) );
		}


		private function make_link_for_edit( $id ) {
			$exp = $this->exp;
			return sprintf( ' <a href="javascript:nelioabConfirmEditing(\'%s\',\'dialog\');">%s</a>',
				admin_url( 'post.php?post=' . $id . '&action=edit' ),
				__( 'Edit' ) );
		}


		protected function get_action_links( $exp, $alt_id ) {
			$action_links = array();
			if ( $exp->are_heatmaps_tracked() )
				array_push( $action_links, $this->make_link_for_heatmap( $exp, $alt_id ) );
			switch ( $exp->get_status() ) {
				case NelioABExperiment::STATUS_RUNNING:
					array_push( $action_links, $this->make_link_for_edit( $alt_id ) );
					break;
				case NelioABExperiment::STATUS_FINISHED:
					if ( $alt_id == $exp->get_originals_id() )
						break;
					$aux = sprintf(
						' <a class="apply-link" href="javascript:nelioab_confirm_overwriting(%1$s);">%2$s</a>',
						$alt_id, __( 'Apply', 'nelioab' ) );
					array_push( $action_links, $aux );
					break;
			}
			return $action_links;
		}


		protected function print_the_original_alternative() {
			// THE ORIGINAL
			// -----------------------------------------
			$exp       = $this->exp;
			$link      = get_permalink( $exp->get_originals_id() );
			$ori_label = __( 'Original', 'nelioab' );

			$action_links = $this->get_action_links( $exp, $exp->get_originals_id() );

			if ( $this->is_winner( $exp->get_originals_id() ) )
				$set_as_winner = $this->winner_label;
			else
				$set_as_winner = '';

			if ( $link )
				$link = sprintf( '<strong><a href="%s" target="_blank">%s</a></strong>',
					$link, $this->trunk( $this->ori ) );
			else
				$link = '<strong>' . $this->trunk( $this->ori ) . '</strong> <small>' . __( '(Not found)', 'nelioab' ) . '</small>';

			echo sprintf( '<tr>' .
				'<td><span class="alt-type add-new-h2 %s">%s</span></td>' .
				'<td>%s<br />' .
				'<small>%s&nbsp;</small></td>' .
				'</tr>',
				$set_as_winner, $ori_label, $link, implode( ' | ', $action_links ) );
		}

		protected function print_the_real_alternatives() {
			// REAL ALTERNATIVES
			// -----------------------------------------
			$exp = $this->exp;
			$i   = 0;

			foreach ( $exp->get_alternatives() as $alt ) {
				$i++;
				$link = get_permalink( $alt->get_value() );

				if ( $this->is_ori_page )
					$link = esc_url( add_query_arg( array(
							'preview' => 'true',
						), $link ) );

				$action_links = $this->get_action_links( $exp, $alt->get_value() );

				if ( $this->is_winner( $alt->get_value() ) )
					$set_as_winner = $this->winner_label;
				else
					$set_as_winner = '';

				$alt_label = sprintf( __( 'Alternative %s', 'nelioab' ), $i );

				if ( $link )
					$link = sprintf( '<strong><a href="%s" target="_blank">%s</a></strong>',
						$link, $this->trunk( $alt->get_name() ) );
				else
					$link = '<strong>' . $this->trunk( $alt->get_name() ) . '</strong> <small>' . __( '(Not found)', 'nelioab' ) . '</small>';

				echo sprintf( '<tr>' .
					'<td><span class="alt-type add-new-h2 %1$s">%2$s</span></td>' .
					'<td>%3$s ' .
					'<img id="loading-%4$s" style="display:none;width:1em;margin-top:-1em;" src="%5$s" />' .
					'<strong><small id="success-%4$s" style="display:none;">%6$s</small></strong><br />' .
					'<small>%7$s&nbsp;</small></td>' .
					'</tr>',
					$set_as_winner, $alt_label,
					$link,
					$alt->get_value(), nelioab_asset_link( '/images/loading-small.gif' ),
					__( '(Done!)', 'nelioab' ),
					implode( ' | ', $action_links ) );
			}

		}

		protected function print_dialog_content() {
			$exp = $this->exp;
			?>
			<p><?php
				printf( __( 'You are about to overwrite the original %s with the content of an alternative. Please, remember <strong>this operation cannot be undone</strong>. Are you sure you want to overwrite it?', 'nelioab' ), $this->post_type['name'] );
			?></p>
			<form id="apply_alternative" method="post" action="<?php
				echo admin_url(
					'admin.php?page=nelioab-experiments&action=progress&' .
					'id=' . $exp->get_id() . '&' .
					'type=' . $exp->get_type() ); ?>">
				<input type="hidden" name="apply_alternative" value="true" />
				<input type="hidden" name="nelioab_exp_type" value="<?php echo $exp->get_type(); ?>" />
				<input type="hidden" id="original" name="original" value="<?php echo $exp->get_originals_id(); ?>" />
				<input type="hidden" id="alternative" name="alternative" value="" />
				<p><input type="checkbox" id="copy_content" name="copy_content" checked="checked" disabled="disabled" /><?php
					_e( 'Override title and content', 'nelioab' ); ?></p>
				<p><input type="checkbox" id="copy_meta" name="copy_meta" <?php
					if ( NelioABSettings::is_copying_metadata_enabled() ) echo 'checked="checked" ';
				?>/><?php _e( 'Override all metadata', 'nelioab' ); ?></p>
				<?php
				if ( ! 'page' == $this->post_type['name'] ) { ?>
					<p><input type="checkbox" id="copy_categories" name="copy_categories" <?php
						if ( NelioABSettings::is_copying_categories_enabled() ) echo 'checked="checked" ';
					?>/><?php _e( 'Override categories', 'nelioab' ); ?></p>
					<p><input type="checkbox" id="copy_tags" name="copy_tags" <?php
						if ( NelioABSettings::is_copying_tags_enabled() ) echo 'checked="checked" ';
					?>/><?php _e( 'Override tags', 'nelioab' ); ?></p><?php
				} ?>
			</form>
			<?php
		}

		protected function get_labels_for_conversion_rate_js() {
			$labels = array();
			$labels['title']    = __( 'Conversion Rates', 'nelioab' );
			$labels['subtitle'] = sprintf( __( 'for the original and the alternative %s', 'nelioab' ), strtolower( $this->post_type['plural'] ) );
			$labels['xaxis']    = __( 'Alternatives', 'nelioab' );
			$labels['yaxis']    = __( 'Conversion Rate (%)', 'nelioab' );
			$labels['column']   = __( '{0}%', 'nelioab' );
			$labels['detail']   = __( '<b>{0}</b><br />Conversions: {1}%', 'nelioab' );
			return $labels;
		}

		protected function get_labels_for_improvement_factor_js() {
			$labels = array();
			$labels['title']    = __( 'Improvement Factors', 'nelioab' );
			$labels['subtitle'] = sprintf( __( 'with respect to the original %s', 'nelioab' ), $this->post_type['name'] );
			$labels['xaxis']    = __( 'Alternatives', 'nelioab' );
			$labels['yaxis']    = __( 'Improvement (%)', 'nelioab' );
			$labels['column']   = __( '{0}%', 'nelioab' );
			$labels['detail']   = __( '<b>{0}</b><br />{1}% improvement', 'nelioab' );
			return $labels;
		}

		protected function get_labels_for_visitors_js() {
			$labels = array();
			$labels['title']       = __( 'Page Views and Conversions', 'nelioab' );
			$labels['subtitle']    = sprintf( __( 'for the original and the alternative %s', 'nelioab' ) , strtolower( $this->post_type['plural'] ) );
			$labels['xaxis']       = __( 'Alternatives', 'nelioab' );
			$labels['detail']      = __( 'Number of {series.name}: <b>{point.y}</b>', 'nelioab' );
			$labels['visitors']    = __( 'Page Views', 'nelioab' );
			$labels['conversions'] = __( 'Conversions', 'nelioab' );
			return $labels;
		}

	}//NelioABPostAltExpProgressPage

}



?>
