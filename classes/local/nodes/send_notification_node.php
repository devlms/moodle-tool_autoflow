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

namespace tool_autoflow\local\nodes;

use tool_autoflow\local\engine\node_failed;
use tool_autoflow\local\engine\node_result;
use tool_autoflow\local\engine\node_type;
use tool_autoflow\local\engine\param;

/**
 * Sends a Moodle notification to one user, from the no-reply user.
 *
 * It goes through the plugin's own message provider, so users and admins control
 * its delivery (popup, email) in the normal notification preferences.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class send_notification_node implements node_type {
    /**
     * Type name.
     *
     * @return string
     */
    public function type(): string {
        return 'moodle.send_notification';
    }

    /**
     * Parameters.
     *
     * @return param[]
     */
    public function params(): array {
        return [new param('userid', 'user', true), new param('subject', 'text', true), new param('message', 'textarea', true)];
    }

    /**
     * Branches.
     *
     * @return string[]
     */
    public function branches(): array {
        return [];
    }

    /**
     * Send it.
     *
     * @param array $params userid, subject, message
     * @param array $context
     * @return node_result
     */
    public function execute(array $params, array $context): node_result {
        $userid = $params['userid'] ?? 0;
        $user = is_numeric($userid) ? \core_user::get_user((int) $userid) : false;
        if (!$user || $user->deleted) {
            throw new node_failed("Cannot send a notification: no user with id \"$userid\".");
        }
        $subject = trim((string) ($params['subject'] ?? ''));
        if ($subject === '') {
            throw new node_failed('A notification needs a subject.');
        }
        $text = (string) ($params['message'] ?? '');

        $message = new \core\message\message();
        $message->component = 'tool_autoflow';
        $message->name = 'notification';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = $subject;
        $message->fullmessage = $text;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = text_to_html(s($text));
        $message->smallmessage = $subject;
        $message->notification = 1;
        $messageid = message_send($message);
        if (!$messageid) {
            throw new node_failed("The notification to user {$user->id} was not sent.");
        }
        return new node_result(['messageid' => (int) $messageid, 'userid' => (int) $user->id]);
    }
}
