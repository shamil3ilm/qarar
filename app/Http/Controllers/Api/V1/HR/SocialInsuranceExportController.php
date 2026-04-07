<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\SocialInsuranceSubmission;
use App\Services\HR\BahrainSioExportService;
use App\Services\HR\KuwaitPifssExportService;
use App\Services\HR\OmanPasiExportService;
use App\Services\HR\QatarGrsiaExportService;
use App\Services\HR\UaeGpssaExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Handles download of country-specific social insurance export files.
 * Routes submissions to the correct export service based on their scheme_code.
 */
class SocialInsuranceExportController extends Controller
{
    /** @var array<string, object> Keyed by scheme_code */
    private array $exportServices;

    public function __construct(
        OmanPasiExportService    $oman,
        KuwaitPifssExportService $kuwait,
        BahrainSioExportService  $bahrain,
        QatarGrsiaExportService  $qatar,
        UaeGpssaExportService    $uae,
    ) {
        $this->exportServices = [
            'PASI'          => $oman,
            'PIFSS'         => $kuwait,
            'SIO_NATIONALS' => $bahrain,
            'SIO_EXPATS'    => $bahrain,
            'GRSIA'         => $qatar,
            'GPSSA'         => $uae,
        ];
    }

    /**
     * Download the authority-formatted export file for a submission.
     *
     * GET /hr/social-insurance/submissions/{submission}/export
     */
    public function export(string $submission): StreamedResponse
    {
        /** @var SocialInsuranceSubmission $sub */
        $sub = SocialInsuranceSubmission::where('uuid', $submission)
            ->with('scheme')
            ->firstOrFail();

        abort_unless(
            $sub->organization_id === auth()->user()?->organization_id,
            403,
            'Access denied.'
        );

        $schemeCode = $sub->scheme?->scheme_code ?? '';

        abort_unless(
            isset($this->exportServices[$schemeCode]),
            422,
            "No export service registered for scheme code: {$schemeCode}"
        );

        return $this->exportServices[$schemeCode]->download($sub);
    }
}
