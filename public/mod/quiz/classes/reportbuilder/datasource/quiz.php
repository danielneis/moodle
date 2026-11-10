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

use core_course\reportbuilder\local\entities\{course_category, course_module};
use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\{course, user};
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
        $quizentity = new quiz_entity();
        [
            'context' => $contextalias,
            'course_modules' => $coursemodulesalias,
            'quiz' => $quizalias,
        ] = $quizentity->get_table_aliases();

        $this->set_main_table('quiz', $quizalias);
        $this->add_entity($quizentity->add_joins($quizentity->get_course_modules_joins('quiz', "{$quizalias}.id")));

        $courseentity = new course();
        $coursealias = $courseentity->get_table_alias('course');
        $this->add_entity($courseentity->add_join("LEFT JOIN {course} {$coursealias} ON {$coursealias}.id = {$quizalias}.course"));

        // Join the course category entity.
        $coursecatentity = new course_category();
        $coursecatalias = $coursecatentity->get_table_alias('course_categories');
        $this->add_entity($coursecatentity
            ->add_joins($courseentity->get_joins())
            ->add_join("LEFT JOIN {course_categories} {$coursecatalias} ON {$coursecatalias}.id = {$coursealias}.category"));

        // Join the course module entity.
        $coursemodentity = (new course_module())
            ->set_table_alias('course_modules', $coursemodulesalias);
        $this->add_entity($coursemodentity
            ->add_joins($quizentity->get_joins()));

        $quizgradesentity = (new quiz_grades())
            ->set_table_alias('context', $contextalias);
        $quizgrades = $quizgradesentity->get_table_alias('quiz_grades');
        $qgjoin = "LEFT JOIN {quiz_grades} {$quizgrades} ON {$quizgrades}.quiz = {$quizalias}.id";
        $this->add_entity($quizgradesentity->add_joins($quizentity->get_joins())->add_join($qgjoin));

        $userentity = new user();
        $user = $userentity->get_table_alias('user');
        $userjoin = "LEFT JOIN {user} {$user} ON {$user}.id = {$quizgrades}.userid";
        $this->add_entity($userentity->add_joins($quizgradesentity->get_joins())->add_join($userjoin));

        // Exclude site course.
        $paramsiteid = database::generate_param_name();

        $this->add_all_from_entities([
            $coursecatentity,
            $courseentity,
            $coursemodentity,
            $quizentity,
            $quizgradesentity,
            $userentity,
        ]);
    }

    /**
     * Return the columns that will added to the report once is created
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return [
                'course:coursefullnamewithlink',
                'quiz:name',
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

    /**
     * Return the default sorting that will be added to the report upon creation
     *
     * @return int[]
     */
    public function get_default_column_sorting(): array {
        return [
            'course:coursefullnamewithlink' => SORT_ASC,
            'quiz:name' => SORT_ASC,
        ];
    }
}
