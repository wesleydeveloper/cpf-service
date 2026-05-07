<?php
require 'vendor/autoload.php';
use Symfony\Component\HttpClient\NativeHttpClient;

$options = [
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ]
];
$client = new NativeHttpClient($options);
try {
    $response = $client->request('GET', 'https://servicos.receita.fazenda.gov.br/Servicos/CPF/ConsultaSituacao/ConsultaPublica.asp');
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Tamanho: " . strlen($response->getContent(false)) . "\n";
} catch (\Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
