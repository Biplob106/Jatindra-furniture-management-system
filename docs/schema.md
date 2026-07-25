# Furniture Shop Management System - Database Schema & Build Spec

Target stack: Laravel 12 + Inertia 2 + React 19 + MySQL 8 + PWA (shop floor screens)


---

## 1. System Overview

The business has four revenue streams and one large labour ledger:

| Stream | Money in | Tables |
|---|---|---|
| Custom furniture orders | Advance + installments + final payment | `orders`, `order_items` |
| Readymade furniture sales | Cash or credit invoice | `sales`, `sale_items` |
| CNC job work | Per sqft / per piece / fixed | `cnc_jobs` |
| Owner capital injection | Occasional | `transactions` |

Money out: worker wages/advances/tiffin, wood + hardware purchases, shop rent, electricity, transport, machine maintenance.

### The three-ledger principle

Every business event writes to up to three places:

1. **Operational record** - what happened (order, attendance, purchase)
2. **Party ledger** - who now owes whom (`employee_ledger`, `supplier_ledger`, order/sale dues)
3. **Cash ledger** - `transactions`, written ONLY when physical money moves

Credit purchases write to 1 and 2 but NOT 3. This is what keeps the nightly cash report honest.

---

## 2. Foundation Tables

```sql
CREATE TABLE shops (
  id                    BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name                  VARCHAR(150) NOT NULL,
  address               TEXT,
  phone                 VARCHAR(20),
  monthly_rent          DECIMAL(12,2) DEFAULT 0,
  rent_due_day          TINYINT UNSIGNED,          -- 1-31
  landlord_name         VARCHAR(150),
  landlord_phone        VARCHAR(20),
  electricity_meter_no  VARCHAR(50),
  is_active             BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL
);

CREATE TABLE roles (
  id         BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name       VARCHAR(50) NOT NULL,      -- owner, manager, accountant, storekeeper
  permissions JSON,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL
);

CREATE TABLE users (
  id         BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name       VARCHAR(150) NOT NULL,
  phone      VARCHAR(20) UNIQUE NOT NULL,
  email      VARCHAR(150) UNIQUE NULL,
  password   VARCHAR(255) NOT NULL,
  role_id    BIGINT UNSIGNED NOT NULL,
  shop_id    BIGINT UNSIGNED NULL,
  is_active  BOOLEAN DEFAULT TRUE,
  last_login_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  FOREIGN KEY (role_id) REFERENCES roles(id),
  FOREIGN KEY (shop_id) REFERENCES shops(id)
);

CREATE TABLE settings (
  id      BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  `key`   VARCHAR(100) UNIQUE NOT NULL,
  value   TEXT,
  `group` VARCHAR(50)
);

CREATE TABLE activity_logs (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED,
  action      VARCHAR(50),               -- created, updated, deleted
  model_type  VARCHAR(100),
  model_id    BIGINT UNSIGNED,
  old_data    JSON,
  new_data    JSON,
  ip_address  VARCHAR(45),
  created_at  TIMESTAMP NULL,
  INDEX idx_model (model_type, model_id),
  INDEX idx_user_date (user_id, created_at)
);

-- Polymorphic file store: order photos, design files, invoice slips, receipts
CREATE TABLE media (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  mediable_type VARCHAR(100) NOT NULL,
  mediable_id   BIGINT UNSIGNED NOT NULL,
  file_path     VARCHAR(255) NOT NULL,
  thumb_path    VARCHAR(255),
  mime_type     VARCHAR(100),
  file_size     INT UNSIGNED,
  caption       VARCHAR(255),
  sort_order    SMALLINT DEFAULT 0,
  uploaded_by   BIGINT UNSIGNED,
  created_at    TIMESTAMP NULL,
  INDEX idx_mediable (mediable_type, mediable_id)
);
```

---

## 3. Customers & Orders

