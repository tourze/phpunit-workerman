<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Examples;

use PHPUnit\Framework\Attributes\CoversClass;
use Workerman\Connection\TcpConnection;
use Workerman\Worker;

/**
 * 自定义测试Worker类
 */
#[CoversClass(Worker::class)]
class TestCustomWorker extends Worker
{
    /** @var array<int, array{connection: TcpConnection, login_time: int, authenticated: bool}> */
    private array $users = [];

    private int $messageCount = 0;

    public function __construct()
    {
        parent::__construct('tcp://127.0.0.1:8080');

        $this->setupEventHandlers();
    }

    private function setupEventHandlers(): void
    {
        $this->onConnect = function (TcpConnection $connection): void { $this->handleConnect($connection); };
        $this->onMessage = function (TcpConnection $connection, mixed $data): void { $this->handleMessage($connection, $data); };
        $this->onClose = function (TcpConnection $connection): void { $this->handleClose($connection); };
    }

    public function handleConnect(TcpConnection $connection): void
    {
        $this->users[$connection->id] = $this->createUserRecord($connection);
        $this->sendAuthenticationPrompt($connection);
    }

    /**
     * @return array{connection: TcpConnection, login_time: int, authenticated: bool}
     */
    private function createUserRecord(TcpConnection $connection): array
    {
        return [
            'connection' => $connection,
            'login_time' => time(),
            'authenticated' => false,
        ];
    }

    private function sendAuthenticationPrompt(TcpConnection $connection): void
    {
        $connection->send('Please authenticate: {"action": "login", "token": "your_token"}');
    }

    public function handleMessage(TcpConnection $connection, mixed $data): void
    {
        ++$this->messageCount;
        $message = $this->parseMessage($data);
        if (null === $message) {
            return;
        }

        $this->routeMessage($connection, $message);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseMessage(mixed $data): ?array
    {
        if (!\is_string($data)) {
            return null;
        }

        $decodedMessage = json_decode($data, true);
        if (!\is_array($decodedMessage)) {
            return null;
        }

        if (!$this->validateMessageKeys($decodedMessage)) {
            return null;
        }

        /** @var array<string, mixed> */
        return $decodedMessage;
    }

    /**
     * @param mixed[] $decodedMessage
     */
    private function validateMessageKeys(array $decodedMessage): bool
    {
        foreach (array_keys($decodedMessage) as $key) {
            if (!\is_string($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $message
     */
    private function routeMessage(TcpConnection $connection, array $message): void
    {
        if (!$this->isUserAuthenticated($connection->id)) {
            $this->handleAuthentication($connection, $message);

            return;
        }

        $this->handleAuthenticatedMessage($connection, $message);
    }

    private function isUserAuthenticated(int $connectionId): bool
    {
        return $this->users[$connectionId]['authenticated'] ?? false;
    }

    /**
     * @param array<string, mixed> $message
     */
    private function handleAuthentication(TcpConnection $connection, array $message): void
    {
        if ($this->isValidAuthentication($message)) {
            $this->authenticateUser($connection);
            $connection->send('Authentication successful');
        } else {
            $connection->send('Authentication failed');
            $connection->close();
        }
    }

    /**
     * @param array<string, mixed> $message
     */
    private function isValidAuthentication(array $message): bool
    {
        $action = $message['action'] ?? null;
        $token = $message['token'] ?? null;

        return 'login' === $action && 'valid_token' === $token;
    }

    private function authenticateUser(TcpConnection $connection): void
    {
        $this->users[$connection->id]['authenticated'] = true;
    }

    /**
     * @param array<string, mixed> $message
     */
    private function handleAuthenticatedMessage(TcpConnection $connection, array $message): void
    {
        $action = $message['action'] ?? null;
        if (!\is_string($action)) {
            return;
        }

        $this->executeAuthenticatedAction($connection, $action, $message);
    }

    /**
     * @param array<string, mixed> $message
     */
    private function executeAuthenticatedAction(TcpConnection $connection, string $action, array $message): void
    {
        match ($action) {
            'echo' => $this->handleEcho($connection, $message),
            'get_user_count' => $this->handleGetUserCount($connection),
            'get_message_count' => $this->handleGetMessageCount($connection),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $message
     */
    private function handleEcho(TcpConnection $connection, array $message): void
    {
        $text = $message['text'] ?? '';
        if (\is_string($text)) {
            $connection->send('Echo: ' . $text);
        }
    }

    private function handleGetUserCount(TcpConnection $connection): void
    {
        $authCount = $this->countAuthenticatedUsers();
        $connection->send("Authenticated users: {$authCount}");
    }

    private function countAuthenticatedUsers(): int
    {
        return count(array_filter($this->users, fn ($u) => $u['authenticated']));
    }

    private function handleGetMessageCount(TcpConnection $connection): void
    {
        $connection->send("Total messages: {$this->messageCount}");
    }

    public function handleClose(TcpConnection $connection): void
    {
        unset($this->users[$connection->id]);
    }

    /**
     * @return array<int, array{connection: TcpConnection, login_time: int, authenticated: bool}>
     */
    public function getUsers(): array
    {
        return $this->users;
    }

    public function getMessageCount(): int
    {
        return $this->messageCount;
    }
}