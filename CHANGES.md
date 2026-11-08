# Changes

## 1.0.0 (2026-11-08)

First stable release.

- A workflow never falls back to running as the site admin. If its run-as user is deleted, suspended or removed by a privacy request, the workflow records a failed run with the reason and turns itself off until someone picks a new user. Workflows saved by older versions without a run-as user now run as the person who last saved them.
- A privacy deletion that removes a run-as user also turns those workflows off.
- Unexpected errors during a run are recorded as a failed run instead of being retried by cron, which could repeat earlier steps.
- Run history older than 90 days is deleted by a daily task (plugin setting runretentiondays, 0 keeps everything).
- URLs in run errors leave out query strings and user info, which often carry keys.
- People with only the view runs capability can open the runs page.
- The privacy export includes each run's log and error.
- Workflow names with "&" no longer show as "&amp;".
- CI covers Moodle 4.5, 5.0, 5.1, 5.2 and 5.3 on PostgreSQL and MariaDB.

Earlier versions: see the GitHub releases (0.1.0 to 0.5.0).
