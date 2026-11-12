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

namespace search_elastic\reportbuilder\local\systemreports;

use context_system;
use core_course\reportbuilder\local\entities\course_category;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\column;
use core_reportbuilder\system_report;
use lang_string;
use search_elastic\local\semantic\course_scope;

/**
 * Courses currently in the semantic search scope.
 *
 * @package     search_elastic
 * @copyright   2026 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class semantic_courses extends system_report {
    /**
     * Initialise the report.
     */
    protected function initialise(): void {
        $courseentity = new course();
        $coursealias = $courseentity->get_table_alias('course');

        $this->set_main_table('course', $coursealias);
        $this->add_entity($courseentity);

        $categoryentity = new course_category();
        $categoryalias = $categoryentity->get_table_alias('course_categories');
        $this->add_entity($categoryentity->add_join(
            "JOIN {course_categories} {$categoryalias} ON {$categoryalias}.id = {$coursealias}.category"
        ));

        // Exclude the site course.
        $siteparam = database::generate_param_name();
        $this->add_base_condition_sql("{$coursealias}.id != :{$siteparam}", [$siteparam => SITEID]);

        [$scopesql, $scopeparams] = course_scope::get_sql($coursealias, database::generate_param_name('_'));
        if ($scopesql !== '') {
            $this->add_base_condition_sql($scopesql, $scopeparams);
        }

        $this->add_column((new column(
            'courseid',
            new lang_string('courseid', 'search_elastic'),
            $courseentity->get_entity_name()
        ))
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$coursealias}.id", 'courseid')
            ->set_is_sortable(true));

        $this->add_columns_from_entity($courseentity->get_entity_name(), [
            'coursefullnamewithlink',
            'courseshortnamewithlink',
        ]);
        $this->add_columns_from_entity($categoryentity->get_entity_name(), ['name']);

        $this->set_initial_sort_column($courseentity->get_entity_name() . ':courseid', SORT_ASC);
        $this->set_default_per_page(50);
    }

    /**
     * Only site administrators can view the report.
     *
     * @return bool
     */
    protected function can_view(): bool {
        return has_capability('moodle/site:config', context_system::instance());
    }
}
