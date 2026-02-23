<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\Attempt;
use App\Models\Downtime;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\MonitoringReport;
use GuzzleHttp\TransferStats;

class MonitorController extends Controller
{
	private $valRules = [
		'name' =>  'required',
		'url' =>  'required|url',
		'phrase' =>  'required',
		'mserver' => 'required',
		'mrequests' => 'required',
		'mtimeout' => 'required'
	];

	public function index(SettingsService $settings) {
		return inertia('Dashboard', [
			'websites' => Website::get(['id', 'active', 'name', 'url', 'status', 'phrase', 'attempts', 'server', 'requests', 'timeout', 'checked_at']),
			'hasNodeServer' => $settings->get('node_server')
		]);
	}

	public function store(Request $request) {
		$request->validate($this->valRules);

		$storedItem = Website::create([
			'name' => $request->name,
			'url' => $request->url,
			'phrase' => $request->phrase,
			'active' => $request->active,
			'server' => $request->mserver,
			'requests' => $request->mrequests,
			'timeout' => $request->mtimeout
		]);

		return back()->with('data', $storedItem);
	}

	public function update(Request $request, Website $website) {
		$request->validate($this->valRules);

		$shouldUpdate = $website->active != $request->active || $website->phrase != $request->phrase;

		$website->update([
			'name' => $request->name,
			'url' => $request->url,
			'phrase' => $request->phrase,
			'active' => $request->active,
			'server' => $request->mserver,
			'requests' => $request->mrequests,
			'timeout' => $request->mtimeout
		]);

		if ($shouldUpdate && $website->active && $request->mserver == 'php') $this->checkSite($website);

		return back()->with('data', $website);
	}

	public function switchActive(Request $request, Website $website) {
		if (!isset($request->active)) return abort(400);

		$updated = $website->update([
			'active' => $request->active
		]);

		if ($request->active && $website->server == 'php') $this->checkSite($website);

		return response([
			'success' => $updated
		]);
	}

	public function destroy(Website $website) {
		$deleted = $website->delete();

		return response([
			'success' => $deleted
		]);
	}

	private function createDowntime($websiteId, $statusCode, $ts, $attemptId, $body) {
		Downtime::create([
			'website_id' => $websiteId,
			'status' => $statusCode,
			'from' => $ts,
			'to' => $ts+60,
			'attempts' => [$attemptId],
			'body' => $body ? trim($body) : null
		]);
	}

	public function checkSite(Website $website, $setDowntime = false) {
		$duration = 0;
		$effectiveUri = '';

		try {
			$response = Http::withOptions([
				'on_stats' => function (TransferStats $stats) use (&$duration, &$effectiveUri) {
					$duration = round($stats->getTransferTime() * 1000);
					$effectiveUri = (string) $stats->getEffectiveUri();
				}
			])->timeout($website->timeout)->get($website->url);

		} catch (ConnectionException $e) {
			$response = $e;
		}

		return $this->processResponse($website, $response, $duration, $effectiveUri, false);
	}

	public function showStats(Website $website, SettingsService $settings) {
		$website->loadCount('downtimes');

		return inertia('Website', [
			'website' => $website,
			'hasNodeServer' => $settings->get('node_server')
		]);
	}

