<?php

namespace App\Services\Remuneration\Payslips\Contracts;

interface PayslipParserInterface
{
    public function provider(): string;

    public function version(): string;

    /** @param array{text:string,tokens:array<int,array{x:float,y:float,text:string}>,page_number:int,extraction_method:string} $page */
    public function confidence(array $page): float;

    /**
     * @param  array{text:string,tokens:array<int,array{x:float,y:float,text:string}>,page_number:int,extraction_method:string}  $page
     * @return array<string,mixed>
     */
    public function parse(array $page): array;
}
