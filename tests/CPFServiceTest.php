<?php

namespace Wesleydeveloper\CPFService\Tests;

use PHPUnit\Framework\TestCase;
use Wesleydeveloper\CPFService\CPFService;

class CPFServiceTest extends TestCase
{
    public function test_can_instantiate_service(): void
    {
        $service = new CPFService('fake-token');
        $this->assertInstanceOf(CPFService::class, $service);
    }
}
