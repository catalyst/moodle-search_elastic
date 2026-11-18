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
use search_elastic\local\service\error_service;

/**
 * Writes to and deletes from the semantic index, for documents in courses in the semantic scope only.
 *
 * Nothing here throws: the keyword index must never depend on the semantic index. Failures are recorded
 * with the error service, so the existing retry task re-indexes the document through the normal path.
 *
 * @package     search_elastic
 * @copyright   2026 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class indexer {
    /** @var client */
    private client $http;

    /** @var string Semantic index name. */
    private string $index;

    /** @var string Bulk request body being built. */
    private string $payload = '';

    /** @var array[] Documents in the bulk request, by id, kept to record failures. */
    private array $pending = [];

    /** @var bool[]|null Course id => true for courses in scope, loaded on first use. */
    private ?array $scope = null;

    /**
     * Constructor.
     *
     * @param esrequest $client HTTP client for the OpenSearch domain.
     * @param string $baseurl Domain URL, without trailing slash.
     * @param string $keywordindex Keyword index name; the semantic index name derives from it.
     * @param int $sendsize Send the bulk request once it reaches this many bytes.
     */
    public function __construct(esrequest $client, string $baseurl, string $keywordindex, private int $sendsize = 9000000) {
        $this->http = new client($client, $baseurl);
        $this->index = schema::get_index_name($keywordindex);
    }

    /**
     * Whether semantic search is switched on in the settings.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return (bool) get_config('search_elastic', 'semanticsearchenabled');
    }

    /**
     * Whether a document belongs in the semantic index.
     *
     * @param array $docdata Document as sent to the keyword index.
     * @return bool
     */
    public function accepts(array $docdata): bool {
        if (
            !isset($docdata['courseid'])
            || ($docdata['type'] ?? null) == \core_search\manager::TYPE_FILE
            || trim((string) ($docdata['content'] ?? '')) === ''
        ) {
            return false;
        }
        $this->scope ??= array_fill_keys(course_scope::get_courseids(), true);

        return isset($this->scope[(int) $docdata['courseid']]);
    }

    /**
     * Queue documents, or the chunks of one document, for the semantic index. Out of scope documents are skipped.
     *
     * @param array[] $docs Documents as sent to the keyword index.
     */
    public function add(array $docs): void {
        foreach ($docs as $doc) {
            if (!$this->accepts($doc)) {
                continue;
            }
            unset($doc['filetext']);
            $meta = json_encode(['index' => ['_index' => $this->index, '_id' => $doc['id']]]);
            $line = $meta . "\n" . json_encode($doc) . "\n";
            $this->payload .= $line;
            $this->pending[$doc['id']] = $doc;
        }
        if (strlen($this->payload) >= $this->sendsize) {
            $this->flush();
        }
    }

    /**
     * Send the queued documents.
     */
    public function flush(): void {
        if ($this->payload === '') {
            return;
        }
        $payload = $this->payload;
        $pending = $this->pending;
        $this->payload = '';
        $this->pending = [];

        try {
            [$code, $body] = $this->http->request('POST', '/' . $this->index . '/_bulk', $payload);
        } catch (\Throwable $e) {
            $this->record_failure($e->getMessage(), $pending);
            return;
        }
        if ($code >= 300) {
            $this->record_failure("HTTP {$code} " . ($body['error']['reason'] ?? ''), $pending);
            return;
        }
        if (empty($body['errors'])) {
            return;
        }
        foreach ($body['items'] ?? [] as $item) {
            $result = $item['index'] ?? [];
            if (($result['status'] ?? 200) >= 300 && isset($pending[$result['_id'] ?? ''])) {
                $reason = $result['error']['reason'] ?? 'unknown';
                $this->record_failure($reason, [$result['_id'] => $pending[$result['_id']]]);
            }
        }
    }

    /**
     * Delete one document.
     *
     * @param string $id
     */
    public function delete_by_id(string $id): void {
        $this->delete_by_query(['term' => ['id' => $id]]);
    }

    /**
     * Delete all chunks of a document.
     *
     * @param string $originalid
     */
    public function delete_by_original_id(string $originalid): void {
        $this->delete_by_query(['term' => ['original_id' => $originalid]]);
    }

    /**
     * Delete all documents of a search area.
     *
     * @param string $areaid
     */
    public function delete_by_area(string $areaid): void {
        $this->delete_by_query(['term' => ['areaid' => $areaid]]);
    }

    /**
     * Delete all documents of a course.
     *
     * @param int $courseid
     */
    public function delete_by_course(int $courseid): void {
        $this->delete_by_query(['term' => ['courseid' => $courseid]]);
    }

    /**
     * Delete every document.
     */
    public function delete_all(): void {
        $this->delete_by_query(['match_all' => new \stdClass()]);
    }

    /**
     * Delete by query, ignoring any failure.
     *
     * @param array $query
     */
    private function delete_by_query(array $query): void {
        try {
            $this->http->request('POST', '/' . $this->index . '/_delete_by_query?conflicts=proceed', ['query' => $query]);
        } catch (\Throwable $e) {
            debugging('Semantic delete failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Record failed documents with the error service so they are retried.
     *
     * @param string $reason
     * @param array[] $docs
     */
    private function record_failure(string $reason, array $docs): void {
        foreach ($docs as $doc) {
            error_service::record_document_error(
                get_string('semanticindexfail', 'search_elastic', $reason),
                $doc
            );
        }
    }
}
