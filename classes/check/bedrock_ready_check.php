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
use search_elastic\guzzle_helper;

/**
 * Smoke test: one SigV4-signed bedrock:InvokeModel call using the configured embedding model.
 *
 * @package     search_elastic
 * @copyright   2025 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bedrock_ready_check extends check {
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
        $config = get_config('search_elastic');
        if (empty($config->signingkeyid) || empty($config->signingsecretkey) || empty($config->bedrockregion)) {
            return new result(result::NA, get_string('bedrockcheck:na', 'search_elastic'));
        }

        $modelid = $config->bedrockmodelid ?: 'amazon.titan-embed-text-v2:0';
        $dimension = (int) ($config->bedrockdimension ?: 1024);
        $region = $config->bedrockregion;
        $host = "bedrock-runtime.{$region}.amazonaws.com";
        $url = "https://{$host}/model/" . rawurlencode($modelid) . '/invoke';

        if (str_starts_with($modelid, 'cohere.')) {
            $body = ['texts' => ['moodle semantic search smoke test'], 'input_type' => 'search_document'];
        } else {
            $body = ['inputText' => 'moodle semantic search smoke test'];
            if ($modelid === 'amazon.titan-embed-text-v2:0') {
                $body['dimensions'] = $dimension;
                $body['normalize'] = true;
            }
        }

        $request = new \GuzzleHttp\Psr7\Request(
            'POST',
            $url,
            ['content-type' => 'application/json', 'accept' => 'application/json'],
            json_encode($body)
        );
        $credentials = new \Aws\Credentials\Credentials($config->signingkeyid, $config->signingsecretkey);
        $request = (new \Aws\Signature\SignatureV4('bedrock', $region))->signRequest($request, $credentials);

        $options = ['timeout' => 15, 'connect_timeout' => 5, 'http_errors' => false];
        if ($this->stack) {
            $options['handler'] = $this->stack;
        }
        $client = guzzle_helper::configure_client_proxy(new \GuzzleHttp\Client($options));

        $start = microtime(true);
        try {
            $response = $client->send($request);
        } catch (\Throwable $e) {
            return new result(result::ERROR, get_string('bedrockcheck:failed', 'search_elastic', [
                'model' => s($modelid),
                'region' => s($region),
                'detail' => s($e->getMessage()),
            ]));
        }
        $ms = (int) round((microtime(true) - $start) * 1000);

        $status = $response->getStatusCode();
        $decoded = json_decode((string) $response->getBody(), true) ?: [];
        if ($status !== 200) {
            // Typical causes: 403 AccessDeniedException (IAM or model access not enabled), 429 throttling.
            $detail = "HTTP {$status}: " . ($decoded['message'] ?? $decoded['Message'] ?? 'no message');
            return new result(result::ERROR, get_string('bedrockcheck:failed', 'search_elastic', [
                'model' => s($modelid),
                'region' => s($region),
                'detail' => s($detail),
            ]));
        }

        $vector = $decoded['embedding'] ?? ($decoded['embeddings'][0] ?? $decoded['embeddings']['float'][0] ?? []);
        $returned = count($vector);
        if ($returned !== $dimension) {
            return new result(result::WARNING, get_string('bedrockcheck:dimension', 'search_elastic', [
                'model' => s($modelid),
                'expected' => $dimension,
                'actual' => $returned,
            ]));
        }

        return new result(result::OK, get_string('bedrockcheck:ok', 'search_elastic', [
            'model' => s($modelid),
            'region' => s($region),
            'dimension' => $returned,
            'ms' => $ms,
        ]));
    }

    /**
     * Returns the link to action this check if it failed.
     *
     * @return action_link
     */
    public function get_action_link(): action_link {
        return new action_link(
            new moodle_url('/admin/settings.php', ['section' => 'search_elastic_semanticsettings']),
            get_string('semanticsearchsettings', 'search_elastic')
        );
    }
}
