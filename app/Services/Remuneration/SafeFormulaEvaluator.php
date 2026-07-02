<?php

namespace App\Services\Remuneration;

use InvalidArgumentException;

class SafeFormulaEvaluator
{
    /**
     * @param  array<string, int|float|string|null>  $variables
     */
    public function evaluate(?string $expression, array $variables): float
    {
        $expression = trim((string) $expression);
        if ($expression === '') {
            return 0.0;
        }

        $tokens = $this->tokenize($expression);
        $rpn = $this->toReversePolishNotation($tokens);

        return $this->evaluateReversePolishNotation($rpn, $variables);
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $expression): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($expression);

        while ($offset < $length) {
            if (preg_match('/\G\s+/A', $expression, $match, 0, $offset)) {
                $offset += strlen($match[0]);
                continue;
            }

            if (preg_match('/\G([A-Za-z_][A-Za-z0-9_]*|\d+(?:\.\d+)?|[()+\-*\/])/A', $expression, $match, 0, $offset)) {
                $tokens[] = $match[1];
                $offset += strlen($match[1]);
                continue;
            }

            throw new InvalidArgumentException('La formula contiene caracteres no permitidos.');
        }

        return $tokens;
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array<int, string>
     */
    private function toReversePolishNotation(array $tokens): array
    {
        $output = [];
        $operators = [];
        $previous = null;
        $precedence = ['u-' => 3, '*' => 2, '/' => 2, '+' => 1, '-' => 1];

        foreach ($tokens as $token) {
            if ($this->isNumber($token) || $this->isIdentifier($token)) {
                $output[] = $token;
                $previous = 'value';
                continue;
            }

            if ($token === '(') {
                $operators[] = $token;
                $previous = '(';
                continue;
            }

            if ($token === ')') {
                while ($operators !== [] && end($operators) !== '(') {
                    $output[] = array_pop($operators);
                }

                if ($operators === []) {
                    throw new InvalidArgumentException('La formula tiene parentesis desbalanceados.');
                }

                array_pop($operators);
                $previous = 'value';
                continue;
            }

            $operator = ($token === '-' && ($previous === null || $previous === '(' || $previous === 'operator')) ? 'u-' : $token;
            if (!array_key_exists($operator, $precedence)) {
                throw new InvalidArgumentException('La formula contiene un operador no permitido.');
            }

            while ($operators !== [] && end($operators) !== '(' && $precedence[end($operators)] >= $precedence[$operator]) {
                $output[] = array_pop($operators);
            }

            $operators[] = $operator;
            $previous = 'operator';
        }

        while ($operators !== []) {
            $operator = array_pop($operators);
            if ($operator === '(') {
                throw new InvalidArgumentException('La formula tiene parentesis desbalanceados.');
            }
            $output[] = $operator;
        }

        return $output;
    }

    /**
     * @param  array<int, string>  $rpn
     * @param  array<string, int|float|string|null>  $variables
     */
    private function evaluateReversePolishNotation(array $rpn, array $variables): float
    {
        $stack = [];

        foreach ($rpn as $token) {
            if ($this->isNumber($token)) {
                $stack[] = (float) $token;
                continue;
            }

            if ($this->isIdentifier($token)) {
                if (!array_key_exists($token, $variables)) {
                    throw new InvalidArgumentException("Variable no permitida en formula: {$token}");
                }
                $stack[] = (float) ($variables[$token] ?? 0);
                continue;
            }

            if ($token === 'u-') {
                if ($stack === []) {
                    throw new InvalidArgumentException('Formula invalida.');
                }
                $stack[] = -1 * array_pop($stack);
                continue;
            }

            if (count($stack) < 2) {
                throw new InvalidArgumentException('Formula invalida.');
            }

            $right = array_pop($stack);
            $left = array_pop($stack);

            $stack[] = match ($token) {
                '+' => $left + $right,
                '-' => $left - $right,
                '*' => $left * $right,
                '/' => abs($right) < 0.000001 ? 0 : $left / $right,
                default => throw new InvalidArgumentException('Operador no permitido.'),
            };
        }

        if (count($stack) !== 1) {
            throw new InvalidArgumentException('Formula invalida.');
        }

        return (float) $stack[0];
    }

    private function isNumber(string $token): bool
    {
        return (bool) preg_match('/^\d+(?:\.\d+)?$/', $token);
    }

    private function isIdentifier(string $token): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $token);
    }
}
