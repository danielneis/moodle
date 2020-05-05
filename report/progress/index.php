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
 * Activity progress reports
 *
 * @package    report
 * @subpackage progress
 * @copyright  2008 Sam Marshall
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->libdir . '/completionlib.php');

define('COMPLETION_REPORT_PAGE', 25);

// Get course
$id = required_param('course',PARAM_INT);
$course = $DB->get_record('course',array('id'=>$id));
if (!$course) {
    print_error('invalidcourseid');
}
$context = context_course::instance($course->id);

// Sort (default lastname, optionally firstname)
$sort = optional_param('sort','',PARAM_ALPHA);
$firstnamesort = $sort == 'firstname';

// CSV format
$format = optional_param('format','',PARAM_ALPHA);
$excel = $format == 'excelcsv';
$csv = $format == 'csv' || $excel;

// Paging
$start   = optional_param('start', 0, PARAM_INT);
$sifirst = optional_param('sifirst', 'all', PARAM_NOTAGS);
$silast  = optional_param('silast', 'all', PARAM_NOTAGS);
$start   = optional_param('start', 0, PARAM_INT);

// Whether to show extra user identity information
$extrafields = get_extra_user_fields($context);
$leftcols = 1 + count($extrafields);

function csv_quote($value) {
    global $excel;
    if ($excel) {
        return core_text::convert('"'.str_replace('"',"'",$value).'"','UTF-8','UTF-16LE');
    } else {
        return '"'.str_replace('"',"'",$value).'"';
    }
}

$url = new moodle_url('/report/progress/index.php', array('course'=>$id));
if ($sort !== '') {
    $url->param('sort', $sort);
}
if ($format !== '') {
    $url->param('format', $format);
}
if ($start !== 0) {
    $url->param('start', $start);
}
if ($sifirst !== 'all') {
    $url->param('sifirst', $sifirst);
}
if ($silast !== 'all') {
    $url->param('silast', $silast);
}
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');

require_login($course);

// Check basic permission
require_capability('report/progress:view',$context);

// Get data on activities and progress of all users, and give error if we've
// nothing to display (no users or no activities)
$reportsurl = $CFG->wwwroot.'/course/report.php?id='.$course->id;
$completion = new completion_info($course);
$activities = $completion->get_activities();

if ($sifirst !== 'all') {
    set_user_preference('ifirst', $sifirst);
}
if ($silast !== 'all') {
    set_user_preference('ilast', $silast);
}

if (!empty($USER->preference['ifirst'])) {
    $sifirst = $USER->preference['ifirst'];
} else {
    $sifirst = 'all';
}

if (!empty($USER->preference['ilast'])) {
    $silast = $USER->preference['ilast'];
} else {
    $silast = 'all';
}

// Generate where clause
$where = array();
$where_params = array();

if ($sifirst !== 'all') {
    $where[] = $DB->sql_like('u.firstname', ':sifirst', false, false);
    $where_params['sifirst'] = $sifirst.'%';
}

if ($silast !== 'all') {
    $where[] = $DB->sql_like('u.lastname', ':silast', false, false);
    $where_params['silast'] = $silast.'%';
}

// Get the currently applied filters.
require_once($CFG->dirroot.'/user/lib.php');
$roleid       = optional_param('roleid', 0, PARAM_INT);
$groupparam   = optional_param('group', 0, PARAM_INT);
$filtersapplied = optional_param_array('unified-filters', [], PARAM_NOTAGS);
$filterwassubmitted = optional_param('unified-filter-submitted', 0, PARAM_BOOL);

// If they passed a role make sure they can view that role.
if ($roleid) {
    $viewableroles = get_profile_roles($context);

    // Check if the user can view this role.
    if (array_key_exists($roleid, $viewableroles)) {
        $filtersapplied[] = USER_FILTER_ROLE . ':' . $roleid;
    } else {
        $roleid = 0;
    }
}

