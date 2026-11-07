<?php
/** Fixed VND prices. Never accept a price or total from the browser. */
function notezy_plan_prices(): array
{
    return ['STUDENT' => 29000, 'PERSONAL' => 59000, 'TEAM' => 149000];
}

function notezy_order_total(string $planCode, int $months): ?array
{
    $prices = notezy_plan_prices();
    if (!isset($prices[$planCode]) || !in_array($months, [1, 3, 12], true)) {
        return null;
    }
    $subtotal = $prices[$planCode] * $months;
    $discount = $months === 12 ? intdiv($subtotal, 10) : 0;
    return ['unit_price_vnd' => $prices[$planCode], 'months' => $months,
        'subtotal_vnd' => $subtotal, 'discount_vnd' => $discount,
        'total_vnd' => $subtotal - $discount];
}
