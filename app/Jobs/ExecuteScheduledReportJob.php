<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Reports\ReportExecution;
use App\Models\Reports\SavedReport;
use App\Services\Reports\ReportExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ExecuteScheduledReportJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 600];
    public int $timeout = 600; // 10 minutes max
    public int $uniqueFor = 3600;

    public function uniqueId(): string
    {
        return $this->report->id . ':' . now()->format('Y-m-d-H');
    }

    public function __construct(
        protected SavedReport $report,
        protected ?int $userId = null
    ) {}

    public function handle(ReportExportService $exportService): void
    {
        // Idempotency: skip if a completed execution already exists within this job's timeout window
        // (prevents duplicate reports when the scheduler dispatches the job twice)
        $recentCompleted = ReportExecution::withoutGlobalScopes()
            ->where('saved_report_id', $this->report->id)
            ->where('status', ReportExecution::STATUS_COMPLETED)
            ->where('started_at', '>=', now()->subSeconds($this->timeout))
            ->exists();

        if ($recentCompleted) {
            Log::info('ExecuteScheduledReportJob: skipping duplicate dispatch — already completed', [
                'report_id' => $this->report->id,
            ]);
            return;
        }

        $execution = ReportExecution::withoutGlobalScopes()->create([
            'saved_report_id' => $this->report->id,
            'organization_id' => $this->report->organization_id,
            'executed_by' => $this->userId,
            'parameters' => $this->report->parameters,
            'format' => $this->report->export_format,
            'status' => ReportExecution::STATUS_PROCESSING,
            'started_at' => now(),
        ]);

        try {
            Log::info("Executing scheduled report: {$this->report->name}", [
                'report_id' => $this->report->id,
                'execution_id' => $execution->id,
            ]);

            // Generate the report data
            $reportData = $this->generateReportData();

            if (empty($reportData)) {
                $execution->markAsFailed('No data returned from report');
                return;
            }

            // Export to file
            $result = $exportService->export(
                $this->report->report_type,
                $reportData,
                $this->report->export_format,
                $this->report->organization
            );

            // Store the file
            $filename = $this->generateFilename();
            $path = "reports/{$this->report->organization_id}/{$filename}";

            Storage::disk('local')->put($path, $result['content']);

            DB::transaction(function () use ($execution, $path, $reportData) {
                $execution->markAsCompleted(
                    $path,
                    Storage::disk('local')->size($path),
                    $reportData['row_count'] ?? count($reportData['data'] ?? [])
                );

                // Update report's last run time
                $this->report->update([
                    'last_run_at' => now(),
                    'next_run_at' => $this->calculateNextRun(),
                ]);
            });

            // Send email notifications if configured
            $this->sendNotifications($execution, $path);

            Log::info("Report executed successfully: {$this->report->name}", [
                'execution_id' => $execution->id,
                'file_path' => $path,
            ]);

        } catch (\Throwable $e) {
            Log::error("Report execution failed: {$this->report->name}", [
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $execution->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    protected function generateReportData(): array
    {
        $service = $this->getReportService();

        if (!$service) {
            throw new \Exception("No service found for report type: {$this->report->report_type}");
        }

        $service->setContext(
            $this->report->organization_id,
            $this->report->parameters['branch_id'] ?? null
        );

        $params = $this->report->parameters;

        // Add default date parameters if not set
        if (!isset($params['start_date'])) {
            $params['start_date'] = $this->getDefaultStartDate();
        }
        if (!isset($params['end_date'])) {
            $params['end_date'] = now()->toDateString();
        }
        if (!isset($params['as_of_date'])) {
            $params['as_of_date'] = now()->toDateString();
        }

        return match ($this->report->report_type) {
            // Financial Reports
            'balance_sheet' => $service->getBalanceSheet($params['as_of_date']),
            'income_statement', 'profit_loss' => $service->getProfitAndLoss($params['start_date'], $params['end_date']),
            'trial_balance' => $service->getTrialBalance($params['as_of_date']),
            'cash_flow' => $service->getCashFlow($params['start_date'], $params['end_date']),
            'aged_receivables' => $service->getReceivableAging($params['as_of_date']),
            'aged_payables' => $service->getPayableAging($params['as_of_date']),

            // Inventory Reports
            'stock_valuation' => $service->generateStockValuation($params),
            'stock_movement' => $service->generateStockMovement($params['start_date'], $params['end_date'], $params),
            'low_stock' => $service->generateLowStockReport($params),

            // Sales Reports
            'sales_by_customer' => $service->generateSalesByCustomer($params['start_date'], $params['end_date'], $params),
            'sales_by_product' => $service->generateSalesByProduct($params['start_date'], $params['end_date'], $params),
            'sales_trend' => $service->generateSalesTrend($params['start_date'], $params['end_date'], $params),

            // HR Reports
            'hr_headcount' => $service->generateHeadcountReport($params['as_of_date'], $params['department_id'] ?? null),
            'hr_turnover' => $service->generateTurnoverReport($params['start_date'], $params['end_date']),
            'hr_attendance' => $service->generateAttendanceReport($params['start_date'], $params['end_date'], $params['department_id'] ?? null),
            'hr_leave' => $service->generateLeaveReport($params['start_date'], $params['end_date'], $params['department_id'] ?? null),
            'hr_payroll' => $service->generatePayrollReport($params['start_date'], $params['end_date']),

            default => throw new \Exception("Unknown report type: {$this->report->report_type}"),
        };
    }

    protected function getReportService(): mixed
    {
        return match (true) {
            str_starts_with($this->report->report_type, 'hr_') => app(\App\Services\HR\HRReportService::class),
            in_array($this->report->report_type, ['stock_valuation', 'stock_movement', 'low_stock', 'inventory_turnover', 'expiry_report']) =>
                app(\App\Services\Reports\InventoryReportService::class),
            in_array($this->report->report_type, ['sales_by_customer', 'sales_by_product', 'sales_by_salesperson', 'sales_trend']) =>
                app(\App\Services\Reports\SalesReportService::class),
            default => app(\App\Services\Reports\FinancialReportService::class),
        };
    }

    protected function getDefaultStartDate(): string
    {
        return match ($this->report->schedule) {
            'daily' => now()->subDay()->toDateString(),
            'weekly' => now()->subWeek()->toDateString(),
            'monthly' => now()->subMonth()->startOfMonth()->toDateString(),
            'quarterly' => now()->subQuarter()->startOfQuarter()->toDateString(),
            'yearly' => now()->subYear()->startOfYear()->toDateString(),
            default => now()->startOfMonth()->toDateString(),
        };
    }

    protected function calculateNextRun(): ?\DateTimeInterface
    {
        if (!$this->report->is_scheduled) {
            return null;
        }

        $scheduleTime = $this->report->schedule_time ?? '06:00';
        $timeParts = explode(':', $scheduleTime);
        $hour = (int) ($timeParts[0] ?? 6);
        $minute = (int) ($timeParts[1] ?? 0);

        return match ($this->report->schedule) {
            'daily' => now()->addDay()->setTime($hour, $minute),
            'weekly' => now()->addWeek()->startOfWeek()->setTime($hour, $minute),
            'monthly' => now()->addMonth()->startOfMonth()->setTime($hour, $minute),
            'quarterly' => now()->addQuarter()->startOfQuarter()->setTime($hour, $minute),
            default => null,
        };
    }

    protected function generateFilename(): string
    {
        $timestamp = now()->format('Y-m-d_His');
        $slug = str_replace(' ', '_', strtolower($this->report->name));

        return "{$slug}_{$timestamp}.{$this->report->export_format}";
    }

    protected function sendNotifications(ReportExecution $execution, string $filePath): void
    {
        $recipients = $this->report->email_recipients ?? [];

        if (empty($recipients)) {
            return;
        }

        try {
            if (!Storage::disk('local')->exists($filePath)) {
                Log::error('Report file missing', ['path' => $filePath]);
                return;
            }

            foreach ($recipients as $email) {
                Mail::send(
                    'emails.reports.scheduled-report',
                    [
                        'report' => $this->report,
                        'execution' => $execution,
                        'organization' => $this->report->organization,
                    ],
                    function ($message) use ($email, $filePath) {
                        $message->to($email)
                            ->subject("Scheduled Report: {$this->report->name}")
                            ->attach(Storage::disk('local')->path($filePath));
                    }
                );
            }

            Log::info("Report notifications sent", [
                'report_id' => $this->report->id,
                'recipients' => $recipients,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to send report notifications", [
                'report_id' => $this->report->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Scheduled report job failed permanently", [
            'report_id' => $this->report->id,
            'report_name' => $this->report->name,
            'error' => $exception->getMessage(),
        ]);
    }
}