// Default group ID.
$groupid = false;
$canaccessallgroups = has_capability('moodle/site:accessallgroups', $context);
if ($course->groupmode != NOGROUPS) {
    if ($canaccessallgroups) {
        // Change the group if the user can access all groups and has specified group in the URL.
        if ($groupparam) {
            $groupid = $groupparam;
        }
    } else {
        // Otherwise, get the user's default group.
        $groupid = groups_get_course_group($course, true);
        if ($course->groupmode == SEPARATEGROUPS && !$groupid) {
            // The user is not in the group so show message and exit.
            echo $OUTPUT->notification(get_string('notingroup'));
            echo $OUTPUT->footer();
            exit;
        }
    }
}
$hasgroupfilter = false;
$searchkeywords = [];
$enrolid = 0;
$userfields = get_extra_user_fields($context);
foreach ($filtersapplied as $index => $filter) {
    $filtervalue = explode(':', $filter, 2);
    $value = null;
    if (count($filtervalue) == 2) {
        $key = clean_param($filtervalue[0], PARAM_INT);
        $value = clean_param($filtervalue[1], PARAM_INT);
    } else {
        // Search string.
        $key = USER_FILTER_STRING;
        $value = clean_param($filtervalue[0], PARAM_TEXT);
    }

    switch ($key) {
        case USER_FILTER_ENROLMENT:
            $enrolid = $value;
            break;
        case USER_FILTER_GROUP:
            $groupid = $value;
            $hasgroupfilter = true;
            break;
        case USER_FILTER_ROLE:
            $roleid = $value;
            // Limit list to users with some role only.
            // We want to query both the current context and parent contexts.
            list($relatedctxsql, $relatedctxparams) = $DB->get_in_or_equal($context->get_parent_context_ids(true),
                SQL_PARAMS_NAMED, 'relatedctx');

            // Get users without any role.
            if ($roleid == -1) {
                $where[] = "u.id NOT IN (SELECT userid FROM {role_assignments} WHERE contextid $relatedctxsql)";
                $where_params = array_merge($where_params, $relatedctxparams);
            } else {
                $where[] = "u.id IN (SELECT userid FROM {role_assignments} WHERE roleid = :roleid AND contextid $relatedctxsql)";
                $where_params = array_merge($where_params, array('roleid' => $roleid), $relatedctxparams);
            }
            break;
        case USER_FILTER_STATUS:
            // We only accept active/suspended statuses.
            if ($value == ENROL_USER_ACTIVE || $value == ENROL_USER_SUSPENDED) {
                $status = $value;
            }
            break;
        default:
            // Search string.
            $searchkeywords[] = $value;

            $searchkey1 = 'search' . $index . '1';
            $searchkey2 = 'search' . $index . '2';
            $searchkey3 = 'search' . $index . '3';
            $searchkey4 = 'search' . $index . '4';
            $searchkey5 = 'search' . $index . '5';
            $searchkey6 = 'search' . $index . '6';
            $searchkey7 = 'search' . $index . '7';

            $conditions = array();
            // Search by fullname.
            $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
            $conditions[] = $DB->sql_like($fullname, ':' . $searchkey1, false, false);

            // Search by email.
            $email = $DB->sql_like('email', ':' . $searchkey2, false, false);
            if (!in_array('email', $userfields)) {
                $maildisplay = 'maildisplay' . $index;
                $userid1 = 'userid' . $index . '1';
                // Prevent users who hide their email address from being found by others
                // who aren't allowed to see hidden email addresses.
                $email = "(". $email ." AND (" .
                        "u.maildisplay <> :$maildisplay " .
                        "OR u.id = :$userid1". // User can always find himself.
                        "))";
                $where_params[$maildisplay] = core_user::MAILDISPLAY_HIDE;
                $where_params[$userid1] = $USER->id;
            }
            $conditions[] = $email;

            // Search by idnumber.
            $idnumber = $DB->sql_like('idnumber', ':' . $searchkey3, false, false);
            if (!in_array('idnumber', $userfields)) {
                $userid2 = 'userid' . $index . '2';
                // Users who aren't allowed to see idnumbers should at most find themselves
                // when searching for an idnumber.
                $idnumber = "(". $idnumber . " AND u.id = :$userid2)";
                $where_params[$userid2] = $USER->id;
            }
            $conditions[] = $idnumber;

            if (!empty($CFG->showuseridentity)) {
                // Search all user identify fields.
                $extrasearchfields = explode(',', $CFG->showuseridentity);
                foreach ($extrasearchfields as $extrasearchfield) {
                    if (in_array($extrasearchfield, ['email', 'idnumber', 'country'])) {
                        // Already covered above. Search by country not supported.
                        continue;
                    }
                    $param = $searchkey3 . $extrasearchfield;
                    $condition = $DB->sql_like($extrasearchfield, ':' . $param, false, false);
                    $where_params[$param] = "%$keyword%";
                    if (!in_array($extrasearchfield, $userfields)) {
                        // User cannot see this field, but allow match if their own account.
                        $userid3 = 'userid' . $index . '3' . $extrasearchfield;
                        $condition = "(". $condition . " AND u.id = :$userid3)";
                        $where_params[$userid3] = $USER->id;
                    }
                    $conditions[] = $condition;
                }
            }

            // Search by middlename.
            $middlename = $DB->sql_like('middlename', ':' . $searchkey4, false, false);
            $conditions[] = $middlename;

            // Search by alternatename.
            $alternatename = $DB->sql_like('alternatename', ':' . $searchkey5, false, false);
            $conditions[] = $alternatename;

            // Search by firstnamephonetic.
            $firstnamephonetic = $DB->sql_like('firstnamephonetic', ':' . $searchkey6, false, false);
            $conditions[] = $firstnamephonetic;

            // Search by lastnamephonetic.
            $lastnamephonetic = $DB->sql_like('lastnamephonetic', ':' . $searchkey7, false, false);
            $conditions[] = $lastnamephonetic;

            $keyword = $value;

            $where[] = "(". implode(" OR ", $conditions) .") ";
            $where_params[$searchkey1] = "%$keyword%";
            $where_params[$searchkey2] = "%$keyword%";
            $where_params[$searchkey3] = "%$keyword%";
            $where_params[$searchkey4] = "%$keyword%";
            $where_params[$searchkey5] = "%$keyword%";
            $where_params[$searchkey6] = "%$keyword%";
            $where_params[$searchkey7] = "%$keyword%";
            break;
    }
}