```sql
CREATE TABLE customers (
  id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name         VARCHAR(150) NOT NULL,
  phone        VARCHAR(20) UNIQUE NOT NULL,     -- primary lookup key
  alt_phone    VARCHAR(20),
  address      TEXT,
  area         VARCHAR(100),
  customer_type ENUM('retail','dealer','contractor') DEFAULT 'retail',
  opening_due  DECIMAL(12,2) DEFAULT 0,
  note         TEXT,
  created_by   BIGINT UNSIGNED,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  INDEX idx_name (name), INDEX idx_area (area)
);

CREATE TABLE product_categories (
  id        BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name      VARCHAR(100) NOT NULL,             -- খাট, আলমারি, সোফা, ড্রেসিং টেবিল
  parent_id BIGINT UNSIGNED NULL,
  is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE orders (
  id                     BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  order_no               VARCHAR(30) UNIQUE NOT NULL,   -- SH-2607-0142
  customer_id            BIGINT UNSIGNED NOT NULL,
  shop_id                BIGINT UNSIGNED NOT NULL,
  order_date             DATE NOT NULL,
  expected_delivery_date DATE,
  delivered_at           DATETIME NULL,
  status ENUM('draft','confirmed','in_production','ready','delivered','cancelled')
         DEFAULT 'draft',
  subtotal      DECIMAL(12,2) DEFAULT 0,
  discount      DECIMAL(12,2) DEFAULT 0,
  delivery_charge DECIMAL(12,2) DEFAULT 0,
  total_amount  DECIMAL(12,2) DEFAULT 0,
  paid_amount   DECIMAL(12,2) DEFAULT 0,        -- denormalized, recalculated on payment
  due_amount    DECIMAL(12,2) DEFAULT 0,
  delivery_address TEXT,
  note          TEXT,
  created_by    BIGINT UNSIGNED,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (shop_id) REFERENCES shops(id),
  INDEX idx_status_date (status, order_date),
  INDEX idx_delivery (expected_delivery_date)
);

CREATE TABLE order_items (
  id           BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  order_id     BIGINT UNSIGNED NOT NULL,
  category_id  BIGINT UNSIGNED,
  item_name    VARCHAR(200) NOT NULL,
  description  TEXT,
  wood_type    VARCHAR(100),                    -- সেগুন, মেহগনি, চাম্বল
  design_no    VARCHAR(50),
  length       DECIMAL(8,2), width DECIMAL(8,2), height DECIMAL(8,2),
  dimension_unit ENUM('inch','feet','cm') DEFAULT 'inch',
  polish_type  VARCHAR(100),
  quantity     DECIMAL(10,2) DEFAULT 1,
  unit_price   DECIMAL(12,2) DEFAULT 0,
  line_total   DECIMAL(12,2) DEFAULT 0,
  target_date  DATE,
  status ENUM('pending','in_progress','completed') DEFAULT 'pending',
  remarks      TEXT,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE TABLE order_status_logs (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  order_id    BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(30), to_status VARCHAR(30),
  changed_by  BIGINT UNSIGNED,
  note        TEXT,
  created_at  TIMESTAMP NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
```

**Order number format:** `SH-YYMM-NNNN`. Keep it printable so it can be written on the paper slip during transition.

---

## 4. Workers, Attendance & Wages

