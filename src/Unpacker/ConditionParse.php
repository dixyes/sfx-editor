<?php

declare(strict_types=1);

namespace Unpacker;

require_once __DIR__ . '/utilFunctions.php';

/**
 * Condition parser, parse condition string to AST
 * 
 * PEG of cond
 * cond(start) := expr
 * expr := logical
 * logical := binary (('&&' / '||') binary)* # 和C不太一样，逻辑运算算作同一优先级
 * binary := cmp (('&' / '|' / '^') cmp)* # 和C不太一样，位运算算作同一优先级
 * cmp := shift (('==' / '!=' / '<' / '<=' / '>' / '>=') shift)* # 和C不太一样，比较算作同一优先级
 * shift := sum (('<<' / '>>') sum)*
 * sum := product (('+' / '-') product)*
 * product := not (('*' / '/' / '%') not)*
 * not := ('!' / '~')? subscript / subscript
 * subscript := value ('[' expr ']')?
 * value := NUMBER / STRING / var / '(' expr ')'
 * var := '$data' / '$rem' / '$off' / '$this->' IDENT
 * 
 * @todo 改一下tokenizer来实现能看的报错
 */
final class ConditionParse
{

    /**
     * @var false|array{'type': string, 'literal': ?string, 'value': ?mixed}
     */
    private array|false $token;
    private function __construct(
        private string $cond,
        private Iterable $tokens,
    )
    {
        reset($this->tokens);
        $this->nextToken();
    }

    public static function parse(string $cond): array
    {
        $tokens = tokenizeCond($cond);
        $parser = new self($cond, $tokens);
        return $parser->cond();
    }

    private function nextToken(): void
    {
        $this->token = current($this->tokens);
        if ($this->token === false) {
            $this->token = ['type' => 'eof'];
            return;
        }
        next($this->tokens);
        var_dump($this->token);
    }

    /**
     * parse current token as cond
     * cond(start) := expr
     * 
     * @return array
     * @throws \Exception if not match
     */
    private function cond(): array
    {
        if (($ret = $this->expr())) {
            return $ret;
        }
        if ($this->token['type'] !== 'eof') {
            throw new \Exception('expect eof');
        }
        throw new \Exception('not match');
    }

    /**
     * parse current token as expr
     * expr := logical
     * 
     * @return ?array
     */
    private function expr(): ?array
    {
        return $this->logical();
    }

    /**
     * parse current token as logical
     * logical := binary (('&&' / '||') binary)*
     * 
     * @return ?array
     */
    private function logical(): ?array
    {
        $binary = $this->binary();
        if (!$binary) {
            return null;
        }

        $ret = $binary;
        while (in_array($this->token['type'], ['&&', '||'])) {
            $op = $this->token['type'];
            $this->nextToken();
            $binary2 = $this->binary();
            if (!$binary2) {
                throw new \Exception('expect binary');
            }
            $ret = [$op, $ret, $binary2];
        }
        return $ret;
    }

    /**
     * parse current token as binary
     * binary := cmp (('&' / '|' / '^') cmp)*
     * 
     * @return ?array
     */
    function binary(): ?array
    {
        $cmp = $this->cmp();
        if (!$cmp) {
            return null;
        }

        $ret = $cmp;
        while (in_array($this->token['type'], ['&', '|', '^'])) {
            $op = $this->token['type'];
            $this->nextToken();
            $cmp2 = $this->cmp();
            if (!$cmp2) {
                throw new \Exception('expect cmp');
            }
            $ret = [$op, $ret, $cmp2];
        }
        return $ret;
    }

    /**
     * parse current token as cmp
     * cmp := shift (('==' / '!=' / '<' / '<=' / '>' / '>=') shift)*
     * 
     * @return ?array
     */
    function cmp(): ?array
    {
        $shift = $this->shift();
        if (!$shift) {
            return null;
        }

        $ret = $shift;
        while (in_array($this->token['type'], ['==', '!=', '<', '<=', '>', '>='])) {
            $op = $this->token['type'];
            $this->nextToken();
            $shift2 = $this->shift();
            if (!$shift2) {
                throw new \Exception('expect shift');
            }
            $ret = [$op, $ret, $shift2];
        }
        return $ret;
    }

