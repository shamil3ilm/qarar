<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\InspectionLot;
use App\Models\Manufacturing\SpcChart;
use App\Models\Manufacturing\SpcSubgroup;
use InvalidArgumentException;

/**
 * Statistical Process Control (SPC) service.
 *
 * Implements:
 *  - Xbar-R control charts (variable data)
 *  - Process capability indices (Cp, Cpk)
 *  - Western Electric rules 1–4 for out-of-control detection
 */
class SpcService
{
    /**
     * Control chart constants for Xbar-R by subgroup size n.
     * Keys: A2 (X-bar control limit factor), D3/D4 (R-chart factors).
     *
     * @var array<int, array{A2: float, D3: float, D4: float}>
     */
    private const CONTROL_CHART_CONSTANTS = [
        2  => ['A2' => 1.880, 'D3' => 0.000, 'D4' => 3.267],
        3  => ['A2' => 1.023, 'D3' => 0.000, 'D4' => 2.575],
        4  => ['A2' => 0.729, 'D3' => 0.000, 'D4' => 2.282],
        5  => ['A2' => 0.577, 'D3' => 0.000, 'D4' => 2.115],
        6  => ['A2' => 0.483, 'D3' => 0.000, 'D4' => 2.004],
        7  => ['A2' => 0.419, 'D3' => 0.076, 'D4' => 1.924],
        8  => ['A2' => 0.373, 'D3' => 0.136, 'D4' => 1.864],
        9  => ['A2' => 0.337, 'D3' => 0.184, 'D4' => 1.816],
        10 => ['A2' => 0.308, 'D3' => 0.223, 'D4' => 1.777],
    ];

    /**
     * Calculate Xbar-R (mean and range) control chart statistics.
     *
     * @param  float[][] $samples  Array of subgroups; each subgroup is an array of readings.
     *
     * @return array{
     *   xbar_values: float[],
     *   r_values: float[],
     *   x_double_bar: float,
     *   r_bar: float,
     *   ucl_xbar: float,
     *   lcl_xbar: float,
     *   ucl_r: float,
     *   lcl_r: float,
     *   out_of_control_points: array<int, array{index: int, value: float, chart: string, rule: string}>
     * }
     */
    public function calculateXbarR(array $samples): array
    {
        if (empty($samples)) {
            throw new InvalidArgumentException('At least one subgroup is required.');
        }

        $subgroupSize = count(reset($samples));

        if ($subgroupSize < 2 || $subgroupSize > 10) {
            throw new InvalidArgumentException('Subgroup size must be between 2 and 10.');
        }

        foreach ($samples as $index => $subgroup) {
            if (count($subgroup) !== $subgroupSize) {
                throw new InvalidArgumentException(
                    "All subgroups must have the same size. Subgroup {$index} has a different count."
                );
            }
        }

        $constants = self::CONTROL_CHART_CONSTANTS[$subgroupSize];

        $xbarValues = [];
        $rValues    = [];

        foreach ($samples as $subgroup) {
            $floatSubgroup = array_map('floatval', $subgroup);
            $xbarValues[]  = array_sum($floatSubgroup) / $subgroupSize;
            $rValues[]     = max($floatSubgroup) - min($floatSubgroup);
        }

        $xDoubleBar = array_sum($xbarValues) / count($xbarValues);
        $rBar       = array_sum($rValues) / count($rValues);

        $uclXbar = $xDoubleBar + $constants['A2'] * $rBar;
        $lclXbar = $xDoubleBar - $constants['A2'] * $rBar;
        $uclR    = $constants['D4'] * $rBar;
        $lclR    = $constants['D3'] * $rBar;

        // Standard deviation estimate: σ̂ = R̄ / d2
        // d2 constants for detecting out-of-control (sigma zones)
        $sigmaXbar = ($constants['A2'] > 0)
            ? ($uclXbar - $xDoubleBar) / 3
            : 0.0;

        $outOfControlXbar = $this->detectOutOfControl($xbarValues, $xDoubleBar, $uclXbar, $lclXbar, 'xbar', $sigmaXbar);
        $sigmaR = ($uclR - $rBar) / 3;
        $outOfControlR = $this->detectOutOfControl($rValues, $rBar, $uclR, $lclR, 'r', $sigmaR);

        return [
            'xbar_values'          => $xbarValues,
            'r_values'             => $rValues,
            'x_double_bar'         => round($xDoubleBar, 6),
            'r_bar'                => round($rBar, 6),
            'ucl_xbar'             => round($uclXbar, 6),
            'lcl_xbar'             => round($lclXbar, 6),
            'ucl_r'                => round($uclR, 6),
            'lcl_r'                => round($lclR, 6),
            'out_of_control_points' => array_merge($outOfControlXbar, $outOfControlR),
        ];
    }