```sql
CREATE TABLE trades (
  id                 BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name               VARCHAR(100) NOT NULL,   -- বার্নিশ, নকশা, প্লেন কাঠ, সিএনসি, হেলপার
  default_daily_rate DECIMAL(10,2) DEFAULT 0,
  is_active          BOOLEAN DEFAULT TRUE
);

CREATE TABLE employees (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  employee_code   VARCHAR(20) UNIQUE NOT NULL,
  name            VARCHAR(150) NOT NULL,
  phone           VARCHAR(20),
  address         TEXT,
  photo           VARCHAR(255),
  nid_no          VARCHAR(30),
  trade_id        BIGINT UNSIGNED,
  shop_id         BIGINT UNSIGNED,
  wage_type       ENUM('daily','monthly','piece') NOT NULL,
  daily_rate      DECIMAL(10,2) DEFAULT 0,
  monthly_salary  DECIMAL(12,2) DEFAULT 0,
  joining_date    DATE,
  guarantor_name  VARCHAR(150),
  guarantor_phone VARCHAR(20),
  opening_advance DECIMAL(12,2) DEFAULT 0,
  is_active       BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  FOREIGN KEY (trade_id) REFERENCES trades(id)
);

CREATE TABLE attendance (
  id             BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  employee_id    BIGINT UNSIGNED NOT NULL,
  shop_id        BIGINT UNSIGNED,
  work_date      DATE NOT NULL,
  status         ENUM('present','absent','half_day','leave','holiday') DEFAULT 'present',
  in_time        TIME NULL, out_time TIME NULL,
  overtime_hours DECIMAL(5,2) DEFAULT 0,
  overtime_rate  DECIMAL(10,2) DEFAULT 0,
  note           VARCHAR(255),
  marked_by      BIGINT UNSIGNED,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uk_emp_date (employee_id, work_date),
  FOREIGN KEY (employee_id) REFERENCES employees(id)
);

-- Single source of truth for what each worker has earned and taken.
-- balance = SUM(credit) - SUM(debit)  => positive means the shop owes the worker
CREATE TABLE employee_ledger (
  id             BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  employee_id    BIGINT UNSIGNED NOT NULL,
  entry_date     DATE NOT NULL,
  type ENUM('opening','wage_earned','piece_earned','overtime','bonus',
            'advance','tiffin','payout','fine','adjustment') NOT NULL,
  direction      ENUM('credit','debit') NOT NULL,
  amount         DECIMAL(12,2) NOT NULL,
  reference_type VARCHAR(100) NULL,        -- Attendance, OrderItemWork, Transaction
  reference_id   BIGINT UNSIGNED NULL,
  payment_method ENUM('cash','bkash','nagad','bank') NULL,
  note           VARCHAR(255),
  created_by     BIGINT UNSIGNED,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  FOREIGN KEY (employee_id) REFERENCES employees(id),
  INDEX idx_emp_date (employee_id, entry_date),
  INDEX idx_type_date (type, entry_date)
);

-- Who worked on which order item (piece work + productivity tracking)
CREATE TABLE order_item_works (
  id             BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  order_item_id  BIGINT UNSIGNED NOT NULL,
  employee_id    BIGINT UNSIGNED NOT NULL,
  trade_id       BIGINT UNSIGNED,
  work_type      VARCHAR(100),
  agreed_amount  DECIMAL(12,2) DEFAULT 0,   -- for piece-rate work
  assigned_date  DATE,
  started_at     DATETIME NULL,
  completed_at   DATETIME NULL,
  status ENUM('assigned','working','done','rejected') DEFAULT 'assigned',
  note           TEXT,
  FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
  FOREIGN KEY (employee_id) REFERENCES employees(id)
);

CREATE TABLE salary_periods (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  employee_id   BIGINT UNSIGNED NOT NULL,
  period_start  DATE NOT NULL, period_end DATE NOT NULL,
  present_days  DECIMAL(5,1) DEFAULT 0,
  gross_earned  DECIMAL(12,2) DEFAULT 0,
  total_advance DECIMAL(12,2) DEFAULT 0,
  total_tiffin  DECIMAL(12,2) DEFAULT 0,
  total_paid    DECIMAL(12,2) DEFAULT 0,
  net_payable   DECIMAL(12,2) DEFAULT 0,
  settled_at    DATETIME NULL,
  UNIQUE KEY uk_emp_period (employee_id, period_start, period_end)
);
```

**Automation rules:**
- On saving attendance with `status = present`, insert `employee_ledger` row: `type = wage_earned`, `direction = credit`, `amount = employees.daily_rate` (half for `half_day`). Skip for `wage_type = monthly`.
- For `wage_type = monthly`, insert one `wage_earned` credit on the last day of the month.
- On `order_item_works.status = done` with an `agreed_amount`, insert `piece_earned` credit.
- Advance, tiffin and payout each insert a debit AND a matching `transactions` row.

---

## 5. Readymade Products & Stock

