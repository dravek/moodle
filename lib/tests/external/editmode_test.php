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

namespace core\external;

use context_user;
use core_user;
use external_api;
use externallib_advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->dirroot}/webservice/tests/helpers.php");

/**
 * Unit tests of external class to change the edit mode
 *
 * @package     core
 * @covers      \core\external\editmode
 * @copyright   2022 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editmode_test extends externallib_advanced_testcase {

    /**
     * Text change_editmode method
     *
     * @param string $currentusername
     * @param string $testusername
     * @param string $pagetype
     * @param bool $hasmymanageblockscap
     * @param bool $expected Expected result
     *
     * @dataProvider change_editmode_provider
     */
    public function test_change_editmode(string $currentusername, string $testusername, string $pagetype,
                                         bool $hasmymanageblockscap, bool $expected): void {
        $this->resetAfterTest();

        $this->getDataGenerator()->create_user(['username' => 'user01']);
        $this->getDataGenerator()->create_user(['username' => 'user02']);

        $currentuser = core_user::get_user_by_username($currentusername);
        $testuser = core_user::get_user_by_username($testusername);
        $testusercontext = context_user::instance($testuser->id);

        if (!$hasmymanageblockscap) {
            // Capability 'moodle/my:manageblocks' is prohibited.
            $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
            assign_capability('moodle/my:manageblocks', CAP_PROHIBIT, $roleid, $testusercontext->id);
            role_assign($roleid, $currentuser->id, $testusercontext->id);
        }

        $this->setUser($currentuser);

        $result = editmode::change_editmode(true, $testusercontext->id, $pagetype);
        $result = external_api::clean_returnvalue(editmode::change_editmode_returns(), $result);

        if ($expected) {
            $this->assertTrue($result['success']);
        } else {
            $this->assertFalse($result['success']);
        }
    }

    /**
     * Data provider for {@see test_change_editmode}
     *
     * @return array
     */
    public function change_editmode_provider(): array {
        return [
            // User can edit its own dashboard page blocks.
            ['user01', 'user01', 'my-index', true, true],
            // User can edit its own profile page blocks.
            ['user01', 'user01', 'user-profile', true, true],
            // A different user cannot edit another user profile page blocks.
            ['user02', 'user01', 'user-profile', true, false],
            // User cannot edit its own dashboard page blocks without the capability 'moodle/my:manageblocks'.
            ['user01', 'user01', 'my-index', false, false],
            // User can edit its own profile page blocks without the capability 'moodle/my:manageblocks'.
            ['user01', 'user01', 'user-profile', false, true],
            // A different user cannot edit another user profile page blocks without the capability 'moodle/my:manageblocks'.
            ['user02', 'user01', 'user-profile', false, false],
        ];
    }
}
