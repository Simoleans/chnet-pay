/**
 * Guarda en localStorage el estado VISUAL de un formulario de pago
 * (paso actual + campos escritos) para que el usuario no lo pierda si
 * sale a la app del banco y el navegador recarga la página.
 *
 * - No toca payloads: solo vuelve a llenar los mismos refs del formulario.
 * - Expira sola (TTL) y se borra con clear() al pagar o al cancelar.
 * - NUNCA guardes aquí tokens/claves bancarias.
 */
import { watch, type WatchSource } from 'vue';

interface Options<T> {
    key: () => string | null; // null = no persistir (ej. sin usuario)
    snapshot: () => T; // qué guardar
    sources: WatchSource[]; // qué observar
    ttlMinutes?: number;
    enabled?: () => boolean; // ej. solo mientras el modal está abierto
}

export function usePersistedForm<T extends object>({ key, snapshot, sources, ttlMinutes = 30, enabled = () => true }: Options<T>) {
    let paused = false;
    let timer: ReturnType<typeof setTimeout> | null = null;

    const safe = <R>(fn: () => R, fallback: R): R => {
        try {
            return fn();
        } catch {
            return fallback; // modo incógnito / storage lleno: seguimos sin persistir
        }
    };

    const save = () => {
        const k = key();
        if (!k || paused || !enabled()) return;
        safe(() => localStorage.setItem(k, JSON.stringify({ t: Date.now(), v: snapshot() })), undefined);
    };

    const load = (): Partial<T> | null => {
        const k = key();
        if (!k) return null;
        return safe(() => {
            const raw = localStorage.getItem(k);
            if (!raw) return null;
            const { t, v } = JSON.parse(raw);
            if (Date.now() - t > ttlMinutes * 60_000) {
                localStorage.removeItem(k);
                return null;
            }
            return v as Partial<T>;
        }, null);
    };

    const clear = () => {
        const k = key();
        if (k) safe(() => localStorage.removeItem(k), undefined);
    };

    // Guardado con pequeño retraso para no escribir en cada tecla
    watch(sources, () => {
        if (timer) clearTimeout(timer);
        timer = setTimeout(save, 300);
    });

    /** Ejecuta fn sin guardar (útil mientras se restaura o se resetea) */
    const withoutSaving = (fn: () => void) => {
        paused = true;
        try {
            fn();
        } finally {
            setTimeout(() => (paused = false), 350);
        }
    };

    return { load, clear, save, withoutSaving };
}
