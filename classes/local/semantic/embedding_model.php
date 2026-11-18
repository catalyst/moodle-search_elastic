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
 * An embedding model that OpenSearch calls through an ML Commons connector.
 *
 * Adding a model means implementing this interface and registering it in {@see model_factory}.
 *
 * @package     search_elastic
 * @copyright   2026 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface embedding_model {
    /**
     * The Bedrock model id, as stored in the bedrockmodelid setting.
     *
     * @return string
     */
    public function get_id(): string;

    /**
     * A short name safe to use in OpenSearch resource names.
     *
     * @return string
     */
    public function get_slug(): string;

    /**
     * Length of the vectors the model returns with the current configuration.
     *
     * @return int
     */
    public function get_dimension(): int;

    /**
     * The ML Commons connector request body for this model.
     *
     * @param string $name Connector name.
     * @param string $region AWS region Bedrock is called in.
     * @param string $rolearn Role OpenSearch assumes to invoke the model.
     * @return array
     */
    public function get_connector_definition(string $name, string $region, string $rolearn): array;
}
