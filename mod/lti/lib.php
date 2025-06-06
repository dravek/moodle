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
//
// This file is part of BasicLTI4Moodle
//
// BasicLTI4Moodle is an IMS BasicLTI (Basic Learning Tools for Interoperability)
// consumer for Moodle 1.9 and Moodle 2.0. BasicLTI is a IMS Standard that allows web
// based learning tools to be easily integrated in LMS as native ones. The IMS BasicLTI
// specification is part of the IMS standard Common Cartridge 1.1 Sakai and other main LMS
// are already supporting or going to support BasicLTI. This project Implements the consumer
// for Moodle. Moodle is a Free Open source Learning Management System by Martin Dougiamas.
// BasicLTI4Moodle is a project iniciated and leaded by Ludo(Marc Alier) and Jordi Piguillem
// at the GESSI research group at UPC.
// SimpleLTI consumer for Moodle is an implementation of the early specification of LTI
// by Charles Severance (Dr Chuck) htp://dr-chuck.com , developed by Jordi Piguillem in a
// Google Summer of Code 2008 project co-mentored by Charles Severance and Marc Alier.
//
// BasicLTI4Moodle is copyright 2009 by Marc Alier Forment, Jordi Piguillem and Nikolas Galanis
// of the Universitat Politecnica de Catalunya http://www.upc.edu
// Contact info: Marc Alier Forment granludo @ gmail.com or marc.alier @ upc.edu.

/**
 * This file contains a library of functions and constants for the lti module
 *
 * @package mod_lti
 * @copyright  2009 Marc Alier, Jordi Piguillem, Nikolas Galanis
 *  marc.alier@upc.edu
 * @copyright  2009 Universitat Politecnica de Catalunya http://www.upc.edu
 * @author     Marc Alier
 * @author     Jordi Piguillem
 * @author     Nikolas Galanis
 * @author     Chris Scribner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__ . '/deprecatedlib.php');

/**
 * List of features supported in URL module
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 */
function lti_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_INTRO:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GRADE_OUTCOMES:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_OTHER;

        default:
            return null;
    }
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $instance An object from the form in mod.html
 * @return int The id of the newly inserted basiclti record
 **/
function lti_add_instance($lti, $mform) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/lti/locallib.php');

    if (!isset($lti->toolurl)) {
        $lti->toolurl = '';
    }

    lti_load_tool_if_cartridge($lti);

    $lti->timecreated = time();
    $lti->timemodified = $lti->timecreated;
    $lti->servicesalt = uniqid('', true);
    if (!isset($lti->typeid)) {
        $lti->typeid = null;
    }

    lti_force_type_config_settings($lti, lti_get_type_config_by_instance($lti));

    if (empty($lti->typeid) && isset($lti->urlmatchedtypeid)) {
        $lti->typeid = $lti->urlmatchedtypeid;
    }

    if (!isset($lti->instructorchoiceacceptgrades) || $lti->instructorchoiceacceptgrades != LTI_SETTING_ALWAYS) {
        // The instance does not accept grades back from the provider, so set to "No grade" value 0.
        $lti->grade = 0;
    }

    $lti->id = $DB->insert_record('lti', $lti);

    if (isset($lti->instructorchoiceacceptgrades) && $lti->instructorchoiceacceptgrades == LTI_SETTING_ALWAYS) {
        if (!isset($lti->cmidnumber)) {
            $lti->cmidnumber = '';
        }

        lti_grade_item_update($lti);
    }

    $services = lti_get_services();
    foreach ($services as $service) {
        $service->instance_added( $lti );
    }

    $completiontimeexpected = !empty($lti->completionexpected) ? $lti->completionexpected : null;
    \core_completion\api::update_completion_date_event($lti->coursemodule, 'lti', $lti->id, $completiontimeexpected);

    return $lti->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will update an existing instance with new data.
 *
 * @param object $instance An object from the form in mod.html
 * @return boolean Success/Fail
 **/
