# Graph Report - kari-voip  (2026-11-21)

## Corpus Check
- 257 files · ~85,085 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 33 file(s) not represented in the graph (top: (none) 20, .woff2 5, .css 3)

## Summary
- 1582 nodes · 4026 edges · 99 communities (55 shown, 44 thin omitted)
- Extraction: 98% EXTRACTED · 2% INFERRED · 0% AMBIGUOUS · INFERRED: 83 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- React Frontend & UI Components
- Appointments & Audit Models
- User Auth & Access Policies
- Feature Test Suite
- Artisan CLI & Telephony Adapters
- Accounting & Commercial Models
- Appointment & Availability Engine
- Billing & Invoice Controller
- Docker Compose & Infrastructure
- Company & DNC Management
- Branch & Role Controllers
- Branch & Contact Eloquent Models
- CRM & Contact Enums
- Call Workspace & Telephony Controller
- Customer 360 Contact Controller
- Developer API & Webhook System
- Real-Time Reverb Call Events
- Authentication & OTP Controller
- Campaigns & Executive Reports
- Commercial Database Migrations
- Frontend Package Dependencies
- Telephony & Call Enums
- AI Receptionist & SMS Adapters
- Backend Build & Tooling
- TypeScript Compiler Config
- .__construct() Module
- VoipConfigController Module
- CampaignCallJob Module
- CampaignController Module
- frontend/package.json Module
- App\Services\Telephony\AsteriskAdapter Module
- Call Module
- tsconfig.node.json Module
- CallDisposition Module
- AppServiceProvider Module
- CampaignOutcomeEnum Module
- .__construct() Module
- 0001_01_01_000001_create_cache_table Module
- .__construct() Module
- {closure#2}() Module
- AsteriskAdapter Module
- tsconfig.json Module
- CallMissed Module
- .__construct() Module
- GenericSipAdapter Module
- .__construct() Module
- 0001_01_01_000000_create_users_table Module
- CampaignStatusEnum Module
- CallStatusEnum Module
- MessagingController Module
- UserController Module
- 2026_11_18_000100_create_telephony_tables Module
- VoIPAdapterTest Module
- composer.json Module
- scripts Module
- 2026_11_15_070100_create_contacts_tables Module
- 2026_11_19_000100_create_campaigns_tables Module
- .handle() Module
- Payment Module
- require Module
- require-dev Module
- 2026_11_15_065804_create_permission_tables Module
- VoiceReach REST API v1 Reference Module
- CampaignPolicy Module
- CallHoursValidator Module
- pestphp/pest-plugin Module
- AuthTest Module
- vite.config.js Module
- CampaignTypeEnum Module
- NoteTypeEnum Module
- 0001_01_01_000002_create_jobs_table Module
- ContactTest Module
- .oxlintrc.json Module
- CallAnswered Module
- CallEnded Module
- CallRecorded Module
- CallStarted Module
- CallTransferred Module
- autoload Module
- 2026_11_19_000300_create_phase6_webhooks_tables Module
- AIReceptionistFlowTest Module
- RecordingAccessTest Module
- SystemHealthCommandTest Module
- WebhookSignatureTest Module
- scripts Module
- Unit/ExampleTest Module
- autoload-dev Module
- extra Module

## God Nodes (most connected - your core abstractions)
1. `User` - 93 edges
2. `Call` - 57 edges
3. `TestCase` - 52 edges
4. `Campaign` - 48 edges
5. `Button` - 46 edges
6. `Contact` - 45 edges
7. `toPersianDigits()` - 43 edges
8. `Invoice` - 41 edges
9. `react` - 41 edges
10. `Controller` - 38 edges

## Surprising Connections (you probably didn't know these)
- `{closure#2}()` --references--> `User`  [EXTRACTED]
  backend/app/Http/Controllers/Api/V1/ReportController.php → backend/app/Models/User.php
- `{closure#1}()` --references--> `Payment`  [EXTRACTED]
  backend/app/Services/Billing/PaymentService.php → backend/app/Models/Payment.php
- `CallAnswered` --references--> `Call`  [EXTRACTED]
  backend/app/Events/CallAnswered.php → backend/app/Models/Call.php
- `CallEnded` --references--> `Call`  [EXTRACTED]
  backend/app/Events/CallEnded.php → backend/app/Models/Call.php
- `CallMissed` --references--> `Call`  [EXTRACTED]
  backend/app/Events/CallMissed.php → backend/app/Models/Call.php

## Import Cycles
- None detected.

## Communities (99 total, 44 thin omitted)

### Community 0 - "React Frontend & UI Components"
Cohesion: 0.07
Nodes (117): App(), queryClient, InvoiceReceiptPrinter(), InvoiceReceiptPrinterProps, ActionItem, CommandPalette(), ContextMenu(), ContextMenuItem (+109 more)

### Community 1 - "Appointments & Audit Models"
Cohesion: 0.06
Nodes (15): AppointmentReminder, AuditLog, CallEvent, CampaignWorkflow, CompanyProfile, Holiday, Integration, InvoiceItem (+7 more)

### Community 2 - "User Auth & Access Policies"
Cohesion: 0.06
Nodes (9): User, AppointmentPolicy, BranchPolicy, CallPolicy, ContactPolicy, InvoicePolicy, UserPolicy, {closure#1}() (+1 more)

### Community 3 - "Feature Test Suite"
Cohesion: 0.06
Nodes (14): AppointmentAvailabilityTest, BranchTest, CallWorkspaceTest, CampaignRetryLogicTest, CompanyTest, ContactImportTest, DeveloperApiTokenTest, ExampleTest (+6 more)

### Community 4 - "Artisan CLI & Telephony Adapters"
Cohesion: 0.05
Nodes (17): VoiceReachBackupCommand, VoiceReachHealthCommand, VoiceReachInstallCommand, RoleEnum, ACCOUNTANT, BRANCH_MANAGER, CALL_AGENT, DEVELOPER (+9 more)

### Community 5 - "Accounting & Commercial Models"
Cohesion: 0.06
Nodes (8): AccountingLog, CampaignCallLog, Cheque, ContactFile, ContactMerge, ContactNote, CreditNote, IvrSession

### Community 6 - "Appointment & Availability Engine"
Cohesion: 0.08
Nodes (7): AppointmentController, Appointment, {closure#1}(), Service, Staff, AvailabilityEngine, ReminderScheduler

### Community 7 - "Billing & Invoice Controller"
Cohesion: 0.09
Nodes (7): InvoiceController, {closure#1}(), Invoice, {closure#1}(), InvoiceService, {closure#1}(), PaymentService

### Community 8 - "Docker Compose & Infrastructure"
Cohesion: 0.06
Nodes (39): build, depends_on, environment, volumes, context, dockerfile, MYSQL_DATABASE, MYSQL_PASSWORD (+31 more)

### Community 9 - "Company & DNC Management"
Cohesion: 0.07
Nodes (5): CompanyController, DncController, RoleController, SetupController, Controller

### Community 10 - "Branch & Role Controllers"
Cohesion: 0.13
Nodes (4): BranchController, IvrFlowController, ReportController, IvrFlow

### Community 12 - "CRM & Contact Enums"
Cohesion: 0.08
Nodes (13): ContactSourceEnum, CALL, CAMPAIGN, ONLINE, REFERRAL, WALK_IN, ContactStatusEnum, ACTIVE (+5 more)

### Community 15 - "Developer API & Webhook System"
Cohesion: 0.15
Nodes (3): DeveloperController, Webhook, WebhookDispatcherService

### Community 18 - "Campaigns & Executive Reports"
Cohesion: 0.11
Nodes (5): {closure#2}(), AuditActivity, {closure#1}(), {closure#2}(), {closure#3}()

### Community 19 - "Commercial Database Migrations"
Cohesion: 0.17
Nodes (18): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}() (+10 more)

### Community 20 - "Frontend Package Dependencies"
Cohesion: 0.09
Nodes (22): dependencies, axios@^1.21.1, clsx@^2.1.1, framer-motion@^14.14.0, lucide-react@^1.73.0, react@^19.3.1, react-dom@^19.3.1, react-is@^19.3.1 (+14 more)

### Community 21 - "Telephony & Call Enums"
Cohesion: 0.10
Nodes (7): CallDirectionEnum, INBOUND, OUTBOUND, CallTypeEnum, CAMPAIGN, IVR, MANUAL

### Community 22 - "AI Receptionist & SMS Adapters"
Cohesion: 0.12
Nodes (3): GhasedakAdapter, KavenegarAdapter, SmsAdapterInterface

### Community 23 - "Backend Build & Tooling"
Cohesion: 0.12
Nodes (20): devDependencies, concurrently@^10.0.3, laravel-vite-plugin@^3.1, tailwindcss@^4.0.0, @tailwindcss/vite@^4.0.0, vite@^8.0.0, tailwindcss, @tailwindcss/vite (+12 more)

### Community 24 - "TypeScript Compiler Config"
Cohesion: 0.10
Nodes (20): compilerOptions, allowArbitraryExtensions, allowImportingTsExtensions, erasableSyntaxOnly, jsx, lib, module, moduleDetection (+12 more)

### Community 26 - "VoipConfigController Module"
Cohesion: 0.12
Nodes (3): VoipConfigController, IssabelAdapter, TelephonyManager

### Community 27 - "CampaignCallJob Module"
Cohesion: 0.15
Nodes (4): CampaignCallJob, {closure#1}(), {closure#2}(), DNCEnforcementTest

### Community 29 - "frontend/package.json Module"
Cohesion: 0.15
Nodes (17): devDependencies, oxlint@^1.90.0, @types/node@^24.19.2, @types/react@^19.3.0, @types/react-dom@^19.3.0, typescript@~7.0.2, vite@^8.3.6, vite (+9 more)

### Community 30 - "App\Services\Telephony\AsteriskAdapter Module"
Cohesion: 0.14
Nodes (5): {closure#1}(), DatabaseSeeder, InitialSystemSeeder, RoleAndPermissionSeeder, ReportsAnalyticsTest

### Community 32 - "tsconfig.node.json Module"
Cohesion: 0.12
Nodes (16): compilerOptions, allowImportingTsExtensions, erasableSyntaxOnly, lib, module, moduleDetection, noEmit, noFallthroughCasesInSwitch (+8 more)

### Community 33 - "CallDisposition Module"
Cohesion: 0.14
Nodes (4): CallDisposition, MessageLog, MessageTemplate, SmsProvider

### Community 34 - "AppServiceProvider Module"
Cohesion: 0.13
Nodes (5): AppServiceProvider, {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}()

### Community 35 - "CampaignOutcomeEnum Module"
Cohesion: 0.13
Nodes (11): CampaignOutcomeEnum, ANSWERED, BUSY, COMPLETED, DTMF_SELECTED, FAILED, HUMAN_TRANSFER_REQUESTED, INVALID_NUMBER (+3 more)

### Community 36 - ".__construct() Module"
Cohesion: 0.21
Nodes (3): MessageTemplateEngine, SmsManager, SMSAdapterTest

### Community 37 - "0001_01_01_000001_create_cache_table Module"
Cohesion: 0.15
Nodes (4): {closure#1}(), {closure#2}(), {closure#1}(), {closure#1}()

### Community 39 - "{closure#2}() Module"
Cohesion: 0.21
Nodes (3): {closure#2}(), OpenAICompatibleAdapter, IntentDetector

### Community 41 - "tsconfig.json Module"
Cohesion: 0.14
Nodes (13): compilerOptions, jsx, lib, module, moduleResolution, noEmit, noFallthroughCasesInSwitch, noUnusedLocals (+5 more)

### Community 42 - "CallMissed Module"
Cohesion: 0.18
Nodes (3): CallMissed, DtmfReceived, VoipWebhookController

### Community 46 - "0001_01_01_000000_create_users_table Module"
Cohesion: 0.17
Nodes (9): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}(), {closure#8}() (+1 more)

### Community 47 - "CampaignStatusEnum Module"
Cohesion: 0.18
Nodes (7): CampaignStatusEnum, CANCELLED, COMPLETED, DRAFT, PAUSED, RUNNING, SCHEDULED

### Community 48 - "CallStatusEnum Module"
Cohesion: 0.20
Nodes (8): CallStatusEnum, ACTIVE, ENDED, FAILED, MISSED, ON_HOLD, RINGING, TRANSFERRED

### Community 51 - "2026_11_18_000100_create_telephony_tables Module"
Cohesion: 0.20
Nodes (7): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}()

### Community 53 - "composer.json Module"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 54 - "scripts Module"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 55 - "2026_11_15_070100_create_contacts_tables Module"
Cohesion: 0.22
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}()

### Community 56 - "2026_11_19_000100_create_campaigns_tables Module"
Cohesion: 0.22
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}()

### Community 59 - "require Module"
Cohesion: 0.25
Nodes (8): require, laravel/framework, laravel/horizon, laravel/reverb, laravel/sanctum, laravel/tinker, php, spatie/laravel-permission

### Community 60 - "require-dev Module"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pao, laravel/pint, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 61 - "2026_11_15_065804_create_permission_tables Module"
Cohesion: 0.25
Nodes (5): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}()

### Community 62 - "VoiceReach REST API v1 Reference Module"
Cohesion: 0.25
Nodes (8): VoiceReach REST API v1 Reference, Pre-Dispatch DNC List Enforcement, dqnamo 7-Stage Thermal Receipt Printer, HMAC-SHA256 Signed Webhooks, Deployment & PBX Installation Guide, Jalali Calendar & Availability Engine, Single-Tenant Multi-Branch Architecture, VoiceReach OS Architecture

### Community 64 - "CallHoursValidator Module"
Cohesion: 0.48
Nodes (3): CallHoursValidator, CampaignEngine, CallHoursRestrictionTest

### Community 65 - "pestphp/pest-plugin Module"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 68 - "CampaignTypeEnum Module"
Cohesion: 0.33
Nodes (4): CampaignTypeEnum, AUDIO, IVR, TTS

### Community 69 - "NoteTypeEnum Module"
Cohesion: 0.33
Nodes (4): NoteTypeEnum, FOLLOW_UP, NOTE, TASK

### Community 70 - "0001_01_01_000002_create_jobs_table Module"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 72 - ".oxlintrc.json Module"
Cohesion: 0.33
Nodes (5): plugins, rules, react/only-export-components, react/rules-of-hooks, $schema

### Community 78 - "autoload Module"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 86 - "scripts Module"
Cohesion: 0.40
Nodes (5): scripts, build, dev, lint, preview

### Community 89 - "autoload-dev Module"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 90 - "extra Module"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

## Knowledge Gaps
- **60 isolated node(s):** `INBOUND`, `OUTBOUND`, `RINGING`, `ACTIVE`, `ON_HOLD` (+55 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 539 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **44 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `User` connect `User Auth & Access Policies` to `Appointments & Audit Models`, `CallDisposition Module`, `AuthTest Module`, `Artisan CLI & Telephony Adapters`, `Feature Test Suite`, `.__construct() Module`, `ContactTest Module`, `Branch & Contact Eloquent Models`, `Authentication & OTP Controller`, `Campaigns & Executive Reports`, `UserController Module`, `AIReceptionistFlowTest Module`, `RecordingAccessTest Module`, `VoIPAdapterTest Module`, `CampaignCallJob Module`, `App\Services\Telephony\AsteriskAdapter Module`, `CampaignPolicy Module`?**
  _High betweenness centrality (0.061) - this node is a cross-community bridge._
- **What connects `INBOUND`, `OUTBOUND`, `RINGING` to the rest of the system?**
  _60 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `React Frontend & UI Components` be split into smaller, more focused modules?**
  _Cohesion score 0.06971770744225834 - nodes in this community are weakly interconnected._
- **Why does `Campaign` connect `CampaignController Module` to `CallHoursValidator Module`, `Appointments & Audit Models`, `CallDisposition Module`, `Feature Test Suite`, `Accounting & Commercial Models`, `.__construct() Module`, `Company & DNC Management`, `.__construct() Module`, `Branch & Contact Eloquent Models`, `CampaignStatusEnum Module`, `Real-Time Reverb Call Events`, `Authentication & OTP Controller`, `Campaigns & Executive Reports`, `.handle() Module`, `CampaignCallJob Module`, `CampaignPolicy Module`, `Call Module`?**
  _High betweenness centrality (0.039) - this node is a cross-community bridge._
- **Should `Appointments & Audit Models` be split into smaller, more focused modules?**
  _Cohesion score 0.06453634085213032 - nodes in this community are weakly interconnected._
- **Why does `Call` connect `Call Module` to `Appointments & Audit Models`, `User Auth & Access Policies`, `CallDisposition Module`, `Appointment & Availability Engine`, `CallAnswered Module`, `CallEnded Module`, `CallRecorded Module`, `CallMissed Module`, `CallStarted Module`, `CallTransferred Module`, `Call Workspace & Telephony Controller`, `Real-Time Reverb Call Events`, `Campaigns & Executive Reports`, `RecordingAccessTest Module`, `Telephony & Call Enums`?**
  _High betweenness centrality (0.037) - this node is a cross-community bridge._
- **Should `User Auth & Access Policies` be split into smaller, more focused modules?**
  _Cohesion score 0.061952861952861954 - nodes in this community are weakly interconnected._