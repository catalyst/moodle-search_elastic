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
 * Creates and checks the OpenSearch resources semantic search needs.
 *
 * The steps mirror docs/opensearch-semantic-setup.md: Bedrock connector, remote model, ingest pipeline,
 * semantic index, then an embedding test. Every step checks for its resource before creating it, so running
 * setup repeatedly is safe.
 *
 * @package     search_elastic
 * @copyright   2026 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provisioner {
    /** @var string Step key: ML Commons connector to Bedrock. */
    public const STEP_CONNECTOR = 'connector';

    /** @var string Step key: remote model registered on the connector and deployed. */
    public const STEP_MODEL = 'model';

    /** @var string Step key: ingest pipeline that embeds the content field. */
    public const STEP_PIPELINE = 'pipeline';

    /** @var string Step key: the semantic index. */
    public const STEP_INDEX = 'index';

    /** @var string Step key: a test embedding through the deployed model. */
    public const STEP_EMBEDDING = 'embedding';

    /** @var string State: resource exists and matches the settings. */
    public const STATE_OK = 'ok';

    /** @var string State: resource does not exist. */
    public const STATE_MISSING = 'missing';

    /** @var string State: resource exists but was built with a different model or dimension. */
    public const STATE_MISMATCH = 'mismatch';

    /** @var string State: creating or checking the resource failed. */
    public const STATE_FAILED = 'failed';

    /** @var string State: not run because an earlier step is not ok. */
    public const STATE_SKIPPED = 'skipped';

    /** @var int Seconds to wait for the model to report as deployed. */
    private const DEPLOY_WAIT = 30;

    /** @var string|null */
    private ?string $connectorid = null;

    /** @var string|null */
    private ?string $modelid = null;

    /** @var client */
    private client $http;

    /**
     * Constructor.
     *
     * @param esrequest $client HTTP client for the OpenSearch domain.
     * @param string $baseurl Domain URL, without trailing slash.
     * @param string $index Keyword index name.
     * @param embedding_model $model
     * @param string $region AWS region Bedrock is called in.
     * @param string $rolearn Role OpenSearch assumes to invoke Bedrock.
     */
    public function __construct(
        esrequest $client,
        string $baseurl,
        private string $index,
        private embedding_model $model,
        private string $region,
        private string $rolearn
    ) {
        $this->http = new client($client, $baseurl);
    }

    /**
     * Create a provisioner from the plugin settings.
     *
     * @return self
     * @throws \moodle_exception If the engine or Bedrock settings are incomplete or the model is unsupported.
     */
    public static function from_config(): self {
        $engine = new \search_elastic\engine();
        $baseurl = $engine->get_url();
        $region = (string) get_config('search_elastic', 'bedrockregion');
        $rolearn = (string) get_config('search_elastic', 'bedrockrolearn');
        if (!$baseurl || $region === '' || $rolearn === '') {
            throw new \moodle_exception('semanticsetupnotconfigured', 'search_elastic');
        }

        $index = get_config('search_elastic', 'index') ?: 'moodle';
        return new self(new esrequest(), $baseurl, $index, model_factory::from_config(), $region, $rolearn);
    }

    /**
     * The embedding model this provisioner sets up.
     *
     * @return embedding_model
     */
    public function get_model(): embedding_model {
        return $this->model;
    }

    /**
     * Report the state of every step without creating anything.
     *
     * @return array[] Step key => ['state' => STATE_*, 'detail' => string].
     */
    public function get_status(): array {
        return $this->run(false);
    }

    /**
     * Create everything that is missing, stopping at the first step that cannot be completed.
     *
     * @return array[] Step key => ['state' => STATE_*, 'detail' => string].
     */
    public function setup(): array {
        return $this->run(true);
    }

    /**
     * Delete the semantic index and create everything again.
     *
     * Use after the model or dimension changed. The index is refilled by the semantic sync task.
     *
     * @return array[] Step key => ['state' => STATE_*, 'detail' => string].
     */
    public function rebuild(): array {
        $this->request('DELETE', '/' . schema::get_index_name($this->index));
        return $this->run(true);
    }

    /**
     * The id of the deployed model, once the model step is ok.
     *
     * @return string|null
     */
    public function get_model_id(): ?string {
        return $this->modelid;
    }

    /**
     * Run all steps in order.
     *
     * @param bool $create Create missing resources, rather than only reporting them.
     * @return array[]
     */
    private function run(bool $create): array {
        $steps = [
            self::STEP_CONNECTOR => fn() => $this->ensure_connector($create),
            self::STEP_MODEL => fn() => $this->ensure_model($create),
            self::STEP_PIPELINE => fn() => $this->ensure_pipeline($create),
            self::STEP_INDEX => fn() => $this->ensure_index($create),
            self::STEP_EMBEDDING => fn() => $this->test_embedding(),
        ];

        $results = [];
        $blocked = false;
        foreach ($steps as $key => $step) {
            if ($blocked) {
                $results[$key] = $this->result(self::STATE_SKIPPED);
                continue;
            }
            try {
                $results[$key] = $step();
            } catch (\Throwable $e) {
                $results[$key] = $this->result(self::STATE_FAILED, $e->getMessage());
            }
            $blocked = $results[$key]['state'] !== self::STATE_OK;
        }

        return $results;
    }

    /**
     * Ensure the Bedrock connector exists.
     *
     * @param bool $create
     * @return array
     */
    private function ensure_connector(bool $create): array {
        $name = schema::get_model_name($this->index, $this->model);
        $this->connectorid = $this->http->find_id_by_name('/_plugins/_ml/connectors/_search', $name);
        if ($this->connectorid !== null) {
            return $this->result(self::STATE_OK, $this->connectorid);
        }
        if (!$create) {
            return $this->result(self::STATE_MISSING);
        }

        $definition = $this->model->get_connector_definition($name, $this->region, $this->rolearn);
        [$code, $body] = $this->request('POST', '/_plugins/_ml/connectors/_create', $definition);
        if ($code >= 300 || empty($body['connector_id'])) {
            return $this->result(self::STATE_FAILED, $this->describe_error($code, $body));
        }
        $this->connectorid = $body['connector_id'];

        return $this->result(self::STATE_OK, $this->connectorid);
    }

    /**
     * Ensure the remote model exists and is deployed.
     *
     * @param bool $create
     * @return array
     */
    private function ensure_model(bool $create): array {
        $name = schema::get_model_name($this->index, $this->model);
        $this->modelid = $this->http->find_id_by_name('/_plugins/_ml/models/_search', $name);

        if ($this->modelid === null) {
            if (!$create) {
                return $this->result(self::STATE_MISSING);
            }
            [$code, $body] = $this->request('POST', '/_plugins/_ml/models/_register', [
                'name' => $name,
                'function_name' => 'remote',
                'description' => 'Moodle search_elastic embeddings',
                'connector_id' => $this->connectorid,
            ]);
            if ($code >= 300 || empty($body['model_id'])) {
                return $this->result(self::STATE_FAILED, $this->describe_error($code, $body));
            }
            $this->modelid = $body['model_id'];
        }

        if ($this->get_model_state() === 'DEPLOYED') {
            return $this->result(self::STATE_OK, $this->modelid);
        }
        if (!$create) {
            return $this->result(self::STATE_MISSING, get_string('semanticsetup_notdeployed', 'search_elastic'));
        }

        [$code, $body] = $this->request('POST', "/_plugins/_ml/models/{$this->modelid}/_deploy");
        if ($code >= 300) {
            return $this->result(self::STATE_FAILED, $this->describe_error($code, $body));
        }
        if (!$this->wait_for_deployment()) {
            return $this->result(self::STATE_FAILED, get_string('semanticsetup_deploytimeout', 'search_elastic'));
        }

        return $this->result(self::STATE_OK, $this->modelid);
    }

    /**
     * Ensure the ingest pipeline exists and points at the current model.
     *
     * @param bool $create
     * @return array
     */
    private function ensure_pipeline(bool $create): array {
        $name = schema::get_pipeline_name($this->index);
        [$code, $body] = $this->request('GET', '/_ingest/pipeline/' . $name);
        $current = $body[$name]['processors'][0]['text_embedding']['model_id'] ?? null;

        if ($code === 200 && $current === $this->modelid) {
            return $this->result(self::STATE_OK, $name);
        }
        if (!$create) {
            return $this->result($code === 200 ? self::STATE_MISMATCH : self::STATE_MISSING, $name);
        }

        [$code, $body] = $this->request('PUT', '/_ingest/pipeline/' . $name, [
            'description' => 'Moodle search_elastic: embed ' . schema::SOURCE_FIELD,
            'processors' => [[
                'text_embedding' => [
                    'model_id' => $this->modelid,
                    'field_map' => [schema::SOURCE_FIELD => schema::FIELD],
                ],
            ]],
        ]);
        if ($code >= 300) {
            return $this->result(self::STATE_FAILED, $this->describe_error($code, $body));
        }

        return $this->result(self::STATE_OK, $name);
    }

    /**
     * Ensure the semantic index exists. An index built for another model or dimension is never changed.
     *
     * @param bool $create
     * @return array
     */
    private function ensure_index(bool $create): array {
        $name = schema::get_index_name($this->index);
        [$code, $body] = $this->request('GET', '/' . $name);

        if ($code === 200) {
            $meta = $body[$name]['mappings']['_meta'] ?? [];
            $matches = ($meta['embedding_model'] ?? null) === $this->model->get_id()
                && (int) ($meta['embedding_dimension'] ?? 0) === $this->model->get_dimension();
            if ($matches) {
                return $this->result(self::STATE_OK, $name);
            }
            return $this->result(self::STATE_MISMATCH, get_string('semanticsetup_indexmismatch', 'search_elastic', (object) [
                'actual' => ($meta['embedding_model'] ?? '?') . ' / ' . ($meta['embedding_dimension'] ?? '?'),
                'expected' => $this->model->get_id() . ' / ' . $this->model->get_dimension(),
            ]));
        }
        if (!$create) {
            return $this->result(self::STATE_MISSING, $name);
        }

        [$code, $body] = $this->request('PUT', '/' . $name, schema::get_index_definition($this->index, $this->model));
        if ($code >= 300) {
            return $this->result(self::STATE_FAILED, $this->describe_error($code, $body));
        }

        return $this->result(self::STATE_OK, $name);
    }

    /**
     * Embed a test sentence through the deployed model and check the vector length.
     *
     * @return array
     */
    private function test_embedding(): array {
        [$code, $body] = $this->request('POST', "/_plugins/_ml/_predict/text_embedding/{$this->modelid}", [
            'text_docs' => ['moodle semantic search test'],
            'return_number' => true,
            'target_response' => ['sentence_embedding'],
        ]);
        if ($code >= 300) {
            return $this->result(self::STATE_FAILED, $this->describe_error($code, $body));
        }

        $shape = $body['inference_results'][0]['output'][0]['dataAsMap']['shape'][0]
            ?? $body['inference_results'][0]['output'][0]['shape'][0]
            ?? null;
        if ((int) $shape !== $this->model->get_dimension()) {
            return $this->result(self::STATE_FAILED, get_string('semanticsetup_badshape', 'search_elastic', (object) [
                'actual' => $shape ?? '?',
                'expected' => $this->model->get_dimension(),
            ]));
        }

        return $this->result(self::STATE_OK, (string) $shape);
    }

    /**
     * Current state of the model, e.g. DEPLOYED.
     *
     * @return string|null
     */
    private function get_model_state(): ?string {
        [$code, $body] = $this->request('GET', "/_plugins/_ml/models/{$this->modelid}");
        return $code === 200 ? ($body['model_state'] ?? null) : null;
    }

    /**
     * Wait for the model to finish deploying.
     *
     * @return bool
     */
    private function wait_for_deployment(): bool {
        $deadline = time() + self::DEPLOY_WAIT;
        do {
            if ($this->get_model_state() === 'DEPLOYED') {
                return true;
            }
            sleep(1);
        } while (time() < $deadline);

        return false;
    }

    /**
     * Send a request to the domain.
     *
     * @param string $method GET, POST, PUT or DELETE.
     * @param string $path Path starting with a slash.
     * @param array|null $body Request body, sent as JSON.
     * @return array [HTTP status code, decoded JSON body or an empty array].
     */
    private function request(string $method, string $path, ?array $body = null): array {
        return $this->http->request($method, $path, $body);
    }

    /**
     * Build a readable message from an error response.
     *
     * @param int $code
     * @param array $body
     * @return string
     */
    private function describe_error(int $code, array $body): string {
        $reason = $body['error']['reason'] ?? $body['error'] ?? $body['message'] ?? '';
        return "HTTP {$code}: " . (is_string($reason) ? $reason : json_encode($reason));
    }

    /**
     * Build a step result.
     *
     * @param string $state
     * @param string $detail
     * @return array
     */
    private function result(string $state, string $detail = ''): array {
        return ['state' => $state, 'detail' => $detail];
    }
}
