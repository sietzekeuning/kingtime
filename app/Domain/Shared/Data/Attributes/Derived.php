<?php

declare(strict_types=1);

namespace App\Domain\Shared\Data\Attributes;

use App\Domain\Shared\Data\BaseData;
use Attribute;

/**
 * Marks a DTO property that is read from the model (a count, a joined name)
 * but has no column of its own. {@see BaseData::toUpdateArray()}
 * leaves these out, so a DTO round-tripped through a form never tries to
 * write them back.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Derived {}
