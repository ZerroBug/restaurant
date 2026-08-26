<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once "../includes/db_connection.php";

if (
    !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true ||
    !isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrator'
) {
    // Return JSON instead of a 403 HTML error page so the dashboard can
    // display a friendly permission message to the user.
    echo json_encode([
        'success' => false,
        'permission_denied' => true,
        'message' => 'You do not have permission to delete orders. Only Administrators can delete orders.'
    ]);
    exit;
}

try {
    $input=json_decode(file_get_contents('php://input'),true);
    $orderId=(int)($input['order_id'] ?? 0);
    if ($orderId<=0) throw new RuntimeException('Invalid order ID.');

    $pdo->beginTransaction();

    $q=$pdo->prepare("SELECT id, order_number FROM orders WHERE id=:id LIMIT 1");
    $q->execute([':id'=>$orderId]);
    $order=$q->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new RuntimeException('Order not found.');

    // Delete child records first to satisfy foreign-key constraints.
    $q=$pdo->prepare("DELETE FROM payments WHERE order_id=:id");
    $q->execute([':id'=>$orderId]);

    $q=$pdo->prepare("DELETE FROM order_items WHERE order_id=:id");
    $q->execute([':id'=>$orderId]);

    $q=$pdo->prepare("DELETE FROM orders WHERE id=:id LIMIT 1");
    $q->execute([':id'=>$orderId]);

    if ($q->rowCount()!==1) throw new RuntimeException('Order could not be deleted.');

    $pdo->commit();
    echo json_encode([
        'success'=>true,
        'message'=>'Order deleted successfully.',
        'order_id'=>$orderId,
        'order_number'=>$order['order_number']
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}