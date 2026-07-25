import { toBengaliDigits } from '@/components/data-table';
import { cn } from '@/lib/utils';

interface MoneyProps {
    /** Decimal string from the server. Never parsed for arithmetic here. */
    amount: string;
    className?: string;
    /**
     * Colours a party balance: positive means the shop owes, negative means
     * the worker has taken more than they earned.
     */
    signed?: boolean;
}

/**
 * Renders an amount in taka with Bengali numerals.
 *
 * The value arrives as a decimal string because it is a DECIMAL cast on the
 * server. Number() here is for display and comparison only; no arithmetic on
 * money happens on the client.
 */
export function Money({ amount, className, signed = false }: MoneyProps) {
    const value = Number(amount);
    const negative = value < 0;

    return (
        <span
            className={cn(
                'tabular-nums whitespace-nowrap',
                signed && negative && 'text-destructive',
                signed && value > 0 && 'text-green-700 dark:text-green-400',
                className,
            )}
        >
            ৳ {toBengaliDigits(negative ? amount.slice(1) : amount)}
            {signed && negative && ' (কর্মীর কাছে)'}
        </span>
    );
}
