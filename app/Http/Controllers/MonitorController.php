<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\Attempt;
use App\Models\Downtime;
use App\Services\SettingsService;
use App\Services\WebsiteStats;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\Promise\Each;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Mail;
use App\Mail\MonitoringReport;
use GuzzleHttp\TransferStats;

class MonitorController extends Controller
{
	private const CHECK_TIMEOUT_SECONDS = 50;
	private const START_INTERVAL_NS = 300_000_000;

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
		return $this->runCheck($website, false);
	}

	private function runCheck(Website $website, bool $setDowntime, ?CurlMultiHandler $handler = null) {
		$duration = 0;
		$effectiveUri = '';
		$deadline = hrtime(true) + self::CHECK_TIMEOUT_SECONDS * 1_000_000_000;

		$request = Http::timeout(self::CHECK_TIMEOUT_SECONDS)
			->connectTimeout(self::CHECK_TIMEOUT_SECONDS)
			->withMiddleware(static function ($next) use ($deadline) {
				return static function ($request, $options) use ($next, $deadline) {
					// Guzzle starts a new transfer on redirect. Share the original deadline.
					$remainingMs = intdiv($deadline - hrtime(true), 1_000_000);
					if ($remainingMs < 1) {
						throw new ConnectException('Website check timed out', $request, null, ['errno' => CURLE_OPERATION_TIMEDOUT]);
					}
					$options['timeout'] = $remainingMs / 1000;
					return $next($request, $options);
				};
			})
			->withOptions(['on_stats' => function (TransferStats $stats) use (&$duration, &$effectiveUri) {
				$duration += $stats->getTransferTime() * 1000;
				$effectiveUri = (string) $stats->getEffectiveUri();
			}]);

		$finish = function ($response) use ($website, $setDowntime, &$duration, &$effectiveUri) {
			return $this->processResponse($website, $response, round($duration), $effectiveUri, $setDowntime);
		};

		if ($handler !== null) {
			$promise = $request->async()->setHandler($handler)->get($website->url)->buildPromise();
			return $promise->then($finish, $finish);
		}

		try {
			$response = $request->get($website->url);
		} catch (ConnectionException | TransferException $e) {
			$response = $e;
		}
		return $finish($response);
	}

	public function showStats(Website $website, SettingsService $settings) {
		$website->loadCount('downtimes');

		return inertia('Website', [
			'website' => $website,
			'hasNodeServer' => $settings->get('node_server'),
			'algorithmUpdates' => $website->server === 'php' ? (object) config('monitoring.php_algorithm_updates', []) : null,
		]);
	}

	public function getOverview(Website $website, WebsiteStats $stats) {
		return $stats->overview($website);
	}

	public function getMonthData(Request $request, Website $website, WebsiteStats $stats) {
		$start = Carbon::createFromDate($request->year, $request->month, 1)->startOfMonth()->toImmutable();

		return response($stats->month($website, $start));
	}

	public function summarizeDailyStats(Request $request, SettingsService $settings, WebsiteStats $stats) {
		$key = $settings->get('cron_key');
		if (!$key || !is_string($request->key) || !hash_equals((string) $key, $request->key)) abort(429);

		$executed = RateLimiter::attempt(
			'summarize-daily-stats',
			1,
			fn () => true,
			6 * 60 * 60
		);

		if (!$executed) abort(429);

		return response(['completed_through' => $stats->summarizeThroughYesterday()]);
	}

	public function getDayData(Request $request, Website $website) {
		$request->validate([
			'date' => 'required|date_format:Y-m-d'
		]);

		$start = Carbon::parse($request->date)->startOfDay()->toImmutable();
		$attempts = $website->attempts()->where('created_at', '>=', $start)
			->where('created_at', '<', $start->addDay())->orderBy('created_at')->get();

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

		$websites = Website::where('active', 1)->where('server', 'php')->orderBy('id')->get();
		$handler = new CurlMultiHandler(['select_timeout' => 0.01]);
		$concurrency = match ($settings->get('php_check_mode')) {
			'concurrent_5' => 5,
			'concurrent_20' => 20,
			default => null,
		};

		if ($concurrency !== null) {
			// Create a request only when a pool slot opens, so queued sites get their full timeout.
			$checks = $websites->lazy()->map(fn ($website) => $this->runCheck($website, true, $handler));
			Each::ofLimitAll($checks->getIterator(), $concurrency)->wait();
			return $websites->count();
		}

		$promises = [];
		$nextStart = hrtime(true);

		foreach ($websites as $website) {
			while (hrtime(true) < $nextStart) {
				// Drive in-flight transfers and save completions between launches.
				$handler->tick();
				$remainingUs = intdiv($nextStart - hrtime(true), 1000);
				if ($remainingUs > 0) usleep(min($remainingUs, 2000));
			}

			$promises[] = $this->runCheck($website, true, $handler);
			$nextStart = hrtime(true) + self::START_INTERVAL_NS;
		}

		foreach ($promises as $promise) {
			$promise->wait();
		}

		return $websites->count();
	}

	private function resolveStatus(Website $website, $response, $effectiveUri) {
		if ($response instanceof ConnectionException || $response instanceof TransferException) {
			$cause = $response instanceof ConnectionException ? $response->getPrevious() : $response;
			$errno = $cause instanceof ConnectException ? ($cause->getHandlerContext()['errno'] ?? null) : null;
			return [
				'status' => $errno === CURLE_OPERATION_TIMEDOUT ? 600 : 700,
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
		$result = $this->resolveStatus($website, $response, $effectiveUri);

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
		})->orderBy('from', 'desc')->paginate(20, ['id', 'website_id', 'status', 'from', 'to'])->withQueryString();
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
		$data = $request->validate([
			'downtime_id' => 'required|integer'
		]);

		$downtime = Downtime::findOrFail($data['downtime_id'], ['body', 'attempts']);

		return response()->json([
			'body' => $downtime->body,
			'records' => Attempt::whereIn('id', $downtime->attempts ?? [])->get(),
		]);
	}

	public function status() {
		return inertia('Status', [
			'websites' => fn() => Website::where('active', 1)->get(['name', 'url', 'status', 'checked_at'])
		]);
	}

	public function mailReport(Request $request, SettingsService $settings, WebsiteStats $stats) {
		$settingsData = $settings->only(['cron_key', 'reports']);

		if (!isset($settingsData['cron_key']) || $settingsData['cron_key'] != $request->key) abort(429); // should be 401 - but the key can then be guessed

		$today = Carbon::now()->day;
		$sendMonthReport = $today == 1;
		$showPreview = request('preview');

		if ($showPreview) {
			if ($showPreview == 1) return $this->mailReportHelper($stats, false, $settingsData['reports'], $showPreview);
			elseif ($showPreview == 2) return $this->mailReportHelper($stats, true, $settingsData['reports'], $showPreview);
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

		$this->mailReportHelper($stats, false, $settingsData['reports']);
		if ($sendMonthReport) $this->mailReportHelper($stats, true, $settingsData['reports']);

		return;
	}

	public function mailReportHelper(WebsiteStats $stats, $monthly, $reportsArr, $preview = false) {
		if (!$preview) {
			if (empty($reportsArr)) return;

			$reportsArr = array_filter($reportsArr, function($r) use ($monthly) {
				if ($monthly) return $r['frequency'] == 'monthly';
				else return $r['frequency'] == 'daily';
			});

			if (empty($reportsArr)) return;
		}

		$today = Carbon::now()->toImmutable()->startOfDay();
		$end = $monthly ? $today->startOfMonth() : $today;
		$start = $monthly ? $end->subMonth() : $end->subDay();
		$report = $stats->report($start, $end);

		$title = $monthly ? "Monthly monitoring report - {$start->format('m/Y')}" : "Daily monitoring report - {$start->format('d.m.Y')}";

		if ($preview) {
			return new MonitoringReport($title, $report);
		}

		foreach ($reportsArr as $rep) {
			Mail::to($rep['email'])->send(new MonitoringReport($title, $report));
		}

		return;
	}
}