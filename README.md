# CPF Service
Serviço para consultar a situação cadastral no CPF - RF

## Instalação
`composer require wesleydeveloper/cpf-service`

## Uso
Necessita de uma chave da API [2Captcha](https://2captcha.com)
```php
<?php

require __DIR__ . './vendor/autoload.php';

use Wesleydeveloper\CPFService\CPFService;

$twoCaptchaKey = ''; // API KEY https://2captcha.com
$cpfService = new CPFService($twoCaptchaKey);

$cpf = ''; // CPF para pesquisa (com ou sem pontuação)
$dataNasc = ''; // data de nascimento no formato br d/m/Y
try{
    $cpfService->check($cpf, $dataNasc);
}catch (\Exception $e){
    die($e->getMessage());
}
/*
 $cpfService->check($cpf, $dataNasc) retorna um beloano
 $cpfService->getResult() retorna um array
*/
```
["2Captcha"]: https://2captcha.com