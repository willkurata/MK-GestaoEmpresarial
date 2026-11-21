<?php

namespace App\Actions\Companies;

/**
 * Classifica o risco financeiro de um período em quatro níveis de cor
 * (verde, amarelo, laranja, vermelho) a partir de valores já calculados.
 *
 * As regras são propositalmente simples e explicáveis: cada sinal que eleva
 * o nível vem acompanhado de um motivo em texto, para que a cor nunca seja a
 * única informação exibida. Sem receita nem despesas, o nível é "sem dados".
 */
class AssessCompanyRisk
{
    public const LEVELS = ['none', 'ok', 'attention', 'high', 'critical'];

    public const LABELS = [
        'none' => 'Sem dados',
        'ok' => 'Saudável',
        'attention' => 'Atenção',
        'high' => 'Risco alto',
        'critical' => 'Crítico',
    ];

    /**
     * @param  float|null  $previousRevenue  receita do período anterior de mesma duração; null quando não comparável
     * @param  float  $cashDifference  soma das diferenças de fechamento de caixa (negativo = falta)
     * @return array{level: string, label: string, reasons: list<string>}
     */
    public function handle(float $revenue, float $expenses, ?float $previousRevenue = null, float $cashDifference = 0.0): array
    {
        if ($revenue <= 0 && $expenses <= 0) {
            return $this->result('none', ['Nenhuma venda ou despesa registrada no período.']);
        }

        $level = 'ok';
        $reasons = [];

        if ($revenue <= 0) {
            $level = $this->raise($level, 'critical');
            $reasons[] = 'Há despesas lançadas e nenhuma venda no período.';
        } else {
            $expenseRatio = $expenses / $revenue * 100;
            $ratioLabel = number_format($expenseRatio, 0, ',', '.').'%';

            if ($expenseRatio >= 100) {
                $level = $this->raise($level, 'critical');
                $reasons[] = "Despesas somam {$ratioLabel} da receita.";
            } elseif ($expenseRatio >= 80) {
                $level = $this->raise($level, 'high');
                $reasons[] = "Despesas somam {$ratioLabel} da receita.";
            } elseif ($expenseRatio >= 60) {
                $level = $this->raise($level, 'attention');
                $reasons[] = "Despesas somam {$ratioLabel} da receita.";
            }
        }

        if ($previousRevenue !== null && $previousRevenue > 0) {
            $variation = ($revenue - $previousRevenue) / $previousRevenue * 100;
            $variationLabel = number_format(abs($variation), 0, ',', '.').'%';

            if ($variation <= -30) {
                $level = $this->raise($level, 'high');
                $reasons[] = "Receita caiu {$variationLabel} em relação ao período anterior.";
            } elseif ($variation <= -10) {
                $level = $this->raise($level, 'attention');
                $reasons[] = "Receita caiu {$variationLabel} em relação ao período anterior.";
            }
        }

        if ($cashDifference < 0) {
            $level = $this->raise($level, 'attention');
            $reasons[] = 'Fechamentos de caixa com falta de dinheiro.';
        }

        if ($reasons === []) {
            $reasons[] = 'Despesas abaixo de 60% da receita e sem queda relevante.';
        }

        return $this->result($level, $reasons);
    }

    private function raise(string $current, string $candidate): string
    {
        return array_search($candidate, self::LEVELS, true) > array_search($current, self::LEVELS, true)
            ? $candidate
            : $current;
    }

    /**
     * @param  list<string>  $reasons
     * @return array{level: string, label: string, reasons: list<string>}
     */
    private function result(string $level, array $reasons): array
    {
        return ['level' => $level, 'label' => self::LABELS[$level], 'reasons' => $reasons];
    }
}