```sql
CREATE TABLE products (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  sku           VARCHAR(50) UNIQUE NOT NULL,
  name          VARCHAR(200) NOT NULL,
  category_id   BIGINT UNSIGNED,
  description   TEXT,
  wood_type     VARCHAR(100),
  size_label    VARCHAR(100),
  cost_price    DECIMAL(12,2) DEFAULT 0,
  sale_price    DECIMAL(12,2) DEFAULT 0,
  current_stock DECIMAL(10,2) DEFAULT 0,
  min_stock     DECIMAL(10,2) DEFAULT 0,
  shop_id       BIGINT UNSIGNED,
  is_active     BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL
);

CREATE TABLE stock_movements (
  id             BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  product_id     BIGINT UNSIGNED NOT NULL,
  shop_id        BIGINT UNSIGNED,
  movement_date  DATE NOT NULL,
  type ENUM('production_in','purchase_in','sale_out','order_out',
            'transfer_in','transfer_out','damage','adjustment') NOT NULL,
  quantity       DECIMAL(10,2) NOT NULL,
  unit_cost      DECIMAL(12,2) DEFAULT 0,
  reference_type VARCHAR(100), reference_id BIGINT UNSIGNED,
  note           VARCHAR(255),
  created_by     BIGINT UNSIGNED,
  created_at     TIMESTAMP NULL,
  FOREIGN KEY (product_id) REFERENCES products(id),
  INDEX idx_prod_date (product_id, movement_date)
);

CREATE TABLE sales (
  id             BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  invoice_no     VARCHAR(30) UNIQUE NOT NULL,
  customer_id    BIGINT UNSIGNED NULL,          -- null for walk-in
  customer_name  VARCHAR(150),                  -- walk-in name if no record
  customer_phone VARCHAR(20),
  shop_id        BIGINT UNSIGNED NOT NULL,
  sale_date      DATE NOT NULL,
  subtotal       DECIMAL(12,2) DEFAULT 0,
  discount       DECIMAL(12,2) DEFAULT 0,
  delivery_charge DECIMAL(12,2) DEFAULT 0,
  total_amount   DECIMAL(12,2) DEFAULT 0,
  paid_amount    DECIMAL(12,2) DEFAULT 0,
  due_amount     DECIMAL(12,2) DEFAULT 0,
  note           TEXT,
  created_by     BIGINT UNSIGNED,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL
);

CREATE TABLE sale_items (
  id         BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  sale_id    BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  quantity   DECIMAL(10,2) NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
);
```

---

## 6. CNC Job Work

```sql
CREATE TABLE cnc_jobs (
  id             BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  job_no         VARCHAR(30) UNIQUE NOT NULL,
  customer_id    BIGINT UNSIGNED NULL,
  customer_name  VARCHAR(150),
  customer_phone VARCHAR(20),
  order_id       BIGINT UNSIGNED NULL,          -- if it is our own order
  job_date       DATE NOT NULL,
  description    TEXT,
  material_by    ENUM('customer','shop') DEFAULT 'customer',
  rate_type      ENUM('per_sqft','per_piece','per_hour','fixed') DEFAULT 'per_sqft',
  quantity       DECIMAL(10,2) DEFAULT 1,
  rate           DECIMAL(12,2) DEFAULT 0,
  total_amount   DECIMAL(12,2) DEFAULT 0,
  paid_amount    DECIMAL(12,2) DEFAULT 0,
  due_amount     DECIMAL(12,2) DEFAULT 0,
  machine_hours  DECIMAL(6,2) DEFAULT 0,
  operator_id    BIGINT UNSIGNED NULL,
  status ENUM('pending','running','completed','delivered','cancelled') DEFAULT 'pending',
  delivery_date  DATE,
  note           TEXT,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL
);
```

---

## 7. Suppliers, Purchases & Credit

