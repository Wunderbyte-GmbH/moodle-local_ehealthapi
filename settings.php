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

/**
 * Plugin administration entities are defined here.
 *
 * @package     local_ehealthapi
 * @category    admin
 * @copyright   2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$componentname = 'local_ehealthapi';

// Default for users that have site config.
if ($hassiteconfig) {
    // Add the category to the local plugin branch.
    $settings = new admin_settingpage($componentname . '_settings', '');
    $ADMIN->add('localplugins', new admin_category($componentname, get_string('pluginname', $componentname)));
    $ADMIN->add($componentname, $settings);

    // Add API URL setting.
    $settings->add(
            new admin_setting_configtext(
                    $componentname . '/apiurl',
                    get_string('apiurl', $componentname),
                    get_string('apiurl:description', $componentname),
                    'https://example.com/api', // Default API URL
                    PARAM_URL
            )
    );

    // Add API token setting.
    $settings->add(
            new admin_setting_configtext(
                    $componentname . '/apitoken',
                    get_string('apitoken', $componentname),
                    get_string('apitoken:description', $componentname),
                    '', // Default API token
                    PARAM_TEXT
            )
    );
}
