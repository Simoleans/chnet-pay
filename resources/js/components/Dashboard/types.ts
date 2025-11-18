export interface Payment {
    id: number
    reference: string
    amount: number
    amount_bs: number
    payment_date: string
    bank: string
    phone: string
    id_number: string
    user_name: string
    user_code: string
    invoice_period: string
    created_at: string
    image_path?: string
    verify_payments: boolean
}

export interface Contract {
    state: string
    start_date: string | null
    latitude?: string | null
    longitude?: string | null
}

export interface Plan {
    name: string
    price: number
}

export interface ContractStats {
    enabled?: number
    alerted?: number
    degraded?: number
    disabled?: number
}

