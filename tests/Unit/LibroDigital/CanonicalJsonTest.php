<?php

namespace Tests\Unit\LibroDigital;

use App\Services\LibroDigital\CanonicalJson;
use PHPUnit\Framework\TestCase;

class CanonicalJsonTest extends TestCase
{
    public function test_object_keys_are_sorted_recursively_but_list_order_is_preserved(): void
    {
        $canonical = new CanonicalJson;

        $left = ['z' => 1, 'a' => ['y' => 2, 'b' => 3], 'items' => [['z' => 2, 'a' => 1], ['a' => 4]]];
        $right = ['items' => [['a' => 1, 'z' => 2], ['a' => 4]], 'a' => ['b' => 3, 'y' => 2], 'z' => 1];

        $this->assertSame($canonical->encode($left), $canonical->encode($right));
        $this->assertSame($canonical->hash($left), $canonical->hash($right));
    }
}
