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
 * Builds the embedding model selected in the plugin settings.
 *
 * @package     search_elastic
 * @copyright   2026 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class model_factory {
    /**
     * Models with an implementation, by Bedrock model id.
     *
     * @return string[] Model id => embedding_model class name.
     */
    public static function get_supported(): array {
        return [titan_v2::MODEL_ID => titan_v2::class];
    }

    /**
     * Create the model from the bedrockmodelid and bedrockdimension settings.
     *
     * @return embedding_model
     * @throws \moodle_exception If the selected model has no implementation or the dimension is invalid for it.
     */
    public static function from_config(): embedding_model {
        $modelid = get_config('search_elastic', 'bedrockmodelid') ?: titan_v2::MODEL_ID;
        $dimension = (int) (get_config('search_elastic', 'bedrockdimension') ?: 1024);

        $class = self::get_supported()[$modelid] ?? null;
        if ($class === null) {
            throw new \moodle_exception('semanticmodelunsupported', 'search_elastic', '', $modelid);
        }

        try {
            return new $class($dimension);
        } catch (\coding_exception $e) {
            throw new \moodle_exception('semanticdimensionunsupported', 'search_elastic', '', (object) [
                'model' => $modelid,
                'dimension' => $dimension,
            ]);
        }
    }
}
