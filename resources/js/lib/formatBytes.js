const units = ['byte', 'kilobyte', 'megabyte', 'gigabyte'];

/**
 * Format a file size for display, such as "12.3 kB".
 *
 * @param {number} bytes
 * @returns {string}
 */
export function formatBytes(bytes) {
    const exponent = Math.min(Math.floor(Math.log(Math.max(bytes, 1)) / Math.log(1000)), units.length - 1);

    return new Intl.NumberFormat(undefined, {
        style: 'unit',
        unit: units[exponent],
        unitDisplay: 'short',
        maximumFractionDigits: exponent === 0 ? 0 : 1,
    }).format(bytes / 1000 ** exponent);
}
