# CLAUDE.md

Conventions every session in this repo must follow. Read `docs/schema.md` before
touching anything that concerns the database.

## Project

Furniture shop ERP. Laravel 12 + Inertia 2 + React 19 + TypeScript + Tailwind 4
+ shadcn/ui. MySQL 8. Single codebase, single deploy. No separate REST API layer
unless explicitly asked.

## Schema

docs/schema.md is the single source of truth for the database. Never invent
tables or columns. If something is missing, stop and ask.

## Architecture rules

- Controllers are thin. They validate via FormRequest, call an Action, and
  return an Inertia response or redirect. No business logic in controllers.
- Every business operation is a single-purpose class in app/Actions/<Domain>/.
  It has one public method: handle().
- Any operation touching money wraps its writes in DB::transaction().
- Writes to employee_ledger and supplier_ledger go through LedgerService only.
- Writes to transactions go through CashService only. Never insert directly.
- transactions rows are created ONLY when physical money moves. Credit
  purchases must not create a transactions row.
- Party balances (employee, supplier) are always computed with
  SUM(credit) - SUM(debit). Never store a running balance column.
- Money columns use decimal:2 casts. Do arithmetic in SQL where practical.
  Never compare money values with float equality.
- All enums are PHP backed enums in app/Enums/, mirrored as TypeScript string
  unions in resources/js/types/.

## Frontend rules

- Validation lives in FormRequest on the server. React uses Inertia's useForm
  and renders the errors Laravel returns. Do not duplicate validation rules in
  Zod or elsewhere.
- All user-facing strings are in Bengali. Code, comments, variable names,
  file names and commit messages are in English.
- Shop floor pages (attendance, employee payment, order create, daily closing)
  are mobile-first: large tap targets, minimal typing, sticky bottom save bar.
- Reuse the shared DataTable and form primitives. Do not hand-roll a table.

## Testing

Every Action gets a Pest feature test. Ledger and cash tests must assert the
exact number of rows written to each table, including asserting zero
transactions rows for credit purchases.

---

# Inferred from docs/schema.md

The rules above are binding as written. What follows is derived from the schema
document and carries the same weight unless it contradicts the above.

## The three-ledger principle

Every business event writes to up to three places, and it matters which:

1. **Operational record** — what happened (`orders`, `attendance`, `purchases`)
2. **Party ledger** — who now owes whom (`employee_ledger`, `supplier_ledger`,
   or the `due_amount` column on orders / sales / cnc_jobs)
3. **Cash ledger** — `transactions`, written ONLY when physical money moves

`docs/schema.md` section 9 is the authoritative event-to-table mapping. Before
writing any Action, find the event in that table and write exactly the rows it
lists — no more, no fewer. The credit-purchase row (writes 1 and 2, nothing to
`transactions`) is the case the whole design exists to protect. Assert it in a
test.

## Ledger direction semantics

- `employee_ledger`: `SUM(credit) - SUM(debit)`, positive means **the shop owes
  the worker**. Earnings are credits (`wage_earned`, `piece_earned`, `overtime`,
  `bonus`); money handed out is a debit (`advance`, `tiffin`, `payout`, `fine`).
- `supplier_ledger`: `SUM(credit) - SUM(debit)`, positive means **we owe the
  supplier**. A purchase is a credit; paying it down is a debit.
- Never invert these. A sign error here is silently wrong money.

## Idempotency

These operations run more than once against the same date and must not double
anything:

- `MarkDailyAttendance` — re-saving a date must not add a second
  `wage_earned` row per employee. Upsert the attendance row and reconcile the
  matching ledger entry.
- `GenerateMonthlySalary` — one `wage_earned` credit per employee per month.
- Recurring rent expense command — one expense per shop per month.

Each of these gets a test that runs the action twice and asserts row counts are
unchanged on the second run.

## Wage automation

- `wage_type = daily`: attendance `present` writes a `wage_earned` credit of
  `employees.daily_rate`; `half_day` writes half. `absent`, `leave` and
  `holiday` write no ledger row.
- `wage_type = monthly`: attendance rows are still recorded, but no daily
  `wage_earned` entry. One credit on the last day of the month instead.
- `wage_type = piece`: earnings come from `order_item_works` reaching status
  `done` with an `agreed_amount`, written as a `piece_earned` credit.

## Numbering

- Orders: `SH-YYMM-NNNN` (e.g. `SH-2607-0142`). Printable — it gets written on
  the paper slip during the transition period.
- Sales (`invoice_no`), purchases (`purchase_no`) and CNC jobs (`job_no`) follow
  the same shape with their own prefix and counter.
- Generation goes through `NumberSeries` and must be safe under concurrency.

## Money and precision

- `DECIMAL(12,2)` for transactional amounts, `DECIMAL(14,2)` for account and
  closing balances, `DECIMAL(12,3)` for material quantities. Match
  `docs/schema.md` exactly per column.
- Pass money around as strings, not floats. Use `bcmath` or SQL for arithmetic.
- `accounts.current_balance` is the one deliberate exception to the
  no-running-balance rule: `CashService` maintains it inside the same DB
  transaction as the `transactions` row. Nothing else may write it.

## Localization

- Timezone `Asia/Dhaka`, locale `bn`.
- Every user-facing string, validation message, PDF and email is Bengali.
  Validation messages live in `lang/bn/validation.php`, never inline.
- Bengali needs a font with correct conjunct rendering — Hind Siliguri is the
  project default. Check conjuncts (ক্ষ, ন্ত্র, স্ট্র) after any font change.

## Permissions

- Roles: owner, manager, accountant, storekeeper.
- Only `owner` gets any permission containing `profit` or `reports.financial`.
  Per-order profit, P&L and margin data never render for other roles — enforce
  server-side, not by hiding a nav item.

## Soft deletes and referential safety

Master data (shops, trades, employees, customers, categories, accounts,
suppliers, products) soft-deletes. Block the delete with a clear Bengali
message when the record is referenced by operational rows. Ledger,
`transactions` and attendance rows are never deleted — correct them with an
`adjustment` entry.

## Directory layout

```
app/Actions/<Domain>/      one class, one public handle()
app/Services/              LedgerService, CashService, NumberSeries
app/Enums/                 backed enums, each with label(): string in Bengali
app/Queries/               one class per report/widget query, each tested
resources/js/types/        TypeScript mirrors of models and enums
docs/schema.md             database source of truth
```

## Definition of done for an Action

1. Writes are inside a single `DB::transaction()`.
2. A Pest test asserts exact row counts in every table the action touches.
3. A Pest test runs it twice and asserts idempotency where applicable.
4. A Pest test forces an exception mid-way and asserts zero rows persisted.
5. Balance arithmetic asserted to the paisa.
