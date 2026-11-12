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

namespace search_elastic\local\semantic;

/**
 * Tests for the semantic search course scope.
 *
 * @package     search_elastic
 * @copyright   2026 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \search_elastic\local\semantic\course_scope
 */
final class course_scope_test extends \advanced_testcase {
    /**
     * Get ids of courses matching the saved scope.
     *
     * @return int[]
     */
    private function get_scoped_courseids(): array {
        global $DB;
        [$sql, $params] = course_scope::get_sql('c');
        return array_map('intval', array_keys($DB->get_records_sql("SELECT c.id FROM {course} c WHERE {$sql}", $params)));
    }

    /**
     * Every condition must match exactly, and courses without a data row do not match.
     */
    public function test_conditions_are_anded_with_exact_match(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $category = $generator->create_custom_field_category(['name' => 'Semantic']);
        $generator->create_custom_field(['categoryid' => $category->get('id'), 'shortname' => 'allowed', 'type' => 'text']);
        $generator->create_custom_field(['categoryid' => $category->get('id'), 'shortname' => 'optin', 'type' => 'text']);

        $both = $generator->create_course(['customfield_allowed' => '1', 'customfield_optin' => '1']);
        $generator->create_course(['customfield_allowed' => '1', 'customfield_optin' => '0']);
        $generator->create_course(['customfield_allowed' => '10', 'customfield_optin' => '1']);
        $generator->create_course();

        set_config('semanticscopemode', 'customfield', 'search_elastic');
        set_config('semanticcoursefilter', "allowed=1\n\n  optin=1  \n", 'search_elastic');

        $this->assertTrue(course_scope::is_scoped());
        $this->assertSame([(int) $both->id], $this->get_scoped_courseids());
    }

    /**
     * The system report lists only the scoped courses.
     */
    public function test_report_lists_scoped_courses(): void {
        $this->resetAfterTest();
        global $PAGE;
        $this->setAdminUser();
        $PAGE->set_url('/search/engine/elastic/semanticcourses.php');
        $generator = $this->getDataGenerator();
        $category = $generator->create_custom_field_category(['name' => 'Semantic']);
        $generator->create_custom_field(['categoryid' => $category->get('id'), 'shortname' => 'allowed', 'type' => 'text']);
        $generator->create_course(['fullname' => 'Inscope course', 'customfield_allowed' => '1']);
        $generator->create_course(['fullname' => 'Outscope course']);

        set_config('semanticscopemode', 'customfield', 'search_elastic');
        set_config('semanticcoursefilter', 'allowed=1', 'search_elastic');

        $report = \core_reportbuilder\system_report_factory::create(
            \search_elastic\reportbuilder\local\systemreports\semantic_courses::class,
            \context_system::instance()
        );
        $output = $report->output();
        $this->assertStringContainsString('Inscope course', $output);
        $this->assertStringNotContainsString('Outscope course', $output);
    }

    /**
     * No scoping applies unless the mode is custom field and there is at least one condition.
     */
    public function test_not_scoped(): void {
        $this->resetAfterTest();

        $this->assertFalse(course_scope::is_scoped());

        set_config('semanticscopemode', 'customfield', 'search_elastic');
        set_config('semanticcoursefilter', " \n", 'search_elastic');
        $this->assertFalse(course_scope::is_scoped());
        $this->assertSame(['', []], course_scope::get_sql('c'));

        set_config('semanticscopemode', 'none', 'search_elastic');
        set_config('semanticcoursefilter', 'allowed=1', 'search_elastic');
        $this->assertFalse(course_scope::is_scoped());
    }
}