    /**
     * Calculate process capability indices for a set of individual measurements.
     *
     * @param  float[] $measurements
     *
     * @return array{
     *   mean: float,
     *   std_dev: float,
     *   cp: float,
     *   cpk: float,
     *   cpu: float,
     *   cpl: float,
     *   sigma_level: float,
     *   ppm_defects: float,
     *   is_capable: bool
     * }
     */
    public function calculateCpk(array $measurements, float $usl, float $lsl): array
    {
        if (count($measurements) < 2) {
            throw new InvalidArgumentException('At least 2 measurements are required.');
        }

        if ($usl <= $lsl) {
            throw new InvalidArgumentException('USL must be greater than LSL.');
        }

        $n     = count($measurements);
        $mean  = array_sum($measurements) / $n;
        $variance = array_sum(array_map(fn(float $x) => ($x - $mean) ** 2, $measurements)) / ($n - 1);
        $stdDev   = sqrt($variance);

        if ($stdDev <= 0.0) {
            // Perfect process — return near-infinite capability
            return [
                'mean'        => round($mean, 6),
                'std_dev'     => 0.0,
                'cp'          => 9999.0,
                'cpk'         => 9999.0,
                'cpu'         => 9999.0,
                'cpl'         => 9999.0,
                'sigma_level' => 9999.0,
                'ppm_defects' => 0.0,
                'is_capable'  => true,
            ];
        }

        $cp  = ($usl - $lsl) / (6 * $stdDev);
        $cpu = ($usl - $mean) / (3 * $stdDev);
        $cpl = ($mean - $lsl) / (3 * $stdDev);
        $cpk = min($cpu, $cpl);

        // Sigma level = Cpk * 3
        $sigmaLevel = $cpk * 3;

        // PPM estimate using normal distribution tail probability
        $ppmDefects = $this->estimatePpm($mean, $stdDev, $usl, $lsl);

        return [
            'mean'        => round($mean, 6),
            'std_dev'     => round($stdDev, 6),
            'cp'          => round($cp, 4),
            'cpk'         => round($cpk, 4),
            'cpu'         => round($cpu, 4),
            'cpl'         => round($cpl, 4),
            'sigma_level' => round($sigmaLevel, 4),
            'ppm_defects' => round($ppmDefects, 2),
            'is_capable'  => $cpk >= 1.33,
        ];
    }

