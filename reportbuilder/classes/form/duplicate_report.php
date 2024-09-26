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

namespace core_reportbuilder\form;

use context;
use core_form\dynamic_form;
use core_reportbuilder\manager;
use core_reportbuilder\permission;
use moodle_url;

/**
 * Dynamic duplicate custom reports form
 *
 * @package     core_reportbuilder
 * @copyright   2024 David Carrillo <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class duplicate_report extends dynamic_form {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'reportid');
        $mform->setType('reportid', PARAM_INT);

        $mform->addElement('text', 'reportname', get_string('editreportname', 'core_reportbuilder'));
        $mform->setType('reportname', PARAM_TEXT);
        $mform->setDefault('reportname', $this->optional_param('reportname', 0, PARAM_TEXT));

        $mform->addElement('advcheckbox', 'audiences', get_string('duplicateaudiences', 'core_reportbuilder'));
        $mform->addHelpButton('audiences', 'duplicateaudiences', 'core_reportbuilder');

        $mform->addElement('advcheckbox', 'schedules', get_string('duplicateschedules', 'core_reportbuilder'));
        $mform->addHelpButton('schedules', 'duplicateschedules', 'core_reportbuilder');
        $mform->disabledIf('schedules', 'audiences', 'eq', 0);
    }

    /**
     * Form validation.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        $errors = [];

        if (trim($data['reportname']) === '') {
            $errors['reportname'] = get_string('required');
        }

        return $errors;
    }

    /**
     * Returns context where this form is used
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        $report = manager::get_report_from_id($this->optional_param('reportid', 0, PARAM_INT));
        return $report->get_context();
    }

    /**
     * Ensure current user is able to use this form
     *
     * A {@see \core_reportbuilder\exception\report_access_exception} will be thrown if they can't
     */
    protected function check_access_for_dynamic_submission(): void {
        $report = manager::get_report_from_id($this->optional_param('reportid', 0, PARAM_INT));
        permission::require_can_edit_report($report->get_report_persistent()) && permission::require_can_create_report();
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $report = manager::get_report_from_id($this->optional_param('reportid', 0, PARAM_INT));

        $newreport = manager::duplicate_report(
            $report->get_report_persistent(),
            $data->reportname ?? '',
            (bool)$data->audiences,
            (bool)$data->schedules
        );

        return (new moodle_url('/reportbuilder/edit.php', ['id' => $newreport->get('id')]))->out(false);
    }

    /**
     * Load in existing data as form defaults
     */
    public function set_data_for_dynamic_submission(): void {
        $this->set_data($this->_ajaxformdata);
    }

    /**
     * Page url
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/reportbuilder/edit.php', ['id' => $this->optional_param('reportid', 0, PARAM_INT)]);
    }
}
