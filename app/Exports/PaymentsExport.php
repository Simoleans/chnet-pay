<?php

namespace App\Exports;

use App\Helpers\BncHelper;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PaymentsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable;

    protected ?string $search;
    protected ?string $dateFrom;
    protected ?string $dateTo;
    protected float $bcvRate;

    public function __construct(?string $search, ?string $dateFrom, ?string $dateTo)
    {
        $this->search = $search ?: null;
        $this->dateFrom = $dateFrom ?: null;
        $this->dateTo = $dateTo ?: null;
        $this->bcvRate = (float) (BncHelper::getBcvRatesCached()['Rate'] ?? 1);
    }

    public function query()
    {
        /** @var Builder $query */
        $query = Payment::query()->with(['user', 'invoice'])->orderByDesc('id');

        if ($this->search) {
            $term = $this->search;
            $query->where(function ($q) use ($term) {
                $q->where('reference', 'like', "%{$term}%")
                    ->orWhereHas('user', function ($userQuery) use ($term) {
                        $userQuery->where('code', 'like', "%{$term}%");
                    });
            });
        }

        if ($this->dateFrom) {
            $query->whereDate('payment_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('payment_date', '<=', $this->dateTo);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Referencia',
            'Cliente',
            'Código',
            'Monto USD',
            'Monto Bs',
            'Fecha Pago',
            'Banco',
            'Cédula',
            'Teléfono',
            'Periodo Factura',
            'Verificación',
            'Registrado',
        ];
    }

    public function map($payment): array
    {
        /** @var \App\Models\Payment $payment */
        $amountUsd = (float) $payment->amount;
        $amountBs = $amountUsd * $this->bcvRate;

        return [
            $payment->id,
            $payment->reference,
            optional($payment->user)->name,
            optional($payment->user)->code,
            number_format($amountUsd, 2, '.', ''),
            number_format($amountBs, 2, '.', ''),
            optional($payment->payment_date)->format('Y-m-d'),
            $payment->bank,
            $payment->id_number,
            $payment->phone,
            ($payment->invoice && $payment->invoice->period) ? $payment->invoice->period->format('Y-m') : 'Sin factura',
            $payment->verify_payments ? 'Verificado' : 'Sin verificar',
            optional($payment->created_at)->format('Y-m-d H:i:s'),
        ];
    }
}


