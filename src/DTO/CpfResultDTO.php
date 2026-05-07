<?php

namespace Wesleydeveloper\CPFService\DTO;

readonly class CpfResultDTO
{
    public function __construct(
        public string $numero,
        public string $nome,
        public string $dataNasc,
        public string $situacao,
        public string $dataInsc,
        public string $digVerificador
    ) {
    }

    public function toArray(): array
    {
        return [
            'numero' => $this->numero,
            'nome' => $this->nome,
            'dataNasc' => $this->dataNasc,
            'situacao' => $this->situacao,
            'dataInsc' => $this->dataInsc,
            'digVerificador' => $this->digVerificador,
        ];
    }
}
