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
 * Sends JSON requests to the OpenSearch domain and decodes the answers.
 *
 * @package     search_elastic
 * @copyright   2026 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client {
    /**
     * Constructor.
     *
     * @param esrequest $client HTTP client for the OpenSearch domain.
     * @param string $baseurl Domain URL, without trailing slash.
     */
    public function __construct(private esrequest $client, private string $baseurl) {
    }

    /**
     * Send a request.
     *
     * @param string $method GET, POST, PUT or DELETE.
     * @param string $path Path starting with a slash.
     * @param array|string|null $body Array sent as JSON, or a ready made string such as a bulk payload.
     * @return array [HTTP status code, decoded JSON body or an empty array].
     */
    public function request(string $method, string $path, array|string|null $body = null): array {
        $url = $this->baseurl . $path;
        $payload = is_array($body) ? json_encode($body) : (string) $body;
        $response = match ($method) {
            'GET' => $this->client->get($url),
            'PUT' => $this->client->put($url, $payload),
            'POST' => $this->client->post($url, $payload),
            'DELETE' => $this->client->delete($url),
        };

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true) ?? []];
    }

    /**
     * Find the id of an ML Commons resource by its exact name.
     *
     * @param string $path Search endpoint, e.g. /_plugins/_ml/models/_search.
     * @param string $name
     * @return string|null
     */
    public function find_id_by_name(string $path, string $name): ?string {
        [$code, $body] = $this->request('POST', $path, ['query' => ['match_phrase' => ['name' => $name]], 'size' => 10]);
        if ($code !== 200) {
            return null;
        }
        foreach ($body['hits']['hits'] ?? [] as $hit) {
            if (($hit['_source']['name'] ?? null) === $name) {
                return $hit['_id'];
            }
        }
        return null;
    }
}
