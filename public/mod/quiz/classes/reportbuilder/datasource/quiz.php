<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

declare(strict_types=1);

namespace mod_quiz\reportbuilder\datasource;

use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\filters\{boolean_select, date, select, text, number};
use core_reportbuilder\local\helpers\database;
use mod_quiz\reportbuilder\local\entities\quiz as quiz_entity;
use mod_quiz\reportbuilder\local\entities\quiz_grades;

/**
 * Quiz datasource
 *
 * @package  mod_quiz
 * @copyright Thiago Livramento <thiago@adapta.online>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz extends datasource {

     /**
      * Return user friendly name of the datasource
      *
      * @return string
      */
    public static function get_name(): string {
        return get_string('modulenameplural', 'mod_quiz');
    }

     /**
      * Initialise report
      */
    protected function initialise(): void {

        $courseentity = new course();
        $course = $courseentity->get_table_alias('course');

        // Exclude site course.
        $paramsiteid = database::generate_param_name();

        $this->set_main_table('course', $course);
        $this->add_base_condition_sql("{$course}.id != :{$paramsiteid}", [$paramsiteid => SITEID]);

        $this->add_entity($courseentity);

        $quizentity = new quiz_entity();
        $quiz = $quizentity->get_table_alias('quiz');
        $quizjoin = "LEFT JOIN {quiz} {$quiz} ON {$course}.id = {$quiz}.course";
        $this->add_entity($quizentity->add_joins($courseentity->get_joins())->add_join($quizjoin));

        $quizgradesentity = new quiz_grades();
        $quizgrades = $quizgradesentity->get_table_alias('quiz_grades');
        $qgjoin = "LEFT JOIN {quiz_grades} {$quizgrades} ON {$quizgrades}.quiz = {$quiz}.id";
        $this->add_entity($quizgradesentity->add_joins($quizentity->get_joins())->add_join($qgjoin));

        $userentity = new user();
        $user = $userentity->get_table_alias('user');
        $userjoin = "LEFT JOIN {user} {$user} ON {$user}.id = {$quizgrades}.userid";
        $this->add_entity($userentity->add_joins($quizgradesentity->get_joins())->add_join($userjoin));

        $this->add_all_from_entities(/*[
            $courseentity->get_entity_name(),
            $quizentity->get_entity_name(),
            $quizgradesentity->get_entity_name(),
            $userentity->get_entity_name(),
        ]*/);
    }

    /**
     * Return the columns that will added to the report once is created
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return [
                'course:coursefullnamewithlink',
                'quiz:namewithlink',
               ];
    }

    /**
     * Return the filters that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return  [];
    }

    /**
     * Return the conditions that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [];
    }
}
