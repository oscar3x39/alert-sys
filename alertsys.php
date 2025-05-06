<?php

declare(strict_types=1);

class LogMonitor
{
    private readonly array $config;
    private array $currentStats = [];

    public function __construct(array $config)
    {
        $this->validateConfig($config);
        $this->config = $config;
    }

    public function run(): void
    {
        $this->scanLogFiles();

        if (!file_exists($this->config['STATUS_FILE'])) {
            $this->initStatusFile();
            return;
        }

        $this->processChanges();
        $this->saveStatus();
    }

    private function validateConfig(array $config): void
    {
        $required = ['LOG_PATH', 'STATUS_FILE', 'SLACK_CHANNEL'];
        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw new InvalidArgumentException("缺少設定參數：$key");
            }
        }
        if (!is_dir($config['LOG_PATH'])) {
            throw new InvalidArgumentException("LOG_PATH 不是有效目錄");
        }
    }

    private function scanLogFiles(): void
    {
        $logFiles = glob($this->config['LOG_PATH'] . '/*.log');
        foreach ($logFiles as $filename) {
            $this->currentStats[$filename] = [
                'line' => $this->countLines($filename),
                'size' => filesize($filename)
            ];
        }
    }

    private function countLines(string $filename): int
    {
        $file = fopen($filename, 'r');
        $lines = 0;
        while (!feof($file)) {
            $lines += substr_count(fread($file, 8192), "\n");
        }
        fclose($file);
        return $lines;
    }

    private function initStatusFile(): void
    {
        file_put_contents($this->config['STATUS_FILE'], serialize($this->currentStats));
        echo "初始化狀態檔完成，已退出。" . PHP_EOL;
    }

    private function loadPreviousStats(): array
    {
        $content = @file_get_contents($this->config['STATUS_FILE']);
        if ($content === false) {
            throw new RuntimeException("無法讀取狀態檔");
        }
        $data = @unserialize($content);
        if (!is_array($data)) {
            throw new RuntimeException("狀態檔格式錯誤");
        }
        return $data;
    }

    private function processChanges(): void
    {
        $pastStats = $this->loadPreviousStats();

        foreach ($this->currentStats as $filename => $status) {
            echo "檢查是否有新日誌..." . PHP_EOL;
            $past = $pastStats[$filename] ?? ['size' => 0];
            if ($status['size'] > ($past['size'] ?? 0)) {
                $newContent = $this->getNewContent($filename, $past['size'] ?? 0);
                if ($newContent !== '') {
                    echo "發送 Slack 訊息..." . PHP_EOL;
                    $this->sendToSlack($newContent);
                }
            } else {
                echo "沒有新訊息。" . PHP_EOL;
            }
        }
    }

    private function getNewContent(string $filename, int $offset): string
    {
        $fp = fopen($filename, 'r');
        if ($fp === false) {
            return '';
        }
        fseek($fp, $offset);
        $content = stream_get_contents($fp);
        fclose($fp);
        return $content ?: '';
    }

    private function saveStatus(): void
    {
        file_put_contents($this->config['STATUS_FILE'], serialize($this->currentStats));
    }

    private function sendToSlack(string $message): void
    {
        $payload = [
            'payload' => json_encode([
                'text' => "``````",
            ])
        ];
        $ch = curl_init("https://hooks.slack.com/services/{$this->config['SLACK_CHANNEL']}");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $result = curl_exec($ch);
        if ($result === false) {
            error_log("Slack 傳送失敗：" . curl_error($ch));
        }
        curl_close($ch);
    }
}

// 主程式入口
try {
    $ini = parse_ini_file('./alertsys.ini');
    if ($ini === false) {
        throw new RuntimeException("無法讀取設定檔");
    }
    $monitor = new LogMonitor($ini);
    $monitor->run();
} catch (Throwable $e) {
    error_log("[CRITICAL] LogMonitor 失敗：" . $e->getMessage());
    exit(1);
}
