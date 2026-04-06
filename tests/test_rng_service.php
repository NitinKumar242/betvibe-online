<?php

use PHPUnit\Framework\TestCase;
use App\Services\RNGService;

/**
 * RNG Service Tests
 * Tests for cryptographically secure random number generation
 */
class RNGServiceTest extends TestCase
{
    /**
     * Test randInt returns integer within range
     */
    public function testRandIntReturnsIntegerWithinRange()
    {
        $min = 1;
        $max = 100;

        for ($i = 0; $i < 100; $i++) {
            $result = RNGService::randInt($min, $max);
            $this->assertIsInt($result);
            $this->assertGreaterThanOrEqual($min, $result);
            $this->assertLessThanOrEqual($max, $result);
        }
    }

    /**
     * Test randInt with same min and max
     */
    public function testRandIntWithSameMinMax()
    {
        $result = RNGService::randInt(5, 5);
        $this->assertEquals(5, $result);
    }

    /**
     * Test randFloat returns float between 0 and 1
     */
    public function testRandFloatReturnsFloatBetweenZeroAndOne()
    {
        for ($i = 0; $i < 100; $i++) {
            $result = RNGService::randFloat();
            $this->assertIsFloat($result);
            $this->assertGreaterThanOrEqual(0.0, $result);
            $this->assertLessThanOrEqual(1.0, $result);
        }
    }

    /**
     * Test weightedRandom distribution
     * Run 10000 times and check within 2% of expected
     */
    public function testWeightedRandomDistribution()
    {
        $weights = ['red' => 45, 'green' => 45, 'violet' => 10];
        $total = array_sum($weights);
        $iterations = 10000;
        $results = ['red' => 0, 'green' => 0, 'violet' => 0];

        for ($i = 0; $i < $iterations; $i++) {
            $result = RNGService::weightedRandom($weights);
            $results[$result]++;
        }

        // Check each result is within 2% of expected
        foreach ($weights as $key => $weight) {
            $expected = ($weight / $total) * $iterations;
            $actual = $results[$key];
            $percentage = ($actual / $iterations) * 100;
            $expectedPercentage = ($weight / $total) * 100;
            $difference = abs($percentage - $expectedPercentage);

            $this->assertLessThanOrEqual(
                2.0,
                $difference,
                "Distribution for '{$key}' is off by {$difference}% (expected ~{$expectedPercentage}%, got {$percentage}%)"
            );
        }
    }

    /**
     * Test weightedRandom with single option
     */
    public function testWeightedRandomWithSingleOption()
    {
        $weights = ['only' => 100];
        $result = RNGService::weightedRandom($weights);
        $this->assertEquals('only', $result);
    }

    /**
     * Test crashMultiplier never returns less than 1.00
     */
    public function testCrashMultiplierNeverReturnsLessThanOne()
    {
        for ($i = 0; $i < 1000; $i++) {
            $result = RNGService::crashMultiplier();
            $this->assertGreaterThanOrEqual(1.00, $result);
        }
    }

    /**
     * Test crashMultiplier is capped at 1000.00
     */
    public function testCrashMultiplierCappedAtOneThousand()
    {
        for ($i = 0; $i < 1000; $i++) {
            $result = RNGService::crashMultiplier();
            $this->assertLessThanOrEqual(1000.00, $result);
        }
    }

    /**
     * Test crashMultiplier returns float with 2 decimal places
     */
    public function testCrashMultiplierReturnsTwoDecimalPlaces()
    {
        for ($i = 0; $i < 100; $i++) {
            $result = RNGService::crashMultiplier();
            $rounded = round($result, 2);
            $this->assertEquals(
                $rounded,
                $result,
                "Multiplier should have exactly 2 decimal places, got {$result}"
            );
        }
    }

    /**
     * Test crashMultiplier with custom house edge
     */
    public function testCrashMultiplierWithCustomHouseEdge()
    {
        $results = [];
        for ($i = 0; $i < 1000; $i++) {
            $result = RNGService::crashMultiplier(0.10); // 10% house edge
            $results[] = $result;
        }

        // With 10% house edge, we should see more instant crashes (1.00)
        $instantCrashes = array_filter($results, function ($val) {
            return $val == 1.00; });
        $instantCrashRate = count($instantCrashes) / count($results);

        // Should be approximately 10% (allow 5% margin for randomness)
        $this->assertGreaterThan(0.05, $instantCrashRate);
        $this->assertLessThan(0.15, $instantCrashRate);
    }

