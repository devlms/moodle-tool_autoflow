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

> **Status: alpha.** The test and release setup is in place and the engine is being built. Do not use it on a production site yet.

## Why

Every school that runs Moodle has a list of small automations: email a student when they fall behind, enrol people when they finish a course, tell a teacher when a quiz closes. Today each one means a custom plugin, a code review and an upgrade to maintain.

Autoflow turns Moodle's own APIs into nodes. An administrator, or anyone with the right capability, wires them together in a [React Flow](https://reactflow.dev) editor, and Moodle runs the workflow from scheduled or ad hoc tasks. Think n8n, Zapier or Make, but inside Moodle, with Moodle's permissions and logs.

## What it will do

| Part | Status |
| --- | --- |
| Workflow list, capabilities, privacy provider | Done |
| Placeholder templates (`{{trigger.relateduserid}}`) | Done |
| Graph validation (one trigger, no cycles, no dangling edges) | Done in the editor, server side next |
| Triggers: run now, schedule, Moodle events | Next |
| Nodes: condition, set variable, log, send notification | Next |
| Node: call any Moodle external function (the Moodle API as nodes) | Planned |
| Node: HTTP request to an external service | Planned |
| Run history with step logs | Planned |

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
cd /path/to/moodle-tool_autoflow/frontend && MOODLE_URL=http://localhost:8000 MOODLE_ADMIN_PASS=... npx playwright test
```

## Contributing

Issues and pull requests are welcome. New code comes with tests, and the mutation scores should not drop.

## License

GNU GPL v3 or later. Built by [DevLMS](https://devlms.com).
