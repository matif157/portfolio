<?php
/**
 * SPS Professional Services CRM
 * Service Details View
 */
require_once __DIR__ . '/auth.php';
require_permission('services.view');

$service_id = intval($_GET['id'] ?? 1);
$stmt = $pdo->prepare("SELECT s.*, sc.name as category_name FROM services s LEFT JOIN service_categories sc ON s.category_id = sc.id WHERE s.id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if (!$service) {
    set_flash('danger', 'Service not found.');
    redirect('services.php');
}

redirect("service_workflow.php?service_id=$service_id");