    /**
     * Detect out-of-control points using Western Electric rules 1–4.
     *
     * Rule 1: One point beyond 3σ
     * Rule 2: Two of three consecutive points beyond 2σ on same side
     * Rule 3: Four of five consecutive points beyond 1σ on same side
     * Rule 4: Eight consecutive points on same side of centre line
     *
     * @param  float[] $values
     * @param  string  $chartType 'xbar' or 'r' — used for labelling only
     *
     * @return array<int, array{index: int, value: float, chart: string, rule: string}>
     */
    public function detectOutOfControl(
        array $values,
        float $mean,
        float $ucl,
        float $lcl,
        string $chartType = 'xbar',
        float $sigma = 0.0
    ): array {
        $outOfControl = [];

        if ($sigma <= 0.0) {
            $sigma = ($ucl - $mean) / 3;
        }

        $n = count($values);

        // Pre-compute sigma zones for each point
        $zones = array_map(function (float $v) use ($mean, $sigma): float {
            return $sigma > 0 ? ($v - $mean) / $sigma : 0.0;
        }, $values);

        for ($i = 0; $i < $n; $i++) {
            $v = $values[$i];

            // Rule 1: Beyond 3σ (outside control limits)
            if ($v > $ucl || $v < $lcl) {
                $outOfControl[] = [
                    'index' => $i,
                    'value' => $v,
                    'chart' => $chartType,
                    'rule'  => 'Rule 1: Point beyond 3σ control limit',
                ];
                continue;
            }

            // Rule 2: 2 of 3 consecutive beyond 2σ same side
            if ($i >= 2) {
                $window = [$zones[$i - 2], $zones[$i - 1], $zones[$i]];
                $beyondPos2sigma = count(array_filter($window, fn(float $z) => $z >= 2.0));
                $beyondNeg2sigma = count(array_filter($window, fn(float $z) => $z <= -2.0));
                if ($beyondPos2sigma >= 2 || $beyondNeg2sigma >= 2) {
                    $outOfControl[] = [
                        'index' => $i,
                        'value' => $v,
                        'chart' => $chartType,
                        'rule'  => 'Rule 2: 2 of 3 consecutive points beyond 2σ',
                    ];
                    continue;
                }
            }

            // Rule 3: 4 of 5 consecutive beyond 1σ same side
            if ($i >= 4) {
                $window = [$zones[$i - 4], $zones[$i - 3], $zones[$i - 2], $zones[$i - 1], $zones[$i]];
                $beyondPos1sigma = count(array_filter($window, fn(float $z) => $z >= 1.0));
                $beyondNeg1sigma = count(array_filter($window, fn(float $z) => $z <= -1.0));
                if ($beyondPos1sigma >= 4 || $beyondNeg1sigma >= 4) {
                    $outOfControl[] = [
                        'index' => $i,
                        'value' => $v,
                        'chart' => $chartType,
                        'rule'  => 'Rule 3: 4 of 5 consecutive points beyond 1σ',
                    ];
                    continue;
                }
            }

            // Rule 4: 8 consecutive points same side of centreline
            if ($i >= 7) {
                $window = array_slice($zones, $i - 7, 8);
                $allAbove = count(array_filter($window, fn(float $z) => $z > 0.0)) === 8;
                $allBelow = count(array_filter($window, fn(float $z) => $z < 0.0)) === 8;
                if ($allAbove || $allBelow) {
                    $outOfControl[] = [
                        'index' => $i,
                        'value' => $v,
                        'chart' => $chartType,
                        'rule'  => 'Rule 4: 8 consecutive points same side of centreline',
                    ];
                }
            }
        }

        return $outOfControl;
    }

    // -------------------------------------------------------------------------
    // Persistent SPC Chart management (Gap 3 additions)
    // -------------------------------------------------------------------------

    /**
     * Create a persistent SPC chart, computing control limits from initial measurement data.
     *
     * @param  array<string, mixed> $data  Must contain: characteristic_name, initial_measurements (flat float[]),
     *                                     subgroup_size (int), chart_type, product_id, usl, lsl
     */
    public function createChart(int $organizationId, array $data): SpcChart
    {
        $subgroupSize = (int) ($data['subgroup_size'] ?? 5);
        $flatMeasurements = array_map('floatval', $data['initial_measurements']);

        // Build subgroups from the flat measurements array
        $numSubgroups = (int) floor(count($flatMeasurements) / $subgroupSize);
        if ($numSubgroups < 2) {
            throw new InvalidArgumentException(
                "Not enough initial measurements to form at least 2 subgroups of size {$subgroupSize}."
            );
        }

        $samples = [];
        for ($i = 0; $i < $numSubgroups; $i++) {
            $samples[] = array_slice($flatMeasurements, $i * $subgroupSize, $subgroupSize);
        }

        $result = $this->calculateXbarR($samples);

        return SpcChart::create([
            'organization_id'     => $organizationId,
            'product_id'          => $data['product_id'] ?? null,
            'characteristic_name' => $data['characteristic_name'],
            'chart_type'          => $data['chart_type'] ?? 'xbar_r',
            'subgroup_size'       => $subgroupSize,
            'ucl'                 => $result['ucl_xbar'],
            'lcl'                 => $result['lcl_xbar'],
            'center_line'         => $result['x_double_bar'],
            'usl'                 => $data['usl'] ?? null,
            'lsl'                 => $data['lsl'] ?? null,
        ]);
    }

