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

use search_elastic\esrequest;

/**
 * Brings the semantic index in line with the course scope.
 *
 * Courses that left the scope (or no longer exist) are removed. Documents of courses in scope that are
 * missing are copied from the keyword index, which already holds the chunked documents.
 *
 * @package     search_elastic
 * @copyright   2026 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync {
    /** @var int Documents read from the keyword index per request. */
    private const BATCH = 200;

    /** @var int Largest number of courses read from the index. */
    private const MAX_COURSES = 10000;

    /** @var client */
    private client $http;

    /** @var string */
    private string $keywordindex;

    /** @var string */
    private string $semanticindex;

    /**
     * Constructor.
     *
     * @param esrequest $client HTTP client for the OpenSearch domain.
     * @param string $baseurl Domain URL, without trailing slash.
     * @param string $keywordindex Keyword index name.
     * @param indexer $indexer
     */
    public function __construct(esrequest $client, string $baseurl, string $keywordindex, private indexer $indexer) {
        $this->http = new client($client, $baseurl);
        $this->keywordindex = $keywordindex;
        $this->semanticindex = schema::get_index_name($keywordindex);
    }

    /**
     * Remove documents of courses that are no longer in scope.
     *
     * @param int[] $scopedids Ids of the courses in scope.
     * @return int[] Ids of the courses removed.
     */
    public function remove_out_of_scope(array $scopedids): array {
        $indexed = array_keys($this->count_by_course($this->semanticindex, []));
        $removed = array_values(array_diff($indexed, $scopedids));
        foreach ($removed as $courseid) {
            $this->indexer->delete_by_course($courseid);
        }

        return $removed;
    }

    /**
     * Copy documents missing from the semantic index, course by course.
     *
     * @param int[] $scopedids Ids of the courses in scope.
     * @param int $stopat Unix time after which no new course or batch is started.
     * @return int Documents copied.
     */
    public function backfill(array $scopedids, int $stopat): int {
        $filter = $this->get_indexable_filter();
        $keyword = $this->count_by_course($this->keywordindex, $filter);
        $semantic = $this->count_by_course($this->semanticindex, []);

        $copied = 0;
        foreach ($scopedids as $courseid) {
            if (($keyword[$courseid] ?? 0) <= ($semantic[$courseid] ?? 0)) {
                continue;
            }
            if (time() >= $stopat) {
                break;
            }
            $copied += $this->backfill_course($courseid, $filter, $stopat);
        }

        return $copied;
    }

    /**
     * Copy the missing documents of one course.
     *
     * @param int $courseid
     * @param array $filter Filter for documents that belong in the semantic index.
     * @param int $stopat
     * @return int Documents copied.
     */
    private function backfill_course(int $courseid, array $filter, int $stopat): int {
        $copied = 0;
        $after = null;
        do {
            $search = [
                'size' => self::BATCH,
                'sort' => [['id' => 'asc']],
                'query' => ['bool' => ['filter' => array_merge($filter, [['term' => ['courseid' => $courseid]]])]],
            ];
            if ($after !== null) {
                $search['search_after'] = [$after];
            }
            [$code, $body] = $this->http->request('POST', "/{$this->keywordindex}/_search", $search);
            $hits = $code === 200 ? ($body['hits']['hits'] ?? []) : [];
            if (!$hits) {
                break;
            }
            $after = end($hits)['_source']['id'];

            $existing = $this->get_existing_ids(array_column($hits, '_id'));
            $missing = [];
            foreach ($hits as $hit) {
                if (!isset($existing[$hit['_id']])) {
                    $missing[] = $hit['_source'];
                }
            }
            $this->indexer->add($missing);
            $copied += count($missing);
        } while (count($hits) === self::BATCH && time() < $stopat);
        $this->indexer->flush();

        return $copied;
    }

    /**
     * Which of the ids are already in the semantic index.
     *
     * @param string[] $ids
     * @return bool[] Id => true.
     */
    private function get_existing_ids(array $ids): array {
        [$code, $body] = $this->http->request('POST', "/{$this->semanticindex}/_search", [
            'size' => count($ids),
            '_source' => false,
            'query' => ['ids' => ['values' => $ids]],
        ]);

        return $code === 200 ? array_fill_keys(array_column($body['hits']['hits'] ?? [], '_id'), true) : [];
    }

    /**
     * Documents that belong in the semantic index: regular documents with text.
     *
     * @return array
     */
    private function get_indexable_filter(): array {
        return [
            ['term' => ['type' => \core_search\manager::TYPE_TEXT]],
            ['exists' => ['field' => schema::SOURCE_FIELD]],
        ];
    }

    /**
     * Count documents per course.
     *
     * @param string $index
     * @param array $filter Filters to count within.
     * @return int[] Course id => document count.
     */
    private function count_by_course(string $index, array $filter): array {
        [$code, $body] = $this->http->request('POST', "/{$index}/_search", [
            'size' => 0,
            'query' => ['bool' => ['filter' => $filter]],
            'aggs' => ['courses' => ['terms' => ['field' => 'courseid', 'size' => self::MAX_COURSES]]],
        ]);
        $counts = [];
        foreach ($code === 200 ? ($body['aggregations']['courses']['buckets'] ?? []) : [] as $bucket) {
            $counts[(int) $bucket['key']] = (int) $bucket['doc_count'];
        }

        return $counts;
    }
}
