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
 * Resolves the semantic search course scope settings into SQL.
 *
 * @package     search_elastic
 * @copyright   2026 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_scope {
    /** @var string Scope mode: all courses are in scope. */
    public const MODE_NONE = 'none';

    /** @var string Scope mode: courses are limited by course custom field values. */
    public const MODE_CUSTOMFIELD = 'customfield';

    /**
     * Whether the saved settings limit the scope by course custom fields.
     *
     * @return bool
     */
    public static function is_scoped(): bool {
        return get_config('search_elastic', 'semanticscopemode') === self::MODE_CUSTOMFIELD
            && self::parse_conditions((string) get_config('search_elastic', 'semanticcoursefilter')) !== [];
    }

    /**
     * Parse "shortname=value" lines into conditions, ignoring blank lines.
     *
     * @param string $config
     * @return array[] List of [shortname, value] pairs.
     */
    public static function parse_conditions(string $config): array {
        $conditions = [];
        foreach (preg_split('/\R/', $config) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
            $conditions[] = [trim($name), trim($value)];
        }
        return $conditions;
    }

    /**
     * Build SQL requiring a course to match every saved condition.
     *
     * @param string $coursealias Alias of the course table in the calling query.
     * @param string $paramprefix Prefix used to make aliases and parameter names unique.
     * @return array [sql, params]; sql is an empty string when no scoping applies.
     */
    public static function get_sql(string $coursealias, string $paramprefix = 'sem'): array {
        global $DB;

        if (!self::is_scoped()) {
            return ['', []];
        }

        $conditions = self::parse_conditions((string) get_config('search_elastic', 'semanticcoursefilter'));
        $clauses = [];
        $params = [];
        foreach ($conditions as $i => [$shortname, $value]) {
            $p = "{$paramprefix}{$i}";
            $clauses[] = "EXISTS (
                SELECT 1
                  FROM {customfield_data} {$p}d
                  JOIN {customfield_field} {$p}f ON {$p}f.id = {$p}d.fieldid
                  JOIN {customfield_category} {$p}c ON {$p}c.id = {$p}f.categoryid
                 WHERE {$p}d.instanceid = {$coursealias}.id
                   AND {$p}c.component = 'core_course'
                   AND {$p}c.area = 'course'
                   AND {$p}f.shortname = :{$p}name
                   AND " . $DB->sql_compare_text("{$p}d.value", 255) . " = " . $DB->sql_compare_text(":{$p}val", 255) . "
            )";
            $params["{$p}name"] = $shortname;
            $params["{$p}val"] = $value;
        }

        return [implode(' AND ', $clauses), $params];
    }
}
