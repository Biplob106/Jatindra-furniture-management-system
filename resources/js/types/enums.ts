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

export type OrderStatus = 'draft' | 'confirmed' | 'in_production' | 'ready' | 'delivered' | 'cancelled';

export const orderStatusLabels: Record<OrderStatus, string> = {
    draft: 'খসড়া',
    confirmed: 'নিশ্চিত',
    in_production: 'কাজ চলছে',
    ready: 'তৈরি',
    delivered: 'ডেলিভারি হয়েছে',
    cancelled: 'বাতিল',
};

export type OrderItemStatus = 'pending' | 'in_progress' | 'completed';

export const orderItemStatusLabels: Record<OrderItemStatus, string> = {
    pending: 'বাকি',
    in_progress: 'কাজ চলছে',
    completed: 'শেষ',
};

export type DimensionUnit = 'inch' | 'feet' | 'cm';

export const dimensionUnitLabels: Record<DimensionUnit, string> = {
    inch: 'ইঞ্চি',
    feet: 'ফুট',
    cm: 'সেন্টিমিটার',
};

export type OrderItemWorkStatus = 'assigned' | 'working' | 'done' | 'rejected';

export const orderItemWorkStatusLabels: Record<OrderItemWorkStatus, string> = {
    assigned: 'দেওয়া হয়েছে',
    working: 'কাজ চলছে',
    done: 'শেষ',
    rejected: 'বাতিল',
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

/** transactions.payment_method, which unlike the ledger one includes cheque. */
export type CashPaymentMethod = 'cash' | 'bkash' | 'nagad' | 'bank' | 'cheque';

export const cashPaymentMethodLabels: Record<CashPaymentMethod, string> = {
    cash: 'ক্যাশ',
    bkash: 'বিকাশ',
    nagad: 'নগদ',
    bank: 'ব্যাংক',
    cheque: 'চেক',
};

export type PaymentMethod = 'cash' | 'bkash' | 'nagad' | 'bank';

export const paymentMethodLabels: Record<PaymentMethod, string> = {
    cash: 'ক্যাশ',
    bkash: 'বিকাশ',
    nagad: 'নগদ',
    bank: 'ব্যাংক',
};

export type SupplierType = 'wood' | 'hardware' | 'paint' | 'transport' | 'other';

export const supplierTypeLabels: Record<SupplierType, string> = {
    wood: 'কাঠ',
    hardware: 'হার্ডওয়্যার',
    paint: 'রং',
    transport: 'পরিবহন',
    other: 'অন্যান্য',
};

export type MaterialCategory = 'wood' | 'board' | 'hardware' | 'paint' | 'polish' | 'glue' | 'other';

export const materialCategoryLabels: Record<MaterialCategory, string> = {
    wood: 'কাঠ',
    board: 'বোর্ড',
    hardware: 'হার্ডওয়্যার',
    paint: 'রং',
    polish: 'পলিশ',
    glue: 'আঠা',
    other: 'অন্যান্য',
};

export type MaterialUnit = 'cft' | 'sqft' | 'piece' | 'kg' | 'litre' | 'bundle' | 'set';

export const materialUnitLabels: Record<MaterialUnit, string> = {
    cft: 'ঘনফুট',
    sqft: 'বর্গফুট',
    piece: 'পিস',
    kg: 'কেজি',
    litre: 'লিটার',
    bundle: 'বান্ডিল',
    set: 'সেট',
};

export type PurchasePaymentType = 'cash' | 'credit' | 'partial';

export const purchasePaymentTypeLabels: Record<PurchasePaymentType, string> = {
    cash: 'নগদ',
    credit: 'বাকি',
    partial: 'আংশিক',
};

export type PurchaseStatus = 'pending' | 'partial' | 'paid' | 'returned';

export const purchaseStatusLabels: Record<PurchaseStatus, string> = {
    pending: 'বাকি',
    partial: 'আংশিক পরিশোধ',
    paid: 'পরিশোধিত',
    returned: 'ফেরত',
};

export type PurchaseItemType = 'material' | 'product';

export const purchaseItemTypeLabels: Record<PurchaseItemType, string> = {
    material: 'কাঁচামাল',
    product: 'পণ্য',
};

export type SupplierLedgerEntryType = 'opening' | 'purchase' | 'payment' | 'return' | 'discount' | 'adjustment';

export const supplierLedgerEntryTypeLabels: Record<SupplierLedgerEntryType, string> = {
    opening: 'প্রারম্ভিক',
    purchase: 'ক্রয়',
    payment: 'পরিশোধ',
    return: 'ফেরত',
    discount: 'ছাড়',
    adjustment: 'সংশোধন',
};

export type MaterialMovementType = 'in' | 'out' | 'wastage' | 'return' | 'adjustment';

export const materialMovementTypeLabels: Record<MaterialMovementType, string> = {
    in: 'জমা',
    out: 'ব্যবহার',
    wastage: 'নষ্ট',
    return: 'ফেরত',
    adjustment: 'সংশোধন',
};

export type PartyType = 'supplier' | 'customer';

export const partyTypeLabels: Record<PartyType, string> = {
    supplier: 'সরবরাহকারী',
    customer: 'গ্রাহক',
};
