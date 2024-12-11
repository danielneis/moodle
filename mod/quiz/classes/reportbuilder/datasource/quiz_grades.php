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
use core_course\reportbuilder\local\entities\course_category;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;

/**
 * Quiz grades datasource
 *
 * @package mod_quiz
 * @copyright Thiago Livramento <thiago@adapta.online>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_grades extends datasource {

    /**
     * Return user friendly name of the datasource
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('grades', 'grades');
    }

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $quizgradesentity = new \mod_quiz\local\entities\quiz_grades();
        $quizgradesalias = $quizgradesentity->get_table_alias('quiz_grades');
        $this->set_main_table('quiz_grades', $quizgradesalias);
        $this->add_entity($quizgradesentity);

        $quizentity = new \mod_quiz\local\entities\quiz();
        $quizalias = $quizentity->get_table_alias('quiz');
        $quizjoin = "JOIN {quiz} {$quizalias} ON {$quizalias}.id = {$quizgradesalias}.quiz";
        $this->add_entity($quizentity);
        $this->add_join($quizjoin);

        $courseentity = new course();
        $coursealias = $courseentity->get_table_alias('course');
        $coursejoin = "JOIN {course} {$coursealias} ON {$coursealias}.id = {$quizalias}.course";
        $this->add_entity($courseentity);
        $this->add_join($coursejoin);

        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $userjoin = "JOIN {user} {$useralias} ON {$useralias}.id = {$quizgradesalias}.userid";
        $this->add_entity($userentity);
        $this->add_join($userjoin);

        $this->add_all_from_entities();
    }

    /**
     * Return the columns that will added to the report once is created
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return ['course:coursefullnamewithlink',
                'quiz:namewithlink',
                'user:fullnamewithlink',
                'quiz_grades:grade', ];
    }

    /**
     * Return the filters that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return [];
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