function lti_update_instance($lti, $mform) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/lti/locallib.php');

    lti_load_tool_if_cartridge($lti);

    $lti->timemodified = time();
    $lti->id = $lti->instance;

    if (!isset($lti->showtitlelaunch)) {
        $lti->showtitlelaunch = 0;
    }

    if (!isset($lti->showdescriptionlaunch)) {
        $lti->showdescriptionlaunch = 0;
    }

    lti_force_type_config_settings($lti, lti_get_type_config_by_instance($lti));

    if (isset($lti->instructorchoiceacceptgrades) && $lti->instructorchoiceacceptgrades == LTI_SETTING_ALWAYS) {
        lti_grade_item_update($lti);
    } else {
        // Instance is no longer accepting grades from Provider, set grade to "No grade" value 0.
        $lti->grade = 0;
        $lti->instructorchoiceacceptgrades = 0;

        lti_grade_item_delete($lti);
    }

    if ($lti->typeid == 0 && isset($lti->urlmatchedtypeid)) {
        $lti->typeid = $lti->urlmatchedtypeid;
    }

    $services = lti_get_services();
    foreach ($services as $service) {
        $service->instance_updated( $lti );
    }

    $completiontimeexpected = !empty($lti->completionexpected) ? $lti->completionexpected : null;
    \core_completion\api::update_completion_date_event($lti->coursemodule, 'lti', $lti->id, $completiontimeexpected);

    return $DB->update_record('lti', $lti);
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 **/
function lti_delete_instance($id) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/lti/locallib.php');

    if (! $basiclti = $DB->get_record("lti", array("id" => $id))) {
        return false;
    }

    $result = true;

    // Delete any dependent records here.
    lti_grade_item_delete($basiclti);

    $ltitype = $DB->get_record('lti_types', array('id' => $basiclti->typeid));
    if ($ltitype) {
        $DB->delete_records('lti_tool_settings',
            array('toolproxyid' => $ltitype->toolproxyid, 'course' => $basiclti->course, 'coursemoduleid' => $id));
    }

    $cm = get_coursemodule_from_instance('lti', $id);
    \core_completion\api::update_completion_date_event($cm->id, 'lti', $id, null);

    // We must delete the module record after we delete the grade item.
    if ($DB->delete_records("lti", array("id" => $basiclti->id)) ) {
        $services = lti_get_services();
        foreach ($services as $service) {
            $service->instance_deleted( $id );
        }
        return true;
    }
    return false;

}

/**
 * Return the preconfigured tools which are configured for inclusion in the activity picker.
 *
 * @param \core_course\local\entity\content_item $defaultmodulecontentitem reference to the content item for the LTI module.
 * @param \stdClass $user the user object, to use for cap checks if desired.
 * @param stdClass $course the course to scope items to.
 * @return array the array of content items.
 */
function lti_get_course_content_items(\core_course\local\entity\content_item $defaultmodulecontentitem, \stdClass $user,
        \stdClass $course) {
    global $CFG, $OUTPUT;
    require_once($CFG->dirroot.'/mod/lti/locallib.php');

    $types = [];

    // Use of a tool type, whether site or course level, is controlled by the following cap.
    if (!has_capability('mod/lti:addpreconfiguredinstance', \core\context\course::instance($course->id), $user)) {
        return $types;
    }
    $preconfiguredtools = lti_get_configured_types($course->id, $defaultmodulecontentitem->get_link()->param('sr'));

    foreach ($preconfiguredtools as $preconfiguredtool) {

        // Append the help link to the help text.
        if (isset($preconfiguredtool->help)) {
            if (isset($preconfiguredtool->helplink)) {
                $linktext = get_string('morehelp');
                $preconfiguredtool->help .= html_writer::tag('div',
                    $OUTPUT->doc_link($preconfiguredtool->helplink, $linktext, true), ['class' => 'helpdoclink']);
            }
        } else {
            $preconfiguredtool->help = '';
        }

        // Preconfigured tools take their own id + 1. This logic exists because, previously, the entry permitting manual instance
        // creation (the $defaultmodulecontentitem, or 'External tool' item) was included and had the id 1. This logic prevented id
        // collisions.
        $types[] = new \core_course\local\entity\content_item(
            $preconfiguredtool->id + 1,
            $preconfiguredtool->name,
            new \core_course\local\entity\string_title($preconfiguredtool->title),
            $preconfiguredtool->link,
            $preconfiguredtool->icon,
            $preconfiguredtool->help,
            $defaultmodulecontentitem->get_archetype(),
            $defaultmodulecontentitem->get_component_name(),
            $defaultmodulecontentitem->get_purpose()
        );
    }
    return $types;
}

