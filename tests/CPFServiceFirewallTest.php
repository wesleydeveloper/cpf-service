<?php

namespace Wesleydeveloper\CPFService\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Exception\TransportException;
use Wesleydeveloper\CPFService\CPFService;
use Exception;

class CPFServiceFirewallTest extends TestCase
{
    public function test_it_handles_firewall_or_ssl_block_gracefully(): void
    {
        // Mocking the HttpClient to throw a TransportException, simulating a firewall/SSL EOF block
        $mockHttpClient = new MockHttpClient(function ($method, $url, $options) {
            throw new TransportException('OpenSSL SSL_read: OpenSSL/3.5.4: error:0A000126:SSL routines::unexpected eof while reading');
        });

        $browser = new HttpBrowser($mockHttpClient);

        // Inject the mocked browser into the CPFService
        $service = new CPFService('fake-captcha-key', $browser);

        // Assert that the specific firewall exception is thrown
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Possível bloqueio de firewall ou erro SSL/TLS');

        $service->check('11111111111', '01011990', 'fake-token');
    }
}