```sql
CREATE TABLE suppliers (
  id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name                VARCHAR(150) NOT NULL,
  business_name       VARCHAR(150),
  phone               VARCHAR(20),
  address             TEXT,
  supplier_type       ENUM('wood','hardware','paint','transport','other') DEFAULT 'other',
  opening_due         DECIMAL(12,2) DEFAULT 0,
  credit_limit        DECIMAL(12,2) DEFAULT 0,
  default_credit_days SMALLINT DEFAULT 0,
  is_active           BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL
);

CREATE TABLE materials (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name          VARCHAR(150) NOT NULL,
  category      ENUM('wood','board','hardware','paint','polish','glue','other') NOT NULL,
  unit          ENUM('cft','sqft','piece','kg','litre','bundle','set') NOT NULL,
  current_stock DECIMAL(12,3) DEFAULT 0,
  avg_cost      DECIMAL(12,2) DEFAULT 0,
  min_stock     DECIMAL(12,3) DEFAULT 0,
  is_active     BOOLEAN DEFAULT TRUE
);

CREATE TABLE purchases (
  id               BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  purchase_no      VARCHAR(30) UNIQUE NOT NULL,
  supplier_id      BIGINT UNSIGNED NOT NULL,
  shop_id          BIGINT UNSIGNED,
  purchase_date    DATE NOT NULL,
  reference_no     VARCHAR(50),                 -- supplier challan number
  payment_type     ENUM('cash','credit','partial') DEFAULT 'cash',
  payment_due_date DATE NULL,
  subtotal         DECIMAL(12,2) DEFAULT 0,
  transport_cost   DECIMAL(12,2) DEFAULT 0,
  discount         DECIMAL(12,2) DEFAULT 0,
  total_amount     DECIMAL(12,2) DEFAULT 0,
  paid_amount      DECIMAL(12,2) DEFAULT 0,
  due_amount       DECIMAL(12,2) DEFAULT 0,
  status           ENUM('pending','partial','paid','returned') DEFAULT 'pending',
  note             TEXT,
  created_by       BIGINT UNSIGNED,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  INDEX idx_due (payment_due_date, status)
);

CREATE TABLE purchase_items (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  purchase_id BIGINT UNSIGNED NOT NULL,
  item_type   ENUM('material','product') NOT NULL,
  item_id     BIGINT UNSIGNED NOT NULL,      -- materials.id or products.id
  quantity    DECIMAL(12,3) NOT NULL,
  unit        VARCHAR(20),
  unit_price  DECIMAL(12,2) NOT NULL,
  line_total  DECIMAL(12,2) NOT NULL,
  note        VARCHAR(255),
  FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
  INDEX idx_item (item_type, item_id)
);

-- balance = SUM(credit) - SUM(debit) => positive means we owe the supplier
CREATE TABLE supplier_ledger (
  id             BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  supplier_id    BIGINT UNSIGNED NOT NULL,
  entry_date     DATE NOT NULL,
  type ENUM('opening','purchase','payment','return','discount','adjustment') NOT NULL,
  direction      ENUM('credit','debit') NOT NULL,
  amount         DECIMAL(12,2) NOT NULL,
  reference_type VARCHAR(100), reference_id BIGINT UNSIGNED,
  note           VARCHAR(255),
  created_by     BIGINT UNSIGNED,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  INDEX idx_sup_date (supplier_id, entry_date)
);

CREATE TABLE material_movements (
  id             BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  material_id    BIGINT UNSIGNED NOT NULL,
  movement_date  DATE NOT NULL,
  type           ENUM('in','out','wastage','return','adjustment') NOT NULL,
  quantity       DECIMAL(12,3) NOT NULL,
  unit_cost      DECIMAL(12,2) DEFAULT 0,
  reference_type VARCHAR(100), reference_id BIGINT UNSIGNED,
  order_id       BIGINT UNSIGNED NULL,      -- which order consumed it
  note           VARCHAR(255),
  created_by     BIGINT UNSIGNED,
  created_at     TIMESTAMP NULL,
  FOREIGN KEY (material_id) REFERENCES materials(id),
  INDEX idx_mat_date (material_id, movement_date),
  INDEX idx_order (order_id)
);
```

---

## 8. Money: Payments, Expenses, Cash Ledger

