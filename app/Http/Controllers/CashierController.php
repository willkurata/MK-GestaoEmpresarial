<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CashierController extends Controller
{
    private const MAX_AMOUNT_CENTS = 999999999999;

    public function index(Request $request): Response
    {
        /** @var Company $company */
        $company = $request->user('company');
        /** @var CompanyUser $companyUser */
        $companyUser = $request->user('company_user');
        $activeSession = $company->cashSessions()
            ->where('status', 'aberto')
            ->with('user:id,name')
            ->with('cashRegister:id,name')
            ->latest('opened_at')
            ->first();
        $session = $activeSession && (int) $activeSession->company_user_id === (int) $companyUser->id
            ? $activeSession
            : null;

        return Inertia::render('Cashier/Index', [
            'products' => $company->products()
                ->orderBy('name')
                ->get()
                ->map(fn (Product $product): array => [
                    ...$product->toArray(),
                    'active' => $product->status === 'ativo',
                ]),
            'currentSession' => $session,
            'cashHeldBy' => $activeSession && ! $session
                ? ($activeSession->user?->name ?? 'outro usuário')
                : null,
            'recentSales' => $company->sales()
                ->with(['items', 'payment.refund', 'user:id,name'])
                ->latest('occurred_at')
                ->limit(10)
                ->get()
                ->map(fn (Sale $sale): array => [
                    ...$sale->toArray(),
                    'payment_method' => $sale->payment?->method,
                    'refunded_at' => $sale->payment?->refund?->refunded_at?->toISOString(),
                    'operator_name' => $sale->user?->name,
                ]),
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        /** @var Company $company */
        $company = $request->user('company');
        /** @var CompanyUser $companyUser */
        $companyUser = $request->user('company_user');
        if (! $request->exists('initial_cash') && $request->exists('opening_amount')) {
            $request->merge(['initial_cash' => $request->input('opening_amount')]);
        }
        $validated = $request->validate([
            'initial_cash' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ]);

        DB::transaction(function () use ($company, $companyUser, $validated): void {
            $company->newQuery()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $company->companyUsers()->whereKey($companyUser->id)->lockForUpdate()->firstOrFail();
            abort_if(
                $company->cashSessions()->where('status', 'aberto')->exists(),
                422,
                'Já existe um caixa aberto para esta empresa.',
            );

            $register = $company->cashRegisters()->lockForUpdate()->first();
            if (! $register) {
                $register = $company->cashRegisters()->create([
                    'name' => 'Caixa Principal',
                    'status' => 'ativo',
                ]);
            }

            abort_if($register->status !== 'ativo', 422, 'O caixa permanente desta empresa está inativo.');
            abort_if($register->company_user_id && (int) $register->company_user_id !== (int) $companyUser->id, 403, 'Este caixa está atribuído a outro usuário.');

            $register->sessions()->create([
                'company_id' => $company->id,
                'company_user_id' => $companyUser->id,
                'opened_at' => now(),
                'opening_amount' => $validated['initial_cash'],
                'status' => 'aberto',
            ]);
        });

        return back()->with('success', 'Caixa aberto.');
    }

    public function storeSale(Request $request): RedirectResponse
    {
        /** @var Company $company */
        $company = $request->user('company');
        /** @var CompanyUser $companyUser */
        $companyUser = $request->user('company_user');
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'discount_type' => ['required', Rule::in(['none', 'fixed', 'percentage'])],
            'discount_value' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'payment_method' => ['required', Rule::in(['Pix', 'Dinheiro'])],
            'received_amount' => ['required_if:payment_method,Dinheiro', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ]);

        DB::transaction(function () use ($company, $companyUser, $validated): void {
            $session = $company->cashSessions()
                ->where('company_user_id', $companyUser->id)
                ->where('status', 'aberto')
                ->lockForUpdate()
                ->latest('opened_at')
                ->first();

            abort_if(! $session, 422, 'Abra um caixa antes de registrar uma venda.');

            $productIds = collect($validated['items'])->pluck('product_id')->map(fn ($id): int => (int) $id)->sort()->values();
            $products = $company->products()
                ->whereIn('id', $productIds)
                ->where('status', 'ativo')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            abort_if($products->count() !== $productIds->count(), 422, 'Um ou mais produtos não estão disponíveis.');

            $subtotalCents = 0;
            $lineItems = [];
            foreach ($validated['items'] as $item) {
                /** @var Product $product */
                $product = $products->get((int) $item['product_id']);
                $quantity = (int) $item['quantity'];
                $unitPriceCents = $this->toCents((string) $product->sale_price);
                $lineTotalCents = $unitPriceCents * $quantity;
                abort_if($lineTotalCents > self::MAX_AMOUNT_CENTS - $subtotalCents, 422, 'O valor total da venda excede o limite permitido.');
                $subtotalCents += $lineTotalCents;
                $lineItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $product->sale_price,
                    'unit_cost' => $product->cost,
                    'line_total_cents' => $lineTotalCents,
                ];
            }

            $requestedDiscount = $this->toCents((string) $validated['discount_value']);
            if ($validated['discount_type'] === 'percentage') {
                abort_if($requestedDiscount > 10000, 422, 'O desconto percentual não pode exceder 100%.');
            }
            $discountCents = match ($validated['discount_type']) {
                'none' => 0,
                'fixed' => min($requestedDiscount, $subtotalCents),
                'percentage' => min($subtotalCents, intdiv(($subtotalCents * $requestedDiscount) + 5000, 10000)),
            };

            $totalCents = $subtotalCents - $discountCents;
            $receivedCents = $validated['payment_method'] === 'Dinheiro'
                ? $this->toCents((string) $validated['received_amount'])
                : $totalCents;
            abort_if($receivedCents < $totalCents, 422, 'O valor recebido é menor que o total da venda.');

            $sale = $company->sales()->create([
                'company_user_id' => $companyUser->id,
                'cash_register_id' => $session->cash_register_id,
                'cash_session_id' => $session->id,
                'occurred_at' => now(),
                'status' => 'finalizada',
                'subtotal' => $this->fromCents($subtotalCents),
                'discount' => $this->fromCents($discountCents),
                'total' => $this->fromCents($totalCents),
            ]);

            foreach ($lineItems as $lineItem) {
                $sale->items()->create([
                    'company_id' => $company->id,
                    'product_id' => $lineItem['product']->id,
                    'product_name' => $lineItem['product']->name,
                    'quantity' => $lineItem['quantity'],
                    'unit_price' => $lineItem['unit_price'],
                    'unit_cost' => $lineItem['unit_cost'],
                ]);
            }

            $sale->payment()->create([
                'company_id' => $company->id,
                'method' => $validated['payment_method'],
                'amount' => $this->fromCents($totalCents),
                'status' => 'confirmado',
                'paid_at' => now(),
                'amount_received' => $this->fromCents($receivedCents),
                'change_amount' => $this->fromCents($receivedCents - $totalCents),
            ]);

            if ($totalCents > 0) {
                $this->recordMovement($company, $companyUser, $session, 'venda', $validated['payment_method'], $totalCents, 'Venda #'.$sale->id);
            }
        });

        return back()->with('success', 'Venda registrada.');
    }

    public function refund(Request $request, int $sale): RedirectResponse
    {
        /** @var Company $company */
        $company = $request->user('company');
        /** @var CompanyUser $companyUser */
        $companyUser = $request->user('company_user');
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($company, $companyUser, $sale, $validated): void {
            $sale = $company->sales()->whereKey($sale)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()
                ->where('company_id', $company->id)
                ->where('sale_id', $sale->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_if($sale->status === 'estornada' || $payment->refund()->exists(), 422, 'Esta venda já foi estornada.');

            $session = $company->cashSessions()
                ->where('company_user_id', $companyUser->id)
                ->where('status', 'aberto')
                ->lockForUpdate()
                ->latest('opened_at')
                ->first();
            abort_if(! $session, 422, 'Abra seu caixa antes de registrar um estorno.');
            $payment->refund()->create([
                'company_id' => $company->id,
                'company_user_id' => $companyUser->id,
                'amount' => $payment->amount,
                'method' => $payment->method,
                'reason' => trim($validated['reason']),
                'refunded_at' => now(),
            ]);

            $sale->update(['status' => 'estornada']);
            $payment->update(['status' => 'estornado']);
            $this->recordMovement(
                $company,
                $companyUser,
                $session,
                'estorno',
                $payment->method,
                -$this->toCents((string) $payment->amount),
                'Estorno da venda #'.$sale->id,
            );
        });

        return back()->with('success', 'Venda estornada.');
    }

    public function close(Request $request): RedirectResponse
    {
        /** @var Company $company */
        $company = $request->user('company');
        /** @var CompanyUser $companyUser */
        $companyUser = $request->user('company_user');
        if (! $request->exists('counted_cash') && $request->exists('closing_amount')) {
            $request->merge(['counted_cash' => $request->input('closing_amount')]);
        }
        $validated = $request->validate([
            'counted_cash' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ]);

        DB::transaction(function () use ($company, $companyUser, $validated): void {
            $session = $company->cashSessions()
                ->where('company_user_id', $companyUser->id)
                ->where('status', 'aberto')
                ->lockForUpdate()
                ->latest('opened_at')
                ->firstOrFail();
            $openingCents = $this->toCents((string) $session->opening_amount);
            $movementAmount = $company->cashMovements()
                ->where('cash_session_id', $session->id)
                ->where('method', 'Dinheiro')
                ->sum('amount');
            $expectedCents = $openingCents + $this->toCents((string) $movementAmount);
            $countedCents = $this->toCents((string) $validated['counted_cash']);

            $session->update([
                'status' => 'fechado',
                'closed_at' => now(),
                'expected_amount' => $this->fromCents($expectedCents),
                'counted_amount' => $this->fromCents($countedCents),
                'difference_amount' => $this->fromCents($countedCents - $expectedCents),
            ]);
        });

        return back()->with('success', 'Caixa fechado.');
    }

    private function recordMovement(
        Company $company,
        CompanyUser $companyUser,
        CashSession $session,
        string $type,
        string $method,
        int $amountCents,
        string $description,
    ): CashMovement {
        return $company->cashMovements()->create([
            'cash_session_id' => $session->id,
            'company_user_id' => $companyUser->id,
            'type' => $type,
            'method' => $method,
            'amount' => $this->fromCents($amountCents),
            'description' => $description,
            'occurred_at' => Carbon::now(),
        ]);
    }

    private function toCents(string $amount): int
    {
        $isNegative = str_starts_with($amount, '-');
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        $whole = ltrim($whole, '-');
        $cents = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        return $isNegative ? -$cents : $cents;
    }

    private function fromCents(int $amount): string
    {
        return sprintf('%s%d.%02d', $amount < 0 ? '-' : '', intdiv(abs($amount), 100), abs($amount) % 100);
    }
}
