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

namespace tool_autoflow\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use tool_autoflow\local\display;

/**
 * Run history of one workflow: each run with readable steps, newest first.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_list implements renderable, templatable {
    /** @var string[] Filters, in the order they are shown. */
    public const FILTERS = ['all', 'failed', 'success'];

    /** @var int Longest value shown inline before it is cut. */
    private const MAXVALUE = 120;

    /** @var string Active filter. */
    private string $filter;

    /**
     * Create the list.
     *
     * @param \stdClass $workflow
     * @param \stdClass[] $runs Run records, newest first.
     * @param string $filter all, failed or success.
     * @param int $now
     */
    public function __construct(
        /** @var \stdClass The workflow. */
        private readonly \stdClass $workflow,
        /** @var \stdClass[] Runs. */
        private readonly array $runs,
        string $filter,
        /** @var int Current time. */
        private readonly int $now,
    ) {
        $this->filter = in_array($filter, self::FILTERS, true) ? $filter : 'all';
    }

    /**
     * Data for tool_autoflow/run_list.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;
        $runs = array_values($this->runs);
        $shown = array_values(array_filter($runs, fn($r) => $this->filter === 'all' || $r->status === $this->filter));
        $userids = array_unique(array_filter(array_map(fn($r) => (int) $r->userid, $shown)));
        $users = $userids ? $DB->get_records_list('user', 'id', $userids) : [];

        $filters = [];
        foreach (self::FILTERS as $name) {
            $filters[] = [
                'name' => $name,
                'label' => get_string('filter:' . $name, 'tool_autoflow'),
                'count' => count(array_filter($runs, fn($r) => $name === 'all' || $r->status === $name)),
                'url' => (new \moodle_url('/admin/tool/autoflow/runs.php', ['id' => $this->workflow->id, 'status' => $name]))
                    ->out(false),
                'active' => $name === $this->filter,
            ];
        }

        $rows = [];
        foreach ($shown as $index => $run) {
            $user = $users[(int) $run->userid] ?? null;
            $log = json_decode($run->log ?? '[]', true) ?: [];
            $steps = array_map(fn($s) => $this->step($s), $log);
            $error = (string) ($run->error ?? '');
            $rows[] = [
                'id' => (int) $run->id,
                'status' => $run->status,
                'statuslabel' => display::run_status($run->status),
                'badge' => display::run_badge($run->status),
                'failed' => $run->status === 'failed',
                'trigger' => $this->trigger_label($run->triggertype, $log),
                'hasuser' => $user !== null,
                'user' => $user ? fullname($user) : '',
                'picture' => $user ? $output->user_picture($user, ['size' => 24, 'link' => false, 'alttext' => false]) : '',
                'ago' => display::ago((int) $run->timecreated, $this->now),
                'time' => userdate((int) $run->timecreated),
                'error' => $error,
                'showerror' => $error !== '' && !in_array(true, array_column($steps, 'failed'), true),
                'open' => $index === 0,
                'steps' => $steps,
                'hassteps' => !empty($steps),
            ];
        }

        return [
            'runs' => $rows,
            'hasruns' => !empty($rows),
            'filters' => $filters,
            'hasfilters' => !empty($runs),
            'emptymessage' => get_string(empty($runs) ? 'noruns' : 'norunsinfilter', 'tool_autoflow'),
        ];
    }

    /**
     * One step of a run, as the template shows it.
     *
     * @param array $step As the executor logs it: node, type, status, branch, output, error.
     * @return array
     */
    private function step(array $step): array {
        $failed = ($step['status'] ?? '') !== 'ok';
        $output = is_array($step['output'] ?? null) ? $step['output'] : [];
        $fields = [];
        // A trigger's data is the event as Moodle records it; it is noise in the summary, so it stays in the raw view.
        $istrigger = str_starts_with((string) ($step['type'] ?? ''), 'trigger.');
        foreach ($istrigger ? [] : $output as $name => $value) {
            $fields[] = ['name' => (string) $name, 'value' => $this->shorten($value)];
        }
        $branch = $step['branch'] ?? null;
        $branchlabel = $branch === null ? '' : display::string_or('branch:' . $branch, (string) $branch);
        return [
            'node' => (string) ($step['node'] ?? ''),
            'type' => (string) ($step['type'] ?? ''),
            'label' => display::string_or('node:' . ($step['type'] ?? ''), (string) ($step['type'] ?? '')),
            'failed' => $failed,
            'statuslabel' => get_string($failed ? 'step:error' : 'step:ok', 'tool_autoflow'),
            'error' => $failed ? (string) ($step['error'] ?? '') : '',
            'branch' => $branch === null ? '' : get_string('step:branch', 'tool_autoflow', $branchlabel),
            'fields' => $fields,
            'hasfields' => !empty($fields),
            'hasraw' => !empty($output),
            'raw' => $output ? json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
        ];
    }

    /**
     * What started a run, in words.
     *
     * @param string $type manual, schedule or event.
     * @param array $steps The run's steps; the first one holds the event name.
     * @return string
     */
    private function trigger_label(string $type, array $steps): string {
        if ($type !== 'event') {
            return display::string_or('trigger:' . $type, $type);
        }
        $class = $steps[0]['output']['eventname'] ?? '';
        $name = is_string($class) && class_exists($class) && is_subclass_of($class, \core\event\base::class)
            ? $class::get_name() : get_string('trigger:anevent', 'tool_autoflow');
        return get_string('trigger:event', 'tool_autoflow', $name);
    }

    /**
     * A value as short text: scalars as they are, lists and objects as compact JSON.
     *
     * @param mixed $value
     * @return string
     */
    private function shorten($value): string {
        if (is_bool($value)) {
            $text = $value ? 'true' : 'false';
        } else if (is_scalar($value) || $value === null) {
            $text = (string) $value;
        } else {
            $text = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return \core_text::strlen($text) > self::MAXVALUE ? \core_text::substr($text, 0, self::MAXVALUE) . '…' : $text;
    }
}
