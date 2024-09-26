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

namespace core_reportbuilder;

use core_collator;
use core_component;
use core_plugin_manager;
use stdClass;
use core_reportbuilder\event\report_created;
use core_reportbuilder\local\models\{audience, column, filter, report, schedule};
use core_reportbuilder\local\report\base;
use core_reportbuilder\exception\{source_invalid_exception, source_unavailable_exception};

/**
 * Report management class
 *
 * @package     core_reportbuilder
 * @copyright   2020 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** @var base $instances */
    private static $instances = [];

    /**
     * Return an instance of a report class from the given report persistent
     *
     * We statically cache the list of loaded reports per user during request lifecycle, to allow this method to be called
     * repeatedly without potential performance problems initialising the same report multiple times
     *
     * @param report $report
     * @param array $parameters
     * @return base
     * @throws source_invalid_exception
     * @throws source_unavailable_exception
     */
    public static function get_report_from_persistent(report $report, array $parameters = []): base {
        global $USER;

        // Cached instance per report/user, to account for initialization dependent on current user.
        $instancekey = $report->get('id') . ':' . ($USER->id ?? 0);

        if (!array_key_exists($instancekey, static::$instances)) {
            $source = $report->get('source');

            // Throw exception for invalid or unavailable report source.
            if (!self::report_source_exists($source)) {
                throw new source_invalid_exception($source);
            } else if (!self::report_source_available($source)) {
                throw new source_unavailable_exception($source);
            }

            static::$instances[$instancekey] = new $source($report, $parameters);
        }

        return static::$instances[$instancekey];
    }

    /**
     * Run reset code after tests to reset the instance cache
     */
    public static function reset_caches(): void {
        if (PHPUNIT_TEST || defined('BEHAT_TEST')) {
            static::$instances = [];
        }
    }

    /**
     * Return an instance of a report class from the given report ID
     *
     * @param int $reportid
     * @param array $parameters
     * @return base
     */
    public static function get_report_from_id(int $reportid, array $parameters = []): base {
        $report = new report($reportid);

        return self::get_report_from_persistent($report, $parameters);
    }

    /**
     * Verify that report source exists and extends appropriate base classes
     *
     * @param string $source Full namespaced path to report definition
     * @param string $additionalbaseclass Specify addition base class that given classname should extend
     * @return bool
     */
    public static function report_source_exists(string $source, string $additionalbaseclass = ''): bool {
        return (class_exists($source) && is_subclass_of($source, base::class) &&
            (empty($additionalbaseclass) || is_subclass_of($source, $additionalbaseclass)));
    }

    /**
     * Verify given report source is available. Note that it is assumed caller has already checked that it exists
     *
     * @param string $source
     * @return bool
     */
    public static function report_source_available(string $source): bool {
        return call_user_func([$source, 'is_available']);
    }

    /**
     * Create new report persistent
     *
     * @param stdClass $reportdata
     * @return report
     */
    public static function create_report_persistent(stdClass $reportdata): report {
        return (new report(0, $reportdata))->create();
    }

    /**
     * Return an array of all valid report sources across the site
     *
     * @return array[][] Indexed by [component => [class => name]]
     */
    public static function get_report_datasources(): array {
        $sources = array();

        $datasources = core_component::get_component_classes_in_namespace(null, 'reportbuilder\\datasource');
        foreach ($datasources as $class => $path) {
            if (self::report_source_exists($class, datasource::class) && self::report_source_available($class)) {

                // Group each report source by the component that it belongs to.
                [$component] = explode('\\', $class);
                if ($plugininfo = core_plugin_manager::instance()->get_plugin_info($component)) {
                    $componentname = $plugininfo->displayname;
                } else {
                    $componentname = get_string('site');
                }

                $sources[$componentname][$class] = call_user_func([$class, 'get_name']);
            }
        }

        // Order source for each component alphabetically.
        array_walk($sources, static function(array &$componentsources): void {
            core_collator::asort($componentsources);
        });

        return $sources;
    }

    /**
     * Configured site limit for number of custom reports threshold has been reached
     *
     * @return bool
     */
    public static function report_limit_reached(): bool {
        global $CFG;

        return (!empty($CFG->customreportslimit) &&
            (int) $CFG->customreportslimit <= report::count_records(['type' => base::TYPE_CUSTOM_REPORT]));
    }

    /**
     * Duplicate an existing report with audiences and schedules.
     *
     * @param report $originalreport The report to duplicate.
     * @param string $reportname
     * @param bool $audiences
     * @param bool $schedules
     * @return report The duplicated report.
     */
    public static function duplicate_report(report $originalreport, string $reportname, bool $audiences, bool $schedules): report {
        // Create new report persistent.
        $record = $originalreport->to_record();
        unset($record->id);
        $record->name = $reportname;
        $report = self::create_report_persistent($record);

        // Duplicate columns.
        self::duplicate_content(column::class, $originalreport->get('id'), $report->get('id'));

        // Duplicate conditions/filters.
        self::duplicate_content(filter::class, $originalreport->get('id'), $report->get('id'));

        // Duplicate audiences.
        if ($audiences) {
            $mapping = [];
            foreach (audience::get_records(['reportid' => $originalreport->get('id')]) as $originalpersistent) {
                $record = $originalpersistent->to_record();
                unset($record->id);
                $record->reportid = $report->get('id');
                $newpersistent = new audience(0, $record);
                $newpersistent->create();
                $mapping[$originalpersistent->get('id')] = $newpersistent->get('id');
            }

            // Duplicate schedules and map them to the new audience ids.
            if ($schedules) {
                foreach (schedule::get_records(['reportid' => $originalreport->get('id')]) as $originalpersistent) {
                    $record = $originalpersistent->to_record();
                    unset($record->id);
                    $record->reportid = $report->get('id');

                    // Map new audience ids with the old ones.
                    $originalaudiences = json_decode($record->audiences, true);
                    $newaudiences = [];
                    foreach ($originalaudiences as $audienceid) {
                        $newaudiences[] = $mapping[$audienceid];
                    }
                    $record->audiences = json_encode($newaudiences);

                    $newpersistent = new schedule(0, $record);
                    $newpersistent->create();
                }
            }
        }

        // Copy report tags.
        $tags = \core_tag_tag::get_item_tags_array('core_reportbuilder', 'reportbuilder_report', $originalreport->get('id'));
        \core_tag_tag::set_item_tags('core_reportbuilder', 'reportbuilder_report', $report->get('id'), $report->get_context(),
            $tags);

        // Trigger event.
        report_created::create_from_object($report)->trigger();

        return $report;
    }

    /**
     * Duplicate content from one persistent object to another.
     *
     * @param \core\persistent $persistent
     * @param int $originalid The ID of the original persistent object.
     * @param int $newid The ID of the new persistent object.
     */
    private static function duplicate_content(string $persistent, int $originalid, int $newid): void {
        foreach ($persistent::get_records(['reportid' => $originalid]) as $originalpersistent) {
            $record = $originalpersistent->to_record();
            unset($record->id);
            $record->reportid = $newid;
            $newpersistent = new $persistent(0, $record);
            $newpersistent->create();
        }
    }
}