	public function getDailyStats(Website $website) {
		$dailyStats = $website->attempts()
			->where('created_at', '>=', now()->subMonths(12)->startOfDay())
			->selectRaw('
				DATE(created_at) as date,
				ROUND(SUM(status = 200) / COUNT(*) * 100, 2) as success_rate,
				ROUND(AVG(duration), 0) as avg_duration
			')
			->groupByRaw('DATE(created_at)')
			->orderByRaw('date ASC')
			->get();

		return $dailyStats;
	}

	public function getStats(Website $website) {
		$attempts = $website->attempts()
			->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as total, SUM(status = 200) as ok, AVG(duration) as avg_duration')
			->groupByRaw('YEAR(created_at), MONTH(created_at)')
			->orderByRaw('YEAR(created_at) DESC, MONTH(created_at) DESC')
			->get();

		$grouped = [];

		foreach ($attempts as $row) {
			$year = (int) $row->year;
			$month = (int) ltrim($row->month, '0');

			$yearIndex = null;
			foreach ($grouped as $index => $y) {
				if ($y['year'] === $year) {
					$yearIndex = $index;
					break;
				}
			}

			if ($yearIndex === null) {
				$grouped[] = [
					'year' => $year,
					'attempts' => 0,
					'ok' => 0,
					'avg_duration' => 0,
					'months' => [],
					'_duration_sum' => 0,
					'_duration_count' => 0
				];
				$yearIndex = array_key_last($grouped);
			}

			$grouped[$yearIndex]['attempts'] += $row->total;
			$grouped[$yearIndex]['ok'] += $row->ok;
			$grouped[$yearIndex]['_duration_sum'] += $row->avg_duration * $row->total;
			$grouped[$yearIndex]['_duration_count'] += $row->total;

			$grouped[$yearIndex]['months'][] = [
				'month' => $month,
				'attempts' => $row->total,
				'ok' => $row->ok,
				'avg_duration' => round($row->avg_duration, 0)
			];
		}

		foreach ($grouped as $year => &$data) {
			if ($data['_duration_count'] > 0) $data['avg_duration'] = round($data['_duration_sum'] / $data['_duration_count'], 0);
			unset($data['_duration_sum'], $data['_duration_count']);
		}

		return $grouped;
	}

	public function getMonthData(Request $request, Website $website) {
		$startDate = Carbon::createFromDate($request->year, $request->month, 1)->startOfMonth();
		$endDate = (clone $startDate)->endOfMonth();

		 $attempts = $website->attempts()
			->selectRaw('
				DATE(created_at) as date,
				WEEK(created_at, 3) as week,
				DAY(created_at) as day,
				COUNT(*) as total,
				SUM(status = 200) as ok,
				AVG(duration) as avg_duration
			')
			->whereBetween('created_at', [$startDate, $endDate])
			->groupByRaw('DATE(created_at), WEEK(created_at, 3), DAY(created_at), DAYNAME(created_at)')
			->orderBy('date', 'desc')
			->get();

		$weeks = [];

		foreach ($attempts as $row) {
			$week = (int) $row->week;
			$date = Carbon::parse($row->date);
			$day = (int) $row->day;

			$weekIndex = null;
			foreach ($weeks as $i => $w) {
				if ($w['week'] === $week) {
					$weekIndex = $i;
					break;
				}
			}

			if ($weekIndex === null) {
				$from = (clone $date)->startOfWeek(Carbon::MONDAY);
				$to = (clone $from)->endOfWeek(Carbon::SUNDAY);

				$from = $from->lessThan($startDate) ? $startDate->copy() : $from;
				$to = $to->greaterThan($endDate) ? $endDate->copy() : $to;

				$weeks[] = [
					'week' => $week,
					'from' => $from->format('j.n'),
					'to' => $to->format('j.n'),
					'attempts' => 0,
					'ok' => 0,
					'avg_duration' => 0,
					'days' => [],
					'_duration_sum' => 0,
					'_duration_count' => 0
				];
				$weekIndex = array_key_last($weeks);
			}

			$weeks[$weekIndex]['attempts'] += $row->total;
			$weeks[$weekIndex]['ok'] += $row->ok;
			$weeks[$weekIndex]['_duration_sum'] += $row->avg_duration * $row->total;
			$weeks[$weekIndex]['_duration_count'] += $row->total;

			$weeks[$weekIndex]['days'][] = [
				'day' => $day,
				'weekday' => $date->dayOfWeekIso,
				'date' => $date->format('Y-m-d'),
				'attempts' => $row->total,
				'ok' => $row->ok,
				'avg_duration' => round($row->avg_duration, 0),
			];
		}

		foreach ($weeks as &$week) {
			if ($week['_duration_count'] > 0) {
				$week['avg_duration'] = round($week['_duration_sum'] / $week['_duration_count'], 0);
			}
			unset($week['_duration_sum'], $week['_duration_count']);
		}

		return response($weeks);
	}

	public function getDayData(Request $request, Website $website) {
		$request->validate([
			'date' => 'date_format:Y-m-d'
		]);

		$attempts = $website->attempts()->whereDate('created_at', $request->date)->orderBy('created_at')->get();

		$grouped = [];

		foreach ($attempts as $attempt) {
			$hour = Carbon::parse($attempt->created_at)->hour;

			if (!isset($grouped[$hour])) {
				$grouped[$hour] = [
					'hour' => $hour,
					'attempts' => 0,
					'ok' => 0,
					'min_duration' => $attempt->duration,
					'max_duration' => $attempt->duration,
					'items' => []
				];
			}

			$grouped[$hour]['attempts']++;
			if ($attempt->status == 200) $grouped[$hour]['ok']++;

			if ($attempt->duration < $grouped[$hour]['min_duration']) $grouped[$hour]['min_duration'] = $attempt->duration;
			if ($attempt->duration > $grouped[$hour]['max_duration']) $grouped[$hour]['max_duration'] = $attempt->duration;

			$attempt->realTime = $attempt->created_at->format('H:i:s');
			$grouped[$hour]['items'][] = $attempt;
		}

		$grouped = array_values($grouped);

		return response($grouped);
	}

	public function checkAll(Request $request, SettingsService $settings) {
		$key = $settings->get('cron_key');
		if (!$key || $key != $request->key) abort(429); // should be 401 - but the key can then be guessed

		$executed = RateLimiter::attempt(
			'check-all-sites',
			2,
			fn () => true,
			60
		);

		if (!$executed) abort(429);

		$websites = $websites = Website::where('active', 1)->where('server', 'php')->get();

		if ($websites->isEmpty()) {
			return 0;
		}

		$durations = [];
		$effectiveURIs = [];

		$responses = Http::pool(function (Pool $pool) use ($websites, &$durations, &$effectiveURIs) {
			foreach ($websites as $website) {
				$pool->as($website->id)->withOptions([
					'on_stats' => function (TransferStats $stats) use (&$durations, &$effectiveURIs, $website) {
						$durations[$website->id] = round($stats->getTransferTime() * 1000);
						$effectiveURIs[$website->id] = (string) $stats->getEffectiveUri();
					}
				])->timeout($website->timeout)->get($website->url);
			}
		}, concurrency: 20);

		foreach ($websites as $web) {
			$d = $durations[$web->id] ?? 0;
			$uri = $effectiveURIs[$web->id] ?? '';

			$this->processResponse($web, $responses[$web->id], $d, $uri, true);
		}

		return $websites->count();
	}

	private function resolveStatus(Website $website, $response, $duration, $effectiveUri) {
		if ($response instanceof ConnectionException) {
			return [
				'status' => $duration >= ($website->timeout * 1000) ? 600 : 700,
				'body' => $response->getMessage(),
				'phrase' => false,
				'redirect' => null
			];
		}

		$status = $response->status();
		$html = $response->body();

		return [
			'status' => $status,
			'body' => $status !== 200 ? $html : null,
			'phrase' => $status === 200 && str_contains($html, $website->phrase),
			'redirect' => $effectiveUri ? rtrim($effectiveUri, '/') !== rtrim($website->url, '/') : null
		];
	}

	private function handleDowntime(Website $website, $newAttempt, $status, $body) {
		if ($status == 200 && in_array($website->status, [200, '200+', null])) return;

		$ts = $newAttempt->created_at->getTimestamp();

		if (in_array($website->status, [200, '200+'])) {
			return $this->createDowntime($website->id, $status, $ts, $newAttempt->id, $body);
		}

		$latestDT = Downtime::where('website_id', $website->id)->latest()->first();

		if ($status == 200 && isset($latestDT->id) && $ts - $latestDT->to < 60) {
			return $latestDT->update([ 'to' => $ts ]);
		}

		if (isset($latestDT->id) && $latestDT->status == $status && $ts - $latestDT->to < 60) {
			$latestDT->update([
				'to' => $ts+60,
				'attempts' => [...$latestDT->attempts, $newAttempt->id]
			]);
		} else {
			$this->createDowntime($website->id, $status, $ts, $newAttempt->id, $body);
		}
	}

	private function processResponse($website, $response, $duration, $effectiveUri, $setDowntime = false) {
		$result = $this->resolveStatus($website, $response, $duration, $effectiveUri);

		$newAttempt = $website->attempts()->create([
			'status' => $result['status'],
			'phrase' => $result['phrase'],
			'duration' => $duration,
			'redirected' => $result['redirect'],
			'server' => 'php'
		]);

		if ($setDowntime) $this->handleDowntime($website, $newAttempt, $result['status'], $result['body']);

		$website->update([
			'status' => $result['status'] == 200 && $result['phrase'] ? '200+' : $result['status'],
			'attempts' => $website->attempts+1,
			'checked_at' => time()
		]);

		return response([
			'status_code' => $result['status'],
			'phrase_found' => $result['phrase'],
			'response_time_ms' => $duration,
			'is_redirected' => $result['redirect'],
			'error' => in_array($result['status'], [600, 700]) ? $result['body'] : null
		]);
	}

	public function downtimeLog(Request $request) {
		$attempts2 = Downtime::when(request('website_id'), function ($query) {
			$query->where('website_id', request('website_id'));
		})->when(request('status'), function ($query) {
			$query->whereIn('status', request('status'));
		})->orderBy('from', 'desc')->paginate(20)->withQueryString();
		$websites = Website::get(['id', 'name', 'url']);
		$statuses = Downtime::distinct()->pluck('status');

		return inertia('Downtime', [
			'downtimes' => $attempts2,
			'websites' => $websites,
			'filters' => $request->only(['website_id', 'status']),
			'statuses' => $statuses
		]);
	}

	public function getDowntimeRecords(Request $request) {
		$request->validate([
			'ids' => 'array'
		]);

		$attempts = Attempt::whereIn('id', $request->ids)->get();

		return response($attempts);
	}

	public function status() {
		return inertia('Status', [
			'websites' => fn() => Website::where('active', 1)->get(['name', 'url', 'status', 'checked_at'])
		]);
	}

	public function mailReport(Request $request, SettingsService $settings) {
		$settingsData = $settings->only(['cron_key', 'reports']);

		if (!isset($settingsData['cron_key']) || $settingsData['cron_key'] != $request->key) abort(429); // should be 401 - but the key can then be guessed

		$today = Carbon::now()->day;
		$sendMonthReport = $today == 1;
		$showPreview = request('preview');

		if ($showPreview) {
			if ($showPreview == 1) return $this->mailReportHelper(false, $settingsData['reports'], $showPreview);
			elseif ($showPreview == 2) return $this->mailReportHelper(true, $settingsData['reports'], $showPreview);
			else abort(400);
		}

		$shouldRun = false;
		$executed = RateLimiter::attempt(
			'reports-limiter',
			1,
			function() use (&$shouldRun) {
				$shouldRun = true;
			},
			72000
		);

		if (!$executed || !$shouldRun) abort(429);

		$this->mailReportHelper(false, $settingsData['reports']);
		if ($sendMonthReport) $this->mailReportHelper(true, $settingsData['reports']);

		return;
	}

	public function mailReportHelper($monthly, $reportsArr, $preview = false) {
		$start = $monthly ? Carbon::now()->startOfMonth()->subMonth() : Carbon::now()->startOfDay()->subDay();
		$end = $monthly ? Carbon::now()->startOfMonth()->subSecond() : Carbon::now()->startOfDay()->subSecond();

		$downtimesSubquery = Downtime::select(
				'website_id',
				DB::raw('COUNT(*) as downtimes_count')
			)
			->whereBetween('created_at', [$start, $end])
			->groupBy('website_id');

		$report = Attempt::select(
				'attempts.website_id',
				DB::raw('COUNT(attempts.id) as attempts'),
				DB::raw('SUM(attempts.status = 200) as ok'),
				DB::raw('ROUND(AVG(attempts.duration)) as avg_duration'),
				DB::raw('COALESCE(MAX(d.downtimes_count), 0) as downtimes_count')
			)
			->leftJoinSub($downtimesSubquery, 'd', function($join) {
				$join->on('attempts.website_id', '=', 'd.website_id');
			})
			->whereBetween('attempts.created_at', [$start, $end])
			->groupBy('attempts.website_id')
			->with('website:id,name,url,active')
			->get()
			->map(function ($row) {
				$row->availability = $row->attempts > 0 ? round(($row->ok / $row->attempts) * 100, 2) : 0;
				return $row;
			});

		$title = $monthly ? "Monthly monitoring report - {$start->format('m/Y')}" : "Daily monitoring report - {$start->format('d.m.Y')}";

		if ($preview) {
			return new MonitoringReport($title, $report);
		}

		if (empty($reportsArr)) return;

		$reportsArr = array_filter($reportsArr, function($r) use ($monthly) {
			if ($monthly) return $r['frequency'] == 'monthly';
			else return $r['frequency'] == 'daily';
		});

		if (empty($reportsArr)) return;

		foreach ($reportsArr as $rep) {
			Mail::to($rep['email'])->send(new MonitoringReport($title, $report));
		}

		return;
	}
}