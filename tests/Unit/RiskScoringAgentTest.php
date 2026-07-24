<?php

namespace Tests\Unit;

use LinkGuard\Services\Agents\AgentResult;
use LinkGuard\Services\Agents\RiskScoringAgent;
use PHPUnit\Framework\TestCase;

final class RiskScoringAgentTest extends TestCase
{
    public function testScoreUsesUniqueRulesAndCorrectLevel(): void
    {
        $agent = new RiskScoringAgent(['literal_ip' => 20, 'phishing_keyword' => 10]);
        $result = $agent->analyze([
            new AgentResult('A', 'complete', [
                ['code' => 'literal_ip', 'title' => 'IP'],
                ['code' => 'phishing_keyword', 'title' => 'Words'],
            ]),
            new AgentResult('B', 'complete', [['code' => 'phishing_keyword', 'title' => 'Words']]),
        ]);
        self::assertSame(30, $result['score']);
        self::assertSame('Moderate Risk', $result['level']);
        self::assertCount(2, $result['contributions']);
    }

    public function testScoreIsCappedAtOneHundred(): void
    {
        $agent = new RiskScoringAgent(['a' => 60, 'b' => 60]);
        $result = $agent->analyze([
            new AgentResult('A', 'complete', [
                ['code' => 'a', 'title' => 'A'],
                ['code' => 'b', 'title' => 'B'],
            ]),
        ]);
        self::assertSame(100, $result['score']);
        self::assertSame('Critical Risk', $result['level']);
    }

    public function testZeroIsLabeledAsObservedRiskNotSafety(): void
    {
        $result = (new RiskScoringAgent([]))->analyze([]);

        self::assertSame(0, $result['score']);
        self::assertSame('Low Observed Risk', $result['level']);
    }
}
