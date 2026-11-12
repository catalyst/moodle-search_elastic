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

namespace search_elastic;

use admin_setting_configtextarea;
use core_course\customfield\course_handler;

/**
 * Admin setting holding course custom field conditions (one "shortname=value" per line, all must match).
 *
 * @package     search_elastic
 * @copyright   2026 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_course_custom_field_filter extends admin_setting_configtextarea {
    /**
     * Validate that every non-blank line is "shortname=value" for an existing course custom field.
     *
     * @param string $data
     * @return mixed true if ok, string if error found
     */
    public function validate($data) {
        $result = parent::validate($data);
        if ($result !== true) {
            return $result;
        }

        $shortnames = [];
        foreach (course_handler::create()->get_fields() as $field) {
            $shortnames[] = $field->get('shortname');
        }

        foreach (preg_split('/\R/', (string) $data) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = explode('=', $line, 2);
            $name = trim($parts[0]);
            if (count($parts) !== 2 || $name === '') {
                return get_string('semanticfilterinvalidline', 'search_elastic', s($line));
            }
            if (!in_array($name, $shortnames, true)) {
                return get_string('semanticfilterunknownfield', 'search_elastic', s($name));
            }
        }

        return true;
    }
}
