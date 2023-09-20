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

namespace search_elastic\enrich\text;

use search_elastic\enrich\base\base_enrich;
use search_elastic\esrequest;

/**
 * Extract text from files using Tika.
 *
 * @package     search_elastic
 * @copyright   Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class elastic extends base_enrich {

    /**
     * Array of file mimetypes that enrichment class supports.
     *
     * @var array
     */
    protected $acceptedmime = [
        'application/pdf',
        'text/html',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
        'application/vnd.ms-word.document.macroEnabled.12',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
        'application/vnd.ms-excel.sheet.macroEnabled.12',
        'application/vnd.ms-excel.template.macroEnabled.12',
        'application/vnd.ms-excel.addin.macroEnabled.12',
        'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.openxmlformats-officedocument.presentationml.template',
        'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
        'application/vnd.ms-powerpoint.addin.macroEnabled.12',
        'application/vnd.ms-powerpoint.presentation.macroEnabled.12',
        'application/vnd.ms-powerpoint.slideshow.macroEnabled.12',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
        'application/epub+zip'
    ];

    /**
     * Returns the step name.
     *
     * @return string human readable step name.
     */
    public static function get_enrich_name() {
        return get_string('elastic', 'search_elastic');
    }


    /**
     * Check if the tika server is ready.
     *
     * @return boolean
     */
    private function elastic_server_ready() {
        $returnval = false;

        // Check if we have a valid set of config.
        if (!empty($this->config->elasticapikey) && !empty($this->config->elastichostname)) {
            $url = trim($this->config->elastichostname, "/");
        }

        if (!empty($url)) {
            $response = $this->get_client()->get($url);
            if ($response->getStatusCode() == 200) {
                $returnval = true;
            }
        }

        return $returnval;
    }

    /**
     * Checks if supplied file is can be analyzed by this enrichment class.
     *
     * @param \stored_file $file File to check.
     * @return boolean
     */
    public function can_analyze($file) {
        $cananalyze = parent::can_analyze($file);

        // If we can analyze this type of file
        // check if tika is configured and available.
        if ($cananalyze) {
            $cananalyze = $this->elastic_server_ready();
        }

        return $cananalyze;
    }

    /**
     * Use tika to extract text from file.
     *
     * @param \stored_file $file
     * @param \search_elastic\esrequest $client client
     * @return string
     */
    public function extract_text($file, $client) {
        $extractedtext = '';

        $hostname = trim($this->config->elastichostname, "/");
        $id = $file->get_contenthash();
        $url = $hostname . '/' . $this->config->elasticindex . '/_doc/' . $id . '?pipeline=' . $this->config->elasticpipeline;

        $data = [
            $this->config->elasticfield => base64_encode($file->get_content()),
        ];

        $data = json_encode($data);
        $response = $client->put($url, $data);

        if ($response->getStatusCode() == 200 || $response->getStatusCode() == 201) {
            $url = $hostname . '/' . $this->config->elasticindex . '/_doc/' . $id;
            $response = $client->get($url);
            if ($response->getStatusCode() == 200) {
                $jsoncontent = json_decode($response->getBody());

                if (!empty($jsoncontent->found) && !empty($jsoncontent->_source->attachment->content)) {
                    $extractedtext .= strip_tags($jsoncontent->_source->attachment->content);

                }
            }
        }

        return $extractedtext;
    }

    /**
     * Analyse file and return results.
     *
     * @param \stored_file $file The image file to analyze.
     * @return string $filetext Text of file description labels.
     */
    public function analyze_file($file) {
        return $this->extract_text($file, $this->get_client());
    }

    /**
     * A callback to add fields to the enrich form, specific to enrichment class.
     *
     * @param \moodleform $form
     * @param \MoodleQuickForm $mform
     * @param mixed $customdata
     * @param mixed $config
     */
    public static function form_definition_extra($form, $mform, $customdata, $config) {
        $mform->addElement('text', 'elastichostname',  get_string ('elastichostname', 'search_elastic'));
        $mform->setType('elastichostname', PARAM_URL);
        $mform->addHelpButton('elastichostname', 'elastichostname', 'search_elastic');
        self::set_default('elastichostname', 'https://127.0.0.1', $mform, $customdata, $config);

        $mform->addElement('text', 'elasticapikey',  get_string ('elasticapikey', 'search_elastic'));
        $mform->setType('elasticapikey', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('elasticapikey', 'elasticpipeline', 'search_elastic');
        self::set_default('elasticapikey', '', $mform, $customdata, $config);

        $mform->addElement('text', 'elasticpipeline',  get_string ('elasticpipeline', 'search_elastic'));
        $mform->setType('elasticpipeline', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('elasticpipeline', 'elasticpipeline', 'search_elastic');
        self::set_default('elasticpipeline', 'attachment', $mform, $customdata, $config);

        $mform->addElement('text', 'elasticfield',  get_string ('elasticfield', 'search_elastic'));
        $mform->setType('elasticfield', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('elasticfield', 'elasticpipeline', 'search_elastic');
        self::set_default('elasticfield', 'data', $mform, $customdata, $config);

        $mform->addElement('text', 'elasticindex',  get_string ('elasticindex', 'search_elastic'));
        $mform->setType('elasticindex', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('elasticindex', 'elasticpipeline', 'search_elastic');
        self::set_default('elasticindex', 'my-index', $mform, $customdata, $config);
    }

    /**
     * Get a new client.
     *
     * @return \search_elastic\esrequest
     */
    protected function get_client(): esrequest {
        return new esrequest(false, (object) [
            'apikey' => $this->config->elasticapikey
        ]);
    }
}
