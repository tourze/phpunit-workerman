<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Utility;

use Tourze\PHPUnitWorkerman\Exception\InvalidSocketArgumentException;
use Tourze\PHPUnitWorkerman\Exception\SocketException;

/**
 * Socket 对工具类
 *
 * 提供用于测试的 Socket 对创建和管理功能
 */
class SocketPair
{
    /** @var resource|null 客户端 socket */
    private $clientSocket;

    /** @var resource|null 服务端 socket */
    private $serverSocket;

    /** @var bool 是否已连接 */
    private bool $connected = false;

    /**
     * 创建 Socket 对
     *
     * @param int $domain   协议族
     * @param int $type     套接字类型
     * @param int $protocol 协议
     *
     * @throws SocketException 创建失败时抛出异常
     */
    public function __construct(
        int $domain = STREAM_PF_UNIX,
        int $type = STREAM_SOCK_STREAM,
        int $protocol = STREAM_IPPROTO_IP,
    ) {
        $sockets = stream_socket_pair($domain, $type, $protocol);

        if (false === $sockets) {
            throw new SocketException('无法创建 Socket 对：' . (error_get_last()['message'] ?? '未知错误'));
        }

        $this->clientSocket = $sockets[0];
        $this->serverSocket = $sockets[1];
        $this->connected = true;

        // 设置为非阻塞模式
        stream_set_blocking($this->clientSocket, false);
        stream_set_blocking($this->serverSocket, false);
    }

    /**
     * 创建 TCP Socket 对
     */
    public static function createTcp(): self
    {
        return new self(STREAM_PF_INET, STREAM_SOCK_STREAM, STREAM_IPPROTO_TCP);
    }

    /**
     * 创建 UDP Socket 对
     */
    public static function createUdp(): self
    {
        return new self(STREAM_PF_INET, STREAM_SOCK_DGRAM, STREAM_IPPROTO_UDP);
    }

