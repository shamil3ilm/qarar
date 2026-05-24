# SAP Gap Analysis & Feature Roadmap
**Date:** 2026-04-02  
**Project:** Masaar ERP (Multi-Tenant ERP for GCC & India)  
**Status:** Living document — update as gaps are resolved  
**Coverage:** SAP S/4HANA parity gaps + modern features beyond SAP

---

## How to Use This Document

Each gap entry has:
- **SAP Equivalent** — the SAP transaction code / module reference
- **Status** — `missing`, `partial`, or `flag-only`
- **Priority** — `P1` (critical / compliance blocker) · `P2` (high business value) · `P3` (nice to have)
- **Notes** — implementation hints or dependencies

When a gap is resolved, change its status to `done` and add the PR/commit reference.

---

## Table of Contents

1. [FI — Financial Accounting](#1-fi--financial-accounting)
2. [CO — Controlling](#2-co--controlling)
3. [SD — Sales & Distribution](#3-sd--sales--distribution)
4. [MM — Materials Management & Purchasing](#4-mm--materials-management--purchasing)
5. [PP — Production Planning](#5-pp--production-planning)
6. [QM — Quality Management](#6-qm--quality-management)
7. [PM — Plant Maintenance](#7-pm--plant-maintenance)
8. [HCM — Human Capital Management](#8-hcm--human-capital-management)
9. [PS — Project System](#9-ps--project-system)
10. [WM/EWM — Warehouse Management](#10-wmewm--warehouse-management)
11. [TM — Transportation Management](#11-tm--transportation-management)
12. [RE-FX — Real Estate](#12-re-fx--real-estate)
13. [CRM — Customer Relationship Management](#13-crm--customer-relationship-management)
14. [GRC — Governance, Risk & Compliance](#14-grc--governance-risk--compliance)
15. [Regional Tax & E-Invoicing Compliance](#15-regional-tax--e-invoicing-compliance)
16. [Cross-Cutting Infrastructure Gaps](#16-cross-cutting-infrastructure-gaps)
17. [Non-SAP Modern Features](#17-non-sap-modern-features)
18. [Summary Priority Matrix](#18-summary-priority-matrix)

---

## 1. FI — Financial Accounting

**Current coverage:** Strong core GL, bank reconciliation, asset accounting, parallel ledgers, IFRS 16, XBRL, consolidation, installment plans.

### Gaps

| # | Gap | SAP Equivalent | Status | Priority | Notes |
|---|-----|---------------|--------|----------|-------|
| FI-01 | Bank statement import (MT940 / CAMT.053 / BAI2) | SAP FF.5 / FEBP | missing | P1 | Banks in GCC/India deliver electronic statements. Manual upload or SFTP polling needed. |
| FI-02 | SEPA mandate management (direct debit) | SAP FSEPA_M | partial | P2 | `DirectDebitCollection` model exists; mandate lifecycle (active, cancelled, revoked) and SEPA XML pain.008 generation are missing. |
| FI-03 | Payment medium formats (SEPA pain.001, SWIFT MT101, Saudi ACH/SARIE, India NEFT/RTGS) | SAP PMW | done | P1 | `PaymentFormatService::generateSarie()` (ISO 20022 pain.001.001.09 + SARIE local instrument), `generateNeft()`, `generateRtgs()` added. `generate()` dispatch updated. 16 tests. |
| FI-04 | Document splitting (segment / profit-center real-time) | SAP FAGL_SPLIT (NewGL) | missing | P2 | Needed for segment reporting (IFRS 8) and profit-center P&L. Journal entries post at header level today. |
| FI-05 | Open item clearing automation (automatic matching rules) | SAP F.13 / F-32 | partial | P2 | Payment allocation exists; automatic matching by amount + reference + date is missing. |
| FI-06 | Zakat calculation and filing (Saudi Arabia) | SAP ZAKAT module | done | P1 | `ZakatAssessment` model + migration, `ZakatCalculationService` with `calculateBase()` (2.5%, Saudi ownership %, non-Zakatable deductions), `createAssessment()`, `submitAssessment()`, `recordPayment()`. 16 tests. |
| FI-07 | Corporate Income Tax (CIT) provisions and filing | SAP TE-SL | missing | P2 | UAE introduced 9% CIT in 2023. Saudi has 20% CIT on foreign entities. Deferred tax, current tax provision, and filing needed. |
| FI-08 | Withholding Tax (WHT) on cross-border vendor payments (GCC) | SAP FI-AP-WHT | done | P1 | `GccCrossBorderWhtSeeder` seeds 6 codes (SA 15%, AE 0%, OM 10%, KW 5%, BH 0%, QA 5%). `GccWithholdingTaxService::resolveCode()` + `applyIfCrossBorder()` + `isCrossBorder()`. 17 tests. |
| FI-09 | Extended dunning with legal escalation steps | SAP F150 | partial | P2 | Dunning model exists; legal escalation, court notice templates, and statutory interest calculation missing. |
| FI-10 | Financial close checklist + period-end task automation | SAP Financial Closing Cockpit | partial | P2 | `FinancialCloseCockpit` model exists but automated task scheduling, dependency chains, and status dashboard are incomplete. |
| FI-11 | Accrual engine (rule-based recurring accruals) | SAP ACE (FBS1/FBS2) | partial | P2 | Accrual models exist but rule-based monthly auto-accrual scheduling and reversal engine missing. |
| FI-12 | Treasury: Money Market / FX Forward booking | SAP TR-MM / TR-FM | partial | P2 | `FxDerivative` model exists; deal capture, valuation, and hedge accounting settlement are incomplete. |
| FI-13 | Cash pooling / zero-balancing between entities | SAP TR-CM | missing | P3 | Intercompany cash concentration for treasury management. |
| FI-14 | XBRL iXBRL inline submission (HMRC / GAZT) | SAP XBRL | partial | P2 | `XbrlFiling` model exists; inline iXBRL rendering and regulator submission API missing. |
| FI-15 | Inter-company netting (automated clearing) | SAP F.13 + IC | partial | P3 | Intercompany reconciliation exists; automated netting cycle with settlement instructions missing. |

---

## 2. CO — Controlling

**Current coverage:** Cost centers, profit centers, internal orders, product costing, COPA, overhead keys, assessment/distribution, parallel accounting, variance analysis.

### Gaps

| # | Gap | SAP Equivalent | Status | Priority | Notes |
|---|-----|---------------|--------|----------|-------|
| CO-01 | Activity type pricing (confirmed vs. planned rates) | SAP KP26 / KSBT | missing | P2 | Work centers exist; activity types with planned-price confirmation and plan-actual variance tracking missing. |
| CO-02 | CO settlement to assets / GL / orders (KO88 / CJ88) | SAP KO88 | partial | P2 | Settlement rules exist; period-end CO settlement run with multi-receiver splits and posting incomplete. |
| CO-03 | Standard cost estimate release to production version | SAP CK11N / CK24 | partial | P1 | Product costing exists; releasing a standard cost as the "active" cost used in valuation is missing. |
| CO-04 | Transfer price management between profit centers | SAP EC-PCA | partial | P2 | Transfer price model exists; dual-valuation posting (legal vs. profit-center ledger) incomplete. |
| CO-05 | Cost object hierarchy (cost element groups, cost center groups) | SAP KSH1 / KAH1 | missing | P3 | Needed for reporting roll-ups without custom SQL each time. |
| CO-06 | Budget availability control (FM — Funds Management) | SAP FM | partial | P2 | Budget exists; real-time commitment tracking against budget with tolerance groups and hard/soft stop incomplete. |
| CO-07 | Overhead costing sheet with surcharge / credit split | SAP KZS2 | partial | P2 | Overhead keys exist; multi-step costing sheet with base amounts and percentages by cost element incomplete. |
| CO-08 | Reconciliation ledger (FI/CO differences) | SAP KALC | done | — | Implemented 2026-04-02 per project memory. |

---

## 3. SD — Sales & Distribution

**Current coverage:** Sales orders, quotations, invoices, credit management, ATP, rebates, pricing conditions, promotions, billing plans, revenue recognition, intercompany, consignment, backorders.

### Gaps

| # | Gap | SAP Equivalent | Status | Priority | Notes |
|---|-----|---------------|--------|----------|-------|
| SD-01 | Output/message determination (auto-send order confirmation, delivery note, invoice) | SAP NACE | missing | P2 | Documents are sent manually today. Rule-based auto-dispatch via email/EDI/print on document status change needed. |
| SD-02 | Delivery split rules (by shipping point, partial delivery tolerance) | SAP VL01N | partial | P2 | Delivery documents exist; configurable split criteria (max weight, shipping point, route) missing. |
| SD-03 | Batch determination strategy for outbound delivery | SAP LS27 / MBV1 | missing | P2 | Batch model exists; FEFO/FIFO picking strategy linked to sales delivery is missing. |
| SD-04 | Serial number assignment in delivery (goods issue) | SAP VL02N serialization | partial | P2 | Serial number model exists; mandatory serial assignment during picking/GI with customer history is missing. |
| SD-05 | Returnable packaging / empties deposit management | SAP Handling Units | missing | P3 | Pallet and returnable container tracking with deposit invoicing and returns. |
| SD-06 | Make-to-Order (MTO) / Make-to-Stock (MTS) ATP with RLT | SAP MD04 | partial | P2 | ATP check exists; Replenishment Lead Time (RLT) fallback and MTO individual requirement tracking missing. |
| SD-07 | Sales organizational hierarchy (Division / Distribution Channel) | SAP SD Org | missing | P2 | Only one org level (organization) used. Division/distribution channel required for multi-channel businesses. |
| SD-08 | Service item in sales order (free-of-charge items, repair service) | SAP CS-SD | partial | P3 | Service tickets exist in CRM; SD service items (item category TAPA, TATX) not linked to service delivery. |
| SD-09 | Credit memo / debit memo request workflow (approval before document) | SAP VA01 credit/debit request | partial | P2 | Credit notes exist; pre-approval workflow for credit/debit memo request (separate from the note itself) missing. |
| SD-10 | Collective invoice (multiple deliveries → one invoice) | SAP VF04 | missing | P2 | Needed for efficiency — billing all open deliveries in one run. |
| SD-11 | Milestone / POC revenue recognition (long-term contracts) | SAP RA | partial | P2 | Billing plans and performance obligations exist; percentage-of-completion method with variable consideration incomplete. |
| SD-12 | Subscription / recurring billing engine | SAP BRIM / FI-CA | partial | P2 | `Billing` module for SaaS exists (billing the ERP itself); customer subscription invoicing for recurring revenue businesses missing. |

---

## 4. MM — Materials Management & Purchasing

**Current coverage:** Purchase orders, requisitions, RFQ, outline agreements, scheduling agreements, goods receipts, 3-way match, ERS, vendor consignment, scorecards, contracts, quota arrangements, PCard.

### Gaps

| # | Gap | SAP Equivalent | Status | Priority | Notes |
|---|-----|---------------|--------|----------|-------|
| MM-01 | Automatic source determination (source list priority, quota) | SAP ME0M / quota | partial | P2 | Quota arrangements and source lists exist; automatic source selection when creating requisitions/POs missing. |
| MM-02 | Demand-Driven MRP (DDMRP) | SAP S/4HANA 2022 DDMRP | missing | P3 | Buffer-based replenishment; complements traditional MRP. Useful for companies moving away from ERP-driven forecasting. |
| MM-03 | Ariba / e-procurement portal integration | SAP Ariba | missing | P3 | Supplier catalogue, punch-out, and PO flip from Ariba. Not required for SME; valuable for enterprise customers. |
| MM-04 | Material master governance (data quality workflow) | SAP MDG-M | missing | P3 | Workflow-driven new material creation with duplicate check. |
| MM-05 | Pipeline / consignment settlement (vendor-owned stock used in production) | SAP MRKO | partial | P2 | Vendor consignment withdrawal exists; automatic settlement (periodic billing to vendor based on usage) missing. |
| MM-06 | Vendor return purchase order with goods return movement type | SAP MIGO 122 / VL02N | partial | P2 | Vendor credits exist; reverse goods movement with vendor return advice and logistics document missing. |
| MM-07 | Reorder-point planning (MRP type VB / VM) | SAP MD02 VB | missing | P2 | MRP exists but reorder-point with safety stock and automatic replenishment proposal distinct from MRP run missing. |
| MM-08 | Invoice blocking reasons and tolerance configuration UI | SAP MM tolerance / OMR6 | partial | P2 | `MmToleranceRule` model exists; UI for configuring price/quantity tolerance groups per vendor is missing. |
| MM-09 | Goods receipt inspection (quality notification auto-creation) | SAP QM-PT | partial | P2 | Inspection lots exist; auto-creation from GR event with block-stock until inspection complete missing. |
| MM-10 | Subcontracting settlement (BOM explosion at GR, component reconciliation) | SAP ME2O / MIGO 101 | partial | P2 | Subcontracting model exists; component reconciliation at goods receipt and automatic 543 movement missing. |

---

## 5. PP — Production Planning

**Current coverage:** BOM, routing, work orders, MRP, capacity planning, kanban, repetitive manufacturing, process orders, subcontracting, detailed scheduling, LTP.

### Gaps

| # | Gap | SAP Equivalent | Status | Priority | Notes |
|---|-----|---------------|--------|----------|-------|
| PP-01 | Planned Independent Requirements (PIR) management | SAP MD61 | done | P1 | `PlannedIndependentRequirement` model + migration + `SOURCE_PIR` in `MrpDemandItem`; `collectDemand()` in `MrpService` consumes active PIR open qty. 12 tests. |
| PP-02 | MPS (Master Production Schedule) | SAP MP30 | missing | P2 | High-level production schedule for key items before MRP explosion. |
| PP-03 | Production order confirmation with time ticket (CATS integration) | SAP CO11N | partial | P2 | Work order operations exist; labor confirmation with actual time capture and automatic time wage type generation incomplete. |
| PP-04 | OEE (Overall Equipment Effectiveness) tracking | SAP ME OEE | missing | P2 | Equipment utilization, downtime classification, shift reports. Not in SAP standard but expected in modern manufacturing. |
| PP-05 | Goods movement 261/262 from production order (component issue/reversal) | SAP MIGO 261 | partial | P2 | Work order materials exist; automatic component issue on order release and backflush posting missing. |
| PP-06 | Production version with lot size and validity | SAP C223 | partial | P2 | Production versions exist; lot-size-dependent routing/BOM selection and validity period enforcement incomplete. |
| PP-07 | APO / IBP demand planning (statistical forecasting) | SAP IBP | missing | P3 | Statistical forecast models (exponential smoothing, trend, seasonality). `DemandForecast` model exists but the ML/statistical engine is absent. |
| PP-08 | Digital Manufacturing / MES integration (IoT, machine data) | SAP DM / ME | missing | P3 | Shop floor integration via OPC-UA or MQTT for real-time machine status, operator guidance. |
| PP-09 | Co-product / by-product accounting at production order close | SAP PP co-products | partial | P2 | BOM co-products exist; apportionment of costs to co-products and by-product credit posting at order settlement missing. |

---

## 6. QM — Quality Management

**Current coverage:** Quality plans, inspection lots, results recording, notifications, CAPA, 8D, SPC, stability studies, skip-lot, calibration, CoA, supplier quality, audits.

### Gaps

| # | Gap | SAP Equivalent | Status | Priority | Notes |
|---|-----|---------------|--------|----------|-------|
| QM-01 | QM control key per material/operation (QM in procurement/production auto-trigger) | SAP QM control key | partial | P2 | Inspection lots can be created but automatic triggering by QM control key on material master is missing. |
| QM-02 | Usage decision (UD) workflow with stock posting (unrestricted / blocked / scrap) | SAP QA11 | done | P1 | `UsageDecision` model + migration; `recordUsageDecision()` in `QualityManagementService` posts movements 321/346/551 and auto-creates CAPA on rejection. 13 tests. |
| QM-03 | GxP electronic batch record (FDA 21 CFR Part 11) | SAP QM-GxP | missing | P3 | Required for pharmaceutical / food manufacturing customers. Digital signatures on quality records with audit trail. |
| QM-04 | LIMS integration (Laboratory Information Management System) | SAP QM-LIMS | missing | P3 | External lab result import via interface. Common in chemical/food industries. |
| QM-05 | Complaint processing (customer → supplier) linkage | SAP QM notif. chain | partial | P2 | Complaints and CAPA exist but customer complaint → supplier corrective action chaining missing. |

---

## 7. PM — Plant Maintenance

**Current coverage:** Equipment, functional locations, maintenance orders, notifications, preventive maintenance, condition-based maintenance, RCA, fleet, permits, counters.

### Gaps

| # | Gap | SAP Equivalent | Status | Priority | Notes |
|---|-----|---------------|--------|----------|-------|
| PM-01 | Warranty management (equipment and component warranties) | SAP CS-WM | missing | P2 | Track warranty expiry per equipment, auto-generate warranty claim on notification. |
| PM-02 | Pool / lease asset management | SAP AM Pool | missing | P3 | Fleet pooling with availability calendar and booking. |
| PM-03 | PMIS (Maintenance Information System) standard reporting | SAP PMIS | partial | P2 | Maintenance KPIs exist; MTTR, MTBF, breakdown frequency, cost-per-equipment reports missing. |
| PM-04 | Service contract management (third-party AMC) | SAP CS-SC | missing | P2 | Annual maintenance contract tracking with billing cycles for equipment under external service. |
| PM-05 | Linear Asset Management (pipelines, roads, cables) | SAP LAM | missing | P3 | Relevant for utility/oil & gas customers. |

---

## 8. HCM — Human Capital Management

**Current coverage:** Employee management, payroll, GOSI/WPS (Saudi), time & attendance, leave, recruitment, onboarding/exit, performance, training, succession, travel expenses, EOSB.

### Gaps

| # | Gap | SAP Equivalent | Status | Priority | Notes |
|---|-----|---------------|--------|----------|-------|
| HCM-01 | Configurable payroll schema / PCR (Personnel Calculation Rules) | SAP PE01/PE02 | missing | P1 | Current payroll uses hard-coded or table-driven rules. For GCC/India parity, country-specific payroll rules (allowances, deductions, overtime) must be configurable without code changes. |
| HCM-02 | Payroll factoring (partial period prorations) | SAP payroll factoring | partial | P2 | Partial-period payment for joiners/leavers. Monthly proration by calendar days vs. working days configurable. |
| HCM-03 | Concurrent / multiple assignments (one employee, multiple positions) | SAP PA40 | missing | P2 | Common in healthcare and hospitality. Employee holds primary + secondary position with separate pay runs. |
| HCM-04 | Global employment (cross-country posting) | SAP GE | missing | P3 | Employee on international assignment with home/host country payroll split and tax equalization. |
| HCM-05 | Oman PASI social insurance | regional compliance | done | P1 | Oman pension and social insurance contribution calculation and file generation for PASI. |
| HCM-06 | Kuwait PIFSS social insurance | regional compliance | done | P1 | Kuwait social security contribution and registration integration. |
| HCM-07 | Bahrain SIO social insurance | regional compliance | done | P1 | Bahrain Social Insurance Organisation contribution file. |
| HCM-08 | Qatar GRSIA social insurance | regional compliance | done | P1 | Qatar General Retirement and Social Insurance Authority. |
| HCM-09 | India PF (EPF), ESI, PT (Professional Tax) | India payroll | done | P1 | TDS (India) is implemented; EPF/ESI/PT payroll deductions, challans, and ECR (Electronic Challan cum Return) missing. |
| HCM-10 | WPS (UAE) — SIF file generation for UAE Ministry of Labour | UAE WPS | done | P1 | UAE Wage Protection System SIF file (different format from Saudi WPS). Salary Transfer File specification. |
| HCM-11 | Workforce analytics (headcount, attrition, diversity, age profile) | SAP SuccessFactors | missing | P2 | HR reporting exists; workforce trend analytics with period comparisons and benchmarking missing. |
| HCM-12 | Learning Management System (LMS) with e-learning and SCORM | SAP LMS | partial | P2 | Training module exists; SCORM-compliant content hosting, online assessments, and completion certificates missing. |
| HCM-13 | Compensation review cycles (merit matrix, salary band calibration) | SAP ECM | partial | P2 | Compensation review exists; merit matrix calibration (9-box grid, salary band enforcement, manager calibration UI) missing. |
| HCM-14 | Digital personnel file (document DMS per employee) | SAP DMS-HR | partial | P2 | Employee documents model exists; document retention policies, expiry reminders, and controlled access per doc type missing. |
| HCM-15 | Absence quota generation (leave accrual engine by entitlement rules) | SAP PT60 | partial | P2 | Leave accruals exist; rule-based accrual engine (calendar/anniversary/fiscal year, different rates by tenure/grade) incomplete. |

---

## 9. PS — Project System

**Current coverage:** Projects, WBS, network activities, milestones, budget with supplements, EVM (baselines, snapshots), revenue recognition, project time sheets, settlement rules, project invoicing.

### Gaps

| # | Gap | SAP Equivalent | Status | Priority | Notes |
|---|-----|---------------|--------|----------|-------|
| PS-01 | Network activity float / critical path calculation | SAP PS network scheduling | missing | P2 | Activity relationships exist; CPM (Critical Path Method) forward/backward pass scheduling and float calculation missing. |
| PS-02 | Project procurement integration (WBS → PR → PO) | SAP PS-MM | partial | P2 | WBS commitments exist; automatic purchase requisition generation from WBS element with budget availability check missing. |
| PS-03 | Claims management (variations and change orders) | SAP PS claims | missing | P3 | Common in construction / engineering projects. Formal variation order with pricing impact tracking. |
| PS-04 | Resource leveling (capacity-constrained scheduling) | SAP PS resource leveling | missing | P3 | Reschedule activities based on resource availability constraints. |
| PS-05 | Project templates with complete WBS / activity copy | SAP CN01 template | partial | P2 | `ProjectTemplate` model exists; full template instantiation with WBS/activity/milestone copy and date offset calculation missing. |

---

## 10. WM/EWM — Warehouse Management

**Current coverage:** Bins, storage sections, transfer orders, putaway rules, wave management, picking, cross-docking, yard management, truck appointments, hazmat, physical inventory, cycle counts.

### Gaps

| # | Gap | SAP Equivalent | Status | Priority | Notes |
|---|-----|---------------|--------|----------|-------|
| WM-01 | GS1-128 / SSCC label generation and scanning workflow | SAP EWM label | missing | P2 | Barcode model exists; GS1-128 compliant handling unit labels for outbound logistics missing. |
| WM-02 | Voice-directed picking (VDP) integration | SAP EWM VDP | missing | P3 | API interface for voice picking devices (Honeywell Vocollect). |
| WM-03 | RF (Radio Frequency) device UI / warehouse mobile app | SAP ITS Mobile | missing | P2 | Warehouse operations currently desktop-only. Mobile-first warehouse UI for goods receipt, putaway, picking required. |
| WM-04 | Slotting (product placement optimization) | SAP EWM slotting | missing | P3 | Optimize bin assignments based on velocity, weight, and dimensions. |
| WM-05 | Task interleaving (combine putaway and picking in one trip) | SAP EWM task interleaving | missing | P3 | Warehouse efficiency optimization. |
| WM-06 | Dangerous goods document generation (Hazmat) | SAP DG management | partial | P2 | Hazmat model exists; automatic dangerous goods declaration (ADR/IATA/IMDG) document generation missing. |

---

## 11. TM — Transportation Management

**Current coverage:** Carriers, carrier services, freight agreements, rates, tenders, surcharges, transportation orders, load plans.

### Gaps

| # | Gap | SAP Equivalent | Status | Priority | Notes |
|---|-----|---------------|--------|----------|-------|
| TM-01 | Track & trace integration (carrier API: Aramex, DHL, FedEx, SMSA) | SAP TM event mgmt | missing | P1 | Real-time shipment visibility by polling carrier APIs. Critical for GCC customers who ship via Aramex/DHL. |
| TM-02 | Freight order cost distribution to inventory / sales order | SAP TM settlement | partial | P2 | Landed costs model exists; automated distribution of freight costs to PO/SO lines missing. |
| TM-03 | Last-mile delivery routing (route optimization) | SAP TM routing | missing | P2 | Multi-stop route optimization for B2B delivery. Integration with Google Maps / HERE Maps. |
| TM-04 | COD (Cash on Delivery) reconciliation | — | missing | P2 | Very common in GCC/India e-commerce deliveries. COD amount matching against remittances. |
| TM-05 | Third-party logistics (3PL) portal for warehouse outsourcing | SAP TM 3PL | missing | P3 | Secure portal for 3PL partners to receive orders, confirm receipts, and report stock levels. |

---

## 12. RE-FX — Real Estate

**Current coverage:** Portfolios, properties, buildings, floors, rental units, lease contracts, IFRS 16 schedules, service charges, security deposits, vacancy management.

### Gaps

| # | Gap | SAP Equivalent | Status | Priority | Notes |
|---|-----|---------------|--------|----------|-------|
| RE-01 | Rental index / market rent comparison | SAP RE-FX market rent | missing | P3 | Track market rent vs. contracted rent for renewal negotiation. |
| RE-02 | Tenant portal (online rent payment, maintenance requests) | — | missing | P2 | Self-service portal for tenants to pay rent, raise maintenance tickets. Modern SaaS feature. |
| RE-03 | Property valuation and appraisal tracking | SAP RE-FX | missing | P3 | Record and track periodic property valuations for balance sheet. |
| RE-04 | Automated escalation clauses (CPI-linked rent increases) | SAP RE-FX | partial | P2 | `ContractOption` model exists; automatic CPI-based rent escalation calculation and contract amendment generation missing. |

---

## 13. CRM — Customer Relationship Management

**Current coverage:** Leads, opportunities, pipeline stages, activities, service tickets, SLA, territories, routing rules.

### Gaps

| # | Gap | SAP Equivalent | Status | Priority | Notes |
|---|-----|---------------|--------|----------|-------|
| CRM-01 | Email / phone integration (click-to-call, email threading) | SAP C4C / CRM | missing | P2 | Activities exist but no integration with Gmail / Outlook / telephony for auto-logging communications. |
| CRM-02 | Lead scoring (ML-based or rule-based) | SAP C4C lead scoring | missing | P2 | Automatic lead quality score based on behavior, firmographics, and engagement. |
| CRM-03 | Quote-to-Order automation (opportunity → quotation → order) | SAP CPQ | partial | P2 | All three documents exist separately; one-click promotion from opportunity to quotation to sales order missing. |
| CRM-04 | Customer 360 view (unified timeline: orders, payments, tickets, calls) | SAP C4C 360 | missing | P2 | No unified view aggregating all customer interactions across modules. |
| CRM-05 | Field service management (dispatch, mobile, SLA enforcement) | SAP FSM | missing | P2 | Service tickets exist; field technician dispatch, mobile work order, GPS tracking, and customer signature capture missing. |

---

## 14. GRC — Governance, Risk & Compliance

**Current coverage:** GRC-PC (controls, CCM, CSA), GRC-RM (risks, KRIs, treatments), GRC-IA (audits, findings), SoD, AML, DPS, fraud rules.

### Gaps

| # | Gap | SAP Equivalent | Status | Priority | Notes |
|---|-----|---------------|--------|----------|-------|
| GRC-01 | Policy management (policy library, attestation cycles) | SAP GRC policy mgmt | missing | P2 | Store, version, and manage compliance policies; employee attestation with digital acknowledgment. |
| GRC-02 | Incident management (IT security / operational incidents) | SAP GRC-IM | missing | P2 | Separate from maintenance notifications. Security breaches, operational failures, near-miss recording with root cause and corrective action. |
| GRC-03 | Business Continuity Management (BCM) | SAP GRC-BCM | missing | P3 | BCP/DRP documentation, impact analysis, recovery objectives, test exercises. |
| GRC-04 | Whistleblower / ethics hotline | SAP Ethics & Compliance | missing | P2 | Anonymous reporting channel with case management, investigation workflow, and non-retaliation tracking. |
| GRC-05 | SOX financial reporting controls (ICFR) | SAP GRC-PC SOX | partial | P2 | GRC controls exist; SOX-specific control testing templates, deficiency classification (significant deficiency / material weakness), and management assessment missing. |
| GRC-06 | Privacy management (DSAR handling, consent records, data map) | SAP GRC-Privacy | partial | P2 | GDPR consent records exist; Data Subject Access Request (DSAR) workflow, data inventory map, and breach notification timer missing. |

---

## 15. Regional Tax & E-Invoicing Compliance

This is the most critical section for Masaar's GCC & India market positioning.

### Saudi Arabia (ZATCA)

| # | Gap | Status | Priority | Notes |
|---|-----|--------|----------|-------|
| ZATCA-01 | ZATCA Phase 2 clearance-based e-invoicing (cryptographic stamp + UUID) | partial | P1 | CompliPay gateway handles this; verify all invoice types covered: standard (B2B clearance), simplified (B2C reporting), credit/debit notes. |
| ZATCA-02 | Fatoora portal onboarding (CCSID/PCSID certificate provisioning) | partial | P1 | Compliance onboarding endpoint exists; automated certificate renewal and rotation missing. |
| ZATCA-03 | Zakat filing and base calculation | done | P1 | Implemented via FI-06 — `ZakatCalculationService` + `ZakatAssessment`. `submitAssessment()` records GAZT reference and filed_at date. |
| ZATCA-04 | ZATCA real estate VAT (5% on commercial, 15% on residential) | partial | P2 | Tax determination rules exist; real-estate-specific rate overrides and partial exemption calculation missing. |

### UAE (FTA — Federal Tax Authority)

| # | Gap | Status | Priority | Notes |
|---|-----|--------|----------|-------|
| FTA-01 | FTA e-invoicing implementation (Phase 1 and Phase 2) | done | P1 | `FtaUblBuilder` (UBL 2.1 + TLV QR) + `FtaEInvoiceService` + `fta_einvoice_submissions` table. 21 tests pass. |
| FTA-02 | UAE Corporate Tax (CIT) 9% filing (effective 2023) | done | P1 | `UaeCorporateTaxService` (0%/9% threshold, SBR) + `uae_cit_assessments` table + EmaraTax submission workflow. 20 tests pass. |
| FTA-03 | UAE VAT return (VAT 201 form) filing and reconciliation | partial | P2 | VAT return model exists; reconciliation with GL balance, auto-populate from transactions, and EmaraTax API submission missing. |
| FTA-04 | UAE Excise Tax (tobacco, energy drinks, carbonated drinks) | missing | P2 | Different from VAT. EmaraTax excise return filing. |
| FTA-05 | Transfer Pricing documentation (UAE / Saudi MNE rules) | missing | P2 | Master file / local file / Country-by-Country Report (CbCR) requirements for large multinationals. |

### Qatar (GTA — General Tax Authority)

| # | Gap | Status | Priority | Notes |
|---|-----|--------|----------|-------|
| QA-01 | Qatar e-invoicing framework integration | done | P1 | `QatarGtaEInvoiceService` (UBL 2.1 + TLV QR, QAR, QatarTRN) + `qatar_gta_submissions` table. 16 tests pass. |
| QA-02 | Qatar QSS (Qatar Social Security) contributions | missing | P1 | Social insurance for Qatari nationals; distinct from GOSI. |
| QA-03 | Qatar Income Tax (QIT) filing | missing | P2 | 10% corporate income tax for non-Qatari entities. |

### Bahrain (NBR — National Bureau for Revenue)

| # | Gap | Status | Priority | Notes |
|---|-----|--------|----------|-------|
| BH-01 | Bahrain VAT e-invoicing and return filing (NBR portal) | done | P1 | `BahrainVatReturnService` (10% VAT, 8-box NBR form, CSV export, quarterly/monthly) + `bahrain_vat_returns` table. 20 tests pass. |
| BH-02 | Bahrain SIO social insurance file generation | missing | P1 | See HCM-07. |

### Kuwait (MoF)

| # | Gap | Status | Priority | Notes |
|---|-----|--------|----------|-------|
| KW-01 | Kuwait income tax (KFAS, NLST, ZAKAT) declaration | missing | P2 | Several levies on corporate profit. Filing to MoF required. |
| KW-02 | Kuwait PIFSS social insurance contribution file | missing | P1 | See HCM-06. |

### Oman (Tax Authority)

| # | Gap | Status | Priority | Notes |
|---|-----|--------|----------|-------|
| OM-01 | Oman VAT return filing (Oman Tax Authority) | missing | P1 | Oman 5% VAT. OTA return format. |
| OM-02 | Oman PASI social insurance file | missing | P1 | See HCM-05. |
| OM-03 | Oman WHT filing | missing | P2 | 10% WHT on dividends, interest, royalties paid to non-residents. |

### India

| # | Gap | Status | Priority | Notes |
|---|-----|--------|----------|-------|
| IN-01 | India e-invoice XML (IRP portal, IRN generation) | done | P1 | `IndiaIrnBuilder` (NIC schema v1.1, IRN=SHA-256 hash, QR base64-JSON) + `IndiaIrpEInvoiceService` + `india_einvoice_submissions` table. 22 tests pass. |
| IN-02 | EPF/ESI/PT payroll compliance | done | P1 | See HCM-09 — same gap. TDS implemented; EPF (12%), ESI (3.25%), Professional Tax (state-wise) deductions, challans, and ECR file missing. |
| IN-03 | India TDS 194Q (buyer deducts TDS on purchase) | done | P2 | `Tds194QService` (₹50L FY threshold, 0.1%/5% rates, 206C(1H) exemption, FY Q tracking) + migration seeding 194Q section. 16 tests pass. |
| IN-04 | GSTR-9 (annual return) and GSTR-9C (reconciliation) | done | P2 | `Gstr9ReturnService` (Tables 4/6/7/9, ITC setoff, GSTR-1/3B aggregation, ARN filing) + `gstr9_returns` table. 13 tests pass. |
| IN-05 | India XBRL filing (MCA21 for large companies) | missing | P3 | Ministry of Corporate Affairs XBRL for balance sheet, P&L filing. Different taxonomy from GAZT. |

---

## 16. Cross-Cutting Infrastructure Gaps

| # | Gap | Status | Priority | Notes |
|---|-----|--------|----------|-------|
| INFRA-01 | Test coverage — currently ~43 feature tests for 1,064 models | partial | P1 | Test coverage is extremely low relative to model count. Need journey tests for every critical business flow per module. Target: 500+ tests. |
| INFRA-02 | API versioning strategy for breaking changes | partial | P2 | `/api/v1` exists; deprecation notices, v2 migration guide, and sunset headers missing. |
| INFRA-03 | Background job queue monitoring (Horizon dashboard) | missing | P2 | Laravel Horizon or similar for queue visibility, failure alerting, throughput metrics. |
| INFRA-04 | Multi-database tenancy option (one DB per tenant) | missing | P3 | Current: shared DB with `organization_id`. High-compliance customers (banking, government) may require isolated DB. |
| INFRA-05 | Event sourcing / audit log replay | partial | P2 | Audit trail exists; full event sourcing (replay to any point in time) for FI compliance not implemented. |
| INFRA-06 | Rate limiting per organization / per user | partial | P2 | `QueryBudget` middleware exists; per-organization rate limit quotas and burst allowances missing. |
| INFRA-07 | Data archival / tiering strategy | partial | P2 | `ArchiveOldDataCommand` exists; configurable retention policies per data type and automatic tiering to cold storage missing. |
| INFRA-08 | Full-text search (Elasticsearch / OpenSearch) | missing | P2 | Global search across customers, products, invoices. Critical for usability at scale. |
| INFRA-09 | Observability stack (distributed tracing, metrics, logs) | partial | P2 | `TrackResponseTime` middleware exists; OpenTelemetry integration, distributed trace IDs, and Grafana/Prometheus dashboards missing. |
| INFRA-10 | GraphQL API (alongside REST) | missing | P3 | Reduces over-fetching in complex dashboard and mobile scenarios. Optional but adds developer experience. |

---

## 17. Non-SAP Modern Features

These are features that SAP does not natively offer but are expected by modern SaaS ERP buyers and highly valued in GCC/India markets.

### 17.1 AI & Machine Learning

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| AI-01 | Invoice / receipt OCR + AI extraction | P1 | Scan vendor invoices and auto-populate bill fields. Critical for AP efficiency. Integrate with Azure AI Document Intelligence or Google Document AI. |
| AI-02 | Expense receipt scanning (mobile photo → expense line) | P1 | Common expectation in modern HR apps. Reduces manual data entry. |
| AI-03 | Anomaly detection in GL transactions | P2 | Flag unusual journal entries (round amounts, unusual accounts, off-hours posting) for finance review. |
| AI-04 | Predictive cash flow forecasting | P2 | ML model on historical AR/AP to predict cash position 30/60/90 days out. |
| AI-05 | Smart vendor duplicate detection | P2 | Fuzzy name + bank account matching to prevent duplicate vendor payments. |
| AI-06 | Natural language PO / invoice search | P3 | "Show me all overdue invoices from Aramco last quarter" via NLP query. |
| AI-07 | Automated bank reconciliation suggestions | P2 | Match unreconciled bank lines to GL entries using ML similarity. |
| AI-08 | Demand forecasting with seasonality (ML-enhanced MRP) | P2 | Enhance PIR generation with time-series ML model for demand planning. |

### 17.2 Digital Payments & Open Banking

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| PAY-01 | Saudi payment gateways (Mada, STC Pay, Tabby BNPL) | P1 | Mandatory for Saudi retail and B2B payment collection. |
| PAY-02 | UAE payment gateways (Tap, Network International, Apple Pay) | P1 | Required for UAE operations. |
| PAY-03 | India payment gateways (Razorpay / PayU — UPI, NEFT, IMPS) | P1 | Required for India collections. |
| PAY-04 | Open Banking (IBAN verification, account lookup via bank APIs) | P2 | IBAN validation via central bank API (Saudi SAMA open banking, UAE API sandbox). Reduces payment errors. |
| PAY-05 | Real-time payment status (SARIE / FAWRI for Saudi/UAE) | P2 | SARIE is Saudi Arabia's RTGS. Real-time confirmation of high-value transfers. |
| PAY-06 | Virtual IBANs for automated payment matching | P3 | Each customer gets a virtual IBAN; incoming transfer auto-matches to open invoice. |

### 17.3 ESG & Sustainability

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| ESG-01 | Carbon footprint tracking (Scope 1, 2, 3) | P2 | Track energy consumption, fleet emissions, supply chain emissions. Mandatory reporting for Saudi Vision 2030 listed companies. |
| ESG-02 | Sustainability reporting (GRI / SASB / TCFD dashboards) | P2 | Generate ESG reports for investor and regulatory disclosure. |
| ESG-03 | ESG KPI targets and progress tracking | P3 | Set annual ESG targets and track against actuals. |
| ESG-04 | Supply chain ESG scoring (vendor sustainability assessment) | P3 | Extend supplier scorecards with ESG criteria (carbon, labor, governance). |

### 17.4 Collaboration & Communication

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| COLLAB-01 | Microsoft Teams / Slack notification webhooks | P2 | Push approvals, alerts, and workflow notifications to Teams/Slack channels. |
| COLLAB-02 | In-app document commenting and @mentions | P2 | Collaborative annotation on invoices, POs, contracts with thread history. |
| COLLAB-03 | Video meeting integration (Zoom / Teams) for CRM activities | P3 | Schedule and join meetings from within the CRM activity timeline. |

### 17.5 Self-Service Portals

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| PORTAL-01 | Customer portal (invoice download, payment, order tracking) | P1 | Reduces AR team workload. Customers self-serve statements, pay online, dispute invoices. |
| PORTAL-02 | Vendor portal (PO acknowledgment, invoice submission, payment status) | P1 | Reduces AP team workload. Suppliers submit invoices digitally, reducing paper and email. `DpsPortal` exists; full vendor portal missing. |
| PORTAL-03 | Employee self-service app (mobile-first) | P2 | Leave requests, payslips, expense claims, attendance marking from smartphone. |
| PORTAL-04 | Tenant portal for real estate (rent payment, maintenance request) | P2 | See RE-02. |

### 17.6 Low-Code Customization

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| LC-01 | Visual workflow / approval designer (no-code) | P2 | Current approval framework is code-driven. Admins should configure multi-level approvals via drag-and-drop UI. |
| LC-02 | Custom report builder (drag-and-drop) | P2 | Saved reports exist; visual report builder for non-technical users missing. |
| LC-03 | Custom form / field builder (beyond custom_fields model) | P2 | Add organization-specific fields to any entity without code changes. Custom fields model exists but UI builder missing. |
| LC-04 | Automation rule builder (if-this-then-that, visual) | P2 | Automation rules exist; visual rule composer for business users missing. |

### 17.7 Marketplace & Integrations

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| INT-01 | Shopify / WooCommerce / Magento bi-directional sync | partial | P1 | Ecommerce channel adapters exist for Shopify and WooCommerce; full bi-directional sync (inventory updates, order status) incomplete. |
| INT-02 | WhatsApp Business API for customer notifications | P2 | Send invoice PDFs, payment reminders, and order confirmations via WhatsApp. Essential in GCC/India markets. |
| INT-03 | Accounting integrations (QuickBooks, Xero) for migration | P3 | Data import from QuickBooks / Xero for customers migrating to Masaar. |
| INT-04 | HR integrations (LinkedIn Recruiter, Bayt.com) | P3 | Post jobs and import candidates from GCC-popular job boards. |
| INT-05 | Bank API connections (direct debit, statement pull) | P2 | See FI-01. Connect directly to bank APIs (SAMA open banking, ENBD, DIB) for automated statement import. |

---

## 18. Summary Priority Matrix

### P1 — Compliance Blockers & Core Business Gaps (Fix First)

| ID | Title | Module |
|----|-------|--------|
| FI-03 | Payment medium formats (SARIE, NEFT, RTGS, SEPA) | FI |
| FI-06 | Zakat calculation and filing | FI |
| FI-08 | WHT on cross-border vendor payments (GCC) | FI |
| CO-03 | Standard cost estimate release to production version | CO |
| PP-01 | Planned Independent Requirements (PIR) | PP |
| QM-02 | Usage decision with stock posting | QM |
| HCM-01 | Configurable payroll schema / PCR | HCM |
| HCM-09 | India EPF/ESI/PT payroll compliance | HCM |
| HCM-10 | UAE WPS SIF file generation | HCM |
| HCM-05 | Oman PASI social insurance | HCM |
| HCM-06 | Kuwait PIFSS social insurance | HCM |
| HCM-07 | Bahrain SIO social insurance | HCM |
| HCM-08 | Qatar GRSIA social insurance | HCM |
| ZATCA-01 | ZATCA Phase 2 clearance complete verification | ZATCA |
| ZATCA-03 | Zakat filing | ZATCA |
| FTA-01 | FTA UAE e-invoicing | FTA |
| FTA-02 | UAE Corporate Tax 9% | FTA |
| QA-01 | Qatar GTA e-invoicing | Qatar |
| BH-01 | Bahrain VAT e-invoicing / NBR | Bahrain |
| KW-02 | Kuwait PIFSS file | Kuwait |
| OM-01 | Oman VAT return filing | Oman |
| OM-02 | Oman PASI file | Oman |
| IN-01 | India e-invoice IRN / IRP | India |
| IN-02 | India EPF/ESI/PT | India |
| INFRA-01 | Test coverage — 43 tests for 1,064 models | Infra |
| AI-01 | Invoice OCR / AI extraction | AI |
| AI-02 | Expense receipt scanning | AI |
| PAY-01 | Saudi payment gateways (Mada, STC Pay) | Payments |
| PAY-02 | UAE payment gateways (Tap, Network International) | Payments |
| PAY-03 | India payment gateways (Razorpay) | Payments |
| PORTAL-01 | Customer self-service portal | Portal |
| PORTAL-02 | Vendor portal | Portal |
| INT-01 | Shopify / WooCommerce full bi-directional sync | Integration |

### P2 — High Business Value (Next Wave)

FI-01, FI-02, FI-04, FI-05, FI-07, FI-09, FI-10, FI-11, FI-12, CO-01, CO-02, CO-06, CO-07, SD-01, SD-02, SD-03, SD-04, SD-06, SD-07, SD-09, SD-10, SD-11, SD-12, MM-01, MM-05, MM-06, MM-07, MM-08, MM-09, MM-10, PP-03, PP-04, PP-05, PP-06, PP-09, QM-01, QM-03, QM-05, PM-01, PM-03, PM-04, PS-01, PS-02, PS-05, WM-01, WM-03, WM-06, TM-01, TM-02, TM-03, TM-04, RE-02, RE-04, CRM-01, CRM-02, CRM-03, CRM-04, CRM-05, GRC-01, GRC-02, GRC-04, GRC-05, GRC-06, ZATCA-04, FTA-03, FTA-04, FTA-05, QA-02, KW-01, OM-03, IN-03, IN-04, INFRA-02, INFRA-03, INFRA-05, INFRA-06, INFRA-07, INFRA-08, INFRA-09, AI-03, AI-04, AI-05, AI-07, AI-08, PAY-04, PAY-05, ESG-01, ESG-02, COLLAB-01, COLLAB-02, PORTAL-03, PORTAL-04, LC-01, LC-02, LC-03, LC-04, INT-02, INT-05

### P3 — Nice to Have

FI-13, FI-14, FI-15, CO-05, SD-05, SD-08, MM-02, MM-03, MM-04, PP-07, PP-08, QM-04, PM-02, PM-05, PS-03, PS-04, WM-02, WM-04, WM-05, TM-05, RE-01, RE-03, GRC-03, IN-05, INFRA-04, INFRA-10, AI-06, PAY-06, ESG-03, ESG-04, COLLAB-03, INT-03, INT-04, HCM-04

---

## 19. Fix Plan — Implementation Order

Implemented sequentially. Check each box when done. Update the gap entry status to `done` and add PR reference.

### Wave 1 — GCC Social Insurance & Payroll Compliance (HCM P1)
> Foundation: `SocialInsuranceScheme` + `SocialInsuranceService` already exist. Each item adds a country export service + seeder.

- [x] **HCM-05** Oman PASI — `OmanPasiExportService` + `SocialInsuranceSchemesSeeder`
- [x] **HCM-06** Kuwait PIFSS — `KuwaitPifssExportService` + seeder
- [x] **HCM-07** Bahrain SIO — `BahrainSioExportService` + seeder (nationals + expats)
- [x] **HCM-08** Qatar GRSIA — `QatarGrsiaExportService` + seeder
- [x] **HCM-10** UAE GPSSA + UAE WPS — `UaeGpssaExportService` + `UaeWpsExportService`
- [x] **HCM-09 / IN-02** India EPF/ESI/PT — `EpfContribution`, `EsiContribution`, `IndiaEpfEsiService`, ECR export

### Wave 2 — Quality & Manufacturing Gaps (QM/PP P1)

- [x] **QM-02** Usage decision workflow with stock type transfer (unrestricted / blocked / scrap)
- [x] **PP-01** Planned Independent Requirements (PIR) — models + MRP integration

### Wave 3 — Financial Compliance (FI P1)

- [x] **FI-08** GCC Withholding Tax on cross-border vendor payments (Saudi 15%, UAE 0%, Oman 10%)
- [x] **FI-06 / ZATCA-03** Zakat calculation engine + GAZT filing draft
- [x] **FI-03** Payment medium format adapters (Saudi SARIE, India NEFT/RTGS)

### Wave 4 — UAE & Qatar E-Invoicing (FTA / QA P1)

- [x] **FTA-01** UAE FTA e-invoicing (UBL 2.1 adapter, FTA QR, webhook)
- [x] **FTA-02** UAE CIT 9% — tax base computation + EmaraTax filing draft
- [x] **QA-01** Qatar GTA e-invoicing framework
- [x] **BH-01** Bahrain VAT return (NBR format)

### Wave 5 — India Tax Compliance (IN P1)

- [x] **IN-01** India e-invoice IRP/IRN — GSTN API adapter + QR code
- [x] **IN-03** TDS 194Q on purchases
- [x] **IN-04** GSTR-9 annual return

### Wave 6 — Core FI/CO P2 (High Value)

- [ ] **FI-01** Bank statement import (MT940 / CAMT.053)
- [ ] **FI-04** Document splitting (NewGL segment)
- [ ] **CO-03** Standard cost estimate release to production version
- [ ] **CO-01** Activity type pricing (planned vs. actual rates)
- [ ] **FI-10** Financial close checklist automation

### Wave 7 — Test Coverage (INFRA-01)

- [ ] Add journey tests: every P1 feature implemented above gets a journey test
- [ ] Add missing unit tests to reach ≥ 500 test cases

### Wave 8 — SD / MM Gaps (P2)

- [ ] **SD-01** Output/message determination
- [ ] **SD-02** Delivery split rules
- [ ] **MM-06** Vendor return purchase order
- [ ] **MM-07** Reorder-point planning (MRP type VB)
- [ ] **TM-01** Track & trace carrier API integration

### Wave 9 — Modern Features (P2)

- [ ] **AI-01** Invoice OCR (Azure Document Intelligence adapter)
- [ ] **PAY-01/02/03** Payment gateway adapters (Mada, Tap, Razorpay)
- [ ] **PORTAL-01** Customer self-service portal API
- [ ] **PORTAL-02** Vendor portal API

---

## Change Log

| Date | Author | Change |
|------|--------|--------|
| 2026-04-02 | shamil | Initial version — full gap analysis across all modules |
| 2026-04-02 | shamil | Added Fix Plan (Wave 1–9) with ordered implementation checkboxes |
| 2026-04-02 | shamil | Wave 1 complete: HCM-05..10 + IN-02 — GCC social insurance export services + India EPF/ESI/PT |
