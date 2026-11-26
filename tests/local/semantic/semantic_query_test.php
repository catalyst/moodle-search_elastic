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

use search_elastic\query;

/**
 * Tests for the semantic query.
 *
 * @package     search_elastic
 * @copyright   2026 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \search_elastic\query
 */
final class semantic_query_test extends \advanced_testcase {
    /**
     * The semantic query is the keyword query with only the text clause swapped for a neural one.
     */
    public function test_semantic_query_differs_only_in_text_clause(): void {
        $filters = (object) [
            'q' => 'plants', 'courseids' => [3], 'areaids' => ['mod_page'], 'timestart' => 0, 'timeend' => 0,
            'title' => 'T', 'order' => 'desc',
        ];
        $keyword = (new query())->get_query($filters, [5, 6]);
        $semantic = (new query())->get_query($filters, [5, 6], 'm1');

        $this->assertSame($keyword['size'], $semantic['size']);
        $this->assertSame($keyword['sort'], $semantic['sort']);
        $this->assertSame($keyword['query']['bool']['should'], $semantic['query']['bool']['should']);
        $this->assertEquals($keyword['highlight']['fields'], $semantic['highlight']['fields']);
        $this->assertArrayHasKey('highlight_query', $semantic['highlight']);

        $neural = $semantic['query']['bool']['must'][0]['neural']['content_v'];
        $this->assertSame('plants', $neural['query_text']);
        $this->assertSame('m1', $neural['model_id']);
        $filter = json_encode($neural['filter']);
        $this->assertStringContainsString('"courseid":[3]', $filter);
        $this->assertStringContainsString('"contextid":[5,6]', $filter);
        $this->assertStringContainsString('"prefix":{"areaid":"mod_page-"}', $filter);
        $this->assertStringContainsString('phrase_prefix', $filter);
        $this->assertArrayNotHasKey('filter', $semantic['query']['bool']);
    }
}
