import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'পে-রোল সেটিংস',
        href: '/settings/payroll',
    },
];

interface Props {
    nightMultiplier: string;
}

export default function PayrollSettings({ nightMultiplier }: Props) {
    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        night_multiplier: nightMultiplier,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('settings.payroll.update'), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="পে-রোল সেটিংস" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="রাত্রি ভাতা" description="দৈনিক হাজিরার কত অংশ রাতের জন্য দেওয়া হবে" />

                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="night_multiplier">রাত্রি ভাতার হার (দৈনিক হাজিরার গুণক)</Label>

                            <Input
                                id="night_multiplier"
                                type="number"
                                inputMode="decimal"
                                step="0.05"
                                min="0"
                                className="mt-1 block w-full"
                                value={data.night_multiplier}
                                onChange={(e) => setData('night_multiplier', e.target.value)}
                                required
                            />

                            <p className="text-muted-foreground text-sm">
                                যেমন ০.৫ দিলে দৈনিক হাজিরার অর্ধেক রাত্রি ভাতা হবে। কোনো কর্মীর আলাদা হার দেওয়া থাকলে সেটিই প্রাধান্য পাবে।
                            </p>

                            <InputError className="mt-2" message={errors.night_multiplier} />
                        </div>

                        <div className="flex items-center gap-4">
                            <Button disabled={processing}>সংরক্ষণ করুন</Button>

                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-muted-foreground text-sm">সংরক্ষিত হয়েছে</p>
                            </Transition>
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
