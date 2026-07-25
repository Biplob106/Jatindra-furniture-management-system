import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

interface LoginForm {
    [key: string]: string | boolean;
    phone: string;
    password: string;
    remember: boolean;
}

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

export default function Login({ status, canResetPassword }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        phone: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout title="লগ ইন করুন" description="মোবাইল নম্বর ও পাসওয়ার্ড দিন">
            <Head title="লগ ইন" />

            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="phone">মোবাইল নম্বর</Label>
                        <Input
                            id="phone"
                            type="tel"
                            inputMode="numeric"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="username"
                            className="h-12 text-base"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                            placeholder="01XXXXXXXXX"
                        />
                        <InputError message={errors.phone} />
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-center">
                            <Label htmlFor="password">পাসওয়ার্ড</Label>
                            {canResetPassword && (
                                <TextLink href={route('password.request')} className="ml-auto text-sm" tabIndex={5}>
                                    পাসওয়ার্ড ভুলে গেছেন?
                                </TextLink>
                            )}
                        </div>
                        <Input
                            id="password"
                            type="password"
                            required
                            tabIndex={2}
                            autoComplete="current-password"
                            className="h-12 text-base"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder="পাসওয়ার্ড"
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="flex items-center space-x-3">
                        <Checkbox id="remember" name="remember" tabIndex={3} />
                        <Label htmlFor="remember">মনে রাখুন</Label>
                    </div>

                    <Button type="submit" className="mt-4 h-12 w-full text-base" tabIndex={4} disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        লগ ইন
                    </Button>
                </div>

                <div className="text-muted-foreground text-center text-sm">
                    অ্যাকাউন্ট নেই?{' '}
                    <TextLink href={route('register')} tabIndex={5}>
                        নতুন অ্যাকাউন্ট
                    </TextLink>
                </div>
            </form>

            {status && <div className="mb-4 text-center text-sm font-medium text-green-600">{status}</div>}
        </AuthLayout>
    );
}
