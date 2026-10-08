import { Field } from '@/components/form-field';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { useState } from 'react';

const OTHER = 'অন্যান্য';

interface Props {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: string[];
    error?: string;
}

/**
 * Tap a word instead of typing it. "অন্যান্য" opens a box for anything not on
 * the list, and a stored value that is not on the list opens there too, so
 * older orders keep what was written.
 */
export function ChoiceChips({ id, label, value, onChange, options, error }: Props) {
    const [otherOpen, setOtherOpen] = useState(value !== '' && !options.includes(value));

    const chip = (text: string, active: boolean, onClick: () => void) => (
        <button
            key={text}
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={cn(
                'h-11 rounded-full border px-4 text-sm transition-colors',
                active ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-muted',
            )}
        >
            {text}
        </button>
    );

    return (
        <Field id={id} label={label} error={error}>
            <div className="flex flex-wrap gap-2">
                {options.map((option) =>
                    chip(option, !otherOpen && value === option, () => {
                        setOtherOpen(false);
                        onChange(value === option ? '' : option);
                    }),
                )}
                {chip(OTHER, otherOpen, () => {
                    setOtherOpen(!otherOpen);
                    onChange('');
                })}
            </div>
            {otherOpen && (
                <Input id={id} value={value} onChange={(e) => onChange(e.target.value)} placeholder="লিখুন" className="h-12 text-base" autoFocus />
            )}
        </Field>
    );
}
