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
 * @package    moodlecore
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * restore plugin class that provides the necessary information
 * needed to restore one numerical qtype plugin
 *
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qtype_numerical_plugin extends restore_qtype_plugin {

    /**
     * Returns the paths to be handled by the plugin at question level
     */
    protected function define_question_plugin_structure() {

        $paths = array();

        // This qtype uses question_answers, add them.
        $this->add_question_question_answers($paths);

        // This qtype uses question_numerical_options and question_numerical_units, add them.
        $this->add_question_numerical_options($paths);
        $this->add_question_numerical_units($paths);

        // Add own qtype stuff.
        $elename = 'numerical';
        $elepath = $this->get_pathfor('/numerical_records/numerical_record');
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths; // And we return the interesting paths.
    }

    /**
     * Process the qtype/numerical element
     */
    public function process_numerical($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ? true : false;

        // If the question has been created by restore, we need to create its
        // question_numerical too.
        if ($questioncreated) {
            // Adjust some columns.
            $data->question = $newquestionid;
            $data->answer = $this->get_mappingid('question_answer', $data->answer);
            // Insert record.
            $newitemid = $DB->insert_record('question_numerical', $data);
        }
    }

    #[\Override]
    public static function convert_backup_to_questiondata(array $backupdata): \stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/question/type/numerical/questiontype.php');
        $questiondata = parent::convert_backup_to_questiondata($backupdata);
        if (count(get_object_vars($questiondata->options)) <= 2) {
            // Old question, set defaults.
            $qtype = new qtype_numerical();
            $questiondata->options->unitgradingtype = 0;
            $questiondata->options->unitpenalty = 0.1;
            if ($qtype->get_default_numerical_unit($questiondata)) {
                $questiondata->options->showunits = $qtype::UNITINPUT;
            } else {
                $questiondata->options->showunits = $qtype::UNITNONE;
            }
            $questiondata->options->unitsleft = 0;
        }
        if (isset($backupdata['plugin_qtype_numerical_question']['numerical_records'])) {
            foreach ($backupdata['plugin_qtype_numerical_question']['numerical_records']['numerical_record'] as $record) {
                foreach ($questiondata->options->answers as &$answer) {
                    if ($answer->id == $record['answer']) {
                        $answer->tolerance = $record['tolerance'];
                        continue 2;
                    }
                }
            }
        } else {
            // If the numerical record is missing (e.g. MDL-85721), default tolerances to 0.
            foreach ($questiondata->options->answers as &$answer) {
                $answer->tolerance = 0;
            }
        }
        return $questiondata;
    }
}
