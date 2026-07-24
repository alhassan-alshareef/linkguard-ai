<?php

namespace LinkGuard\Models;

use PDO;
use RuntimeException;

final class AnalysisRepository
{
    private PDO $pdo;

    public function __construct(?string $databasePath = null)
    {
        $path = $databasePath ?? (string) config('app.database');
        if (
            !str_starts_with($path, ':')
            && !str_starts_with($path, '/')
            && !preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)
        ) {
            $path = BASE_PATH . '/' . ltrim($path, '/\\');
        }
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the database directory.');
        }
        $this->pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->initialize();
    }

    public function save(array $report): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO analyses
            (case_id, submitted_url, normalized_url, host, scheme, risk_score, risk_level, report_json, created_at)
            VALUES (:case_id, :submitted_url, :normalized_url, :host, :scheme, :risk_score, :risk_level, :report_json, :created_at)'
        );
        $statement->execute([
            ':case_id' => $report['case_id'],
            ':submitted_url' => $report['submitted_url'],
            ':normalized_url' => $report['url']['url'],
            ':host' => $report['url']['host'],
            ':scheme' => $report['url']['scheme'],
            ':risk_score' => $report['risk']['score'],
            ':risk_level' => $report['risk']['level'],
            ':report_json' => json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ':created_at' => $report['created_at'],
        ]);
    }

    public function find(string $caseId): ?array
    {
        $statement = $this->pdo->prepare('SELECT report_json FROM analyses WHERE case_id = :case_id LIMIT 1');
        $statement->execute([':case_id' => $caseId]);
        $raw = $statement->fetchColumn();
        return is_string($raw) ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : null;
    }

    public function search(string $query = ''): array
    {
        if ($query === '') {
            $statement = $this->pdo->query(
                'SELECT case_id, submitted_url, host, scheme, risk_score, risk_level, created_at
                 FROM analyses ORDER BY created_at DESC LIMIT 100'
            );
            return $statement->fetchAll();
        }
        $statement = $this->pdo->prepare(
            'SELECT case_id, submitted_url, host, scheme, risk_score, risk_level, created_at
             FROM analyses
             WHERE submitted_url LIKE :query OR host LIKE :query OR risk_level LIKE :query OR case_id LIKE :query
             ORDER BY created_at DESC LIMIT 100'
        );
        $statement->execute([':query' => '%' . $query . '%']);
        return $statement->fetchAll();
    }

    public function delete(string $caseId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM analyses WHERE case_id = :case_id');
        $statement->execute([':case_id' => $caseId]);
        return $statement->rowCount() > 0;
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM analyses')->fetchColumn();
    }

    private function initialize(): void
    {
        $schema = file_get_contents(BASE_PATH . '/database/schema.sql');
        if ($schema === false) {
            throw new RuntimeException('Database schema is missing.');
        }
        $this->pdo->exec($schema);
    }
}
