<?php


namespace Wesleydeveloper\CPFService;

use Exception;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use TwoCaptcha\Exception\ApiException;
use TwoCaptcha\Exception\NetworkException;
use TwoCaptcha\Exception\TimeoutException;
use TwoCaptcha\Exception\ValidationException;
use TwoCaptcha\TwoCaptcha;

class CPFService
{
    private const BASE_URI = 'https://servicos.receita.fazenda.gov.br/Servicos/CPF/ConsultaSituacao';

    /**
     * @var HttpBrowser;
     */
    private HttpBrowser $browser;

    /**
     * @var TwoCaptcha
     */
    private TwoCaptcha $twoCaptcha;

    /**
     * @var array
     */
    private array $params;

    /**
     * @var array
     */
    private array $result;

    /**
     * @var array
     */
    private array $keys;

    public function __construct(string $twoCaptchaKey, ?HttpBrowser $browser = null)
    {
        $this->twoCaptcha = new TwoCaptcha([
            'apiKey' => $twoCaptchaKey,
            'softId' => 2999
        ]);
        
        if ($browser === null) {
            $options = [];
            $proxyUrl = getenv('PROXY_URL');
            if ($proxyUrl) {
                $options['proxy'] = $proxyUrl;
            }
            // Utilizamos o NativeHttpClient para contornar o bug "unexpected eof while reading" do OpenSSL 3 no cURL
            $browser = new HttpBrowser(new \Symfony\Component\HttpClient\NativeHttpClient($options));
        }

        $this->browser = $browser;

        $this->params = [
            'idCheckedReCaptcha' => 'false',
            'Enviar' => 'Consultar'
        ];
        $this->result = [];
        $this->keys = [
            'numero',
            'nome',
            'dataNasc',
            'situacao',
            'dataInsc',
            'digVerificador'
        ];
    }

    /**
     * @param string $cpf
     * @param string $dataNasc
     * @param string|null $token
     * @param string|null $http_user_agent
     * @return bool
     * @throws ApiException
     * @throws NetworkException
     * @throws TimeoutException
     * @throws ValidationException
     * @throws Exception
     */
    public function check(string $cpf, string $dataNasc, ?string $token = null, ?string $http_user_agent = null): bool
    {
        try {
            $this->browser->request('GET', self::BASE_URI . '/ConsultaPublica.asp');
        } catch (TransportExceptionInterface $e) {
            throw new Exception('Erro de conexão com a Receita Federal (Possível bloqueio de firewall ou erro SSL/TLS): ' . $e->getMessage(), 0, $e);
        }

        $this->browser->setServerParameters([
            'HTTP_USER_AGENT' => $http_user_agent ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'HTTP_REFERER' => self::BASE_URI . '/ConsultaPublica.asp',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'HTTP_ACCEPT_LANGUAGE' => 'pt-BR,pt;q=0.9,en;q=0.8',
        ]);

        $this->params['txtCPF'] = $cpf;

        $this->params['txtDataNascimento'] = $dataNasc;

        if (!$token) {
            $this->resolveCaptcha();
        } else {
            $this->params['h-captcha-response'] = $token;
            $this->params['idCheckedReCaptcha'] = 'false';
        }

        try {
            $crawler = $this->browser->request('POST', self::BASE_URI . '/ConsultaPublicaExibir.asp', $this->params);
        } catch (TransportExceptionInterface $e) {
            throw new Exception('Erro de conexão com a Receita Federal (Possível bloqueio de firewall ou erro SSL/TLS): ' . $e->getMessage(), 0, $e);
        }

        $errorMessage = '';

        if ($crawler->filter('#idMensagemErro')->count() > 0) {
            $errorMessage = trim($crawler->filter('#idMensagemErro')->text());
        } elseif ($crawler->filter('div.clConteudoCentro h4')->count() > 0) {
            $errorMessage = trim($crawler->filter('div.clConteudoCentro h4')->text());
        }

        if (str_contains($errorMessage, 'CPF incorreto')) {
            throw new Exception('CPF inválido. Informe um cpf válido existente');
        }

        if (str_contains($errorMessage, 'Anti-Robô')) {
            throw new Exception('Token hCaptcha inválido ou expirado');
        }

        if (str_contains($errorMessage, 'Data de nascimento informada ' . $dataNasc . ' está divergente')) {
            throw new Exception('A data de nascimento informada ' . $dataNasc . ' está divergente da constante na base de dados da Secretaria da Receita Federal.');
        }

        $this->serializeResponse($crawler);

        return count($this->result) > 0;
    }

    /**
     * @return array
     */
    public function getResult(): array
    {
        return $this->result;
    }

    /**
     * @return string
     * @throws Exception
     */
    private function getSiteKey(): string
    {
        try {
            $crawler = $this->browser->request('GET', self::BASE_URI . '/ConsultaPublica.asp');
        } catch (TransportExceptionInterface $e) {
            throw new Exception('Erro de conexão com a Receita Federal (Possível bloqueio de firewall ou erro SSL/TLS): ' . $e->getMessage(), 0, $e);
        }
        $siteKey = $crawler->filter('.h-captcha')->attr('data-sitekey');
        if (is_null($siteKey)) throw new Exception('Site key is null');
        return $siteKey;
    }

    /**
     * @throws ApiException
     * @throws NetworkException
     * @throws TimeoutException
     * @throws ValidationException
     * @throws Exception
     */
    private function resolveCaptcha(): void
    {
        set_time_limit(610);
        $reCaptcha = $this->twoCaptcha->hcaptcha([
            'sitekey' => $this->getSiteKey(),
            'url' => self::BASE_URI . '/ConsultaPublica.asp'
        ]);
        $this->params['h-captcha-response'] = $reCaptcha->code;
    }

    private function serializeResponse(Crawler $crawler): void
    {
        $crawler->filter('.clConteudoDados b')->each(function ($item, $i) {
            $value = trim($item->text());
            $key = !empty($this->keys[$i]) ? $this->keys[$i] : $i;
            $this->result[$key] = !empty($value) ? $value : '';
        });
    }
}