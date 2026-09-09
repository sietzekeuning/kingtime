/**
 * Read or set one column filter of a DataTable from a page's own control in
 * its `#buttons` slot, such as the archive switch above the projects list.
 */
export interface TableFilterApi {
    get: (columnId: string) => string | null;
    set: (columnId: string, value: string | null) => void;
}
