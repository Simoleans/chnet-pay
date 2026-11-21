/**
 * Fecha LOCAL en formato YYYY-MM-DD (mismo formato que ya espera el backend).
 *
 * Ojo: `new Date().toISOString()` devuelve la fecha en UTC. En Venezuela (UTC-4)
 * desde las 8:00 pm eso da el día de MAÑANA, y el pago se reportaba con fecha errada.
 */
export const localIsoDate = (offsetDays = 0): string => {
    const d = new Date();
    d.setDate(d.getDate() + offsetDays);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
};

/** "2026-11-19" → "19/11/2026" (solo para mostrar) */
export const displayDate = (iso: string): string => {
    const [y, m, d] = (iso || '').split('-');
    return y && m && d ? `${d}/${m}/${y}` : iso;
};
