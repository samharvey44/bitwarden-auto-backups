const formatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });

/**
 * Format a UTC timestamp from the server in the viewer's own timezone.
 *
 * @param {string|null} timestamp An ISO 8601 timestamp.
 * @returns {string|null}
 */
export function formatDateTime(timestamp) {
    return timestamp ? formatter.format(new Date(timestamp)) : null;
}
