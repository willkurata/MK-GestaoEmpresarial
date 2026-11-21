<?php

namespace App\Actions\Companies;

use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Monta o resumo gerencial de uma empresa para a equipe MK / contador.
 *
 * Todas as consultas filtram explicitamente por `company_id` para manter o
 * isolamento entre empresas. Nenhum valor é presumido: margem e lucro só
 * consideram itens com custo informado, e a cobertura desse custo é exibida.
 */
class BuildCompanyDashboard
{
    public const BUSINESS_TIMEZONE = 'America/Sao_Paulo';

    public const PERIODS = ['today', 'last_7_days', 'this_month', 'last_month', 'last_90_days', 'this_year', 'custom'];

    private const MONTHS_IN_TREND = 6;

    private const RANKING_SIZE = 5;

    private const MONTH_LABELS = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

    public function __construct(private AssessCompanyRisk $assessRisk) {}

    /**
     * @param  array{period?: string|null, start_date?: string|null, end_date?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function handle(Company $company, array $filters): array
    {
        $period = $filters['period'] ?? 'this_month';
        [$startDate, $endDate] = $this->resolvePeriod($period, $filters);
        $periodLengthInDays = (int) $startDate->diffInDays($endDate) + 1;
        $previousEnd = $startDate->subDay();
        $previousStart = $previousEnd->subDays($periodLengthInDays - 1);

        $current = $this->periodTotals($company, $startDate, $endDate);
        $previous = $this->periodTotals($company, $previousStart, $previousEnd);
        $margin = $this->margin($company, $startDate, $endDate);
        $products = $this->products($company, $startDate, $endDate);
        $cashFlow = $this->cashFlow($company, $startDate, $endDate, $current);
        $expenses = $this->expenses($company, $startDate, $endDate, $current['expenseTotal']);

        $sales = [
            'count' => $current['salesCount'],
            'grossTotal' => $this->money($current['grossTotal']),
            'discountTotal' => $this->money($current['discountTotal']),
            'netTotal' => $this->money($current['netTotal']),
            'averageTicket' => $this->money($this->divide($current['netTotal'], $current['salesCount'])),
            'discountRate' => $this->percent($current['discountTotal'], $current['grossTotal']),
            'refundedCount' => $current['refundedCount'],
            'refundedTotal' => $this->money($current['refundedTotal']),
            'refundRate' => $this->percent($current['refundedCount'], $current['salesCount'] + $current['refundedCount']),
            'byPaymentMethod' => [
                'pix' => $this->money($current['pixTotal']),
                'dinheiro' => $this->money($current['cashTotal']),
            ],
            'byOperator' => $this->salesByOperator($company, $startDate, $endDate),
        ];

        $result = [
            'revenue' => $this->money($current['netTotal']),
            'expenses' => $this->money($current['expenseTotal']),
            'revenueMinusExpenses' => $this->money($current['netTotal'] - $current['expenseTotal']),
            'costOfGoodsSold' => $this->money($margin['cogs']),
            'operatingResult' => $margin['costCoverage'] === 100.0
                ? $this->money($current['netTotal'] - $margin['cogs'] - $current['expenseTotal'])
                : null,
            'expenseToRevenueRate' => $this->percent($current['expenseTotal'], $current['netTotal']),
        ];

        $comparison = [
            'previousStartDate' => $previousStart->toDateString(),
            'previousEndDate' => $previousEnd->toDateString(),
            'netTotal' => $this->variation($current['netTotal'], $previous['netTotal']),
            'salesCount' => $this->variation($current['salesCount'], $previous['salesCount']),
            'averageTicket' => $this->variation(
                $this->divide($current['netTotal'], $current['salesCount']),
                $this->divide($previous['netTotal'], $previous['salesCount']),
            ),
            'expenseTotal' => $this->variation($current['expenseTotal'], $previous['expenseTotal']),
        ];

        $marginSummary = [
            'revenueWithCost' => $this->money($margin['revenueWithCost']),
            'costOfGoodsSold' => $this->money($margin['cogs']),
            'grossProfit' => $margin['revenueWithCost'] > 0 ? $this->money($margin['revenueWithCost'] - $margin['cogs']) : null,
            'grossMargin' => $margin['revenueWithCost'] > 0 ? $this->percent($margin['revenueWithCost'] - $margin['cogs'], $margin['revenueWithCost']) : null,
            'costCoverage' => $margin['costCoverage'],
        ];

        return [
            'filters' => [
                'period' => $period,
                'startDate' => $startDate->toDateString(),
                'endDate' => $endDate->toDateString(),
            ],
            'sales' => $sales,
            'margin' => $marginSummary,
            'result' => $result,
            'comparison' => $comparison,
            'expenses' => $expenses,
            'products' => $products,
            'cashFlow' => $cashFlow,
            'monthlyTrend' => $this->monthlyTrend($company, $endDate),
            'insights' => $this->insights($sales, $marginSummary, $result, $comparison, $products, $cashFlow),
            'risk' => $this->assessRisk->handle(
                $current['netTotal'],
                $current['expenseTotal'],
                $previous['netTotal'],
                (float) $cashFlow['differenceTotal'],
            ),
            'activeUsersCount' => CompanyUser::query()->where('company_id', $company->id)->where('status', 'ativa')->count(),
            'unavailable' => [
                'stock' => 'Estoque ainda não é controlado pelo sistema (backlog V2). Não há quantidades em estoque para exibir.',
                'delinquency' => 'Toda venda é paga no ato (Pix ou dinheiro), sem venda a prazo ou contas a receber. Por isso não há inadimplência a medir.',
            ],
        ];
    }

    /**
     * @param  array{start_date?: string|null, end_date?: string|null}  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolvePeriod(string $period, array $filters): array
    {
        $today = CarbonImmutable::now(self::BUSINESS_TIMEZONE)->startOfDay();

        return match ($period) {
            'today' => [$today, $today],
            'last_7_days' => [$today->subDays(6), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'last_90_days' => [$today->subDays(89), $today],
            'this_year' => [$today->startOfYear(), $today],
            'custom' => [
                CarbonImmutable::createFromFormat('!Y-m-d', $filters['start_date'] ?? '', self::BUSINESS_TIMEZONE),
                CarbonImmutable::createFromFormat('!Y-m-d', $filters['end_date'] ?? '', self::BUSINESS_TIMEZONE),
            ],
            default => [$today->startOfMonth(), $today],
        };
    }

    /**
     * Converte o intervalo de datas locais (horário de Brasília) para o fuso do banco.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function queryRange(CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $databaseTimezone = config('app.timezone', 'UTC');

        return [
            $startDate->startOfDay()->setTimezone($databaseTimezone),
            $endDate->endOfDay()->setTimezone($databaseTimezone),
        ];
    }

    /** @return Builder<Sale> */
    private function salesIn(Company $company, CarbonImmutable $startDate, CarbonImmutable $endDate, string $status = 'finalizada'): Builder
    {
        return Sale::query()
            ->where('company_id', $company->id)
            ->where('status', $status)
            ->whereBetween('occurred_at', $this->queryRange($startDate, $endDate));
    }

