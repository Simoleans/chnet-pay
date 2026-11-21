/**
 * Formato venezolano solo para MOSTRAR (5.911,50).
 * Lo que se copia o se envía al backend sigue siendo el valor crudo (5911.50).
 */
export const formatBs = (value: string | number | null | undefined): string => {
    const n = typeof value === 'number' ? value : Number(String(value ?? '').replace(',', '.'));
    if (!Number.isFinite(n)) return String(value ?? '');
    return new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
};

export const formatUsd = (value: string | number | null | undefined): string => {
    const n = Number(value ?? 0);
    return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number.isFinite(n) ? n : 0);
};
