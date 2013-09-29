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


if ( !class_exists( NelioABSelectExpCreationPageController ) ) {

	require_once( NELIOAB_ADMIN_DIR . '/views/select-exp-creation-page.php' );

	class NelioABSelectExpCreationPageController {

		public static function build() {
			$title = __( 'Experiment Type Selection', 'nelioab' );

			// Check settings
			require_once( NELIOAB_MODELS_DIR . '/settings.php' );
			if ( !NelioABSettings::check_user_settings() ) {
				require_once( NELIOAB_ADMIN_DIR . '/views/errors/invalid-config-page.php' );
				$view = new NelioABInvalidConfigPage( $title );
				$view->render();
				return;
			}

			$view = new NelioABSelectExpCreationPage( $title );
			$view->render();
		}

	}//NelioABSelectExpCreationPageController

}

?>
