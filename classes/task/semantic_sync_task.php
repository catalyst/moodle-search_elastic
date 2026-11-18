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

namespace search_elastic\task;

use core\task\scheduled_task;
use search_elastic\engine;
use search_elastic\esrequest;
use search_elastic\local\semantic\course_scope;
use search_elastic\local\semantic\sync;

/**
 * Keeps the semantic index in line with the course scope: removes courses that left it, adds the missing ones.
 *
 * @package    search_elastic
 * @copyright  2026 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class semantic_sync_task extends scheduled_task {
    /** @var int Seconds after which no new batch is started. */
    private const TIME_LIMIT = 1800;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('semanticsynctask', 'search_elastic');
    }

    /**
     * Do the job.
     */
    public function execute() {
        $engine = new engine();
        $indexer = $engine->get_semantic_indexer();
        if ($indexer === null) {
            return;
        }

        $config = get_config('search_elastic');
        $sync = new sync(new esrequest(), $engine->get_url(), $config->index ?: 'moodle', $indexer);
        $courseids = course_scope::get_courseids();

        $removed = $sync->remove_out_of_scope($courseids);
        mtrace('Removed ' . count($removed) . ' out of scope courses from the semantic index.');
        $copied = $sync->backfill($courseids, time() + self::TIME_LIMIT);
        mtrace("Added {$copied} documents to the semantic index.");
    }
}
