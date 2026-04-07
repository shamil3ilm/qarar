<?php

declare(strict_types=1);

namespace App\Services\Core;

use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalYear;
use App\Models\Core\ModuleReadinessCheck;
use App\Models\Core\ModuleReadinessResult;
use App\Models\HR\Department;
use App\Models\HR\Designation;
use App\Models\HR\EmployeeSalary;
use App\Models\HR\SalaryStructure;
use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Sales\Contact;
use App\Models\Tax\TaxCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ModuleReadinessService
{
    /**
     * Run all active readiness checks for the specified module and persist the result.
     *
     * @return array{overall_status: string, results: array<int, array<string, mixed>>}
     */
    public function runChecks(int $organizationId, string $module, int $userId): array
    {
        $checks   = $this->executeChecksForModule($organizationId, $module);
        $overall  = $this->deriveOverallStatus($checks);
        $savedResult = $this->saveResult($organizationId, $module, $checks, $userId);

        return [
            'overall_status' => $overall,
            'results'        => $checks,
            'result_id'      => $savedResult->uuid,
            'run_at'         => $savedResult->run_at,
        ];
    }

    /**
     * Get all registered checks for a module from the DB.
     */
    public function getChecksForModule(string $module): array
    {
        return ModuleReadinessCheck::where('module', $module)
            ->where('is_active', true)
            ->orderBy('order')
            ->get()
            ->toArray();
    }

    /**
     * Get the most recent readiness result for an org + module.
     */
    public function getLastResult(int $organizationId, string $module): ?ModuleReadinessResult
    {
        return ModuleReadinessResult::where('organization_id', $organizationId)
            ->where('module', $module)
            ->latest('run_at')
            ->first();
    }

    /**
     * Persist a readiness run result.
     */
    public function saveResult(
        int $organizationId,
        string $module,
        array $results,
        int $userId
    ): ModuleReadinessResult {
        $overall = $this->deriveOverallStatus($results);

        return ModuleReadinessResult::create([
            'uuid'            => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'module'          => $module,
            'run_by'          => $userId,
            'run_at'          => Carbon::now(),
            'overall_status'  => $overall,
            'results'         => $results,
        ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Dispatch the built-in checks for a module and return the result rows.
     *
     * @return array<int, array<string, mixed>>
     */
    private function executeChecksForModule(int $organizationId, string $module): array
    {
        return match ($module) {
            'accounting'    => $this->checkAccounting($organizationId),
            'hr'            => $this->checkHr($organizationId),
            'inventory'     => $this->checkInventory($organizationId),
            'sales'         => $this->checkSales($organizationId),
            'purchase'      => $this->checkPurchase($organizationId),
            'manufacturing' => $this->checkManufacturing($organizationId),
            default         => [],
        };
    }

    private function deriveOverallStatus(array $results): string
    {
        $statuses = array_column($results, 'status');

        if (in_array('fail', $statuses, true)) {
            return 'fail';
        }

        if (in_array('warning', $statuses, true)) {
            return 'warning';
        }

        return 'pass';
    }

    private function pass(string $checkKey, string $message, int $count): array
    {
        return ['check_key' => $checkKey, 'status' => 'pass', 'message' => $message, 'count' => $count];
    }

    private function fail(string $checkKey, string $message, int $count): array
    {
        return ['check_key' => $checkKey, 'status' => 'fail', 'message' => $message, 'count' => $count];
    }

    private function warning(string $checkKey, string $message, int $count): array
    {
        return ['check_key' => $checkKey, 'status' => 'warning', 'message' => $message, 'count' => $count];
    }

    // -------------------------------------------------------------------------
    // Module-specific checks
    // -------------------------------------------------------------------------

    private function checkAccounting(int $organizationId): array
    {
        $results = [];

        // has_fiscal_year
        $fiscalYearCount = FiscalYear::where('organization_id', $organizationId)->count();
        $results[] = $fiscalYearCount > 0
            ? $this->pass('has_fiscal_year', 'At least one fiscal year is defined.', $fiscalYearCount)
            : $this->fail('has_fiscal_year', 'No fiscal year found. Please create at least one fiscal year.', 0);

        // has_chart_of_accounts (at least 10 accounts)
        $accountCount = Account::where('organization_id', $organizationId)->count();
        if ($accountCount >= 10) {
            $results[] = $this->pass('has_chart_of_accounts', "Chart of accounts has {$accountCount} accounts.", $accountCount);
        } elseif ($accountCount > 0) {
            $results[] = $this->warning('has_chart_of_accounts', "Chart of accounts has only {$accountCount} accounts. At least 10 are recommended.", $accountCount);
        } else {
            $results[] = $this->fail('has_chart_of_accounts', 'No accounts found in the chart of accounts.', 0);
        }

        // has_bank_account
        $bankAccountCount = DB::table('bank_accounts')
            ->where('organization_id', $organizationId)
            ->count();
        $results[] = $bankAccountCount > 0
            ? $this->pass('has_bank_account', 'At least one bank account is configured.', $bankAccountCount)
            : $this->warning('has_bank_account', 'No bank accounts configured. Bank reconciliation will not be available.', 0);

        // opening_balances_entered — proxy: any journal entry of type 'opening'
        $openingEntries = DB::table('journal_entries')
            ->where('organization_id', $organizationId)
            ->where('type', 'opening')
            ->count();
        $results[] = $openingEntries > 0
            ? $this->pass('opening_balances_entered', 'Opening balances have been entered.', $openingEntries)
            : $this->warning('opening_balances_entered', 'No opening balance journal entries found.', 0);

        return $results;
    }

    private function checkHr(int $organizationId): array
    {
        $results = [];

        // has_departments
        $deptCount = Department::where('organization_id', $organizationId)->count();
        $results[] = $deptCount > 0
            ? $this->pass('has_departments', "Found {$deptCount} department(s).", $deptCount)
            : $this->fail('has_departments', 'No departments found. Create at least one department before activating HR.', 0);

        // has_designations
        $desigCount = Designation::where('organization_id', $organizationId)->count();
        $results[] = $desigCount > 0
            ? $this->pass('has_designations', "Found {$desigCount} designation(s).", $desigCount)
            : $this->fail('has_designations', 'No designations found. Create designations to assign to employees.', 0);

        // has_salary_structures
        $structureCount = SalaryStructure::where('organization_id', $organizationId)->count();
        $results[] = $structureCount > 0
            ? $this->pass('has_salary_structures', "Found {$structureCount} salary structure(s).", $structureCount)
            : $this->warning('has_salary_structures', 'No salary structures defined. Payroll processing will require them.', 0);

        // employees_have_salaries
        $employeesWithSalary = EmployeeSalary::whereHas(
            'employee',
            fn ($q) => $q->where('organization_id', $organizationId)
        )->count();
        $results[] = $employeesWithSalary > 0
            ? $this->pass('employees_have_salaries', "{$employeesWithSalary} employee(s) have salary records.", $employeesWithSalary)
            : $this->warning('employees_have_salaries', 'No employee salary records found. Assign salaries before running payroll.', 0);

        return $results;
    }

    private function checkInventory(int $organizationId): array
    {
        $results = [];

        // has_warehouses
        $warehouseCount = Warehouse::where('organization_id', $organizationId)->count();
        $results[] = $warehouseCount > 0
            ? $this->pass('has_warehouses', "Found {$warehouseCount} warehouse(s).", $warehouseCount)
            : $this->fail('has_warehouses', 'No warehouses found. At least one warehouse is required for inventory.', 0);

        // has_categories
        $categoryCount = Category::where('organization_id', $organizationId)->count();
        $results[] = $categoryCount > 0
            ? $this->pass('has_categories', "Found {$categoryCount} product categorie(s).", $categoryCount)
            : $this->warning('has_categories', 'No product categories defined. Categories help organise your inventory.', 0);

        // has_products
        $productCount = Product::where('organization_id', $organizationId)->count();
        $results[] = $productCount > 0
            ? $this->pass('has_products', "Found {$productCount} product(s).", $productCount)
            : $this->warning('has_products', 'No products found. Add products to start tracking inventory.', 0);

        return $results;
    }

    private function checkSales(int $organizationId): array
    {
        $results = [];

        // has_contacts (customers)
        $contactCount = Contact::where('organization_id', $organizationId)
            ->whereIn('contact_type', [Contact::TYPE_CUSTOMER, Contact::TYPE_BOTH])
            ->count();
        $results[] = $contactCount > 0
            ? $this->pass('has_contacts', "Found {$contactCount} customer contact(s).", $contactCount)
            : $this->warning('has_contacts', 'No customer contacts found. Add customers to start creating invoices.', 0);

        // has_tax_rates
        $taxRateCount = TaxCategory::where('organization_id', $organizationId)->count();
        $results[] = $taxRateCount > 0
            ? $this->pass('has_tax_rates', "Found {$taxRateCount} tax categorie(s).", $taxRateCount)
            : $this->warning('has_tax_rates', 'No tax rates configured. Tax will not be applied to invoices.', 0);

        return $results;
    }

    private function checkPurchase(int $organizationId): array
    {
        $results = [];

        // has_contacts (vendors)
        $vendorCount = Contact::where('organization_id', $organizationId)
            ->whereIn('contact_type', [Contact::TYPE_SUPPLIER, Contact::TYPE_BOTH])
            ->count();
        $results[] = $vendorCount > 0
            ? $this->pass('has_contacts', "Found {$vendorCount} vendor contact(s).", $vendorCount)
            : $this->warning('has_contacts', 'No vendor contacts found. Add vendors before creating purchase orders.', 0);

        // has_warehouses
        $warehouseCount = Warehouse::where('organization_id', $organizationId)->count();
        $results[] = $warehouseCount > 0
            ? $this->pass('has_warehouses', "Found {$warehouseCount} warehouse(s) for goods receipt.", $warehouseCount)
            : $this->fail('has_warehouses', 'No warehouses found. Goods receipts require a destination warehouse.', 0);

        return $results;
    }

    private function checkManufacturing(int $organizationId): array
    {
        $results = [];

        // has_products
        $productCount = Product::where('organization_id', $organizationId)->count();
        $results[] = $productCount > 0
            ? $this->pass('has_products', "Found {$productCount} product(s).", $productCount)
            : $this->fail('has_products', 'No products found. Products are required to create Bills of Materials.', 0);

        // has_bom_for_products
        $bomCount = BomTemplate::where('organization_id', $organizationId)->count();
        $results[] = $bomCount > 0
            ? $this->pass('has_bom_for_products', "Found {$bomCount} Bill(s) of Material.", $bomCount)
            : $this->warning('has_bom_for_products', 'No Bills of Material found. Define BOMs before creating work orders.', 0);

        return $results;
    }
}
