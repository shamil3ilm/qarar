<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Accounting\BankStatementImport;
use App\Models\Accounting\BankTransaction;
use App\Services\Accounting\BankStatementImportService;
use App\Services\Accounting\Camt053Parser;
use App\Services\Accounting\Mt940Parser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class BankStatementImportTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private Mt940Parser $mt940;
    private Camt053Parser $camt053;
    private BankStatementImportService $service;

    private static string $mt940Sample = ":20:STMT0001\r\n:25:NL91ABNA0417164300\r\n:28C:00001/001\r\n:60F:C230101EUR1000,00\r\n:61:2301020102CR500,00NTRFMSC0001\r\n:86:Payment received from client\r\n:61:2301030103DR200,00NTRFMSC0002\r\n:86:Supplier payment\r\n:62F:C230103EUR1300,00\r\n";

    private static string $camt053Sample = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.02">
  <BkToCstmrStmt>
    <Stmt>
      <Id>STMT-2023-001</Id>
      <Acct><Id><IBAN>NL91ABNA0417164300</IBAN></Id></Acct>
      <Bal>
        <Tp><CdOrPrtry><Cd>OPBD</Cd></CdOrPrtry></Tp>
        <Amt Ccy="EUR">1000.00</Amt>
        <CdtDbtInd>CRDT</CdtDbtInd>
        <Dt><Dt>2023-01-01</Dt></Dt>
      </Bal>
      <Bal>
        <Tp><CdOrPrtry><Cd>CLBD</Cd></CdOrPrtry></Tp>
        <Amt Ccy="EUR">1300.00</Amt>
        <CdtDbtInd>CRDT</CdtDbtInd>
        <Dt><Dt>2023-01-03</Dt></Dt>
      </Bal>
      <Ntry>
        <Amt Ccy="EUR">500.00</Amt>
        <CdtDbtInd>CRDT</CdtDbtInd>
        <BookgDt><Dt>2023-01-02</Dt></BookgDt>
        <ValDt><Dt>2023-01-02</Dt></ValDt>
        <NtryDtls><TxDtls><RmtInf><Ustrd>Payment received</Ustrd></RmtInf></TxDtls></NtryDtls>
      </Ntry>
      <Ntry>
        <Amt Ccy="EUR">200.00</Amt>
        <CdtDbtInd>DBIT</CdtDbtInd>
        <BookgDt><Dt>2023-01-03</Dt></BookgDt>
        <ValDt><Dt>2023-01-03</Dt></ValDt>
        <NtryDtls><TxDtls><RmtInf><Ustrd>Supplier payment</Ustrd></RmtInf></TxDtls></NtryDtls>
      </Ntry>
    </Stmt>
  </BkToCstmrStmt>
