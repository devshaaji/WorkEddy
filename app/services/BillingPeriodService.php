<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use DateTimeImmutable;
use DateTimeInterface;

final class BillingPeriodService
{
    /**
     * @return array{billing_cycle: string, period_start: DateTimeImmutable, period_end: DateTimeImmutable}
     */
    public function currentPeriod(string $startDate, string $billingCycle, ?DateTimeImmutable $now = null): array
    {
        $cycle = $this->normalizeCycle($billingCycle);
        $anchor = $this->anchorDate($startDate);
        $now = $now ?? new DateTimeImmutable('now');

        if ($now < $anchor) {
            return [
                'billing_cycle' => $cycle,
                'period_start' => $anchor,
                'period_end' => $this->shiftByCycle($anchor, 1, $cycle),
            ];
        }

        $periodStart = $this->periodStartForNow($anchor, $now, $cycle);
        $periodEnd = $this->shiftByCycle($anchor, $this->cyclesBetween($anchor, $periodStart, $cycle) + 1, $cycle);

        return [
            'billing_cycle' => $cycle,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];
    }

    private function normalizeCycle(string $billingCycle): string
    {
        $cycle = strtolower(trim($billingCycle));

        return match ($cycle) {
            'yearly', 'annual' => 'yearly',
            default => 'monthly',
        };
    }

    private function anchorDate(string $startDate): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $startDate);
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed->setTime(0, 0, 0);
        }

        return (new DateTimeImmutable($startDate))->setTime(0, 0, 0);
    }

    private function periodStartForNow(DateTimeImmutable $anchor, DateTimeImmutable $now, string $cycle): DateTimeImmutable
    {
        if ($cycle === 'yearly') {
            $approx = max(0, (int) $now->format('Y') - (int) $anchor->format('Y'));
            $candidate = $this->shiftYearsClamped($anchor, $approx);

            while ($candidate > $now) {
                $approx--;
                $candidate = $this->shiftYearsClamped($anchor, max(0, $approx));
            }

            while ($this->shiftYearsClamped($anchor, $approx + 1) <= $now) {
                $approx++;
                $candidate = $this->shiftYearsClamped($anchor, $approx);
            }

            return $candidate;
        }

        $monthsNow = ((int) $now->format('Y') * 12) + ((int) $now->format('n') - 1);
        $monthsAnchor = ((int) $anchor->format('Y') * 12) + ((int) $anchor->format('n') - 1);
        $approx = max(0, $monthsNow - $monthsAnchor);
        $candidate = $this->shiftMonthsClamped($anchor, $approx);

        while ($candidate > $now) {
            $approx--;
            $candidate = $this->shiftMonthsClamped($anchor, max(0, $approx));
        }

        while ($this->shiftMonthsClamped($anchor, $approx + 1) <= $now) {
            $approx++;
            $candidate = $this->shiftMonthsClamped($anchor, $approx);
        }

        return $candidate;
    }

    private function cyclesBetween(DateTimeImmutable $anchor, DateTimeImmutable $periodStart, string $cycle): int
    {
        if ($cycle === 'yearly') {
            return max(0, (int) $periodStart->format('Y') - (int) $anchor->format('Y'));
        }

        $monthsStart = ((int) $periodStart->format('Y') * 12) + ((int) $periodStart->format('n') - 1);
        $monthsAnchor = ((int) $anchor->format('Y') * 12) + ((int) $anchor->format('n') - 1);

        return max(0, $monthsStart - $monthsAnchor);
    }

    private function shiftByCycle(DateTimeImmutable $anchor, int $cycles, string $cycle): DateTimeImmutable
    {
        if ($cycle === 'yearly') {
            return $this->shiftYearsClamped($anchor, $cycles);
        }

        return $this->shiftMonthsClamped($anchor, $cycles);
    }

    private function shiftYearsClamped(DateTimeImmutable $anchor, int $years): DateTimeImmutable
    {
        $targetYear = (int) $anchor->format('Y') + $years;
        $month = (int) $anchor->format('n');
        $day = min((int) $anchor->format('j'), $this->daysInMonth($month, $targetYear));

        return $anchor->setDate($targetYear, $month, $day);
    }

    private function shiftMonthsClamped(DateTimeImmutable $anchor, int $months): DateTimeImmutable
    {
        $startYear = (int) $anchor->format('Y');
        $startMonth = (int) $anchor->format('n');
        $startDay = (int) $anchor->format('j');

        $monthIndex = ($startYear * 12) + ($startMonth - 1) + $months;
        $targetYear = intdiv($monthIndex, 12);
        $targetMonth = ($monthIndex % 12) + 1;
        $targetDay = min($startDay, $this->daysInMonth($targetMonth, $targetYear));

        return $anchor->setDate($targetYear, $targetMonth, $targetDay);
    }

    private function daysInMonth(int $month, int $year): int
    {
        return (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
    }
}