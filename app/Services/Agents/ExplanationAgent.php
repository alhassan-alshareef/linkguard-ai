<?php

namespace LinkGuard\Services\Agents;

final class ExplanationAgent
{
    public function explain(array $findings, array $limitations, array $risk, array $coverage = []): array
    {
        $recommendations = $risk['score'] >= 50
            ? [
                'Do not open the link or submit credentials.',
                'Verify the request through the organization’s official app or a manually typed address.',
                'Report the message to your security team or service provider.',
            ]
            : [
                'Confirm the sender and destination before opening the link.',
                'Use a bookmarked or manually typed official address for sensitive actions.',
                'Treat unexpected login, payment, or urgency requests with caution.',
            ];

        $incomplete = ($coverage['status'] ?? '') === 'Limited';
        $summary = match (true) {
            $risk['level'] === 'Critical Risk' => 'Multiple strong warning indicators were observed. Avoid interacting with this link.',
            $risk['level'] === 'High Risk' => 'Several meaningful warning indicators were observed. Independent verification is strongly recommended.',
            $risk['level'] === 'Moderate Risk' => 'Some warning indicators were observed. Verify the destination and sender before proceeding.',
            $incomplete => 'No structural warning indicators were found, but this assessment is incomplete: live reputation and page content were not inspected. Do not treat this as proof of safety.',
            default => 'No major warning indicators were found in the completed checks. This remains a risk assessment, not proof that the destination is safe.',
        };

        return [
            'agent' => 'Explanation Agent',
            'status' => 'complete',
            'summary' => $summary,
            'reasons' => array_map(static fn (array $finding): string => $finding['explanation'], $findings),
            'recommendations' => $recommendations,
            'limitations' => array_values(array_unique($limitations)),
            'boundary' => 'This explanation restates deterministic findings and does not alter the risk score.',
        ];
    }
}
