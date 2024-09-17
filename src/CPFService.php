<?php


namespace Wesleydeveloper\CPFService;

use Exception;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use TwoCaptcha\Exception\ApiException;
use TwoCaptcha\Exception\NetworkException;
use TwoCaptcha\Exception\TimeoutException;
use TwoCaptcha\Exception\ValidationException;
use TwoCaptcha\TwoCaptcha;

class CPFService
{
    private const BASE_URI = 'https://servicos.receita.fazenda.gov.br/Servicos/CPF/ConsultaSituacao';

    /**
     * @var Client;
     */
    private Client $client;

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

    public function __construct(string $twoCaptchaKey)
    {
        $this->twoCaptcha = new TwoCaptcha([
            'apiKey'           => $twoCaptchaKey,
            'softId'           => 2999
        ]);
        $this->client = new Client();
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
     * @return bool
     * @throws ApiException
     * @throws NetworkException
     * @throws TimeoutException
     * @throws ValidationException
     */
    public function check(string $cpf, string $dataNasc): bool
    {
        try{
            $this->params['txtCPF'] = $cpf;
            $this->params['txtDataNascimento'] = $dataNasc;
            $this->resolveCaptcha();
            $crawler = $this->client->request('POST', self::BASE_URI . '/ConsultaPublicaExibir.asp', $this->params);
            $this->serializeResponse($crawler);
            return count($this->result) > 0;
        }catch (Exception $e){
            throw $e;
        }
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
            $crawler = $this->client->request('GET', self::BASE_URI . '/ConsultaPublica.asp');
            $siteKey = $crawler->filter('.h-captcha')->attr('data-sitekey');
            if(is_null($siteKey)) throw new Exception('Site key is null');
            return $siteKey;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @throws ApiException
     * @throws NetworkException
     * @throws TimeoutException
     * @throws ValidationException
     */
    private function resolveCaptcha(): void
    {
        try {
            set_time_limit(610);
            $reCaptcha = $this->twoCaptcha->hcaptcha([
                'sitekey' => $this->getSiteKey(),
                'url' => self::BASE_URI . '/ConsultaPublica.asp'
            ]);
            $this->params['h-captcha-response'] = $reCaptcha->code;
        } catch (Exception $e) {
            throw $e;
        }
    }

    private function serializeResponse(Crawler $crawler): void
    {
        $crawler->filter('.clConteudoDados b')->each(function ($item, $i){
            $value = trim($item->text());
            $key = !empty($this->keys[$i]) ? $this->keys[$i] : $i;
            $this->result[$key] = !empty($value) ? $value : '';
        });
    }
}