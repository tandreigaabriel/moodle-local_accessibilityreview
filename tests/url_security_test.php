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

namespace local_accessibilityreview;

use local_accessibilityreview\local\links\http_fetcher;
use local_accessibilityreview\local\links\link_classifier;
use local_accessibilityreview\local\links\security_helper;
use local_accessibilityreview\local\links\url_guard;
use local_accessibilityreview\local\links\vimeo;

/**
 * SSRF protection, link classification and Vimeo handling.
 *
 * DNS is replaced by a fake resolver so the tests never touch the network.
 *
 * @package    local_accessibilityreview
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_accessibilityreview\local\links\url_guard
 * @covers     \local_accessibilityreview\local\links\security_helper
 * @covers     \local_accessibilityreview\local\links\link_classifier
 * @covers     \local_accessibilityreview\local\links\vimeo
 */
final class url_security_test extends \advanced_testcase {
    /**
     * Guard with a fake resolver.
     *
     * @return url_guard
     */
    private function guard(): url_guard {
        $dns = [
            'example.com' => ['93.184.215.14'],
            'intranet.example.org' => ['10.0.0.5'],
            'rebind.example.net' => ['93.184.215.14', '127.0.0.1'],
            'sixonly.example.net' => ['::1'],
            'metadata-alias.example.net' => ['169.254.169.254'],
            'cgnat.example.net' => ['100.100.100.200'],
        ];
        return new url_guard(fn(string $host) => $dns[$host] ?? []);
    }

    /**
     * Unsafe URLs that must be blocked, with the expected reason.
     *
     * @return array
     */
    public static function blocked_provider(): array {
        return [
            'file scheme' => ['file:///etc/passwd', 'invalidurl'],
            'gopher scheme' => ['gopher://example.com/', 'scheme'],
            'ftp scheme' => ['ftp://example.com/file', 'scheme'],
            'loopback v4' => ['http://127.0.0.1/', 'privateaddress'],
            'loopback v6' => ['http://[::1]/', 'privateaddress'],
            'mapped v6' => ['http://[::ffff:127.0.0.1]/', 'privateaddress'],
            'private 10/8' => ['http://10.1.2.3/', 'privateaddress'],
            'private 192.168' => ['http://192.168.1.10/', 'privateaddress'],
            'link-local metadata' => ['http://169.254.169.254/latest/meta-data/', 'privateaddress'],
            'cgnat metadata' => ['http://100.100.100.200/', 'privateaddress'],
            'decimal ip' => ['http://2130706433/', 'invalidhost'],
            'hex ip' => ['http://0x7f.0x0.0x0.0x1/', 'invalidhost'],
            'short ip' => ['http://127.1/', 'invalidhost'],
            'localhost' => ['http://localhost/', 'invalidhost'],
            'localhost subdomain' => ['http://app.localhost/', 'privateaddress'],
            'gcp metadata host' => ['http://metadata.google.internal/', 'privateaddress'],
            'credentials' => ['https://user:secret@example.com/', 'credentials'],
            'port' => ['http://example.com:22/', 'port'],
            'dns to private' => ['https://intranet.example.org/', 'privateaddress'],
            'mixed dns answer' => ['https://rebind.example.net/', 'privateaddress'],
            'dns to v6 loopback' => ['https://sixonly.example.net/', 'privateaddress'],
            'dns to metadata' => ['https://metadata-alias.example.net/', 'privateaddress'],
            'dns to cgnat' => ['https://cgnat.example.net/', 'privateaddress'],
            'unresolvable' => ['https://nowhere.example.invalid/', 'dns'],
        ];
    }

    /**
     * Unsafe URLs are blocked.
     *
     * @dataProvider blocked_provider
     * @param string $url
     * @param string $reason
     */
    public function test_blocked(string $url, string $reason): void {
        $result = $this->guard()->check($url);
        $this->assertFalse($result['ok'], $url);
        $this->assertSame($reason, $result['reason'], $url);
    }

