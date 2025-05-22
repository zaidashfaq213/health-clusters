<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
require(__DIR__) . './vendor/autoload.php';
require_once './includes/config.php';

class Chat implements MessageComponentInterface {
    protected $clients;
    protected $conn;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        global $conn;
        $this->conn = $conn;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (!$data || !isset($data['sender_id'], $data['receiver_id'], $data['content'])) {
            return;
        }

        $sender_id = (int)$data['sender_id'];
        $receiver_id = (int)$data['receiver_id'];
        $content = trim($data['content']);
        $hospital_id = isset($data['hospital_id']) ? (int)$data['hospital_id'] : null;

        if (empty($content)) {
            return;
        }

        // Store message in database
        $stmt = $this->conn->prepare("INSERT INTO messages (sender_id, receiver_id, content, hospital_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisi", $sender_id, $receiver_id, $content, $hospital_id);
        if ($stmt->execute()) {
            $message_id = $this->conn->insert_id;
            $created_at = date('Y-m-d H:i:s');
            $response = [
                'message_id' => $message_id,
                'sender_id' => $sender_id,
                'receiver_id' => $receiver_id,
                'content' => $content,
                'created_at' => $created_at,
                'is_read' => 0
            ];

            // Broadcast message to relevant clients
            foreach ($this->clients as $client) {
                $client->send(json_encode($response));
            }
        }
        $stmt->close();
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection closed! ({$conn->resourceId})\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

$server = \Ratchet\Server\IoServer::factory(
    new \Ratchet\Http\HttpServer(
        new \Ratchet\WebSocket\WsServer(
            new Chat()
        )
    ),
    8080
);
$server->run();