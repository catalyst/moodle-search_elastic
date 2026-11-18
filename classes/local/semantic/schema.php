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

use search_elastic\document;

/**
 * Names and definition of the semantic search index.
 *
 * @package     search_elastic
 * @copyright   2026 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schema {
    /** @var string Vector field the ingest pipeline fills from the content field. */
    public const FIELD = 'content_v';

    /** @var string Text field the vectors are generated from. */
    public const SOURCE_FIELD = 'content';

    /** @var string Prefix that separates the semantic index from the keyword index. */
    public const INDEX_PREFIX = 'semantic_';

    /**
     * Name of the semantic index for a keyword index.
     *
     * @param string $index The keyword index name.
     * @return string
     */
    public static function get_index_name(string $index): string {
        return self::INDEX_PREFIX . $index;
    }

    /**
     * Name of the ingest pipeline that generates the vectors for an index.
     *
     * @param string $index The keyword index name.
     * @return string
     */
    public static function get_pipeline_name(string $index): string {
        return 'neural-pipeline-' . $index;
    }

    /**
     * Name shared by the connector and the remote model.
     *
     * @param string $index The keyword index name.
     * @param embedding_model $model
     * @return string
     */
    public static function get_model_name(string $index, embedding_model $model): string {
        return "moodle-{$index}-{$model->get_slug()}-{$model->get_dimension()}";
    }

    /**
     * Fields stored in the semantic index: the keyword index fields, without file text, plus the vector.
     *
     * @param embedding_model $model
     * @return array Field name => mapping.
     */
    public static function get_fields(embedding_model $model): array {
        $fields = array_merge(document::get_required_fields_definition(), document::get_optional_fields_definition());
        unset($fields['filetext']);

        $fields[self::FIELD] = [
            'type' => 'knn_vector',
            'dimension' => $model->get_dimension(),
            'method' => ['name' => 'hnsw', 'engine' => 'lucene', 'space_type' => 'cosinesimil'],
        ];

        return $fields;
    }

    /**
     * The request body that creates the semantic index.
     *
     * @param string $index The keyword index name.
     * @param embedding_model $model
     * @return array
     */
    public static function get_index_definition(string $index, embedding_model $model): array {
        return [
            'settings' => [
                'index.knn' => true,
                'default_pipeline' => self::get_pipeline_name($index),
                'number_of_shards' => 1,
                'number_of_replicas' => 1,
            ],
            'mappings' => [
                '_meta' => [
                    'embedding_model' => $model->get_id(),
                    'embedding_dimension' => $model->get_dimension(),
                ],
                'properties' => self::get_fields($model),
            ],
        ];
    }
}
