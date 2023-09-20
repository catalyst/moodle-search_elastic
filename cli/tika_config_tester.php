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
 * CLI config tester
 *
 * @package    search
 * @copyright  2023 David Castro <david.castro@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('CLI_SCRIPT', true);

require(__DIR__.'/../../../../config.php');
require_once($CFG->libdir.'/clilib.php');      // Cli only functions.

list($options, $unrecognized) = cli_get_params([
    'help' => false,
    'testfileid' => '',
], [
    'h' => 'help',
    't' => 'testfileid',
]);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = "
Run Tika diagnostics.

Options:
-h, --help              Print out this help
-t, --testfileid        (Optional) PDF or accepted file id to send to tika for analysis

Examples:
\$ sudo -u www-data /usr/bin/php search/engine/elastic/cli/tika_config_tester.php -t=<file id>
";

    echo $help;
    die;
}

/**
 * Inspired by \search_elastic\enrich\text\tika::tika_server_ready.
 * Outputs cli messages on error.
 */
function tika_server_ready() {
    $tikahostname = get_config('search_elastic', 'tikahostname');
    $tikaport = get_config('search_elastic', 'tikaport');

    $returnval = false;
    $client = new \search_elastic\esrequest();
    $url = '';
    // Check if we have a valid set of config.
    if (!empty($tikahostname) && !empty($tikaport)) {
        $port = $tikaport;
        $hostname = rtrim($tikahostname, "/");
        $url = $hostname . ':' . $port;
    } else {
        cli_writeln('tikahostname or tikaport are not set in elasticsearch config');
    }

    // Check we can reach Tika server.
    if ($url !== '') {
        $response = $client->get($url);
        $responsecode = $response->getStatusCode();

        if ($responsecode == 200) {
            $returnval = true;
        } else {
            $error = 'Undetermined';
            if (method_exists($response, 'getBody')) {
                // This might be transformed into a guzzleexception.
                // We need to check if it is still a response.
                $error = $response->getBody();
            }
            cli_error("Making a GET request to $url resulted in error:\nHTTP Code: $responsecode\nResponse: $error");
        }
    }

    return $returnval;
}

//$canusetika = tika_server_ready();
//if (!$canusetika) {
//    cli_error("Tika cannot be used. Please verify plugin configuration.");
//}
cli_writeln('Connection to tika was successful!');

//$fileid = $options['testfileid'];
//if (empty($fileid)) {
//    cli_writeln('No file id specified, exiting.');
//    exit(0);
//}

$tika = new \search_elastic\enrich\text\elastic(get_config('search_elastic'));
$fs = get_file_storage();

$record = new \stdClass();
$record->contextid = context_system::instance()->id;
$record->component = 'phpunit';
$record->filearea = 'test';
$record->itemid = 0;
$record->filepath = '/';
$record->filename = 'tika-test-file.txt';

$fs = get_file_storage();
$file = $fs->create_file_from_string($record, 'Tika test.');
$text = $tika->analyze_file($file);
$file->delete();

cli_writeln('Text found in file ' . $file->get_filename() . ': ' . $text);
