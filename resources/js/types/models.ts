/**
 * TypeScript mirrors of the Eloquent models in app/Models/.
 * Money columns arrive as strings because they are DECIMAL casts on the
 * server; never parseFloat them for comparison or arithmetic.
 */

import type {
    AccountType,
    AttendanceStatus,
    CustomerType,
    LedgerDirection,
    LedgerEntryType,
    MaterialCategory,
    MaterialUnit,
    PaymentMethod,
    SupplierLedgerEntryType,
    SupplierType,
    WageType,
} from './enums';

export interface Shop {
    id: number;
    name: string;
    address: string | null;
    phone: string | null;
    monthly_rent: string;
    rent_due_day: number | null;
    landlord_name: string | null;
    landlord_phone: string | null;
    electricity_meter_no: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
}

export interface Customer {
    id: number;
    name: string;
    phone: string;
    alt_phone: string | null;
    address: string | null;
    area: string | null;
    customer_type: CustomerType;
    opening_due: string;
    note: string | null;
    created_by: number | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
}

export interface Trade {
    id: number;
    name: string;
    default_daily_rate: string;
    is_active: boolean;
    deleted_at: string | null;
}

export interface Employee {
    id: number;
    employee_code: string;
    name: string;
    phone: string | null;
    address: string | null;
    photo: string | null;
    nid_no: string | null;
    trade_id: number | null;
    shop_id: number | null;
    wage_type: WageType;
    daily_rate: string;
    monthly_salary: string;
    joining_date: string | null;
    guarantor_name: string | null;
    guarantor_phone: string | null;
    opening_advance: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    trade?: Trade;
    shop?: Shop;
}

export interface Account {
    id: number;
    name: string;
    type: AccountType;
    account_no: string | null;
    shop_id: number | null;
    opening_balance: string;
    current_balance: string;
    is_active: boolean;
    deleted_at: string | null;
}

export interface ExpenseCategory {
    id: number;
    name: string;
    is_recurring: boolean;
    is_active: boolean;
    deleted_at: string | null;
}

export interface Attendance {
    id: number;
    employee_id: number;
    shop_id: number | null;
    work_date: string;
    status: AttendanceStatus;
    in_time: string | null;
    out_time: string | null;
    overtime_hours: string;
    overtime_rate: string;
    note: string | null;
    marked_by: number | null;
    employee?: Employee;
}

export interface EmployeeLedgerEntry {
    id: number;
    employee_id: number;
    entry_date: string;
    type: LedgerEntryType;
    direction: LedgerDirection;
    amount: string;
    reference_type: string | null;
    reference_id: number | null;
    payment_method: PaymentMethod | null;
    note: string | null;
    created_by: number | null;
    created_at: string;
}

export interface ProductCategory {
    id: number;
    name: string;
    parent_id: number | null;
    is_active: boolean;
    deleted_at: string | null;
    parent?: ProductCategory;
    children?: ProductCategory[];
}

export interface Supplier {
    id: number;
    name: string;
    business_name: string | null;
    phone: string | null;
    address: string | null;
    supplier_type: SupplierType;
    opening_due: string;
    credit_limit: string;
    default_credit_days: number;
    is_active: boolean;
    deleted_at: string | null;
}

export interface Material {
    id: number;
    name: string;
    category: MaterialCategory;
    unit: MaterialUnit;
    current_stock: string;
    avg_cost: string;
    min_stock: string;
    is_active: boolean;
}

export interface SupplierLedgerEntry {
    id: number;
    supplier_id: number;
    entry_date: string;
    type: SupplierLedgerEntryType;
    direction: LedgerDirection;
    amount: string;
    reference_type: string | null;
    reference_id: number | null;
    note: string | null;
    created_by: number | null;
    created_at: string;
}

export interface Product {
    id: number;
    sku: string;
    name: string;
    category_id: number | null;
    description: string | null;
    wood_type: string | null;
    size_label: string | null;
    cost_price: string;
    sale_price: string;
    current_stock: string;
    min_stock: string;
    shop_id: number | null;
    is_active: boolean;
    deleted_at: string | null;
    category?: ProductCategory;
}
