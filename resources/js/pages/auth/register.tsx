import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

interface RegisterForm {
    [key: string]: string;
    name: string;
    phone: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm<RegisterForm>({
        name: '',
        phone: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout title="নতুন অ্যাকাউন্ট" description="অ্যাকাউন্ট খুলতে নিচের তথ্য দিন">
            <Head title="নতুন অ্যাকাউন্ট" />
            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="name">নাম</Label>
                        <Input
                            id="name"
                            type="text"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="name"
                            className="h-12 text-base"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            disabled={processing}
                            placeholder="পুরো নাম"
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="phone">মোবাইল নম্বর</Label>
                        <Input
                            id="phone"
                            type="tel"
                            inputMode="numeric"
                            required
                            tabIndex={2}
                            autoComplete="username"
                            className="h-12 text-base"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                            disabled={processing}
                            placeholder="01XXXXXXXXX"
                        />
                        <InputError message={errors.phone} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">ইমেইল (ঐচ্ছিক)</Label>
                        <Input
                            id="email"
                            type="email"
                            tabIndex={3}
                            autoComplete="email"
                            className="h-12 text-base"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            disabled={processing}
                            placeholder="email@example.com"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">পাসওয়ার্ড</Label>
                        <Input
                            id="password"
                            type="password"
                            required
                            tabIndex={4}
                            autoComplete="new-password"
                            className="h-12 text-base"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            disabled={processing}
                            placeholder="পাসওয়ার্ড"
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">পাসওয়ার্ড আবার দিন</Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            required
                            tabIndex={5}
                            autoComplete="new-password"
                            className="h-12 text-base"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            disabled={processing}
                            placeholder="পাসওয়ার্ড আবার দিন"
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>

                    <Button type="submit" className="mt-2 h-12 w-full text-base" tabIndex={6} disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        অ্যাকাউন্ট খুলুন
                    </Button>
                </div>

                <div className="text-muted-foreground text-center text-sm">
                    আগে থেকেই অ্যাকাউন্ট আছে?{' '}
                    <TextLink href={route('login')} tabIndex={7}>
                        লগ ইন
                    </TextLink>
                </div>
            </form>
        </AuthLayout>
    );
}
