// Builds the GitHub Pages site from the CI reports: one shields.io endpoint badge per
// metric in badges/, and an index page that links every report.
//   node .github/scripts/report.mjs <site dir>
// Env: RESULTS (toJSON(needs)), RUN_URL, COMMIT.
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const site = process.argv[2] ?? 'site';
const results = JSON.parse(process.env.RESULTS ?? '{}');
const read = (path) => (existsSync(join(site, path)) ? readFileSync(join(site, path), 'utf8') : null);

/** Line coverage from a Clover XML report, as a percentage. */
export function cloverPercent(xml) {
  // The project total is the metrics element that counts files; per-file elements do not.
  const m = xml?.match(/<metrics files="\d+"[^>]*\bstatements="(\d+)"[^>]*\bcoveredstatements="(\d+)"/);
  if (!m) return null;
  const [total, covered] = [Number(m[1]), Number(m[2])];
  return total === 0 ? 100 : (covered / total) * 100;
}

/** Stryker's mutation score: detected mutants over all valid mutants. */
export function strykerScore(json) {
  if (!json) return null;
  let detected = 0;
  let valid = 0;
  for (const file of Object.values(JSON.parse(json).files)) {
    for (const m of file.mutants) {
      if (['Killed', 'Timeout'].includes(m.status)) detected++;
      if (['Killed', 'Timeout', 'Survived', 'NoCoverage'].includes(m.status)) valid++;
    }
  }
  return valid === 0 ? 100 : (detected / valid) * 100;
}

const colour = (pct) => (pct === null ? 'lightgrey' : pct >= 95 ? 'brightgreen' : pct >= 85 ? 'green' : pct >= 70 ? 'yellow' : 'red');
const pct = (v) => (v === null ? 'unknown' : `${Math.floor(v * 10) / 10}%`);
const status = (job) => results[job]?.result ?? 'unknown';

const infection = read('php-mutation/summary.json');
const e2e = read('e2e/results.json') ? JSON.parse(read('e2e/results.json')).stats : null;
const jsSummary = read('js-coverage/coverage-summary.json');

const metrics = {
  'php-coverage': { label: 'PHP coverage', value: cloverPercent(read('php-coverage/clover.xml')), report: 'php-coverage/' },
  'php-mutation': {
    label: 'PHP mutation (MSI)',
    value: infection ? JSON.parse(infection).stats.msi : null,
    report: 'php-mutation/',
  },
  'js-coverage': {
    label: 'JS coverage',
    value: jsSummary ? JSON.parse(jsSummary).total.lines.pct : null,
    report: 'js-coverage/',
  },
  'js-mutation': { label: 'JS mutation', value: strykerScore(read('js-mutation/mutation.json')), report: 'js-mutation/' },
};

mkdirSync(join(site, 'badges'), { recursive: true });
const badge = (name, label, message, color) =>
  writeFileSync(join(site, 'badges', `${name}.json`), JSON.stringify({ schemaVersion: 1, label, message, color }));

for (const [name, m] of Object.entries(metrics)) {
  badge(name, m.label, pct(m.value), colour(m.value));
}
const e2eMessage = e2e ? `${e2e.expected} passed${e2e.unexpected ? `, ${e2e.unexpected} failed` : ''}` : status('e2e');
badge('e2e', 'Playwright e2e', e2eMessage, e2e && !e2e.unexpected && status('e2e') === 'success' ? 'brightgreen' : 'red');
badge('moodle', 'PHPUnit + Behat', status('moodle') === 'success' ? 'passing' : status('moodle'), status('moodle') === 'success' ? 'brightgreen' : 'red');

const rows = Object.values(metrics)
  .map((m) => `<tr><td>${m.label}</td><td>${pct(m.value)}</td><td><a href="${m.report}">report</a></td></tr>`)
  .join('\n');
writeFileSync(
  join(site, 'index.html'),
  `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>tool_autoflow test reports</title>
<style>body{font:16px/1.5 system-ui,sans-serif;max-width:46rem;margin:2rem auto;padding:0 1rem;color:#14161a}
table{border-collapse:collapse;width:100%}td,th{border-bottom:1px solid #dde;padding:.4rem;text-align:left}a{color:#2438ff}</style></head>
<body><h1>tool_autoflow test reports</h1>
<p>Built from commit <code>${(process.env.COMMIT ?? '').slice(0, 7)}</code>. <a href="${process.env.RUN_URL ?? '#'}">CI run</a>.</p>
<table><thead><tr><th>Metric</th><th>Value</th><th></th></tr></thead><tbody>
${rows}
<tr><td>Playwright end to end</td><td>${e2eMessage}</td><td><a href="e2e/">report</a></td></tr>
<tr><td>Moodle PHPUnit and Behat (all supported versions)</td><td>${status('moodle')}</td><td><a href="${process.env.RUN_URL ?? '#'}">run</a></td></tr>
<tr><td>Engine coverage (standalone)</td><td>${pct(cloverPercent(read('engine-coverage/clover.xml')))}</td><td><a href="engine-coverage/">report</a></td></tr>
</tbody></table>
<p><a href="https://github.com/devlms/moodle-tool_autoflow">Source on GitHub</a></p></body></html>
`,
);
console.log(Object.fromEntries(Object.entries(metrics).map(([k, m]) => [k, pct(m.value)])), 'e2e:', e2eMessage);
