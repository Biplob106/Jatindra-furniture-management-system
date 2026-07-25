import { Column, DataTable } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { roleLabels, type Role } from '@/types/enums';
import type { Paginated } from '@/types/pagination';
import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Power } from 'lucide-react';

interface UserRow {
    id: number;
    name: string;
    phone: string;
    email: string | null;
    is_active: boolean;
    role: Role | null;
    shop: { id: number; name: string } | null;
    last_login_at: string | null;
}

interface Props {
    users: Paginated<UserRow>;
    search: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ড্যাশবোর্ড', href: '/dashboard' },
    { title: 'ব্যবহারকারী', href: '/users' },
];

export default function UsersIndex({ users, search }: Props) {
    const columns: Column<UserRow>[] = [
        {
            header: 'নাম',
            cell: (user) => (
                <div className="flex flex-col">
                    <span className="font-medium">{user.name}</span>
                    <span className="text-muted-foreground text-sm sm:hidden">{user.phone}</span>
                </div>
            ),
        },
        {
            header: 'মোবাইল',
            cell: (user) => user.phone,
            hideOnMobile: true,
        },
        {
            header: 'পদ',
            cell: (user) => (user.role ? roleLabels[user.role] : '—'),
        },
        {
            header: 'দোকান',
            cell: (user) => user.shop?.name ?? 'সব দোকান',
            hideOnMobile: true,
        },
        {
            header: 'অবস্থা',
            cell: (user) => <Badge variant={user.is_active ? 'default' : 'secondary'}>{user.is_active ? 'সক্রিয়' : 'বন্ধ'}</Badge>,
        },
        {
            header: '',
            className: 'w-px',
            cell: (user) => (
                <div className="flex gap-1">
                    <Button variant="ghost" size="icon" asChild title="সম্পাদনা">
                        <Link href={route('users.edit', user.id)}>
                            <Pencil className="h-4 w-4" />
                        </Link>
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        title={user.is_active ? 'বন্ধ করুন' : 'চালু করুন'}
                        onClick={() => router.delete(route('users.destroy', user.id), { preserveScroll: true })}
                    >
                        <Power className={user.is_active ? 'h-4 w-4' : 'text-muted-foreground h-4 w-4'} />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="ব্যবহারকারী" />

            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">ব্যবহারকারী</h1>
                    <p className="text-muted-foreground text-sm">কর্মীদের অ্যাকাউন্ট এবং তাদের পদ</p>
                </div>

                <DataTable
                    rows={users}
                    columns={columns}
                    routeName="users.index"
                    search={search}
                    searchPlaceholder="নাম বা মোবাইল নম্বর"
                    emptyMessage="এখনো কোনো ব্যবহারকারী যোগ করা হয়নি"
                    rowKey={(user) => user.id}
                    actions={
                        <Button asChild className="h-11 text-base">
                            <Link href={route('users.create')}>
                                <Plus className="h-4 w-4" />
                                নতুন ব্যবহারকারী
                            </Link>
                        </Button>
                    }
                />
            </div>
        </AppLayout>
    );
}
