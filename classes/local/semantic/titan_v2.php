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
 * Amazon Titan Text Embeddings v2.
 *
 * @package     search_elastic
 * @copyright   2026 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class titan_v2 implements embedding_model {
    /** @var string Bedrock model id. */
    public const MODEL_ID = 'amazon.titan-embed-text-v2:0';

    /** @var int[] Output dimensions Titan v2 supports. */
    public const DIMENSIONS = [256, 512, 1024];

    /** @var int */
    private int $dimension;

    /**
     * Constructor.
     *
     * @param int $dimension One of {@see self::DIMENSIONS}.
     */
    public function __construct(int $dimension = 1024) {
        if (!in_array($dimension, self::DIMENSIONS, true)) {
            throw new \coding_exception('Unsupported Titan v2 dimension: ' . $dimension);
        }
        $this->dimension = $dimension;
    }

    #[\Override]
    public function get_id(): string {
        return self::MODEL_ID;
    }

    #[\Override]
    public function get_slug(): string {
        return 'titan-embed-text-v2';
    }

    #[\Override]
    public function get_dimension(): int {
        return $this->dimension;
    }

    #[\Override]
    public function get_connector_definition(string $name, string $region, string $rolearn): array {
        return [
            'name' => $name,
            'description' => 'Moodle search_elastic: ' . self::MODEL_ID . ' via Amazon Bedrock',
            'version' => 1,
            'protocol' => 'aws_sigv4',
            'credential' => ['roleArn' => $rolearn],
            'parameters' => [
                'region' => $region,
                'service_name' => 'bedrock',
                'model' => self::MODEL_ID,
                'dimensions' => $this->dimension,
                'normalize' => true,
                'embeddingTypes' => ['float'],
            ],
            'actions' => [[
                'action_type' => 'predict',
                'method' => 'POST',
                'url' => 'https://bedrock-runtime.${parameters.region}.amazonaws.com/model/${parameters.model}/invoke',
                'headers' => ['content-type' => 'application/json', 'x-amz-content-sha256' => 'required'],
                'request_body' => '{ "inputText": "${parameters.inputText}", "dimensions": ${parameters.dimensions}, '
                    . '"normalize": ${parameters.normalize}, "embeddingTypes": ${parameters.embeddingTypes} }',
                'pre_process_function' => 'connector.pre_process.bedrock.embedding',
                'post_process_function' => 'connector.post_process.bedrock_v2.embedding.float',
            ]],
        ];
    }
}