```sql
CREATE TABLE accounts (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name            VARCHAR(100) NOT NULL,     -- ক্যাশ বাক্স, বিকাশ, নগদ, ব্যাংক
  type            ENUM('cash','mobile_banking','bank') NOT NULL,
  account_no      VARCHAR(50),
  shop_id         BIGINT UNSIGNED NULL,
  opening_balance DECIMAL(14,2) DEFAULT 0,
  current_balance DECIMAL(14,2) DEFAULT 0,
  is_active       BOOLEAN DEFAULT TRUE
);

CREATE TABLE expense_categories (
  id        BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name      VARCHAR(100) NOT NULL,   -- দোকান ভাড়া, কারেন্ট বিল, চা-নাস্তা,
                                     -- ট্রান্সপোর্ট, মেশিন মেরামত, লাইসেন্স
  is_recurring BOOLEAN DEFAULT FALSE,
  is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE expenses (
  id             BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  shop_id        BIGINT UNSIGNED,
  category_id    BIGINT UNSIGNED NOT NULL,
  expense_date   DATE NOT NULL,
  amount         DECIMAL(12,2) NOT NULL,
  paid_to        VARCHAR(150),
  payment_method ENUM('cash','bkash','nagad','bank') DEFAULT 'cash',
  account_id     BIGINT UNSIGNED,
  note           TEXT,
  created_by     BIGINT UNSIGNED,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  FOREIGN KEY (category_id) REFERENCES expense_categories(id),
  INDEX idx_date_cat (expense_date, category_id)
);

-- One payment can settle several invoices
CREATE TABLE party_payments (
  id             BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  party_type     ENUM('supplier','customer') NOT NULL,
  party_id       BIGINT UNSIGNED NOT NULL,
  direction      ENUM('in','out') NOT NULL,   -- in = received, out = paid
  payment_date   DATE NOT NULL,
  amount         DECIMAL(12,2) NOT NULL,
  account_id     BIGINT UNSIGNED NOT NULL,
  payment_method ENUM('cash','bkash','nagad','bank','cheque') DEFAULT 'cash',
  reference_no   VARCHAR(50),
  note           VARCHAR(255),
  created_by     BIGINT UNSIGNED,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  INDEX idx_party (party_type, party_id, payment_date)
);

CREATE TABLE payment_allocations (
  id                BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  party_payment_id  BIGINT UNSIGNED NOT NULL,
  allocatable_type  VARCHAR(100) NOT NULL,     -- Purchase, Order, Sale, CncJob
  allocatable_id    BIGINT UNSIGNED NOT NULL,
  allocated_amount  DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (party_payment_id) REFERENCES party_payments(id) ON DELETE CASCADE,
  INDEX idx_alloc (allocatable_type, allocatable_id)
);

-- THE CASH LEDGER. One row for every actual movement of money.
CREATE TABLE transactions (
  id             BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  txn_date       DATE NOT NULL,
  shop_id        BIGINT UNSIGNED,
  account_id     BIGINT UNSIGNED NOT NULL,
  direction      ENUM('in','out') NOT NULL,
  amount         DECIMAL(12,2) NOT NULL,
  source_type ENUM('order_payment','sale','cnc_payment','purchase_payment',
                   'expense','employee_payment','capital_in','owner_draw',
                   'transfer_in','transfer_out','adjustment') NOT NULL,
  source_id      BIGINT UNSIGNED NULL,
  party_type     VARCHAR(50) NULL, party_id BIGINT UNSIGNED NULL,
  payment_method ENUM('cash','bkash','nagad','bank','cheque') DEFAULT 'cash',
  note           VARCHAR(255),
  created_by     BIGINT UNSIGNED,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  FOREIGN KEY (account_id) REFERENCES accounts(id),
  INDEX idx_date_shop (txn_date, shop_id),
  INDEX idx_source (source_type, source_id)
);

CREATE TABLE daily_closings (
  id               BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  shop_id          BIGINT UNSIGNED NOT NULL,
  closing_date     DATE NOT NULL,
  opening_balance  DECIMAL(14,2) DEFAULT 0,
  total_in         DECIMAL(14,2) DEFAULT 0,
  total_out        DECIMAL(14,2) DEFAULT 0,
  net_amount       DECIMAL(14,2) DEFAULT 0,
  expected_closing DECIMAL(14,2) DEFAULT 0,
  counted_cash     DECIMAL(14,2) DEFAULT 0,
  difference       DECIMAL(14,2) DEFAULT 0,
  credit_purchase_today DECIMAL(14,2) DEFAULT 0,
  total_payable    DECIMAL(14,2) DEFAULT 0,
  total_receivable DECIMAL(14,2) DEFAULT 0,
  closed_by        BIGINT UNSIGNED,
  closed_at        DATETIME NULL,
  note             TEXT,
  UNIQUE KEY uk_shop_date (shop_id, closing_date)
);
```

---

## 9. Event to Table Mapping

