<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_ehealthapi\event;

/**
 * The certificate_transferred event class.
 *
 * @package     local_ehealthapi
 * @category    event
 * @copyright   2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificate_transferred extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_ehealthapi';
    }

    public static function get_name() {
        return get_string('transfer_completed', 'local_ehealthapi');
    }

    public function get_description() {
        return "The course completion of course with id '{$this->courseid}' for user with id '{$this->userid}'
        was successfully transferred.";
    }

    public function get_url() {
        return new \moodle_url('/course/view.php', ['id' => $this->courseid]);
    }
}
