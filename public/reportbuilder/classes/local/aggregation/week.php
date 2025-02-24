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

namespace core_reportbuilder\local\aggregation;

use core\{clock, di};
use core\lang_string;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;

/**
 * Column week aggregation type
 *
 * @package     core_reportbuilder
 * @copyright   2025 Daniel Neis Araujo
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class week extends base {
    /**
     * Return aggregation name
     *
     * @return lang_string
     */
    public static function get_name(): lang_string {
        return new lang_string('aggregationweek', 'core_reportbuilder');
    }

    /**
     * This aggregation can be performed on timestamp columns
     *
     * @param int $columntype
     * @return bool
     */
    public static function compatible(int $columntype): bool {
        return $columntype === column::TYPE_TIMESTAMP;
    }

    /**
     * Return the aggregated field SQL
     *
     * @param string $field
     * @param int $columntype
     * @return string
     */
    public static function get_field_sql(string $field, int $columntype): string {
        return "(CAST(({$field} / " . WEEKSECS . ") as INT) * " . WEEKSECS . ")";
    }

    /**
     * When applied to a column, we should group by its fields
     *
     * @return bool
     */
    public static function column_groupby(): bool {
        return true;
    }

    /**
     * Return formatted value for column when applying aggregation
     *
     * @param mixed $value
     * @param array $values
     * @param array $callbacks
     * @param int $columntype
     * @return string
     */
    public function format_value($value, array $values, array $callbacks, int $columntype): string {
        $value = (int)$value;
        $date = new \DateTime();
        $date->setTimezone(\core_date::get_user_timezone_object());
        $date->setTimestamp($value);
        if ($date->format('w') == 0) { // Sunday.
            return format::userdate(strtotime('this sunday', $value), (object) [], get_string('strftimedate', 'core_langconfig')) .
                   ' - ' .
                   format::userdate(strtotime('next saturday', $value), (object) [], get_string('strftimedate', 'core_langconfig'));
        } else if ($date->format('w') == 6) { // Saturday.
            return format::userdate(strtotime('last sunday', $value), (object) [], get_string('strftimedate', 'core_langconfig')) .
                   ' - ' .
                   format::userdate(strtotime('this saturday', $value), (object) [], get_string('strftimedate', 'core_langconfig'));
        } else { // Other weekdays.
            return format::userdate(strtotime('last sunday', $value), (object) [], get_string('strftimedate', 'core_langconfig')) .
                   ' - ' .
                   format::userdate(strtotime('next saturday', $value), (object) [], get_string('strftimedate', 'core_langconfig'));
        }
    }
}