// If course supports groups we may need to set a default.
if (!empty($groupid)) {
    if ($canaccessallgroups) {
        // User can access all groups, let them filter by whatever was selected.
        $filtersapplied[] = USER_FILTER_GROUP . ':' . $groupid;
    } else if (!$filterwassubmitted && $course->groupmode == VISIBLEGROUPS) {
        // If we are in a course with visible groups and the user has not submitted anything and does not have
        // access to all groups, then set a default group.
        $filtersapplied[] = USER_FILTER_GROUP . ':' . $groupid;
    } else if (!$hasgroupfilter && $course->groupmode != VISIBLEGROUPS) {
        // The user can't access all groups and has not set a group filter in a course where the groups are not visible
        // then apply a default group filter.
        $filtersapplied[] = USER_FILTER_GROUP . ':' . $groupid;
    } else if (!$hasgroupfilter) { // No need for the group id to be set.
        $groupid = false;
    }
}

// Get user match count
$total = $completion->get_num_tracked_users(implode(' AND ', $where), $where_params, $groupid);

// Total user count
$grandtotal = $completion->get_num_tracked_users('', array(), $groupid);

// Get user data
$progress = array();

if ($total) {
    $progress = $completion->get_progress_all(
        implode(' AND ', $where),
        $where_params,
        $groupid,
        $firstnamesort ? 'u.firstname ASC, u.lastname ASC' : 'u.lastname ASC, u.firstname ASC',
        $csv ? 0 : COMPLETION_REPORT_PAGE,
        $csv ? 0 : $start,
        $context
    );
}

