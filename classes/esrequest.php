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

/**
 * Provides request signing
 *
 * @package     search_elastic
 * @copyright   Matt Porritt <mattp@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace search_elastic;

defined('MOODLE_INTERNAL') || die();

require($CFG->dirroot .'/search/engine/elastic/extlib/aws/aws-autoloader.php');

class esrequest {
    /**
     * @var bool True if we should sign requests, false if not.
     */
    private $signing = false;

    /**
     * @var elasticsearch plugin config.
     */
    private $config = null;

    /**
     * Initialises the search engine configuration.
     *
     * Search engine availability should be checked separately.
     *
     * @return void
     */
    public function __construct() {
        $this->config = get_config('search_elastic');
        $this->signing = (bool)$this->config->signing;
    }

    /**
     * Signs a request with the supplied credentials.
     * This is used for access control to the Elasticsearch endpoint.
     *
     * @param \GuzzleHttpv6\Psr7\Request $request
     * @throws \moodle_exception
     * @return \GuzzleHttpv6\Psr7\Request
     */
    private function signrequest($request) {
        // Check we are all configured for request signing.
        if (empty($this->config->keyid) ||
                empty($this->config->secretkey) ||
                empty($this->config->region)) {
            throw new \moodle_exception('noconfig', 'search_elastic', '');
        }

        // Pull credentials from the default provider chain.
        $credentials = new \Aws\Credentials\Credentials(
                $this->config->keyid,
                $this->config->secretkey
                );
        // Create a signer with the service's signing name and region.
        $signer = new \Aws\Signature\SignatureV4('es', $this->config->region);

        // Sign your request.
        $signedrequest = $signer->signRequest($request, $credentials);

        return $signedrequest;
    }

    /**
     * Process GET requests to Elasticsearch.
     *
     * @param string $url
     * @return \GuzzleHttpv6\Psr7\Response
     */
    public function get($url) {
        $psr7request = new \GuzzleHttpv6\Psr7\Request('GET', $url);
        if ($this->signing) {
            $psr7request = $this->signrequest($psr7request);
        }

        $client = new \GuzzleHttpv6\Client();

        // Requests that receive a 4xx or 5xx response will throw a
        // Guzzle\Http\Exception\BadResponseException. We want to
        // handle this in a sane way and provide the caller with
        // a useful response. So we catch the error and return the
        // resposne.
        try {
            $response = $client->send($psr7request);
        } catch (\GuzzleHttpv6\Exception\BadResponseException $e) {
            $response = $e->getResponse();
        }

        return $response;

    }

    /**
     * Process PUT requests to Elasticsearch.
     *
     * @param string $url
     * @return \GuzzleHttpv6\Psr7\Response
     */
    public function put($url) {
        $psr7request = new \GuzzleHttpv6\Psr7\Request('PUT', $url);
        if ($this->signing) {
            $psr7request = $this->signrequest($psr7request);
        }

        $client = new \GuzzleHttpv6\Client();

        // Requests that receive a 4xx or 5xx response will throw a
        // Guzzle\Http\Exception\BadResponseException. We want to
        // handle this in a sane way and provide the caller with
        // a useful response. So we catch the error and return the
        // resposne.
        try {
            $response = $client->send($psr7request);
        } catch (\GuzzleHttpv6\Exception\BadResponseException $e) {
            $response = $e->getResponse();
        }

        return $response;

    }

    /**
     *
     * @param unknown $url
     * @param unknown $params
     * @return \Psr\Http\Message\ResponseInterface|NULL
     */
    public function post($url, $params) {
        $headers = ['content-type' => 'application/x-www-form-urlencoded'];
        $psr7request = new \GuzzleHttpv6\Psr7\Request('POST', $url, $headers, $params);
        if ($this->signing) {
            $psr7request = $this->signrequest($psr7request);
        }

        $client = new \GuzzleHttpv6\Client();

        // Requests that receive a 4xx or 5xx response will throw a
        // Guzzle\Http\Exception\BadResponseException. We want to
        // handle this in a sane way and provide the caller with
        // a useful response. So we catch the error and return the
        // resposne.
        try {
            $response = $client->send($psr7request);
        } catch (\GuzzleHttpv6\Exception\BadResponseException $e) {
            $response = $e->getResponse();
        }

        return $response;

    }

    /**
     *
     * @param unknown $url
     * @return \Psr\Http\Message\ResponseInterface|NULL
     */
    public function delete($url) {
        $psr7request = new \GuzzleHttpv6\Psr7\Request('DELETE', $url);
        if ($this->signing) {
            $psr7request = $this->signrequest($psr7request);
        }

        $client = new \GuzzleHttpv6\Client();

        // Requests that receive a 4xx or 5xx response will throw a
        // Guzzle\Http\Exception\BadResponseException. We want to
        // handle this in a sane way and provide the caller with
        // a useful response. So we catch the error and return the
        // resposne.
        try {
            $response = $client->send($psr7request);
        } catch (\GuzzleHttpv6\Exception\BadResponseException $e) {
            $response = $e->getResponse();
        }

        return $response;

    }
}
