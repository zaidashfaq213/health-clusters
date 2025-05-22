```php
<?php
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use Twilio\Rest\Client;

class NotificationService {
    private $conn;
    private $env;

    public function __construct($conn, $env) {
        $this->conn = $conn;
        $this->env = $env;
    }

    public function getManagerEmails($hospital_id, $health_center_id) {
        $emails = [];
        $query = $hospital_id 
            ? "SELECT email FROM users WHERE hospital_id = ? AND role = 'manager'"
            : "SELECT email FROM users WHERE health_center_id = ? AND role = 'manager'";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("NotificationService: Prepare failed for manager emails: " . $this->conn->error);
            return $emails;
        }
        $id = $hospital_id ?: $health_center_id;
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $emails[] = $row['email'];
            }
        } else {
            error_log("NotificationService: Execute failed for manager emails: " . $stmt->error);
        }
        $stmt->close();
        return $emails;
    }

    public function sendEmail($emails, $subject, $message) {
        if (empty($emails)) {
            error_log("NotificationService: No emails provided for sending");
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->env['SMTP_HOST'] ?? 'smtp.example.com';
            $mail->SMTPAuth = true;
            $mail->Username = $this->env['SMTP_USERNAME'] ?? 'your_smtp_username';
            $mail->Password = $this->env['SMTP_PASSWORD'] ?? 'your_smtp_password';
            $mail->SMTPSecure = 'tls';
            $mail->Port = $this->env['SMTP_PORT'] ?? 587;
            $mail->setFrom($this->env['EMAIL_FROM'] ?? 'no-reply@healthcluster.com', 'Health Cluster');
            foreach ($emails as $email) {
                $mail->addAddress($email);
            }
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->send();
            error_log("NotificationService: Email sent successfully to " . implode(', ', $emails));
            return true;
        } catch (Exception $e) {
            error_log("NotificationService: Failed to send email: {$mail->ErrorInfo}");
            return false;
        }
    }

    public function sendSMS($phone_numbers, $message) {
        if (empty($phone_numbers)) {
            error_log("NotificationService: No phone numbers provided for SMS");
            return false;
        }

        try {
            $client = new \Twilio\Rest\Client(
                $this->env['TWILIO_SID'] ?? 'your_twilio_sid',
                $this->env['TWILIO_TOKEN'] ?? 'your_twilio_token'
            );
            foreach ($phone_numbers as $phone) {
                $client->messages->create(
                    $phone,
                    [
                        'from' => $this->env['TWILIO_NUMBER'] ?? '+1234567890',
                        'body' => $message
                    ]
                );
            }
            error_log("NotificationService: SMS sent successfully to " . implode(', ', $phone_numbers));
            return true;
        } catch (Exception $e) {
            error_log("NotificationService: Failed to send SMS: {$e->getMessage()}");
            return false;
        }
    }

    public function createNotification($type, $extinguisher_id, $alarm_id, $hospital_id, $health_center_id, $message, $due_date = null) {
        $stmt = $this->conn->prepare(
            "INSERT INTO notifications (type, extinguisher_id, alarm_id, hospital_id, health_center_id, message, due_date, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')"
        );
        if (!$stmt) {
            error_log("NotificationService: Prepare failed for notification insert: " . $this->conn->error);
            return false;
        }
        $stmt->bind_param(
            "siiisss",
            $type,
            $extinguisher_id,
            $alarm_id,
            $hospital_id,
            $health_center_id,
            $message,
            $due_date
        );
        if ($stmt->execute()) {
            $notification_id = $this->conn->insert_id;
            error_log("NotificationService: Notification created, id=$notification_id, type=$type");
            return $notification_id;
        } else {
            error_log("NotificationService: Execute failed for notification insert: " . $stmt->error);
            return false;
        }
    }

    public function updateNotificationStatus($notification_id, $status) {
        $stmt = $this->conn->prepare("UPDATE notifications SET status = ? WHERE id = ?");
        if (!$stmt) {
            error_log("NotificationService: Prepare failed for notification update: " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("si", $status, $notification_id);
        if ($stmt->execute()) {
            error_log("NotificationService: Notification status updated, id=$notification_id, status=$status");
            return true;
        } else {
            error_log("NotificationService: Execute failed for notification update: " . $stmt->error);
            return false;
        }
    }
}
?>
```