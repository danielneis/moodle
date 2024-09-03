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
 * Content bank global search unit tests.
 *
 * @package     core_contentbank
 * @copyright   2022 Daniel Neis Araujo <danielneis@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/search/tests/fixtures/testable_core_search.php');

use contenttype_testable\contenttype as contenttype;

/**
 * Provides the unit tests for content bank global search.
 *
 * @package     core_contentbank
 * @copyright   2022 Daniel Neis Araujo <danielneis@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_contentbank_search_testcase extends advanced_testcase {

    /**
     * @var string Area id
     */
    protected $contentsareaid = null;

    public function setUp(): void {
        $this->resetAfterTest(true);
        set_config('enableglobalsearch', true);

        $this->contentsareaid = \core_search\manager::generate_areaid('core_contentbank', 'content');

        // Set \core_search::instance to the mock_search_engine as we don't require the search engine to be working to test this.
        $search = testable_core_search::instance();
    }

    /**
     * Indexing contents.
     *
     * @return void
     */
    public function test_contents_indexing() {

        // Returns the instance as long as the area is supported.
        $searcharea = \core_search\manager::get_search_area($this->contentsareaid);
        $this->assertInstanceOf('\core_contentbank\search\content', $searcharea);

        $contenttype = new contenttype(context_system::instance());

        $record = new stdClass();
        $record->name = 'Content example 1';
        $record->contextid = context_system::instance()->id;
        $content = $contenttype->create_content($record);

        $record->name = 'Content example 2';
        $content = $contenttype->create_content($record);

        // All records.
        $recordset = $searcharea->get_recordset_by_timestamp(0);
        $this->assertTrue($recordset->valid());
        $nrecords = 0;
        foreach ($recordset as $record) {
            $this->assertInstanceOf('stdClass', $record);
            $doc = $searcharea->get_document($record);
            $this->assertInstanceOf('\core_search\document', $doc);
            $nrecords++;
        }
        // If there would be an error/failure in the foreach above the recordset would be closed on shutdown.
        $recordset->close();
        $this->assertEquals(2, $nrecords);

        // The +2 is to prevent race conditions.
        $recordset = $searcharea->get_recordset_by_timestamp(time() + 2);

        // No new records.
        $this->assertFalse($recordset->valid());
        $recordset->close();
    }

    /**
     * Tests course indexing support for contexts.
     */
    public function test_contents_indexing_contexts() {
        global $DB, $USER, $SITE;

        $searcharea = \core_search\manager::get_search_area($this->contentsareaid);

        $user = self::getDataGenerator()->create_user();

        // Create some content.
        $record = new stdClass();
        $record->name = 'Test content 1';
        $record->configdata = '';
        $record->usercreated = $user->id;

        $contenttype = new contenttype(context_system::instance());
        $content1 = $contenttype->create_content($record);

        $generator = $this->getDataGenerator();
        $cat1 = $generator->create_category();
        $cat2 = $generator->create_category();
        $course1 = $generator->create_course(['category' => $cat1->id]);

        $coursecontext = context_course::instance($course1->id);
        $record->name = 'Test content 2';

        $contenttype = new contenttype($coursecontext);
        $content2 = $contenttype->create_content($record);

        // Check with system context and null - should return all the content.
        $systemcontext = context_system::instance();
        $results = self::recordset_to_ids($searcharea->get_document_recordset(0, $systemcontext));
        $this->assertEquals([$content1->get_id(), $content2->get_id()], $results);

        // Check with course context.
        $results = self::recordset_to_ids($searcharea->get_document_recordset(0, $coursecontext));
        $this->assertEquals([$content2->get_id()], $results);

        // Check with category context.
        $catcontext = context_coursecat::instance($cat1->id);
        $record->name = 'Test content 3';

        $contenttype = new contenttype($catcontext);
        $content3 = $contenttype->create_content($record);

        $results = self::recordset_to_ids($searcharea->get_document_recordset(0, $catcontext));
        $this->assertEqualsCanonicalizing([$content2->get_id(), $content3->get_id()], $results);

        // Check with another category context.
        $catcontext = context_coursecat::instance($cat2->id);
        $record->name = 'Test content 4';

        $contenttype = new contenttype($catcontext);
        $content4 = $contenttype->create_content($record);

        $results = self::recordset_to_ids($searcharea->get_document_recordset(0, $catcontext));
        $this->assertEquals([$content4->get_id()], $results);
    }

    /**
     * Utility function to convert recordset to array of IDs for testing.
     *
     * @param moodle_recordset $rs Recordset to convert (and close)
     * @return array Array of IDs from records indexed by number (0, 1, 2, ...)
     */
    protected static function recordset_to_ids(moodle_recordset $rs) {
        $results = [];
        foreach ($rs as $rec) {
            $results[] = $rec->id;
        }
        $rs->close();
        return $results;
    }

    /**
     * Document contents.
     *
     * @return void
     */
    public function test_contents_document() {

        // Returns the instance as long as the area is supported.
        $searcharea = \core_search\manager::get_search_area($this->contentsareaid);
        $this->assertInstanceOf('\core_contentbank\search\content', $searcharea);

        $generator = $this->getDataGenerator();
        $cat1 = $generator->create_category();
        $course1 = $generator->create_course(['category' => $cat1->id]);

        $user = self::getDataGenerator()->create_user();

        // Create content.
        $record = new stdClass();
        $record->name = 'Test content';
        $record->configdata = '';
        $record->contextid = context_system::instance()->id;
        $record->usercreated = $user->id;

        $contenttype = new contenttype(context_system::instance());
        $content = $contenttype->create_content($record);

        $doc = $searcharea->get_document($content->get_content());
        $this->assertInstanceOf('\core_search\document', $doc);
        $this->assertEquals($content->get_id(), $doc->get('itemid'));
        $this->assertEquals($this->contentsareaid . '-' . $content->get_id(), $doc->get('id'));
        $this->assertFalse($doc->is_set('userid'));
        $this->assertEquals(\core_search\manager::NO_OWNER_ID, $doc->get('owneruserid'));
        $this->assertEquals($content->get_name(), $doc->get('title'));
        $this->assertEquals($content->get_name(), $doc->get('content'));
    }

    /**
     * Document accesses.
     *
     * @return void
     */
    public function test_contents_access() {
        $this->resetAfterTest();

        // Returns the instance as long as the area is supported.
        $searcharea = \core_search\manager::get_search_area($this->contentsareaid);

        $contenttype = new contenttype(context_system::instance());

        $userauthor = self::getDataGenerator()->create_user();
        $userother = self::getDataGenerator()->create_user();

        $unlistedrecord = new stdClass();
        $unlistedrecord->visibility = \core_contentbank\content::VISIBILITY_UNLISTED;
        $unlistedrecord->usercreated = $userauthor->id;
        $unlistedrecord->contextid = \context_system::instance()->id;
        $unlistedcontent = $contenttype->create_content($unlistedrecord);

        $publicrecord = new stdClass();
        $publicrecord->visibility = \core_contentbank\content::VISIBILITY_PUBLIC;
        $publicrecord->usercreated = $userauthor->id;
        $publicrecord->contextid = \context_system::instance()->id;
        $publiccontent = $contenttype->create_content($publicrecord);

        $this->setUser($userother);
        $this->assertEquals(\core_search\manager::ACCESS_GRANTED, $searcharea->check_access($publiccontent->get_id()));
        $this->assertEquals(\core_search\manager::ACCESS_DENIED, $searcharea->check_access($unlistedcontent->get_id()));
        $this->assertEquals(\core_search\manager::ACCESS_DELETED, $searcharea->check_access(-123));

        $this->setUser($userauthor);
        $this->assertEquals(\core_search\manager::ACCESS_GRANTED, $searcharea->check_access($publiccontent->get_id()));
        $this->assertEquals(\core_search\manager::ACCESS_GRANTED, $searcharea->check_access($unlistedcontent->get_id()));

        $this->assertEquals(\core_search\manager::ACCESS_DELETED, $searcharea->check_access(-123));
    }

    /**
     * Test document icon for content bank area.
     */
    public function test_get_doc_icon_for_content_area() {
        $searcharea = \core_search\manager::get_search_area($this->contentsareaid);

        $document = $this->getMockBuilder('\core_search\document')
            ->disableOriginalConstructor()
            ->getMock();

        $result = $searcharea->get_doc_icon($document);

        $this->assertEquals('i/contentbank', $result->get_name());
        $this->assertEquals('moodle', $result->get_component());
    }
}
