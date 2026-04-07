<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\DocumentDownloadToken;
use App\Models\Purchase\Bill;
use App\Models\Sales\Invoice;
use App\Services\Core\DocumentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class DocumentDownloadController extends Controller
{
    public function __construct(
        private DocumentService $documentService,
    ) {}

    /**
     * Generate a signed, time-limited download link for an invoice or bill.
     *
     * Requires authenticated user.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'document_id'   => 'required|integer',
            'document_type' => 'required|string|in:invoice,bill,receipt,payslip',
            'ttl_hours'     => 'nullable|integer|min:1|max:168',
        ]);

        $organizationId = $this->organizationId($request);

        // Verify the document exists within the authenticated organization before issuing a token.
        try {
            $this->documentService->resolveDocument(
                $validated['document_type'],
                (int) $validated['document_id'],
                (int) $organizationId
            );
        } catch (ModelNotFoundException) {
            return $this->error(
                'The requested document was not found in your organization.',
                'DOCUMENT_NOT_FOUND',
                404
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'VALIDATION_ERROR', 422);
        }

        $ttlHours = isset($validated['ttl_hours'])
            ? (int) $validated['ttl_hours']
            : DocumentService::DEFAULT_LINK_TTL_HOURS;

        $tokenModel = $this->documentService->generateDownloadToken(
            documentType:   $validated['document_type'],
            documentId:     (int) $validated['document_id'],
            organizationId: (int) $organizationId,
            generatedBy:    auth()->id(),
            ttlHours:       $ttlHours,
        );

        $downloadUrl = rtrim(config('app.url'), '/') . '/api/v1/documents/download/' . $tokenModel->token;

        return $this->success(
            [
                'download_url' => $downloadUrl,
                'expires_at'   => $tokenModel->expires_at->toIso8601String(),
                'token'        => $tokenModel->token,
            ],
            'Download link generated successfully.'
        );
    }

    /**
     * Stream the document PDF for a valid single-use token.
     *
     * This endpoint is intentionally public — authentication is provided by the token itself.
     */
    public function download(string $token): Response|JsonResponse
    {
        $tokenModel = DocumentDownloadToken::where('token', $token)->first();

        if ($tokenModel === null) {
            return $this->notFound('Download link not found.');
        }

        if (!$tokenModel->isValid()) {
            return $this->error('This download link has expired.', 'LINK_EXPIRED', 410);
        }

        $tokenModel->recordAccess();

        try {
            $document = $this->documentService->resolveDocument(
                $tokenModel->document_type,
                (int) $tokenModel->document_id,
                (int) $tokenModel->organization_id
            );

            [$pdfContent, $filename] = $this->buildPdf($tokenModel->document_type, $document);

            return response($pdfContent, 200, [
                'Content-Disposition' => "attachment; filename=\"{$filename}.pdf\"",
                'Content-Type'        => 'application/pdf',
            ]);
        } catch (\Throwable $e) {
            Log::error('DocumentDownloadController: failed to generate PDF', [
                'document_id'   => $tokenModel->document_id,
                'document_type' => $tokenModel->document_type,
                'error'         => $e->getMessage(),
                'token'         => $token,
            ]);

            return $this->serverError('Unable to generate the document at this time.');
        }
    }

    /**
     * Dispatch PDF generation and derive a sensible filename.
     *
     * @return array{0: string, 1: string}  [pdfBinary, filename]
     */
    private function buildPdf(string $documentType, Invoice|Bill $document): array
    {
        return match ($documentType) {
            DocumentDownloadToken::TYPE_INVOICE,
            DocumentDownloadToken::TYPE_RECEIPT => [
                $this->documentService->generateInvoicePdf($document),
                'invoice-' . ($document->invoice_number ?? $document->id),
            ],

            DocumentDownloadToken::TYPE_BILL => [
                $this->documentService->generateBillPdf($document),
                'bill-' . ($document->bill_number ?? $document->id),
            ],

            default => throw new \InvalidArgumentException("Cannot build PDF for type: {$documentType}"),
        };
    }
}
