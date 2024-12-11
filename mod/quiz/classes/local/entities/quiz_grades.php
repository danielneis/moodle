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

namespace mod_quiz\local\entities;

use core_reportbuilder\local\filters\{date, duration, number, text};
use core_reportbuilder\local\report\{column, filter};
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\helpers\format;
use lang_string;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Quiz grades entity class implementation quiz
 *
 * This entity defines all the quiz grades columns and filters to be use in any report.
 *
 * @package mod_quiz
 * @copyright Thiago Livramento <thiago@adapta.online>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_grades extends base {

    /**
     * Database tables that this entity uses
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return ['quiz_grades'];
    }

    /**
     * the defaul title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('gradenoun');
    }

    /**
     * Initialise the entity, add all quiz grades fields
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
     * Return list of all avaliable columns
     *
     * These are all columns avaliable to use in report that use this entity.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {

        $columns = [];

        $quizgradealias = $this->get_table_alias('quiz_grades');

        // Quiz grade column.
        $columns[] = (new column(
            'grade',
            new lang_string('gradenoun'),
            $this->get_entity_name()
        ))
          ->set_type(column::TYPE_FLOAT)
          ->set_is_sortable(true)
          ->add_field("{$quizgradealias}.grade");

        // Quiz grade time modified column.
        $columns[] = (new column(
            'timemodified',
            new lang_string('timemodified', 'mod_quiz'),
            $this->get_entity_name()
        ))
          ->set_type(column::TYPE_TIMESTAMP)
          ->set_is_sortable(true)
          ->add_field("{$quizgradealias}.timemodified")
          ->add_callback([format::class, 'userdate']);

         return $columns;
    }

    /**
     * Return the list off all avaliable filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $filters = [];
        $quizgradealias = $this->get_table_alias('quiz_grades');
        $filters[] = (new filter(
            text::class,
            'gradeselector',
            new lang_string('grade', 'mod_quiz'),
            $this->get_entity_name(),
            "{$quizgradealias}.grade"
        ));
        return $filters;
    }
}
