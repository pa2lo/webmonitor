<?php

namespace App\Services;

use App\Models\Website;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WebsiteStats
{
    private const CHECKPOINT = 'website_stats_completed_through';

    public function __construct(private readonly SettingsService $settings) {}

    private function rawDays(): Builder
    {
        return DB::table('attempts')->select('website_id')
            ->selectRaw("DATE(created_at) as date, COUNT(*) as attempt_count,
                SUM(CASE WHEN status = '200' THEN 1 ELSE 0 END) as success_count,
                COALESCE(SUM(duration), 0) as duration_sum_ms, COUNT(duration) as duration_count")
            ->groupBy('website_id')->groupByRaw('DATE(created_at)');
    }

    public function summarizeThroughYesterday(): string
    {
        $target = now()->subDay()->toDateString();
        do {
            $completed = DB::transaction(function () use ($target) {
                $previous = $this->settings->get(self::CHECKPOINT);
                if ($previous > $target) {
                    return $previous;
                }

                $date = min(CarbonImmutable::parse($previous)->addDay()->toDateString(), $target);

                $start = CarbonImmutable::parse($date);
                $rows = $this->rawDays()->where('created_at', '>=', $start)
                    ->where('created_at', '<', $start->addDay());
                DB::table('website_daily_stats')->where('date', $date)->delete();
                DB::table('website_daily_stats')->insertUsing([
                    'website_id', 'date', 'attempt_count', 'success_count', 'duration_sum_ms', 'duration_count',
                ], $rows);
                $this->settings->set(self::CHECKPOINT, $date);

                return $date;
            }, 5);
        } while ($completed < $target);

        return $completed;
    }

    private function days(Website $website, CarbonImmutable $end, ?CarbonImmutable $start = null): Collection
    {
        // Capture one boundary. A writer advancing it cannot change which side owns a date.
        $completed = $this->settings->get(self::CHECKPOINT);
        $days = collect();
        if ($completed !== null) {
            $days = DB::table('website_daily_stats')->where('website_id', $website->id)
                ->where('date', '<=', $completed)->where('date', '<', $end->toDateString())
                ->when($start, fn ($q) => $q->where('date', '>=', $start->toDateString()))
                ->orderBy('date')->get();
            $liveStart = CarbonImmutable::parse($completed)->addDay();
            $start = $start ? $start->max($liveStart) : $liveStart;
        }

        if ($start && $start >= $end) {
            return $days;
        }

        $live = $this->rawDays()->where('website_id', $website->id)
            ->when($start, fn ($q) => $q->where('created_at', '>=', $start))
            ->where('created_at', '<', $end)->orderBy('date')->get();

        return $days->concat($live);
    }

    private function totals(Collection $days): array
    {
        $measured = $days->sum('duration_count');

        return [
            'attempts' => $days->sum('attempt_count'),
            'ok' => $days->sum('success_count'),
            'avg_duration' => $measured ? round($days->sum('duration_sum_ms') / $measured) : 0,
        ];
    }

    public function overview(Website $website): array
    {
        $today = now()->toImmutable()->startOfDay();
        $days = $this->days($website, $today->addDay());
        $chartStart = $today->subMonths(12)->toDateString();
        $chart = $days->where('date', '>=', $chartStart)->values()->map(fn ($day) => [
            'date' => $day->date,
            'success_rate' => $day->attempt_count ? round(100 * $day->success_count / $day->attempt_count, 2) : 0,
            'avg_duration' => $day->duration_count ? round($day->duration_sum_ms / $day->duration_count) : null,
        ]);

        $years = [];
        foreach ($days->reverse()->groupBy(fn ($day) => substr($day->date, 0, 4)) as $year => $yearDays) {
            $months = [];
            foreach ($yearDays->groupBy(fn ($day) => (int) substr($day->date, 5, 2)) as $month => $monthDays) {
                $months[] = ['month' => $month] + $this->totals($monthDays);
            }
            $years[] = ['year' => $year, 'months' => $months] + $this->totals($yearDays);
        }

        return ['dailyStats' => $chart, 'stats' => $years];
    }

    public function month(Website $website, CarbonImmutable $start): array
    {
        $end = $start->addMonth();
        $days = $this->days($website, $end, $start)->reverse();
        $weeks = [];
        foreach ($days->groupBy(fn ($day) => CarbonImmutable::parse($day->date)->isoWeek()) as $week => $weekDays) {
            $monday = CarbonImmutable::parse($weekDays->first()->date)->startOfWeek(CarbonImmutable::MONDAY);
            $weeks[] = [
                'week' => $week,
                'from' => $monday->max($start)->format('j.n'),
                'to' => $monday->addDays(6)->min($end->subDay())->format('j.n'),
                'days' => $weekDays->map(function ($day) {
                    $date = CarbonImmutable::parse($day->date);

                    return [
                        'day' => $date->day,
                        'weekday' => $date->dayOfWeekIso,
                        'date' => $day->date,
                        'attempts' => (int) $day->attempt_count,
                        'ok' => (int) $day->success_count,
                        'avg_duration' => $day->duration_count ? round($day->duration_sum_ms / $day->duration_count) : 0,
                    ];
                }),
            ] + $this->totals($weekDays);
        }

        return $weeks;
    }
}
