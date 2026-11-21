/**
 * Arma el objeto que se envía al modal de pago (selectedInvoice).
 * COPIA EXACTA de la lógica de WisproInvoicesTable.vue (openPaymentModal /
 * openPayAllPending) para que el payload final no cambie.
 * Si cambias algo aquí, cámbialo también allá (lo usa la vista de admin).
 */

export const getPendingAmount = (invoice: any): number => parseFloat(String(invoice.balance ?? invoice.amount ?? 0));

export const isPending = (invoice: any): boolean => invoice.state === 'pending';

/** Devuelve un firm id solo si todas las facturas pertenecen a la misma empresa. */
export const getUniqueInvoicingFirmId = (invoices: any[]): string | null => {
    const firmIds = Array.from(new Set(invoices.map((invoice: any) => invoice.invoicing_firm_id).filter(Boolean).map(String)));
    return firmIds.length === 1 ? firmIds[0] : null;
};

/** Pagar UNA factura */
export const buildSingleInvoicePayment = (invoice: any) => {
    const amount = getPendingAmount(invoice);
    return {
        invoice_ids: [invoice.id],
        amount,
        invoicing_firm_id: invoice.invoicing_firm_id ?? null,
        client_id: invoice.client_id ?? null,
        invoices: [
            {
                id: invoice.id,
                invoice_number: invoice.invoice_number,
                amount,
                invoicing_firm_id: invoice.invoicing_firm_id ?? null,
            },
        ],
    };
};

/** Pagar TODAS las pendientes (solo si comparten empresa / pago móvil) */
export const buildPayAllPayment = (pendingInvoices: any[]) => {
    const total = pendingInvoices.reduce((sum: number, inv: any) => sum + getPendingAmount(inv), 0);
    return {
        invoice_ids: pendingInvoices.map((i: any) => i.id),
        amount: total,
        invoicing_firm_id: getUniqueInvoicingFirmId(pendingInvoices),
        invoices: pendingInvoices.map((i: any) => ({
            id: i.id,
            invoice_number: i.invoice_number,
            amount: getPendingAmount(i),
            invoicing_firm_id: i.invoicing_firm_id ?? null,
        })),
    };
};
