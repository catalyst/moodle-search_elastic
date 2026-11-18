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

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use search_elastic\esrequest;

/**
 * Tests for writing the semantic index and keeping it in step with the course scope.
 *
 * @package     search_elastic
 * @copyright   2026 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \search_elastic\local\semantic\indexer
 * @covers      \search_elastic\local\semantic\sync
 */
final class indexer_test extends \advanced_testcase {
    /** @var MockHandler */
    private MockHandler $mock;

    /** @var array Requests the mock received. */
    private array $history = [];

    /**
     * Create an esrequest answering from the queued responses.
     *
     * @param Response[] $responses
     * @return esrequest
     */
    private function get_client(array $responses): esrequest {
        $this->mock = new MockHandler($responses);
        $stack = HandlerStack::create($this->mock);
        $stack->push(\GuzzleHttp\Middleware::history($this->history));
        return new esrequest($stack);
    }

    /**
     * Build a JSON response.
     *
     * @param array $body
     * @param int $status
     * @return Response
     */
    private function json(array $body, int $status = 200): Response {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }

    /**
     * Only course documents with text, from courses in scope, are accepted.
     */
    public function test_accepts_only_in_scope_course_text(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $category = $generator->create_custom_field_category(['name' => 'Semantic']);
        $generator->create_custom_field(['categoryid' => $category->get('id'), 'shortname' => 'optin', 'type' => 'text']);
        $in = $generator->create_course(['customfield_optin' => '1']);
        $out = $generator->create_course();
        set_config('semanticscopemode', 'customfield', 'search_elastic');
        set_config('semanticcoursefilter', 'optin=1', 'search_elastic');

        $indexer = new indexer($this->get_client([]), 'https://domain.example', 'moodle');
        $doc = ['id' => 'a', 'courseid' => $in->id, 'content' => 'text', 'type' => 1];

        $this->assertTrue($indexer->accepts($doc));
        $this->assertFalse($indexer->accepts(['courseid' => $out->id] + $doc));
        $this->assertFalse($indexer->accepts(['courseid' => SITEID] + $doc));
        $this->assertFalse($indexer->accepts(['content' => ' '] + $doc));
        $this->assertFalse($indexer->accepts(['type' => 2] + $doc));
    }

    /**
     * A failed bulk write is recorded for retry, not thrown.
     */
    public function test_failed_write_is_recorded(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $indexer = new indexer($this->get_client([$this->json(['error' => ['reason' => 'boom']], 500)]), 'https://d.example', 'moodle');
        $indexer->add([['id' => 'a', 'courseid' => $course->id, 'content' => 'text', 'type' => 1, 'filetext' => 'x']]);
        $indexer->flush();

        $this->assertDebuggingCalled();
        $this->assertSame(1, $DB->count_records('search_elastic_errors'));
        $this->assertStringContainsString('/semantic_moodle/_bulk', (string) $this->history[0]['request']->getUri());
        $this->assertStringNotContainsString('filetext', (string) $this->history[0]['request']->getBody());
    }

    /**
     * Sync removes courses that left the scope, and copies only the documents that are missing.
     */
    public function test_sync_removes_and_backfills(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $doc = ['id' => 'a', 'courseid' => (int) $course->id, 'content' => 'text', 'type' => 1];

        $client = $this->get_client([
            $this->json(['aggregations' => ['courses' => ['buckets' => [['key' => 999, 'doc_count' => 3]]]]]),
            $this->json(['deleted' => 3]),
            $this->json(['aggregations' => ['courses' => ['buckets' => [['key' => (int) $course->id, 'doc_count' => 2]]]]]),
            $this->json(['aggregations' => ['courses' => ['buckets' => []]]]),
            $this->json(['hits' => ['hits' => [
                ['_id' => 'a', '_source' => $doc],
                ['_id' => 'b', '_source' => ['id' => 'b'] + $doc],
            ]]]),
            $this->json(['hits' => ['hits' => [['_id' => 'a']]]]),
            $this->json(['errors' => false]),
        ]);
        $indexer = new indexer($client, 'https://d.example', 'moodle');
        $sync = new sync($client, 'https://d.example', 'moodle', $indexer);

        $removed = $sync->remove_out_of_scope([(int) $course->id]);
        $copied = $sync->backfill([(int) $course->id], time() + 60);

        $this->assertSame([999], $removed);
        $this->assertSame(1, $copied);
        $bulk = (string) end($this->history)['request']->getBody();
        $this->assertStringContainsString('"_id":"b"', $bulk);
        $this->assertStringNotContainsString('"_id":"a"', $bulk);
    }
}
