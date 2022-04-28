<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace contenttype_document\form;

/**
 * Content type document form is defined here.
 *
 * @package     contenttype_document
 * @copyright   2021 Daniel Neis Araujo <daniel@adapta.online>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Content type document form class.
 *
 * @package     contenttype_document
 * @copyright   2021 Daniel Neis Araujo <daniel@adapta.online>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editor extends \core_contentbank\form\edit_content {
 
    public function definition() {
        global $CFG, $DB, $OUTPUT;

        parent::definition();
 
        $mform =& $this->_form;

        // Id of the content to edit.
        $id = $this->_customdata['id'];
        $content = $DB->get_record('contentbank_content', ['id' => $id]);

        // Set required customfields.
        $breadcrumb = \core_contentbank\contentbank::make_breadcrumb($content->folderid, $content->contextid);
        if (!empty($breadcrumb)) {
            $requiredfields = [];
            if ($breadcrumb[0]['name'] == 'Professores') {
                $requiredfields = [
                    'customfield_content_name',
                    'customfield_mark',
                ];
                $fieldstoremove = [
                  'nickname',
                  'authors',
                  'pagecount',
                  'releasedate',
                  'reviewdate',
                  'collection',
                  'institution',
                  'officialcodes',
                  'officialname',
                  'description_editor',
                  'teachinggoals_editor',
                  'theme',
                  'geozone',
                  'startyear',
                  'casetype',
                  'business',
                  'businesssize',
                  'occupationarea',
                  'businessorigin',
                  'department',
                  'translation',
                  'notes_editor',
                  'ieseexclusive',
                  'temporary',
              ];
              foreach ($fieldstoremove as $f) {
                  $mform->removeElement('customfield_' . $f);
              }
            } else if ($breadcrumb[0]['name'] == 'Programas Específicos') {
                $requiredfields = [
                    'customfield_content_name',
                    'customfield_doctype',
                    'customfield_language',
                    'customfield_collection',
                    'customfield_institution',
                    'customfield_officialname',
                    'customfield_translation',
                    'customfield_ieseexclusive',
                    'customfield_mark',
                ];
            } else if (($breadcrumb[0]['name'] == 'Todos os Programas') || ($breadcrumb[0]['name'] == 'Provisórios')) {
                $requiredfields = [
                    'customfield_code',
                    'customfield_content_name',
                    'customfield_nickname',
                    'customfield_authors',
                    'customfield_doctype',
                    'customfield_area',
                    'customfield_professor',
                    'customfield_language',
                    'customfield_pagecount',
                    'customfield_releasedate',
                    'customfield_reviewdate',
                    'customfield_collection',
                    'customfield_institution',
                    'customfield_officialcodes',
                    'customfield_officialname',
                    'customfield_translation',
                    'customfield_ieseexclusive',
                    'customfield_mark',
                    'customfield_temporary',
                ];
            }
            foreach ($requiredfields as $elementname) {
               $mform->addRule($elementname, null, 'required', null, 'client');
            }
        }

        $options = \contenttype_document\content::get_potential_linked_content($id);
        $content->linkedcontent = array_keys(\contenttype_document\content::get_linked_content($id));

        $mform->addElement('header', 'linkedcontentheader', get_string('linkedcontent', 'contenttype_document'));
        $autocomplete = $mform->addElement(
            'autocomplete',
            'linkedcontent',
            get_string('linkedcontent', 'contenttype_document'),
            $options,
            ['multiple' => true]
        );

        if (\core_tag_tag::is_enabled('contenttype_document', 'contentbank_content')) {
            $mform->addElement('header', 'tagshdr', get_string('tags', 'tag'));
            $mform->addElement('tags', 'tags', get_string('tags'),
                    array('itemtype' => 'contentbank_content', 'component' => 'contenttype_document'));
            $content->tags = \core_tag_tag::get_item_tags_array('contenttype_document', 'contentbank_content', $id);
        }

        $this->set_data($content);
 
        $this->add_action_buttons();
    }

    /**
     * Modify or create a Document content from the form data.
     *
     * @param stdClass $data Form data to create or modify a Document content.
     *
     * @return int The id of the edited or created content.
     */
    public function save_content(\stdClass $data): int {
        global $DB;
        $handler = \core_contentbank\customfield\content_handler::create();
        $handler->instance_form_save($data, true);
        $context = \context::instance_by_id($data->contextid);
        if (isset($data->tags)) {
            \core_tag_tag::set_item_tags('contenttype_document', 'contentbank_content', $data->id, $context, $data->tags);
        }
        if (isset($data->linkedcontent)) {
            \contenttype_document\content::save_linked_content($data->id, $data->linkedcontent);
        }
        return $data->id;
    }
}
