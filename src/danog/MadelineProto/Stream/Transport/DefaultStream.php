<?php

declare(strict_types=1);

/**
 * Default stream wrapper.
 *
 * This file is part of MadelineProto.
 * MadelineProto is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * MadelineProto is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU General Public License along with MadelineProto.
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Daniil Gentili <daniil@daniil.it>
 * @copyright 2016-2020 Daniil Gentili <daniil@daniil.it>
 * @license   https://opensource.org/licenses/AGPL-3.0 AGPLv3
 * @link https://docs.madelineproto.xyz MadelineProto documentation
 */

namespace danog\MadelineProto\Stream\Transport;

use Amp\ByteStream\ClosedException;
use Amp\Cancellation;
use Amp\CancellationToken;
use Amp\Future;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\Connector;
use Amp\Socket\EncryptableSocket;
use Amp\Socket\SocketConnector;
use Amp\Success;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Stream\Async\RawStream;
use danog\MadelineProto\Stream\ConnectionContext;
use danog\MadelineProto\Stream\ProxyStreamInterface;
use danog\MadelineProto\Stream\RawStreamInterface;
use Generator;
use Throwable;

use function Amp\Socket\connector;

/**
 * Default stream wrapper.
 *
 * Manages reading data in chunks
 *
 * @author Daniil Gentili <daniil@daniil.it>
 */
class DefaultStream implements RawStreamInterface, ProxyStreamInterface
{
    use RawStream;
    /**
     * Socket.
     *
     * @var ?EncryptableSocket
     */
    protected ?EncryptableSocket $stream = null;
    /**
     * Connector.
     *
     * @var Connector
     */
    private SocketConnector $connector;
    public function setupTls(?Cancellation $cancellationToken = null): void
    {
        return $this->stream->setupTls($cancellationToken);
    }
    public function getStream(): EncryptableSocket
    {
        return $this->stream;
    }
    public function connect(ConnectionContext $ctx, string $header = ''): void
    {
        $ctx = $ctx->getCtx();
        $uri = $ctx->getUri();
        $secure = $ctx->isSecure();
        if ($secure) {
            $ctx->setSocketContext($ctx->getSocketContext()->withTlsContext(new ClientTlsContext($uri->getHost())));
        }
        $this->stream = (($this->connector ?? connector())->connect((string) $uri, $ctx->getSocketContext(), $ctx->getCancellationToken()));
        if ($secure) {
            $this->stream->setupTls();
        }
        $this->stream->write($header);
    }
    /**
     * Async chunked read.
     */
    public function read(?Cancellation $cancellation = null): ?string
    {
        return $this->stream ? $this->stream->read($cancellation) : null;
    }
    /**
     * Async write.
     *
     * @param string $data Data to write
     */
    public function write(string $data): void
    {
        if (!$this->stream) {
            throw new ClosedException("MadelineProto stream was disconnected");
        }
        $this->stream->write($data);
    }
    /**
     * Close.
     */
    public function disconnect(): void
    {
        try {
            if ($this->stream) {
                $this->stream->close();
                $this->stream = null;
            }
        } catch (Throwable $e) {
            Logger::log('Got exception while closing stream: '.$e->getMessage());
        }
    }
    /**
     * Close.
     */
    public function close(): void
    {
        $this->disconnect();
    }
    /**
     * {@inheritdoc}
     */
    public function getSocket(): EncryptableSocket
    {
        return $this->stream;
    }
    public function setExtra($extra): void
    {
        $this->connector = $extra;
    }
    public static function getName(): string
    {
        return __CLASS__;
    }
}
