import { Option, SelectField, TextField } from '@/components/form-field';
import { StickySaveBar } from '@/components/sticky-save-bar';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface EditableUser {
    id: number;
    name: string;
    phone: string;
    email: string | null;
    shop_id: number | null;
    is_active: boolean;
    role: string | null;
}

interface Props {
    user: EditableUser;
    roles: Option[];
    shops: Option[];
}

interface UserForm {
    [key: string]: string | boolean;
    name: string;
    phone: string;
    email: string;
    password: string;
    password_confirmation: string;
    role: string;
    shop_id: string;
    is_active: boolean;
}

export default function EditUser({ user, roles, shops }: Props) {
    const { data, setData, put, processing, errors } = useForm<UserForm>({
        name: user.name,
        phone: user.phone,
        email: user.email ?? '',
        password: '',
        password_confirmation: '',
        role: user.role ?? '',
        shop_id: user.shop_id ? String(user.shop_id) : '',
        is_active: user.is_active,
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'ড্যাশবোর্ড', href: '/dashboard' },
        { title: 'ব্যবহারকারী', href: '/users' },
        { title: user.name, href: `/users/${user.id}/edit` },
    ];

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('users.update', user.id));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={user.name} />

            <form onSubmit={submit} className="flex max-w-2xl flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">{user.name}</h1>
                    <p className="text-muted-foreground text-sm">তথ্য বদলান</p>
                </div>

                <TextField id="name" label="নাম" value={data.name} onChange={(v) => setData('name', v)} error={errors.name} required />

                <TextField
                    id="phone"
                    label="মোবাইল নম্বর"
                    type="tel"
                    numeric
                    value={data.phone}
                    onChange={(v) => setData('phone', v)}
                    error={errors.phone}
                    required
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
                />

                <TextField id="email" label="ইমেইল" type="email" value={data.email} onChange={(v) => setData('email', v)} error={errors.email} />

                <TextField
                    id="password"
                    label="নতুন পাসওয়ার্ড"
                    type="password"
                    value={data.password}
                    onChange={(v) => setData('password', v)}
                    error={errors.password}
                    hint="বদলাতে না চাইলে খালি রাখুন"
                />

                <TextField
                    id="password_confirmation"
                    label="নতুন পাসওয়ার্ড আবার দিন"
                    type="password"
                    value={data.password_confirmation}
                    onChange={(v) => setData('password_confirmation', v)}
                    error={errors.password_confirmation}
                />

                <div className="flex items-center gap-3">
                    <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked === true)} />
                    <Label htmlFor="is_active">অ্যাকাউন্ট সক্রিয়</Label>
                </div>

                <StickySaveBar processing={processing} cancelHref={route('users.index')} />
            </form>
        </AppLayout>
    );
}
