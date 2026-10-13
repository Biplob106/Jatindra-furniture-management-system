// Components
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <AuthLayout title="ইমেইল যাচাই" description="আপনার ইমেইলে পাঠানো লিংকে চাপ দিয়ে ইমেইল যাচাই করুন।">
            <Head title="ইমেইল যাচাই" />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    আপনার ইমেইলে নতুন যাচাই লিংক পাঠানো হয়েছে।
                </div>
            )}

            <form onSubmit={submit} className="space-y-6 text-center">
                <Button disabled={processing} variant="secondary">
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    আবার পাঠান
                </Button>

                <TextLink href={route('logout')} method="post" className="mx-auto block text-sm">
                    লগ আউট
                </TextLink>
            </form>
        </AuthLayout>
    );
}
