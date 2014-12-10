<?php
/**
 * The Initial Developer of the Original Code is
 * Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * Portions created by the Initial Developer are
 * Copyright (C) 2014 Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * All Rights Reserved.
 *
 * Contributor(s):
 * 2014 Tarmo Alexander Sundström <ta@sundstrom.im>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 */

namespace Webvaloa\Helpers;

/*
 * Generate reference numbers in Finnish bank format.
 */

class Referencefi
{
    private $reference;

    public function __construct($n)
    {
        $this->calculateReference($n);
    }

    private function calculateReference($n)
    {
        $broken = str_split($n, 1);
        $broken = array_reverse($broken);
        $total = 0;
        $mod = 7;
        foreach ($broken as $k=>$v) {
            $total += ($v * $mod);
            if ($mod == 7) {
                $mod = 3;
            } elseif ($mod == 3) {
                $mod = 1;
            } elseif ($mod == 1) {
                $mod = 7;
            }
        }
        $final = 10 - substr($total, -1);
        if ($final == 10) {
            $final = 0;
        }
        $this->reference = $n . $final;
    }

    public function __toString()
    {
        return (string) $this->reference;
    }

    public static function numFormat($number)
    {
        $foo = str_split($number);
        $str = "";
        $i = 0;
        foreach ($foo as $k=>$v) {
            if ($i == 5) {
                $str .= " ";
                $i = 0;
            }
            $str .= $v;
            $i++;
        }

        return $str;
    }

}
