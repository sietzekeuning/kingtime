/**
 * Remembers the URL (path + query) of the list page the user last looked at,
 * so that after opening a row and editing it on the detail page we can send the
 * user back to the *same* view: same filters, same sort, same page.
 *
 * Keyed by pathname so several lists can be remembered independently. The
 * value is a relative `path?query` string (never absolute) so it is safe to
 * hand straight to Inertia visits and to the `X-Inertia-List-Return` header
 * that {@link PreserveListReturnRedirect} reads on the server.
 */
const store = new Map<string, string>();

function pathOf(url: string): string {
    return new URL(url, window.location.origin).pathname;
}

/**
 * Record a list URL. Call with an explicit URL when the caller already built
 * the target (filter/sort/pagination handlers); call with no argument to
 * snapshot the current browser location (row click, initial mount).
 */
export function rememberListUrl(url?: string): void {
    const full = url
        ? new URL(url, window.location.origin)
        : new URL(window.location.href);

    store.set(full.pathname, `${full.pathname}${full.search}`);
}

/**
 * A bare index URL, as either a plain string or a Wayfinder route helper
 * result (`{ url, method }`).
 */
type IndexUrl = string | { url: string };

function toStr(url: IndexUrl): string {
    return typeof url === 'string' ? url : url.url;
}

/**
 * Resolve where "back to the list" should go for a given index URL. Returns the
 * remembered filtered/paged URL when the user came from that list, otherwise
 * the bare index URL untouched. Accepts a Wayfinder route result directly so it
 * can wrap a back-link `:href` in a template (`$listReturn(routes.x.index(slug))`).
 */
export function listReturnUrl(indexUrl: IndexUrl): string {
    const bare = toStr(indexUrl);

    return store.get(pathOf(bare)) ?? bare;
}