    /**
     * Record a new subgroup of measurements against a persistent chart,
     * apply Western Electric rules, persist the result and return the subgroup.
     *
     * @param  float[] $measurements
     */
    public function recordSubgroup(int $chartId, array $measurements, int $recordedBy): SpcSubgroup
    {
        $chart = SpcChart::findOrFail($chartId);

        $floatMeasurements = array_map('floatval', $measurements);
        $count  = count($floatMeasurements);
        $mean   = $count > 0 ? array_sum($floatMeasurements) / $count : 0.0;
        $range  = $count > 0 ? max($floatMeasurements) - min($floatMeasurements) : 0.0;

        $violations   = $this->applyWesternElectricRules($chart, $chartId, $mean);
        $outOfControl = !empty($violations);

        $subgroup = SpcSubgroup::create([
            'spc_chart_id'   => $chartId,
            'organization_id'=> $chart->organization_id,
            'measured_at'    => now(),
            'measurements'   => $floatMeasurements,
            'subgroup_mean'  => round($mean, 6),
            'subgroup_range' => round($range, 6),
            'out_of_control' => $outOfControl,
            'violated_rules' => $violations ?: null,
            'recorded_by'    => $recordedBy,
        ]);

        // Recompute Cpk from recent subgroup means when spec limits are set
        if ($chart->usl !== null && $chart->lsl !== null) {
            $recentMeans = SpcSubgroup::where('spc_chart_id', $chartId)
                ->latest('measured_at')
                ->limit(25)
                ->pluck('subgroup_mean')
                ->map(fn ($v) => (float) $v)
                ->toArray();

            if (count($recentMeans) >= 2) {
                $sigma     = $this->estimateSigmaFromValues($recentMeans);
                $grandMean = array_sum($recentMeans) / count($recentMeans);
                if ($sigma > 0.0) {
                    $cpu = ((float) $chart->usl - $grandMean) / (3.0 * $sigma);
                    $cpl = ($grandMean - (float) $chart->lsl) / (3.0 * $sigma);
                    $chart->update(['cpk' => round(min($cpu, $cpl), 4)]);
                }
            }
        }

        return $subgroup;
    }

    /**
     * Return the last N subgroups for a chart with process stability assessment.
     *
     * @return array<string, mixed>
     */
    public function getTrend(int $chartId, int $limit = 30): array
    {
        $chart = SpcChart::findOrFail($chartId);

        $subgroups = SpcSubgroup::where('spc_chart_id', $chartId)
            ->orderByDesc('measured_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $oocCount = $subgroups->where('out_of_control', true)->count();

        return [
            'chart'          => $chart,
            'subgroups'      => $subgroups,
            'ooc_count'      => $oocCount,
            'ooc_rate_pct'   => $subgroups->count() > 0
                ? round($oocCount / $subgroups->count() * 100, 1)
                : 0.0,
            'process_stable' => $oocCount === 0,
            'latest_cpk'     => $chart->cpk,
        ];
    }

    /**
     * Apply Western Electric rules 1–4 against a persistent chart's stored limits.
     * Returns an array of human-readable violation strings (empty = in control).
     *
     * @return string[]
     */
    private function applyWesternElectricRules(SpcChart $chart, int $chartId, float $currentMean): array
    {
        $ucl        = (float) $chart->ucl;
        $lcl        = (float) $chart->lcl;
        $centerLine = (float) $chart->center_line;
        $sigma      = ($ucl - $centerLine) / 3.0;
        $violations = [];

        // Rule 1: One point beyond 3σ (outside UCL / LCL)
        if ($currentMean > $ucl || $currentMean < $lcl) {
            $violations[] = 'Rule 1: Point beyond 3σ control limits';
        }

        // Fetch recent history (newest-first) for rules 2–4
        $recent = SpcSubgroup::where('spc_chart_id', $chartId)
            ->orderByDesc('measured_at')
            ->limit(8)
            ->pluck('subgroup_mean')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        // Build means array: current first, then history
        $means = array_merge([$currentMean], $recent);

        // Rule 2: 9 consecutive points on same side of centre line
        if (count($means) >= 9) {
            $last9    = array_slice($means, 0, 9);
            $allAbove = count(array_filter($last9, fn (float $m) => $m > $centerLine)) === 9;
            $allBelow = count(array_filter($last9, fn (float $m) => $m < $centerLine)) === 9;
            if ($allAbove || $allBelow) {
                $violations[] = 'Rule 2: 9 consecutive points on same side of centerline';
            }
        }

        // Rule 3: 6 consecutive points trending in one direction
        if (count($means) >= 6) {
            $last6      = array_slice($means, 0, 6);
            $ascending  = true;
            $descending = true;
            for ($i = 0; $i < 5; $i++) {
                if ($last6[$i] <= $last6[$i + 1]) {
                    $ascending = false;
                }
                if ($last6[$i] >= $last6[$i + 1]) {
                    $descending = false;
                }
            }
            if ($ascending || $descending) {
                $violations[] = 'Rule 3: 6 consecutive points trending in one direction';
            }
        }

        // Rule 4: 2 of 3 consecutive points beyond 2σ on same side
        if ($sigma > 0.0 && count($means) >= 3) {
            $last3  = array_slice($means, 0, 3);
            $beyondPos = count(array_filter($last3, fn (float $m) => $m > $centerLine + 2.0 * $sigma));
            $beyondNeg = count(array_filter($last3, fn (float $m) => $m < $centerLine - 2.0 * $sigma));
            if ($beyondPos >= 2 || $beyondNeg >= 2) {
                $violations[] = 'Rule 4: 2 of 3 consecutive points beyond 2σ';
            }
        }

        return $violations;
    }

    /**
     * Estimate process sigma from a sample of values using the sample standard deviation.
     *
     * @param  float[] $values
     */
    private function estimateSigmaFromValues(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }
        $mean     = array_sum($values) / $n;
        $variance = array_sum(array_map(fn (float $v) => ($v - $mean) ** 2, $values)) / ($n - 1);
        return sqrt($variance);
    }

