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
 * Elastic search engine settings.
 *
 * @package    search_elastic
 * @copyright  Matt Porritt <mattp@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('search_elastic_settings', '', get_string('pluginname_desc', 'search_elastic')));

    if (! during_initial_install ()) {
        $settings->add(new admin_setting_configtext('search_elastic_hostname',
                get_string('hostname', 'search_elastic' ),
                get_string('hostname_desc', 'search_elastic'),
                'localhost', PARAM_HOST));

        $settings->add(new admin_setting_configtext('search_elastic_port',
                get_string('port', 'search_elastic' ),
                get_string('port_desc', 'search_elastic'),
                9200, PARAM_INT));
    }
}
