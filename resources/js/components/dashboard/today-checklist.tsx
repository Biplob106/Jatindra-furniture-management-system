import { toBengaliDigits } from '@/components/data-table';
import { Link } from '@inertiajs/react';
import { ChevronRight, CircleCheck, CircleX, TriangleAlert } from 'lucide-react';
import { ReactNode } from 'react';

export interface TodayData {
    /** Each block is null when the reader holds no permission for it. */
    attendance: { marked: number; total: number } | null;
    closing: { shop_id: number; name: string; closed: boolean }[] | null;
    late_orders: { id: number; order_no: string | null; customer_name: string | null; days_late: number }[] | null;
}

type Tone = 'done' | 'todo' | 'warn';

const toneIcon: Record<Tone, ReactNode> = {
    done: <CircleCheck className="size-5 shrink-0 text-emerald-600" />,
    todo: <CircleX className="text-destructive size-5 shrink-0" />,
    warn: <TriangleAlert className="size-5 shrink-0 text-amber-600" />,
};

function Row({ tone, children, href, action }: { tone: Tone; children: ReactNode; href?: string; action?: string }) {
    return (
        <li className="flex min-h-12 items-center gap-3 px-4 py-2">
            {toneIcon[tone]}
            <span className="flex-1">{children}</span>
            {href && action && (
                <Link href={href} className="text-primary flex min-h-11 items-center gap-1 font-medium">
                    {action}
                    <ChevronRight className="size-4" />
                </Link>
            )}
        </li>
    );
}

/**
 * Today's unfinished jobs, each with a link straight to the screen that
 * finishes it. Renders nothing when there is nothing the reader may see.
 */
export function TodayChecklist({ today, lateTotal }: { today: TodayData; lateTotal: number }) {
    const { attendance, closing, late_orders } = today;

    const showAttendance = attendance !== null && attendance.total > 0;
    const showClosing = closing !== null && closing.length > 0;
    const showLate = late_orders !== null && late_orders.length > 0;

    if (!showAttendance && !showClosing && !showLate) {
        return null;
    }

    return (
        <section className="flex flex-col gap-2">
            <h2 className="text-lg font-semibold">আজকের কাজ</h2>
            <ul className="divide-y rounded-xl border">
                {showAttendance &&
                    (attendance.marked >= attendance.total ? (
                        <Row tone="done">
                            হাজিরা নেওয়া হয়েছে ({toBengaliDigits(attendance.marked)}/{toBengaliDigits(attendance.total)})
                        </Row>
                    ) : (
                        <Row tone="todo" href={route('attendance.index')} action="হাজিরা নিন">
                            হাজিরা বাকি ({toBengaliDigits(attendance.marked)}/{toBengaliDigits(attendance.total)})
                        </Row>
                    ))}

                {showClosing &&
                    closing.map((shop) =>
                        shop.closed ? (
                            <Row key={shop.shop_id} tone="done">
                                দিন শেষের হিসাব বন্ধ হয়েছে{closing.length > 1 && ` — ${shop.name}`}
                            </Row>
                        ) : (
                            <Row key={shop.shop_id} tone="todo" href={route('daily-closing.index', { shop_id: shop.shop_id })} action="এখন করুন">
                                দিন শেষের হিসাব বাকি{closing.length > 1 && ` — ${shop.name}`}
                            </Row>
                        ),
                    )}

                {showLate && (
                    <li className="flex flex-col">
                        <Row tone="warn" href={route('orders.index', { status: 'open' })} action="সব দেখুন">
                            {toBengaliDigits(lateTotal)}টি অর্ডারের ডেলিভারি পেছিয়েছে
                        </Row>
                        <ul className="pb-2">
                            {late_orders.map((order) => (
                                <li key={order.id}>
                                    <Link
                                        href={route('orders.show', order.id)}
                                        className="hover:bg-muted/50 flex min-h-11 items-center gap-3 py-2 pr-4 pl-12 text-sm"
                                    >
                                        <span className="font-medium">{order.order_no ?? 'খসড়া'}</span>
                                        <span className="text-muted-foreground flex-1 truncate">{order.customer_name ?? ''}</span>
                                        <span className="text-destructive">{toBengaliDigits(order.days_late)} দিন দেরি</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </li>
                )}
            </ul>
        </section>
    );
}
