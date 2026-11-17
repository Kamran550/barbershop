<?php

namespace Tests\Unit;

use App\Services\Booking\TimeRange;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TimeRangeTest extends TestCase
{
    private function r(string $from, string $to): TimeRange
    {
        return new TimeRange(CarbonImmutable::parse("2030-01-01 $from"), CarbonImmutable::parse("2030-01-01 $to"));
    }

    /** @param list<TimeRange> $ranges */
    private function str(array $ranges): array
    {
        return array_map(fn (TimeRange $r) => $r->start->format('H:i').'-'.$r->end->format('H:i'), $ranges);
    }

    public function test_end_must_be_after_start(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->r('10:00', '10:00');
    }

    public function test_adjacent_ranges_do_not_overlap(): void
    {
        $this->assertFalse($this->r('10:00', '10:30')->overlaps($this->r('10:30', '11:00')));
        $this->assertTrue($this->r('10:00', '10:31')->overlaps($this->r('10:30', '11:00')));
    }

    public function test_contains(): void
    {
        $this->assertTrue($this->r('10:00', '14:00')->contains($this->r('13:30', '14:00')));
        $this->assertFalse($this->r('10:00', '14:00')->contains($this->r('13:45', '14:15')));
    }

    public function test_subtract_middle_splits_in_two(): void
    {
        $this->assertSame(['10:00-14:00', '15:00-20:00'], $this->str($this->r('10:00', '20:00')->subtract($this->r('14:00', '15:00'))));
    }

    public function test_subtract_edges_and_whole(): void
    {
        $this->assertSame(['11:00-20:00'], $this->str($this->r('10:00', '20:00')->subtract($this->r('09:00', '11:00'))));
        $this->assertSame(['10:00-19:00'], $this->str($this->r('10:00', '20:00')->subtract($this->r('19:00', '21:00'))));
        $this->assertSame([], $this->r('10:00', '20:00')->subtract($this->r('09:00', '21:00')));
        $this->assertSame(['10:00-20:00'], $this->str($this->r('10:00', '20:00')->subtract($this->r('20:00', '21:00'))));
    }

    public function test_subtract_all(): void
    {
        $free = TimeRange::subtractAll([$this->r('10:00', '20:00')], [
            $this->r('14:00', '15:00'),  // fasilə
            $this->r('10:55', '11:35'),  // rezervasiya + buffer
            $this->r('17:00', '18:00'),  // blok
        ]);

        $this->assertSame(['10:00-10:55', '11:35-14:00', '15:00-17:00', '18:00-20:00'], $this->str($free));
    }
}
