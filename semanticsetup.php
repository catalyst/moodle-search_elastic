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
 * Shows the state of the semantic search OpenSearch resources and creates the missing ones.
 *
 * @package    search_elastic
 * @copyright  2026 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use search_elastic\local\semantic\provisioner;
use search_elastic\local\semantic\schema;

admin_externalpage_setup('search_elastic_semanticsetup');

$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$url = new moodle_url('/search/engine/elastic/semanticsetup.php');
$PAGE->set_url($url);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('semanticsetup', 'search_elastic'));
echo html_writer::tag('p', get_string('semanticsetup_desc', 'search_elastic'));

try {
    $provisioner = provisioner::from_config();
} catch (moodle_exception $e) {
    echo $OUTPUT->notification($e->getMessage(), 'error');
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'rebuild' && !$confirm) {
    $index = schema::get_index_name(get_config('search_elastic', 'index') ?: 'moodle');
    echo $OUTPUT->confirm(
        get_string('semanticsetup_rebuildconfirm', 'search_elastic', $index),
        new moodle_url($url, ['action' => 'rebuild', 'confirm' => 1, 'sesskey' => sesskey()]),
        $url
    );
    echo $OUTPUT->footer();
    exit;
}

if ($action === 'setup' || $action === 'rebuild') {
    require_sesskey();
    $steps = $action === 'rebuild' ? $provisioner->rebuild() : $provisioner->setup();
} else {
    $steps = $provisioner->get_status();
}

$table = new html_table();
$table->head = [
    get_string('semanticsetup_step', 'search_elastic'),
    get_string('semanticsetup_state', 'search_elastic'),
    get_string('semanticsetup_detail', 'search_elastic'),
];
$needsrebuild = false;
$allok = true;
foreach ($steps as $key => $step) {
    $needsrebuild = $needsrebuild || $step['state'] === provisioner::STATE_MISMATCH;
    $allok = $allok && $step['state'] === provisioner::STATE_OK;
    $table->data[] = [
        get_string("semanticsetup_step_{$key}", 'search_elastic'),
        get_string("semanticsetup_state_{$step['state']}", 'search_elastic'),
        s($step['detail']),
    ];
}
echo html_writer::table($table);

if (!$allok) {
    echo $OUTPUT->single_button(
        new moodle_url($url, ['action' => 'setup', 'sesskey' => sesskey()]),
        get_string('semanticsetup_run', 'search_elastic')
    );
}
if ($needsrebuild) {
    echo $OUTPUT->single_button(
        new moodle_url($url, ['action' => 'rebuild']),
        get_string('semanticsetup_rebuild', 'search_elastic')
    );
}

echo $OUTPUT->footer();
