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
