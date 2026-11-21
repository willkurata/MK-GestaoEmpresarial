import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import type { CompanyPermissions } from '@/types';

/**
 * Role permissions shared by the backend. The server always enforces them;
 * these flags only hide actions the signed-in user cannot perform.
 */
export function usePermissions(): ComputedRef<CompanyPermissions> {
    const page = usePage();

    return computed(() => {
        const permissions = page.props.auth?.permissions ?? {};

        return {
            reverseSales: Boolean(permissions.reverseSales),
            manageProducts: Boolean(permissions.manageProducts),
            manageExpenses: Boolean(permissions.manageExpenses),
            viewReports: Boolean(permissions.viewReports),
            manageUsers: Boolean(permissions.manageUsers),
        };
    });
}
