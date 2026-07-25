import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Whether the signed-in user holds a permission.
 *
 * This decides what to render, nothing more. Every guarded route enforces the
 * same permission server-side; hiding a nav item is not access control.
 */
export function usePermission() {
    const { auth } = usePage<SharedData>().props;

    const can = (permission: string): boolean => auth?.permissions?.includes(permission) ?? false;

    const hasRole = (role: string): boolean => auth?.roles?.includes(role) ?? false;

    return { can, hasRole };
}
