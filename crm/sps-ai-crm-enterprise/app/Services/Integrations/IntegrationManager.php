<?php
namespace App\Services\Integrations;
final class IntegrationManager { public function get(string $name):NotConfiguredIntegration{return new NotConfiguredIntegration($name);} }
