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
use tool_autoflow\local\engine\param;

/**
 * Tests for the node that sends an HTTP request to another service.
 *
 * @package    tool_autoflow
 * @category   test
 * @copyright  2026 DevLMS <contact@devlms.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_autoflow\local\nodes\http_request_node
 */
final class http_request_node_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Run the node and return its output.
     *
     * @param array $params
     * @return array
     */
    private function run_node(array $params): array {
        return (new http_request_node())->execute($params, [])->output;
    }

    public function test_json_responses_are_decoded(): void {
        \curl::mock_response('{"ok": true, "items": [1, 2]}');
        $output = $this->run_node(['method' => 'get', 'url' => 'https://api.example.com/items']);
        $this->assertSame(200, $output['status']);
        $this->assertSame('{"ok": true, "items": [1, 2]}', $output['body']);
        $this->assertSame(['ok' => true, 'items' => [1, 2]], $output['json']);
    }

    public function test_other_responses_keep_the_body_only(): void {
        \curl::mock_response('plain words');
        $output = $this->run_node(['method' => 'post', 'url' => 'https://example.com/hook', 'body' => ['a' => 'b']]);
        $this->assertSame('plain words', $output['body']);
        $this->assertNull($output['json']);
    }

    public function test_body_must_be_a_json_object(): void {
        // The editor keeps text that is not yet valid JSON as a string: that must not be sent as if it were JSON.
        foreach (['{"a": ', 'plain text', '[1, 2]'] as $body) {
            try {
                $this->run_node(['method' => 'post', 'url' => 'https://example.com/', 'body' => $body]);
                $this->fail("Sent $body");
            } catch (node_failed $e) {
                $this->assertSame('The body must be a JSON object.', $e->getMessage());
            }
        }
        // An empty body is fine.
        \curl::mock_response('{}');
        $this->assertSame(200, $this->run_node(['method' => 'delete', 'url' => 'https://example.com/', 'body' => ' '])['status']);
    }

    public function test_method_defaults_to_get(): void {
        \curl::mock_response('{}');
        $this->assertSame(200, $this->run_node(['url' => 'https://example.com/'])['status']);
    }

    public function test_only_http_and_https(): void {
        foreach (['file:///etc/passwd', 'ftp://example.com/x', 'gopher://example.com', 'example.com', ''] as $url) {
            try {
                $this->run_node(['url' => $url]);
                $this->fail("Accepted $url");
            } catch (node_failed $e) {
                $this->assertStringContainsString('http or https', $e->getMessage());
            }
        }
    }

    public function test_unknown_methods_are_refused(): void {
        $this->expectException(node_failed::class);
        $this->expectExceptionMessage('TRACE');
        $this->run_node(['method' => 'TRACE', 'url' => 'https://example.com/']);
    }

    public function test_hosts_blocked_by_the_site_are_refused(): void {
        // Moodle's HTTP security setting blocks local and private addresses by default.
        set_config('curlsecurityblockedhosts', "127.0.0.0/8\nlocalhost\n169.254.169.254");
        foreach (['http://127.0.0.1/admin', 'http://localhost:8080/', 'http://169.254.169.254/latest/meta-data/'] as $url) {
            try {
                $this->run_node(['url' => $url]);
                $this->fail("Reached $url");
            } catch (node_failed $e) {
                $this->assertStringContainsString('blocked', $e->getMessage());
            }
        }
        // Moodle reports every blocked URL through debugging in tests.
        $this->assertDebuggingCalledCount(3);
    }

    public function test_headers_must_be_an_object_of_plain_values(): void {
        $bad = [
            'not an object' => '{"X": 1}',
            'header injection' => ['X-Token' => "abc\r\nX-Admin: yes"],
            'bad name' => ['X Token: a' => 'b'],
            'nested value' => ['X-List' => ['a']],
        ];
        foreach ($bad as $case => $headers) {
            try {
                $this->run_node(['url' => 'https://example.com/', 'headers' => $headers]);
                $this->fail("Accepted $case");
            } catch (node_failed $e) {
                $this->assertStringContainsString('header', strtolower($e->getMessage()), $case);
            }
        }
    }

    public function test_good_headers_are_sent(): void {
        \curl::mock_response('{}');
        $output = $this->run_node(['url' => 'https://example.com/', 'headers' => ['Authorization' => 'Bearer x', 'X-Id' => 7]]);
        $this->assertSame(200, $output['status']);
    }

    public function test_request_headers_are_built_safely(): void {
        $this->assertSame(
            ['Authorization: Bearer x', 'X-Id: 7', 'Content-Type: application/json'],
            http_request_node::header_lines(['Authorization' => 'Bearer x', 'X-Id' => 7], true)
        );
        // A Content-Type the user set wins over the JSON default.
        $this->assertSame(['content-type: text/plain'], http_request_node::header_lines(['content-type' => 'text/plain'], true));
        $this->assertSame([], http_request_node::header_lines([], false));
    }

    public function test_error_statuses_fail_the_node(): void {
        $node = new http_request_node(fn() => new class extends \curl {
            /**
             * Answer every request with a server error.
             *
             * @param string $url
             * @param array $options
             * @return string
             */
            protected function request($url, $options = []) {
                $this->info = ['http_code' => 503];
                return 'Service down';
            }
        });
        try {
            $node->execute(['url' => 'https://example.com/'], []);
            $this->fail('Accepted a 503');
        } catch (node_failed $e) {
            $this->assertSame('https://example.com/ answered 503: Service down', $e->getMessage());
        }
    }

    public function test_requests_have_limits(): void {
        $curl = http_request_node::make_curl();
        $options = (new \ReflectionProperty(\curl::class, 'options'))->getValue($curl);
        $this->assertSame(15, $options['CURLOPT_TIMEOUT']);
        $this->assertSame(5, $options['CURLOPT_CONNECTTIMEOUT']);
        $this->assertSame(3, $options['CURLOPT_MAXREDIRS']);
        $this->assertSame(http_request_node::MAX_BYTES, $options['CURLOPT_MAXFILESIZE']);
        $this->assertSame(CURLPROTO_HTTP | CURLPROTO_HTTPS, $options['CURLOPT_PROTOCOLS']);
    }

    public function test_long_bodies_are_cut(): void {
        \curl::mock_response(str_repeat('a', http_request_node::MAX_BYTES + 10));
        $output = $this->run_node(['url' => 'https://example.com/']);
        $this->assertSame(http_request_node::MAX_BYTES, strlen($output['body']));
        $this->assertTrue($output['truncated']);
    }

    public function test_metadata(): void {
        $node = new http_request_node();
        $this->assertSame('action.http', $node->type());
        $this->assertSame([], $node->branches());
        $this->assertEquals([
            new param('method', 'select', true, ['get', 'post', 'put', 'patch', 'delete']),
            new param('url', 'text', true),
            new param('headers', 'json', false),
            new param('body', 'json', false),
        ], $node->params());
    }

    public function test_urls_in_errors_drop_keys_and_user_info(): void {
        $this->assertSame(
            'https://api.example.com/v1/x?...',
            \tool_autoflow\local\nodes\http_request_node::safe_url('https://user:pw@api.example.com/v1/x?key=secret#f')
        );
        $this->assertSame('http://h:8080/p', \tool_autoflow\local\nodes\http_request_node::safe_url('http://h:8080/p'));
    }
}
