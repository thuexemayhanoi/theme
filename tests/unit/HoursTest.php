<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
require_once NT_TEST_ROOT . '/inc/lib/hours.php';
final class HoursTest extends TestCase {
    private function at(string $date, array $days = array(), array $exceptions = array()): array {
        return nt_hours_at((new DateTimeImmutable($date, new DateTimeZone('Asia/Ho_Chi_Minh')))->getTimestamp(), '09:00', '21:00', $days, $exceptions);
    }
    public function test_boundaries_and_midnight(): void {
        $this->assertFalse($this->at('2026-10-18 08:59:59')['open']);
        $this->assertTrue($this->at('2026-10-18 09:00:00')['open']);
        $this->assertTrue($this->at('2026-10-18 20:59:59')['open']);
        $this->assertFalse($this->at('2026-10-18 21:00:00')['open']);
        $this->assertStringContainsString('19/10', $this->at('2026-10-18 21:00:00')['label']);
        $this->assertStringContainsString('09:00', $this->at('2026-10-19 00:00:00')['label']);
    }
    public function test_holidays_and_special_hours(): void {
        $r = $this->at('2026-10-18 12:00', array(7), array('2026-10-19'=>'closed','2026-10-20'=>'10:00-18:00'));
        $this->assertFalse($r['open']);
        $this->assertStringContainsString('10:00 ngày 20/10', $r['label']);
        $this->assertTrue($this->at('2026-10-18 12:00', array(7), array('2026-10-18'=>'10:00-18:00'))['open']);
    }
    public function test_permanent_closure_has_no_invented_opening(): void {
        $r = $this->at('2026-10-18 12:00', range(1,7));
        $this->assertNull($r['next_change']);
        $this->assertStringContainsString('xác nhận', $r['label']);
    }
    public function test_invalid_times(): void {
        $this->assertFalse(nt_valid_time('29:99'));
        $this->assertFalse(nt_valid_time('9:00'));
        $this->assertTrue(nt_valid_time('00:00'));
    }
}
