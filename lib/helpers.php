<?php
declare(strict_types=1);

namespace Matrix\Helpers;

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
