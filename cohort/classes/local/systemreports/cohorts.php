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

namespace core_cohort\local\systemreports;

use core_cohort\local\entities\cohort;
use core_reportbuilder\system_report;

/**
 * Cohorts system report class implementation
 *
 * @package    core_cohort
 * @copyright  2021 David Matamoros <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohorts extends system_report {

    /** @var cohort $entitymain */
    private $entitymain = null;

    protected function initialise(): void {
        // Our main entity, it contains all of the column definitions that we need.
        $this->entitymain = new cohort();
        $entitymainalias = $this->entitymain->get_table_alias('cohort');

        $this->set_main_table('cohort', $entitymainalias);
        $this->add_entity($this->entitymain);

        // Any columns required by actions should be defined here to ensure they're always available.
        $this->add_base_fields("{$entitymainalias}.id");

        // Now we can call our helper methods to add the content we want to include in the report.
        $this->add_columns();
        $this->add_filters();
        $this->add_actions();

        // Set if report can be downloaded.
        $this->set_downloadable(false);
    }

    protected function can_view(): bool {
        // TODO: Implement can_view() method.
    }

    /**
     * Adds the columns we want to display in the report
     *
     * They are provided by the entities we previously added in the {@see initialise} method, referencing each by their
     * unique identifier. If custom columns are needed just for this report, they can be defined here.
     */
    public function add_columns(): void {
        // TODO.
    }

    /**
     * Adds the filters we want to display in the report
     *
     * They are all provided by the entities we previously added in the {@see initialise} method, referencing each by their
     * unique identifier
     */
    protected function add_filters(): void {
        // TODO.
    }

    /**
     * Add the system report actions. An extra column will be appended to each row, containing all actions added here
     *
     * Note the use of ":id" placeholder which will be substituted according to actual values in the row
     */
    protected function add_actions(): void {
        // TODO.
    }
}
