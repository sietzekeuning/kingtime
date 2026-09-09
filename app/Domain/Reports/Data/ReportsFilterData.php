<?php

declare(strict_types=1);

namespace App\Domain\Reports\Data;

use App\Domain\Reports\Actions\BuildReportsAction;
use App\Domain\Shared\Data\BaseData;
use Spatie\LaravelData\Attributes\Validation\AfterOrEqual;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\BuiltinTypeCast;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The query string of the reports page. Every field is optional: without
 * dates {@see BuildReportsAction} picks a range
 * that suits the granularity (last 12 months, last 26 weeks, every year
 * since the first entry).
 */
#[TypeScript]
class ReportsFilterData extends BaseData
{
    public const string DEFAULT_GRANULARITY = 'month';

    public function __construct(
        #[In('week', 'month', 'year')]
        public string $granularity = self::DEFAULT_GRANULARITY,
        #[Date]
        public ?string $from = null,
        #[Date, AfterOrEqual('from')]
        public ?string $to = null,
        #[Exists('clients', 'id'), WithCast(BuiltinTypeCast::class, 'int')]
        public ?int $client_id = null,
        #[Exists('projects', 'id'), WithCast(BuiltinTypeCast::class, 'int')]
        public ?int $project_id = null,
        #[WithCast(BuiltinTypeCast::class, 'bool')]
        public bool $billable_only = false,
    ) {}
}
