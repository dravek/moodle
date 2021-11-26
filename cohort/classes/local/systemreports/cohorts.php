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

use context;
use core_cohort\local\entities\cohort;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\column;
use core_reportbuilder\system_report;
use html_writer;
use lang_string;
use moodle_url;
use stdClass;

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
        return true;
    }

    /**
     * Adds the columns we want to display in the report
     *
     * They are provided by the entities we previously added in the {@see initialise} method, referencing each by their
     * unique identifier. If custom columns are needed just for this report, they can be defined here.
     */
    public function add_columns(): void {
        $entitymainalias = $this->entitymain->get_table_alias('cohort');
        $showall = $this->get_parameter('showall', null, PARAM_INT);

        // Category column.
        if (!empty($showall) && $showall === 1) {
            $this->add_column(new column(
                'context',
                new lang_string('category'),
                $this->entitymain->get_entity_name()
            ))
                ->set_type(column::TYPE_INTEGER)
                ->add_fields("{$entitymainalias}.contextid")
                ->set_is_sortable(true)
                ->add_callback(static function (int $contextid, stdClass $cohort): string {
                    $cohortcontext = context::instance_by_id($cohort->contextid);
                    if ($cohortcontext->contextlevel === CONTEXT_COURSECAT) {
                        return html_writer::link(new moodle_url('/cohort/index.php',
                            ['contextid' => $cohort->contextid]), $cohortcontext->get_context_name(false));
                    }

                    return $cohortcontext->get_context_name(false);
                });
        }

        // Name column using the inplace editable component.
        $this->add_column(new column(
            'editablename',
            new lang_string('name', 'core_cohort'),
            $this->entitymain->get_entity_name()
        ))
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_fields("{$entitymainalias}.name, {$entitymainalias}.id, {$entitymainalias}.contextid")
            ->add_callback(static function(string $name, stdClass $cohort): string {
                global $OUTPUT, $PAGE;
                $renderer = $PAGE->get_renderer('core');

                $template = new \core_cohort\output\cohortname($cohort);
                return $renderer->render_from_template('core/inplace_editable', $template->export_for_template($OUTPUT));
            });

        // ID Number column using the inplace editable component.
        $this->add_column(new column(
            'editableidnumber',
            new lang_string('idnumber', 'core_cohort'),
            $this->entitymain->get_entity_name()
        ))
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_fields("{$entitymainalias}.idnumber, {$entitymainalias}.id, {$entitymainalias}.contextid")
            ->add_callback(static function(string $idnumber, stdClass $cohort): string {
                global $OUTPUT, $PAGE;
                $renderer = $PAGE->get_renderer('core');

                $template = new \core_cohort\output\cohortidnumber($cohort);
                return $renderer->render_from_template('core/inplace_editable', $template->export_for_template($OUTPUT));
            });

        // Description column.
        $this->add_column_from_entity('cohort:description');

        // Cohort size column using a custom SQL query to count cohort members.
        $cm = database::generate_param_name();
        $sql = "(SELECT count($cm.id) as memberscount
                FROM {cohort_members} $cm
                WHERE $cm.cohortid = {$entitymainalias}.id)";
        $this->add_column(new column(
            'memberscount',
            new lang_string('memberscount', 'cohort'),
            $this->entitymain->get_entity_name()
        ))
            ->set_type(column::TYPE_INTEGER)
            ->set_is_sortable(true)
            ->add_field($sql, 'memberscount');

        // Component column.
        $this->add_column_from_entity('cohort:component');

        // It's possible to override the display name of a column, if you don't want to use the value provided by the entity.
        if ($column = $this->get_column('cohort:component')) {
            $column->set_title(new lang_string('source', 'core_plugin'));
        }
    }

    /**
     * Adds the filters we want to display in the report
     *
     * They are all provided by the entities we previously added in the {@see initialise} method, referencing each by their
     * unique identifier
     */
    protected function add_filters(): void {
        $filters = [
            'cohort:name',
            'cohort:idnumber',
        ];
        $this->add_filters_from_entities($filters);
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