/**
 * Return all content items which can be added to any course.
 *
 * @param \core_course\local\entity\content_item $defaultmodulecontentitem
 * @return array the array of content items.
 */
function mod_lti_get_all_content_items(\core_course\local\entity\content_item $defaultmodulecontentitem): array {
    global $OUTPUT, $CFG;
    require_once($CFG->dirroot . '/mod/lti/locallib.php'); // For access to constants.

    $types = [];

    foreach (lti_get_lti_types() as $ltitype) {
        if ($ltitype->coursevisible != LTI_COURSEVISIBLE_ACTIVITYCHOOSER) {
            continue;
        }
        $type           = new stdClass();
        $type->id       = $ltitype->id;
        $type->modclass = MOD_CLASS_ACTIVITY;
        $type->name     = 'lti_type_' . $ltitype->id;
        // Clean the name. We don't want tags here.
        $type->title    = clean_param($ltitype->name, PARAM_NOTAGS);
        $trimmeddescription = trim($ltitype->description ?? '');
        $type->help = '';
        if ($trimmeddescription != '') {
            // Clean the description. We don't want tags here.
            $type->help     = clean_param($trimmeddescription, PARAM_NOTAGS);
            $type->helplink = get_string('modulename_shortcut_link', 'lti');
        }
        if (empty($ltitype->icon)) {
            $type->icon = $OUTPUT->pix_icon('monologo', '', 'lti', array('class' => 'icon'));
        } else {
            $type->icon = html_writer::empty_tag('img', array('src' => $ltitype->icon, 'alt' => $ltitype->name, 'class' => 'icon'));
        }
        $type->link = new moodle_url('/course/modedit.php', array('add' => 'lti', 'return' => 0, 'typeid' => $ltitype->id));

        // Preconfigured tools take their own id + 1. This logic exists because, previously, the entry permitting manual instance
        // creation (the $defaultmodulecontentitem, or 'External tool' item) was included and had the id 1. This logic prevented id
        // collisions.
        $types[] = new \core_course\local\entity\content_item(
            $type->id + 1,
            $type->name,
            new \core_course\local\entity\string_title($type->title),
            $type->link,
            $type->icon,
            $type->help,
            $defaultmodulecontentitem->get_archetype(),
            $defaultmodulecontentitem->get_component_name(),
            $defaultmodulecontentitem->get_purpose()
        );
    }

    return $types;
}

/**
 * Given a coursemodule object, this function returns the extra
 * information needed to print this activity in various places.
 * For this module we just need to support external urls as
 * activity icons
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info info
 */
function lti_get_coursemodule_info($coursemodule) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/lti/locallib.php');

    if (!$lti = $DB->get_record('lti', array('id' => $coursemodule->instance),
            'icon, secureicon, intro, introformat, name, typeid, toolurl, launchcontainer')) {
        return null;
    }

    $info = new cached_cm_info();

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $info->content = format_module_intro('lti', $lti, $coursemodule->id, false);
    }

    if (!empty($lti->typeid)) {
        $toolconfig = lti_get_type_config($lti->typeid);
    } else if ($tool = lti_get_tool_by_url_match($lti->toolurl)) {
        $toolconfig = lti_get_type_config($tool->id);
    } else {
        $toolconfig = array();
    }

    // We want to use the right icon based on whether the
    // current page is being requested over http or https.
    if (lti_request_is_using_ssl() &&
        (!empty($lti->secureicon) || (isset($toolconfig['secureicon']) && !empty($toolconfig['secureicon'])))) {
        if (!empty($lti->secureicon)) {
            $info->iconurl = new moodle_url($lti->secureicon);
        } else {
            $info->iconurl = new moodle_url($toolconfig['secureicon']);
        }
    } else if (!empty($lti->icon)) {
        $info->iconurl = new moodle_url($lti->icon);
    } else if (isset($toolconfig['icon']) && !empty($toolconfig['icon'])) {
        $info->iconurl = new moodle_url($toolconfig['icon']);
    }

    // Does the link open in a new window?
    $launchcontainer = lti_get_launch_container($lti, $toolconfig);
    if ($launchcontainer == LTI_LAUNCH_CONTAINER_WINDOW) {
        $launchurl = new moodle_url('/mod/lti/launch.php', array('id' => $coursemodule->id));
        $info->onclick = "window.open('" . $launchurl->out(false) . "', 'lti-".$coursemodule->id."'); return false;";
    }

    $info->name = $lti->name;

    return $info;
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return null
 * @TODO: implement this moodle function (if needed)
 **/
