/**
 * TypeScript mirrors of the PHP backed enums in app/Enums/.
 * Keep the members and the Bengali labels in step with the PHP side.
 */

export type Role = 'owner' | 'manager' | 'accountant' | 'storekeeper';

export const roleLabels: Record<Role, string> = {
    owner: 'মালিক',
    manager: 'ম্যানেজার',
    accountant: 'হিসাবরক্ষক',
    storekeeper: 'স্টোরকিপার',
};

export type WageType = 'daily' | 'monthly' | 'piece';

export const wageTypeLabels: Record<WageType, string> = {
    daily: 'দৈনিক',
    monthly: 'মাসিক',
    piece: 'কাজ চুক্তি',
};

export type CustomerType = 'retail' | 'dealer' | 'contractor';

export const customerTypeLabels: Record<CustomerType, string> = {
    retail: 'খুচরা',
    dealer: 'ডিলার',
    contractor: 'ঠিকাদার',
};

export type AccountType = 'cash' | 'mobile_banking' | 'bank';

export const accountTypeLabels: Record<AccountType, string> = {
    cash: 'ক্যাশ',
    mobile_banking: 'মোবাইল ব্যাংকিং',
    bank: 'ব্যাংক',
};

export type AttendanceStatus = 'present' | 'absent' | 'half_day' | 'leave' | 'holiday';

export const attendanceStatusLabels: Record<AttendanceStatus, string> = {
    present: 'উপস্থিত',
    absent: 'অনুপস্থিত',
    half_day: 'অর্ধদিবস',
    leave: 'ছুটি',
    holiday: 'বন্ধের দিন',
};

export type LedgerDirection = 'credit' | 'debit';

export const ledgerDirectionLabels: Record<LedgerDirection, string> = {
    credit: 'জমা',
    debit: 'খরচ',
};

export type LedgerEntryType =
    | 'opening'
    | 'wage_earned'
    | 'piece_earned'
    | 'overtime'
    | 'bonus'
    | 'advance'
    | 'tiffin'
    | 'payout'
    | 'fine'
    | 'adjustment';

export const ledgerEntryTypeLabels: Record<LedgerEntryType, string> = {
    opening: 'প্রারম্ভিক',
    wage_earned: 'হাজিরা',
    piece_earned: 'কাজের মজুরি',
    overtime: 'ওভারটাইম',
    bonus: 'বোনাস',
    advance: 'অগ্রিম',
    tiffin: 'টিফিন',
    payout: 'পরিশোধ',
    fine: 'জরিমানা',
    adjustment: 'সংশোধন',
};

export type PaymentMethod = 'cash' | 'bkash' | 'nagad' | 'bank';

export const paymentMethodLabels: Record<PaymentMethod, string> = {
    cash: 'ক্যাশ',
    bkash: 'বিকাশ',
    nagad: 'নগদ',
    bank: 'ব্যাংক',
};
