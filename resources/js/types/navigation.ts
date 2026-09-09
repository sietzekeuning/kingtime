import type { InertiaLinkProps } from '@inertiajs/vue3';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    /**
     * Font Awesome icon name for `<FaIcon>` (registered in
     * `plugins/fontawesome.ts`). Every entry carries one, so the collapsed
     * sidebar rail stays readable.
     */
    icon: string;
    isActive?: boolean;
    badge?: string | number;
};
