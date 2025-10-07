<?php
declare(strict_types=1);
namespace AntonBrutto\McpTransportStreamHttp;
use AntonBrutto\McpCore\TransportInterface;
use AntonBrutto\McpCore\JsonRpcMessage;
use AntonBrutto\McpCodecJson\JsonCodec;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
final class StreamHttpTransport implements TransportInterface {
    public function __construct(
        private readonly string $endpoint,
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $reqFactory
    ) {}
    public function open(): void {}
    public function send(JsonRpcMessage $msg): void {
        $req = $this->reqFactory->createRequest('POST', $this->endpoint)
            ->withHeader('Content-Type', 'application/json');
        $req->getBody()->write(JsonCodec::encode($msg));
        $this->http->sendRequest($req);
    }
    public function incoming(): iterable {
        if (false) { yield new JsonRpcMessage(null, 'noop'); }
        return;
    }
    public function close(): void {}
}
