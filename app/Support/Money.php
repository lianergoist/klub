<?php

namespace App\Support;

class Money
{
    public static function fmt(int $ore): string
    {
        return number_format($ore / 100, 2, ',', '.').' kr.';
    }
}
