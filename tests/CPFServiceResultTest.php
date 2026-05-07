<?php

namespace Wesleydeveloper\CPFService\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Wesleydeveloper\CPFService\CPFService;

class CPFServiceResultTest extends TestCase
{
    public function test_it_correctly_parses_the_result_from_receita_federal(): void
    {
        // Simulando a página de resultado da Receita Federal com os dados
        $htmlResponse = <<<HTML
        <html>
            <body>
                <div class="clConteudoDados">
                    <b>111.111.111-11</b>
                    <b>WESLEY SILVA</b>
                    <b>23/09/1991</b>
                    <b>REGULAR</b>
                    <b>01/01/2010</b>
                    <b>00</b>
                </div>
            </body>
        </html>
HTML;

        // O MockHttpClient vai retornar essas respostas na ordem que o CPFService requisitar
        $responses = [
            // 1ª Requisição: GET /ConsultaPublica.asp (Retorna o form e pega os cookies)
            new MockResponse('<html><body><div class="h-captcha" data-sitekey="fake-site-key"></div></body></html>'),
            // 2ª Requisição: POST /ConsultaPublicaExibir.asp (Retorna os dados do CPF)
            new MockResponse($htmlResponse)
        ];

        $mockHttpClient = new MockHttpClient($responses);
        $browser = new HttpBrowser($mockHttpClient);

        $service = new CPFService('fake-captcha-key', $browser);

        // Passando um "fake-token" ignoramos a chamada à API do 2captcha no teste
        $isValid = $service->check('11111111111', '23/09/1991', 'fake-token');

        $this->assertTrue($isValid);

        // Validação do getResult()
        $result = $service->getResult();

        $this->assertIsArray($result);
        $this->assertCount(6, $result);
        $this->assertEquals('111.111.111-11', $result['numero']);
        $this->assertEquals('WESLEY SILVA', $result['nome']);
        $this->assertEquals('23/09/1991', $result['dataNasc']);
        $this->assertEquals('REGULAR', $result['situacao']);
        $this->assertEquals('01/01/2010', $result['dataInsc']);
        $this->assertEquals('00', $result['digVerificador']);
    }
}
