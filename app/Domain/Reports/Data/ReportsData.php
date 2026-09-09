<?php

declare(strict_types=1);

namespace App\Domain\Reports\Data;

use App\Domain\Reports\Actions\BuildReportsAction;
use App\Domain\Shared\Data\BaseData;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Everything the reports page needs for one filter, built by
 * {@see BuildReportsAction}. `from`/`to` are the
 * effective range (the filter's dates or the granularity's default),
 * `previous_from`/`previous_to` the range the totals are compared with.
 */
#[TypeScript]
class ReportsData extends BaseData
{
    /**
     * @param  Collection<int, ReportBucketData>  $buckets
     * @param  Collection<int, ReportBreakdownRowData>  $clients
     * @param  Collection<int, ReportBreakdownRowData>  $projects
     */
    public function __construct(
        public string $granularity,
        public string $from,
        public string $to,
        public string $previous_from,
        public string $previous_to,
        /** Date of the oldest entry that matches the filter; null without entries. */
        public ?string $earliest_entry_on,
        #[DataCollectionOf(ReportBucketData::class)]
        public Collection $buckets,
        public ReportTotalsData $totals,
        #[DataCollectionOf(ReportBreakdownRowData::class)]
        public Collection $clients,
        #[DataCollectionOf(ReportBreakdownRowData::class)]
        public Collection $projects,
    ) {}
}
