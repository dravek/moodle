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

use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\database_table;
use core_privacy\local\metadata\types\user_preference;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use core_reportbuilder\local\report\base;
use core_reportbuilder\manager;
use core_reportbuilder\local\helpers\user_filter_manager;
use core_reportbuilder\local\models\audience;
use core_reportbuilder\local\models\column;
use core_reportbuilder\local\models\filter;
use core_reportbuilder\local\models\report;
use core_reportbuilder\local\models\schedule;
use core_reportbuilder_generator;
use core_user\reportbuilder\datasource\users;

/**
 * Unit tests for privacy provider
 *
 * @package     core_reportbuilder
 * @covers      \core_reportbuilder\privacy\provider
 * @copyright   2021 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_test extends provider_testcase {

    /**
     * Test provider metadata
     */
    public function test_get_metadata(): void {
        $collection = new collection('core_reportbuilder');
        $metadata = provider::get_metadata($collection)->get_collection();

        $this->assertCount(6, $metadata);

        $this->assertInstanceOf(database_table::class, $metadata[0]);
        $this->assertEquals(report::TABLE, $metadata[0]->get_name());

        $this->assertInstanceOf(database_table::class, $metadata[1]);
        $this->assertEquals(column::TABLE, $metadata[1]->get_name());

        $this->assertInstanceOf(database_table::class, $metadata[2]);
        $this->assertEquals(filter::TABLE, $metadata[2]->get_name());

        $this->assertInstanceOf(database_table::class, $metadata[3]);
        $this->assertEquals(audience::TABLE, $metadata[3]->get_name());

        $this->assertInstanceOf(database_table::class, $metadata[4]);
        $this->assertEquals(schedule::TABLE, $metadata[4]->get_name());

        $this->assertInstanceOf(user_preference::class, $metadata[5]);
    }

    /**
     * Test to check export_user_preferences.
     */
    public function test_export_user_preferences(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);

        // Create report and set some filters for the user.
        $report1 = manager::create_report_persistent((object) [
            'type' => 1,
            'source' => 'class',
        ]);
        $filtervalues1 = [
            'task_log:name_operator' => 0,
            'task_log:name_value' => 'My task logs',
        ];
        user_filter_manager::set($report1->get('id'), $filtervalues1);

        // Add a filter for user2.
        $filtervalues1user2 = [
            'task_log:name_operator' => 0,
            'task_log:name_value' => 'My task logs user2',
        ];
        user_filter_manager::set($report1->get('id'), $filtervalues1user2, (int)$user2->id);

        // Create a second report and set some filters for the user.
        $report2 = manager::create_report_persistent((object) [
            'type' => 1,
            'source' => 'class',
        ]);
        $filtervalues2 = [
            'config_change:setting_operator' => 0,
            'config_change:setting_value' => str_repeat('A', 3000),
        ];
        user_filter_manager::set($report2->get('id'), $filtervalues2);

        // Switch to admin user (so we can validate preferences of our test user are still exported).
        $this->setAdminUser();

        // Export user preferences.
        provider::export_user_preferences((int)$user1->id);
        $writer = writer::with_context(context_system::instance());
        $prefs = $writer->get_user_preferences('core_reportbuilder');

        // Check that user preferences only contain the 2 preferences from user1.
        $this->assertCount(2, (array)$prefs);

        // Check that exported user preferences for report1 are correct.
        $report1key = 'reportbuilder-report-' . $report1->get('id');
        $this->assertEquals(json_encode($filtervalues1, JSON_PRETTY_PRINT), $prefs->$report1key->value);

        // Check that exported user preferences for report2 are correct.
        $report2key = 'reportbuilder-report-' . $report2->get('id');
        $this->assertEquals(json_encode($filtervalues2, JSON_PRETTY_PRINT), $prefs->$report2key->value);
    }

    /**
     * Test class export_user_data method
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $context = context_system::instance();
        $this->export_context_data_for_user((int)$user->id, $context, 'core_reportbuilder');

        $writer = writer::with_context($context);
        $this->assertFalse($writer->has_any_data());

        $report = $generator->create_report([
            'name' => 'My report',
            'type' => base::TYPE_CUSTOM_REPORT,
            'source' => users::class,
        ]);
        $audience = $generator->create_audience([
            'reportid' => $report->get('id'),
            'configdata' => [],
        ]);
        $schedule = $generator->create_schedule([
            'reportid' => $report->get('id'),
            'name' => 'My schedule',
            'audiences' => json_encode([$audience->get_persistent()->get('id')]),
            'timescheduled' => strtotime('7 September 2007 08:00'),
            'recurrence' => schedule::RECURRENCE_DAILY,
            'timelastsent' => strtotime('28 September 2010 08:00'),
            'format' => 'pdf',
            'subject' => 'Hello',
            'message' => 'Here\'s your weekly report',
            'enabled' => 1,
        ]);

        $this->setUser(null);

        $this->export_context_data_for_user((int)$user->id, $context, 'core_reportbuilder');

        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        $contextpath = provider::get_export_path($report);

        $reportdata = $writer->get_data($contextpath);
        $this->assertEquals($report->get('name'), $reportdata->name);
        $this->assertEquals($report->get('source'), $reportdata->source);
        $this->assertEquals($user->id, $reportdata->usercreated);
        $this->assertEquals($user->id, $reportdata->usermodified);
        $this->assertNotEmpty($reportdata->timecreated);
        $this->assertNotEmpty($reportdata->timemodified);

        // Get audience exported data.
        $audiencesdata = $writer->get_related_data($contextpath, 'audiences')->data;
        $this->assertCount(1, $audiencesdata);
        $this->assertEquals($audience->get_persistent()->get('classname'), $audiencesdata[0]->classname);
        $this->assertEquals($audience->get_persistent()->get('configdata'), $audiencesdata[0]->configdata);
        $this->assertEquals($user->id, $audiencesdata[0]->usercreated);
        $this->assertEquals($user->id, $audiencesdata[0]->usermodified);
        $this->assertNotEmpty($audiencesdata[0]->timecreated);
        $this->assertNotEmpty($audiencesdata[0]->timemodified);

        // Get schedules exported data.
        $schedulesdata = $writer->get_related_data($contextpath, 'schedules')->data;
        $this->assertCount(1, $schedulesdata);
        $this->assertEquals($schedule->get('name'), $schedulesdata[0]->name);
        $this->assertEquals($schedule->get('subject'), $schedulesdata[0]->subject);
        $this->assertEquals($schedule->get('message'), $schedulesdata[0]->message);
        $this->assertEquals($schedule->get('enabled'), $schedulesdata[0]->enabled);
        $this->assertEquals($schedule->get('audiences'), $schedulesdata[0]->audiences);
        $this->assertNotEmpty($schedulesdata[0]->timescheduled);
        $this->assertNotEmpty($schedulesdata[0]->timelastsent);
        $this->assertEquals($schedule->get('recurrence'), $schedulesdata[0]->recurrence);
        $this->assertNotEmpty($schedulesdata[0]->timenextsend);
        $this->assertEquals($schedule->get('userviewas'), $schedulesdata[0]->userviewas);
        $this->assertEquals($user->id, $schedulesdata[0]->usercreated);
        $this->assertEquals($user->id, $schedulesdata[0]->usermodified);
        $this->assertNotEmpty($schedulesdata[0]->timecreated);
        $this->assertNotEmpty($schedulesdata[0]->timemodified);
    }

    /**
     * Test class test_export_user_data method with a different user
     */
    public function test_export_user_data_different_user(): void {
        $this->resetAfterTest();

        /** @var core_reportbuilder_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');

        $user1 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);

        $report1 = $generator->create_report([
            'name' => 'My report',
            'type' => base::TYPE_CUSTOM_REPORT,
            'source' => users::class,
        ]);
        $audience1a = $generator->create_audience([
            'reportid' => $report1->get('id'),
            'configdata' => ['users' => [$user1->id]],
        ]);

        // Add a second user who creates and audience in $report and creates a new $report2 with another audience.
        $user2 = $this->getDataGenerator()->create_user();
        $this->setUser($user2);

        $report2 = $generator->create_report([
            'name' => 'My report 2',
            'type' => base::TYPE_CUSTOM_REPORT,
            'source' => users::class,
        ]);
        $audience2 = $generator->create_audience([
            'reportid' => $report2->get('id'),
            'configdata' => ['users' => [$user2->id]],
        ]);
        $audience1b = $generator->create_audience([
            'reportid' => $report1->get('id'),
            'configdata' => ['users' => [$user2->id]],
        ]);

        $this->setUser(null);

        $context = context_system::instance();
        $this->export_context_data_for_user((int)$user2->id, $context, 'core_reportbuilder');

        $writer = writer::with_context($context);

        // Get report1 exported data.
        $contextpath = provider::get_export_path($report1);

        $reportdata = $writer->get_data($contextpath);
        $this->assertEquals($report1->get('name'), $reportdata->name);
        $this->assertEquals($report1->get('source'), $reportdata->source);
        $this->assertEquals($user1->id, $reportdata->usercreated);
        $this->assertEquals($user1->id, $reportdata->usermodified);
        $this->assertNotEmpty($reportdata->timecreated);
        $this->assertNotEmpty($reportdata->timemodified);

        // Get audience exported data.
        $audiencesdata = $writer->get_related_data($contextpath, 'audiences')->data;
        $this->assertCount(1, $audiencesdata);
        $this->assertEquals($audience1b->get_persistent()->get('classname'), $audiencesdata[0]->classname);
        $this->assertEquals($audience1b->get_persistent()->get('configdata'), $audiencesdata[0]->configdata);
        $this->assertEquals($user2->id, $audiencesdata[0]->usercreated);
        $this->assertEquals($user2->id, $audiencesdata[0]->usermodified);
        $this->assertNotEmpty($audiencesdata[0]->timecreated);
        $this->assertNotEmpty($audiencesdata[0]->timemodified);

        // Get report2 exported data.
        $contextpath = provider::get_export_path($report2);

        $reportdata2 = $writer->get_data($contextpath);
        $this->assertEquals($report2->get('name'), $reportdata2->name);
        $this->assertEquals($report2->get('source'), $reportdata2->source);
        $this->assertEquals($user2->id, $reportdata2->usercreated);
        $this->assertEquals($user2->id, $reportdata2->usermodified);
        $this->assertNotEmpty($reportdata2->timecreated);
        $this->assertNotEmpty($reportdata2->timemodified);

        // Get audience exported data.
        $audiencesdata2 = $writer->get_related_data($contextpath, 'audiences')->data;
        $this->assertCount(1, $audiencesdata2);
        $this->assertEquals($audience2->get_persistent()->get('classname'), $audiencesdata2[0]->classname);
        $this->assertEquals($audience2->get_persistent()->get('configdata'), $audiencesdata2[0]->configdata);
        $this->assertEquals($user2->id, $audiencesdata2[0]->usercreated);
        $this->assertEquals($user2->id, $audiencesdata2[0]->usermodified);
        $this->assertNotEmpty($audiencesdata2[0]->timecreated);
        $this->assertNotEmpty($audiencesdata2[0]->timemodified);
    }
}
