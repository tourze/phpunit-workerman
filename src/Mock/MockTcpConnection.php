<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Mock;

use Workerman\Connection\TcpConnection;

/**
 * 模拟 TCP 连接类
 *
 * 继承真实的 TcpConnection 但覆盖 send 方法来支持测试
 */
class MockTcpConnection extends TcpConnection
{
    /** @var callable|null 发送数据的回调 */
    public $sendCallback;

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
        if (null !== $this->sendCallback) {
            ($this->sendCallback)($data);
        }

        // 记录发送的数据
        $this->_sentData[] = $data;

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