if ($csv && $grandtotal && count($activities)>0) { // Only show CSV if there are some users/actvs

    $shortname = format_string($course->shortname, true, array('context' => $context));
    header('Content-Disposition: attachment; filename=progress.'.
        preg_replace('/[^a-z0-9-]/','_',core_text::strtolower(strip_tags($shortname))).'.csv');
    // Unicode byte-order mark for Excel
    if ($excel) {
        header('Content-Type: text/csv; charset=UTF-16LE');
        print chr(0xFF).chr(0xFE);
        $sep="\t".chr(0);
        $line="\n".chr(0);
    } else {
        header('Content-Type: text/csv; charset=UTF-8');
        $sep=",";
        $line="\n";
    }
} else {

    // Navigation and header
    $strreports = get_string("reports");
    $strcompletion = get_string('pluginname', 'report_progress');

    $PAGE->set_title($course->shortname . ": " . $strcompletion);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->heading($strcompletion);
    $PAGE->requires->js_call_amd('report_progress/completion_override', 'init', [fullname($USER)]);

    // Handle groups (if enabled)
    groups_print_course_menu($course,$CFG->wwwroot.'/report/progress/?course='.$course->id);
}

if (count($activities)==0) {
    echo $OUTPUT->container(get_string('err_noactivities', 'completion'), 'errorbox errorboxcontent');
    echo $OUTPUT->footer();
    exit;
}

if ($groupid > 0 && ($course->groupmode != SEPARATEGROUPS || $canaccessallgroups)) {
    $grouprenderer = $PAGE->get_renderer('core_group');
    $groupdetailpage = new \core_group\output\group_details($groupid);
    echo $grouprenderer->group_details($groupdetailpage);
}

$baseurl = $PAGE->url;

// Render the unified filter.
if (!$csv) {
    $renderer = $PAGE->get_renderer('report_progress');
    echo $renderer->unified_filter($course, $context, $filtersapplied, $baseurl);
}

// Add filters to the baseurl after creating unified_filter to avoid losing them.
foreach (array_unique($filtersapplied) as $filterix => $filter) {
    $baseurl->param('unified-filters[' . $filterix . ']', $filter);
}

// Build link for paging
$link = $CFG->wwwroot.'/report/progress/?course='.$course->id;
if (strlen($sort)) {
    $link .= '&amp;sort='.$sort;
}
$link .= '&amp;start=';

$pagingbar = '';

// Initials bar.
$prefixfirst = 'sifirst';
$prefixlast = 'silast';

// The URL used in the initials bar should reset the 'start' parameter.
$initialsbarurl = new moodle_url($url);
$initialsbarurl->remove_params('start');

$pagingbar .= $OUTPUT->initials_bar($sifirst, 'firstinitial', get_string('firstname'), $prefixfirst, $initialsbarurl);
$pagingbar .= $OUTPUT->initials_bar($silast, 'lastinitial', get_string('lastname'), $prefixlast, $initialsbarurl);

// Do we need a paging bar?
if ($total > COMPLETION_REPORT_PAGE) {

    // Paging bar
    $pagingbar .= '<div class="paging">';
    $pagingbar .= get_string('page').': ';

    $sistrings = array();
    if ($sifirst != 'all') {
        $sistrings[] =  "sifirst={$sifirst}";
    }
    if ($silast != 'all') {
        $sistrings[] =  "silast={$silast}";
    }
    $sistring = !empty($sistrings) ? '&amp;'.implode('&amp;', $sistrings) : '';

    // Display previous link
    if ($start > 0) {
        $pstart = max($start - COMPLETION_REPORT_PAGE, 0);
        $pagingbar .= "(<a class=\"previous\" href=\"{$link}{$pstart}{$sistring}\">".get_string('previous').'</a>)&nbsp;';
    }

    // Create page links
    $curstart = 0;
    $curpage = 0;
    while ($curstart < $total) {
        $curpage++;

        if ($curstart == $start) {
            $pagingbar .= '&nbsp;'.$curpage.'&nbsp;';
        } else {
            $pagingbar .= "&nbsp;<a href=\"{$link}{$curstart}{$sistring}\">$curpage</a>&nbsp;";
        }

        $curstart += COMPLETION_REPORT_PAGE;
    }

    // Display next link
    $nstart = $start + COMPLETION_REPORT_PAGE;
    if ($nstart < $total) {
        $pagingbar .= "&nbsp;(<a class=\"next\" href=\"{$link}{$nstart}{$sistring}\">".get_string('next').'</a>)';
    }

    $pagingbar .= '</div>';
}

