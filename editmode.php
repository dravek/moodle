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
 * Set Mode.
 *
 * @package    core
 * @copyright  2021 Andrew Lyons
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('config.php');

$setmode = optional_param('setmode', false, PARAM_BOOL);
$contextid = required_param('context', PARAM_INT);
$pagetype = required_param('pagetype', PARAM_ALPHANUMEXT);
$pageurl = required_param('pageurl', PARAM_LOCALURL);

require_sesskey();
require_login();

$context = \context_helper::instance_by_id($contextid);
$PAGE->set_context($context);

if ($context instanceof \context_user) {
    $currentuser = $context->instanceid === $USER->id;

    if ($pagetype === 'my-index') {
        // User dashboard page.
        $PAGE->set_blocks_editing_capability('moodle/my:manageblocks');
    } else if ($currentuser) {
        // User profile page for the current user.
        $PAGE->set_blocks_editing_capability('moodle/user:manageownblocks');
    } else {
        // User profile page for another user.
        $PAGE->set_blocks_editing_capability('moodle/user:manageblocks');
    }
}

if ($PAGE->user_allowed_editing()) {
    $USER->editing = $setmode;
} else {
    \core\notification::add(get_string('cannotswitcheditmodeon', 'error'), \core\notification::ERROR);
}

redirect($pageurl);
