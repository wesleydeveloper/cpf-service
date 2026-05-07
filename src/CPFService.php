<?php


namespace Wesleydeveloper\CPFService;

use Exception;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Wesleydeveloper\CPFService\DTO\CpfResultDTO;
use Wesleydeveloper\CPFService\Exception\ReceitaFederalConnectionException;
use Wesleydeveloper\CPFService\Exception\InvalidCpfException;
use Wesleydeveloper\CPFService\Exception\InvalidCaptchaException;
use Wesleydeveloper\CPFService\Exception\InvalidDateOfBirthException;
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
     * @var ?CpfResultDTO
     */
    private ?CpfResultDTO $result;

    /**
     * @var array
     */
    private array $keys;

    private ?string $proxyUrl;

    public function __construct(string $twoCaptchaKey, ?string $proxyUrl = null, ?HttpBrowser $browser = null)
    {
        $this->proxyUrl = $proxyUrl;

        $this->twoCaptcha = new TwoCaptcha([
            'apiKey' => $twoCaptchaKey,
            'softId' => 2999
        ]);
        
        if ($browser === null) {
            $this->setProxyUrl($proxyUrl);
        } else {
            $this->browser = $browser;
        }

        $this->params = [
            'idCheckedReCaptcha' => 'false',
            'Enviar' => 'Consultar'
        ];
        $this->result = null;
        $this->keys = [
            'numero',
            'nome',
            'dataNasc',
            'situacao',
            'dataInsc',
            'digVerificador'
        ];
    }

    public function setProxyUrl(?string $proxyUrl): void
    {
        $this->proxyUrl = $proxyUrl;
        $options = [];
        if ($this->proxyUrl) {
            $options['proxy'] = $this->proxyUrl;
        }
        $this->browser = new HttpBrowser(new \Symfony\Component\HttpClient\NativeHttpClient($options));
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
        $this->sendRequest('GET', '/ConsultaPublica.asp');

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

        $crawler = $this->sendRequest('POST', '/ConsultaPublicaExibir.asp', $this->params);

        $this->validateResponse($crawler, $dataNasc);

        $this->serializeResponse($crawler);

        return $this->result !== null;
    }

    /**
     * @return ?CpfResultDTO
     */
    public function getResult(): ?CpfResultDTO
    {
        return $this->result;
    }

    /**
     * @return string
     * @throws Exception
     */
    private function getSiteKey(): string
    {
        $crawler = $this->sendRequest('GET', '/ConsultaPublica.asp');
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
        $data = [];
        $crawler->filter('.clConteudoDados b')->each(function ($item, $i) use (&$data) {
            $value = trim($item->text());
            $key = !empty($this->keys[$i]) ? $this->keys[$i] : $i;
            $data[$key] = !empty($value) ? $value : '';
        });

        if (count($data) >= 6) {
            $this->result = new CpfResultDTO(
                $data['numero'],
                $data['nome'],
                $data['dataNasc'],
                $data['situacao'],
                $data['dataInsc'],
                $data['digVerificador']
            );
        }
    }

    /**
     * @throws ReceitaFederalConnectionException
     */
    private function sendRequest(string $method, string $path, array $parameters = []): Crawler
    {
        try {
            return $this->browser->request($method, self::BASE_URI . $path, $parameters);
        } catch (TransportExceptionInterface $e) {
            throw new ReceitaFederalConnectionException('Erro de conexão com a Receita Federal: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws InvalidCpfException
     * @throws InvalidCaptchaException
     * @throws InvalidDateOfBirthException
     */
    private function validateResponse(Crawler $crawler, string $dataNasc): void
    {
        $errorMessage = '';

        if ($crawler->filter('#idMensagemErro')->count() > 0) {
            $errorMessage = trim($crawler->filter('#idMensagemErro')->text());
        } elseif ($crawler->filter('div.clConteudoCentro h4')->count() > 0) {
            $errorMessage = trim($crawler->filter('div.clConteudoCentro h4')->text());
        }

        if (str_contains($errorMessage, 'CPF incorreto')) {
            throw new InvalidCpfException('CPF inválido. Informe um cpf válido existente');
        }

        if (str_contains($errorMessage, 'Anti-Robô')) {
            throw new InvalidCaptchaException('Token hCaptcha inválido ou expirado');
        }

        if (str_contains($errorMessage, 'Data de nascimento informada ' . $dataNasc . ' está divergente')) {
            throw new InvalidDateOfBirthException('A data de nascimento informada ' . $dataNasc . ' está divergente da constante na base de dados da Secretaria da Receita Federal.');
        }
    }
}