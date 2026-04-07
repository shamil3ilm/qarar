<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tax;

use App\Http\Controllers\Controller;
use App\Models\Core\Organization;
use App\Models\Tax\TdsCertificate;
use App\Models\Tax\TcsCollection;
use App\Models\Tax\TcsConfiguration;
use App\Models\Tax\TdsConfiguration;
use App\Models\Tax\TdsDeduction;
use App\Models\Tax\TdsReturn;
use App\Models\Tax\TdsSection;
use App\Services\Tax\TdsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TdsController extends Controller
{
    public function __construct(
        private readonly TdsService $tdsService
    ) {}

    // -------------------------------------------------------------------------
    // TDS Sections (master data)
    // -------------------------------------------------------------------------

    /**
     * List all active TDS sections.
     */
    public function indexSections(): JsonResponse
    {
        $sections = TdsSection::active()->orderBy('section_code')->get();

        return $this->success($sections);
    }

    /**
     * Calculate TDS for a given amount and section.
     */
    public function calculateTds(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'deductee_type'  => ['required', 'in:vendor,employee,other'],
            'payment_amount' => ['required', 'numeric', 'min:0'],
            'section_code'   => ['required', 'string', 'max:10'],
            'has_pan'        => ['boolean'],
        ]);

        $result = $this->tdsService->calculateTds(
            $validated['deductee_type'],
            (float) $validated['payment_amount'],
            $validated['section_code'],
            $validated['has_pan'] ?? true
        );

        return $this->success($result);
    }

    // -------------------------------------------------------------------------
    // TDS Configuration
    // -------------------------------------------------------------------------

    /**
     * Get or create TDS configuration for the organization.
     */
    public function getConfiguration(): JsonResponse
    {
        $config = TdsConfiguration::where('organization_id', auth()->user()->organization_id)->first();

        if ($config === null) {
            return $this->error('TDS configuration not found.', 'NOT_CONFIGURED', 404);
        }

        return $this->success($config);
    }

    /**
     * Save TDS configuration for the organization.
     */
    public function saveConfiguration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tan'                => ['nullable', 'string', 'max:10'],
            'pan'                => ['nullable', 'string', 'max:10'],
            'deductor_name'      => ['required', 'string', 'max:200'],
            'deductor_type'      => ['required', 'string', 'max:20'],
            'responsible_person' => ['nullable', 'string', 'max:100'],
            'designation'        => ['nullable', 'string', 'max:100'],
        ]);

        $config = TdsConfiguration::updateOrCreate(
            ['organization_id' => auth()->user()->organization_id],
            $validated
        );

        return $this->success($config, 'TDS configuration saved');
    }

    // -------------------------------------------------------------------------
    // TDS Deductions
    // -------------------------------------------------------------------------

    /**
     * List TDS deductions.
     */
    public function indexDeductions(Request $request): JsonResponse
    {
        $query = TdsDeduction::where('organization_id', auth()->user()->organization_id)
            ->with('section')
            ->orderByDesc('payment_date')
            ->when($request->filled('quarter') && $request->filled('year'), fn($q) => $q->forQuarter($request->integer('quarter'), $request->integer('year')))
            ->when($request->filled('deductee_type'), fn($q) => $q->where('deductee_type', $request->input('deductee_type')));

        $deductions = $query->paginate($request->integer('per_page', 25));

        return $this->paginated($deductions);
    }

    /**
     * Record a new TDS deduction.
     */
    public function storeDeduction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'deductee_type'  => ['required', 'in:vendor,employee,other'],
            'deductee_id'    => ['required', 'integer'],
            'section_code'   => ['required', 'string', 'max:10', 'exists:tds_sections,section_code'],
            'payment_date'   => ['required', 'date'],
            'payment_amount' => ['required', 'numeric', 'min:0'],
            'has_pan'        => ['boolean'],
            'source_type'    => ['nullable', 'string', 'max:50'],
            'source_id'      => ['nullable', 'integer'],
        ]);

        $section = TdsSection::where('section_code', $validated['section_code'])->firstOrFail();

        $calculation = $this->tdsService->calculateTds(
            $validated['deductee_type'],
            (float) $validated['payment_amount'],
            $validated['section_code'],
            $validated['has_pan'] ?? true
        );

        if ($calculation['below_threshold']) {
            return $this->error(
                'Payment amount is below TDS threshold. No deduction required.',
                'BELOW_THRESHOLD',
                422
            );
        }

        $deduction = $this->tdsService->recordDeduction([
            'organization_id' => auth()->user()->organization_id,
            'deductee_type'   => $validated['deductee_type'],
            'deductee_id'     => $validated['deductee_id'],
            'section_id'      => $section->id,
            'payment_date'    => $validated['payment_date'],
            'payment_amount'  => $validated['payment_amount'],
            'tds_rate'        => $calculation['tds_rate'],
            'tds_amount'      => $calculation['tds_amount'],
            'surcharge'       => $calculation['surcharge'],
            'education_cess'  => $calculation['education_cess'],
            'net_tds'         => $calculation['net_tds'],
            'source_type'     => $validated['source_type'] ?? null,
            'source_id'       => $validated['source_id'] ?? null,
        ]);

        return $this->success($deduction->load('section'), 'TDS deduction recorded', 201);
    }

    // -------------------------------------------------------------------------
    // TDS Certificates
    // -------------------------------------------------------------------------

    /**
     * List TDS certificates for the organization.
     */
    public function indexCertificates(Request $request): JsonResponse
    {
        $query = TdsCertificate::where('organization_id', auth()->user()->organization_id)
            ->orderByDesc('generated_at')
            ->when($request->filled('quarter') && $request->filled('year'), fn($q) => $q->forQuarter($request->integer('quarter'), $request->integer('year')));

        $certificates = $query->paginate($request->integer('per_page', 15));

        return $this->paginated($certificates);
    }

    /**
     * Generate a TDS certificate (Form 16A) for a deductee.
     */
    public function generateCertificate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'deductee_type' => ['required', 'in:vendor,employee,other'],
            'deductee_id'   => ['required', 'integer'],
            'quarter'       => ['required', 'integer', 'min:1', 'max:4'],
            'year'          => ['required', 'integer', 'min:2020'],
        ]);

        $organization = Organization::findOrFail(auth()->user()->organization_id);

        $certificate = $this->tdsService->generateCertificate(
            $organization,
            $validated['deductee_type'],
            $validated['deductee_id'],
            $validated['quarter'],
            $validated['year']
        );

        return $this->success($certificate, 'TDS certificate generated', 201);
    }

    // -------------------------------------------------------------------------
    // TDS Returns (Form 26Q)
    // -------------------------------------------------------------------------

    /**
     * List quarterly TDS returns.
     */
    public function indexReturns(Request $request): JsonResponse
    {
        $returns = TdsReturn::where('organization_id', auth()->user()->organization_id)
            ->orderByDesc('financial_year')
            ->orderByDesc('quarter')
            ->paginate($request->integer('per_page', 10));

        return $this->paginated($returns);
    }

    /**
     * Prepare a quarterly TDS return.
     */
    public function prepareReturn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'quarter' => ['required', 'integer', 'min:1', 'max:4'],
            'year'    => ['required', 'integer', 'min:2020'],
        ]);

        $organization = Organization::findOrFail(auth()->user()->organization_id);

        $return = $this->tdsService->prepareQuarterlyReturn(
            $organization,
            $validated['quarter'],
            $validated['year']
        );

        return $this->success($return, 'TDS return prepared');
    }

    /**
     * File a quarterly TDS return.
     */
    public function fileReturn(Request $request, TdsReturn $tdsReturn): JsonResponse
    {
        if ($tdsReturn->organization_id !== auth()->user()->organization_id) {
            abort(403, 'Access denied.');
        }

        $validated = $request->validate([
            'acknowledgement_number' => ['required', 'string', 'max:30'],
        ]);

        $return = $this->tdsService->fileReturn($tdsReturn, $validated['acknowledgement_number']);

        return $this->success($return, 'TDS return filed successfully');
    }

    // -------------------------------------------------------------------------
    // TCS (Tax Collected at Source)
    // -------------------------------------------------------------------------

    /**
     * List TCS configurations.
     */
    public function indexTcsConfigurations(): JsonResponse
    {
        $configurations = TcsConfiguration::where('organization_id', auth()->user()->organization_id)
            ->orderBy('section_code')
            ->get();

        return $this->success($configurations);
    }

    /**
     * Create or update a TCS configuration.
     */
    public function saveTcsConfiguration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'section_code' => ['required', 'string', 'max:10'],
            'description'  => ['required', 'string', 'max:200'],
            'rate'         => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active'    => ['boolean'],
        ]);

        $config = TcsConfiguration::updateOrCreate(
            [
                'organization_id' => auth()->user()->organization_id,
                'section_code'    => $validated['section_code'],
            ],
            array_merge($validated, ['organization_id' => auth()->user()->organization_id])
        );

        return $this->success($config, 'TCS configuration saved');
    }

    /**
     * List TCS collections.
     */
    public function indexTcsCollections(Request $request): JsonResponse
    {
        $query = TcsCollection::where('organization_id', auth()->user()->organization_id)
            ->orderByDesc('collection_date')
            ->when($request->filled('deposited'), fn($q) => $q->where('deposited', $request->boolean('deposited')));

        $collections = $query->paginate($request->integer('per_page', 25));

        return $this->paginated($collections);
    }

    /**
     * Record a TCS collection.
     */
    public function storeTcsCollection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id'        => ['required', 'integer'],
            'invoice_id'        => ['nullable', 'integer'],
            'collection_date'   => ['required', 'date'],
            'collection_amount' => ['required', 'numeric', 'min:0'],
            'tcs_rate'          => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $tcsAmount = (float) bcmul(
            (string) $validated['collection_amount'],
            bcdiv((string) $validated['tcs_rate'], '100', 6),
            4
        );

        $collection = TcsCollection::create(array_merge($validated, [
            'organization_id' => auth()->user()->organization_id,
            'tcs_amount'      => $tcsAmount,
            'deposited'       => false,
        ]));

        return $this->success($collection, 'TCS collection recorded', 201);
    }
}