    /**
     * Public URLs are allowed and resolved addresses returned for pinning.
     */
    public function test_allowed_and_pinned(): void {
        $this->resetAfterTest();
        // Disable the core host blocklist here so the core helper performs no real DNS lookups.
        set_config('curlsecurityblockedhosts', '');
        $result = $this->guard()->check('https://example.com/page?x=1');
        $this->assertTrue($result['ok']);
        $this->assertSame(['93.184.215.14'], $result['ips']);
        $this->assertSame(443, $result['port']);

        $helper = new security_helper($this->guard());
        $this->assertFalse($helper->url_is_blocked('https://example.com/page'));
        $this->assertSame(['example.com:443:93.184.215.14'], $helper->get_resolve_info());
        $this->assertTrue($helper->url_is_blocked('https://intranet.example.org/'));
        $this->assertSame('privateaddress', $helper->lastreason);
    }

    /**
     * The site's own cURL blocklist is still applied.
     */
    public function test_site_blocklist_respected(): void {
        $this->resetAfterTest();
        set_config('curlsecurityblockedhosts', 'example.com');
        $helper = new security_helper($this->guard());
        $this->assertTrue($helper->url_is_blocked('https://example.com/'));
        $this->assertSame('siteblocked', $helper->lastreason);
    }

    /**
     * Public IP detection.
     */
    public function test_is_public_ip(): void {
        $this->assertTrue(url_guard::is_public_ip('93.184.215.14'));
        $this->assertTrue(url_guard::is_public_ip('2606:4700:4700::1111'));
        foreach (
            ['127.0.0.1', '0.0.0.0', '10.0.0.1', '172.16.5.4', '192.168.0.1', '169.254.169.254', '100.64.0.1',
                '::1', 'fe80::1', 'fd00::1', '::ffff:10.0.0.1', '64:ff9b::a00:1', '224.0.0.1', 'not-an-ip'] as $ip
        ) {
            $this->assertFalse(url_guard::is_public_ip($ip), $ip);
        }
    }

    /**
     * Relative redirects are resolved against the current URL.
     */
    public function test_absolute_url(): void {
        $this->assertSame('https://a.example/x/y', http_fetcher::absolute_url('https://a.example/x/z', 'y'));
        $this->assertSame('https://a.example/y', http_fetcher::absolute_url('https://a.example/x/z', '/y'));
        $this->assertSame('https://b.example/', http_fetcher::absolute_url('https://a.example/', '//b.example/'));
        $this->assertSame('http://169.254.169.254/', http_fetcher::absolute_url('https://a.example/', 'http://169.254.169.254/'));
        // The absolute result is validated again by the guard before any request.
        $this->assertFalse($this->guard()->check('http://169.254.169.254/')['ok']);
    }

    /**
     * HTTP outcomes are classified conservatively.
     */
    public function test_classifier(): void {
        $r = fn(int $code, int $errno = 0, string $blocked = '') => ['code' => $code, 'errno' => $errno,
            'blocked' => $blocked, 'redirects' => 0];
        $this->assertSame('available', link_classifier::classify($r(200), 0, 2)['status']);
        $this->assertSame('restricted', link_classifier::classify($r(401), 0, 2)['status']);
        $this->assertSame('restricted', link_classifier::classify($r(403), 0, 2)['status']);
        $this->assertSame('ratelimited', link_classifier::classify($r(429), 0, 2)['status']);
        $this->assertSame('unknown', link_classifier::classify($r(503), 0, 2)['status']);
        $first = link_classifier::classify($r(404), 0, 2);
        $this->assertSame('unknown', $first['status']);
        $this->assertSame('notfoundpending', $first['reason']);
        $this->assertSame(1, $first['failures']);
        $second = link_classifier::classify($r(404), 1, 2);
        $this->assertSame('unavailable', $second['status']);
        $this->assertSame('unknown', link_classifier::classify($r(0, 6), 0, 2)['status']);
        $this->assertSame('unavailable', link_classifier::classify($r(0, 6), 1, 2)['status']);
        $this->assertSame('unknown', link_classifier::classify($r(0, 28), 0, 2)['status']);
        $blocked = link_classifier::classify($r(0, 0, 'privateaddress'), 0, 2);
        $this->assertSame('unknown', $blocked['status']);
        $this->assertSame('blocked_privateaddress', $blocked['reason']);
        $this->assertTrue(link_classifier::should_retry_with_get($r(405)));
        $this->assertFalse(link_classifier::should_retry_with_get($r(200)));
        $this->assertFalse(link_classifier::should_retry_with_get($r(0, 0, 'privateaddress')));
    }

