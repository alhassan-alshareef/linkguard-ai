<?php

namespace LinkGuard\Services\Agents;

final class RiskScoringAgent
{
    public function __construct(private readonly array $weights)
    {
    }

    public function analyze(array $agentResults): array
    {
        $score = 0;
        $contributions = [];
        $seen = [];
        foreach ($agentResults as $result) {
            foreach ($result->findings as $finding) {
                $code = $finding['code'];
                if (isset($seen[$code]) || !isset($this->weights[$code])) {
                    continue;
                }
                $seen[$code] = true;
                $points = (int) $this->weights[$code];
                $score += $points;
                $contributions[] = [
                    'code' => $code,
                    'label' => $finding['title'],
                    'points' => $points,
                    'agent' => $result->agent,
                ];
            }
        }
        $score = min(100, max(0, $score));

        return [
            'agent' => 'Risk Scoring Agent',
            'status' => 'complete',
            'score' => $score,
            'level' => $this->level($score),
            'contributions' => $contributions,
            'method' => 'Deterministic rule weights, capped at 100.',
        ];
    }

    public function level(int $score): string
    {
        return match (true) {
            $score >= 75 => 'Critical Risk',
            $score >= 50 => 'High Risk',
            $score >= 25 => 'Moderate Risk',
            default => 'Low Observed Risk',
        };
    }
}
