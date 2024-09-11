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

namespace mod_quiz\local\entities;

use core_reportbuilder\local\filters\{date, duration, number, text};
use core_reportbuilder\local\report\{column, filter};
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\helpers\format;
use lang_string;

/**
 * Quiz entity class implementation quiz
 *
 * This entity defines all the quiz columns and filters to be used in any report.
 *
 * @package     mod_quiz
 * @copyright   Thiago Livramento <thiago@adapta.online>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz extends base {

    /**
     * Database tables that this entity uses
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return ['quiz', 'course_modules'];
    }

    /**
     * The default title for this entity
     *
     * @return lagn_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('modulename', 'mod_quiz');
    }

    /**
     * Initialise the entity, add all quiz fields
     *
     * @return base
     */
    public function initialise(): base {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        $filters = $this->get_all_filters();
        foreach ($filters as $filter) {
            $this->add_filter($filter);
        }

        $conditions = $this->get_all_filters();
        foreach ($conditions as $condition) {
            $this->add_condition($condition);
        }

        return $this;
    }

    /**
     * Return list of all available columns
     *
     * These are all columns available to use in report that use this entity.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        global $DB;

        $columns = [];

        $quizalias = $this->get_table_alias('quiz');

        // Quiz name column.
        $columns[] = (new column(
            'name',
            new lang_string('name', 'mod_quiz'),
            $this->get_entity_name()
        ))
          ->set_is_sortable(true)
          ->add_field("{$quizalias}.name");

        // Quiz name with link column.
        $moduleid = $DB->get_field('modules', 'id', ['name' => 'quiz']);
        $cmalias = $this->get_table_alias('course_modules');
        $cmjoin = "JOIN {course_modules} {$cmalias} ON {$cmalias}.instance = {$quizalias}.id AND {$cmalias}.module = {$moduleid}";
        $columns[] = (new column(
           'namewithlink',
           new lang_string('namewithlink', 'mod_quiz'),
           $this->get_entity_name()
        ))
          ->add_join($cmjoin)
          ->set_type(column::TYPE_TEXT)
          ->add_fields("{$quizalias}.name, {$quizalias}.id, {$cmalias}.id as cmid")
          ->add_callback(static function(?string $name, \stdClass $quiz): string {
            if (empty($quiz->id)) {
                return '';
            }
            $url = new \moodle_url('/mod/quiz/view.php', ['id' => $quiz->cmid]);
            return \html_writer::link($url, format_string($quiz->name, true));
          });

        // Quiz timeopen column.
        $columns[] = (new column(
           'timeopen',
           new lang_string('quizopen', 'mod_quiz'),
           $this->get_entity_name()
        ))
          ->set_type(column::TYPE_TIMESTAMP)
          ->set_is_sortable(true)
          ->add_field("{$quizalias}.timeopen")
          ->add_callback([format::class, 'userdate']);

        // Quiz time close column.
        $columns[] = (new column(
            'timeclose',
            new lang_string('quizclose', 'mod_quiz'),
            $this->get_entity_name()
        ))
           ->set_type(column::TYPE_TIMESTAMP)
           ->set_is_sortable(true)
           ->add_field("{$quizalias}.timeclose")
           ->add_callback([format::class, 'userdate']);

        // Quiz time limit column.
        $columns[] = (new column(
            'timelimit',
            new lang_string('timelimit', 'mod_quiz'),
            $this->get_entity_name()
        ))
          ->set_is_sortable(true)
          ->add_field("{$quizalias}.timelimit");

        // Quiz grade column.
        $columns[] = (new column(
           'grade',
           new lang_string('gradepass', 'grades'),
           $this->get_entity_name()
        ))
          ->set_is_sortable(true)
          ->add_field("{$quizalias}.grade");

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $filters = [];
        $quizalias = $this->get_table_alias('quiz');

        // Quiz name filter.
        $filters[] = (new filter(
           text::class,
           'nameselector',
           new lang_string('name', 'mod_quiz'),
           $this->get_entity_name(),
           "{$quizalias}.name"
        ));

        return $filters;
    }
}
