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

namespace mod_quiz\reportbuilder\local\entities;

use core_collator;
use core\{context, context_helper};
use core\lang_string;
use core_course\reportbuilder\local\entities\course_module_base;
use core_reportbuilder\local\filters\{date, duration, number, text};
use core_reportbuilder\local\report\{column, filter};
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\filters\autocomplete;
use stdClass;

/**
 * Quiz entity class implementation quiz
 *
 * This entity defines all the quiz columns and filters to be used in any report.
 *
 * @package     mod_quiz
 * @copyright   Thiago Livramento <thiago@adapta.online>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz extends course_module_base {
    /**
     * Database tables that this entity uses
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return array_merge(
            parent::get_default_tables(),
            [
                'quiz',
            ],
        );
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
     * Return list of all available columns
     *
     * These are all columns available to use in report that use this entity.
     *
     * @return column[]
     */
    protected function get_available_columns(): array {
        global $DB;

        $columns = [];

        [
            'context' => $contextalias,
            'quiz' => $quizalias,
        ] = $this->get_table_aliases();

        // Quiz name column.
        $columns[] = (new column(
            'name',
            new lang_string('name', 'mod_quiz'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_is_sortable(true)
            ->add_field("{$quizalias}.name")
            ->add_fields(context_helper::get_preload_record_columns_sql($contextalias))
            ->set_is_sortable(true)
            ->set_callback(static function (?string $name, stdClass $quiz): string {
                if ($name === null || $quiz->ctxid === null) {
                    return '';
                }

                context_helper::preload_from_record(clone $quiz);
                $context = context::instance_by_id($quiz->ctxid);

                return format_string($name, true, ['context' => $context]);
            });

        // Quiz timeopen column.
        $columns[] = (new column(
            'timeopen',
            new lang_string('quizopen', 'mod_quiz'),
            $this->get_entity_name()
        ))
          ->add_joins($this->get_joins())
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
          ->add_joins($this->get_joins())
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
          ->add_joins($this->get_joins())
          ->set_is_sortable(true)
          ->add_field("{$quizalias}.timelimit");

        // Quiz grade column.
        $columns[] = (new column(
            'grade',
            new lang_string('gradepass', 'grades'),
            $this->get_entity_name()
        ))
          ->add_joins($this->get_joins())
          ->set_type(column::TYPE_FLOAT)
          ->set_is_sortable(true)
          ->add_field("{$quizalias}.grade");

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_available_filters(): array {
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

        // We add our own custom course selector filter.
        $filters[] = (new filter(
            autocomplete::class,
            'quizselector',
            new lang_string('quizselect', 'mod_quiz'),
            $this->get_entity_name(),
            "{$quizalias}.name"
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback(static function (): array {
                global $DB;
                $names = $DB->get_fieldset_sql('SELECT DISTINCT name FROM {quiz} ORDER BY name ASC');

                $options = [];
                foreach ($names as $name) {
                    $options[$name] = $name;
                }

                core_collator::asort($options);
                return $options;
            });

        $filters[] = (new filter(
            number::class,
            'grade',
            new lang_string('grade', 'mod_quiz'),
            $this->get_entity_name(),
            "{$quizalias}.grade"
        ));

        $filters[] = (new filter(
            date::class,
            'timeopen',
            new lang_string('quizopen', 'mod_quiz'),
            $this->get_entity_name(),
            "{$quizalias}.timeopen"
        ));

        $filters[] = (new filter(
            date::class,
            'timeclose',
            new lang_string('quizclose', 'mod_quiz'),
            $this->get_entity_name(),
            "{$quizalias}.timeclose"
        ));
        return $filters;
    }
}
