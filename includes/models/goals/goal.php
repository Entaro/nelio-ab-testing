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


if( !class_exists( 'NelioABGoal' ) ) {

	abstract class NelioABGoal {

		const UNDEFINED_GOAL_TYPE          = 0;
		const ALTERNATIVE_EXPERIMENT_GOAL  = 1;

		const UNDEFINED_GOAL_TYPE_STR          = 'UndefinedGoalKind';
		const ALTERNATIVE_EXPERIMENT_GOAL_STR  = 'AlternativeExperimentGoal';

		private $exp;
		private $id;
		private $kind;
		private $name;
		private $is_main_goal;

		private $has_to_be_deleted;

		public function __construct( $exp, $id = -1 ) {
			$this->kind = self::UNDEFINED_GOAL_TYPE;
			$this->name = __( 'Undefined', 'nelioab' );
			$this->exp  = $exp;
			$this->id   = $id;
			$this->is_main_goal = false;
			$this->has_to_be_deleted = false;
		}

		public function get_experiment() {
			return $this->exp;
		}

		public function get_id() {
			return $this->id;
		}

		public function set_id( $id ) {
			$this->id = $id;
		}

		public function get_kind() {
			return $this->kind;
		}

		public function set_kind( $kind ) {
			$this->kind = $kind;
		}

		public function set_kind_using_text( $kind ) {
			switch( $kind ) {

				case self::ALTERNATIVE_EXPERIMENT_GOAL_STR:
					$this->set_kind( self::ALTERNATIVE_EXPERIMENT_GOAL );
					return;

				case self::UNDEFINED_GOAL_TYPE_STR:
				default:
					$this->set_kind( self::UNDEFINED_GOAL_TYPE );
					return;

			}
		}

		public function get_textual_kind() {
			switch( $this->get_kind() ) {
				case self::ALTERNATIVE_EXPERIMENT_GOAL:
					return self::ALTERNATIVE_EXPERIMENT_GOAL_STR;
				case self::UNDEFINED_GOAL_TYPE:
				default:
					return self::UNDEFINED_GOAL_TYPE_STR;
			}

		}

		public function get_name() {
			return $this->name;
		}

		public function set_name( $name ) {
			$this->name = $name;
		}

		public function is_main_goal() {
			return $this->is_main_goal;
		}

		public function set_as_main_goal( $is_main_goal ) {
			$this->is_main_goal = $is_main_goal;
		}

		public function set_to_be_deleted( $delete = true ) {
			$this->has_to_be_deleted = $delete;
		}

		public function has_to_be_deleted() {
			return $this->has_to_be_deleted;
		}

		public abstract function json4js();
		public abstract function get_results();
		public abstract function is_ready();

		public static function build_goal_using_json4js( $json_goal, $exp ) {
			throw new Exception( 'This function should be implemented by a concrete class.' );
		}

	}//NelioABGoal

}

