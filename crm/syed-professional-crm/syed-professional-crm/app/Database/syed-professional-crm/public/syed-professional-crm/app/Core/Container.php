<?php
/**
 * =====================================================
 * FILE: Container.php
 * PURPOSE: CRM ki shared services ko central jagah par manage karna
 *
 * WORKFLOW:
 * 1. Application kisi service ko Container mein register karti hai.
 * 2. Module us service ko Container se request karta hai.
 * 3. Same service ko controlled way mein reuse kiya jata hai.
 *
 * EXAMPLE:
 *
 * Database
 * Logger
 * ErrorHandler
 * Configuration
 * Future:
 * Mailer
 * Cache
 * PermissionService
 * AutomationEngine
 *
 * =====================================================
 */

declare(strict_types=1);


/**
 * Container
 *
 * Bhai isay CRM ka "service cupboard" samjho 😄
 *
 * Har important shared service ek jagah register hogi.
 *
 * Example:
 *
 * $container->set('db', $pdo);
 *
 * Phir:
 *
 * $pdo = $container->get('db');
 *
 * Is se har file apna separate connection create nahi karegi.
 */
class Container
{
    /**
     * Registered services yahan store hongi.
     *
     * array<string, mixed>
     */
    private array $services = [];


    /**
     * Service Container mein register karna.
     *
     * @param string $name Service ka unique naam
     * @param mixed $service Actual service/object/value
     */
    public function set(
        string $name,
        mixed $service
    ): void {

        /*
         * Service ko naam ke against store kar rahe hain.
         *
         * Example:
         *
         * 'db' => PDO object
         */
        $this->services[$name] = $service;
    }


    /**
     * Container se service retrieve karna.
     *
     * @param string $name Requested service name
     * @return mixed Registered service
     */
    public function get(
        string $name
    ): mixed {

        /*
         * array_key_exists() = PHP built-in function.
         *
         * Check karta hai ke requested service
         * container mein registered hai ya nahi.
         */
        if (!array_key_exists(
            $name,
            $this->services
        )) {

            /*
             * Agar service registered nahi hai
             * to silent failure nahi karni.
             *
             * Clear exception developer ko root cause batayegi.
             */
            throw new RuntimeException(
                "Container service not found: {$name}"
            );
        }

        /*
         * Registered service return.
         */
        return $this->services[$name];
    }


    /**
     * Check karna ke service registered hai ya nahi.
     */
    public function has(
        string $name
    ): bool {

        /*
         * array_key_exists() = PHP built-in function.
         *
         * True:
         * service available
         *
         * False:
         * service unavailable
         */
        return array_key_exists(
            $name,
            $this->services
        );
    }
}