    /**
     * parse current token as shift
     * shift := sum (('<<' / '>>') sum)*
     * 
     * @return ?array
     */
    function shift(): ?array
    {
        $sum = $this->sum();
        if (!$sum) {
            return null;
        }

        $ret = $sum;
        while (in_array($this->token['type'], ['<<', '>>'])) {
            $op = $this->token['type'];
            $this->nextToken();
            $sum2 = $this->sum();
            if (!$sum2) {
                throw new \Exception('expect sum');
            }
            $ret = [$op, $ret, $sum2];
        }
        return $ret;
    }

    /**
     * parse current token as sum
     * sum := product (('+' / '-') product)*
     * 
     * @return ?array
     */
    function sum(): ?array
    {
        $product = $this->product();
        if (!$product) {
            return null;
        }

        $ret = $product;
        while (in_array($this->token['type'], ['+', '-'])) {
            $op = $this->token['type'];
            $this->nextToken();
            $product2 = $this->product();
            if (!$product2) {
                throw new \Exception('expect product');
            }
            $ret = [$op, $ret, $product2];
        }
        return $ret;
    }

    /**
     * parse current token as product
     * product := not (('*' / '/' / '%') not)*
     * 
     * @return ?array
     */
    function product(): ?array
    {
        $not = $this->not();
        if (!$not) {
            return null;
        }

        $ret = $not;
        while (in_array($this->token['type'], ['*', '/', '%'])) {
            $op = $this->token['type'];
            $this->nextToken();
            $not2 = $this->not();
            if (!$not2) {
                throw new \Exception('expect not');
            }
            $ret = [$op, $ret, $not2];
        }
        return $ret;
    }

    /**
     * parse current token as not
     * not := ('!' / '~')? subscript
     * 
     * @return ?array
     */
    function not(): ?array
    {
        $op = null;
        if (in_array($this->token['type'], ['!', '~'])) {
            $op = $this->token['type'];
            $this->nextToken();
        }
        $subscript = $this->subscript();
        if (!$subscript) {
            throw new \Exception('expect subscript');
        }
        if ($op) {
            return [$op, $subscript];
        }
        return $subscript;
    }

    /**
     * parse current token as subscript
     * subscript := value ('[' expr ']')?
     * 
     * @return ?array
     */
    function subscript(): ?array
    {
        $value = $this->value();
        if (!$value) {
            return null;
        }

        if ($this->token['type'] === '[') {
            $this->nextToken();
            $expr = $this->expr();
            if (!$expr) {
                throw new \Exception('expect expr');
            }
            if ($this->token['type'] !== ']') {
                throw new \Exception('expect ]');
            }
            $this->nextToken();
            return ['[]', $value, $expr];
        }
        return $value;
    }

    /**
     * parse current token as value
     * value := NUMBER / STRING / BOOLEAN / var / '(' expr ')'
     * 
     * @return ?array
     */
    function value(): ?array
    {
        if (in_array($this->token['type'] ,['number','string'])) {
            $ret = ['val', $this->token['value']];
            $this->nextToken();
            return $ret;
        } else if (in_array($this->token['type'] ,['true','false'])) {
            $ret = ['val', (boolean)$this->token['type']];
            $this->nextToken();
            return $ret;
        } else if (($ret = $this->var())) {
            return $ret;
        } else if ($this->token['type'] === '(') {
            $this->nextToken();
            $expr = $this->expr();
            if (!$expr) {
                throw new \Exception('expect expr');
            }
            if ($this->token['type'] !== ')') {
                throw new \Exception('expect )');
            }
            $this->nextToken();
            return $expr;
        }
        return null;
    }

    /**
     * parse current token as var
     * var := '$data' / '$rem' / '$off' / '$this->' IDENT
     * 
     * @return ?array
     */
    function var(): ?array
    {
        if (in_array($this->token['type'], ['$data', '$rem', '$off'])) {
            $ret = ['var', $this->token['type']];
            $this->nextToken();
            return $ret;
        } else if ($this->token['type'] === '$this->') {
            $this->nextToken();
            if ($this->token['type'] !== 'identifier') {
                throw new \Exception('expect identifier');
            }
            $ret = ['prop', $this->token['literal']];
            $this->nextToken();
            return $ret;
        }
        return null;
    }
}