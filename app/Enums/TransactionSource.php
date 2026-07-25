<?php

namespace App\Enums;

/**
 * Why money moved. Every transactions row names the event behind it, so the
 * daily closing can be read back by category rather than as one lump.
 */
enum TransactionSource: string
{
    case OrderPayment = 'order_payment';
    case Sale = 'sale';
    case CncPayment = 'cnc_payment';
    case PurchasePayment = 'purchase_payment';
    case Expense = 'expense';
    case EmployeePayment = 'employee_payment';
    case CapitalIn = 'capital_in';
    case OwnerDraw = 'owner_draw';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::OrderPayment => 'অর্ডারের টাকা',
            self::Sale => 'বিক্রি',
            self::CncPayment => 'সিএনসি কাজের টাকা',
            self::PurchasePayment => 'মালামাল কেনার টাকা',
            self::Expense => 'খরচ',
            self::EmployeePayment => 'কর্মীর পাওনা',
            self::CapitalIn => 'মূলধন জমা',
            self::OwnerDraw => 'মালিকের উত্তোলন',
            self::TransferIn => 'হিসাবে জমা',
            self::TransferOut => 'হিসাব থেকে স্থানান্তর',
            self::Adjustment => 'সংশোধন',
        };
    }
}