    /**
     * Vimeo parsing preserves privacy hashes; redaction removes them.
     */
    public function test_vimeo(): void {
        $this->assertSame(['id' => '123456789', 'hash' => 'abcdef1234'], vimeo::parse('https://vimeo.com/123456789/abcdef1234'));
        $this->assertSame(
            ['id' => '42', 'hash' => 'abc123def0'],
            vimeo::parse('https://player.vimeo.com/video/42?h=abc123def0&amp;badge=0')
        );
        $this->assertSame(['id' => '42', 'hash' => ''], vimeo::parse('//player.vimeo.com/video/42'));
        $this->assertSame(['id' => '7', 'hash' => ''], vimeo::parse('https://vimeo.com/channels/staffpicks/7'));
        $this->assertNull(vimeo::parse('https://example.com/123'));
        $this->assertSame('https://vimeo.com/42/abc123def0', vimeo::canonical_url('42', 'abc123def0'));
        $redacted = vimeo::redact_text('see https://vimeo.com/42/abc123def0 and https://player.vimeo.com/video/42?h=abc123def0');
        $this->assertStringNotContainsString('abc123def0', $redacted);
        $this->assertStringContainsString('vimeo.com/42/', $redacted);
    }

    /**
     * Fake fetcher returning canned responses by URL prefix, recording requests.
     *
     * @param int[] $codes HTTP status by URL prefix.
     * @return http_fetcher
     */
    private function fake_fetcher(array $codes): http_fetcher {
        return new class (new url_guard(fn() => ['93.184.216.34']), $codes) extends http_fetcher {
            /** @var string[] Requested URLs. */
            public array $requested = [];

            /** @var array Status codes by URL. */
            private array $codes;

            /**
             * Constructor.
             *
             * @param url_guard $guard
             * @param array $codes
             */
            public function __construct(url_guard $guard, array $codes) {
                parent::__construct($guard);
                $this->codes = $codes;
            }

            /**
             * Canned fetch.
             *
             * @param string $url
             * @param string $method
             * @param array $extraheaders
             * @return array
             */
            public function fetch(string $url, string $method = 'HEAD', array $extraheaders = []): array {
                $this->requested[] = $url;
                foreach ($this->codes as $prefix => $code) {
                    if (str_starts_with($url, $prefix)) {
                        return ['code' => $code, 'errno' => 0, 'blocked' => '', 'finalurl' => $url, 'headers' => [],
                            'body' => $code === 200 ? '{"title":"Pulse taking"}' : ''];
                    }
                }
                return ['code' => 0, 'errno' => 7, 'blocked' => '', 'finalurl' => $url, 'headers' => [], 'body' => ''];
            }
        };
    }

    /**
     * Domain-restricted Vimeo videos are reported as restricted or available, never as broken or server errors.
     */
    public function test_vimeo_domain_restricted(): void {
        $oembed = 'https://vimeo.com/api/oembed.json';
        $player = 'https://player.vimeo.com/video/877440659';

        // The oEmbed endpoint answers 500 for a domain-restricted video; the player refuses this site.
        $fetcher = $this->fake_fetcher([$oembed => 500, $player => 403]);
        $result = vimeo::check($fetcher, '877440659', '', 0, 2);
        $this->assertSame(link_classifier::RESTRICTED, $result['status']);
        $this->assertSame('vimeodomain', $result['reason']);
        $this->assertSame($player, $fetcher->requested[1]);

        // The player plays for this site.
        $result = vimeo::check($this->fake_fetcher([$oembed => 404, $player => 200]), '877440659', '', 0, 2);
        $this->assertSame(link_classifier::AVAILABLE, $result['status']);
        $this->assertSame('vimeoplayerok', $result['reason']);

        // Public video: oEmbed works and the player is not requested.
        $fetcher = $this->fake_fetcher([$oembed => 200]);
        $result = vimeo::check($fetcher, '1', 'abcdef1234', 0, 2);
        $this->assertSame(link_classifier::AVAILABLE, $result['status']);
        $this->assertSame('Pulse taking', $result['metadata']['title']);
        $this->assertCount(1, $fetcher->requested);

        // Deleted video: both say not found; never confirmed without the API, so not "unavailable".
        $result = vimeo::check($this->fake_fetcher([$oembed => 404, $player => 404]), '2', '', 5, 2);
        $this->assertSame(link_classifier::UNKNOWN, $result['status']);

        $this->assertSame('https://player.vimeo.com/video/42?h=abc123def0', vimeo::player_url('42', 'abc123def0'));
    }
}
