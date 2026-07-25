import { Option, SelectField, TextField } from '@/components/form-field';
import { StickySaveBar } from '@/components/sticky-save-bar';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Props {
    roles: Option[];
    shops: Option[];
}

interface UserForm {
    [key: string]: string;
    name: string;
    phone: string;
    email: string;
    password: string;
    password_confirmation: string;
    role: string;
    shop_id: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ড্যাশবোর্ড', href: '/dashboard' },
    { title: 'ব্যবহারকারী', href: '/users' },
    { title: 'নতুন', href: '/users/create' },
];

export default function CreateUser({ roles, shops }: Props) {
    const { data, setData, post, processing, errors } = useForm<UserForm>({
        name: '',
        phone: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: '',
        shop_id: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('users.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="নতুন ব্যবহারকারী" />

            <form onSubmit={submit} className="flex max-w-2xl flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">নতুন ব্যবহারকারী</h1>
                    <p className="text-muted-foreground text-sm">মোবাইল নম্বর দিয়েই লগ ইন করা হবে</p>
                </div>

                <TextField id="name" label="নাম" value={data.name} onChange={(v) => setData('name', v)} error={errors.name} required autoFocus />

                <TextField
                    id="phone"
                    label="মোবাইল নম্বর"
                    type="tel"
                    numeric
                    value={data.phone}
                    onChange={(v) => setData('phone', v)}
                    error={errors.phone}
                    required
                    placeholder="01XXXXXXXXX"
                    hint="এই নম্বর দিয়ে লগ ইন করতে হবে"
                />

                <SelectField
                    id="role"
                    label="পদ"
                    value={data.role}
                    onChange={(v) => setData('role', v)}
                    options={roles}
                    error={errors.role}
                    required
                />

                <SelectField
                    id="shop_id"
                    label="দোকান"
                    value={data.shop_id}
                    onChange={(v) => setData('shop_id', v)}
                    options={shops}
                    error={errors.shop_id}
                    emptyLabel="সব দোকান"
                    hint="খালি রাখলে সব দোকানে কাজ করতে পারবে"
                />

                <TextField
                    id="email"
                    label="ইমেইল"
                    type="email"
                    value={data.email}
                    onChange={(v) => setData('email', v)}
                    error={errors.email}
                    hint="ঐচ্ছিক"
                />

                <TextField
                    id="password"
                    label="পাসওয়ার্ড"
                    type="password"
                    value={data.password}
                    onChange={(v) => setData('password', v)}
                    error={errors.password}
                    required
                />

                <TextField
                    id="password_confirmation"
                    label="পাসওয়ার্ড আবার দিন"
                    type="password"
                    value={data.password_confirmation}
                    onChange={(v) => setData('password_confirmation', v)}
                    error={errors.password_confirmation}
                    required
                />

                <StickySaveBar processing={processing} cancelHref={route('users.index')} />
            </form>
        </AppLayout>
    );
}