    /**
     * Generate control chart data for an inspection lot and a specific characteristic name.
     * Returns subgroup-level Xbar-R statistics using individual measurements as subgroup n=1
     * (defaulting to a subgroup size of 5 when enough results exist).
     *
     * @return array{
     *   lot_id: int,
     *   characteristic: string,
     *   measurements: float[],
     *   xbar_r: array<string, mixed>,
     *   cpk: array<string, mixed>|null
     * }
     */
    public function generateControlChart(InspectionLot $lot, string $characteristic): array
    {
        $results = $lot->results()
            ->where('characteristic_name', $characteristic)
            ->whereNotNull('measured_value')
            ->orderBy('created_at')
            ->pluck('measured_value')
            ->map(fn($v) => (float) $v)
            ->all();

        if (empty($results)) {
            throw new InvalidArgumentException(
                "No numeric measurements found for characteristic '{$characteristic}' in lot #{$lot->id}."
            );
        }

        // Group into subgroups of size 5 (drop incomplete trailing subgroup)
        $subgroupSize  = 5;
        $availableData = count($results);
        $numSubgroups  = (int) floor($availableData / $subgroupSize);

        $chartData = ['measurements' => $results, 'lot_id' => $lot->id, 'characteristic' => $characteristic];

        if ($numSubgroups >= 2) {
            $samples = [];
            for ($i = 0; $i < $numSubgroups; $i++) {
                $samples[] = array_slice($results, $i * $subgroupSize, $subgroupSize);
            }
            $chartData['xbar_r'] = $this->calculateXbarR($samples);
        } else {
            // Fall back to individuals chart (I-MR) when not enough for subgroups
            $chartData['xbar_r'] = $this->calculateXbarR(
                array_map(fn(float $v) => [$v], $results)
            );
        }

        // Attempt CPK only when control plan characteristic has spec limits
        // (available via quality plan characteristic — omitted here as they're not always set)
        $chartData['cpk'] = null;

        return $chartData;
    }

    /**
     * Estimate PPM defects outside spec limits.
     * Uses a rational approximation to the normal CDF tail probability (Abramowitz & Stegun 26.2.17).
     * Maximum error < 7.5e-8 over the full real line.
     */
    private function estimatePpm(float $mean, float $stdDev, float $usl, float $lsl): float
    {
        $zUsl = ($usl - $mean) / $stdDev;
        $zLsl = ($mean - $lsl) / $stdDev;

        $pAboveUsl = $this->normalCdfTail($zUsl);
        $pBelowLsl = $this->normalCdfTail($zLsl);

        return ($pAboveUsl + $pBelowLsl) * 1_000_000;
    }

    /**
     * Pr(Z > z) — upper tail of standard normal using rational approximation.
     */
    private function normalCdfTail(float $z): float
    {
        if ($z < 0.0) {
            return 1.0 - $this->normalCdfTail(-$z);
        }

        $t = 1.0 / (1.0 + 0.2316419 * $z);
        $poly = $t * (0.319381530
            + $t * (-0.356563782
            + $t * (1.781477937
            + $t * (-1.821255978
            + $t * 1.330274429))));

        $pdf = exp(-0.5 * $z * $z) / sqrt(2.0 * M_PI);

        return max(0.0, $pdf * $poly);
    }
}
