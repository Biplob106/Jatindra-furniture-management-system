<?php

namespace App\Enums;

use BackedEnum;

/**
 * What LedgerService needs to know about an entry type, whichever party ledger
 * it belongs to.
 *
 * Both employee_ledger and supplier_ledger use the same arithmetic
 * (SUM(credit) - SUM(debit)) and the same rule that most entry types have one
 * fixed direction. This lets one direction-resolving path serve both, so there
 * is a single place a sign error could hide rather than two.
 */
interface LedgerEntryKind extends BackedEnum
{
    public function label(): string;

    /** The direction this type always moves, or null when the caller must say. */
    public function direction(): ?LedgerDirection;
}
