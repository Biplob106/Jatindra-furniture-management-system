import { toBengaliDigits } from '@/components/data-table';
import { FlashMessages } from '@/components/flash-messages';
import { Money } from '@/components/money';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowDownLeft, ArrowUpRight, Boxes, ClipboardList, HandCoins, TriangleAlert, Wallet } from 'lucide-react';
import { ReactNode } from 'react';

interface Cash {
    cash_in_hand: string;
    today_in: string;
    today_out: string;
}

interface Orders {
    receivable: string;
    open_orders: number;
    due_this_week: number;
    late_delivery: number;
}

interface Payable {
    payable: string;
    owing_challans: number;
    overdue_challans: number;
}

interface Labour {
    worker_dues: string;
    workers_owed: number;
}

interface Props {
    /** Null when the reader holds no permission for that block. */
    cash: Cash | null;
    orders: Orders | null;
    payable: Payable | null;
    labour: Labour | null;
    stock: { low_stock: number } | null;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'ড্যাশবোর্ড', href: '/dashboard' }];

interface TileProps {
    label: string;
    href: string;
    icon: ReactNode;
    children: ReactNode;
    footer?: ReactNode;
}

function Tile({ label, href, icon, children, footer }: TileProps) {
    return (
        <Link href={href} className="hover:bg-muted/50 flex flex-col gap-1 rounded-xl border p-4 transition-colors">
            <div className="text-muted-foreground flex items-center gap-2 text-sm">
                {icon}
                {label}
            </div>
            <div className="text-2xl font-semibold">{children}</div>
            {footer && <div className="text-muted-foreground text-sm">{footer}</div>}
        </Link>
    );
}

export default function Dashboard({ cash, orders, payable, labour, stock }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="ড্যাশবোর্ড" />

            <div className="flex flex-col gap-4 p-4">
                <FlashMessages />

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {cash && (
                        <Tile
                            label="হাতে নগদ"
                            href={route('daily-closing.index')}
                            icon={<Wallet className="size-4" />}
                            footer={
                                <span className="flex gap-3">
                                    <span className="flex items-center gap-1 text-emerald-600">
                                        <ArrowDownLeft className="size-3" />
                                        <Money amount={cash.today_in} />
                                    </span>
                                    <span className="text-destructive flex items-center gap-1">
                                        <ArrowUpRight className="size-3" />
                                        <Money amount={cash.today_out} />
                                    </span>
                                </span>
                            }
                        >
                            <Money amount={cash.cash_in_hand} />
                        </Tile>
                    )}

                    {orders && (
                        <Tile
                            label="কাস্টমারের কাছে পাওনা"
                            href={route('orders.index', { status: 'open' })}
                            icon={<ClipboardList className="size-4" />}
                            footer={`${toBengaliDigits(orders.open_orders)} টি অর্ডার চলছে`}
                        >
                            <Money amount={orders.receivable} />
                        </Tile>
                    )}

                    {payable && (
                        <Tile
                            label="সরবরাহকারীকে দেনা"
                            href={route('supplier-ledger.index')}
                            icon={<HandCoins className="size-4" />}
                            footer={
                                payable.overdue_challans > 0 ? (
                                    <span className="text-destructive">
                                        {toBengaliDigits(payable.overdue_challans)} টি চালানের মেয়াদ পার হয়েছে
                                    </span>
                                ) : (
                                    `${toBengaliDigits(payable.owing_challans)} টি চালান বাকি`
                                )
                            }
                        >
                            <Money amount={payable.payable} />
                        </Tile>
                    )}

                    {labour && (
                        <Tile
                            label="কর্মীদের পাওনা"
                            href={route('employee-ledger.index')}
                            icon={<HandCoins className="size-4" />}
                            footer={`${toBengaliDigits(labour.workers_owed)} জন কর্মীর পাওনা আছে`}
                        >
                            <Money amount={labour.worker_dues} />
                        </Tile>
                    )}

                    {orders && (
                        <Tile
                            label="এই সপ্তাহে ডেলিভারি"
                            href={route('orders.index', { status: 'open' })}
                            icon={<ClipboardList className="size-4" />}
                            footer={
                                orders.late_delivery > 0 ? (
                                    <span className="text-destructive">
                                        {toBengaliDigits(orders.late_delivery)} টি অর্ডারের তারিখ পার হয়েছে
                                    </span>
                                ) : (
                                    'সব ঠিক আছে'
                                )
                            }
                        >
                            {toBengaliDigits(orders.due_this_week)} টি
                        </Tile>
                    )}

                    {stock && (
                        <Tile
                            label="মজুদ কম"
                            href={route('materials.index', { low: 1 })}
                            icon={<Boxes className="size-4" />}
                            footer={stock.low_stock > 0 ? 'কেনার সময় হয়েছে' : 'সব মালামাল যথেষ্ট আছে'}
                        >
                            <span className={stock.low_stock > 0 ? 'text-destructive' : undefined}>
                                {toBengaliDigits(stock.low_stock)} টি
                            </span>
                        </Tile>
                    )}
                </div>

                {/* A reader with no permission for any block gets a page rather than
                    an empty grid that reads as a broken screen. */}
                {!cash && !orders && !payable && !labour && !stock && (
                    <div className="text-muted-foreground flex flex-col items-center gap-2 rounded-xl border border-dashed p-10 text-center">
                        <TriangleAlert className="size-5" />
                        আপনার জন্য এখনো কোনো হিসাব দেখানোর অনুমতি নেই।
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
