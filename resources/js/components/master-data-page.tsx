import { Column, DataTable } from '@/components/data-table';
import { FlashMessages } from '@/components/flash-messages';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import type { Paginated } from '@/types/pagination';
import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { ReactNode } from 'react';

interface MasterDataPageProps<T> {
    title: string;
    subtitle?: string;
    /** Base route name, e.g. "shops". Index, create, edit and destroy derive from it. */
    resource: string;
    rows: Paginated<T>;
    columns: Column<T>[];
    search: string;
    searchPlaceholder?: string;
    emptyMessage?: string;
    addLabel: string;
    canManage: boolean;
    rowKey: (row: T) => string | number;
    /** Confirmation text shown before deleting. */
    deleteConfirm: (row: T) => string;
}

/**
 * The shape every master data list screen shares: heading, flash messages,
 * searchable table, and edit and delete buttons on each row.
 *
 * Delete uses window.confirm rather than a dialog because a blocked delete is
 * answered by the server with a Bengali message explaining what is in the way.
 */
export function MasterDataPage<T>({
    title,
    subtitle,
    resource,
    rows,
    columns,
    search,
    searchPlaceholder,
    emptyMessage,
    addLabel,
    canManage,
    rowKey,
    deleteConfirm,
}: MasterDataPageProps<T>) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'ড্যাশবোর্ড', href: '/dashboard' },
        { title, href: `/${resource}` },
    ];

    const allColumns: Column<T>[] = canManage
        ? [
              ...columns,
              {
                  header: '',
                  className: 'w-px',
                  cell: (row) => (
                      <div className="flex gap-1">
                          <Button variant="ghost" size="icon" asChild title="সম্পাদনা">
                              <Link href={route(`${resource}.edit`, rowKey(row))}>
                                  <Pencil className="h-4 w-4" />
                              </Link>
                          </Button>
                          <Button
                              variant="ghost"
                              size="icon"
                              title="মুছে ফেলুন"
                              onClick={() => {
                                  if (window.confirm(deleteConfirm(row))) {
                                      router.delete(route(`${resource}.destroy`, rowKey(row)), { preserveScroll: true });
                                  }
                              }}
                          >
                              <Trash2 className="text-destructive h-4 w-4" />
                          </Button>
                      </div>
                  ),
              },
          ]
        : columns;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={title} />

            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    {subtitle && <p className="text-muted-foreground text-sm">{subtitle}</p>}
                </div>

                <FlashMessages />

                <DataTable
                    rows={rows}
                    columns={allColumns}
                    routeName={`${resource}.index`}
                    search={search}
                    searchPlaceholder={searchPlaceholder}
                    emptyMessage={emptyMessage}
                    rowKey={rowKey}
                    actions={
                        canManage && (
                            <Button asChild className="h-11 text-base">
                                <Link href={route(`${resource}.create`)}>
                                    <Plus className="h-4 w-4" />
                                    {addLabel}
                                </Link>
                            </Button>
                        )
                    }
                />
            </div>
        </AppLayout>
    );
}

interface MasterDataFormPageProps {
    title: string;
    subtitle?: string;
    resource: string;
    resourceTitle: string;
    children: ReactNode;
    onSubmit: (e: React.FormEvent) => void;
}

/**
 * The shape every master data create and edit screen shares.
 */
export function MasterDataFormPage({ title, subtitle, resource, resourceTitle, children, onSubmit }: MasterDataFormPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'ড্যাশবোর্ড', href: '/dashboard' },
        { title: resourceTitle, href: `/${resource}` },
        { title, href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={title} />

            <form onSubmit={onSubmit} className="flex max-w-2xl flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    {subtitle && <p className="text-muted-foreground text-sm">{subtitle}</p>}
                </div>

                {children}
            </form>
        </AppLayout>
    );
}