    /**
     * Test generateMines returns exactly mineCount values
     */
    public function testGenerateMinesReturnsExactCount()
    {
        $gridSize = 25;
        $mineCount = 5;

        for ($i = 0; $i < 100; $i++) {
            $result = RNGService::generateMines($gridSize, $mineCount);
            $this->assertCount($mineCount, $result);
        }
    }

    /**
     * Test generateMines returns unique values
     */
    public function testGenerateMinesReturnsUniqueValues()
    {
        $gridSize = 25;
        $mineCount = 10;

        for ($i = 0; $i < 100; $i++) {
            $result = RNGService::generateMines($gridSize, $mineCount);
            $unique = array_unique($result);
            $this->assertCount(
                $mineCount,
                $unique,
                "Mine positions should be unique"
            );
        }
    }

    /**
     * Test generateMines returns values within range
     */
    public function testGenerateMinesReturnsValuesWithinRange()
    {
        $gridSize = 25;
        $mineCount = 8;

        for ($i = 0; $i < 100; $i++) {
            $result = RNGService::generateMines($gridSize, $mineCount);
            foreach ($result as $position) {
                $this->assertGreaterThanOrEqual(0, $position);
                $this->assertLessThan($gridSize, $position);
            }
        }
    }

    /**
     * Test generateMines with maximum mines
     */
    public function testGenerateMinesWithMaximumMines()
    {
        $gridSize = 25;
        $mineCount = 24; // Almost all tiles

        $result = RNGService::generateMines($gridSize, $mineCount);
        $this->assertCount($mineCount, $result);
        $this->assertCount($mineCount, array_unique($result));
    }

    /**
     * Test generateSlotReel returns valid key
     */
    public function testGenerateSlotReelReturnsValidKey()
    {
        $weights = ['cherry' => 35, 'lemon' => 25, 'orange' => 20, 'grape' => 12, 'seven' => 6, 'diamond' => 2];

        for ($i = 0; $i < 100; $i++) {
            $result = RNGService::generateSlotReel($weights);
            $this->assertArrayHasKey(
                $result,
                $weights,
                "Result '{$result}' is not a valid symbol"
            );
        }
    }

    /**
     * Test generateSlotReel distribution
     */
    public function testGenerateSlotReelDistribution()
    {
        $weights = ['common' => 70, 'rare' => 20, 'legendary' => 10];
        $total = array_sum($weights);
        $iterations = 10000;
        $results = ['common' => 0, 'rare' => 0, 'legendary' => 0];

        for ($i = 0; $i < $iterations; $i++) {
            $result = RNGService::generateSlotReel($weights);
            $results[$result]++;
        }

        // Check each result is within 2% of expected
        foreach ($weights as $key => $weight) {
            $expected = ($weight / $total) * $iterations;
            $actual = $results[$key];
            $percentage = ($actual / $iterations) * 100;
            $expectedPercentage = ($weight / $total) * 100;
            $difference = abs($percentage - $expectedPercentage);

            $this->assertLessThanOrEqual(
                2.0,
                $difference,
                "Distribution for '{$key}' is off by {$difference}%"
            );
        }
    }

    /**
     * Test generateBytes returns correct length
     */
    public function testGenerateBytesReturnsCorrectLength()
    {
        $length = 32;
        $result = RNGService::generateBytes($length);
        $this->assertEquals($length, strlen($result));
    }

    /**
     * Test generateBytes returns binary data
     */
    public function testGenerateBytesReturnsBinaryData()
    {
        $result = RNGService::generateBytes(16);
        // Binary data should contain non-printable characters
        $this->assertNotEquals(bin2hex($result), $result);
    }

    /**
     * Test generateFloat returns float within range
     */
    public function testGenerateFloatReturnsFloatWithinRange()
    {
        $min = 5.5;
        $max = 10.5;

        for ($i = 0; $i < 100; $i++) {
            $result = RNGService::generateFloat($min, $max);
            $this->assertIsFloat($result);
            $this->assertGreaterThanOrEqual($min, $result);
            $this->assertLessThanOrEqual($max, $result);
        }
    }

    /**
     * Test instance method generateInt works
     */
    public function testInstanceMethodGenerateInt()
    {
        $rng = new RNGService();
        $result = $rng->generateInt(1, 100);
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertLessThanOrEqual(100, $result);
    }
}
