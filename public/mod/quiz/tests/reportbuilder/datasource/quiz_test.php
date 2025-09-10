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

use core_reportbuilder_generator;
use core_reportbuilder\local\filters\{boolean_select, date, select, text, number};
use core_reportbuilder\tests\core_reportbuilder_testcase;
use core_question_generator;
use mod_quiz_generator;
use question_engine;
use mod_quiz\quiz_settings;

/**
 * Unit tests for quiz datasource
 *
 * @package     mod_quiz
 * @covers      \mod_quiz\reportbuilder\datasource\quiz
 * @copyright   2025 Thiago Livramento <thiago@adapta.online>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class quiz_test extends core_reportbuilder_testcase {

    /**
     * Test default datasource
     */
    public function test_datasource_default(): void {
        global $DB;
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->assertNotEmpty($studentrole);
        $this->assertTrue(enrol_try_internal_enrol($course->id, $user1->id, $studentrole->id));
        
        $usertimes = [];

        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');

        // Basic quiz settings

        $quiz = $quizgenerator->create_instance(['course' => $course->id, 'timeclose' => 1200, 'timelimit' => 600]);
        $attemptid = $DB->insert_record('quiz_attempts', ['quiz' => $quiz->id, 'userid' => $user1->id, 'state' => 'inprogress',
                'timestart' => 100, 'timecheckstate' => 0, 'layout' => '', 'uniqueid' => $this->usage_id($quiz)]);
        $usertimes[$attemptid] = ['timeclose' => 1200, 'timelimit' => 600, 'message' => 'Test1A', 'time1000state' => 'finished'];
        
        $attemptid = $DB->insert_record('quiz_attempts', ['quiz' => $quiz->id, 'userid' => $user2->id, 'state' => 'inprogress',
                'timestart' => 100, 'timecheckstate' => 0, 'layout' => '', 'uniqueid' => $this->usage_id($quiz)]);
        $usertimes[$attemptid] = ['timeclose' => 1200, 'timelimit' => 600, 'message' => 'Test1A', 'time1000state' => 'finished'];
        
        // Compute expected end time for each attempt.
        foreach ($usertimes as $attemptid => $times) {
            $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);

            if ($times['timeclose'] > 0 && $times['timelimit'] > 0) {
                $usertimes[$attemptid]['timedue'] = min($times['timeclose'], $attempt->timestart + $times['timelimit']);
            } else if ($times['timeclose'] > 0) {
                $usertimes[$attemptid]['timedue'] = $times['timeclose'];
            } else if ($times['timelimit'] > 0) {
                $usertimes[$attemptid]['timedue'] = $attempt->timestart + $times['timelimit'];
            }
        }
        quiz_update_open_attempts(['courseid' => $course->id]);
        /** @var core_reportbuilder_generator $generator */ 
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Quiz', 'source' => quiz::class, 'default' => 1]);

        $content = $this->get_custom_report_content($report->get('id'));

        // Default columns .
        $courseoneurl = course_get_url($course);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $quizurl = new \moodle_url('/mod/quiz/view.php', ['id'=> $cm->id]);

        $this->assertEquals([
            ["<a href=\"{$courseoneurl}\">{$course->fullname}</a>",
             "<a href=\"{$quizurl}\">{$quiz->name}</a>"],
        ], array_map('array_values', $content));
    }

    /**
     * Make any old question usage for a quiz.
     *
     * The attempts used in test_bulk_update_functions must have some
     * question usage to store in uniqueid, but they don't have to be
     * very realistic.
     *
     * @param \stdClass $quiz
     * @return int question usage id.
     */
    protected function usage_id(\stdClass $quiz): int {
        $quba = question_engine::make_questions_usage_by_activity('mod_quiz',
                \context_module::instance($quiz->cmid));
        $quba->set_preferred_behaviour('deferredfeedback');
        question_engine::save_questions_usage_by_activity($quba);
        return $quba->get_id();
    }

    /**
     * Test datasource columns that aren't add by default
     */
    public function test_datasource_non_default_columns(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $this->resetAfterTest(true);
        
        // Make a user to do the quiz.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        // Make a quiz.
        $timeopen = time();
        $timeclose = time() + 100;

        $navmethod = QUIZ_NAVMETHOD_FREE;
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(['course' => $course->id,
            'grade' => 100.0, 'sumgrades' => 2, 'navmethod' => $navmethod, 'timeopen' => $timeopen, 'timeclose' => $timeclose]);

        $quizobj = quiz_settings::create((int)$quiz->id, (int)$user->id);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        $matchq = $questiongenerator->create_question('match', null, ['category' => $cat->id]);
        $description = $questiongenerator->create_question('description', null, ['category' => $cat->id]);

        // Add them to the quiz.
        quiz_add_quiz_question($saq->id, $quiz);
        quiz_add_quiz_question($numq->id, $quiz);
        quiz_add_quiz_question($matchq->id, $quiz);
        quiz_add_quiz_question($description->id, $quiz);

        // Start the attempt.
        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, false, $user->id);

        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);

        quiz_attempt_save_started($quizobj, $quba, $attempt);

        // Process some responses from the student.
        $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);

        $tosubmit = [1 => ['answer' => 'frog'],
                          2 => ['answer' => '3.14']];

        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        $tosubmit = [
            3 => [
                'frog' => 'amphibian',
                'cat' => 'mammal',
                'newt' => '',
            ],
        ];

        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        $tosubmit = [
            3 => [
                'frog' => 'amphibian',
                'cat' => 'mammal',
                'newt' => 'amphibian',
            ],
        ];

        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);
        
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Quiz', 'source' => quiz::class, 'default' => 0]);

        //Quiz
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'quiz:namewithlink']);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'quiz:timeopen']);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'quiz:timeclose']);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'quiz:timelimit']);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'quiz:grade']);
        
        //Quiz grades
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'quiz_grades:grade']);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'quiz_grades:timemodified']);

        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'user:fullnamewithlink']);

        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(1, $content);

        // Map values.
        [
            $quiznamewithlink,
            $quiztimeopen,
            $quiztimeclose,
            $quiztimelimit,
            $quizgrade,
            $quizgradesgrade,
            $quizgradestimemodified,
            $userfullnamewithlink,
        ] = array_values($content[0]);

        // Example assertions – ajuste conforme os dados reais de teste.
        $this->assertStringContainsString('<a href=', $quiznamewithlink);
        $this->assertEquals(userdate($timeopen), $quiztimeopen);
        $this->assertEquals(userdate($timeclose), $quiztimeclose);
        $this->assertNotEmpty(format_time($quiz->timelimit));
        $this->assertEquals(format_float($quiz->grade, 2), $quizgrade);
        $this->assertNotEmpty($quizgradesgrade); // Ajuste conforme o valor de nota no teste.
        $this->assertNotEmpty($quizgradestimemodified);
        $this->assertStringContainsString('<a href=', $userfullnamewithlink);


    }

    /**
     * Data provider for {@see test_datasource_filters}
     *
     * @return array[]
     */

    public static function datasource_filters_provider(): array {
        return [
            //quiz.
            'Quiz name' => ['quiz:nameselector', [
               'quiz:nameselector_operator' => text::IS_EQUAL_TO,
               'quiz:nameselector_value' => 'Quiz 1',
            ], true],
            'Quiz name (no match)' => ['quiz:nameselector', [
               'quiz:nameselector_operator' => text::IS_EQUAL_TO,
               'quiz:nameselector_value' => 'teste',
            ], false],
            'Quiz grade' => ['quiz:grade', [
               'quiz:grade_operator' => number::EQUAL_TO,
               'quiz:grade_value1' => 100.0,
            ], true],
            'Quiz grade (no match)' => ['quiz:grade', [
               'quiz:grade_operator' => number::EQUAL_TO,
               'quiz:grade_value1' => 8,
            ], false],
            'Quiz timeopen' => ['quiz:timeopen', [
               'quiz:timeopen_operator' => date::DATE_RANGE,
               'quiz:timeopen_from' => 1000,
            ], true],
            'Quiz timeopen (no match)' => ['quiz:timeopen', [
               'quiz:timeopen_operator' => date::DATE_RANGE,
               'quiz:timeopen_from' => 1200,
            ], false],
            'Quiz timeclose' => ['quiz:timeclose', [
               'quiz:timeclose_operator' => date::DATE_RANGE,
               'quiz:timeclose_from' => 4600,
            ], true],
            'Quiz timeclose (no match)' => ['quiz:timeclose', [
               'quiz:timeclose_operator' => date::DATE_RANGE,
               'quiz:timeclose_from' => 5000,
            ], false],

            //Quiz grades.
            'Quiz grades grade' => ['quiz_grades:grade',[
               'quiz_grades:grade_operator' => number::EQUAL_TO,
               'quiz_grades:grade_value1' => 100,
            ], true],
            'Quiz grades grade (no match)' => ['quiz_grades:grade',[
               'quiz_grades:grade_operator' => number::EQUAL_TO,
               'quiz_grades:grade_value1' => 7,
            ], false],
       ];
    }

    /**
     * Test datasource filters
     *
     * @param string $filtername
     * @param array $filtervalues
     * @param bool $expectmatch
     *
     * @dataProvider datasource_filters_provider
     */
    public function test_datasource_filters(
       string $filtername,
       array $filtervalues,
       bool $expectmatch
    ): void {
        global $DB;
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user(['username' => 'usertest']);
        $course = $this->getDataGenerator()->create_course();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->assertNotEmpty($studentrole);
        $this->assertTrue(enrol_try_internal_enrol($course->id, $user1->id, $studentrole->id));

        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');

        $quiz = $quizgenerator->create_instance(['course' => $course->id, 'questionsperpage' => 0, 'grade' => 100.0,
                                                      'sumgrades' => 3, 'timeopen' => 1000, 'timeclose' => 4600]);

        // Create a couple of questions.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');

        $cat = $questiongenerator->create_question_category();
        $saq = $questiongenerator->create_question('shortanswer', null, ['category' => $cat->id]);
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        $matchq = $questiongenerator->create_question('match', null, ['category' => $cat->id]);
        $description = $questiongenerator->create_question('description', null, ['category' => $cat->id]);

        // Add them to the quiz.
        quiz_add_quiz_question($saq->id, $quiz);
        quiz_add_quiz_question($numq->id, $quiz);
        quiz_add_quiz_question($matchq->id, $quiz);
        quiz_add_quiz_question($description->id, $quiz);

        //Make a user to do the quiz
        $quizobj = quiz_settings::create((int)$quiz->id, (int) $user1->id);

        // Start the attempt.
        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, false, $user1->id);

        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);

        quiz_attempt_save_started($quizobj, $quba, $attempt);

        // Process some responses from the student.
        $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);

        $tosubmit = [1 => ['answer' => 'frog'],
                          2 => ['answer' => '3.14']];

        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        $tosubmit = [
            3 => [
                'frog' => 'amphibian',
                'cat' => 'mammal',
                'newt' => '',
            ],
        ];

        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        $tosubmit = [
            3 => [
                'frog' => 'amphibian',
                'cat' => 'mammal',
                'newt' => 'amphibian',
            ],
        ];

        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);

        // Finish the attempt.
        $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);
        
        // Generator report and create report.
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Quiz', 'source' => quiz::class, 'default' => 0]);
        
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'quiz:name']);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'quiz_grades:grade']);
        $generator->create_column(['reportid' => $report->get('id'), 'uniqueidentifier' => 'quiz_grades:timemodified']);
        
        // Add filter, set it's values.
        $generator->create_filter(['reportid' => $report->get('id'), 'uniqueidentifier' => $filtername]);
        $content = $this->get_custom_report_content(
            reportid: $report->get('id'),
            filtervalues: $filtervalues,
        );
        if ($expectmatch) {
            $this->assertEquals('Quiz 1', reset($content[0]));
        } else {
            $this->assertEmpty($content);
        }
    }

    /**
     * Stress test datasource
     *
     * In order to exevute this test PHPUNIT_LONGTEST should be defined as true in phpunit.xml or directly in config.php
     */
    public function test_stress_datasource(): void {
        if (!PHPUNIT_LONGTEST) {
        $this->markTestSkipped('PHPUNIT_LONGTEST is not defined');
    }
  }
}