function lti_user_outline($course, $user, $mod, $basiclti) {
    return null;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @return boolean
 * @TODO: implement this moodle function (if needed)
 **/
function lti_user_complete($course, $user, $mod, $basiclti) {
    return true;
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in basiclti activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @uses $CFG
 * @return boolean
 * @TODO: implement this moodle function
 **/
function lti_print_recent_activity($course, $isteacher, $timestart) {
    return false;  //  True if anything was printed, otherwise false.
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @uses $CFG
 * @return boolean
 **/
function lti_cron () {
    return true;
}

/**
 * Must return an array of grades for a given instance of this module,
 * indexed by user.  It also returns a maximum allowed grade.
 *
 * Example:
 *    $return->grades = array of grades;
 *    $return->maxgrade = maximum allowed grade;
 *
 *    return $return;
 *
 * @param int $basicltiid ID of an instance of this module
 * @return mixed Null or object with an array of grades and with the maximum grade
 *
 * @TODO: implement this moodle function (if needed)
 **/
function lti_grades($basicltiid) {
    return null;
}

/**
 * Checks if scale is being used by any instance of basiclti.
 * This function was added in 1.9
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any basiclti
 *
 */
function lti_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('lti', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Execute post-install custom actions for the module
 * This function was added in 1.9
 *
 * @return boolean true if success, false on error
 */
function lti_install() {
     return true;
}

/**
 * Execute post-uninstall custom actions for the module
 * This function was added in 1.9
 *
 * @return boolean true if success, false on error
 */
function lti_uninstall() {
    return true;
}

/**
 * Returns available Basic LTI types
 *
 * @return array of basicLTI types
 */
function lti_get_lti_types() {
    global $DB;

    return $DB->get_records('lti_types', null, 'state DESC, timemodified DESC');
}

/**
 * Returns available Basic LTI types that match the given
 * tool proxy id
 *
 * @param int $toolproxyid Tool proxy id
 * @return array of basicLTI types
 */
function lti_get_lti_types_from_proxy_id($toolproxyid) {
    global $DB;

    return $DB->get_records('lti_types', array('toolproxyid' => $toolproxyid), 'state DESC, timemodified DESC');
}

/**
 * Create grade item for given basiclti
 *
 * @category grade
 * @param object $basiclti object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function lti_grade_item_update($basiclti, $grades = null) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');
    require_once($CFG->dirroot.'/mod/lti/servicelib.php');

    if (!lti_accepts_grades($basiclti)) {
        return 0;
    }

    $params = array('itemname' => $basiclti->name, 'idnumber' => $basiclti->cmidnumber);

    if ($basiclti->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $basiclti->grade;
        $params['grademin']  = 0;

    } else if ($basiclti->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$basiclti->grade;

    } else {
        $params['gradetype'] = GRADE_TYPE_TEXT; // Allow text comments only.
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/lti', $basiclti->course, 'mod', 'lti', $basiclti->id, 0, $grades, $params);
}

/**
 * Update activity grades
 *
 * @param stdClass $basiclti The LTI instance
 * @param int      $userid Specific user only, 0 means all.
 * @param bool     $nullifnone Not used
 */
function lti_update_grades($basiclti, $userid=0, $nullifnone=true) {
    global $CFG;
    require_once($CFG->dirroot.'/mod/lti/servicelib.php');
    // LTI doesn't have its own grade table so the only thing to do is update the grade item.
    if (lti_accepts_grades($basiclti)) {
        lti_grade_item_update($basiclti);
    }
}

/**
 * Delete grade item for given basiclti
 *
 * @category grade
 * @param object $basiclti object
 * @return object basiclti
 */
function lti_grade_item_delete($basiclti) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/lti', $basiclti->course, 'mod', 'lti', $basiclti->id, 0, null, array('deleted' => 1));
}

/**
 * Log post actions
 *
 * @return array
 */
function lti_get_post_actions() {
    return array();
}

/**
 * Log view actions
 *
 * @return array
 */
function lti_get_view_actions() {
    return array('view all', 'view');
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $lti        lti object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.0
 */
function lti_view($lti, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = array(
        'context' => $context,
        'objectid' => $lti->id
    );

    $event = \mod_lti\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('lti', $lti);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function lti_check_updates_since(cm_info $cm, $from, $filter = array()) {
    global $DB, $USER;

    $updates = course_check_module_updates_since($cm, $from, array(), $filter);

    // Check if there is a new submission.
    $updates->submissions = (object) array('updated' => false);
    $select = 'ltiid = :id AND userid = :userid AND (datesubmitted > :since1 OR dateupdated > :since2)';
    $params = array('id' => $cm->instance, 'userid' => $USER->id, 'since1' => $from, 'since2' => $from);
    $submissions = $DB->get_records_select('lti_submission', $select, $params, '', 'id');
    if (!empty($submissions)) {
        $updates->submissions->updated = true;
        $updates->submissions->itemids = array_keys($submissions);
    }

    // Now, teachers should see other students updates.
    if (has_capability('mod/lti:manage', $cm->context)) {
        $select = 'ltiid = :id AND (datesubmitted > :since1 OR dateupdated > :since2)';
        $params = array('id' => $cm->instance, 'since1' => $from, 'since2' => $from);

        if (groups_get_activity_groupmode($cm) == SEPARATEGROUPS) {
            $groupusers = array_keys(groups_get_activity_shared_group_members($cm));
            if (empty($groupusers)) {
                return $updates;
            }
            list($insql, $inparams) = $DB->get_in_or_equal($groupusers, SQL_PARAMS_NAMED);
            $select .= ' AND userid ' . $insql;
            $params = array_merge($params, $inparams);
        }

        $updates->usersubmissions = (object) array('updated' => false);
        $submissions = $DB->get_records_select('lti_submission', $select, $params, '', 'id');
        if (!empty($submissions)) {
            $updates->usersubmissions->updated = true;
            $updates->usersubmissions->itemids = array_keys($submissions);
        }
    }

    return $updates;
}

/**
 * Get icon mapping for font-awesome.
 */
function mod_lti_get_fontawesome_icon_map() {
    return [
        'mod_lti:warning' => 'fa-exclamation text-warning',
    ];
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid User id to use for all capability checks, etc. Set to 0 for current user (default).
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_lti_core_calendar_provide_event_action(calendar_event $event,
                                                      \core_calendar\action_factory $factory,
                                                      int $userid = 0) {
    global $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['lti'][$event->instance];

    if (!$cm->uservisible) {
        // The module is not visible to the user for any reason.
        return null;
    }

    $completion = new \completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false, $userid);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    return $factory->create_instance(
        get_string('view'),
        new \moodle_url('/mod/lti/view.php', ['id' => $cm->id]),
        1,
        true
    );
}

/**
 * Extend the course navigation with an "LTI External tools" link which redirects to a list of all tools available for course use.
 *
 * @param settings_navigation $navigation The settings navigation object
 * @param stdClass $course The course
 * @param stdclass $context Course context
 * @return void
 */
function mod_lti_extend_navigation_course($navigation, $course, $context): void {
    if (has_capability('mod/lti:addpreconfiguredinstance', $context)) {
        $url = new moodle_url('/mod/lti/coursetools.php', ['id' => $course->id]);
        $settingsnode = navigation_node::create(get_string('courseexternaltools', 'mod_lti'), $url, navigation_node::TYPE_SETTING,
            null, 'coursetools', new pix_icon('i/settings', ''));
        $navigation->add_node($settingsnode);
    }
}
