<?php

declare(strict_types=1);

namespace App\Domain\Shared\Data\Contracts;

interface HasEnumLabels
{
    public function label(): string;

    public function colorClass(): string;
}
