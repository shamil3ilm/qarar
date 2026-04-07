<?php

use App\Http\Controllers\Api\V1\Campaign\CampaignController;
use App\Http\Controllers\Api\V1\Campaign\SegmentController;
use App\Http\Controllers\Api\V1\CRM\ActivityController;
use App\Http\Controllers\Api\V1\CRM\CrmReportController;
use App\Http\Controllers\Api\V1\CRM\CustomerProfileController;
use App\Http\Controllers\Api\V1\CRM\LeadController;
use App\Http\Controllers\Api\V1\CRM\OpportunityController;
use App\Http\Controllers\Api\V1\CRM\ServiceTicketController;
use App\Http\Controllers\Api\V1\Sales\ContactController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CRM API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/crm
|
*/

Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Leads
    |--------------------------------------------------------------------------
    */
    Route::prefix('leads')->group(function () {
        Route::post('/', [LeadController::class, 'store'])->middleware('check.permission:crm.leads.create')->name('crm.leads.store');
        Route::post('/{lead}/convert', [LeadController::class, 'convert'])->middleware('check.permission:crm.leads.convert')->name('crm.leads.convert');

        Route::middleware('check.permission:crm.leads.view')->group(function () {
            Route::get('/', [LeadController::class, 'index'])->name('crm.leads.index');
            Route::get('/statistics', [LeadController::class, 'statistics'])->name('crm.leads.statistics');
            Route::get('/{lead}', [LeadController::class, 'show'])->name('crm.leads.show');
        });

        Route::middleware('check.permission:crm.leads.edit')->group(function () {
            Route::put('/{lead}', [LeadController::class, 'update'])->name('crm.leads.update');
            Route::post('/{lead}/status', [LeadController::class, 'changeStatus'])->name('crm.leads.status');
            Route::post('/{lead}/assign', [LeadController::class, 'assign'])->name('crm.leads.assign');
        });

        Route::middleware('check.permission:crm.leads.delete')->delete('/{lead}', [LeadController::class, 'destroy'])->name('crm.leads.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Activities
    |--------------------------------------------------------------------------
    */
    Route::prefix('activities')->group(function () {
        Route::get('/', [ActivityController::class, 'index'])
            ->middleware('check.permission:crm.activities.view')
            ->name('crm.activities.index');
        Route::post('/', [ActivityController::class, 'store'])
            ->middleware('check.permission:crm.activities.create')
            ->name('crm.activities.store');
        Route::get('/{activity}', [ActivityController::class, 'show'])
            ->middleware('check.permission:crm.activities.view')
            ->name('crm.activities.show');
        Route::put('/{activity}', [ActivityController::class, 'update'])
            ->middleware('check.permission:crm.activities.edit')
            ->name('crm.activities.update');
        Route::post('/{activity}/complete', [ActivityController::class, 'complete'])
            ->middleware('check.permission:crm.activities.complete')
            ->name('crm.activities.complete');
    });

    /*
    |--------------------------------------------------------------------------
    | Opportunities
    |--------------------------------------------------------------------------
    */
    Route::prefix('opportunities')->group(function () {
        Route::get('/', [OpportunityController::class, 'index'])->middleware('check.permission:crm.opportunities.view')->name('crm.opportunities.index');
        Route::post('/', [OpportunityController::class, 'store'])->middleware('check.permission:crm.opportunities.create')->name('crm.opportunities.store');
        Route::get('/pipeline', [OpportunityController::class, 'pipeline'])->middleware('check.permission:crm.opportunities.view')->name('crm.opportunities.pipeline');
        Route::get('/statistics', [OpportunityController::class, 'statistics'])->middleware('check.permission:crm.opportunities.view')->name('crm.opportunities.statistics');
        Route::get('/forecast', [OpportunityController::class, 'forecast'])->middleware('check.permission:crm.opportunities.view')->name('crm.opportunities.forecast');
        Route::get('/{opportunity}', [OpportunityController::class, 'show'])->middleware('check.permission:crm.opportunities.view')->name('crm.opportunities.show');
        Route::put('/{opportunity}', [OpportunityController::class, 'update'])->middleware('check.permission:crm.opportunities.edit')->name('crm.opportunities.update');
        Route::delete('/{opportunity}', [OpportunityController::class, 'destroy'])->middleware('check.permission:crm.opportunities.delete')->name('crm.opportunities.destroy');
        Route::post('/{opportunity}/stage', [OpportunityController::class, 'moveToStage'])->middleware('check.permission:crm.opportunities.edit')->name('crm.opportunities.stage');
        Route::post('/{opportunity}/win', [OpportunityController::class, 'win'])->middleware('check.permission:crm.opportunities.win')->name('crm.opportunities.win');
        Route::post('/{opportunity}/lose', [OpportunityController::class, 'lose'])->middleware('check.permission:crm.opportunities.lose')->name('crm.opportunities.lose');
        Route::post('/{opportunity}/reopen', [OpportunityController::class, 'reopen'])->middleware('check.permission:crm.opportunities.edit')->name('crm.opportunities.reopen');
    });

    /*
    |--------------------------------------------------------------------------
    | Service Tickets & SLA Policies
    |--------------------------------------------------------------------------
    */
    Route::prefix('service-tickets')->group(function (): void {
        Route::get('/', [ServiceTicketController::class, 'index'])
            ->middleware('check.permission:crm.service_tickets.view')
            ->name('crm.service_tickets.index');
        Route::post('/', [ServiceTicketController::class, 'store'])
            ->middleware('check.permission:crm.service_tickets.create')
            ->name('crm.service_tickets.store');
        Route::get('/{serviceTicket}', [ServiceTicketController::class, 'show'])
            ->middleware('check.permission:crm.service_tickets.view')
            ->name('crm.service_tickets.show');
        Route::put('/{serviceTicket}', [ServiceTicketController::class, 'update'])
            ->middleware('check.permission:crm.service_tickets.edit')
            ->name('crm.service_tickets.update');
        Route::post('/{serviceTicket}/assign', [ServiceTicketController::class, 'assign'])
            ->middleware('check.permission:crm.service_tickets.assign')
            ->name('crm.service_tickets.assign');
        Route::post('/{serviceTicket}/first-response', [ServiceTicketController::class, 'recordFirstResponse'])
            ->middleware('check.permission:crm.service_tickets.edit')
            ->name('crm.service_tickets.first_response');
        Route::post('/{serviceTicket}/resolve', [ServiceTicketController::class, 'resolve'])
            ->middleware('check.permission:crm.service_tickets.resolve')
            ->name('crm.service_tickets.resolve');
        Route::post('/{serviceTicket}/close', [ServiceTicketController::class, 'close'])
            ->middleware('check.permission:crm.service_tickets.close')
            ->name('crm.service_tickets.close');
        Route::post('/{serviceTicket}/comments', [ServiceTicketController::class, 'addComment'])
            ->middleware('check.permission:crm.service_tickets.comment')
            ->name('crm.service_tickets.comment');
    });

    /*
    |--------------------------------------------------------------------------
    | 360° Customer Profile
    |--------------------------------------------------------------------------
    */
    Route::get('customers/{contactId}/360', [CustomerProfileController::class, 'show'])
        ->middleware('check.permission:crm.customers.view')
        ->name('crm.customers.360');

    /*
    |--------------------------------------------------------------------------
    | CRM Reports
    |--------------------------------------------------------------------------
    */
    Route::prefix('reports')->name('crm.reports.')->middleware('check.permission:crm.reports.view')->group(function (): void {
        Route::get('pipeline', [CrmReportController::class, 'pipeline'])->name('pipeline');
        Route::get('win-loss', [CrmReportController::class, 'winLoss'])->name('win-loss');
        Route::get('activities', [CrmReportController::class, 'activities'])->name('activities');
        Route::get('lead-funnel', [CrmReportController::class, 'leadFunnel'])->name('lead-funnel');
    });

    Route::prefix('sla-policies')->group(function (): void {
        Route::get('/', [ServiceTicketController::class, 'indexSla'])
            ->middleware('check.permission:crm.sla_policies.view')
            ->name('crm.sla_policies.index');
        Route::post('/', [ServiceTicketController::class, 'storeSla'])
            ->middleware('check.permission:crm.sla_policies.create')
            ->name('crm.sla_policies.store');
    });

    /*
    |--------------------------------------------------------------------------
    | CRM Contacts (read-only view of Sales contacts from CRM perspective)
    |--------------------------------------------------------------------------
    */
    Route::prefix('contacts')->middleware('check.permission:crm.contacts.view')->group(function (): void {
        Route::get('/', [ContactController::class, 'index'])->name('crm.contacts.index');
        Route::get('/{uuid}', [ContactController::class, 'show'])->name('crm.contacts.show');
        // 360° profile is available at crm/customers/{contactId}/360
    });

    /*
    |--------------------------------------------------------------------------
    | CRM Accounts (companies / organisations — contacts with company_name)
    |--------------------------------------------------------------------------
    */
    Route::get('accounts', function (\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse {
        $contacts = \App\Models\Sales\Contact::where('organization_id', $request->user()->organization_id)
            ->whereNotNull('company_name')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $contacts]);
    })->middleware('check.permission:crm.contacts.view')->name('crm.accounts.index');

    /*
    |--------------------------------------------------------------------------
    | CRM Campaigns (CRM-driven campaigns, proxies Campaign module)
    |--------------------------------------------------------------------------
    */
    Route::prefix('campaigns')->middleware('check.permission:crm.campaigns.view')->group(function (): void {
        Route::get('/', [CampaignController::class, 'index'])->name('crm.campaigns.index');
        Route::get('/{id}', [CampaignController::class, 'show'])->name('crm.campaigns.show');
        Route::post('/', [CampaignController::class, 'store'])
            ->withoutMiddleware('check.permission:crm.campaigns.view')
            ->middleware('check.permission:crm.campaigns.create')
            ->name('crm.campaigns.store');
        Route::put('/{id}', [CampaignController::class, 'update'])
            ->withoutMiddleware('check.permission:crm.campaigns.view')
            ->middleware('check.permission:crm.campaigns.edit')
            ->name('crm.campaigns.update');
        Route::post('/{id}/activate', [CampaignController::class, 'activate'])
            ->withoutMiddleware('check.permission:crm.campaigns.view')
            ->middleware('check.permission:crm.campaigns.edit')
            ->name('crm.campaigns.activate');
        Route::post('/{id}/pause', [CampaignController::class, 'pause'])
            ->withoutMiddleware('check.permission:crm.campaigns.view')
            ->middleware('check.permission:crm.campaigns.edit')
            ->name('crm.campaigns.pause');
    });

    /*
    |--------------------------------------------------------------------------
    | CRM Customer Segmentation (proxies Campaign/Segment module)
    |--------------------------------------------------------------------------
    */
    Route::prefix('segments')->group(function (): void {
        Route::get('/', [SegmentController::class, 'index'])
            ->middleware('check.permission:crm.segments.view')
            ->name('crm.segments.index');
        Route::post('/', [SegmentController::class, 'store'])
            ->middleware('check.permission:crm.segments.create')
            ->name('crm.segments.store');
        Route::get('/{id}', [SegmentController::class, 'show'])
            ->middleware('check.permission:crm.segments.view')
            ->name('crm.segments.show');
        Route::put('/{id}', [SegmentController::class, 'update'])
            ->middleware('check.permission:crm.segments.edit')
            ->name('crm.segments.update');
        Route::delete('/{id}', [SegmentController::class, 'destroy'])
            ->middleware('check.permission:crm.segments.delete')
            ->name('crm.segments.destroy');
        Route::get('/{id}/members', [SegmentController::class, 'members'])
            ->middleware('check.permission:crm.segments.view')
            ->name('crm.segments.members');
    });
});