    /**
     * @return array{salesCount: int, grossTotal: float, discountTotal: float, netTotal: float, refundedCount: int, refundedTotal: float, pixTotal: float, cashTotal: float, expenseTotal: float}
     */
    private function periodTotals(Company $company, CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $finalized = $this->salesIn($company, $startDate, $endDate)
            ->selectRaw('COUNT(*) as sales_count, COALESCE(SUM(subtotal), 0) as gross_total, COALESCE(SUM(discount), 0) as discount_total, COALESCE(SUM(total), 0) as net_total')
            ->toBase()
            ->first();
        $refunded = $this->salesIn($company, $startDate, $endDate, 'estornada')
            ->selectRaw('COUNT(*) as sales_count, COALESCE(SUM(total), 0) as net_total')
            ->toBase()
            ->first();
        $paymentTotals = Payment::query()
            ->where('company_id', $company->id)
            ->where('status', 'confirmado')
            ->whereHas('sale', fn (Builder $query) => $query
                ->where('company_id', $company->id)
                ->where('status', 'finalizada')
                ->whereBetween('occurred_at', $this->queryRange($startDate, $endDate)))
            ->selectRaw('LOWER(method) as method, SUM(amount) as total')
            ->groupByRaw('LOWER(method)')
            ->pluck('total', 'method');

        return [
            'salesCount' => (int) $finalized->sales_count,
            'grossTotal' => (float) $finalized->gross_total,
            'discountTotal' => (float) $finalized->discount_total,
            'netTotal' => (float) $finalized->net_total,
            'refundedCount' => (int) $refunded->sales_count,
            'refundedTotal' => (float) $refunded->net_total,
            'pixTotal' => (float) $paymentTotals->get('pix', 0),
            'cashTotal' => (float) $paymentTotals->get('dinheiro', 0),
            'expenseTotal' => (float) $this->expensesIn($company, $startDate, $endDate)->sum('amount'),
        ];
    }

