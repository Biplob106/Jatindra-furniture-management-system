import { toBengaliDigits } from '@/components/data-table';
import { Option, SelectField, TextField } from '@/components/form-field';
import { Money } from '@/components/money';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { orderItemWorkStatusLabels, type OrderItemWorkStatus } from '@/types/enums';
import { router, useForm } from '@inertiajs/react';
import { LoaderCircle, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

export interface Work {
    id: number;
    employee_id: number;
    employee: string;
    work_type: string | null;
    agreed_amount: string;
    status: OrderItemWorkStatus;
    assigned_date: string | null;
    completed_at: string | null;
}

export interface WorkerOption extends Option {
    isPieceWorker: boolean;
}

interface Props {
    itemId: number;
    works: Work[];
    workers: WorkerOption[];
    workStatuses: Option[];
    today: string;
}

const statusTone: Record<OrderItemWorkStatus, string> = {
    assigned: 'bg-muted text-muted-foreground',
    working: 'bg-amber-600/10 text-amber-700 dark:text-amber-400',
    done: 'bg-green-600/10 text-green-700 dark:text-green-400',
    rejected: 'bg-destructive/10 text-destructive',
};

/**
 * Work handed out on one order item.
 *
 * Marking a job done is what pays a piece worker, so the contract amount is
 * only offered for one, and moving a job off done takes the money back. Both
 * rules live on the server; this only avoids asking for something that would
 * be refused.
 */
export function ItemWorks({ itemId, works, workers, workStatuses, today }: Props) {
    const [adding, setAdding] = useState(false);

    const form = useForm({
        employee_id: '',
        work_type: '',
        agreed_amount: '0',
        assigned_date: today,
        status: 'assigned',
        note: '',
    });

    const selectedWorker = workers.find((worker) => String(worker.value) === form.data.employee_id);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        form.post(route('order-items.works.store', itemId), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setAdding(false);
            },
        });
    };

    const moveStatus = (work: Work, status: OrderItemWorkStatus) => {
        router.put(
            route('order-items.works.update', [itemId, work.id]),
            {
                employee_id: work.employee_id,
                work_type: work.work_type ?? '',
                agreed_amount: work.agreed_amount,
                assigned_date: work.assigned_date ?? today,
                status,
            },
            { preserveScroll: true },
        );
    };

    return (
        <div className="mt-3 flex flex-col gap-2 border-t pt-3">
            <div className="flex items-center justify-between">
                <h3 className="text-muted-foreground text-sm font-medium">কাজ</h3>
                {!adding && (
                    <Button type="button" variant="ghost" size="sm" onClick={() => setAdding(true)}>
                        <Plus className="h-4 w-4" />
                        কাজ দিন
                    </Button>
                )}
            </div>

            {works.map((work) => (
                <div key={work.id} className="flex flex-wrap items-center gap-2 rounded-lg border p-3 text-sm">
                    <div className="min-w-0 flex-1">
                        <p className="font-medium">{work.employee}</p>
                        <p className="text-muted-foreground">
                            {[work.work_type, work.assigned_date && toBengaliDigits(work.assigned_date)]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                    </div>

                    {Number(work.agreed_amount) > 0 && <Money amount={work.agreed_amount} className="font-medium" />}

                    <Badge className={cn('border-0', statusTone[work.status])}>{orderItemWorkStatusLabels[work.status]}</Badge>

                    <div className="flex gap-1">
                        {workStatuses
                            .filter((status) => status.value !== work.status)
                            .map((status) => (
                                <Button
                                    key={status.value}
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => moveStatus(work, status.value as OrderItemWorkStatus)}
                                >
                                    {status.label}
                                </Button>
                            ))}

                        {work.status !== 'done' && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                title="বাদ দিন"
                                onClick={() => {
                                    if (window.confirm(`${work.employee} এর কাজ বাদ দিতে চান?`)) {
                                        router.delete(route('order-items.works.destroy', [itemId, work.id]), {
                                            preserveScroll: true,
                                        });
                                    }
                                }}
                            >
                                <Trash2 className="text-destructive h-4 w-4" />
                            </Button>
                        )}
                    </div>
                </div>
            ))}

            {works.length === 0 && !adding && <p className="text-muted-foreground text-sm">এখনো কাউকে কাজ দেওয়া হয়নি।</p>}

            {adding && (
                <form onSubmit={submit} className="grid gap-3 rounded-lg border p-3 sm:grid-cols-2">
                    <SelectField
                        id={`worker_${itemId}`}
                        label="কর্মী"
                        value={form.data.employee_id}
                        onChange={(value) => form.setData('employee_id', value)}
                        options={workers}
                        error={form.errors.employee_id}
                        required
                    />

                    <TextField
                        id={`work_type_${itemId}`}
                        label="কী কাজ"
                        value={form.data.work_type}
                        onChange={(value) => form.setData('work_type', value)}
                        error={form.errors.work_type}
                        placeholder="নকশা, বার্নিশ"
                    />

                    {selectedWorker?.isPieceWorker ? (
                        <TextField
                            id={`agreed_${itemId}`}
                            label="চুক্তির টাকা"
                            type="number"
                            numeric
                            value={form.data.agreed_amount}
                            onChange={(value) => form.setData('agreed_amount', value)}
                            error={form.errors.agreed_amount}
                            hint="কাজ শেষ হলে এই টাকা কর্মীর হিসাবে জমা হবে"
                        />
                    ) : (
                        selectedWorker && (
                            <p className="text-muted-foreground bg-muted/50 rounded-lg border p-3 text-sm sm:col-span-1">
                                এই কর্মী হাজিরা বা মাসিক বেতনে আছেন, তাই আলাদা চুক্তির টাকা দেওয়া যাবে না।
                            </p>
                        )
                    )}

                    <SelectField
                        id={`work_status_${itemId}`}
                        label="অবস্থা"
                        value={form.data.status}
                        onChange={(value) => form.setData('status', value)}
                        options={workStatuses}
                        error={form.errors.status}
                        required
                    />

                    <div className="flex gap-2 sm:col-span-2">
                        <Button type="submit" className="h-11 flex-1" disabled={form.processing}>
                            {form.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            সংরক্ষণ করুন
                        </Button>
                        <Button type="button" variant="outline" className="h-11" onClick={() => setAdding(false)}>
                            বাতিল
                        </Button>
                    </div>
                </form>
            )}
        </div>
    );
}
