<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_autoflow;

use tool_autoflow\local\runner;
use tool_autoflow\local\workflow_store;

/**
 * Starts workflows whose trigger is the event that just happened.
 *
 * This sees every event on the site, so it does one cached lookup and returns.
 * The run happens later in an ad hoc task, never inside the user's request.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Queue the workflows for this event.
     *
     * @param \core\event\base $event
     */
    public static function handle(\core\event\base $event): void {
        global $CFG;
        if (during_initial_install() || !empty($CFG->upgraderunning)) {
            return;
        }
        $ids = workflow_store::workflows_for_event($event->eventname);
        if (!$ids) {
            return;
        }
        // An event fired by a running workflow starts the next level of the chain.
        $current = runner::current_depth();
        $depth = $current === null ? 0 : $current + 1;
        $data = $event->get_data();
        foreach ($ids as $id) {
            runner::queue($id, $data, 'event', $depth);
        }
    }
}