$customprofilefields = array_filter(array_map('trim', explode(',', get_config('report_progress', 'customprofilefields'))));
if ($customprofilefields) {
    list($sqlfields, $params) = $DB->get_in_or_equal($customprofilefields);
    $customfieldsrecords = $DB->get_records_sql('SELECT shortname, name FROM {user_info_field} WHERE shortname ' . $sqlfields, $params);
}

// Okay, let's draw the table of progress info,

// Start of table
if (!$csv) {
    print '<br class="clearer"/>'; // ugh

    print $pagingbar;

    if (!$total) {
        echo $OUTPUT->heading(get_string('nothingtodisplay'));
        echo $OUTPUT->footer();
        exit;
    }

    print '<div id="completion-progress-wrapper" class="no-overflow">';
    print '<table id="completion-progress" class="generaltable flexible boxaligncenter" style="text-align:left"><thead><tr style="vertical-align:top">';

    // User heading / sort option
    print '<th scope="col" class="completion-sortchoice">';

    $sistring = "&amp;silast={$silast}&amp;sifirst={$sifirst}";

    if ($firstnamesort) {
        print
            get_string('firstname')." / <a href=\"./?course={$course->id}{$sistring}\">".
            get_string('lastname').'</a>';
    } else {
        print "<a href=\"./?course={$course->id}&amp;sort=firstname{$sistring}\">".
            get_string('firstname').'</a> / '.
            get_string('lastname');
    }
    print '</th>';

    // Print user identity columns
    foreach ($extrafields as $field) {
        echo '<th scope="col" class="completion-identifyfield">' .
                get_user_field_name($field) . '</th>';
    }

    if ($customprofilefields) {
        foreach ($customprofilefields as $field) {
            echo '<th scope="col" class="completion-identifyfield">' .  s($customfieldsrecords[$field]->name) . '</th>';
        }
    }
} else {
    foreach ($extrafields as $field) {
        echo $sep . csv_quote(get_user_field_name($field));
    }
    if ($customprofilefields) {
        foreach ($customprofilefields as $field) {
            echo $sep . csv_quote($customfieldsrecords[$field]->name);
        }
    }
}

// Activities
$formattedactivities = array();
foreach($activities as $activity) {
    $datepassed = $activity->completionexpected && $activity->completionexpected <= time();
    $datepassedclass = $datepassed ? 'completion-expired' : '';

    if ($activity->completionexpected) {
        if ($csv) {
            $datetext = userdate($activity->completionexpected, "%F %T");
        } else {
            $datetext = userdate($activity->completionexpected, get_string('strftimedate', 'langconfig'));
        }
    } else {
        $datetext='';
    }

    // Some names (labels) come URL-encoded and can be very long, so shorten them
    $displayname = format_string($activity->name, true, array('context' => $activity->context));

    if ($csv) {
        print $sep.csv_quote($displayname).$sep.csv_quote($datetext);
    } else {
        $shortenedname = shorten_text($displayname);
        print '<th scope="col" class="completion-header '.$datepassedclass.'">'.
            '<a href="'.$CFG->wwwroot.'/mod/'.$activity->modname.
            '/view.php?id='.$activity->id.'" title="' . s($displayname) . '">'.
            '<div class="rotated-text-container"><span class="rotated-text">'.$shortenedname.'</span></div>'.
            '<div class="modicon">'.
            $OUTPUT->image_icon('icon', get_string('modulename', $activity->modname), $activity->modname) .
            '</div>'.
            '</a>';
        if ($activity->completionexpected) {
            print '<div class="completion-expected"><span>'.$datetext.'</span></div>';
        }
        print '</th>';
    }
    $formattedactivities[$activity->id] = (object)array(
        'datepassedclass' => $datepassedclass,
        'displayname' => $displayname,
    );
}

if ($csv) {
    print $line;
} else {
    print '</tr></thead><tbody>';
}

