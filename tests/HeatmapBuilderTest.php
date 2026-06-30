<?php
declare(strict_types=1);

namespace Tests;

use App\Services\HeatmapBuilder;
use PHPUnit\Framework\TestCase;

final class HeatmapBuilderTest extends TestCase
{
    public function testLevelForWords(): void
    {
        $this->assertSame(0, HeatmapBuilder::levelForWords(0));
        $this->assertSame(1, HeatmapBuilder::levelForWords(100));
        $this->assertSame(2, HeatmapBuilder::levelForWords(500));
        $this->assertSame(3, HeatmapBuilder::levelForWords(1000));
        $this->assertSame(4, HeatmapBuilder::levelForWords(1500));
    }

    public function testBuildDailyGridCountsActiveDays(): void
    {
        $today = date('Y-m-d');
        $grid = HeatmapBuilder::buildDailyGrid([
            $today => 2,
            date('Y-m-d', strtotime('-3 days')) => 1,
        ]);

        $this->assertGreaterThan(50, count($grid['days']));
        $this->assertSame(2, $grid['activeDays']);
        $this->assertSame(3, $grid['totalEvents']);
        $this->assertGreaterThan(0, $grid['weeks']);
    }

    public function testBuildWordGridAddsMetadata(): void
    {
        $today = date('Y-m-d');
        $grid = HeatmapBuilder::buildWordGrid(
            [$today => 600],
            30,
            static fn(string $date, int $words, bool $inRange): array => [
                'words' => $inRange ? $words : 0,
                'articles' => $inRange ? 1 : 0,
            ]
        );

        $todayCell = null;
        foreach ($grid['days'] as $day) {
            if (($day['date'] ?? '') === $today) {
                $todayCell = $day;
                break;
            }
        }
        $this->assertIsArray($todayCell);
        $this->assertSame(2, $todayCell['level']);
        $this->assertSame(600, $todayCell['words']);
        $this->assertSame(1, $todayCell['articles']);
    }

    public function testWordCountStripsMarkdown(): void
    {
        $count = HeatmapBuilder::wordCount("# Title\n\nHello **world**");
        $this->assertGreaterThan(0, $count);
        $this->assertLessThan(20, $count);
    }
}
