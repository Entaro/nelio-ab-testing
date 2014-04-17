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


if ( !class_exists( 'NelioABWpHelper' ) ) {

	abstract class NelioABWpHelper {

		/**
		 *
		 */
		public static function override( $ori_id, $overriding_id,
				$meta = true, $categories = true, $tags = true ) {

			$ori = get_post( $ori_id, ARRAY_A );
			if ( !$ori )
				return;
			$overriding = get_post( $overriding_id, ARRAY_A );
			if ( !$overriding )
				return;

			require_once( NELIOAB_UTILS_DIR . '/optimize-press-support.php' );
			NelioABOptimizePressSupport::make_post_compatible_with_optimizepress(
				$ori_id, $overriding_id );

			$ori['post_title']    = $overriding['post_title'];
			$ori['post_content']  = $overriding['post_content'];
			$ori['post_excerpt']  = $overriding['post_excerpt'];
			$ori['post_parent']   = $overriding['post_parent'];
			wp_update_post( $ori );

			if ( $meta )
				NelioABWpHelper::copy_meta_info( $overriding_id, $ori_id );
			NelioABWpHelper::copy_terms( $overriding_id, $ori_id, $categories, $tags );
		}

		/**
		 * Copy all custom fields (post meta) from one object to another
		 */
		public static function copy_meta_info( $src_id, $dest_id, $copy_hidden = true ) {

			// First of all, we remove all post meta from the destination
			$custom_fields = get_post_meta( $dest_id );
			if ( $custom_fields ) {
				foreach ( $custom_fields as $key => $val) {

					// If the metakey is ours (i.e. nelioab), we do not remove it
					if ( strpos( $key, 'nelioab_' ) !== false )
						continue;

					if ( !$copy_hidden && substr( $key, 0, 1 ) == '_' )
						continue;

					delete_post_meta( $dest_id, $key );
				}
			}

			// And then we transfer the new ones
			$custom_fields = get_post_meta( $src_id );
			if ( $custom_fields ) {
				foreach ( $custom_fields as $key => $val) {

					// If the metakey is ours (i.e. nelioab), we do not duplicate it
					if ( strpos( $key, 'nelioab_' ) !== false )
						continue;

					if ( !$copy_hidden && substr( $key, 0, 1 ) == '_' )
						continue;

					if ( !is_array( $val ) ) {
						if ( is_serialized( $val ) )
							$val = unserialize( $val );
						add_post_meta( $dest_id, $key, $val );
					}
					else {
						foreach ( $val as $aux ) {
							if ( is_serialized( $aux ) )
								$aux = unserialize( $aux );
							add_post_meta( $dest_id, $key, $aux );
						}
					}
				}
			}
		}

		/**
		 * Copy all terms (including categories and tags) from one object to another
		 */
		public static function copy_terms( $src_id, $dest_id, $copy_categories, $copy_tags ) {

			// First of all, we remove all terms from the destination
			wp_delete_object_term_relationships( $dest_id, get_taxonomies() );

			// And then we transfer the new ones
			if ( $copy_tags ) {
				$terms = wp_get_post_terms( $src_id );
				$aux   = array();
				if ( $terms )
					foreach ( $terms as $term )
						array_push( $aux, $term->name );
				wp_set_post_terms( $dest_id, $aux );
			}

			if ( $copy_categories ) {
				$categories = wp_get_post_categories( $src_id );
				if ( $categories )
					wp_set_post_categories( $dest_id, $categories );
			}

		}

		/**
		 * Copy all comments from one post to another
		 */
		public static function copy_comments( $src_id, $dest_id ) {
			foreach ( get_comments( array( 'post_id' => $src_id ) ) as $comment ) {
				$comment->comment_post_ID = $dest_id;
				wp_insert_comment( (array) $comment );
			}
		}

		/**
		 * Returns whether this WordPress installation is, at least, the queried version
		 */
		public static function is_at_least_version( $version ) {
			return $version <= floatval( get_bloginfo( 'version' ) );
		}

		/**
		 * Returns site_url, but using http instead of (maybe) https.
		 */
		public static function get_unsecured_site_url() {
			$url = site_url();
			$url = preg_replace( '/^https/', 'http', $url );
			return $url;
		}

	}//NelioABWpHelper

}
?>
