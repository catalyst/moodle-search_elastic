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
 * Runs the k-NN / hybrid search smoke test and shows per-step results.
 *
 * @package    search_elastic
 * @copyright  2026 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use core\check\result;
use search_elastic\check\knn_ready_check;

admin_externalpage_setup('search_elastic_knn_test');

$PAGE->set_url(new moodle_url('/search/engine/elastic/knn_test.php'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('knnconnectiontest', 'search_elastic'));
echo html_writer::tag('p', get_string('knntest_desc', 'search_elastic'));

$check = new knn_ready_check();
$start = microtime(true);
$result = $check->get_result();
$total = (int) round((microtime(true) - $start) * 1000);

if ($result->get_status() === result::NA) {
    echo $OUTPUT->notification($result->get_summary(), 'info');
} else {
    echo html_writer::tag('p', get_string('knncheck:version', 'search_elastic', s($check->get_server_version())));

    $table = new html_table();
    $table->head = [
        get_string('knntest_step', 'search_elastic'),
        get_string('knntest_result', 'search_elastic'),
        get_string('knntest_time', 'search_elastic'),
        get_string('knntest_detail', 'search_elastic'),
    ];
    foreach ($check->get_steps() as [$label, $ok, $detail, $ms]) {
        $table->data[] = [
            s($label),
            $ok ? $OUTPUT->pix_icon('i/valid', get_string('success')) : $OUTPUT->pix_icon('i/invalid', get_string('error')),
            $ms . ' ms',
            s($detail),
        ];
    }
    echo html_writer::table($table);
    echo html_writer::tag('p', get_string('knntest_total', 'search_elastic', $total));

    $type = match ($result->get_status()) {
        result::OK => 'success',
        result::WARNING => 'warning',
        default => 'error',
    };
    $message = $result->get_status() === result::OK ? get_string('knntest_ok', 'search_elastic') : get_string('knntest_notok', 'search_elastic');
    echo $OUTPUT->notification($message, $type);
}

echo $OUTPUT->footer();
