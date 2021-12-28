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

namespace core_reportbuilder\privacy;

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_reportbuilder\local\helpers\user_filter_manager;
use core_reportbuilder\local\models\audience;
use core_reportbuilder\local\models\column;
use core_reportbuilder\local\models\filter;
use core_reportbuilder\local\models\report;
use core_reportbuilder\local\models\schedule;

/**
 * Privacy Subsystem for core_reportbuilder
 *
 * @package     core_reportbuilder
 * @copyright   2021 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\user_preference_provider,
    \core_privacy\local\request\plugin\provider,
    core_userlist_provider {

    /**
     * Returns metadata about the component
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(report::TABLE, [
            'name' => 'privacy:metadata:report:name',
            'source' => 'privacy:metadata:report:source',
            'component' => 'privacy:metadata:report:component',
            'area' => 'privacy:metadata:report:area',
            'itemid' => 'privacy:metadata:report:itemid',
            'usercreated' => 'privacy:metadata:report:usercreated',
            'usermodified' => 'privacy:metadata:report:usermodified',
            'timecreated' => 'privacy:metadata:report:timecreated',
            'timemodified' => 'privacy:metadata:report:timemodified',
        ], 'privacy:metadata:report');

        $collection->add_database_table(column::TABLE, [
            'uniqueidentifier' => 'privacy:metadata:column:uniqueidentifier',
            'usercreated' => 'privacy:metadata:column:usercreated',
            'usermodified' => 'privacy:metadata:column:usermodified',
        ], 'privacy:metadata:column');

        $collection->add_database_table(filter::TABLE, [
            'uniqueidentifier' => 'privacy:metadata:filter:uniqueidentifier',
            'usercreated' => 'privacy:metadata:filter:usercreated',
            'usermodified' => 'privacy:metadata:filter:usermodified',
        ], 'privacy:metadata:filter');

        $collection->add_database_table(audience::TABLE, [
            'classname' => 'privacy:metadata:audience:classname',
            'configdata' => 'privacy:metadata:audience:configdata',
            'usercreated' => 'privacy:metadata:audience:usercreated',
            'usermodified' => 'privacy:metadata:audience:usermodified',
            'timecreated' => 'privacy:metadata:audience:timecreated',
            'timemodified' => 'privacy:metadata:audience:timemodified',
        ], 'privacy:metadata:audience');

        $collection->add_database_table(schedule::TABLE, [
            'name' => 'privacy:metadata:schedule:name',
            'subject' => 'privacy:metadata:schedule:subject',
            'message' => 'privacy:metadata:schedule:message',
            'enabled' => 'privacy:metadata:schedule:enabled',
            'audiences' => 'privacy:metadata:schedule:audiences',
            'timescheduled' => 'privacy:metadata:schedule:timescheduled',
            'timelastsent' => 'privacy:metadata:schedule:timelastsent',
            'recurrence' => 'privacy:metadata:schedule:recurrence',
            'timenextsend' => 'privacy:metadata:schedule:timenextsend',
            'userviewas' => 'privacy:metadata:schedule:userviewas',
            'usercreated' => 'privacy:metadata:schedule:usercreated',
            'usermodified' => 'privacy:metadata:schedule:usermodified',
            'timecreated' => 'privacy:metadata:schedule:timecreated',
            'timemodified' => 'privacy:metadata:schedule:timemodified',
        ], 'privacy:metadata:schedule');

        $collection->add_user_preference('core_reportbuilder', 'privacy:metadata:preference:reportfilter');

        return $collection;
    }

    /**
     * Export all user preferences for the component
     *
     * @param int $userid
     */
    public static function export_user_preferences(int $userid): void {
        $preferencestring = get_string('privacy:metadata:preference:reportfilter', 'core_reportbuilder');

        $filters = user_filter_manager::get_all_for_user($userid);
        foreach ($filters as $key => $filter) {
            writer::export_user_preference('core_reportbuilder',
                $key,
                json_encode($filter, JSON_PRETTY_PRINT),
                $preferencestring
            );
        }
    }

    /**
     * Get context export path for a report
     *
     * @param report $report
     * @return array
     */
    public static function get_export_path(report $report): array {
        $reportnode = implode('-', [
            $report->get('id'),
            clean_filename($report->get('name')),
        ]);

        return [get_string('reportbuilder', 'core_reportbuilder'), $reportnode];
    }

    /**
     * Get the list of contexts that contain user information for the specified user
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $select = 'usercreated = ? OR usermodified = ?';
        $params = [$userid, $userid];

        // We add the system context if the specified user has created/modified any reports or schedules.
        if (report::record_exists_select($select . ' AND type = 0', $params) ||
            audience::record_exists_select($select, $params) ||
            schedule::record_exists_select($select, $params)) {

            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Export all user data for the specified user in the specified contexts
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        // We need to get all reports that the user has created, or reports they have created audience/schedules for.
        $select = 'type = 0 AND (usercreated = ? OR usermodified = ? OR id IN (
            SELECT a.reportid
              FROM {' . audience::TABLE . '} a
             WHERE a.usercreated = ? OR a.usermodified = ?
             UNION
            SELECT s.reportid
              FROM {' . schedule::TABLE . '} s
             WHERE s.usercreated = ? OR s.usermodified = ?
        ))';
        $params = array_fill(0, 6, $user->id);

        foreach (report::get_records_select($select, $params) as $report) {
            $contextpath = static::get_export_path($report);

            self::export_report($report);

            $select = 'reportid = ? AND (usercreated = ? OR usermodified = ?)';
            $params = [$report->get('id'), $user->id, $user->id];

            // Audiences.
            if ($audiences = audience::get_records_select($select, $params)) {
                static::export_audiences($report->get_context(), $contextpath, $audiences);
            }

            // Schedules.
            if ($schedules = schedule::get_records_select($select, $params)) {
                static::export_schedules($report->get_context(), $contextpath, $schedules);
            }
        }
    }

    /**
     * Delete data for all users in context
     *
     * @param context $context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        // We don't perform any deletion of user data.
    }

    /**
     * Delete data for user
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        // We don't perform any deletion of user data.
    }

    /**
     * Delete data for users
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        // We don't perform any deletion of user data.
    }

    /**
     * Get users in context
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        // Users who have created reports.
        $sql = 'SELECT usercreated, usermodified
                  FROM {' . report::TABLE . '}
                 WHERE type = ?';
        $userlist->add_from_sql('usercreated', $sql, [0]);
        $userlist->add_from_sql('usermodified', $sql, [0]);

        // Users who have created audiences.
        $sql = 'SELECT usercreated, usermodified
                  FROM {' .  audience::TABLE . '}';
        $userlist->add_from_sql('usercreated', $sql, []);
        $userlist->add_from_sql('usermodified', $sql, []);

        // Users who have created schedules.
        $sql = 'SELECT usercreated, usermodified
                  FROM {' . schedule::TABLE . '}';
        $userlist->add_from_sql('usercreated', $sql, []);
        $userlist->add_from_sql('usermodified', $sql, []);
    }

    /**
     * Export given report in context
     *
     * @param report $report
     */
    protected static function export_report(report $report): void {
        $reportdata = (object) [
            'source' => $report->get('source'),
            'name' => $report->get_formatted_name(),
            'component' => $report->get('component'),
            'area' => $report->get('area'),
            'itemid' => $report->get('itemid'),
            'conditiondata' => $report->get('conditiondata'),
            'settingsdata' => $report->get('settingsdata'),
            'usercreated' => transform::user($report->get('usercreated')),
            'usermodified' => transform::user($report->get('usermodified')),
            'timecreated' => transform::datetime($report->get('timecreated')),
            'timemodified' => transform::datetime($report->get('timemodified')),
        ];

        $contextpath = self::get_export_path($report);
        writer::with_context($report->get_context())->export_data($contextpath, $reportdata);
    }

    /**
     * Export given audiences in context
     *
     * @param context $context
     * @param array $contextpath
     * @param audience[] $audiences
     */
    protected static function export_audiences(context $context, array $contextpath, array $audiences): void {
        $audiencedata = [];

        foreach ($audiences as $audience) {
            $audiencedata[] = (object) [
                'classname' => $audience->get('classname'),
                'configdata' => $audience->get('configdata'),
                'heading' => $audience->get_formatted_heading(),
                'usercreated' => transform::user($audience->get('usercreated')),
                'usermodified' => transform::user($audience->get('usermodified')),
                'timecreated' => transform::datetime($audience->get('timecreated')),
                'timemodified' => transform::datetime($audience->get('timemodified')),
            ];
        }

        writer::with_context($context)->export_related_data($contextpath, 'audiences', (object) ['data' => $audiencedata]);
    }

    /**
     * Export given schedules in context
     *
     * @param context $context
     * @param array $contextpath
     * @param schedule[] $schedules
     */
    protected static function export_schedules(context $context, array $contextpath, array $schedules): void {
        $scheduledata = [];

        foreach ($schedules as $schedule) {
            $scheduledata[] = (object) [
                'name' => $schedule->get_formatted_name(),
                'timescheduled' => transform::datetime($schedule->get('timescheduled')),
                'recurrence' => $schedule->get('recurrence'),
                'timelastsent' => transform::datetime($schedule->get('timelastsent')),
                'timenextsend' => transform::datetime($schedule->get('timenextsend')),
                'format' => $schedule->get('format'),
                'enabled' => $schedule->get('enabled'),
                'audiences' => $schedule->get('audiences'),
                'userviewas' => $schedule->get('userviewas'),
                'subject' => format_string($schedule->get('subject')),
                'message' => format_text($schedule->get('message'), FORMAT_HTML, ['context' => $context]),
                'usercreated' => transform::user($schedule->get('usercreated')),
                'usermodified' => transform::user($schedule->get('usermodified')),
                'timecreated' => transform::datetime($schedule->get('timecreated')),
                'timemodified' => transform::datetime($schedule->get('timemodified')),
            ];
        }

        writer::with_context($context)->export_related_data($contextpath, 'schedules', (object) ['data' => $scheduledata]);
    }
}
