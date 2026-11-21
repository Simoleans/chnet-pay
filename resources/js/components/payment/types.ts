export type PaymentAlert = {
    type: 'error' | 'warning' | 'success' | 'info';
    message: string;
    title?: string | null;
};

export type PaymentSuccess = {
    title: string;
    message: string;
    tone?: 'success' | 'warning';
    amountBs?: string;
    reference?: string;
    date?: string;
};
