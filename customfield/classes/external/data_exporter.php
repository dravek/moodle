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

namespace core_customfield\external;

use core\external\exporter;

/**
 * Class data_exporter
 *
 * @package    core_customfield
 * @copyright  2025 David Carrillo <davidmc@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_exporter extends exporter {

    /**
     * Return the list of properties used only for display.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'shortname' => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED],
            'name' => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED],
            'value' => ['type' => PARAM_TEXT, 'null' => NULL_ALLOWED],
        ];
    }
}