    /** @return Builder<Expense> */
    private function expensesIn(Company $company, CarbonImmutable $startDate, CarbonImmutable $endDate): Builder
    {
        return Expense::query()
            ->where('company_id', $company->id)
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()]);
    }

    /** @return Builder<SaleItem> */
    private function soldItemsIn(Company $company, CarbonImmutable $startDate, CarbonImmutable $endDate): Builder
    {
        return SaleItem::query()
            ->where('company_id', $company->id)
            ->whereHas('sale', fn (Builder $query) => $query
                ->where('company_id', $company->id)
                ->where('status', 'finalizada')
                ->whereBetween('occurred_at', $this->queryRange($startDate, $endDate)));
    }

    /**
     * Margem calculada sobre o valor bruto dos itens (antes do desconto da venda),
     * apenas para itens cujo custo estava informado no momento da venda.
     *
     * @return array{revenueWithCost: float, cogs: float, costCoverage: float|null}
     */
    private function margin(Company $company, CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $totals = $this->soldItemsIn($company, $startDate, $endDate)
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0) as item_revenue')
            ->selectRaw('COALESCE(SUM(CASE WHEN unit_cost IS NOT NULL THEN quantity * unit_price ELSE 0 END), 0) as revenue_with_cost')
            ->selectRaw('COALESCE(SUM(CASE WHEN unit_cost IS NOT NULL THEN quantity * unit_cost ELSE 0 END), 0) as cogs')
            ->toBase()
            ->first();

        $itemRevenue = (float) $totals->item_revenue;

        return [
            'revenueWithCost' => (float) $totals->revenue_with_cost,
            'cogs' => (float) $totals->cogs,
            'costCoverage' => $itemRevenue > 0 ? round((float) $totals->revenue_with_cost / $itemRevenue * 100, 1) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function products(Company $company, CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $catalog = Product::query()
            ->where('company_id', $company->id)
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) as active")
            ->selectRaw("SUM(CASE WHEN status = 'ativo' AND cost IS NULL THEN 1 ELSE 0 END) as active_without_cost")
            ->toBase()
            ->first();

        $ranking = $this->soldItemsIn($company, $startDate, $endDate)
            ->select('product_id', 'product_name')
            ->selectRaw('SUM(quantity) as quantity, SUM(quantity * unit_price) as revenue')
            ->selectRaw('SUM(CASE WHEN unit_cost IS NOT NULL THEN quantity * unit_price ELSE 0 END) as revenue_with_cost')
            ->selectRaw('SUM(CASE WHEN unit_cost IS NOT NULL THEN quantity * unit_cost ELSE 0 END) as cogs')
            ->groupBy('product_id', 'product_name')
            ->toBase()
            ->get()
            ->map(fn (object $item): array => [
                'productId' => $item->product_id === null ? null : (int) $item->product_id,
                'productName' => (string) $item->product_name,
                'quantity' => (int) $item->quantity,
                'revenue' => $this->money($item->revenue),
                'grossMargin' => (float) $item->revenue_with_cost > 0
                    ? $this->percent((float) $item->revenue_with_cost - (float) $item->cogs, (float) $item->revenue_with_cost)
                    : null,
            ]);

        $soldProductIds = $ranking->pluck('productId')->filter()->all();
        $activeWithoutSales = Product::query()
            ->where('company_id', $company->id)
            ->where('status', 'ativo')
            ->whereNotIn('id', $soldProductIds)
            ->orderBy('name');

        $lowMarginProducts = $ranking
            ->filter(fn (array $product): bool => $product['grossMargin'] !== null && $product['grossMargin'] < 30)
            ->sortBy('grossMargin')
            ->take(self::RANKING_SIZE)
            ->values();

        return [
            'catalogCount' => (int) $catalog->total,
            'activeCount' => (int) $catalog->active,
            'inactiveCount' => (int) $catalog->total - (int) $catalog->active,
            'activeWithoutCostCount' => (int) $catalog->active_without_cost,
            'soldDistinctCount' => $ranking->count(),
            'unitsSold' => (int) $ranking->sum('quantity'),
            'bestSellers' => $ranking->sortByDesc('quantity')->take(self::RANKING_SIZE)->values(),
            'worstSellers' => $ranking->count() > self::RANKING_SIZE
                ? $ranking->sortBy('quantity')->take(self::RANKING_SIZE)->values()
                : [],
            'topRevenue' => $ranking->sortByDesc(fn (array $product): float => (float) $product['revenue'])->take(self::RANKING_SIZE)->values(),
            'lowMargin' => $lowMarginProducts,
            'activeWithoutSalesCount' => (clone $activeWithoutSales)->count(),
            'activeWithoutSales' => $activeWithoutSales->limit(self::RANKING_SIZE)->pluck('name'),
        ];
    }

    /**
     * @param  array{netTotal: float, pixTotal: float, cashTotal: float}  $current
     * @return array<string, mixed>
     */
    private function cashFlow(Company $company, CarbonImmutable $startDate, CarbonImmutable $endDate, array $current): array
    {
        $range = $this->queryRange($startDate, $endDate);
        $refunds = Refund::query()
            ->where('company_id', $company->id)
            ->whereBetween('refunded_at', $range)
            ->selectRaw('LOWER(method) as method, SUM(amount) as total')
            ->groupByRaw('LOWER(method)')
            ->pluck('total', 'method');
        $withdrawals = (float) CashMovement::query()
            ->where('company_id', $company->id)
            ->where('type', 'retirada')
            ->whereBetween('occurred_at', $range)
            ->sum('amount');

        $closedSessions = CashSession::query()
            ->where('company_id', $company->id)
            ->where('status', 'fechado')
            ->whereBetween('opened_at', $range);
        $sessionTotals = (clone $closedSessions)
            ->selectRaw('COUNT(*) as closed_count, COALESCE(SUM(difference_amount), 0) as difference_total')
            ->selectRaw('SUM(CASE WHEN difference_amount < 0 THEN 1 ELSE 0 END) as shortage_count')
            ->selectRaw('SUM(CASE WHEN difference_amount > 0 THEN 1 ELSE 0 END) as surplus_count')
            ->toBase()
            ->first();

        $refundTotal = (float) $refunds->sum();
        $inflow = $current['pixTotal'] + $current['cashTotal'];

        return [
            'inflow' => $this->money($inflow),
            'inflowPix' => $this->money($current['pixTotal']),
            'inflowCash' => $this->money($current['cashTotal']),
            'refundOutflow' => $this->money($refundTotal),
            'refundOutflowPix' => $this->money($refunds->get('pix', 0)),
            'refundOutflowCash' => $this->money($refunds->get('dinheiro', 0)),
            'netInflow' => $this->money($inflow - $refundTotal),
            'withdrawals' => $this->money(abs($withdrawals)),
            'sessionCount' => CashSession::query()->where('company_id', $company->id)->whereBetween('opened_at', $range)->count(),
            'openSessionCount' => CashSession::query()->where('company_id', $company->id)->where('status', 'aberto')->count(),
            'closedSessionCount' => (int) $sessionTotals->closed_count,
            'differenceTotal' => $this->money($sessionTotals->difference_total),
            'shortageCount' => (int) $sessionTotals->shortage_count,
            'surplusCount' => (int) $sessionTotals->surplus_count,
        ];
    }

    /** @return array<string, mixed> */
    private function expenses(Company $company, CarbonImmutable $startDate, CarbonImmutable $endDate, float $expenseTotal): array
    {
        $byType = $this->expensesIn($company, $startDate, $endDate)
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $topExpenses = $this->expensesIn($company, $startDate, $endDate)
            ->select('description')
            ->selectRaw('COUNT(*) as entries, SUM(amount) as total')
            ->groupBy('description')
            ->orderByDesc('total')
            ->limit(self::RANKING_SIZE)
            ->get()
            ->map(fn (Expense $expense): array => [
                'description' => $expense->description,
                'entries' => (int) $expense->getAttribute('entries'),
                'total' => $this->money($expense->getAttribute('total')),
                'share' => $this->percent((float) $expense->getAttribute('total'), $expenseTotal),
            ]);

        return [
            'count' => $this->expensesIn($company, $startDate, $endDate)->count(),
            'total' => $this->money($expenseTotal),
            'fixedTotal' => $this->money($byType->get('fixa', 0)),
            'variableTotal' => $this->money($byType->get('variavel', 0)),
            'fixedShare' => $this->percent((float) $byType->get('fixa', 0), $expenseTotal),
            'topExpenses' => $topExpenses,
        ];
    }

    /** @return Collection<int, array{operator: string, salesCount: int, total: string, averageTicket: string}> */
    private function salesByOperator(Company $company, CarbonImmutable $startDate, CarbonImmutable $endDate): Collection
    {
        $rows = $this->salesIn($company, $startDate, $endDate)
            ->select('company_user_id')
            ->selectRaw('COUNT(*) as sales_count, SUM(total) as total')
            ->groupBy('company_user_id')
            ->orderByDesc('total')
            ->toBase()
            ->get();
        $operatorNames = CompanyUser::query()
            ->where('company_id', $company->id)
            ->whereIn('id', $rows->pluck('company_user_id'))
            ->pluck('name', 'id');

        return $rows->map(fn (object $row): array => [
            'operator' => (string) $operatorNames->get($row->company_user_id, '—'),
            'salesCount' => (int) $row->sales_count,
            'total' => $this->money($row->total),
            'averageTicket' => $this->money($this->divide((float) $row->total, (int) $row->sales_count)),
        ]);
    }

    /**
     * Evolução dos últimos meses, terminando no mês da data final do filtro.
     * Usa uma consulta por mês para continuar compatível com MySQL e SQLite.
     *
     * @return list<array{month: string, label: string, salesCount: int, revenue: string, expenses: string, result: string}>
     */
    private function monthlyTrend(Company $company, CarbonImmutable $endDate): array
    {
        $trend = [];
        $lastMonth = $endDate->startOfMonth();

        for ($offset = self::MONTHS_IN_TREND - 1; $offset >= 0; $offset--) {
            $monthStart = $lastMonth->subMonthsNoOverflow($offset);
            $monthEnd = $monthStart->endOfMonth()->startOfDay();
            $sales = $this->salesIn($company, $monthStart, $monthEnd)
                ->selectRaw('COUNT(*) as sales_count, COALESCE(SUM(total), 0) as revenue')
                ->toBase()
                ->first();
            $expenses = (float) $this->expensesIn($company, $monthStart, $monthEnd)->sum('amount');

            $trend[] = [
                'month' => $monthStart->format('Y-m'),
                'label' => self::MONTH_LABELS[$monthStart->month - 1].'/'.$monthStart->format('y'),
                'salesCount' => (int) $sales->sales_count,
                'revenue' => $this->money($sales->revenue),
                'expenses' => $this->money($expenses),
                'result' => $this->money((float) $sales->revenue - $expenses),
            ];
        }

        return $trend;
    }

    /**
     * Regras simples que apontam onde a empresa está indo bem ou precisa de atenção.
     *
     * @param  array<string, mixed>  $sales
     * @param  array<string, mixed>  $margin
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $comparison
     * @param  array<string, mixed>  $products
     * @param  array<string, mixed>  $cashFlow
     * @return list<array{tone: 'positive'|'warning'|'neutral', title: string, detail: string}>
     */
    private function insights(array $sales, array $margin, array $result, array $comparison, array $products, array $cashFlow): array
    {
        $insights = [];

        if ($sales['count'] === 0) {
            return [[
                'tone' => 'neutral',
                'title' => 'Sem vendas finalizadas no período',
                'detail' => 'Não há dados suficientes para avaliar o desempenho. Verifique se a empresa está usando o PDV.',
            ]];
        }

        $revenueVariation = $comparison['netTotal'];
        if ($revenueVariation !== null && $revenueVariation >= 10) {
            $insights[] = ['tone' => 'positive', 'title' => 'Faturamento em alta', 'detail' => "Receita {$this->formatPercent($revenueVariation)} maior que no período anterior."];
        } elseif ($revenueVariation !== null && $revenueVariation <= -10) {
            $insights[] = ['tone' => 'warning', 'title' => 'Faturamento em queda', 'detail' => 'Receita '.$this->formatPercent(abs($revenueVariation)).' menor que no período anterior.'];
        }

        if ((float) $result['revenueMinusExpenses'] < 0) {
            $insights[] = ['tone' => 'warning', 'title' => 'Despesas maiores que a receita', 'detail' => 'As despesas lançadas superam as vendas do período.'];
        } elseif ($result['expenseToRevenueRate'] !== null && $result['expenseToRevenueRate'] <= 50) {
            $insights[] = ['tone' => 'positive', 'title' => 'Despesas sob controle', 'detail' => "Despesas consomem {$this->formatPercent($result['expenseToRevenueRate'])} da receita."];
        }

        if ($margin['grossMargin'] !== null) {
            if ($margin['grossMargin'] < 30) {
                $insights[] = ['tone' => 'warning', 'title' => 'Margem bruta baixa', 'detail' => "Margem de {$this->formatPercent($margin['grossMargin'])} nos itens com custo informado. Revise preços ou custos."];
            } elseif ($margin['grossMargin'] >= 50) {
                $insights[] = ['tone' => 'positive', 'title' => 'Boa margem bruta', 'detail' => "Margem de {$this->formatPercent($margin['grossMargin'])} nos itens com custo informado."];
            }
        }

        if ($margin['costCoverage'] !== null && $margin['costCoverage'] < 80) {
            $insights[] = ['tone' => 'warning', 'title' => 'Custos incompletos', 'detail' => "Só {$this->formatPercent($margin['costCoverage'])} da receita de itens tem custo informado; margem e lucro ficam parciais."];
        }

        if ($sales['refundRate'] !== null && $sales['refundRate'] >= 5) {
            $insights[] = ['tone' => 'warning', 'title' => 'Muitos estornos', 'detail' => "{$this->formatPercent($sales['refundRate'])} das vendas foram estornadas."];
        }

        if ($sales['discountRate'] !== null && $sales['discountRate'] >= 10) {
            $insights[] = ['tone' => 'warning', 'title' => 'Descontos elevados', 'detail' => "Descontos representam {$this->formatPercent($sales['discountRate'])} do valor bruto vendido."];
        }

        if ($cashFlow['shortageCount'] > 0) {
            $insights[] = ['tone' => 'warning', 'title' => 'Quebras de caixa', 'detail' => "{$cashFlow['shortageCount']} fechamento(s) com falta de dinheiro no período."];
        } elseif ($cashFlow['closedSessionCount'] > 0 && $cashFlow['surplusCount'] === 0) {
            $insights[] = ['tone' => 'positive', 'title' => 'Caixa conferido', 'detail' => 'Todos os fechamentos do período bateram com o valor esperado.'];
        }

        if ($products['activeWithoutSalesCount'] > 0) {
            $insights[] = ['tone' => 'neutral', 'title' => 'Produtos parados', 'detail' => "{$products['activeWithoutSalesCount']} produto(s) ativo(s) sem nenhuma venda no período."];
        }

        return $insights;
    }

    private function divide(float $amount, int|float $divisor): float
    {
        return $divisor > 0 ? $amount / $divisor : 0.0;
    }

    private function percent(int|float $part, int|float $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }

    private function variation(int|float $current, int|float $previous): ?float
    {
        return $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null;
    }

    private function formatPercent(float $value): string
    {
        return number_format($value, 1, ',', '.').'%';
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
