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
 * Sends an HTTP request to another service and returns its answer.
 *
 * Requests go through Moodle's curl class, so the site's HTTP security settings
 * (blocked hosts and allowed ports, which block local and private addresses by
 * default) apply to the first URL and to every redirect. Only http and https are
 * allowed, requests time out, redirects and response size are capped, and headers
 * are checked so a placeholder value cannot add a header of its own.
 *
 * @package    tool_autoflow
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class http_request_node implements node_type {
    /** Largest response body kept, in bytes. */
    public const MAX_BYTES = 262144;

    /** Methods the node can send. */
    private const METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    /** @var \Closure|null Makes the curl object; tests pass their own. */
    private readonly ?\Closure $makecurl;

    /**
     * Create the node.
     *
     * @param \Closure|null $makecurl Returns a \curl. Defaults to {@see self::make_curl()}.
     */
    public function __construct(?\Closure $makecurl = null) {
        $this->makecurl = $makecurl;
    }

    /**
     * Type name.
     *
     * @return string
     */
    public function type(): string {
        return 'action.http';
    }

    /**
     * Parameters.
     *
     * @return param[]
     */
    public function params(): array {
        return [
            new param('method', 'select', true, self::METHODS),
            new param('url', 'text', true),
            new param('headers', 'json', false),
            new param('body', 'json', false),
        ];
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
     * A curl object with the node's limits.
     *
     * @return \curl
     */
    public static function make_curl(): \curl {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 15,
            'CURLOPT_CONNECTTIMEOUT' => 5,
            'CURLOPT_MAXREDIRS' => 3,
            'CURLOPT_MAXFILESIZE' => self::MAX_BYTES,
            'CURLOPT_PROTOCOLS' => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);
        return $curl;
    }

    /**
     * Header lines from a JSON object of names and plain values.
     *
     * @param mixed $headers
     * @param bool $jsonbody Whether the body is JSON, which adds a Content-Type unless one is set.
     * @return string[]
     */
    public static function header_lines(mixed $headers, bool $jsonbody): array {
        if (!is_array($headers) || ($headers && array_is_list($headers))) {
            throw new node_failed('Headers must be a JSON object of names and values.');
        }
        $lines = [];
        $hastype = false;
        foreach ($headers as $name => $value) {
            $name = (string) $name;
            if (!preg_match('/^[A-Za-z0-9!#$%&\'*+.^_\x60|~-]+$/', $name)) {
                throw new node_failed("\"$name\" is not a valid header name.");
            }
            if (!is_scalar($value) || preg_match('/[\r\n\0]/', (string) $value)) {
                throw new node_failed("The header $name must be one line of text.");
            }
            $hastype = $hastype || strtolower($name) === 'content-type';
            $lines[] = $name . ': ' . $value;
        }
        if ($jsonbody && !$hastype) {
            $lines[] = 'Content-Type: application/json';
        }
        return $lines;
    }

    /**
     * Send the request.
     *
     * @param array $params method, url, headers, body
     * @param array $context
     * @return node_result status, body, json (the decoded body or null) and truncated.
     */
    public function execute(array $params, array $context): node_result {
        $method = strtolower(trim((string) ($params['method'] ?? '')) ?: 'get');
        if (!in_array($method, self::METHODS, true)) {
            throw new node_failed('The method must be GET, POST, PUT, PATCH or DELETE, not ' . strtoupper($method) . '.');
        }
        $url = trim((string) ($params['url'] ?? ''));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true) || !parse_url($url, PHP_URL_HOST)) {
            throw new node_failed("The URL must start with http or https: \"$url\".");
        }
        $body = $params['body'] ?? [];
        if (is_string($body) && trim($body) === '') {
            $body = [];
        }
        if (!is_array($body) || ($body && array_is_list($body))) {
            throw new node_failed('The body must be a JSON object.');
        }
        $jsonbody = $body !== [];
        $payload = $jsonbody ? json_encode($body) : '';
        $headers = self::header_lines($params['headers'] ?? [], $jsonbody);

        $curl = $this->makecurl ? ($this->makecurl)() : self::make_curl();
        if ($headers) {
            $curl->setHeader($headers);
        }
        $response = $method === 'get'
            ? $curl->get($url)
            : $curl->post($url, $payload, ['CURLOPT_CUSTOMREQUEST' => strtoupper($method)]);
        if (!empty($curl->error)) {
            throw new node_failed("$url could not be reached: {$curl->error}");
        }
        $status = (int) ($curl->info['http_code'] ?? 0);
        $response = (string) $response;
        $truncated = strlen($response) > self::MAX_BYTES;
        if ($truncated) {
            $response = substr($response, 0, self::MAX_BYTES);
        }
        if ($status >= 400 || $status === 0) {
            throw new node_failed("$url answered $status: " . shorten_text($response, 200));
        }
        $json = json_decode($response, true);
        return new node_result([
            'status' => $status,
            'body' => $response,
            'json' => is_array($json) ? $json : null,
            'truncated' => $truncated,
        ]);
    }
}
