# Autoflow for Moodle (tool_autoflow)

[![CI](https://github.com/devlms/moodle-tool_autoflow/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/devlms/moodle-tool_autoflow/actions/workflows/ci.yml)
[![PHPUnit + Behat](https://img.shields.io/endpoint?url=https%3A%2F%2Fdevlms.github.io%2Fmoodle-tool_autoflow%2Fbadges%2Fmoodle.json)](https://devlms.github.io/moodle-tool_autoflow/)
[![Playwright e2e](https://img.shields.io/endpoint?url=https%3A%2F%2Fdevlms.github.io%2Fmoodle-tool_autoflow%2Fbadges%2Fe2e.json)](https://devlms.github.io/moodle-tool_autoflow/e2e/)
[![PHP coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fdevlms.github.io%2Fmoodle-tool_autoflow%2Fbadges%2Fphp-coverage.json)](https://devlms.github.io/moodle-tool_autoflow/php-coverage/)
[![PHP mutation](https://img.shields.io/endpoint?url=https%3A%2F%2Fdevlms.github.io%2Fmoodle-tool_autoflow%2Fbadges%2Fphp-mutation.json)](https://devlms.github.io/moodle-tool_autoflow/php-mutation/)
[![JS coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fdevlms.github.io%2Fmoodle-tool_autoflow%2Fbadges%2Fjs-coverage.json)](https://devlms.github.io/moodle-tool_autoflow/js-coverage/)
[![JS mutation](https://img.shields.io/endpoint?url=https%3A%2F%2Fdevlms.github.io%2Fmoodle-tool_autoflow%2Fbadges%2Fjs-mutation.json)](https://devlms.github.io/moodle-tool_autoflow/js-mutation/)
[![Moodle](https://img.shields.io/badge/Moodle-4.5%20to%205.3-f98012)](https://moodledev.io/general/releases)
[![PHP](https://img.shields.io/badge/PHP-8.1%20to%208.4-777bb4)](https://www.php.net/supported-versions.php)
[![License: GPL v3](https://img.shields.io/badge/license-GPL%20v3-blue)](LICENSE)

Build Moodle automations as workflows in a node editor, instead of writing a new plugin for every one.

> **Status: alpha.** Workflows can be built, saved and run, and every layer is tested. The node library is small for now, and required node settings are only checked when the workflow runs, not when it is saved. Try it on a test site, not a production one.

## Why

Every school that runs Moodle has a list of small automations: email a student when they fall behind, enrol people when they finish a course, tell a teacher when a quiz closes. Today each one means a custom plugin, a code review and an upgrade to maintain.

Autoflow turns Moodle's own APIs into nodes. An administrator, or anyone with the right capability, wires them together in a [React Flow](https://reactflow.dev) editor, and Moodle runs the workflow from scheduled or ad hoc tasks. Think n8n, Zapier or Make, but inside Moodle, with Moodle's permissions and logs.

## What it does today

| Part | Status |
| --- | --- |
| Node editor (React Flow): palette, settings per node, branch handles, problems list | Done |
| Triggers: started by hand, on a schedule (cron, site timezone), when a Moodle event happens | Done |
| Nodes: condition, set variable, write to run log, send notification, call a Moodle function | Done |
| Placeholders in any setting: `{{trigger.relateduserid}}`, `{{nodes.<id>.<key>}}`, `{{vars.<name>}}` | Done |
| Run history with each step's output or error | Done |
| Loop protection: a workflow that fires its own trigger stops after 3 levels | Done |
| Privacy provider and capabilities | Done |
| Runs as the user who saved it last, so a workflow never exceeds that person's permissions | Done |
| Node: call any Moodle web service function (the Moodle API as nodes), arguments as JSON with placeholders | Done |
| Node: HTTP request to an external service | Planned |
| More Moodle nodes: enrol, add to group, grade, course actions | Planned |

## How a workflow runs

1. A trigger fires: the Run now button, the every-minute schedule task, or a Moodle event caught by one observer that does a single cached lookup and returns.
2. An ad hoc task is queued as the workflow's run-as user, so the work never happens inside someone's page request.
3. The engine walks the graph from the trigger. Each node gets its settings with placeholders filled in, and a condition picks which branch continues.
4. The run, with every step's output, is saved to the run history. A node that fails stops the run and records why.

## How it is built

- **Admin tool** (`admin/tool/autoflow`). Managing workflows is a system capability (`tool/autoflow:manage`) with config, data loss, XSS and personal data risk, given to managers by default.
- **Engine** in `classes/local/engine`: plain PHP with no Moodle globals. That keeps it fast to test and lets mutation testing run thousands of times without booting Moodle.
- **Editor** in `frontend/`: React and React Flow, written in TypeScript, built by Vite into one script in `frontend/dist` that the plugin ships. React is bundled in, so it never clashes with the copy Moodle 5.2+ uses for its own components.
- Supports Moodle 4.5 LTS to 5.3 LTS, on PHP 8.1 to 8.4.

## Tests

Every push runs all of these. Reports and badge data are published to [devlms.github.io/moodle-tool_autoflow](https://devlms.github.io/moodle-tool_autoflow/).

| Layer | Tool | Where |
| --- | --- | --- |
| Moodle checks: lint, code style, PHPDoc, Mustache, Grunt, savepoints, validation | [moodle-plugin-ci](https://github.com/moodlehq/moodle-plugin-ci) | `.github/workflows/ci.yml` |
| PHP unit and integration tests, on every supported Moodle and PHP pairing | PHPUnit | `tests/` |
| PHP coverage | PHPUnit + pcov | Moodle 5.3 job |
| PHP mutation testing | [Infection](https://infection.github.io) | `infection.json5`, engine only |
| Behaviour tests in Moodle | Behat | `tests/behat/` |
| Editor unit and component tests, with coverage | Vitest + Testing Library | `frontend/test/` |
| Editor mutation testing | [Stryker](https://stryker-mutator.io) | `frontend/stryker.config.json` |
| End to end in a real browser | Playwright | `frontend/e2e/` |

Thresholds: Infection fails under 90% MSI, Stryker under 80%. The CI also fails if `frontend/dist` does not match a fresh build of `frontend/src`.

### Running them locally

Engine tests, coverage and mutation testing (Docker provides PHP with pcov):

```sh
composer install
vendor/bin/phpunit -c dev/phpunit.xml
dev/php vendor/bin/phpunit -c dev/phpunit.xml --coverage-text
dev/php vendor/bin/infection --threads=4
```

Editor:

```sh
cd frontend
npm ci
npm test                 # Vitest
npm run test:coverage
npm run test:mutation    # Stryker
npm run build            # rebuild frontend/dist, then commit it
```

Moodle PHPUnit, Behat and Playwright run against [moodle-docker](https://github.com/moodlehq/moodle-docker) with this plugin mounted at `admin/tool/autoflow`:

```sh
# in moodle-docker, with MOODLE_DOCKER_WWWROOT pointing at a Moodle checkout
bin/moodle-docker-compose exec webserver php admin/tool/phpunit/cli/init.php
bin/moodle-docker-compose exec webserver vendor/bin/phpunit --testsuite tool_autoflow_testsuite
bin/moodle-docker-compose exec webserver php admin/tool/behat/cli/init.php
bin/moodle-docker-compose exec -u www-data webserver php admin/tool/behat/cli/run.php --tags=@tool_autoflow
cd /path/to/moodle-tool_autoflow/frontend
MOODLE_URL=http://localhost:8000 MOODLE_ADMIN_PASS=... \
  MOODLE_CRON_CMD="cd /path/to/moodle-docker && bin/moodle-docker-compose exec -T webserver php admin/cli/adhoc_task.php --execute" \
  npx playwright test
```

## Contributing

Issues and pull requests are welcome. New code comes with tests, and the mutation scores should not drop.

## License

GNU GPL v3 or later. Built by [DevLMS](https://devlms.com).
