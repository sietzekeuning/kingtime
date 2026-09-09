<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Data;

use App\Domain\Dashboard\Actions\BuildDashboardAction;
use App\Domain\Shared\Data\BaseData;
use App\Domain\Time\Data\TimeEntryData;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Everything the dashboard page needs, built in one pass by
 * {@see BuildDashboardAction}. The controller
 * hands each section to Inertia as its own prop.
 */
#[TypeScript]
class DashboardData extends BaseData
{
    /**
     * @param  Collection<int, TimeEntryData>  $recent_entries
     * @param  Collection<int, DashboardProjectSummaryData>  $top_projects
     */
    public function __construct(
        public DashboardStatsData $stats,
        public DashboardChartData $chart,
        public DashboardStatisticsData $statistics,
        #[DataCollectionOf(TimeEntryData::class)]
        public Collection $recent_entries,
        #[DataCollectionOf(DashboardProjectSummaryData::class)]
        public Collection $top_projects,
    ) {}
}