| Event | Operational | Party ledger | Cash ledger |
|---|---|---|---|
| Attendance marked | `attendance` | `employee_ledger` credit | none |
| Tiffin money given | none | `employee_ledger` debit | `transactions` out |
| Advance given | none | `employee_ledger` debit | `transactions` out |
| Weekly payout | none | `employee_ledger` debit | `transactions` out |
| New order + advance | `orders`, `order_items`, `media` | order `due_amount` | `transactions` in |
| Order installment | `party_payments`, `payment_allocations` | order `due_amount` | `transactions` in |
| Readymade cash sale | `sales`, `stock_movements` | none | `transactions` in |
| Readymade credit sale | `sales`, `stock_movements` | sale `due_amount` | none |
| CNC job done + paid | `cnc_jobs` | none | `transactions` in |
| Cash purchase | `purchases`, `material_movements` | `supplier_ledger` credit + debit | `transactions` out |
| **Credit purchase** | `purchases`, `material_movements` | `supplier_ledger` credit | **none** |
| Paying supplier later | `party_payments`, `payment_allocations` | `supplier_ledger` debit | `transactions` out |
| Shop rent / bill | `expenses` | none | `transactions` out |
| Material issued to order | `material_movements` out with `order_id` | none | none |

---

## 10. Core Reports

**Daily closing**
```sql
SELECT direction, SUM(amount) AS total
FROM transactions
WHERE txn_date = ? AND shop_id = ?
GROUP BY direction;
```

**Worker balance**
```sql
SELECT e.name,
  SUM(CASE WHEN l.direction='credit' THEN l.amount ELSE -l.amount END) AS balance
FROM employees e
LEFT JOIN employee_ledger l ON l.employee_id = e.id
WHERE e.is_active = 1
GROUP BY e.id, e.name
ORDER BY balance DESC;
```

**Supplier payable + aging**
```sql
SELECT s.name, p.purchase_no, p.purchase_date, p.due_amount,
       DATEDIFF(CURDATE(), p.purchase_date) AS age_days
FROM purchases p
JOIN suppliers s ON s.id = p.supplier_id
WHERE p.due_amount > 0
ORDER BY age_days DESC;
```

**Per-order profit**
```
profit = orders.total_amount
       - (material cost from material_movements WHERE order_id = X)
       - (labour cost from order_item_works agreed_amount + allocated daily wages)
       - allocated overhead
```

Other reports to build: monthly profit and loss, customer due list, delivery due this week, low stock alert, worker productivity by trade, revenue split across orders vs readymade vs CNC.

---

## 11. Build Phases

| Phase | Weeks | Tables | Deliverable |
|---|---|---|---|
| 1. Foundation | 1-2 | shops, roles, users, settings, media, customers, employees, trades, accounts, expense_categories | Login, master data entry |
| 2. Labour | 3-4 | attendance, employee_ledger, order_item_works | Attendance screen, worker balance |
| 3. Orders | 5-6 | orders, order_items, order_status_logs | Order entry with photos, phone search |
| 4. Cash | 7-8 | transactions, expenses, daily_closings | Automated nightly closing |
| 5. Purchase & credit | 9-10 | suppliers, materials, purchases, purchase_items, supplier_ledger, party_payments, payment_allocations, material_movements | Payable tracking, aging |
| 6. Retail & CNC | 11-12 | products, product_categories, stock_movements, sales, sale_items, cnc_jobs | Invoicing, stock |
| 7. Reports | 13+ | salary_periods + views | P&L, per-order profit, dashboards |

---

## 12. Rollout Checklist

- [ ] Seed opening balances on day one: supplier dues, customer dues, worker advances, cash in hand, material stock
- [ ] All shop-floor UI labels in Bengali
- [ ] Role separation: manager sees orders and attendance, profit reports for owner only
- [ ] One designated data entry person per shop
- [ ] Entries made at the moment they happen, not batched at night
- [ ] Run paper ledger in parallel for the first month, reconcile at month end
- [ ] Server-side image compression (max 1600px, ~200KB) with thumbnails
- [ ] Nightly automated database backup to off-site storage
- [ ] PWA with offline queue for attendance and order entry screens
- [ ] Printable Bengali invoice, money receipt, and supplier statement
