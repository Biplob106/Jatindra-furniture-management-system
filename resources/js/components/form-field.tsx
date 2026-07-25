import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ReactNode } from 'react';

/**
 * Shared form primitives. Every field is 48px tall so it stays tappable on a
 * phone held one-handed on the shop floor.
 *
 * Validation lives in the FormRequest on the server. These render the errors
 * Laravel sends back; they never check anything themselves.
 */

interface FieldProps {
    id: string;
    label: string;
    error?: string;
    required?: boolean;
    hint?: string;
    children: ReactNode;
}

export function Field({ id, label, error, required, hint, children }: FieldProps) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>
                {label}
                {required && <span className="text-destructive mr-1">*</span>}
            </Label>
            {children}
            {hint && <p className="text-muted-foreground text-sm">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

interface TextFieldProps {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    required?: boolean;
    hint?: string;
    placeholder?: string;
    type?: 'text' | 'tel' | 'email' | 'password' | 'number' | 'date';
    numeric?: boolean;
    autoFocus?: boolean;
    disabled?: boolean;
}

export function TextField({ id, label, value, onChange, error, required, hint, placeholder, type = 'text', numeric, ...rest }: TextFieldProps) {
    return (
        <Field id={id} label={label} error={error} required={required} hint={hint}>
            <Input
                id={id}
                type={type}
                inputMode={numeric ? 'numeric' : undefined}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                className="h-12 text-base"
                {...rest}
            />
        </Field>
    );
}

export interface Option {
    value: string | number;
    label: string;
}

interface SelectFieldProps {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: Option[];
    error?: string;
    required?: boolean;
    hint?: string;
    placeholder?: string;
    /** Adds an explicit "none" choice mapped to an empty value. */
    emptyLabel?: string;
}

export function SelectField({ id, label, value, onChange, options, error, required, hint, placeholder = 'বেছে নিন', emptyLabel }: SelectFieldProps) {
    return (
        <Field id={id} label={label} error={error} required={required} hint={hint}>
            <Select value={value === '' ? '__none__' : value} onValueChange={(next) => onChange(next === '__none__' ? '' : next)}>
                <SelectTrigger id={id} className="h-12 text-base">
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    {emptyLabel && <SelectItem value="__none__">{emptyLabel}</SelectItem>}
                    {options.map((option) => (
                        <SelectItem key={option.value} value={String(option.value)}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </Field>
    );
}
