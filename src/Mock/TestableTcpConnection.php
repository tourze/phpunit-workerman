<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Mock;

use Workerman\Connection\TcpConnection;

/**
 * 可测试的 TCP 连接类
 *
 * 继承真实的 TcpConnection 但重写 send 方法来追踪发送的数据
 */
class TestableTcpConnection extends TcpConnection
{
    /** @var array<int, mixed> 已发送的数据 */
    public $_sentData = [];

    /**
     * 发送数据
     *
     * @param mixed $data 要发送的数据
     * @param bool  $raw  是否原始发送
     */
    public function send($data, bool $raw = false): ?bool
    {
        // 记录发送的数据
        $this->_sentData[] = $data;

        // 模拟发送成功
        return true;
    }

    /**
     * 获取已发送的数据
     *
     * @return array<int, mixed>
     */
    public function getSentData(): array
    {
        return $this->_sentData;
    }
}
