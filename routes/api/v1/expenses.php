<?php

use App\Http\Controllers\Api\V1\Expense\ExpenseBudgetController;
use App\Http\Controllers\Api\V1\Expense\ExpenseCategoryController;
use App\Http\Controllers\Api\V1\Expense\ExpenseController;
use App\Http\Controllers\Api\V1\Expense\ExpenseReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Expense Tracking Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/expenses
|
*/

Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Expense Categories
    |--------------------------------------------------------------------------
    */
    Route::prefix('categories')->group(function () {
        Route::get('/', [ExpenseCategoryController::class, 'index'])
            ->middleware('check.permission:expenses.categories.view')
            ->name('expense-categories.index');

        Route::post('/', [ExpenseCategoryController::class, 'store'])
            ->middleware('check.permission:expenses.categories.create')
            ->name('expense-categories.store');

        Route::get('/{expenseCategory}', [ExpenseCategoryController::class, 'show'])
            ->middleware('check.permission:expenses.categories.view')
            ->name('expense-categories.show');

        Route::put('/{expenseCategory}', [ExpenseCategoryController::class, 'update'])
            ->middleware('check.permission:expenses.categories.update')
            ->name('expense-categories.update');

        Route::delete('/{expenseCategory}', [ExpenseCategoryController::class, 'destroy'])
            ->middleware('check.permission:expenses.categories.delete')
            ->name('expense-categories.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Recurring Expenses (must be before /{expense} wildcard routes)
    |--------------------------------------------------------------------------
    */
    Route::prefix('recurring')->group(function () {
        Route::get('/', [ExpenseController::class, 'recurringIndex'])
            ->middleware('check.permission:expenses.recurring.view')
            ->name('expenses.recurring.index');

        Route::post('/', [ExpenseController::class, 'createRecurring'])
            ->middleware('check.permission:expenses.recurring.create')
            ->name('expenses.recurring.store');

        Route::post('/process', [ExpenseController::class, 'processRecurring'])
            ->middleware('check.permission:expenses.recurring.process')
            ->name('expenses.recurring.process');
    });

    /*
    |--------------------------------------------------------------------------
    | Expense Reports (must be before /{expense} wildcard routes)
    |--------------------------------------------------------------------------
    */
    Route::prefix('reports')->group(function () {
        Route::get('/', [ExpenseReportController::class, 'index'])
            ->middleware('check.permission:expenses.reports.view')
            ->name('expense-reports.index');

        Route::post('/', [ExpenseReportController::class, 'store'])
            ->middleware('check.permission:expenses.reports.create')
            ->name('expense-reports.store');

        Route::get('/{expenseReport}', [ExpenseReportController::class, 'show'])
            ->middleware('check.permission:expenses.reports.view')
            ->name('expense-reports.show');

        Route::post('/{expenseReport}/add-expenses', [ExpenseReportController::class, 'addExpenses'])
            ->middleware('check.permission:expenses.reports.update')
            ->name('expense-reports.add-expenses');

        Route::post('/{expenseReport}/submit', [ExpenseReportController::class, 'submit'])
            ->middleware('check.permission:expenses.reports.submit')
            ->name('expense-reports.submit');

        Route::post('/{expenseReport}/approve', [ExpenseReportController::class, 'approve'])
            ->middleware('check.permission:expenses.reports.approve')
            ->name('expense-reports.approve');

        Route::post('/{expenseReport}/reject', [ExpenseReportController::class, 'reject'])
            ->middleware('check.permission:expenses.reports.approve')
            ->name('expense-reports.reject');

        Route::post('/{expenseReport}/reimburse', [ExpenseReportController::class, 'reimburse'])
            ->middleware('check.permission:expenses.reports.reimburse')
            ->name('expense-reports.reimburse');
    });

    /*
    |--------------------------------------------------------------------------
    | Expense Budgets (must be before /{expense} wildcard routes)
    |--------------------------------------------------------------------------
    */
    Route::prefix('budgets')->group(function () {
        Route::get('/', [ExpenseBudgetController::class, 'index'])
            ->middleware('check.permission:expenses.budgets.view')
            ->name('expense-budgets.index');

        Route::post('/', [ExpenseBudgetController::class, 'store'])
            ->middleware('check.permission:expenses.budgets.create')
            ->name('expense-budgets.store');

        Route::get('/check', [ExpenseBudgetController::class, 'checkBudget'])
            ->middleware('check.permission:expenses.budgets.view')
            ->name('expense-budgets.check');

        Route::get('/utilization', [ExpenseBudgetController::class, 'utilization'])
            ->middleware('check.permission:expenses.budgets.view')
            ->name('expense-budgets.utilization');

        Route::get('/{expenseBudget}', [ExpenseBudgetController::class, 'show'])
            ->middleware('check.permission:expenses.budgets.view')
            ->name('expense-budgets.show');

        Route::put('/{expenseBudget}', [ExpenseBudgetController::class, 'update'])
            ->middleware('check.permission:expenses.budgets.update')
            ->name('expense-budgets.update');

        Route::delete('/{expenseBudget}', [ExpenseBudgetController::class, 'destroy'])
            ->middleware('check.permission:expenses.budgets.delete')
            ->name('expense-budgets.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Expenses (wildcard routes must come last)
    |--------------------------------------------------------------------------
    */
    Route::get('/', [ExpenseController::class, 'index'])
        ->middleware('check.permission:expenses.view')
        ->name('expenses.index');

    Route::post('/', [ExpenseController::class, 'store'])
        ->middleware('check.permission:expenses.create')
        ->name('expenses.store');

    Route::get('/{expense}', [ExpenseController::class, 'show'])
        ->middleware('check.permission:expenses.view')
        ->name('expenses.show');

    Route::put('/{expense}', [ExpenseController::class, 'update'])
        ->middleware('check.permission:expenses.update')
        ->name('expenses.update');

    Route::delete('/{expense}', [ExpenseController::class, 'destroy'])
        ->middleware('check.permission:expenses.delete')
        ->name('expenses.destroy');

    Route::post('/{expense}/submit', [ExpenseController::class, 'submit'])
        ->middleware('check.permission:expenses.submit')
        ->name('expenses.submit');

    Route::post('/{expense}/review', [ExpenseController::class, 'review'])
        ->middleware('check.permission:expenses.approve')
        ->name('expenses.review');
});
