<?php

use App\Actions\Companies\AssessCompanyRisk;

it('classifies the period risk on the four-colour scale', function (float $revenue, float $expenses, ?float $previousRevenue, float $cashDifference, string $expectedLevel) {
    $risk = (new AssessCompanyRisk)->handle($revenue, $expenses, $previousRevenue, $cashDifference);

    expect($risk['level'])->toBe($expectedLevel)
        ->and($risk['label'])->toBe(AssessCompanyRisk::LABELS[$expectedLevel])
        ->and($risk['reasons'])->not->toBeEmpty();
})->with([
    'no data' => [0, 0, null, 0, 'none'],
    'healthy' => [1000, 500, 1000, 0, 'ok'],
    'expenses at 60% of revenue' => [1000, 600, null, 0, 'attention'],
    'expenses at 80% of revenue' => [1000, 800, null, 0, 'high'],
    'expenses above revenue' => [1000, 1200, null, 0, 'critical'],
    'expenses without sales' => [0, 50, null, 0, 'critical'],
    'revenue down 10%' => [900, 100, 1000, 0, 'attention'],
    'revenue down 30%' => [700, 100, 1000, 0, 'high'],
    'cash shortage' => [1000, 100, null, -5, 'attention'],
]);

it('keeps the highest level when several signals apply and explains each one', function () {
    $risk = (new AssessCompanyRisk)->handle(600, 500, 1000, -10);

    expect($risk['level'])->toBe('high')
        ->and($risk['reasons'])->toHaveCount(3);
});