</Document>
XML;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->setUpAuthenticatedUser();
        $this->mt940   = app(Mt940Parser::class);
        $this->camt053 = app(Camt053Parser::class);
        $this->service = app(BankStatementImportService::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MT940 Parser unit tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_mt940_parses_account_number(): void
    {
        $result = $this->mt940->parse(self::$mt940Sample);

        $this->assertEquals('NL91ABNA0417164300', $result['account_number']);
    }

    public function test_mt940_parses_opening_balance(): void
    {
        $result = $this->mt940->parse(self::$mt940Sample);

        $this->assertEquals('C', $result['opening_balance']['indicator']);
        $this->assertEquals(1000.0, $result['opening_balance']['amount']);
        $this->assertEquals('EUR', $result['opening_balance']['currency']);
        $this->assertEquals('2023-01-01', $result['opening_balance']['date']);
    }

    public function test_mt940_parses_closing_balance(): void
    {
        $result = $this->mt940->parse(self::$mt940Sample);

        $this->assertEquals(1300.0, $result['closing_balance']['amount']);
        $this->assertEquals('2023-01-03', $result['closing_balance']['date']);
    }

    public function test_mt940_parses_two_transactions(): void
    {
        $result = $this->mt940->parse(self::$mt940Sample);

        $this->assertCount(2, $result['transactions']);
    }

    public function test_mt940_first_transaction_is_credit(): void
    {
        $txs = $this->mt940->parse(self::$mt940Sample)['transactions'];

        $this->assertEquals('C', $txs[0]['indicator']);
        $this->assertEquals(500.0, $txs[0]['amount']);
        $this->assertEquals('2023-01-02', $txs[0]['value_date']);
        $this->assertStringContainsString('Payment received', $txs[0]['narrative']);
    }

    public function test_mt940_second_transaction_is_debit(): void
    {
        $txs = $this->mt940->parse(self::$mt940Sample)['transactions'];

        $this->assertEquals('D', $txs[1]['indicator']);
        $this->assertEquals(200.0, $txs[1]['amount']);
    }

    public function test_mt940_throws_on_empty_content(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->mt940->parse('');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CAMT.053 Parser unit tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_camt053_parses_account_number(): void
    {
        $result = $this->camt053->parse(self::$camt053Sample);

        $this->assertEquals('NL91ABNA0417164300', $result['account_number']);
    }

    public function test_camt053_parses_opening_balance(): void
    {
        $result = $this->camt053->parse(self::$camt053Sample);

        $this->assertEquals('C', $result['opening_balance']['indicator']);
        $this->assertEquals(1000.0, $result['opening_balance']['amount']);
        $this->assertEquals('2023-01-01', $result['opening_balance']['date']);
    }

    public function test_camt053_parses_two_entries(): void
    {
        $result = $this->camt053->parse(self::$camt053Sample);

        $this->assertCount(2, $result['transactions']);
    }

    public function test_camt053_first_entry_is_credit(): void
    {
        $txs = $this->camt053->parse(self::$camt053Sample)['transactions'];

        $this->assertEquals('C', $txs[0]['indicator']);
        $this->assertEquals(500.0, $txs[0]['amount']);
        $this->assertEquals('2023-01-02', $txs[0]['value_date']);
        $this->assertStringContainsString('Payment received', $txs[0]['narrative']);
    }

    public function test_camt053_throws_on_invalid_xml(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->camt053->parse('not xml at all');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BankStatementImportService integration tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_import_mt940_creates_import_record(): void
    {
        $bankAccount = \App\Models\Accounting\BankAccount::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $import = $this->service->import([
            'organization_id' => $this->organization->id,
            'bank_account_id' => $bankAccount->id,
            'file_name'       => 'statement.mt940',
            'file_type'       => BankStatementImport::FILE_TYPE_MT940,
            'content'         => self::$mt940Sample,
        ], $this->user->id);

        $this->assertEquals(BankStatementImport::STATUS_COMPLETED, $import->status);
        $this->assertEquals(2, $import->total_transactions);
        $this->assertEquals(2, $import->imported_transactions);
        $this->assertEquals(0, $import->duplicate_transactions);
    }

    public function test_import_mt940_creates_bank_transactions(): void
    {
        $bankAccount = \App\Models\Accounting\BankAccount::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->service->import([
            'organization_id' => $this->organization->id,
            'bank_account_id' => $bankAccount->id,
            'file_name'       => 'statement.mt940',
            'file_type'       => BankStatementImport::FILE_TYPE_MT940,
            'content'         => self::$mt940Sample,
        ], $this->user->id);

        $this->assertEquals(2, BankTransaction::where('bank_account_id', $bankAccount->id)->count());
    }

    public function test_import_detects_duplicates(): void
    {
        $bankAccount = \App\Models\Accounting\BankAccount::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $importData = [
            'organization_id' => $this->organization->id,
            'bank_account_id' => $bankAccount->id,
            'file_name'       => 'statement.mt940',
            'file_type'       => BankStatementImport::FILE_TYPE_MT940,
            'content'         => self::$mt940Sample,
        ];

        $this->service->import($importData, $this->user->id);
        $second = $this->service->import($importData, $this->user->id);

        $this->assertEquals(2, $second->duplicate_transactions);
        $this->assertEquals(0, $second->imported_transactions);
    }

    public function test_import_camt053_creates_import_record(): void
    {
        $bankAccount = \App\Models\Accounting\BankAccount::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $import = $this->service->import([
            'organization_id' => $this->organization->id,
            'bank_account_id' => $bankAccount->id,
            'file_name'       => 'statement.xml',
            'file_type'       => BankStatementImport::FILE_TYPE_CAMT053,
            'content'         => self::$camt053Sample,
        ], $this->user->id);

        $this->assertEquals(BankStatementImport::STATUS_COMPLETED, $import->status);
        $this->assertEquals(2, $import->total_transactions);
    }

    public function test_import_unsupported_type_marks_failed(): void
    {
        $bankAccount = \App\Models\Accounting\BankAccount::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $import = $this->service->import([
            'organization_id' => $this->organization->id,
            'bank_account_id' => $bankAccount->id,
            'file_name'       => 'statement.unknown',
            'file_type'       => 'unknown',
            'content'         => 'irrelevant',
        ], $this->user->id);

        $this->assertEquals(BankStatementImport::STATUS_FAILED, $import->status);
        $this->assertNotEmpty($import->errors);
    }
}