    /**
     * 创建 Unix Socket 对
     */
    public static function createUnix(): self
    {
        return new self(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    }

    /**
     * 获取客户端 socket
     *
     * @return resource
     * @throws SocketException 当socket为空时抛出异常
     */
    public function getClientSocket()
    {
        if (null === $this->clientSocket) {
            throw new SocketException('客户端 socket 尚未初始化');
        }

        return $this->clientSocket;
    }

    /**
     * 获取服务端 socket
     *
     * @return resource
     * @throws SocketException 当socket为空时抛出异常
     */
    public function getServerSocket()
    {
        if (null === $this->serverSocket) {
            throw new SocketException('服务端 socket 尚未初始化');
        }

        return $this->serverSocket;
    }

    /**
     * 从客户端向服务端发送数据
     *
     * @param string $data 要发送的数据
     *
     * @return int 发送的字节数
     */
    public function sendFromClient(string $data): int
    {
        $this->ensureConnected();
        $clientSocket = $this->getClientSocket();

        $bytes = fwrite($clientSocket, $data);
        if (false === $bytes) {
            throw new SocketException('客户端发送数据失败');
        }

        return $bytes;
    }

    /**
     * 确保连接有效
     *
     * @throws SocketException 连接无效时抛出异常
     */
    private function ensureConnected(): void
    {
        if (!$this->isConnected()) {
            throw new SocketException('Socket 对未连接或已关闭');
        }
    }

    /**
     * 检查是否已连接
     */
    public function isConnected(): bool
    {
        return $this->connected
               && is_resource($this->clientSocket)
               && is_resource($this->serverSocket);
    }

    /**
     * 从服务端向客户端发送数据
     *
     * @param string $data 要发送的数据
     *
     * @return int 发送的字节数
     */
    public function sendFromServer(string $data): int
    {
        $this->ensureConnected();
        $serverSocket = $this->getServerSocket();

        $bytes = fwrite($serverSocket, $data);
        if (false === $bytes) {
            throw new SocketException('服务端发送数据失败');
        }

        return $bytes;
    }

    /**
     * 从服务端读取数据
     *
     * @param int $length 读取长度
     *
     * @return string 读取的数据
     */
    public function readFromServer(int $length = 1024): string
    {
        $this->ensureConnected();
        $serverSocket = $this->getServerSocket();

        if ($length <= 0) {
            throw new InvalidSocketArgumentException('读取长度必须大于0');
        }

        $data = fread($serverSocket, $length);

        return false === $data ? '' : $data;
    }

    /**
     * 从客户端读取数据
     *
     * @param int $length 读取长度
     *
     * @return string 读取的数据
     */
    public function readFromClient(int $length = 1024): string
    {
        $this->ensureConnected();
        $clientSocket = $this->getClientSocket();

        if ($length <= 0) {
            throw new InvalidSocketArgumentException('读取长度必须大于0');
        }

        $data = fread($clientSocket, $length);

        return false === $data ? '' : $data;
    }

    /**
     * 等待数据可读
     *
     * @param resource $socket  要检查的 socket
     * @param float    $timeout 超时时间（秒）
     *
     * @return bool 是否有数据可读
     */
    public function waitForReadable($socket, float $timeout = 1.0): bool
    {
        $read = [$socket];
        $write = null;
        $except = null;

        $result = stream_select($read, $write, $except, (int) $timeout, (int) (($timeout - (int) $timeout) * 1000000));

        return $result > 0;
    }

    /**
     * 等待数据可写
     *
     * @param resource $socket  要检查的 socket
     * @param float    $timeout 超时时间（秒）
     *
     * @return bool 是否可写
     */
    public function waitForWritable($socket, float $timeout = 1.0): bool
    {
        $read = null;
        $write = [$socket];
        $except = null;

        $result = stream_select($read, $write, $except, (int) $timeout, (int) (($timeout - (int) $timeout) * 1000000));

        return $result > 0;
    }

    /**
     * 设置 socket 选项
     *
     * @param \Socket               $socket  socket 句柄
     * @param int                    $level   选项级别
     * @param int                    $optname 选项名
     * @param array<mixed>|int|string $optval  选项值
     */
    public function setSocketOption(\Socket $socket, int $level, int $optname, $optval): void
    {
        if (!socket_set_option($socket, $level, $optname, $optval)) {
            throw new SocketException('设置 socket 选项失败');
        }
    }

    /**
     * 获取 socket 信息
     *
     * @param resource $socket socket 资源
     *
     * @return array<string, mixed> socket 信息
     */
    public function getSocketInfo($socket): array
    {
        $info = [];

        if (is_resource($socket)) {
            $meta = stream_get_meta_data($socket);
            $info['meta'] = $meta;

            // 尝试获取对端地址
            $peerName = stream_socket_get_name($socket, true);
            $sockName = stream_socket_get_name($socket, false);

            $info['peer_name'] = $peerName;
            $info['sock_name'] = $sockName;
            $info['resource_type'] = get_resource_type($socket);
        }

        return $info;
    }

    /**
     * 模拟网络延迟
     *
     * @param float $delay 延迟时间（秒）
     */
    public function simulateDelay(float $delay): void
    {
        usleep((int) ($delay * 1000000));
    }

    /**
     * 模拟网络错误
     *
     * @param string $type 错误类型
     */
    public function simulateError(string $type = 'connection_reset'): void
    {
        switch ($type) {
            case 'connection_reset':
                $this->close();
                break;

            case 'timeout':
                // 模拟超时，不发送/接收数据
                break;

            case 'partial_write':
                // 模拟部分写入
                break;

            default:
                throw new InvalidSocketArgumentException("未知的错误类型：{$type}");
        }
    }

    /**
     * 关闭连接
     */
    public function close(): void
    {
        if (is_resource($this->clientSocket)) {
            fclose($this->clientSocket);
            $this->clientSocket = null;
        }

        if (is_resource($this->serverSocket)) {
            fclose($this->serverSocket);
            $this->serverSocket = null;
        }

        $this->connected = false;
    }

    /**
     * 获取缓冲区状态
     *
     * @param resource $socket socket 资源
     *
     * @return array<string, mixed> 缓冲区状态
     */
    public function getBufferStatus($socket): array
    {
        $status = [
            'readable' => false,
            'writable' => false,
            'has_data' => false,
        ];

        if (!is_resource($socket)) {
            return $status;
        }

        // 检查是否可读
        $read = [$socket];
        $write = $except = null;
        $result = stream_select($read, $write, $except, 0, 0);
        $status['readable'] = $result > 0;

        // 检查是否可写
        $read = $except = null;
        $write = [$socket];
        $result = stream_select($read, $write, $except, 0, 0);
        $status['writable'] = $result > 0;

        // 检查是否有数据
        if ($status['readable']) {
            $data = fread($socket, 1);
            if (false !== $data && '' !== $data) {
                $status['has_data'] = true;
                // 将数据放回去（如果可能）
                // 注意：这在实际中是不可能的，这里只是模拟
            }
        }

        return $status;
    }

    /**
     * 析构函数，自动关闭连接
     */
    public function __destruct()
    {
        $this->close();
    }
}
