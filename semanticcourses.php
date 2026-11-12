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
 * Lists the courses currently in the semantic search scope.
 *
 * @package    search_elastic
 * @copyright  2026 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use core_reportbuilder\system_report_factory;
use search_elastic\local\semantic\course_scope;
use search_elastic\reportbuilder\local\systemreports\semantic_courses;

admin_externalpage_setup('search_elastic_semanticcourses');

$PAGE->set_url(new moodle_url('/search/engine/elastic/semanticcourses.php'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('semanticcourses', 'search_elastic'));

if (!course_scope::is_scoped()) {
    $total = $DB->count_records_select('course', 'id <> ?', [SITEID]);
    echo $OUTPUT->notification(get_string('semanticcoursesallinscope', 'search_elastic', $total), 'info');
} else {
    echo html_writer::tag('p', get_string('semanticcoursessavedconfig', 'search_elastic'));
    echo html_writer::tag('pre', s(get_config('search_elastic', 'semanticcoursefilter')));
    echo system_report_factory::create(semantic_courses::class, context_system::instance())->output();
}

echo $OUTPUT->footer();
