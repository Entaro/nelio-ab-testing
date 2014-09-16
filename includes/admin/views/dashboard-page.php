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
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program. If not, see <http://www.gnu.org/licenses/>.
 */


if ( !class_exists( 'NelioABDashboardPage' ) ) {

	require_once( NELIOAB_UTILS_DIR . '/admin-ajax-page.php' );
	class NelioABDashboardPage extends NelioABAdminAjaxPage {

		private $graphic_delay;
		private $summary;

		public function __construct( $title ) {
			parent::__construct( $title );
			$this->set_icon( 'icon-nelioab' );
			$this->add_title_action( __( 'New Experiment', 'nelioab' ), '?page=nelioab-add-experiment' );
			$this->summary = '';
			$this->graphic_delay = 500;
		}

		public function set_summary( $summary ) {
			$this->summary = $summary;
		}

		public function do_render() {
			if ( count( $this->summary ) == 0 ) {
				echo '<center>';
				echo sprintf( '<img src="%s" alt="%s" />',
					nelioab_asset_link( '/admin/images/happy.png' ),
					__( 'Happy smile.', 'nelioab' )
				);
				echo '<h2 style="max-width:750px;">';
				echo sprintf(
					__( 'Hi! You\'re now in Nelio\'s Dashboard, where you\'ll find all relevant information about your running experiments. Right now, however, there are none...<br><a href="%s">Create one now!</a>', 'nelioab' ),
					'admin.php?page=nelioab-add-experiment' );
				echo '</h2>';
				echo '</center>';
			}
			else {
				$this->print_cards();
			}
		}

		public function print_cards() {
			// The following function is used by ALT_EXP cards ?>
			<script>
				function drawGraphic( id, data, label, baseColor ) {
					if ( baseColor == undefined )
						baseColor = '#CCCCCC';
					var $ = jQuery;
					Highcharts.getOptions().plotOptions.pie.colors = (function () {
					var divider = 25;
					var numOfAlts = data.length;
					if ( numOfAlts < 10 ) divider = 20
					if ( numOfAlts < 8 ) divider = 15
					if ( numOfAlts < 4 ) divider = 6
					var colors = [],
						base = baseColor,
						i
						for (i = 0; i < 10; i++)
							colors.push(Highcharts.Color(base).brighten(i / divider).get());
						return colors;
					}());

					// Build the chart
					var chart = $('#' + id).highcharts({
						chart: {
							plotBackgroundColor: null,
							plotBorderWidth: null,
							plotShadow: false,
							margin: [0, 0, 0, 0],
						},
						title: { text:'' },
						exporting: { enabled: false },
						tooltip: {
							pointFormat: '{series.name}: <b>{point.y:.0f}</b>'
						},
						plotOptions: {
							pie: {
								allowPointSelect: false,
								cursor: 'pointer',
								dataLabels: { enabled: false },
							}
						},
						series: [{
							type: 'pie',
							name: label,
							data: data
						}],
					});
				}
			</script><?php

			include_once( NELIOAB_UTILS_DIR . '/wp-helper.php' );
			foreach ( $this->summary as $exp ) {
				switch( $exp->get_type() ) {
					case NelioABExperiment::HEATMAP_EXP:
							$progress_url = NelioABWPHelper::get_unsecured_site_url() .
							'/wp-content/plugins/' . NELIOAB_PLUGIN_DIR_NAME .
							'/heatmaps.php?id=%1$s&exp_type=%2$s';
						$this->print_linked_beautiful_box(
							$exp->get_id(),
							$this->get_beautiful_title( $exp ),
							sprintf( $progress_url, $exp->get_id(), $exp->get_type() ),
							array( &$this, 'print_heatmap_exp_card', array( $exp ) ) );
						break;
					default:
						$this->print_linked_beautiful_box(
							$exp->get_id(),
							$this->get_beautiful_title( $exp ),
							sprintf(
									'?page=nelioab-experiments&action=progress&id=%1$s&exp_type=%2$s',
									$exp->get_id(),
									$exp->get_type()
								),
							array( &$this, 'print_alt_exp_card', array( $exp ) ) );
				}
			}

		}

		public function get_beautiful_title( $exp ) {
			$img = '<div class="tab-type tab-type-%1$s" alt="%2$s" title="%2$s"></div>';
			switch ( $exp->get_type() ) {
				case NelioABExperiment::TITLE_ALT_EXP:
					$img = sprintf( $img, 'title', __( 'Title', 'nelioab' ) );
					break;
				case NelioABExperiment::PAGE_ALT_EXP:
					$img = sprintf( $img, 'page', __( 'Page', 'nelioab' ) );
					break;
				case NelioABExperiment::POST_ALT_EXP:
					$img = sprintf( $img, 'post', __( 'Post', 'nelioab' ) );
					break;
				case NelioABExperiment::TITLE_ALT_EXP:
					$img = sprintf( $img, 'title', __( 'Title', 'nelioab' ) );
					break;
				case NelioABExperiment::THEME_ALT_EXP:
					$img = sprintf( $img, 'theme', __( 'Theme', 'nelioab' ) );
					break;
				case NelioABExperiment::CSS_ALT_EXP:
					$img = sprintf( $img, 'css', __( 'CSS', 'nelioab' ) );
					break;
				case NelioABExperiment::HEATMAP_EXP:
					$img = sprintf( $img, 'heatmap', __( 'Heatmap', 'nelioab' ) );
					break;
				case NelioABExperiment::WIDGET_ALT_EXP:
					$img = sprintf( $img, 'widget', __( 'Widget', 'nelioab' ) );
					break;
				case NelioABExperiment::MENU_ALT_EXP:
					$img = sprintf( $img, 'menu', __( 'Menu', 'nelioab' ) );
					break;
				default:
					$img = '';
			}

			if ( $exp->has_result_status() )
				$light = NelioABGTest::generate_status_light( $exp->get_result_status() );

			$name = '<span class="exp-title">' . $exp->get_name() . '</span>';
			$status = '<span id="info-summary">' . $light . '</span>';

			return $img . $name . $status;
		}

		public function print_alt_exp_card( $exp ) { ?>
			<div class="row padding-top">
				<div class="col col-4">
					<div class="row data padding-left">
						<span class="value"><?php echo $exp->get_total_visitors(); ?></span>
						<span class="label"><?php _e( 'Page Views', 'nelioab' ); ?></span>
					</div>
					<div class="row data padding-left">
						<span class="value"><?php echo count( $exp->get_alternative_info() ); ?></span>
						<span class="label"><?php _e( 'Alternatives', 'nelioab' ); ?></span>
					</div>
				</div>
				<div class="col col-4">
					<div class="row data">
						<span class="value"><?php echo $exp->get_original_conversion_rate(); ?>%</span>
						<span class="label"><?php _e( 'Original Version\'s Conversion Rate', 'nelioab' ); ?></span>
					</div>
					<div class="row data">
						<span class="value"><?php echo $exp->get_best_alternative_conversion_rate(); ?>%</span>
						<span class="label"><?php _e( 'Best Alternative\'s Conversion Rate', 'nelioab' ); ?></span>
					</div>
				</div>
				<?php $graphic_id = 'graphic-' . $exp->get_id(); ?>
				<div class="col col-4 graphic" id="<?php echo $graphic_id; ?>">
				</div><?php
					if ( $exp->get_total_conversions() > 0 )
						$fix = '';
					else
							$fix = '.1';
					$alt_infos = $exp->get_alternative_info();
					$values = '';
					for ( $i = 0; $i < count( $alt_infos ); ++$i ) {
						$aux = $alt_infos[$i];
						$name = $aux['name'];
						$name = str_replace( '\\', '\\\\', $name );
						$name = str_replace( '\'', '\\\'', $name );
						$conv = $aux['conversions'];
						$values .= "\n\t\t\t\t{ name: '$name', y: $conv$fix },\n";
					}

					switch( $exp->get_type() ) {
						case NelioABExperiment::PAGE_ALT_EXP:
							$color = '#DE4A3A';
							break;
						case NelioABExperiment::POST_ALT_EXP:
							$color = '#F19C00';
							break;
						case NelioABExperiment::TITLE_ALT_EXP:
							$color = '#79B75D';
							break;
						case NelioABExperiment::THEME_ALT_EXP:
							$color = '#61B8DD';
							break;
						case NelioABExperiment::CSS_ALT_EXP:
							$color = '#6EBEC5';
							break;
						default:
							$color = '#CCCCCC';
					}
				?>
				<script>jQuery(document).ready(function() {
					var aux = setTimeout( function() {
						drawGraphic('<?php echo $graphic_id; ?>',
							[<?php echo $values; ?>],
							"<?php echo esc_html( __( 'Conversions', 'nelioab' ) ); ?>",
							"<?php echo $color; ?>");
					}, <?php echo $this->graphic_delay; $this->graphic_delay += 250; ?> );
				});</script>
			</div>
			<?php
		}

		public function print_heatmap_exp_card( $exp ) {
			$hm = $exp->get_heatmap_info();
			?>
			<div class="row padding-top">
				<div class="col col-6">
					<div class="row data phone padding-left">
						<span class="value"><?php echo $hm['phone']; ?></span>
						<span class="label"><?php _e( 'Views on Phone', 'nelioab' ); ?></span>
					</div>
					<div class="row data tablet padding-left">
						<span class="value"><?php echo $hm['tablet']; ?></span>
						<span class="label"><?php _e( 'Views on Tablet', 'nelioab' ); ?></span>
					</div>
				</div>
				<div class="col col-6">
					<div class="row data desktop">
						<span class="value"><?php echo $hm['desktop']; ?></span>
						<span class="label"><?php _e( 'Views on Desktop', 'nelioab' ); ?></span>
					</div>
					<div class="row data hd">
						<span class="value"><?php echo $hm['hd']; ?></span>
						<span class="label"><?php _e( 'Views on Large Screens', 'nelioab' ); ?></span>
					</div>
				</div>
			</div>
			<?php
		}

	}//NelioABDashboardPage

}

