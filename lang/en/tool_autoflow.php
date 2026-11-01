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

/**
 * English strings for tool_autoflow.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions'] = 'Actions';
$string['autoflow:manage'] = 'Create, edit and run workflows';
$string['autoflow:viewruns'] = 'View workflow run history';
$string['branch:false'] = 'No';
$string['branch:true'] = 'Yes';
$string['cachedef_eventmap'] = 'Workflows started by each event';
$string['cat:action'] = 'Actions';
$string['cat:logic'] = 'Logic';
$string['cat:moodle'] = 'Moodle';
$string['cat:trigger'] = 'Triggers';
$string['choose'] = 'Choose...';
$string['deletenode'] = 'Delete node';
$string['deleteworkflow'] = 'Delete workflow';
$string['deleteworkflowconfirm'] = 'Delete the workflow "{$a}" and its run history?';
$string['editworkflow'] = 'Edit workflow';
$string['enabled'] = 'Enabled';
$string['error:badcron'] = 'The schedule is not a valid cron expression, for example 0 8 * * 1-5.';
$string['error:badevent'] = 'Choose an event that exists on this site.';
$string['error:cycle'] = 'The workflow loops back on itself. Remove the link that closes the loop.';
$string['error:danglingedge'] = 'A link points to a node that no longer exists.';
$string['error:duplicateid'] = 'Two nodes share the same id.';
$string['error:manytriggers'] = 'A workflow can have only one trigger.';
$string['error:missingparam'] = 'Fill in the required settings of the marked nodes.';
$string['error:notrigger'] = 'Add a trigger to start the workflow.';
$string['error:unknowntype'] = 'A node uses a type that is not installed.';
$string['error:unreachable'] = 'Some nodes are not connected to the trigger.';
$string['invalidjson'] = 'Not a JSON object yet.';
$string['lastrun'] = 'Last run';
$string['messageprovider:notification'] = 'Messages sent by workflows';
$string['never'] = 'Never';
$string['newworkflow'] = 'New workflow';
$string['node:action.http'] = 'Send an HTTP request';
$string['node:action.http:help'] = 'Calls another service over http or https. Hosts blocked in the site HTTP security settings stay blocked.';
$string['node:action.log'] = 'Write to run log';
$string['node:logic.condition'] = 'Condition';
$string['node:logic.set'] = 'Set variable';
$string['node:moodle.add_to_group'] = 'Add user to group';
$string['node:moodle.call_function'] = 'Call a Moodle function';
$string['node:moodle.call_function:help'] = 'Runs any web service function installed on this site, as the user the workflow runs as.';
$string['node:moodle.enrol_user'] = 'Enrol user in course';
$string['node:moodle.send_notification'] = 'Send notification';
$string['node:trigger.event'] = 'When an event happens';
$string['node:trigger.manual'] = 'Started by hand';
$string['node:trigger.schedule'] = 'On a schedule';
$string['nodesettings'] = 'Node settings';
$string['noworkflows'] = 'No workflows yet. Create one to automate a task without writing a plugin.';
$string['option:contains'] = 'contains';
$string['option:delete'] = 'DELETE';
$string['option:empty'] = 'is empty';
$string['option:eq'] = 'is equal to';
$string['option:get'] = 'GET';
$string['option:gt'] = 'is greater than';
$string['option:gte'] = 'is at least';
$string['option:lt'] = 'is less than';
$string['option:lte'] = 'is at most';
$string['option:neq'] = 'is not equal to';
$string['option:notempty'] = 'is not empty';
$string['option:patch'] = 'PATCH';
$string['option:post'] = 'POST';
$string['option:put'] = 'PUT';
$string['palette'] = 'Add a node';
$string['param:args'] = 'Arguments (JSON object, placeholders allowed in values)';
$string['param:body'] = 'Body (JSON object, sent as JSON)';
$string['param:courseid'] = 'Course id';
$string['param:cron'] = 'Schedule (cron)';
$string['param:eventname'] = 'Event';
$string['param:function'] = 'Function';
$string['param:groupid'] = 'Group id';
$string['param:headers'] = 'Headers (JSON object)';
$string['param:left'] = 'Value';
$string['param:message'] = 'Message';
$string['param:method'] = 'Method';
$string['param:name'] = 'Name';
$string['param:operator'] = 'Operator';
$string['param:right'] = 'Compare with';
$string['param:role'] = 'Role short name (default: student)';
$string['param:subject'] = 'Subject';
$string['param:url'] = 'URL';
$string['param:userid'] = 'User id';
$string['param:value'] = 'Value';
$string['pluginname'] = 'Autoflow';
$string['privacy:metadata:httprequest'] = 'A workflow with an HTTP request node sends data to the service its URL names. The people who build the workflow choose that service and what is sent.';
$string['privacy:metadata:httprequest:data'] = 'Whatever the workflow puts in the request URL, headers and body, which can include user data from the trigger.';
$string['privacy:metadata:run'] = 'A record of each workflow run, including the user the triggering event was about.';
$string['privacy:metadata:run:error'] = 'Why the run failed, which can mention the user.';
$string['privacy:metadata:run:log'] = 'What each step of the run did, which can include the user\'s name and other details.';
$string['privacy:metadata:run:timecreated'] = 'When the run started.';
$string['privacy:metadata:run:userid'] = 'The user the triggering event was about.';
$string['privacy:metadata:workflow'] = 'Workflows, with the user who last changed them and the user they run as.';
$string['privacy:metadata:workflow:runasuserid'] = 'The user the workflow runs as.';
$string['privacy:metadata:workflow:timemodified'] = 'When the workflow was last changed.';
$string['privacy:metadata:workflow:usermodified'] = 'The user who last changed the workflow.';
$string['problems'] = 'Problems';
$string['run:error'] = 'Error';
$string['run:status'] = 'Status';
$string['run:steps'] = 'Steps';
$string['run:trigger'] = 'Started by';
$string['runasuserid'] = 'Runs as (user id)';
$string['runnow'] = 'Run now';
$string['runqueued'] = 'Run queued. It starts with the next cron run.';
$string['runs'] = 'Runs';
$string['runsas'] = 'Runs as';
$string['save'] = 'Save';
$string['saved'] = 'Saved';
$string['savefailed'] = 'Could not save';
$string['saving'] = 'Saving...';
$string['selectnode'] = 'Select a node to change its settings.';
$string['status:failed'] = 'Failed';
$string['status:running'] = 'Running';
$string['status:success'] = 'Succeeded';
$string['task:checkschedules'] = 'Start scheduled workflows';
$string['task:runworkflow'] = 'Run a workflow';
$string['trigger'] = 'Trigger';
$string['workflowname'] = 'Workflow name';
$string['workflowruns'] = 'Run history';
$string['workflows'] = 'Workflows';
$string['workflowsaved'] = 'Workflow saved';