// Row for each user
foreach($progress as $user) {
    // User name
    if ($csv) {
        print csv_quote(fullname($user));
        foreach ($extrafields as $field) {
            echo $sep . csv_quote($user->{$field});
        }
        if ($customprofilefields) {
            profile_load_data($user);
            foreach ($customprofilefields as $field) {
                echo $sep . csv_quote($user->{'profile_field_' . $field});
            }
        }
    } else {
        print '<tr><th scope="row"><a href="'.$CFG->wwwroot.'/user/view.php?id='.
            $user->id.'&amp;course='.$course->id.'">'.fullname($user).'</a></th>';
        foreach ($extrafields as $field) {
            echo '<td>' . s($user->{$field}) . '</td>';
        }
        if ($customprofilefields) {
            profile_load_data($user);
            foreach ($customprofilefields as $field) {
                echo '<td>' . s($user->{'profile_field_' . $field}) . '</td>';
            }
        }
    }

    // Progress for each activity
    foreach($activities as $activity) {

        // Get progress information and state
        if (array_key_exists($activity->id, $user->progress)) {
            $thisprogress = $user->progress[$activity->id];
            $state = $thisprogress->completionstate;
            $overrideby = $thisprogress->overrideby;
            $date = userdate($thisprogress->timemodified);
        } else {
            $state = COMPLETION_INCOMPLETE;
            $overrideby = 0;
            $date = '';
        }

        // Work out how it corresponds to an icon
        switch($state) {
            case COMPLETION_INCOMPLETE :
                $completiontype = 'n'.($overrideby ? '-override' : '');
                break;
            case COMPLETION_COMPLETE :
                $completiontype = 'y'.($overrideby ? '-override' : '');
                break;
            case COMPLETION_COMPLETE_PASS :
                $completiontype = 'pass';
                break;
            case COMPLETION_COMPLETE_FAIL :
                $completiontype = 'fail';
                break;
        }
        $completiontrackingstring = $activity->completion == COMPLETION_TRACKING_AUTOMATIC ? 'auto' : 'manual';
        $completionicon = 'completion-' . $completiontrackingstring. '-' . $completiontype;

        if ($overrideby) {
            $overridebyuser = \core_user::get_user($overrideby, '*', MUST_EXIST);
            $describe = get_string('completion-' . $completiontype, 'completion', fullname($overridebyuser));
        } else {
            $describe = get_string('completion-' . $completiontype, 'completion');
        }
        $a=new StdClass;
        $a->state=$describe;
        $a->date=$date;
        $a->user=fullname($user);
        $a->activity = $formattedactivities[$activity->id]->displayname;
        $fulldescribe=get_string('progress-title','completion',$a);

        if ($csv) {
            if ($date != '') {
                $date = userdate($thisprogress->timemodified, "%F %T");
            }
            print $sep.csv_quote($describe).$sep.csv_quote($date);
        } else {
            $celltext = $OUTPUT->pix_icon('i/' . $completionicon, s($fulldescribe));
            if (has_capability('moodle/course:overridecompletion', $context) &&
                    $state != COMPLETION_COMPLETE_PASS && $state != COMPLETION_COMPLETE_FAIL) {
                $newstate = ($state == COMPLETION_COMPLETE) ? COMPLETION_INCOMPLETE : COMPLETION_COMPLETE;
                $changecompl = $user->id . '-' . $activity->id . '-' . $newstate;
                $url = new moodle_url($PAGE->url, ['sesskey' => sesskey()]);
                $celltext = html_writer::link($url, $celltext, array('class' => 'changecompl', 'data-changecompl' => $changecompl,
                                                                     'data-activityname' => $a->activity,
                                                                     'data-userfullname' => $a->user,
                                                                     'data-completiontracking' => $completiontrackingstring,
                                                                     'role' => 'button'));
            }
            print '<td class="completion-progresscell '.$formattedactivities[$activity->id]->datepassedclass.'">'.
                $celltext . '</td>';
        }
    }

    if ($csv) {
        print $line;
    } else {
        print '</tr>';
    }
}

if ($csv) {
    exit;
}
print '</tbody></table>';
print '</div>';

print '<ul class="progress-actions"><li><a href="index.php?course='.$course->id.
    '&amp;format=csv">'.get_string('csvdownload','completion').'</a></li>
    <li><a href="index.php?course='.$course->id.'&amp;format=excelcsv">'.
    get_string('excelcsvdownload','completion').'</a></li></ul>';

echo $OUTPUT->footer();

