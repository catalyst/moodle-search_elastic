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

namespace search_elastic\check;

use action_link;
use core\check\check;
use core\check\result;
use moodle_url;
use search_elastic\engine;
use search_elastic\esrequest;

/**
 * Smoke test: k-NN index, hybrid query, and RRF (score-ranker-processor) pipeline on the configured domain.
 *
 * Creates a throwaway index and search pipelines, and always removes them afterwards.
 *
 * @package     search_elastic
 * @copyright   2025 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class knn_ready_check extends check {
    /** @var string k-NN engine used for the test index. */
    public const KNN_ENGINE = 'lucene';

    /** @var string k-NN space type used for the test index. */
    public const KNN_SPACE = 'l2';

    /** @var array[] Per-step results of the last run: [label, ok, detail, ms]. */
    private array $steps = [];

    /** @var string Server distribution and version of the last run. */
    private string $serverversion = 'unknown';

    /** @var object|false Custom Guzzle stack to use for requests. Used only for unit testing. */
    private $stack;

    /**
     * Create check.
     * @param object|false $stack Custom HTTP stack for unit testing.
     */
    public function __construct($stack = false) {
        $this->stack = $stack;
    }

    /**
     * Performs check and returns result.
     *
     * @return result
     */
    public function get_result(): result {
        if (empty(get_config('search_elastic', 'hostname'))) {
            return new result(result::NA, get_string('connection:na', 'search_elastic'));
        }

        $engine = new engine();
        $base = $engine->get_url();
        $client = new esrequest($this->stack, 15);
        $dimension = (int) (get_config('search_elastic', 'bedrockdimension') ?: 1024);
        $suffix = 'knnsmoke_' . strtolower(random_string(8));
        $index = $base . '/' . $suffix;
        $pipelines = ['norm' => $suffix . '_norm', 'rrf' => $suffix . '_rrf'];
        $this->steps = [];
        $steps = &$this->steps;
        $failed = null;
        $rrffailed = false;

        $vector = array_map(static fn($i) => round(0.1 + ($i % 7) / 10, 1), range(0, $dimension - 1));
        $json = static fn($data) => json_encode($data, JSON_PRESERVE_ZERO_FRACTION);

        // Each step: label => callable returning a response.
        $run = function (string $label, callable $call, bool $optional = false) use (&$steps, &$failed, &$rrffailed) {
            if ($failed !== null) {
                return;
            }
            $start = microtime(true);
            try {
                $response = $call();
                $code = $response->getStatusCode();
                $ok = $code >= 200 && $code < 300;
                $text = (string) $response->getBody();
            } catch (\Throwable $e) {
                $ok = false;
                $code = 0;
                $text = $e->getMessage();
            }
            $ms = (int) round((microtime(true) - $start) * 1000);
            if ($ok) {
                $steps[] = [$label, true, '', $ms];
                return;
            }
            $decoded = json_decode($text, true);
            $reason = $decoded['error']['reason'] ?? $decoded['message'] ?? $text;
            $steps[] = [$label, false, "HTTP {$code}: " . shorten_text((string) $reason, 200), $ms];
            if ($optional) {
                $rrffailed = true;
            } else {
                $failed = $label;
            }
        };

        try {
            $run('version', function () use ($client, $base) {
                $response = $client->get($base);
                $body = json_decode((string) $response->getBody());
                $dist = $body->version->distribution ?? 'elasticsearch';
                $this->serverversion = $dist . ' ' . ($body->version->number ?? '?');
                return $response;
            });

            $run('knn index', fn() => $client->put($index, $json([
                'settings' => ['index' => ['knn' => true]],
                'mappings' => ['properties' => [
                    'content' => ['type' => 'text'],
                    'embedding' => [
                        'type' => 'knn_vector',
                        'dimension' => $dimension,
                        'method' => ['name' => 'hnsw', 'space_type' => self::KNN_SPACE, 'engine' => self::KNN_ENGINE],
                    ],
                ]],
            ])));

            $run('index document', fn() => $client->put(
                $index . '/_doc/1?refresh=true',
                $json(['content' => 'smoke test document', 'embedding' => $vector])
            ));

            $hybrid = $json([
                'query' => ['hybrid' => ['queries' => [
                    ['match' => ['content' => 'smoke']],
                    ['knn' => ['embedding' => ['vector' => $vector, 'k' => 1]]],
                ]]],
            ]);

            $run('hybrid pipeline (normalization)', fn() => $client->put(
                $base . '/_search/pipeline/' . $pipelines['norm'],
                $json(['phase_results_processors' => [['normalization-processor' => [
                    'normalization' => ['technique' => 'min_max'],
                    'combination' => ['technique' => 'arithmetic_mean', 'parameters' => ['weights' => [0.3, 0.7]]],
                ]]]])
            ));
            $run('hybrid query', fn() => $client->post(
                $index . '/_search?search_pipeline=' . $pipelines['norm'],
                $hybrid
            ));

            // RRF needs OpenSearch 2.19+; failure is reported but not fatal.
            $run('RRF pipeline (score-ranker-processor)', fn() => $client->put(
                $base . '/_search/pipeline/' . $pipelines['rrf'],
                $json(['phase_results_processors' => [['score-ranker-processor' => [
                    'combination' => ['technique' => 'rrf'],
                ]]]])
            ), true);
            if (!$rrffailed) {
                $run('RRF hybrid query', fn() => $client->post(
                    $index . '/_search?search_pipeline=' . $pipelines['rrf'],
                    $hybrid
                ), true);
            }
        } finally {
            foreach ($pipelines as $name) {
                try {
                    $client->delete($base . '/_search/pipeline/' . $name);
                } catch (\Throwable $e) {
                    debugging('Failed to delete smoke test pipeline: ' . $e->getMessage());
                }
            }
            try {
                $client->delete($index);
            } catch (\Throwable $e) {
                debugging('Failed to delete smoke test index: ' . $e->getMessage());
            }
        }

        $lines = [];
        foreach ($steps as [$label, $ok, $detail, $ms]) {
            $lines[] = ($ok ? '✔ ' : '✘ ') . s($label) . " ({$ms} ms)" . ($detail ? ': ' . s($detail) : '');
        }
        $summary = get_string('knncheck:version', 'search_elastic', s($this->serverversion)) . '<br>' . implode('<br>', $lines);

        if ($failed !== null) {
            return new result(result::ERROR, $summary);
        }
        return new result($rrffailed ? result::WARNING : result::OK, $summary);
    }

    /**
     * Per-step results of the last get_result() call.
     *
     * @return array[] Each item is [label, ok, detail, ms].
     */
    public function get_steps(): array {
        return $this->steps;
    }

    /**
     * Server distribution and version seen by the last get_result() call.
     *
     * @return string
     */
    public function get_server_version(): string {
        return $this->serverversion;
    }

    /**
     * Returns the link to action this check if it failed.
     *
     * @return action_link
     */
    public function get_action_link(): action_link {
        return new action_link(
            new moodle_url('/admin/settings.php', ['section' => 'elasticsettings']),
            get_string('adminsettings', 'search_elastic')
        );
    }
}
