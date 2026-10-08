const BENGALI_DIGITS = '০১২৩৪৫৬৭৮৯';

export function toBengaliDigits(value: number | string): string {
    return String(value).replace(/\d/g, (d) => BENGALI_DIGITS[Number(d)]);
}

/** 1250000 → 12,50,000: last three digits, then pairs, the Bangladeshi way. */
function group(whole: string): string {
    return whole.length > 3 ? whole.slice(0, -3).replace(/\B(?=(\d{2})+$)/g, ',') + ',' + whole.slice(-3) : whole;
}

/** Splits the server's decimal string without passing it through a float. */
function parts(value: number | string): { sign: string; whole: string; fraction: string } | null {
    const text = String(value).trim();
    if (text === '') return null;

    const [whole, fraction = ''] = text.replace(/^[-+]/, '').split('.');
    return { sign: text.startsWith('-') ? '-' : '', whole, fraction };
}

/**
 * A quantity the way the shop reads it: Bengali digits, grouped, and no
 * trailing zeros, so 1.00 piece reads ১ and 12.500 kg reads ১২.৫.
 */
export function formatNumber(value: number | string): string {
    const p = parts(value);
    if (!p) return '';

    const fraction = p.fraction.replace(/0+$/, '');
    return toBengaliDigits(p.sign + group(p.whole) + (fraction ? '.' + fraction : ''));
}

/**
 * Taka: grouped (৫৭,০০০), whole amounts without ".০০", and paisa always as
 * two digits when there are any (২,৫০০.৫০).
 */
export function formatMoney(value: number | string): string {
    const p = parts(value);
    if (!p) return '';

    const paisa = p.fraction.padEnd(2, '0').slice(0, 2);
    return toBengaliDigits(p.sign + group(p.whole) + (/^0*$/.test(p.fraction) ? '' : '.' + paisa));
}
