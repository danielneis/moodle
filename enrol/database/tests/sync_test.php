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

/**
 * External database enrolment sync tests, this also tests adodb drivers
 * that are matching our four supported Moodle database drivers.
 *
 * @package    enrol_database
 * @category   phpunit
 * @copyright  2011 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class enrol_database_testcase extends advanced_testcase {
    /** @var string Original error log */
    protected $oldlog;

    public function setUp(): void {
        // Capture the value of error_log because it is used in ADODb.
        $this->oldlog = ini_get('error_log');
    }

    protected function setup_base_db_settings(): void {
        global $CFG, $DB;

        set_config('dbencoding', 'utf-8', 'enrol_database');

        set_config('dbhost', $CFG->dbhost, 'enrol_database');
        set_config('dbuser', $CFG->dbuser, 'enrol_database');
        set_config('dbpass', $CFG->dbpass, 'enrol_database');
        set_config('dbname', $CFG->dbname, 'enrol_database');

        if (!empty($CFG->dboptions['dbport'])) {
            set_config(
                'dbhost',
                "{$CFG->dbhost}:{$CFG->dboptions['dbport']}",
                'enrol_database'
            );
        }

        switch ($DB->get_dbfamily()) {

            case 'mysql':
                set_config('dbtype', 'mysqli', 'enrol_database');
                set_config('dbsetupsql', "SET NAMES 'UTF-8'", 'enrol_database');
                set_config('dbsybasequoting', '0', 'enrol_database');
                if (!empty($CFG->dboptions['dbsocket'])) {
                    $dbsocket = $CFG->dboptions['dbsocket'];
                    if ((strpos($dbsocket, '/') === false and strpos($dbsocket, '\\') === false)) {
                        $dbsocket = ini_get('mysqli.default_socket');
                    }
                    set_config('dbtype', 'mysqli://'.rawurlencode($CFG->dbuser).':'.rawurlencode($CFG->dbpass).'@'.rawurlencode($CFG->dbhost).'/'.rawurlencode($CFG->dbname).'?socket='.rawurlencode($dbsocket), 'enrol_database');
                }
                break;

            case 'oracle':
                set_config('dbtype', 'oci8po', 'enrol_database');
                set_config('dbsybasequoting', '1', 'enrol_database');
                break;

            case 'postgres':
                set_config('dbtype', 'postgres7', 'enrol_database');
                $setupsql = "SET NAMES 'UTF-8'";
                if (!empty($CFG->dboptions['dbschema'])) {
                    $setupsql .= "; SET search_path = '".$CFG->dboptions['dbschema']."'";
                }
                set_config('dbsetupsql', $setupsql, 'enrol_database');
                set_config('dbsybasequoting', '0', 'enrol_database');
                if (!empty($CFG->dboptions['dbsocket']) and ($CFG->dbhost === 'localhost' or $CFG->dbhost === '127.0.0.1')) {
                    if (strpos($CFG->dboptions['dbsocket'], '/') !== false) {
                        $socket = $CFG->dboptions['dbsocket'];
                        if (!empty($CFG->dboptions['dbport'])) {
                            $socket .= ':' . $CFG->dboptions['dbport'];
                        }
                        set_config('dbhost', $socket, 'enrol_database');
                    } else {
                      set_config('dbhost', '', 'enrol_database');
                    }
                }
                break;

            case 'mssql':
                set_config('dbtype', 'mssqlnative', 'enrol_database');
                set_config('dbsybasequoting', '1', 'enrol_database');

                // The native sqlsrv driver uses a comma as separator between host and port.
                $dbhost = $CFG->dbhost;
                if (!empty($dboptions['dbport'])) {
                    $dbhost .= ',' . $dboptions['dbport'];
                }
                set_config('dbhost', $dbhost, 'enrol_database');
                break;

            default:
                throw new exception('Unknown database driver '.get_class($DB));
        }
    }

    public function tearDown(): void {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new xmldb_table('enrol_database_test_enrols');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        $table = new xmldb_table('enrol_database_test_courses');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        ini_set('error_log', $this->oldlog);
        $this->oldlog = null;
    }

    protected function assertIsEnrolled(stdClass $user, stdClass $course, int $status = null, ?array $roles) {
        global $DB;

        $sql = <<<EOF
    SELECT ue.id, ue.status
      FROM {user_enrolments} ue
      JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid AND e.enrol = 'database'
     WHERE ue.userid = :userid
EOF;

        $enrolment = $DB->get_record_sql(
            $sql,
            [
                'userid' => $user->id,
                'courseid' => $course->id,
            ]
        );
        $this->assertNotFalse($enrolment);

        if ($status !== null) {
            $this->assertEquals($status, (int) $enrolment->status);
        }

        $this->assertHasRoleAssignment($user, $course, $roles);
    }

    protected function assertHasRoleAssignment(stdClass $user, stdClass $course, ?array $roles) {
        global $DB;

        $coursecontext = context_course::instance($course->id);
        if ($roles === null) {
            $sql = <<<EOF
    SELECT ra.id
      FROM {role_assignments} ra
      JOIN {role} r ON r.id = ra.roleid
      JOIN {enrol} e ON e.id = ra.itemid AND e.courseid = :courseid AND e.enrol = 'database'
     WHERE ra.userid = :userid
EOF;
            $this->assertFalse($DB->record_exists_sql(
                $sql,
                [
                    'userid' => $user->id,
                    'courseid' => $course->id,
                ]
            ));
        } else {
            $sql = <<<EOF
    SELECT ra.id
      FROM {role_assignments} ra
      JOIN {role} r ON r.id = ra.roleid
      JOIN {enrol} e ON e.id = ra.itemid AND e.courseid = :courseid AND e.enrol = 'database'
     WHERE ra.userid = :userid
       AND r.shortname = :rolename
EOF;
            foreach ($roles as $rolename) {
                $this->assertTrue($DB->record_exists_sql(
                    $sql,
                    [
                        'userid' => $user->id,
                        'courseid' => $course->id,
                        'rolename' => $rolename
                    ]
                ));
            }
        }
    }

    protected function assertIsNotEnrolled(stdClass $user, stdClass $course) {
        global $DB;

        $sql = <<<EOF
    SELECT ue.id, ue.status
      FROM {user_enrolments} ue
      JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid AND e.enrol = 'database'
     WHERE ue.userid = :userid
EOF;

        $enrolment = $DB->get_record_sql(
            $sql,
            [
                'userid' => $user->id,
                'courseid' => $course->id,
            ]
        );
        $this->assertFalse($enrolment);
    }

    protected function assertEnrolmentCount(stdClass $course, int $count) {
        global $DB;

        $sql = <<<EOF
   SELECT ue.userid
     FROM {user_enrolments} ue
     JOIN {enrol} e ON e.id = ue.enrolid
    WHERE e.courseid = :courseid AND e.enrol = 'database' 
EOF;

        $this->assertCount(
            $count,
            $DB->get_records_sql(
                $sql,
                [
                    'courseid' => $course->id,
                ]
            )
        );
    }

    protected function setup_user_tables(): void {
        global $CFG, $DB;
        $dbman = $DB->get_manager();

        $table = new xmldb_table('enrol_database_test_enrols');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_CHAR, '255', null, null, null);
        $table->add_field('userid', XMLDB_TYPE_CHAR, '255', null, null, null);
        $table->add_field('roleid', XMLDB_TYPE_CHAR, '255', null, null, null);
        $table->add_field('otheruser', XMLDB_TYPE_CHAR, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        $dbman->create_table($table);
        set_config('remoteenroltable', "{$CFG->prefix}enrol_database_test_enrols", 'enrol_database');
        set_config('remotecoursefield', 'courseid', 'enrol_database');
        set_config('remoteuserfield', 'userid', 'enrol_database');
        set_config('remoterolefield', 'roleid', 'enrol_database');
        set_config('remoteotheruserfield', 'otheruser', 'enrol_database');
    }

    protected function setup_course_tables(bool $setcategory = false): void {
        global $CFG, $DB;
        $dbman = $DB->get_manager();

        $table = new xmldb_table('enrol_database_test_courses');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('fullname', XMLDB_TYPE_CHAR, '255', null, null, null);
        $table->add_field('shortname', XMLDB_TYPE_CHAR, '255', null, null, null);
        $table->add_field('idnumber', XMLDB_TYPE_CHAR, '255', null, null, null);
        $table->add_field('category', XMLDB_TYPE_CHAR, '255', null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        $dbman->create_table($table);
        set_config('newcoursetable', "{$CFG->prefix}enrol_database_test_courses", 'enrol_database');
        set_config('newcoursefullname', 'fullname', 'enrol_database');
        set_config('newcourseshortname', 'shortname', 'enrol_database');
        set_config('newcourseidnumber', 'idnumber', 'enrol_database');
        set_config('newcoursecategory', 'category', 'enrol_database');

        if ($setcategory) {
            set_config('newcoursecategory', 'category', 'enrol_database');
        }
    }

    /**
     * Create a set of sample courses.
     *
     * @param   int $count
     * @return  array
     */
    protected function create_sample_courses(int $count = 5): array {
        global $DB;

        $this->resetAfterTest(true);

        // Create test tables.
        $this->setup_course_tables();

        // Insert sample data.
        for ($i = 0; $i < $count; $i++) {
            $DB->insert_record('enrol_database_test_courses', (object) [
                'fullname' => "Course {$i}",
                'shortname' => "course_{$i}",
                'idnumber' => "remotecourse_{$i}",
            ]);
        }
        return $DB->get_records('enrol_database_test_courses');
    }

    /**
     * Create a set of sample users in the specified courses.
     *
     * @param   array $courses
     * @param   int $countpercourse
     * @return  array
     */
    protected function create_sample_users(array $courses, int $countpercourse = 5): array {
        global $DB;

        $this->resetAfterTest(true);

        // Create test tables.
        $this->setup_user_tables();

        // Create some sample users.
        // Create coursecount * countpercourse users.
        $users = [];
        for ($i = 1; $i <= (count($courses) * $countpercourse); $i++) {
            $users[$i] = $this->getDataGenerator()->create_user([
                'username' => "username{$i}",
                'idnumber' => "remoteuser_{$i}",
                'email' => "remoteuser{$i}@example.com",
            ]);
        }

        // Assign users to courses.
        // Note: There is some course overlap.
        $courseusers = [];
        foreach (array_values($courses) as $courseindex => $course) {
            $courseusers[$course->idnumber] = [];
            for ($i = 1; $i <= $countpercourse; $i++) {
                $user = $users[$i * ($courseindex + 1)];
                $DB->insert_record('enrol_database_test_enrols', (object) [
                    'courseid' => $course->idnumber,
                    'userid' => $user->idnumber,
                ]);
                $courseusers[$course->idnumber][$user->idnumber] = $user;
            }
        }

        return $courseusers;
    }

    /**
     * Create a set of sample courses in the specified courses.
     *
     * @param   array $courses
     * @param   int $countpercourse
     * @return  array
     */
    protected function create_local_courses(int $count): array {
        $courses = [];
        for ($i = 1; $i <= $count; $i++) {
            $courses[$i] = $this->getDataGenerator()->create_course(['idnumber' => "nc{$i}"]);
        };

        return $courses;
    }

    protected function create_local_users(int $count): array {
        $users = [];
        for ($i = 1; $i <= $count; $i++) {
            $users[$i] = $this->getDataGenerator()->create_user(['idnumber' => "su{$i}"]);
        };

        return $users;
    }

    /**
     * Ensure that syncing of courses handles the existence of duplicate course data.
     */
    public function test_sync_courses_ignore_duplicates(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_course_tables();

        // Get generic data.
        $courses = $this->get_remote_course_data(6);

        // Make the shortname for courses 3 and 4 the same.
        $courses[3]['shortname'] = 'xx';
        $courses[4]['shortname'] = 'xx';

        // Make the idnumber for courses 5 and 6 the same.
        $courses[5]['idnumber'] = 'yy';
        $courses[6]['idnumber'] = 'yy';

        foreach ($courses as $course) {
            $DB->insert_record('enrol_database_test_courses', $course);
        }

        // When I perform a course sync.
        $trace = new null_progress_trace();
        $plugin = enrol_get_plugin('database');
        $plugin->sync_courses($trace);

        // Then the number of courses with an idnumber should match the external courses.
        $this->assertCount(
            4,
            $DB->get_records_sql("SELECT id FROM {course} WHERE idnumber <>''")
        );

        // Courses 1 and 2 should exist.
        $this->assertTrue($DB->record_exists('course', $courses[1]));
        $this->assertTrue($DB->record_exists('course', $courses[2]));

        // Behaviour is undefined when duplicates exist.
        // That means that either 3 or 4, and either 5, or 6 should exist.
        $this->assertEquals(1, $DB->count_records('course', ['idnumber' => 'yy']));
        $this->assertEquals(1, $DB->count_records('course', ['shortname' => 'xx']));
    }

    /**
     * Ensure that syncing of courses creates the correct number of course sections.
     */
    public function test_sync_courses_course_sections_match(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_course_tables();

        $courses = $this->get_remote_course_data(2);
        foreach ($courses as $course) {
            $DB->insert_record('enrol_database_test_courses', $course);
        }

        // When I perform a course sync.
        $trace = new null_progress_trace();
        $plugin = enrol_get_plugin('database');
        $plugin->sync_courses($trace);

        // Then the number of courses with an idnumber should match the external courses.
        $syncedcourses = $DB->get_records_sql("SELECT * FROM {course} WHERE idnumber <>''");
        $this->assertCount(
            2,
            $syncedcourses
        );

        // Check default number of sections matches with the created course sections.
        $courseconfig = get_config('moodlecourse');
        foreach ($syncedcourses as $course) {
            $numsections = $DB->count_records('course_sections', ['course' => $course->id]);

            // Note: To compare numsections we have to add topic 0 to default numsections.
            $this->assertEquals(($courseconfig->numsections + 1), $numsections);
        }
    }

    /**
     * Ensure that syncing of courses creates courses in a category by categoryid.
     */
    public function test_sync_courses_category_not_specified(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_course_tables();

        $plugin = enrol_get_plugin('database');

        // Unset the newcoursecategory value.
        $plugin->set_config('newcoursecategory', '');

        // Create course data.
        $courses = $this->get_remote_course_data(2);

        foreach ($courses as $course) {
            $DB->insert_record('enrol_database_test_courses', $course);
        }

        // When I perform a course sync.
        $trace = new null_progress_trace();
        $plugin->sync_courses($trace);

        // Then the number of courses with an idnumber should match the external courses.
        $syncedcourses = $DB->get_records_sql("SELECT * FROM {course} WHERE idnumber <>''");
        $this->assertCount(
            2,
            $syncedcourses
        );

        // Courses 1 and 2 should match and use the default category.
        $defcat = $DB->get_record('course_categories', ['id' => $plugin->get_config('defaultcategory')]);
        $courses[1]['category'] = $defcat->id;
        $courses[2]['category'] = $defcat->id;
        $this->assertTrue($DB->record_exists('course', $courses[1]));
        $this->assertTrue($DB->record_exists('course', $courses[2]));
    }

    /**
     * Ensure that syncing of courses creates courses in a category by categoryid.
     */
    public function test_sync_courses_category_by_id(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_course_tables();

        $plugin = enrol_get_plugin('database');

        // Set the localcategoryfield to 'id'.
        $plugin->set_config('localcategoryfield', 'id');

        // Create course data.
        $courses = $this->get_remote_course_data(4);

        $coursecat = $this->getDataGenerator()->create_category(['name' => 'Test category 1', 'idnumber' => 'tcid1']);
        $defcat = $DB->get_record('course_categories', ['id' => $plugin->get_config('defaultcategory')]);
        $courses[1]['category'] = $coursecat->id;
        $courses[2]['category'] = $defcat->id;
        $courses[3]['category'] = null;
        foreach ($courses as $course) {
            $DB->insert_record('enrol_database_test_courses', $course);
        }

        // When I perform a course sync.
        $trace = new null_progress_trace();
        $plugin->sync_courses($trace);

        // Then the number of courses with an idnumber should match the external courses.
        $syncedcourses = $DB->get_records_sql("SELECT * FROM {course} WHERE idnumber <>''");
        $this->assertCount(
            4,
            $syncedcourses
        );

        // Courses 1 and 2 should match.
        $this->assertTrue($DB->record_exists('course', $courses[1]));
        $this->assertTrue($DB->record_exists('course', $courses[2]));

        // Course 3 should match with the category set to default category.
        $courses[3]['category'] = $defcat->id;
        $this->assertTrue($DB->record_exists('course', $courses[3]));

        // Course 4 should also match, and the category should match the default category.
        $this->assertTrue($DB->record_exists('course', $courses[3]));
        $this->assertEquals($defcat->id, $DB->get_field('course', 'category', $courses[3]));

    }

    /**
     * Ensure that syncing of courses creates courses in a category by categoryid.
     */
    public function test_sync_courses_category_invalid_idnumber(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_course_tables();
        $plugin = enrol_get_plugin('database');

        // Set the localcategoryfield to 'idnumber'.
        $plugin->set_config('localcategoryfield', 'idnumber');

        // Create course data.
        $courses = $this->get_remote_course_data(2);

        $courses[2]['category'] = 'someinvalidcategoryidnumber';

        foreach ($courses as $course) {
            $DB->insert_record('enrol_database_test_courses', $course);
        }

        // When I perform a course sync.
        $trace = new null_progress_trace();
        $plugin->sync_courses($trace);

        // Then the course with an invalid category idnumber should not have been created.
        $syncedcourses = $DB->get_records_sql("SELECT * FROM {course} WHERE idnumber <>''");
        $this->assertCount(
            1,
            $syncedcourses
        );

        // Course 1 should exist and match and use the default category.
        $defcat = $DB->get_record('course_categories', ['id' => $plugin->get_config('defaultcategory')]);
        $courses[1]['category'] = $defcat->id;
        $this->assertTrue($DB->record_exists('course', $courses[1]));

        // Course 2 should not exist.
        unset($courses[2]['category']);
        $this->assertFalse($DB->record_exists('course', $courses[2]));
    }

    /**
     * Ensure that syncing of courses creates courses in a category by category idnumber.
     */
    public function test_sync_courses_category_by_idnumber(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_course_tables();

        $plugin = enrol_get_plugin('database');

        // Set the localcategoryfield to 'idnumber'.
        $plugin->set_config('localcategoryfield', 'idnumber');

        // Create course data.
        $courses = $this->get_remote_course_data(2);

        $coursecat = $this->getDataGenerator()->create_category(['name' => 'Test category 1', 'idnumber' => 'tcid1']);
        $defcat = $DB->get_record('course_categories', ['id' => $plugin->get_config('defaultcategory')]);
        $courses[2]['category'] = $coursecat->idnumber;
        foreach ($courses as $course) {
            $DB->insert_record('enrol_database_test_courses', $course);
        }

        // When I perform a course sync.
        $trace = new null_progress_trace();
        $plugin->sync_courses($trace);

        // Then the number of courses with an idnumber should match the external courses.
        $syncedcourses = $DB->get_records_sql("SELECT * FROM {course} WHERE idnumber <>''");
        $this->assertCount(
            2,
            $syncedcourses
        );

        // Courses 1 should match.
        $this->assertTrue($DB->record_exists('course', $courses[1]));

        // Course 2 should match with the category set to default category.
        $courses[2]['category'] = $coursecat->id;
        $this->assertTrue($DB->record_exists('course', $courses[2]));
    }

    /**
     * Ensure that syncing of courses creates courses using the course template.
     */
    public function test_sync_courses_with_course_template(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_course_tables();

        $plugin = enrol_get_plugin('database');

        // Set the localcategoryfield to 'idnumber'.
        $plugin->set_config('localcategoryfield', 'idnumber');

        // Set the template course.
        $template = $this->getDataGenerator()->create_course([
            'numsections' => 666,
            'shortname' => 'crstempl',
        ]);
        $plugin->set_config('templatecourse', 'crstempl');

        // Create course data.
        $courses = $this->get_remote_course_data(2);

        $coursecat = $this->getDataGenerator()->create_category(['name' => 'Test category 1', 'idnumber' => 'tcid1']);
        $defcat = $DB->get_record('course_categories', ['id' => $plugin->get_config('defaultcategory')]);
        $courses[2]['category'] = $coursecat->idnumber;
        foreach ($courses as $course) {
            $DB->insert_record('enrol_database_test_courses', $course);
        }

        // When I perform a course sync.
        $trace = new null_progress_trace();
        $plugin->sync_courses($trace);

        // Then the number of courses with an idnumber should match the external courses.
        $syncedcourses = $DB->get_records_sql("SELECT * FROM {course} WHERE idnumber <>''");
        $this->assertCount(
            2,
            $syncedcourses
        );

        // Both courses should match.
        $this->assertTrue($DB->record_exists('course', $courses[1]));

        $courses[2]['category'] = $coursecat->id;
        $this->assertTrue($DB->record_exists('course', $courses[2]));

        // Both courses should have the same number of sections as the template course.
        $this->assertEquals(
            666,
            course_get_format($DB->get_record('course', $courses[1]))->get_last_section_number()
        );

        $this->assertEquals(
            666,
            course_get_format($DB->get_record('course', $courses[2]))->get_last_section_number()
        );
    }

    /**
     * Ensure that syncing of enrolments creates user enrolments.
     */
    public function test_sync_users_basic(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_user_tables();

        $plugin = enrol_get_plugin('database');

        // Create some local courses and users for sync.
        $courses = $this->create_local_courses(4);
        $users = $this->create_local_users(4);

        // Given I have remote user enrolment data.
        $this->enrol_user_in_remote_course('su1', 'nc1');
        $this->enrol_user_in_remote_course('su1', 'nc2');
        $this->enrol_user_in_remote_course('su2', 'nc1');
        $this->enrol_user_in_remote_course('su3', 'nc3');

        // When I perform a user sync.
        $trace = new null_progress_trace();
        $plugin->sync_enrolments($trace);

        // Then Users su1, and su2 should be enrolled in course 1.
        $this->assertEnrolmentCount($courses[1], 2);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_ACTIVE, ['student']);
        $this->assertIsEnrolled($users[2], $courses[1], ENROL_USER_ACTIVE, ['student']);


        // And User su1 should be enrolled in course 2.
        $this->assertEnrolmentCount($courses[2], 1);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_ACTIVE, ['student']);

        // And User su3 should be enrolled in course 3.
        $this->assertEnrolmentCount($courses[3], 1);
        $this->assertIsEnrolled($users[3], $courses[3], ENROL_USER_ACTIVE, ['student']);

        // But No user should be enrolled in course 4.
        $this->assertEnrolmentCount($courses[4], 0);
    }

    /**
     * Ensure that syncing of enrolments creates user enrolments.
     */
    public function test_sync_users_with_role(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_user_tables();

        $plugin = enrol_get_plugin('database');

        // Create some local courses and users for sync.
        $courses = $this->create_local_courses(4);
        $users = $this->create_local_users(2);

        // Given I have remote user enrolment data.
        $this->enrol_user_in_remote_course('su1', 'nc1', 'student');
        $this->enrol_user_in_remote_course('su2', 'nc1', 'teacher');
        $this->enrol_user_in_remote_course('su1', 'nc2', 'student');
        $this->enrol_user_in_remote_course('su1', 'nc2', 'teacher');

        // When I perform a user sync.
        $trace = new null_progress_trace();
        $plugin->sync_enrolments($trace);

        // Then Users su1, and su2 should be enrolled in course 1.
        $this->assertEnrolmentCount($courses[1], 2);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_ACTIVE, ['student']);
        $this->assertIsEnrolled($users[2], $courses[1], ENROL_USER_ACTIVE, ['teacher']);

        // And User su1 should be enrolled in course 2 and both a student and teacher.
        $this->assertEnrolmentCount($courses[2], 1);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_ACTIVE, ['student', 'teacher']);

        // But No user should be enrolled in course 3.
        $this->assertEnrolmentCount($courses[3], 0);
    }

    /**
     * Ensure that syncing of enrolments does not remove any settings with ENROL_EXT_REMOVED_KEEP.
     */
    public function test_sync_users_keep(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_user_tables();

        $plugin = enrol_get_plugin('database');
        $plugin->set_config('unenrolaction', ENROL_EXT_REMOVED_KEEP);

        // Create some local courses and users for sync.
        $courses = $this->create_local_courses(4);
        $users = $this->create_local_users(2);

        // Given I have remote user enrolment data.
        $this->enrol_user_in_remote_course('su1', 'nc1', 'student');
        $this->enrol_user_in_remote_course('su2', 'nc1', 'teacher');
        $this->enrol_user_in_remote_course('su1', 'nc2', 'student');
        $this->enrol_user_in_remote_course('su1', 'nc2', 'teacher');

        // When I perform a user sync.
        $trace = new null_progress_trace();
        $plugin->sync_enrolments($trace);

        // When I reset the remote system.
        $DB->delete_records('enrol_database_test_enrols');

        // And I perform a sync
        $plugin->sync_enrolments($trace);

        // Then Users su1, and su2 should be enrolled in course 1.
        $this->assertEnrolmentCount($courses[1], 2);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_ACTIVE, ['student']);
        $this->assertIsEnrolled($users[2], $courses[1], ENROL_USER_ACTIVE, ['teacher']);

        // And User su1 should be enrolled in course 2 and both a student and teacher.
        $this->assertEnrolmentCount($courses[2], 1);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_ACTIVE, ['student', 'teacher']);

        // But No user should be enrolled in course 3.
        $this->assertEnrolmentCount($courses[3], 0);
    }

    /**
     * Ensure that syncing of enrolments does not remove any settings with ENROL_EXT_REMOVED_SUSPEND.
     */
    public function test_sync_users_suspend_removing_roles(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_user_tables();

        $plugin = enrol_get_plugin('database');
        $plugin->set_config('unenrolaction', ENROL_EXT_REMOVED_SUSPENDNOROLES);

        // Create some local courses and users for sync.
        $courses = $this->create_local_courses(4);
        $users = $this->create_local_users(2);

        // Given I have remote user enrolment data.
        $this->enrol_user_in_remote_course('su1', 'nc1', 'student');
        $this->enrol_user_in_remote_course('su2', 'nc1', 'teacher');
        $this->enrol_user_in_remote_course('su1', 'nc2', 'student');
        $this->enrol_user_in_remote_course('su1', 'nc2', 'teacher');

        // When I perform a user sync.
        $trace = new null_progress_trace();
        $plugin->sync_enrolments($trace);

        // And I remove enrolment for user1.
        $DB->delete_records('enrol_database_test_enrols', ['userid' => $users[1]->idnumber]);

        // And I perform a sync
        $plugin->sync_enrolments($trace);

        // Then Users su1, and su2 should be enrolled in course 1.
        $this->assertEnrolmentCount($courses[1], 2);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_SUSPENDED, []);
        $this->assertIsEnrolled($users[2], $courses[1], ENROL_USER_ACTIVE, ['teacher']);

        // And User su1 should be enrolled in course 2 and both a student and teacher.
        $this->assertEnrolmentCount($courses[2], 1);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_SUSPENDED, []);

        // But No user should be enrolled in course 3.
        $this->assertEnrolmentCount($courses[3], 0);
    }

    /**
     * Ensure that syncing of enrolments does not remove any settings with ENROL_EXT_REMOVED_SUSPEND.
     */
    public function test_sync_users_suspend(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_user_tables();

        $plugin = enrol_get_plugin('database');
        $plugin->set_config('unenrolaction', ENROL_EXT_REMOVED_SUSPEND);

        // Create some local courses and users for sync.
        $courses = $this->create_local_courses(4);
        $users = $this->create_local_users(2);

        // Given I have remote user enrolment data.
        $this->enrol_user_in_remote_course('su1', 'nc1', 'student');
        $this->enrol_user_in_remote_course('su2', 'nc1', 'teacher');
        $this->enrol_user_in_remote_course('su1', 'nc2', 'student');
        $this->enrol_user_in_remote_course('su1', 'nc2', 'teacher');

        // When I perform a user sync.
        $trace = new null_progress_trace();
        $plugin->sync_enrolments($trace);

        // And I remove enrolment for user1.
        $DB->delete_records('enrol_database_test_enrols', ['userid' => $users[1]->idnumber]);

        // And I perform a sync
        $plugin->sync_enrolments($trace);

        // Then Users su1, and su2 should be enrolled in course 1.
        $this->assertEnrolmentCount($courses[1], 2);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_SUSPENDED, ['student']);
        $this->assertIsEnrolled($users[2], $courses[1], ENROL_USER_ACTIVE, ['teacher']);

        // And User su1 should be enrolled in course 2 and both a student and teacher.
        $this->assertEnrolmentCount($courses[2], 1);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_SUSPENDED, ['student', 'teacher']);

        // But No user should be enrolled in course 3.
        $this->assertEnrolmentCount($courses[3], 0);
    }

    /**
     * Ensure that syncing of enrolments does not remove any settings with ENROL_EXT_REMOVED_UNENROL.
     */
    public function test_sync_users_unenrol(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_user_tables();

        $plugin = enrol_get_plugin('database');
        $plugin->set_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);

        // Create some local courses and users for sync.
        $courses = $this->create_local_courses(4);
        $users = $this->create_local_users(2);

        // Given I have remote user enrolment data.
        $this->enrol_user_in_remote_course('su1', 'nc1', 'student');
        $this->enrol_user_in_remote_course('su2', 'nc1', 'teacher');
        $this->enrol_user_in_remote_course('su1', 'nc2', 'student');
        $this->enrol_user_in_remote_course('su1', 'nc2', 'teacher');

        // When I perform a user sync.
        $trace = new null_progress_trace();
        $plugin->sync_enrolments($trace);

        // And I remove enrolment for user1.
        $DB->delete_records('enrol_database_test_enrols', ['userid' => $users[1]->idnumber]);

        // And I perform a sync
        $plugin->sync_enrolments($trace);

        // Then the user enrolments will be cleared.
        // Then Users su1, and su2 should be enrolled in course 1.
        $this->assertEnrolmentCount($courses[1], 1);
        $this->assertIsNotEnrolled($users[1], $courses[1]);
        $this->assertIsEnrolled($users[2], $courses[1], ENROL_USER_ACTIVE, ['teacher']);

        // And User su1 should be enrolled in course 2 and both a student and teacher.
        $this->assertEnrolmentCount($courses[2], 0);
        $this->assertIsNotEnrolled($users[1], $courses[2]);

        // But No user should be enrolled in course 3.
        $this->assertEnrolmentCount($courses[3], 0);
    }

    /**
     * Ensure that syncing of enrolments creates user enrolments when courses are mapped by course id.
     */
    public function test_sync_users_by_course_id(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_user_tables();

        $plugin = enrol_get_plugin('database');

        // Create some local courses and users for sync.
        $courses = $this->create_local_courses(4);
        $users = $this->create_local_users(2);

        // Given I have remote user enrolment data.
        $this->enrol_user_in_remote_course('su1', $courses[1]->id, 'student');
        $this->enrol_user_in_remote_course('su2', $courses[1]->id, 'teacher');
        $this->enrol_user_in_remote_course('su1', $courses[2]->id, 'student');
        $this->enrol_user_in_remote_course('su1', $courses[2]->id, 'teacher');

        // And the course mapping field is set to 'id'.
        $plugin->set_config('localcoursefield', 'id');

        // When I perform a user sync.
        $trace = new null_progress_trace();
        $plugin->sync_enrolments($trace);

        // Then Users su1, and su2 should be enrolled in course 1.
        $this->assertEnrolmentCount($courses[1], 2);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_ACTIVE, ['student']);
        $this->assertIsEnrolled($users[2], $courses[1], ENROL_USER_ACTIVE, ['teacher']);

        // And User su1 should be enrolled in course 2 and both a student and teacher.
        $this->assertEnrolmentCount($courses[2], 1);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_ACTIVE, ['student', 'teacher']);

        // But No user should be enrolled in course 3.
        $this->assertEnrolmentCount($courses[3], 0);
    }

    /**
     * Ensure that syncing of enrolments creates user enrolments when users are mapped by user id.
     */
    public function test_sync_users_by_user_id(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_user_tables();

        $plugin = enrol_get_plugin('database');

        // Create some local courses and users for sync.
        $courses = $this->create_local_courses(4);
        $users = $this->create_local_users(2);

        // Given I have remote user enrolment data.
        $this->enrol_user_in_remote_course($users[1]->id, 'nc1', 'student');
        $this->enrol_user_in_remote_course($users[2]->id, 'nc1', 'teacher');
        $this->enrol_user_in_remote_course($users[1]->id, 'nc2', 'student');
        $this->enrol_user_in_remote_course($users[1]->id, 'nc2', 'teacher');

        // And the course mapping field is set to 'id'.
        $plugin->set_config('localuserfield', 'id');

        // When I perform a user sync.
        $trace = new null_progress_trace();
        $plugin->sync_enrolments($trace);

        // Then Users su1, and su2 should be enrolled in course 1.
        $this->assertEnrolmentCount($courses[1], 2);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_ACTIVE, ['student']);
        $this->assertIsEnrolled($users[2], $courses[1], ENROL_USER_ACTIVE, ['teacher']);

        // And User su1 should be enrolled in course 2 and both a student and teacher.
        $this->assertEnrolmentCount($courses[2], 1);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_ACTIVE, ['student', 'teacher']);

        // But No user should be enrolled in course 3.
        $this->assertEnrolmentCount($courses[3], 0);
    }

    /**
     * Ensure that syncing of enrolments creates role enrolments when roles are mapped by role id.
     */
    public function test_sync_users_by_role_id(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_user_tables();

        $plugin = enrol_get_plugin('database');

        // Create some local courses and users for sync.
        $courses = $this->create_local_courses(4);
        $users = $this->create_local_users(2);
        $roles = $DB->get_records_menu('role', [], '', 'shortname, id');

        // Given I have remote user enrolment data.
        $this->enrol_user_in_remote_course('su1', 'nc1', $roles['student']);
        $this->enrol_user_in_remote_course('su2', 'nc1', $roles['teacher']);
        $this->enrol_user_in_remote_course('su1', 'nc2', $roles['student']);
        $this->enrol_user_in_remote_course('su1', 'nc2', $roles['teacher']);

        // And the role mapping field is set to 'id'.
        $plugin->set_config('localrolefield', 'id');

        // When I perform a user sync.
        $trace = new null_progress_trace();
        $plugin->sync_enrolments($trace);

        // Then Users su1, and su2 should be enrolled in course 1.
        $this->assertEnrolmentCount($courses[1], 2);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_ACTIVE, ['student']);
        $this->assertIsEnrolled($users[2], $courses[1], ENROL_USER_ACTIVE, ['teacher']);

        // And User su1 should be enrolled in course 2 and both a student and teacher.
        $this->assertEnrolmentCount($courses[2], 1);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_ACTIVE, ['student', 'teacher']);

        // But No user should be enrolled in course 3.
        $this->assertEnrolmentCount($courses[3], 0);
    }

    /**
     * Ensure that syncing of enrolments creates user enrolments.
     */
    public function test_sync_users_single_course(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_user_tables();

        $plugin = enrol_get_plugin('database');

        // Create some local courses and users for sync.
        $courses = $this->create_local_courses(4);
        $users = $this->create_local_users(2);

        // Given I have remote user enrolment data.
        $this->enrol_user_in_remote_course('su1', 'nc1', 'student');
        $this->enrol_user_in_remote_course('su2', 'nc1', 'teacher');
        $this->enrol_user_in_remote_course('su1', 'nc2', 'student');
        $this->enrol_user_in_remote_course('su1', 'nc2', 'teacher');
        $this->enrol_user_in_remote_course('su1', 'nc3', 'student');

        // When I perform a user sync of course nc1 only.
        $trace = new null_progress_trace();
        $plugin->sync_enrolments($trace, $courses[1]->id);

        // Then Users su1, and su2 should be enrolled in course 1.
        $this->assertEnrolmentCount($courses[1], 2);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_ACTIVE, ['student']);
        $this->assertIsEnrolled($users[2], $courses[1], ENROL_USER_ACTIVE, ['teacher']);

        // But no users should be enrolled in course 2.
        $this->assertEnrolmentCount($courses[2], 0);

        // And no users should be enrolled in course 2.
        $this->assertEnrolmentCount($courses[3], 0);

        // When I perform a user sync of course nc2 only.
        $trace = new null_progress_trace();
        $plugin->sync_enrolments($trace, $courses[2]->id);

        // Then no change should be made to course 1 enrolments.
        $this->assertEnrolmentCount($courses[1], 2);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_ACTIVE, ['student']);
        $this->assertIsEnrolled($users[2], $courses[1], ENROL_USER_ACTIVE, ['teacher']);

        // And no users should be enrolled in course 2.
        $this->assertEnrolmentCount($courses[2], 1);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_ACTIVE, ['student', 'teacher']);

        // And no users should be enrolled in course 2.
        $this->assertEnrolmentCount($courses[3], 0);
    }

    /**
     * Ensure that syncing of enrolments for one user creates user enrolments for only that user.
     */
    public function test_sync_user_enrolments_basic(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_user_tables();

        $plugin = enrol_get_plugin('database');

        // Create some local courses and users for sync.
        $courses = $this->create_local_courses(4);
        $users = $this->create_local_users(2);

        // Given I have remote user enrolment data.
        $this->enrol_user_in_remote_course('su1', 'nc1', 'student');
        $this->enrol_user_in_remote_course('su2', 'nc1', 'teacher');
        $this->enrol_user_in_remote_course('su1', 'nc2', 'student');
        $this->enrol_user_in_remote_course('su1', 'nc2', 'teacher');

        // When I perform a user sync.
        $plugin->sync_user_enrolments($users[1]);

        // Then Users su1, and su2 should be enrolled in course 1.
        $this->assertEnrolmentCount($courses[1], 1);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_ACTIVE, ['student']);
        $this->assertIsNotEnrolled($users[2], $courses[1]);

        // And User su1 should be enrolled in course 2 and both a student and teacher.
        $this->assertEnrolmentCount($courses[2], 1);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_ACTIVE, ['student', 'teacher']);

        // But No user should be enrolled in course 3.
        $this->assertEnrolmentCount($courses[3], 0);

        // When I perform another user sync.
        $plugin->sync_user_enrolments($users[1]);

        // Then there should be no changes.
        $this->assertEnrolmentCount($courses[1], 1);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_ACTIVE, ['student']);
        $this->assertIsNotEnrolled($users[2], $courses[1]);
        $this->assertEnrolmentCount($courses[2], 1);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_ACTIVE, ['student', 'teacher']);
        $this->assertEnrolmentCount($courses[3], 0);
    }

    /**
     * Ensure that syncing of enrolments for one user processes unenrolments as required.
     */
    public function test_sync_user_enrolments_unenrol_keep(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_user_tables();

        $plugin = enrol_get_plugin('database');

        // Create some local courses and users for sync.
        $courses = $this->create_local_courses(2);
        $users = $this->create_local_users(2);

        // Given I have remote user enrolment data.
        $this->enrol_user_in_remote_course('su1', 'nc1', 'student');
        $this->enrol_user_in_remote_course('su2', 'nc1', 'teacher');
        $this->enrol_user_in_remote_course('su1', 'nc2', 'student');
        $this->enrol_user_in_remote_course('su1', 'nc2', 'teacher');

        // And the unenrolaction is to keep enrolments.
        $plugin->set_config('unenrolaction', ENROL_EXT_REMOVED_KEEP);

        // When I perform a user sync.
        $plugin->sync_enrolments(new null_progress_trace());

        // Then Users su1, and su2 should be enrolled in course 1.
        $this->assertEnrolmentCount($courses[1], 2);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_ACTIVE, ['student']);
        $this->assertIsEnrolled($users[2], $courses[1], ENROL_USER_ACTIVE, ['teacher']);

        // And User su1 should be enrolled in course 2 and both a student and teacher.
        $this->assertEnrolmentCount($courses[2], 1);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_ACTIVE, ['student', 'teacher']);

        // When I clear the remote enrolments table.
        $DB->delete_records('enrol_database_test_enrols');

        // And I perform another user sync.
        $plugin->sync_user_enrolments($users[1]);

        // Then all enrolments should remain.
        $this->assertEnrolmentCount($courses[1], 2);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_ACTIVE, ['student']);
        $this->assertIsEnrolled($users[2], $courses[1], ENROL_USER_ACTIVE, ['teacher']);
        $this->assertEnrolmentCount($courses[2], 1);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_ACTIVE, ['student', 'teacher']);
    }

    /**
     * Ensure that syncing of enrolments for one user processes unenrolments as required.
     */
    public function test_sync_user_enrolments_unenrol_suspend(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_user_tables();

        $plugin = enrol_get_plugin('database');

        // Create some local courses and users for sync.
        $courses = $this->create_local_courses(2);
        $users = $this->create_local_users(2);

        // Given I have remote user enrolment data.
        $this->enrol_user_in_remote_course('su1', 'nc1', 'student');
        $this->enrol_user_in_remote_course('su2', 'nc1', 'teacher');
        $this->enrol_user_in_remote_course('su1', 'nc2', 'student');
        $this->enrol_user_in_remote_course('su1', 'nc2', 'teacher');

        // And the unenrolaction is to suspend enrolments.
        $plugin->set_config('unenrolaction', ENROL_EXT_REMOVED_SUSPEND);

        // When I perform a user sync.
        $plugin->sync_enrolments(new null_progress_trace());

        // Then Users su1, and su2 should be enrolled in course 1.
        $this->assertEnrolmentCount($courses[1], 2);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_ACTIVE, ['student']);
        $this->assertIsEnrolled($users[2], $courses[1], ENROL_USER_ACTIVE, ['teacher']);

        // And User su1 should be enrolled in course 2 and both a student and teacher.
        $this->assertEnrolmentCount($courses[2], 1);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_ACTIVE, ['student', 'teacher']);

        // When I clear the remote enrolments table.
        $DB->delete_records('enrol_database_test_enrols');

        // And I perform another user sync.
        $plugin->sync_user_enrolments($users[1]);

        // Then all enrolments should remain.
        $this->assertEnrolmentCount($courses[1], 2);
        $this->assertEnrolmentCount($courses[2], 1);

        // But all enrolments for user1 should be suspended.
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_SUSPENDED, ['student']);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_SUSPENDED, ['student', 'teacher']);

        // And all enrolments for user2 should remain unchanged.
        $this->assertIsEnrolled($users[2], $courses[1], ENROL_USER_ACTIVE, ['teacher']);
    }

    /**
     * Ensure that syncing of enrolments for one user processes unenrolments as required.
     */
    public function test_sync_user_enrolments_unenrol_suspendnoroles(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_user_tables();

        $plugin = enrol_get_plugin('database');

        // Create some local courses and users for sync.
        $courses = $this->create_local_courses(2);
        $users = $this->create_local_users(2);

        // Given I have remote user enrolment data.
        $this->enrol_user_in_remote_course('su1', 'nc1', 'student');
        $this->enrol_user_in_remote_course('su2', 'nc1', 'teacher');
        $this->enrol_user_in_remote_course('su1', 'nc2', 'student');
        $this->enrol_user_in_remote_course('su1', 'nc2', 'teacher');

        // And the unenrolaction is to suspend enrolments.
        $plugin->set_config('unenrolaction', ENROL_EXT_REMOVED_SUSPENDNOROLES);

        // When I perform a user sync.
        $plugin->sync_enrolments(new null_progress_trace());

        // Then Users su1, and su2 should be enrolled in course 1.
        $this->assertEnrolmentCount($courses[1], 2);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_ACTIVE, ['student']);
        $this->assertIsEnrolled($users[2], $courses[1], ENROL_USER_ACTIVE, ['teacher']);

        // And User su1 should be enrolled in course 2 and both a student and teacher.
        $this->assertEnrolmentCount($courses[2], 1);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_ACTIVE, ['student', 'teacher']);

        // When I clear the remote enrolments table.
        $DB->delete_records('enrol_database_test_enrols');

        // And I perform another user sync.
        $plugin->sync_user_enrolments($users[1]);

        // Then all enrolments should remain.
        $this->assertEnrolmentCount($courses[1], 2);
        $this->assertEnrolmentCount($courses[2], 1);

        // But all enrolments for user1 should be suspended.
        // And roles removed.
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_SUSPENDED, []);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_SUSPENDED, []);

        // And all enrolments for user2 should remain unchanged.
        $this->assertIsEnrolled($users[2], $courses[1], ENROL_USER_ACTIVE, ['teacher']);
    }

    /**
     * Ensure that syncing of enrolments for one user processes unenrolments as required.
     */
    public function test_sync_user_enrolments_unenrol_remove(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_user_tables();

        $plugin = enrol_get_plugin('database');

        // Create some local courses and users for sync.
        $courses = $this->create_local_courses(2);
        $users = $this->create_local_users(2);

        // Given I have remote user enrolment data.
        $this->enrol_user_in_remote_course('su1', 'nc1', 'student');
        $this->enrol_user_in_remote_course('su2', 'nc1', 'teacher');
        $this->enrol_user_in_remote_course('su1', 'nc2', 'student');
        $this->enrol_user_in_remote_course('su1', 'nc2', 'teacher');

        // And the unenrolaction is to unenrol enrolments.
        $plugin->set_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);

        // When I perform a user sync.
        $plugin->sync_enrolments(new null_progress_trace());

        // Then Users su1, and su2 should be enrolled in course 1.
        $this->assertEnrolmentCount($courses[1], 2);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_ACTIVE, ['student']);
        $this->assertIsEnrolled($users[2], $courses[1], ENROL_USER_ACTIVE, ['teacher']);

        // And User su1 should be enrolled in course 2 and both a student and teacher.
        $this->assertEnrolmentCount($courses[2], 1);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_ACTIVE, ['student', 'teacher']);

        // When I clear the remote enrolments table.
        $DB->delete_records('enrol_database_test_enrols');

        // And I perform another user sync.
        $plugin->sync_user_enrolments($users[1]);

        // Then only enrolments for user 2 should remain.
        $this->assertEnrolmentCount($courses[1], 1);
        $this->assertEnrolmentCount($courses[2], 0);

        // But all enrolments for user1 should be suspended.
        // And roles removed.
        $this->assertIsNotEnrolled($users[1], $courses[1]);
        $this->assertIsNotEnrolled($users[1], $courses[2]);

        // And all enrolments for user2 should remain unchanged.
        $this->assertIsEnrolled($users[2], $courses[1], ENROL_USER_ACTIVE, ['teacher']);
    }

    /**
     * Ensure that syncing of enrolments for one user creates user enrolments for the correct course when using the
     * course id as a mapping field.
     */
    public function test_sync_user_enrolments_by_course_id(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_user_tables();

        $plugin = enrol_get_plugin('database');

        // Create some local courses and users for sync.
        $courses = $this->create_local_courses(4);
        $users = $this->create_local_users(2);

        // Given I have remote user enrolment data.
        $this->enrol_user_in_remote_course('su1', $courses[1]->id, 'student');
        $this->enrol_user_in_remote_course('su2', $courses[1]->id, 'teacher');
        $this->enrol_user_in_remote_course('su1', $courses[2]->id, 'student');
        $this->enrol_user_in_remote_course('su1', $courses[2]->id, 'teacher');

        // And I set the localcoursefield to id.
        $plugin->set_config('localcoursefield', 'id');

        // When I perform a user sync.
        $plugin->sync_user_enrolments($users[1]);

        // Then Users su1, and su2 should be enrolled in course 1.
        $this->assertEnrolmentCount($courses[1], 1);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_ACTIVE, ['student']);
        $this->assertIsNotEnrolled($users[2], $courses[1]);

        // And User su1 should be enrolled in course 2 and both a student and teacher.
        $this->assertEnrolmentCount($courses[2], 1);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_ACTIVE, ['student', 'teacher']);

        // But No user should be enrolled in course 3.
        $this->assertEnrolmentCount($courses[3], 0);

        // When I perform another user sync.
        $plugin->sync_user_enrolments($users[1]);

        // Then there should be no changes.
        $this->assertEnrolmentCount($courses[1], 1);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_ACTIVE, ['student']);
        $this->assertIsNotEnrolled($users[2], $courses[1]);
        $this->assertEnrolmentCount($courses[2], 1);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_ACTIVE, ['student', 'teacher']);
        $this->assertEnrolmentCount($courses[3], 0);
    }

    /**
     * Ensure that syncing of enrolments for one user creates user enrolments for the correct course when using the
     * user id as a mapping field.
     */
    public function test_sync_user_enrolments_by_user_id(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_user_tables();

        $plugin = enrol_get_plugin('database');

        // Create some local courses and users for sync.
        $courses = $this->create_local_courses(4);
        $users = $this->create_local_users(2);

        // Given I have remote user enrolment data.
        $this->enrol_user_in_remote_course($users[1]->id, 'nc1', 'student');
        $this->enrol_user_in_remote_course($users[2]->id, 'nc1', 'teacher');
        $this->enrol_user_in_remote_course($users[1]->id, 'nc2', 'student');
        $this->enrol_user_in_remote_course($users[1]->id, 'nc2', 'teacher');

        // And I set the localcoursefield to id.
        $plugin->set_config('localuserfield', 'id');

        // When I perform a user sync.
        $plugin->sync_user_enrolments($users[1]);

        // Then Users su1, and su2 should be enrolled in course 1.
        $this->assertEnrolmentCount($courses[1], 1);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_ACTIVE, ['student']);
        $this->assertIsNotEnrolled($users[2], $courses[1]);

        // And User su1 should be enrolled in course 2 and both a student and teacher.
        $this->assertEnrolmentCount($courses[2], 1);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_ACTIVE, ['student', 'teacher']);

        // But No user should be enrolled in course 3.
        $this->assertEnrolmentCount($courses[3], 0);

        // When I perform another user sync.
        $plugin->sync_user_enrolments($users[1]);

        // Then there should be no changes.
        $this->assertEnrolmentCount($courses[1], 1);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_ACTIVE, ['student']);
        $this->assertIsNotEnrolled($users[2], $courses[1]);
        $this->assertEnrolmentCount($courses[2], 1);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_ACTIVE, ['student', 'teacher']);
        $this->assertEnrolmentCount($courses[3], 0);
    }

    /**
     * Ensure that syncing of enrolments for one user creates user enrolments for the correct course when using the
     * course id as a mapping field.
     */
    public function test_sync_user_enrolments_by_role_id(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->preventResetByRollback();

        // Set the base settings for course sync and create the course tables.
        $this->setup_base_db_settings();
        $this->setup_user_tables();

        $plugin = enrol_get_plugin('database');

        // Create some local courses and users for sync.
        $courses = $this->create_local_courses(4);
        $users = $this->create_local_users(2);
        $roles = $DB->get_records_menu('role', [], '', 'shortname, id');

        // Given I have remote user enrolment data.
        $this->enrol_user_in_remote_course('su1', 'nc1', $roles['student']);
        $this->enrol_user_in_remote_course('su2', 'nc1', $roles['teacher']);
        $this->enrol_user_in_remote_course('su1', 'nc2', $roles['student']);
        $this->enrol_user_in_remote_course('su1', 'nc2', $roles['teacher']);

        // And I set the localrolefield to id.
        $plugin->set_config('localrolefield', 'id');

        // When I perform a user sync.
        $plugin->sync_user_enrolments($users[1]);

        // Then Users su1, and su2 should be enrolled in course 1.
        $this->assertEnrolmentCount($courses[1], 1);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_ACTIVE, ['student']);
        $this->assertIsNotEnrolled($users[2], $courses[1]);

        // And User su1 should be enrolled in course 2 and both a student and teacher.
        $this->assertEnrolmentCount($courses[2], 1);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_ACTIVE, ['student', 'teacher']);

        // But No user should be enrolled in course 3.
        $this->assertEnrolmentCount($courses[3], 0);

        // When I perform another user sync.
        $plugin->sync_user_enrolments($users[1]);

        // Then there should be no changes.
        $this->assertEnrolmentCount($courses[1], 1);
        $this->assertIsEnrolled($users[1], $courses[1], ENROL_USER_ACTIVE, ['student']);
        $this->assertIsNotEnrolled($users[2], $courses[1]);
        $this->assertEnrolmentCount($courses[2], 1);
        $this->assertIsEnrolled($users[1], $courses[2], ENROL_USER_ACTIVE, ['student', 'teacher']);
        $this->assertEnrolmentCount($courses[3], 0);
    }

    /**
     * Get basic sample data for a course to be created in the remote table.
     *
     * Note: Just gets the data for insertion into the table but does not insert.
     *
     * @param   int $count
     * @return  array
     */
    protected function get_remote_course_data(int $count): array {
        $courses = [];
        for ($i = 1; $i <= $count; $i++) {
            $courses[$i] = [
                'fullname' => "New course {$i}",
                'shortname' => "nc{$i}",
                'idnumber' => "ncid{$i}",
            ];
        }

        return $courses;
    }

    /**
     * Enrol a user into a course on the remote database used for testing.
     *
     * @param   string $useridnumber
     * @param   string $courseidnumber
     * @param   string $role
     */
    protected function enrol_user_in_remote_course(string $useridnumber, string $courseidnumber, ?string $role = null): void {
        global $DB;

        $enrolment = (object) [
            'userid' => $useridnumber,
            'courseid' => $courseidnumber,
        ];

        if ($role !== null) {
            $enrolment->roleid = $role;
        }

        $DB->insert_record('enrol_database_test_enrols', $enrolment);
    }

}
