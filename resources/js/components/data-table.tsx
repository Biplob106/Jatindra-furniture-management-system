import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { Paginated } from '@/types/pagination';
import { Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { ReactNode, useEffect, useRef, useState } from 'react';
import { toBengaliDigits } from '@/lib/number-format';

export interface Column<T> {
    /** Bengali header text. */
    header: string;
    /** Cell contents for one row. */
    cell: (row: T) => ReactNode;
    /** Tailwind classes applied to both the header and every cell. */
    className?: string;
    /** Hide below sm. Use for anything a phone does not need. */
    hideOnMobile?: boolean;
}

interface DataTableProps<T> {
    rows: Paginated<T>;
    columns: Column<T>[];
    /** Route the search box and pagination navigate to. */
    routeName: string;
    /** Other query params the search keeps, such as the page's filters. */
    params?: Record<string, string | number>;
    /** Current search term, echoed back by the server. */
    search?: string;
    searchPlaceholder?: string;
    /** Shown in place of the table when there is nothing at all. */
    emptyMessage?: string;
    /** Rendered above the table, typically the add button. */
    actions?: ReactNode;
    rowKey: (row: T) => string | number;
}

export function DataTable<T>({
    rows,
    columns,
    routeName,
    params = {},
    search = '',
    searchPlaceholder = 'খুঁজুন',
    emptyMessage = 'কোনো তথ্য নেই',
    actions,
    rowKey,
}: DataTableProps<T>) {
    const [term, setTerm] = useState(search);
    const firstRender = useRef(true);
    // Read at search time, so a new filters object each render does not re-fire the search.
    const paramsRef = useRef(params);
    paramsRef.current = params;

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        const timer = setTimeout(() => {
            router.get(route(routeName), { ...paramsRef.current, ...(term ? { search: term } : {}) }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);

        return () => clearTimeout(timer);
    }, [term, routeName]);

    const isEmpty = rows.data.length === 0;

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="relative sm:max-w-xs">
                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2" />
                    <Input
                        type="search"
                        value={term}
                        onChange={(e) => setTerm(e.target.value)}
                        placeholder={searchPlaceholder}
                        className="h-11 pr-9 text-base"
                    />
                </div>
                {actions}
            </div>

            {isEmpty ? (
                <div className="text-muted-foreground rounded-lg border border-dashed p-10 text-center">
                    {search ? `"${search}" এর জন্য কিছু পাওয়া যায়নি` : emptyMessage}
                </div>
            ) : (
                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {columns.map((column, index) => (
                                    <TableHead key={index} className={cx(column.className, column.hideOnMobile && 'hidden sm:table-cell')}>
                                        {column.header}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.data.map((row) => (
                                <TableRow key={rowKey(row)}>
                                    {columns.map((column, index) => (
                                        <TableCell key={index} className={cx(column.className, column.hideOnMobile && 'hidden sm:table-cell')}>
                                            {column.cell(row)}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            {rows.last_page > 1 && (
                <div className="flex items-center justify-between gap-3">
                    <span className="text-muted-foreground text-sm">
                        {toBengaliDigits(rows.from ?? 0)}–{toBengaliDigits(rows.to ?? 0)} / {toBengaliDigits(rows.total)}
                    </span>
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" asChild disabled={rows.current_page === 1}>
                            <Link href={pageUrl(rows, rows.current_page - 1)} preserveScroll>
                                আগের
                            </Link>
                        </Button>
                        <Button variant="outline" size="sm" asChild disabled={rows.current_page === rows.last_page}>
                            <Link href={pageUrl(rows, rows.current_page + 1)} preserveScroll>
                                পরের
                            </Link>
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}

function pageUrl<T>(rows: Paginated<T>, page: number): string {
    const clamped = Math.min(Math.max(page, 1), rows.last_page);
    const match = rows.links.find((link) => link.label === String(clamped));

    return match?.url ?? '#';
}

/**
 * Bengali numerals for anything the shop floor reads as a count.
 */
// Kept here for the many pages that import it from this module.
export { toBengaliDigits };

function cx(...classes: (string | false | undefined)[]): string {
    return classes.filter(Boolean).join(' ');
}
