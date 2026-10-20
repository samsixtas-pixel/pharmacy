<?php
/**
 * functions.php
 * Small shared helper functions used across views.
 */

function formatMoney(float|int|string $amount): string
{
    return 'TZS ' . number_format((float) $amount, 2);
}

function formatDate(string $date): string
{
    $ts = strtotime($date);
    return $ts ? date('d M Y', $ts) : $date;
}

function statusBadge(string $status): string
{
    $map = [
        'paid'     => 'success',
        'partial'  => 'warning',
        'unpaid'   => 'danger',
        'active'   => 'success',
        'inactive' => 'secondary',
    ];
    $class = $map[$status] ?? 'secondary';
    $label = ucfirst($status);
    return "<span class=\"badge bg-{$class}\">{$label}</span>";
}

function e(?string $value): string
{
    return Validator::clean($value);
}
