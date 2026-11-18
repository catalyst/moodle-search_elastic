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
 * Tests for the semantic search provisioner and model selection.
 *
 * @package     search_elastic
 * @copyright   2026 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \search_elastic\local\semantic\provisioner
 * @covers      \search_elastic\local\semantic\model_factory
 * @covers      \search_elastic\local\semantic\schema
 */
final class provisioner_test extends \advanced_testcase {
    /** @var MockHandler */
    private MockHandler $mock;

    /**
     * Build a provisioner that answers from the queued responses.
     *
     * @param array $responses
     * @param int $dimension
     * @return provisioner
     */
    private function get_provisioner(array $responses, int $dimension = 1024): provisioner {
        $this->mock = new MockHandler($responses);
        return new provisioner(
            new esrequest(HandlerStack::create($this->mock)),
            'https://domain.example',
            'moodle',
            new titan_v2($dimension),
            'ap-southeast-2',
            'arn:aws:iam::123456789012:role/invoke'
        );
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
     * Responses for a search that finds the named resource.
     *
     * @param string $id
     * @return Response
     */
    private function found(string $id): Response {
        return $this->json(['hits' => ['hits' => [['_id' => $id, '_source' => [
            'name' => 'moodle-moodle-titan-embed-text-v2-1024',
        ]]]]]);
    }

    /**
     * An embedding response of the given length.
     *
     * @param int $dimension
     * @return Response
     */
    private function embedding(int $dimension): Response {
        return $this->json(['inference_results' => [['output' => [['shape' => [$dimension]]]]]]);
    }

    /**
     * Setup on an empty domain creates every resource, in order.
     */
    public function test_setup_creates_everything(): void {
        $provisioner = $this->get_provisioner([
            $this->json(['hits' => ['hits' => []]]),
            $this->json(['connector_id' => 'c1']),
            $this->json(['hits' => ['hits' => []]]),
            $this->json(['model_id' => 'm1']),
            $this->json(['model_state' => 'REGISTERED']),
            $this->json(['status' => 'CREATED']),
            $this->json(['model_state' => 'DEPLOYED']),
            $this->json([], 404),
            $this->json(['acknowledged' => true]),
            $this->json([], 404),
            $this->json(['acknowledged' => true]),
            $this->embedding(1024),
        ]);

        $results = $provisioner->setup();

        $this->assertSame(
            array_fill_keys(array_keys($results), provisioner::STATE_OK),
            array_map(fn($step) => $step['state'], $results)
        );
        $this->assertSame('m1', $provisioner->get_model_id());
        $this->assertCount(0, $this->mock);
    }

    /**
     * Status never creates anything and skips the steps that depend on a missing one.
     */
    public function test_status_does_not_create(): void {
        $provisioner = $this->get_provisioner([$this->json(['hits' => ['hits' => []]])]);

        $results = $provisioner->get_status();

        $this->assertSame(provisioner::STATE_MISSING, $results[provisioner::STEP_CONNECTOR]['state']);
        foreach ([provisioner::STEP_MODEL, provisioner::STEP_PIPELINE, provisioner::STEP_INDEX, provisioner::STEP_EMBEDDING] as $step) {
            $this->assertSame(provisioner::STATE_SKIPPED, $results[$step]['state']);
        }
    }

    /**
     * An index built with another dimension is reported, never changed.
     */
    public function test_index_with_other_dimension_needs_rebuild(): void {
        $provisioner = $this->get_provisioner([
            $this->found('c1'),
            $this->found('m1'),
            $this->json(['model_state' => 'DEPLOYED']),
            $this->json(['neural-pipeline-moodle' => ['processors' => [['text_embedding' => ['model_id' => 'm1']]]]]),
            $this->json(['semantic_moodle' => ['mappings' => ['_meta' => [
                'embedding_model' => titan_v2::MODEL_ID,
                'embedding_dimension' => 512,
            ]]]]),
        ]);

        $results = $provisioner->setup();

        $this->assertSame(provisioner::STATE_OK, $results[provisioner::STEP_PIPELINE]['state']);
        $this->assertSame(provisioner::STATE_MISMATCH, $results[provisioner::STEP_INDEX]['state']);
        $this->assertSame(provisioner::STATE_SKIPPED, $results[provisioner::STEP_EMBEDDING]['state']);
    }

    /**
     * A failed create is reported with the reason from OpenSearch and stops the run.
     */
    public function test_failed_connector_reports_reason(): void {
        $provisioner = $this->get_provisioner([
            $this->json(['hits' => ['hits' => []]]),
            $this->json(['error' => ['reason' => 'no permission to pass role']], 403),
        ]);

        $results = $provisioner->setup();

        $this->assertSame(provisioner::STATE_FAILED, $results[provisioner::STEP_CONNECTOR]['state']);
        $this->assertStringContainsString('no permission to pass role', $results[provisioner::STEP_CONNECTOR]['detail']);
        $this->assertSame(provisioner::STATE_SKIPPED, $results[provisioner::STEP_MODEL]['state']);
    }

    /**
     * A vector of the wrong length fails the embedding test.
     */
    public function test_embedding_with_wrong_dimension_fails(): void {
        $provisioner = $this->get_provisioner([
            $this->found('c1'),
            $this->found('m1'),
            $this->json(['model_state' => 'DEPLOYED']),
            $this->json(['neural-pipeline-moodle' => ['processors' => [['text_embedding' => ['model_id' => 'm1']]]]]),
            $this->json(['semantic_moodle' => ['mappings' => ['_meta' => [
                'embedding_model' => titan_v2::MODEL_ID,
                'embedding_dimension' => 1024,
            ]]]]),
            $this->embedding(256),
        ]);

        $results = $provisioner->get_status();

        $this->assertSame(provisioner::STATE_FAILED, $results[provisioner::STEP_EMBEDDING]['state']);
    }

    /**
     * Only models with an implementation can be used.
     */
    public function test_factory_rejects_unsupported_model(): void {
        $this->resetAfterTest();

        set_config('bedrockmodelid', 'cohere.embed-english-v3', 'search_elastic');
        $this->expectException(\moodle_exception::class);
        model_factory::from_config();
    }

    /**
     * The settings pick the Titan dimension.
     */
    public function test_factory_uses_configured_dimension(): void {
        $this->resetAfterTest();

        set_config('bedrockmodelid', titan_v2::MODEL_ID, 'search_elastic');
        set_config('bedrockdimension', 512, 'search_elastic');

        $model = model_factory::from_config();

        $this->assertInstanceOf(titan_v2::class, $model);
        $this->assertSame(512, $model->get_dimension());
    }

    /**
     * The index definition carries the model so a changed setting can be detected.
     */
    public function test_index_definition_records_model(): void {
        $definition = schema::get_index_definition('moodle', new titan_v2(256));

        $this->assertSame(256, $definition['mappings']['_meta']['embedding_dimension']);
        $this->assertSame(256, $definition['mappings']['properties'][schema::FIELD]['dimension']);
        $this->assertSame('neural-pipeline-moodle', $definition['settings']['default_pipeline']);
        $this->assertArrayNotHasKey('filetext', $definition['mappings']['properties']);
    }
}
